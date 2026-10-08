<?php
declare(strict_types=1);

namespace mara\core\integration;

final class CharacterCardMapper
{
    private const PARAMETERS = [
        'think', 'temperature', 'frequency_penalty', 'repeat_last_n',
        'presence_penalty', 'repeat_penalty', 'top_p', 'top_k', 'min_p',
        'max_tokens', 'num_ctx', 'context_limit',
        'dry_multiplier', 'dry_base', 'dry_allowed_length', 'dry_penalty_last_n',
    ];

    public static function toModel(\stdClass $card, int $userId): array
    {
        // Validate without converting nested JSON objects to arrays.
        $card = CharacterCard::decode(CharacterCard::encode($card));
        $data = $card->data;
        if (mb_strlen(trim($data->name), 'UTF-8') > 64) {
            throw new \InvalidArgumentException('Character name exceeds 64 characters.');
        }

        $extension = $data->extensions->mara_lab ?? null;
        $psychePrompt = self::characterPrompt($data);
        $memory = '';
        $enabled = $psychePrompt !== '';
        $parameters = [];

        if ($extension instanceof \stdClass) {
            if (isset($extension->psyche) &&
                $extension->psyche instanceof \stdClass) {
                $psyche = $extension->psyche;
                if (isset($psyche->prompt) && is_string($psyche->prompt)) {
                    $psychePrompt = $psyche->prompt;
                }
                if (isset($psyche->memory) && is_string($psyche->memory)) {
                    $memory = $psyche->memory;
                }
                if (isset($psyche->enabled) && is_bool($psyche->enabled)) {
                    $enabled = $psyche->enabled;
                }
            }
            if (isset($extension->parameters) &&
                $extension->parameters instanceof \stdClass) {
                $parameters = self::parameters((array)$extension->parameters);
            }
        }

        $psychePrompt = CharacterCardContext::withoutLegacyExamples($psychePrompt, $data->mes_example);

        if (strlen($psychePrompt) > 65535 ||
            strlen($memory) > 65535 ||
            strlen($data->system_prompt) > 65535 ||
            strlen($data->creator_notes) > 65535) {
            throw new \InvalidArgumentException('Character text exceeds the database field limit.');
        }

        return [
            'id' => 0,
            'user_id' => $userId,
            'name' => trim($data->name),
            'note' => $data->creator_notes,
            'prompt' => $data->system_prompt,
            'image' => 'noimage.png',
            'psyche' => $enabled ? 1 : 0,
            'psyche_data' => [
                'prompt' => $psychePrompt,
                'memory' => $memory,
                'reason' => '',
            ],
            'parameters' => $parameters,
            'card_data' => CharacterCard::encode($card),
        ];
    }

    public static function fromModel(array $model, bool $includeMemory = false): \stdClass
    {
        $stored = $model['card_data'] ?? null;
        $card = is_string($stored) && $stored !== ''
            ? CharacterCard::decode($stored)
            : (object)[
                'spec' => 'chara_card_v2',
                'spec_version' => '2.0',
                'data' => (object)[
                    'name' => '', 'description' => '', 'personality' => '',
                    'scenario' => '', 'first_mes' => '', 'mes_example' => '',
                    'creator_notes' => '', 'system_prompt' => '',
                    'post_history_instructions' => '',
                    'alternate_greetings' => [], 'tags' => [],
                    'creator' => '', 'character_version' => '',
                    'extensions' => new \stdClass(),
                ],
            ];

        $data = $card->data;
        $psyche = $model['psyche_data'] ?? [];
        $prompt = CharacterCardContext::profilePrompt($model);
        $oldExtension = $data->extensions->mara_lab ?? null;
        $baseline = $oldExtension instanceof \stdClass &&
            ($oldExtension->psyche ?? null) instanceof \stdClass &&
            is_string($oldExtension->psyche->prompt ?? null)
                ? $oldExtension->psyche->prompt
                : self::characterPrompt($data);
        $baseline = CharacterCardContext::withoutLegacyExamples($baseline, $data->mes_example);

        // Keep original split fields while the imported character text is unchanged.
        if ($prompt !== $baseline) {
            $data->description = $prompt;
            $data->personality = '';
            $data->scenario = '';
            $data->mes_example = '';
        }

        $data->name = (string)($model['name'] ?? '');
        $data->creator_notes = (string)($model['note'] ?? '');
        $data->system_prompt = (string)($model['prompt'] ?? '');

        $extension = $oldExtension instanceof \stdClass
            ? clone $oldExtension : new \stdClass();
        $extension->schema_version = 1;
        $extension->parameters = (object)self::parameters($model['parameters'] ?? []);
        $extension->psyche = (object)[
            'enabled' => !empty($model['psyche']),
            'prompt' => $prompt,
        ];
        if ($includeMemory) {
            $extension->psyche->memory = (string)($psyche['memory'] ?? '');
        }
        $data->extensions->mara_lab = $extension;

        return CharacterCard::decode(CharacterCard::encode($card));
    }

    private static function characterPrompt(\stdClass $data): string
    {
        $parts = [];
        foreach (['description', 'personality', 'scenario'] as $field) {
            $value = trim($data->$field);
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        return implode("\n\n", $parts);
    }

    private static function parameters(array $values): array
    {
        $result = [];
        foreach (self::PARAMETERS as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if ($key === 'think' ? is_bool($value) :
                (is_int($value) || (is_float($value) && is_finite($value)))) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
