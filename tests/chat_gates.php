<?php
declare(strict_types=1);
namespace mara\database {
    class mChat {
        public function saveMessage(...$args): int { return 99; }
        public function getMessages(int $id): array {
            return [['role'=>'system','content'=>'Character instructions'],['role'=>'user','content'=>'Hello'],['role'=>'assistant','content'=>'Earlier reply']];
        }
        public function getContextState(int $id): array { return []; }
        public function saveContextState(...$args): void {}
    }
    class mEmotionalState {
        public static int $writes = 0;
        public function get(int $userId, int $modelId): array { return \mara\core\EmotionalState::neutral(); }
        public function save(...$args): bool { self::$writes++; return true; }
    }
}
namespace mara\core {
    class User { public static function id(): int { return 1; } }
    class App {
        public static array $data = [];
        public static function get(string $key, mixed $default=null): mixed {
            $value=self::$data; foreach(explode('.',$key) as $part) {
                if (!is_array($value) || !array_key_exists($part,$value)) return $default;
                $value=$value[$part];
            } return $value;
        }
    }
    class Models {}
    class Rag {}
    class Template { public function __construct(...$args) {} public function fetch(): string { return '<div>message</div>'; } }
    class Status { public static function clear(): void {} }
    class Tools {
        public function registry(): object { return $GLOBALS['emotionTool']; }
        public function get(array $names): array {
            return array_map(fn($name)=>$name==='update_emotional_state' ? $GLOBALS['emotionTool']->definition() :
                ['type'=>'function','function'=>['name'=>$name]],$names);
        }
    }
    class ToolRunner {
        public function __construct(private object $tool) {}
        public function run(string $name,array $args,array $context): array {
            try { return ['success'=>true,'result'=>$this->tool->execute($args,$context)]; }
            catch (\Throwable $e) { return ['success'=>false,'error'=>$e->getMessage()]; }
        }
    }
}
namespace mara\core\voice { class VoiceText { public static function clean(string $text): string { return $text; } } }
namespace mara\core\provider {
    class ProviderManager {
        public function activate(array $model): bool { return true; }
        public function getProvider(string $name): object { return $GLOBALS['provider']; }
    }
    class FakeProvider {
        public array $calls=[];
        public function __construct(private bool $callTool) {}
        public function chat(array $messages,array $options,array $tools): object {
            $this->calls[]=['messages'=>$messages,'tools'=>$tools];
            $calls=[];
            if ($this->callTool && count($this->calls)===1) {
                $args=\mara\core\EmotionalState::neutral()+['note'=>'Friendly greeting'];$args['joy']=8;
                foreach(['first','duplicate'] as $id) $calls[]=['id'=>$id,'function'=>['name'=>'update_emotional_state','arguments'=>json_encode($args)]];
            }
            return (object)['content'=>$calls ? '' : 'Hello there!','toolCalls'=>$calls,'usage'=>[],'meta'=>[]];
        }
    }
}
namespace {
    define('DIR_TPL', '/unused');
    require dirname(__DIR__).'/core/EmotionalState.php';
    require __DIR__.'/ToolInterface.php';
    $GLOBALS['emotionTool']=require dirname(__DIR__).'/core/tools/UpdateEmotionalState.tool.php';
    require dirname(__DIR__).'/core/Chat.php';
    class TestChat extends \mara\core\Chat {
        public function renderRating(?array $rating): string { return ''; }
        public function renderMetrics(array $metrics,array $model): string { return ''; }
    }
    function check(bool $ok,string $message): void { if(!$ok)throw new \RuntimeException($message); }
    @mkdir(dirname(__DIR__).'/var/log',0750,true);
    foreach ([[false,true,true],[true,false,true],[true,true,false],[true,true,true]] as [$front,$tools,$selected]) {
        $_SESSION=['model_id'=>7,'chat_id'=>4];
        $on=$front && $tools && $selected;
        \mara\database\mEmotionalState::$writes=0;
        \mara\core\App::$data=['model'=>['id'=>7,'provider'=>'llamacpp','parameters'=>[
            'capabilities'=>['tools'=>$tools],'builtin_tools'=>$selected?['update_emotional_state']:[],]],
            'system'=>['emotional_ball'=>'EMOTIONAL_TEST_INSTRUCTION']];
        $GLOBALS['provider']=new \mara\core\provider\FakeProvider($on);
        $result=(new TestChat())->send(['message'=>'Hello','emotional_ball'=>$front?'1':'0']);
        check($result['success']===true,'Normal response must still work.');
        $first=$GLOBALS['provider']->calls[0];
        check(str_contains($first['messages'][0]['content'],'EMOTIONAL_TEST_INSTRUCTION')===$on,'Instruction gate failed.');
        check(count($first['tools'])===($on?1:0),'Tool definition gate failed.');
        check(\mara\database\mEmotionalState::$writes===($on?1:0),'Duplicate update or disabled write.');
    }
    echo "Chat gates passed: front switch, model tools, tool selection, prompt exclusion and duplicate suppression.\n";
}
