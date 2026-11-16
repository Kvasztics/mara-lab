<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once MARA_CONFIG . '/config.php';

try {
    $command = $argv[1] ?? '';
    $settingsStore = new \mara\core\Settings();
    $settings = $settingsStore->values('system');
    $port = filter_var($settings['memory_port'] ?? '8085', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('Invalid memory port.');
    }
    if ($command === 'serve') {
        $llama = (new \mara\core\Settings())->values('llamacpp');
        \mara\core\App::init(['provider' => ['llamacpp' => $llama]]);
        $provider = new \mara\core\provider\ProviderManager();
        $model = $settings['memory_model'] ?? '';
        if (!in_array($model, $provider->getLlamaCppModelOptions(), true)) {
            throw new RuntimeException('Select an available memory GGUF in Settings.');
        }
        $binary = $llama['binary'] ?? '';
        if (!is_file($binary) || !is_executable($binary)) {
            throw new RuntimeException('Set the llama-server binary path in llama.cpp settings.');
        }
        $path = $provider->getLlamaCppModelDirectory() . '/' . $model;
        if (!is_readable($path)) {
            throw new RuntimeException('The memory GGUF is not readable.');
        }
        $args = [
            'nice', '-n', '10', $binary, '-m', $path,
            '--alias', 'mara-memory', '--host', '127.0.0.1', '--port', (string) $port,
            '-ngl', '0', '-t', '2', '-tb', '2', '-c', '4096', '-np', '1',
        ];
        passthru('exec ' . implode(' ', array_map('escapeshellarg', $args)), $status);
        exit($status);
    }

    throw new RuntimeException('Usage: serve');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
