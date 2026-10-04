<?php
declare(strict_types=1);
namespace mara\core {
    class User { public static function id(): int { return 1; } }
}
namespace mara\database {
    class mEmotionalState {
        public static array $saved = [];
        public function get(int $userId, int $modelId): array { return \mara\core\EmotionalState::neutral(); }
        public function save(int $userId, int $modelId, int $chatId, array $state, string $note): bool {
            self::$saved = [$userId, $modelId, $chatId, $state, $note]; return true;
        }
    }
}
namespace {
    require dirname(__DIR__).'/core/EmotionalState.php';
    // No app bootstrap or real database: this test uses isolated objects.
    require __DIR__.'/ToolInterface.php';
    $tool = require dirname(__DIR__).'/core/tools/UpdateEmotionalState.tool.php';
    function check(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
    function rejects(callable $fn): void {
        try { $fn(); } catch (InvalidArgumentException | RuntimeException $e) { return; }
        throw new RuntimeException('Invalid input was accepted.');
    }
    $args = array_fill_keys(\mara\core\EmotionalState::AXES, 0) + ['note'=>'A friendly greeting increased joy.'];
    $args['joy']=8;
    $valid=\mara\core\EmotionalState::validate($args);
    check(count($valid['state']) === 8 && $valid['state']['joy'] === 8, 'Eight independent axes expected.');
    foreach ([-1,11,8.5,'8',true,null] as $bad) {
        $test=$args; $test['joy']=$bad;
        rejects(fn()=>\mara\core\EmotionalState::validate($test));
    }
    $test=$args; unset($test['fear']); rejects(fn()=>\mara\core\EmotionalState::validate($test));
    rejects(fn()=>\mara\core\EmotionalState::validate($args+['affinity'=>3]));
    $test=$args; $test['note']=''; rejects(fn()=>\mara\core\EmotionalState::validate($test));
    $test['note']=str_repeat('x',241); rejects(fn()=>\mara\core\EmotionalState::validate($test));
    $args['anger']=10; \mara\core\EmotionalState::validate($args);
    check($tool->name() === 'update_emotional_state', 'Wrong tool name.');
    $schema=$tool->definition()['function']['parameters'];
    check(count($schema['required'])===9 && !$schema['additionalProperties'], 'Strict schema expected.');
    rejects(fn()=> $tool->execute($args, []));
    $_SESSION=['model_id'=>7,'chat_id'=>4];
    rejects(fn()=> $tool->execute($args,['emotional_ball_enabled'=>true,'model_id'=>8,'chat_id'=>4]));
    $result=$tool->execute($args,['emotional_ball_enabled'=>true,'model_id'=>7,'chat_id'=>4]);
    check($result['state']['joy']===8 && $result['state']['anger']===10, 'Tool must preserve values.');
    check(\mara\database\mEmotionalState::$saved[0]===1, 'State must be scoped to current user.');
    echo "Emotional Ball tests passed: eight axes, bounds, types, note, schema, disabled gate, context and persistence.\n";
}
