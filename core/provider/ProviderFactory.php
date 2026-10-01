<?php

declare(strict_types=1);

namespace mara\core\provider;

use InvalidArgumentException;

class ProviderFactory
{
    public static function create(
        string $provider,
        array $config = []
    ): ProviderInterface {
        return match (strtolower($provider)) {
            'llamacpp' => new LlamaCppProvider($config),
            'ollama'   => new OllamaProvider($config),

            default => throw new InvalidArgumentException(
                "Ismeretlen provider: {$provider}"
            ),
        };
    }
}

?>