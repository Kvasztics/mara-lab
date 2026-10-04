<?php
declare(strict_types=1);
namespace mara\core\tools;
use mara\core\EmotionalState;
use mara\core\User;
use mara\database\mEmotionalState;

class UpdateEmotionalState implements ToolInterface
{
    public function name(): string { return 'update_emotional_state'; }
    public function status(): string
    {
        return defined('LANG') ? (LANG['STATUS_EMOTIONALBALL'] ?? 'Emotional Ball...') : 'Emotional Ball...';
    }
    public function definition(): array
    {
        $properties = [];
        foreach (EmotionalState::AXES as $axis) {
            $properties[$axis] = ['type' => 'integer', 'minimum' => 0, 'maximum' => 10];
        }
        $properties['note'] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 240,
            'description' => 'One short explanation of the character-state change.'];
        return ['type' => 'function', 'function' => [
            'name' => $this->name(),
            'description' => 'Update your fictional character emotional state based on the conversation and character traits; do not rate the user.',
            'parameters' => ['type' => 'object', 'properties' => $properties,
                'required' => array_merge(EmotionalState::AXES, ['note']), 'additionalProperties' => false],
        ]];
    }
    public function execute(array $args, array $context = []): mixed
    {
        if (($context['emotional_ball_enabled'] ?? false) !== true) {
            throw new \RuntimeException('Emotional Ball is disabled for this response.');
        }
        $modelId = (int)($context['model_id'] ?? 0);
        $chatId = (int)($context['chat_id'] ?? 0);
        $userId = User::id();
        if ($modelId <= 0 || $chatId <= 0 || $userId <= 0 ||
            $modelId !== (int)($_SESSION['model_id'] ?? 0) || $chatId !== (int)($_SESSION['chat_id'] ?? 0)) {
            throw new \RuntimeException('Missing or invalid Emotional Ball context.');
        }
        $validated = EmotionalState::validate($args);
        $db = new mEmotionalState();
        $previous = $db->get($userId, $modelId);
        if (!$db->save($userId, $modelId, $chatId, $validated['state'], $validated['note'])) {
            throw new \RuntimeException('Could not save character emotional state.');
        }
        EmotionalState::log(['status' => 'UPDATED', 'user_id' => $userId, 'model_id' => $modelId,
            'chat_id' => $chatId, 'previous' => $previous, 'state' => $validated['state'], 'note' => $validated['note']]);
        return ['state' => $validated['state'], 'message' => 'Character state saved. Continue your normal reply; do not call this tool again for this response.'];
    }
}
return new UpdateEmotionalState();
