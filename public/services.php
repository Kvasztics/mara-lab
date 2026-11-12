<?php
// Szolgáltatások listája
$services = [
    'sd-forge' => 'Stable Diffusion Forge WebUI',
    'xtts-server' => 'XTTS Hanggenerátor Szerver',
    'ollama' => 'Ollama LLM Nyelvi Modell Szerver',
    'acestep' => 'ACE-Step 1.5 API Szerver',
    'whisper-medium' => 'Whisper Medium hangfelismerő Szerver',
    'wan-api' => 'WanGP Videógenerátor API Szerver',
    'searxng' => 'SearXNG keresőmotor',
    'qwen-image' => 'Qwen Image 2.1',
    'mara-embedding' => 'Mara Tudástár – llama.cpp embedding szerver',
    'mara-memory' => 'Mara Lab – memóriaszerver',
];

// Gombnyomások kezelése
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? null;
    $service = $_POST['service'] ?? null;

    if (!is_string($action) || !in_array($action, ['start', 'stop'], true)
        || !is_string($service) || !array_key_exists($service, $services)) {
        http_response_code(400);
        exit('Érvénytelen művelet vagy szolgáltatás.');
    }

    exec(
        '/usr/bin/sudo -n /usr/local/bin/mara-service '
        . escapeshellarg($action) . ' ' . escapeshellarg($service) . ' 2>&1',
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        error_log("Mara service control ($action $service): " . implode("\n", $output));
        http_response_code(500);
        exit('A művelet sikertelen. A részletek a PHP hibanaplóban vannak.');
    }

    header('Location: ' . $_SERVER['SCRIPT_NAME'], true, 303);
    exit;
}

// Státuszok lekérdezése funkció
function get_service_status($service)
{
    $status = trim(
        shell_exec(
            "systemctl is-active "
            . escapeshellarg($service)
            . " 2>/dev/null"
        )
    );

    return $status === 'active'
        ? 'running'
        : 'stopped';
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>AI Szolgáltatások Vezérlőpultja</title>
    <style>
        body { font-family: Arial, sans-serif; background: #121212; color: #fff; padding: 20px; }
        .card { background: #1e1e1e; padding: 20px; margin-bottom: 15px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); display: flex; justify-content: space-between; align-items: center; }
        .status { padding: 5px 10px; border-radius: 4px; font-weight: bold; }
        .running { background: #2e7d32; color: #fff; }
        .stopped { background: #c62828; color: #fff; }
        button { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-left: 10px; }
        .btn-start { background: #4caf50; color: white; }
        .btn-stop { background: #f44336; color: white; }
        .btn-start:hover { background: #45a049; }
        .btn-stop:hover { background: #da190b; }
    </style>
</head>
<body>

    <h1>Mara AI Szolgáltatás Kezelő</h1>

    <?php foreach ($services as $id => $name): ?>
        <?php $current_status = get_service_status($id); ?>
        <div class="card">
            <div>
                <h3><?php echo htmlspecialchars($name); ?></h3>
                <p>Rendszer név: <code><?php echo $id; ?>.service</code></p>
            </div>
            
            <div>
                <?php if ($current_status === 'running'): ?>
                    <span class="status running">FUT</span>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="service" value="<?php echo $id; ?>">
                        <input type="hidden" name="action" value="stop">
                        <button type="submit" class="btn-stop">Leállítás</button>
                    </form>
                <?php else: ?>
                    <span class="status stopped">ÁLL</span>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="service" value="<?php echo $id; ?>">
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="btn-start">Indítás</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</body>
</html>
