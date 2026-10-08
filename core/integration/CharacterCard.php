<?php
declare(strict_types=1);

namespace mara\core\integration;

final class CharacterCard
{
    public const MAX_JSON_BYTES = 2097152;

    public static function decode(string $json): \stdClass
    {
        if ($json === '' || strlen($json) > self::MAX_JSON_BYTES) {
            throw new \InvalidArgumentException('Invalid character card size.');
        }

        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
        }

        $card = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        if (!$card instanceof \stdClass) {
            throw new \InvalidArgumentException('Expected a character card object.');
        }

        if (!property_exists($card, 'spec')) {
            $fields = [
                'name', 'description', 'personality',
                'scenario', 'first_mes', 'mes_example',
            ];
            $data = new \stdClass();
            foreach ($fields as $field) {
                if (!isset($card->$field) || !is_string($card->$field)) {
                    throw new \InvalidArgumentException('Invalid V1 character field: ' . $field);
                }
                $data->$field = $card->$field;
            }

            foreach ([
                'creator_notes', 'system_prompt', 'post_history_instructions',
                'creator', 'character_version',
            ] as $field) {
                $data->$field = '';
            }
            $data->alternate_greetings = [];
            $data->tags = [];
            $data->extensions = new \stdClass();

            // Preserve legacy V1 extras without treating them as executable settings.
            $extras = array_diff_key(
                get_object_vars($card),
                array_flip($fields)
            );
            if ($extras !== []) {
                $data->extensions->mara_lab_legacy_v1 = (object)$extras;
            }

            $card = (object)[
                'spec' => 'chara_card_v2',
                'spec_version' => '2.0',
                'data' => $data,
            ];
        }

        $version = match ($card->spec ?? null) {
            'chara_card_v2' => '2.0',
            'chara_card_v3' => '3.0',
            default => throw new \InvalidArgumentException('Unsupported character card format.'),
        };

        if (($card->spec_version ?? null) !== $version ||
            !($card->data ?? null) instanceof \stdClass) {
            throw new \InvalidArgumentException('Invalid character card version or data.');
        }

        $data = $card->data;
        foreach ([
            'name', 'description', 'personality', 'scenario',
            'first_mes', 'mes_example', 'creator_notes', 'system_prompt',
            'post_history_instructions', 'creator', 'character_version',
        ] as $key) {
            if (!isset($data->$key) || !is_string($data->$key)) {
                throw new \InvalidArgumentException('Invalid character card field: ' . $key);
            }
        }

        if (trim($data->name) === '') {
            throw new \InvalidArgumentException('Character name is required.');
        }

        foreach (['alternate_greetings', 'tags'] as $key) {
            self::validateStrings($data->$key ?? null, $key);
        }

        if ($version === '3.0') {
            self::validateStrings(
                $data->group_only_greetings ?? null,
                'group_only_greetings'
            );
        }

        if (!isset($data->extensions)) {
            $data->extensions = new \stdClass();
        }
        if (!$data->extensions instanceof \stdClass) {
            throw new \InvalidArgumentException('Invalid character card extensions.');
        }

        if (property_exists($data, 'character_book') &&
            !$data->character_book instanceof \stdClass) {
            throw new \InvalidArgumentException('Invalid character book.');
        }

        return $card;
    }

    public static function encode(\stdClass $card): string
    {
        $json = json_encode(
            $card,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            64
        );

        self::decode($json);
        return $json;
    }

    private static function validateStrings(mixed $values, string $field): void
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException('Invalid character card field: ' . $field);
        }

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Invalid character card field: ' . $field);
            }
        }
    }
}
