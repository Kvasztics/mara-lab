<?php
declare(strict_types=1);

namespace mara\core\provider;

final class ChatStream
{
    private string $buffer = '';
    private array $response = [];
    private array $message = ['content' => '', 'tool_calls' => []];
    private bool $done = false;
    private ?string $finishReason = null;

    public function __construct(private bool $sse, private \Closure $onText)
    {
    }

    public function write(string $chunk): int
    {
        $this->buffer .= $chunk;
        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = rtrim(substr($this->buffer, 0, $newline), "\r");
            $this->buffer = substr($this->buffer, $newline + 1);
            $this->line($line);
        }
        if (strlen($this->buffer) > 8 * 1024 * 1024) {
            throw new \RuntimeException('Provider stream line too large.');
        }
        return strlen($chunk);
    }

    public function result(): array
    {
        $this->line($this->buffer);
        if (!$this->done || ($this->sse && $this->finishReason === null)) {
            throw new \RuntimeException('Provider stream ended before completion.');
        }
        if ($this->sse) {
            $this->message['tool_calls'] = array_values($this->message['tool_calls']);
            $this->response['choices'] = [['message' => $this->message, 'finish_reason' => $this->finishReason]];
        } else {
            $this->response['message'] = $this->message;
        }
        return $this->response;
    }

    private function line(string $line): void
    {
        if ($line === '' || $this->done) return;
        if ($this->sse) {
            if (!str_starts_with($line, 'data:')) return;
            $line = ltrim(substr($line, 5));
            if ($line === '[DONE]') {
                $this->done = true;
                return;
            }
        }
        $frame = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($frame) || isset($frame['error'])) {
            throw new \RuntimeException('Provider returned a stream error.');
        }

        $choice = $frame['choices'][0] ?? [];
        $delta = $this->sse ? ($choice['delta'] ?? []) : ($frame['message'] ?? []);
        unset($frame['choices'], $frame['message']);
        $this->response = array_replace($this->response, $frame);
        $this->finishReason = $choice['finish_reason'] ?? $this->finishReason;
        $this->done = !$this->sse && ($frame['done'] ?? false) === true;

        foreach (['content', 'thinking', 'reasoning_content'] as $field) {
            $text = $delta[$field] ?? '';
            if (!is_string($text) || $text === '') continue;
            $this->message[$field] = ($this->message[$field] ?? '').$text;
            if ($field === 'content') {
                ($this->onText)($text);
            }
        }
        foreach ($delta['tool_calls'] ?? [] as $call) {
            if (!$this->sse) {
                $this->message['tool_calls'][] = $call;
                continue;
            }
            $index = (int)($call['index'] ?? 0);
            $merged = $this->message['tool_calls'][$index] ?? ['type' => 'function', 'function' => []];
            foreach (['id', 'type'] as $key) {
                if (!empty($call[$key])) $merged[$key] = $call[$key];
            }
            foreach (['name', 'arguments'] as $key) {
                $part = $call['function'][$key] ?? '';
                $merged['function'][$key] = ($merged['function'][$key] ?? '').$part;
            }
            $this->message['tool_calls'][$index] = $merged;
        }
    }
}
