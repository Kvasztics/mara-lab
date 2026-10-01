<?php

declare(strict_types=1);

namespace mara\core\stt;

interface SttProviderInterface
{
    /**
     * @return array{success: bool, text?: string, error?: string, started?: bool}
     */
    public function transcribe(string $filePath): array;
}
