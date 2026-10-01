<?php

declare(strict_types=1);

namespace mara\core\provider;

class ProviderResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?string $finishReason = null,
        public readonly array $usage = [],
        public readonly array $meta = [],
        public readonly ?string $reasoning = null,
        public readonly array $toolCalls = [],
    ) {}
}

?>