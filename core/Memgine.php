<?php

namespace mara\core;

final class Memgine
{
    public static function enabled(): bool
    {
        return self::key() !== '';
    }

    private static function key(): string
    {
        return trim((string)App::get('system.memgine_key', defined('MEMGINE_API_KEY') ? MEMGINE_API_KEY : ''));
    }

    public static function url(): string
    {
        return rtrim((string)App::get('system.memgine_url', 'http://127.0.0.1:8079'), '/');
    }

    public static function timeout(): int
    {
        return (int)App::get('system.memgine_search_timeout_ms', 4000);
    }

    public static function search(int $user, int $model, int $chat, string $query): array
    {
        return array_column(self::searchRecords($user, $model, $chat, $query), 'text');
    }

    public static function searchRecords(int $user, int $model, int $chat, string $query): array
    {
        if ($query === '') {
            return [];
        }

        $response = self::request('search', $user, $model, $chat, ['query' => $query], 200);
        if (!is_array($response['memories'] ?? null)) {
            throw new \RuntimeException('Invalid Memgine search response.');
        }

        return $response['memories'];
    }

    public static function ingest(int $user, int $model, int $chat, int $messageId, string $text, bool $reviewRequired, array $history = []): void
    {
        self::request('ingest', $user, $model, $chat, [
            'message_id' => (string)$messageId,
            'review_required' => $reviewRequired,
            'history' => $history,
            'text' => $text,
        ], 202);
    }

    public static function reviews(int $user, int $model): array
    {
        $response = self::request('reviews', $user, $model, null, [], 200);
        $next = $response['next'] ?? null;
        if ($next === null) {
            return [];
        }
        return [
            'total' => (int)$response['total'],
            'job_id' => $next['job_id'],
            'source' => $next['source'],
            'proposal' => json_encode($next['proposal'], JSON_THROW_ON_ERROR),
            'previous' => $next['previous'] ?? null,
        ];
    }

    public static function review(int $user, int $model, array $fields): void
    {
        self::request('review', $user, $model, null, $fields, 200);
    }

    private static function request(string $action, int $user, int $model, ?int $chat, array $fields, int $expectedStatus): array
    {
        $body = json_encode([
            'user_id' => (string)$user,
            'model_id' => (string)$model,
        ] + ($chat === null ? [] : ['conversation_id' => (string)$chat]) + $fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $request = curl_init(self::url() . '/v1/memory/' . $action);
        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . self::key(),
            ],
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT_MS => 300,
            CURLOPT_TIMEOUT_MS => $action === 'search' ? self::timeout() : 1000,
        ]);
        $response = curl_exec($request);
        $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        if ($response === false || $status !== $expectedStatus) {
            throw new \RuntimeException("Memgine {$action} failed (HTTP {$status}, cURL " . curl_errno($request) . ": " . curl_error($request) . ").");
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }
}
