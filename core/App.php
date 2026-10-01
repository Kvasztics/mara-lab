<?php

declare(strict_types=1);

namespace mara\core;

final class App
{
    /**
     * Application configuration.
     */
    private static array $config = [];

    /**
     * Initialize application configuration.
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * Example:
     * App::get('provider.llamacpp.url')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return self::$config;
        }

        $value = self::$config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Check whether a configuration value exists.
     */
    public static function has(string $key): bool
    {
        $marker = new \stdClass();

        return self::get($key, $marker) !== $marker;
    }

    /**
     * Set or overwrite a configuration value at runtime.
     *
     * Example:
     * App::set('provider.llamacpp.model', 'qwen.gguf');
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);

        $config =& self::$config;

        foreach ($segments as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }

            $config =& $config[$segment];
        }

        $config = $value;
    }

    /**
     * Return the complete configuration.
     */
    public static function all(): array
    {
        return self::$config;
    }
}