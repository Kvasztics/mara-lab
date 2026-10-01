<?php

declare(strict_types=1);

namespace mara\core\voice;

interface VoiceProviderInterface
{
    public function isAvailable(): bool;

    /**
     * @param array $voice Voice record from the voices table.
     * @return string WAV audio bytes.
     */
    public function synthesize(array $voice, string $text): string;
}
