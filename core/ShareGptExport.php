<?php
declare(strict_types=1);
namespace mara\core;

/** Text-only ShareGPT export of persisted messages, never current runtime prompts. */
final class ShareGptExport
{
    public static function conversation(int $chatId, array $messages): array
    {
        $roles = ['system' => 'system', 'user' => 'human', 'assistant' => 'gpt'];
        $turns = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? '');
            if (!isset($roles[$role])) continue;
            $text = (string)($message['content'] ?? '');
            if (trim($text) === '') continue;
            $turns[] = ['from' => $roles[$role], 'value' => $text];
        }
        return ['id' => 'mara-chat-'.$chatId, 'conversations' => $turns];
    }
}
