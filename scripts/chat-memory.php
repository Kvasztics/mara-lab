<?php

declare(strict_types=1);

use mara\database\ChatMemory;

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
    $language = strtolower((string)($settings['language'] ?? 'hu')) === 'en' ? 'en' : 'hu';
    define('LANG', parse_ini_file(MARA_ROOT . '/public/assets/languages/' . $language . '.ini'));
    \mara\core\App::init(['provider' => ['ollama' => $settingsStore->values('ollama')]]);
    $prompt = trim($settings['memory_prompt'] ?? '') ?: \mara\core\MemorySettings::defaultPrompt();
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

    if ($command === 'index-splade') {
        do {
            $count = \mara\core\SpladeMemory::index(20);
            printf("SPLADE indexed: %d\n", $count);
        } while ($count === 20);
        exit;
    }

    if ($command === 'reindex') {
        $retrieval = new \mara\core\MemoryRetrieval(new ChatMemory(), new \mara\core\Rag());
        printf("Indexed memories: %d\n", $retrieval->reindex());
        exit;
    }

    if ($command === 'work') {
        workMemoryQueue(new ChatMemory(), $prompt, $port);
        if (($settings['memory_search'] ?? 'dense') === 'splade') {
            \mara\core\SpladeMemory::index(3);
        }
        exit;
    }

    $messageId = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$messageId || !in_array($command, ['propose', 'add', 'supersede'], true)) {
        throw new RuntimeException('Usage: serve | work | reindex | index-splade | propose MESSAGE_ID | add MESSAGE_ID TEXT [profile|project|context] | supersede MESSAGE_ID MEMORY_ID TEXT [profile|project|context]');
    }

    $memory = new ChatMemory();
    if ($command !== 'propose') {
        $replaces = $command === 'supersede'
            ? filter_var($argv[3] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : null;
        $content = trim($argv[$command === 'add' ? 3 : 4] ?? '');
        if ($replaces === false || $content === '' || mb_strlen($content) > 500) {
            throw new RuntimeException('Provide a valid replacement ID and a memory of 1–500 characters.');
        }
        $kind = $argv[$command === 'add' ? 4 : 5] ?? 'context';
        if (!in_array($kind, ['profile', 'project', 'context'], true)) {
            throw new RuntimeException('Memory kind must be profile, project or context.');
        }
        [$key, $vector] = $kind === 'profile'
            ? [null, null]
            : (new \mara\core\MemoryRetrieval($memory, new \mara\core\Rag()))->vector($content);
        $memory->save($messageId, $content, $replaces, $kind, $key, $vector);
        fwrite(STDOUT, "Memory saved.\n");
        exit;
    }

    $result = memoryProposal($memory, $messageId, $prompt, $port);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

function memoryProposal(ChatMemory $memory, int $messageId, string $prompt, int $port): array
{
    $source = $memory->source($messageId);
    $previous = $memory->active((int) $source['user_id'], (int) $source['model_id'], (int) $source['session_id']);
    $payload = json_encode([
        'model' => 'mara-memory',
        'temperature' => 0,
        'max_tokens' => 150,
        'chat_template_kwargs' => ['enable_thinking' => false],
        'messages' => [
            ['role' => 'system', 'content' => $prompt . <<<'KINDS'

Kimeneti kiegészítés: add és supersede esetén adj meg kind mezőt is.
profile: beszélgetéseken át érvényes személyes tény vagy tartós preferencia.
project: az adott beszélgetés munkájához tartozó döntés vagy szabály.
context: egyéb kereshető emlék. Skip esetén elég az action mező.
KINDS
            ],
            ['role' => 'user', 'content' => json_encode([
                'korábbi emlékek' => $previous,
                'új üzenet' => $source['content'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        ],
    ], JSON_THROW_ON_ERROR);
    $curl = curl_init("http://127.0.0.1:{$port}/v1/chat/completions");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_FAILONERROR => true,
    ]);
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException(curl_error($curl));
    }
    $choice = json_decode($body, true, 512, JSON_THROW_ON_ERROR)['choices'][0] ?? [];
    if (($choice['finish_reason'] ?? '') !== 'stop') {
        throw new RuntimeException('The memory model returned an incomplete response.');
    }
    $proposal = json_decode($choice['message']['content'] ?? '', true, 512, JSON_THROW_ON_ERROR);
    $action = $proposal['action'] ?? null;
    $text = $proposal['text'] ?? null;
    if (!in_array($action, ['add', 'supersede', 'skip'], true)
        || ($action !== 'skip' && (!is_string($text) || trim($text) === '' || mb_strlen($text) > 500))
        || ($action === 'supersede' && !in_array($proposal['memory_id'] ?? null, array_map('intval', array_column($previous, 'id')), true))
    ) {
        throw new RuntimeException('Invalid memory proposal.');
    }
    $validated = ['action' => $action];
    if ($action !== 'skip') {
        $validated['text'] = trim($text);
        $kind = $proposal['kind'] ?? 'context';
        $validated['kind'] = in_array($kind, ['profile', 'project', 'context'], true) ? $kind : 'context';
    }
    if ($action === 'supersede') {
        $validated['memory_id'] = $proposal['memory_id'];
    }
    return ['source' => $source['content'], 'proposal' => $validated];
}

function workMemoryQueue(ChatMemory $memory, string $prompt, int $port): void
{
    $db = \mara\database\Database::getInstance()->getConnection();
    $lock = hash('sha256', 'mara-memory-worker:' . DB_NAME);
    if (!(int)$db->execute_query('SELECT GET_LOCK(?, 0)', [$lock])->fetch_row()[0]) {
        return;
    }

    try {
        $jobs = $db->query(
            "SELECT j.message_id, j.attempts FROM chat_memory_jobs j
             JOIN chat_messages msg ON msg.id = j.message_id
             JOIN chat_sessions s ON s.id = msg.session_id
             JOIN models m ON m.id = s.model_id
             WHERE j.status = 'pending' AND j.available_at <= CURRENT_TIMESTAMP
               AND " . ChatMemory::enabledModelCondition() . "
             ORDER BY j.available_at, j.message_id LIMIT 3"
        )->fetch_all(MYSQLI_ASSOC);
        foreach ($jobs as $job) {
            $id = (int)$job['message_id'];
            try {
                $proposal = memoryProposal($memory, $id, $prompt, $port)['proposal'];
                $source = $memory->source($id);
                $model = (new \mara\database\mModels())->getById((int)$source['model_id']);
                $parameters = json_decode($model['parameters'] ?? '{}', true);
                if (!filter_var($parameters['memory_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }
                $encoded = json_encode($proposal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                if ($proposal['action'] !== 'skip' &&
                    !filter_var($parameters['memory_review'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                    $kind = $proposal['kind'];
                    [$key, $vector] = $kind === 'profile'
                        ? [null, null]
                        : (new \mara\core\MemoryRetrieval($memory, new \mara\core\Rag()))->vector($proposal['text']);
                    $memory->save($id, $proposal['text'], $proposal['memory_id'] ?? null, $kind, $key, $vector, null, $encoded);
                    continue;
                }
                $status = $proposal['action'] === 'skip' ? 'skipped' : 'review';
                $db->execute_query(
                    'UPDATE chat_memory_jobs SET status = ?, proposal = ?, last_error = NULL WHERE message_id = ?',
                    [$status, $encoded, $id]
                );
            } catch (Throwable $error) {
                $attempts = (int)$job['attempts'] + 1;
                $db->execute_query(
                    'UPDATE chat_memory_jobs SET status = ?, attempts = ?, last_error = ?,
                        available_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE)
                     WHERE message_id = ?',
                    [$attempts >= 5 ? 'failed' : 'pending', $attempts, mb_substr($error->getMessage(), 0, 500), $id]
                );
            }
        }
    } finally {
        $db->execute_query('SELECT RELEASE_LOCK(?)', [$lock]);
    }
}
