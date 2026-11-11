<?php
declare(strict_types=1);

namespace mara\core;

final class ChatDocument
{
    public const TEXT_LIMIT = 16 * 1024;
    private const FILE_LIMIT = 8 * 1024 * 1024;
    private const TEXT_EXTENSIONS = ['txt', 'md', 'ini', 'json', 'css', 'js', 'php', 'csv', 'tsv', 'log', 'xml', 'yaml', 'yml', 'html', 'htm', 'py', 'sql', 'sh', 'conf', 'toml'];
    private const DOCUMENT_EXTENSIONS = ['pdf', 'docx', 'pptx', 'xlsx'];

    public static function accept(): string
    {
        return '.'.implode(',.', array_merge(self::TEXT_EXTENSIONS, self::DOCUMENT_EXTENSIONS));
    }

    public static function preview(array $file): array
    {
        $path = $file['tmp_name'] ?? null;
        if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_FILE_LIMIT']);
        }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_UPLOAD_ERROR']);
        }
        $name = self::name((string)$file['name']);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = filesize($path);
        if ($size === false || $size > self::FILE_LIMIT) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_FILE_LIMIT']);
        }

        if (in_array($extension, self::TEXT_EXTENSIONS, true)) {
            if ($size > self::TEXT_LIMIT + 3) {
                throw new \RuntimeException(LANG['CHAT_DOCUMENT_TEXT_LIMIT']);
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException(LANG['CHAT_DOCUMENT_UPLOAD_ERROR']);
            }
        } elseif (in_array($extension, self::DOCUMENT_EXTENSIONS, true)) {
            $content = self::convert($path, $extension);
        } else {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_TYPE']);
        }
        return ['name' => $name, 'text' => self::text($content)];
    }

    public static function name(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));
        if ($name === '' || strlen($name) > 255 || !preg_match('/\A[^\p{C}]+\z/u', $name)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_UPLOAD_ERROR']);
        }
        return $name;
    }

    public static function text(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (strlen($content) > self::TEXT_LIMIT) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_TEXT_LIMIT']);
        }
        if (trim($content) === '' || !preg_match('//u', $content) ||
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_TEXT_INVALID']);
        }
        return $content;
    }

    private static function convert(string $path, string $extension): string
    {
        $python = '/opt/mara-markitdown/bin/python';
        if (!is_executable($python)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_CONVERTER_MISSING']);
        }
        $command = [
            '/usr/bin/timeout', '-k', '2s', '20s', $python,
            dirname(__DIR__).'/scripts/convert_chat_document.py', $path, $extension,
            (string)self::TEXT_LIMIT,
        ];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'],
            2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_CONVERSION_ERROR']);
        }
        $content = stream_get_contents($pipes[1], self::TEXT_LIMIT + 1);
        fclose($pipes[1]);
        $status = proc_close($process);
        if ($status === 3 || ($content !== false && strlen($content) > self::TEXT_LIMIT)) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_TEXT_LIMIT']);
        }
        if ($status === 4) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_CONVERTER_MISSING']);
        }
        if ($status !== 0 || $content === false) {
            throw new \RuntimeException(LANG['CHAT_DOCUMENT_CONVERSION_ERROR']);
        }
        return $content;
    }
}
