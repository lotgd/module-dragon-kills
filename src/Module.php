<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills;

use Composer\Script\Event;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectable;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\DragonKills\Models\DragonKill;
use LotGD\Module\DragonKills\Scenes\DragonScene;
use LotGD\Module\Forest\Module as ForestModule;
use LotGD\Module\Forest\Scenes\Forest;

class Module implements ModuleInterface {
    const ModuleIdentifier = "module-dragon-kills";

    const CharacterPropertyDragonKills = 'lotgd/module-dragon-kills/dk';
    const CharacterPropertySeenDragon = 'lotgd/module-dragon-kills/seenDragon';

    const DragonKilledEvent = 'e/lotgd/module-dragon-kills/kill';

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $event = $context->getEvent();

        switch ($event) {
            case ForestModule::HookForestNavigation:
                return DragonScene::forestNavigationHook($g, $context);
                break;

            case self::DragonKilledEvent:
                // Save an entry in the DB for this DK.
                $dk = new DragonKill($g->getCharacter(), $g->getTimeKeeper()->gameTime());
                $dk->save($g->getEntityManager());

                // For ease of access, also store the count on the character.
                $module = new self($g);
                $count = $module->getDragonKillsForUser($g->getCharacter());
                $count++;
                $module->setDragonKillsForUser($g->getCharacter(), $count);
                break;
        }

        return $context;
    }

    public static function onRegister(Game $g, ModuleModel $module)
    {
        $forestScenes = $g->getEntityManager()->getRepository(Scene::class)
            ->findBy(["template" => Forest::Template]);

        foreach ($forestScenes as $forestScene) {
            $dragonScene = DragonScene::create();

            $dragonSceneConnectionGroup = $dragonScene->getConnectionGroup(DragonScene::ActionGroups["back"][0]);
            $dragonSceneConnectionGroup->connect($forestScene, SceneConnectable::Unidirectional);

            $g->getEntityManager()->persist($dragonScene);
        }
    }

    public static function onUnregister(Game $g, ModuleModel $module)
    {
        $em = $g->getEntityManager();

        // delete all dragon scenes
        $scenes = $g->getEntityManager()->getRepository(Scene::class)
            ->findBy(["template" => DragonScene::Template]);

        foreach($scenes as $scene) {
            $g->getEntityManager()->remove($scene);
        }

        $g->getEntityManager()->flush();
    }
}
