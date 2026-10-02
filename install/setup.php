<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
[$script, $root, $database] = $argv;
if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $database)) { throw new RuntimeException('Invalid database name.'); }
$fields = explode("\0", stream_get_contents(STDIN));
if (count($fields) !== 9) { throw new RuntimeException('Missing provider configuration.'); }
[$name, $email, $password, $providerList, $defaultProvider, $ollamaUrl, $llamaUrl, $llamaBinary, $llamaModels] = $fields;
$providers = explode(',', $providerList);
if (array_diff($providers, ['ollama','llamacpp']) || count(array_unique($providers)) !== count($providers) || !in_array($defaultProvider, $providers, true)) {
    throw new RuntimeException('Invalid provider selection.');
}
foreach (['ollama'=>$ollamaUrl, 'llamacpp'=>$llamaUrl] as $provider=>$url) {
    if (!in_array($provider, $providers, true)) { continue; }
    $parts = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http','https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
        throw new RuntimeException('Invalid provider URL.');
    }
}
if (in_array('llamacpp', $providers, true) && (!str_starts_with($llamaBinary, '/') || !str_starts_with($llamaModels, '/') || !preg_match('~^http://127\.0\.0\.1:([0-9]{1,5})$~', $llamaUrl, $portMatch) || (int)$portMatch[1] < 1024 || (int)$portMatch[1] > 65535)) {
    throw new RuntimeException('Invalid local llama.cpp configuration.');
}
if ($name === '' || mb_strlen($name) > 32 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 64 || strlen($password) < 8) {
    throw new RuntimeException('Invalid login details.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '');
$db->set_charset('utf8mb4');
$exists = $db->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$database'")->num_rows;
$userExists = $db->query("SELECT User FROM mysql.user WHERE User='$database'")->num_rows;
if ($exists || $userExists || file_exists($root.'/config/config.php')) { throw new RuntimeException('Installation already exists.'); }
$dbPassword = bin2hex(random_bytes(24));
$db->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->select_db($database);
$db->multi_query(file_get_contents(__DIR__.'/schema.sql'));
do {
    if ($result = $db->store_result()) { $result->free(); }
} while ($db->more_results() && $db->next_result());
$stmt = $db->prepare("INSERT INTO users (name,email,pass,role) VALUES (?,?,?,'admin')");
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt->bind_param('sss', $name, $email, $hash);
$stmt->execute();
$stmt = $db->prepare('UPDATE settings SET datavalue=? WHERE type=? AND datakey=?');
$settings = [
    ['system','providers',$providerList],
    ['system','provider',$defaultProvider],
    ['system','multi_provider',count($providers) > 1 ? '1' : '0'],
    ['llamacpp','pid_file',$root.'/var/run/llama.pid'],
    ['llamacpp','log_file',$root.'/var/log/llama.log'],
];
if (in_array('ollama', $providers, true)) { $settings[] = ['ollama','url',$ollamaUrl]; }
if (in_array('llamacpp', $providers, true)) {
    $settings = array_merge($settings, [
        ['llamacpp','url',$llamaUrl], ['llamacpp','binary',$llamaBinary],
        ['llamacpp','model_dir',$llamaModels], ['llamacpp','mode','direct'],
    ]);
}
foreach ($settings as [$type,$key,$value]) {
    $stmt->bind_param('sss', $value, $type, $key);
    $stmt->execute();
}
$db->query("CREATE USER '$database'@'localhost' IDENTIFIED BY '$dbPassword'");
$db->query("GRANT SELECT, INSERT, UPDATE, DELETE ON `$database`.* TO '$database'@'localhost'");
$config = "<?php\ndeclare(strict_types=1);\ndate_default_timezone_set('Europe/Budapest');\n";
foreach (['DB_HOST'=>'localhost','DB_USER'=>$database,'DB_PASS'=>$dbPassword,'DB_NAME'=>$database,'DB_CHARSET'=>'utf8mb4','PROVIDERS'=>['llamacpp','ollama']] as $key=>$value) {
    $config .= 'define('.var_export($key,true).', '.var_export($value,true).");\n";
}
if (file_put_contents($root.'/config/config.php', $config, LOCK_EX) === false) { throw new RuntimeException('Cannot write configuration.'); }
chmod($root.'/config/config.php', 0640);
echo "Adatbázis, kezdőbeállítások és első felhasználó létrehozva.\n";
