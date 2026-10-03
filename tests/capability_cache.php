<?php
declare(strict_types=1);
namespace mara\database {
    class mModels {
        public static array $row = [];
        public static array $saved = [];
        public function getById(int $id): ?array { return self::$row; }
        public function updateModelInfo(int $id, array $info, array $source): bool {
            self::$row['modelinfo'] = json_encode($info); return true;
        }
        public function update(int $id, array $data): bool { self::$saved = $data; return true; }
    }
    class mPsyche {}
}
namespace mara\core {
    class Voices {}
    class User {
        public static function id(): int { return 1; }
        public static function isAdmin(): bool { return false; }
    }
}
namespace {
    require dirname(__DIR__).'/core/Models.php';
    function check(bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
    }
    $row = ['id'=>7,'provider'=>'llamacpp','basemodel'=>'hugi.gguf','mmproj'=>'projector.gguf','user_id'=>1,'modelinfo'=>'{"context":262144,"size":"0.8B","capabilities_known":false}'];
    \mara\database\mModels::$row = $row;
    $models = new \mara\core\Models();
    $caps = ['vision'=>true,'video'=>false,'audio'=>false,'tools'=>true,'thinking'=>true,'context'=>2048];
    $info = $models->rememberCapabilities($row, $caps);
    check($info['vision'] && $info['thinking'], 'Capabilities must persist.');
    check($info['context'] === 262144, 'Keep trained context metadata.');
    $cached = array_merge($row, ['modelinfo'=>$info]);
    check(\mara\core\Models::hasCachedCapabilities($cached), 'Same model should use cache.');
    check(!\mara\core\Models::hasCachedCapabilities(array_merge($cached,['mmproj'=>'new.gguf'])), 'Projector change must invalidate cache.');
    check(!\mara\core\Models::hasCachedCapabilities(array_merge($cached,['basemodel'=>'new.gguf'])), 'Base change must invalidate cache.');
    $input = ['id'=>7,'provider'=>'llamacpp','basemodel'=>'hugi.gguf','mmproj'=>'projector.gguf','private_model'=>1,'modelinfo'=>'{"capabilities_known":false,"vision":false}'];
    check($models->save($input) === 7, 'Save should succeed.');
    $saved = \mara\database\mModels::$saved;
    check(json_decode($saved['modelinfo'],true)['vision'], 'Editor save must preserve runtime cache.');
    check(json_decode($saved['parameters'],true)['think'] === false, 'Thinking defaults off.');
    $input['param_think']=1;
    $models->save($input);
    check(json_decode(\mara\database\mModels::$saved['parameters'],true)['think'] === true, 'Thinking checkbox must be saved.');
    $input['mmproj']='replacement.gguf';
    $models->save($input);
    $changed=json_decode(\mara\database\mModels::$saved['modelinfo'],true);
    check(!$changed['capabilities_known'] && !isset($changed['_capability_source']), 'Source change must clear discovery marker.');
    check($models->rememberCapabilities($row, []) === [], 'Empty response must not be cached.');
    \mara\database\mModels::$row['user_id']=2;
    check($models->rememberCapabilities($row, $caps) === [], 'Other users private models must not be updated.');
    echo "Capability cache tests passed.\n";
}
