<?php
declare(strict_types=1);

namespace mara\core\integration;

use mara\core\App;
use mara\core\ServiceManager;

final class KnowledgeWebImport
{
    private const SERVICE = 'mara-reader';
    private const READER_URL = 'http://127.0.0.1:8084';

    public const DEFAULT_PROMPT = 'Extract the main content from the given HTML and convert it to Markdown format. Keep the title and substantive article text, or for a product keep its description, specifications, compatibility, and package contents. Omit navigation, ads, ratings, customer reviews, forms, stock, prices, delivery estimates, and related products. Remove duplicate facts and promotional filler. Keep exact factual details and units in the source language. Return Markdown only.';

    public const LIMITS = [
        'download_timeout_seconds' => [15, 1, 30],
        'startup_timeout_seconds' => [30, 1, 45],
        'timeout_seconds' => [120, 1, 300],
        'max_tokens' => [4096, 1, 8192],
        'download_max_bytes' => [4194304, 1024, 16777216],
        'input_max_bytes' => [48000, 1024, 96000],
    ];

    public static function limit(string $key): int
    {
        [$default, $min, $max] = self::LIMITS[$key];
        $value = filter_var(App::get('reader.'.$key, $default), FILTER_VALIDATE_INT);
        return $value === false ? $default : max($min, min($max, $value));
    }

    public static function prompt(): string
    {
        $prompt = trim((string)App::get('reader.system_prompt', ''));
        return $prompt !== '' ? $prompt : self::DEFAULT_PROMPT;
    }

    public function read(string $url): array
    {
        if (strlen($url) > 500) {
            throw new \RuntimeException(LANG['KNOW_IMPORT_URL_ERROR']);
        }
        $html = $this->download($url);
        $inputLimit = self::limit('input_max_bytes');
        $fetchedAt = gmdate('Y-m-d H:i:s');

        $dom = new \DOMDocument();
        $parsed = @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        if (!$parsed) {
            throw new \RuntimeException(LANG['KNOW_IMPORT_NO_HTML']);
        }
        $xpath = new \DOMXPath($dom);
        $excluded = '//script|//style|//noscript|//svg|//template|//iframe|//comment()'
            .'|//nav|//form|//*[@role="navigation"]'
            .'|//*[@id="reviews" or @id="tab-reviews" or @id="review_form_wrapper"]'
            .'|//section[contains(concat(" ", normalize-space(@class), " "), " related ")'
            .' or contains(concat(" ", normalize-space(@class), " "), " upsells ")]';
        foreach ($xpath->query($excluded) as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach ($xpath->query('//*[@*]') as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (!in_array($attribute->name, ['href', 'src', 'alt', 'role'], true)) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $input = $body ? $dom->saveHTML($body) : false;
        if (!$input || trim($body->textContent) === '') {
            throw new \RuntimeException(LANG['KNOW_IMPORT_NO_HTML']);
        }
        if (strlen($input) > $inputLimit) {
            // Prefer a semantic content container over menus and other page chrome.
            foreach (['//main//article', '//main', '//*[@role="main"]', '//article'] as $selector) {
                foreach ($xpath->query($selector) as $section) {
                    if (mb_strlen(trim($section->textContent)) < 150) continue;
                    $candidate = $dom->saveHTML($section);
                    if ($candidate !== false && strlen($candidate) <= $inputLimit) {
                        $input = $candidate;
                        break 2;
                    }
                }
            }
        }
        if (strlen($input) > $inputLimit) {
            throw new \RuntimeException(LANG['KNOW_IMPORT_SIZE']);
        }

        $title = trim($dom->getElementsByTagName('title')->item(0)?->textContent ?? '');
        return [
            'title' => mb_substr($title !== '' ? $title : $url, 0, 255),
            'content' => $this->runReader($input),
            'source' => $url,
            'fetched_at' => $fetchedAt,
        ];
    }

    private function download(string $url): string
    {
        $downloadLimit = self::limit('download_max_bytes');
        $downloadTimeout = self::limit('download_timeout_seconds');
        for ($redirects = 0; $redirects <= 4; ++$redirects) {
            $parts = parse_url($url);
            $scheme = strtolower($parts['scheme'] ?? '');
            $host = $parts['host'] ?? '';
            $port = $scheme === 'https' ? 443 : 80;
            if (!filter_var($url, FILTER_VALIDATE_URL) || str_contains($url, "\\") ||
                !in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) ||
                ($parts['port'] ?? $port) !== $port ||
                !preg_match('/\A(?=.{1,253}\z)[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z][a-z0-9-]*\z/i', $host)) {
                throw new \RuntimeException(LANG['KNOW_IMPORT_URL_ERROR']);
            }
            $addresses = gethostbynamel($host) ?: [];
            if ($addresses === []) {
                throw new \RuntimeException(LANG['KNOW_IMPORT_DOWNLOAD_ERROR']);
            }
            foreach ($addresses as $address) {
                if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) ||
                    (int)explode('.', $address)[0] >= 224) {
                    throw new \RuntimeException(LANG['KNOW_IMPORT_URL_ERROR']);
                }
            }
            $html = '';
            $tooLarge = false;
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROXY => '',
                CURLOPT_RESOLVE => [$host.':'.$port.':'.$addresses[0]],
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => $downloadTimeout,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'MaraLab-KnowledgeImport/1.0',
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$html, &$tooLarge, $downloadLimit): int {
                    if (strlen($html) + strlen($chunk) > $downloadLimit) {
                        $tooLarge = true;
                        return 0;
                    }
                    $html .= $chunk;
                    return strlen($chunk);
                },
            ]);
            try {
                $ok = curl_exec($curl);
                $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $curlError = curl_errno($curl);
                $next = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
                $type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            } finally {
                curl_close($curl);
            }

            if ($status === 403) {
                throw new \RuntimeException(LANG['KNOW_IMPORT_FORBIDDEN']);
            }
            if ($tooLarge) {
                throw new \RuntimeException(sprintf(LANG['KNOW_IMPORT_TRANSFER_SIZE'], $downloadLimit));
            }
            if ($ok === false) {
                throw new \RuntimeException(sprintf(LANG['KNOW_IMPORT_CONNECTION_ERROR'], $curlError));
            }
            if ($ok && in_array($status, [301, 302, 303, 307, 308], true) && is_string($next) && $next !== '') {
                $url = $next;
                continue;
            }
            if ($ok && $status >= 200 && $status < 300 &&
                preg_match('~^(text/html|application/xhtml\+xml)(?:\s*;|$)~i', $type) && trim($html) !== '') {
                if (preg_match('/charset\s*=\s*["\']?([a-z0-9_-]+)/i', $type, $match)) {
                    try {
                        $html = mb_convert_encoding($html, 'UTF-8', $match[1]);
                    } catch (\ValueError) {
                        throw new \RuntimeException(LANG['KNOW_IMPORT_DOWNLOAD_ERROR']);
                    }
                }
                return $html;
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(sprintf(LANG['KNOW_IMPORT_HTTP_ERROR'], $status));
            }
            throw new \RuntimeException(LANG['KNOW_IMPORT_NO_HTML']);
        }
        throw new \RuntimeException(LANG['KNOW_IMPORT_DOWNLOAD_ERROR']);
    }

    private function runReader(string $html): string
    {
        $lock = @fopen(dirname(__DIR__, 2).'/var/run/mara-reader.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException(LANG['KNOW_IMPORT_SERVICE_ERROR']);
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new \RuntimeException(LANG['KNOW_IMPORT_READER_BUSY']);
        }

        $services = new ServiceManager();
        try {
            $this->serviceAction($services, 'start');
            $this->waitUntilReady();
            return $this->extract($html);
        } finally {
            try {
                $this->serviceAction($services, 'stop');
            } finally {
                fclose($lock);
            }
        }
    }

    private function serviceAction(ServiceManager $services, string $action): void
    {
        try {
            $result = $services->run($action, self::SERVICE, 20);
            if (!$result['success']) {
                throw new \RuntimeException($result['output']);
            }
        } catch (\Throwable $error) {
            error_log('Mara Reader service '.$action.': '.$error->getMessage());
            throw new \RuntimeException(LANG['KNOW_IMPORT_SERVICE_ERROR']);
        }
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + self::limit('startup_timeout_seconds');
        $curl = curl_init(self::READER_URL.'/health');
        curl_setopt_array($curl, [
            CURLOPT_PROXY => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => 1000,
        ]);
        try {
            do {
                $raw = curl_exec($curl);
                if ($raw !== false && curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200 &&
                    (json_decode($raw, true)['status'] ?? null) === 'ok') {
                    return;
                }
                usleep(250000);
            } while (microtime(true) < $deadline);
        } finally {
            curl_close($curl);
        }
        throw new \RuntimeException(LANG['KNOW_IMPORT_READER_ERROR']);
    }

    private function extract(string $html): string
    {
        $curl = curl_init(self::READER_URL.'/v1/chat/completions');
        $raw = '';
        $timeout = self::limit('timeout_seconds');
        $maxTokens = self::limit('max_tokens');
        curl_setopt_array($curl, [
            CURLOPT_PROXY => '',
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'readerlm-v2',
                'messages' => [['role' => 'user', 'content' =>
                    self::prompt()."\n```html\n".$html."\n```"]],
                'temperature' => 0,
                'max_tokens' => $maxTokens,
                'stream' => false,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
                if (strlen($raw) + strlen($chunk) > 128 * 1024) return 0;
                $raw .= $chunk;
                return strlen($chunk);
            },
        ]);
        try {
            $ok = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_errno($curl);
            $elapsed = (float)curl_getinfo($curl, CURLINFO_TOTAL_TIME);
        } finally {
            curl_close($curl);
        }
        $result = json_decode($raw, true);
        $choice = $result['choices'][0] ?? [];
        $content = trim((string)($choice['message']['content'] ?? ''));
        $finishReason = $choice['finish_reason'] ?? null;
        if (!$ok || $status !== 200 || ($result['model'] ?? null) !== 'readerlm-v2' ||
            $content === '' || $finishReason !== 'stop') {
            error_log(sprintf('Mara Reader: HTTP %d, cURL %d, %.1f s, timeout %d s, token limit %d, finish %s',
                $status, $curlError, $elapsed, $timeout, $maxTokens,
                in_array($finishReason, ['stop', 'length'], true) ? $finishReason : 'other'));
            $message = match (true) {
                $curlError === CURLE_OPERATION_TIMEDOUT => sprintf(LANG['KNOW_IMPORT_READER_TIMEOUT'], $timeout),
                $ok && $status === 200 && $finishReason === 'length' =>
                    sprintf(LANG['KNOW_IMPORT_READER_LENGTH'], $maxTokens),
                $curlError !== 0 => sprintf(LANG['KNOW_IMPORT_READER_CONNECTION'], $curlError),
                $status !== 200 => sprintf(LANG['KNOW_IMPORT_READER_HTTP'], $status),
                default => LANG['KNOW_IMPORT_READER_ERROR'],
            };
            throw new \RuntimeException($message);
        }
        return $content;
    }
}
