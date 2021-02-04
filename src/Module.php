<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills;

use LotGD\Core\Events\EventContext;
use LotGD\Core\Exceptions\ArgumentException;
use LotGD\Core\Game;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectable;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\DragonKills\Models\DragonKill;
use LotGD\Module\DragonKills\Scenes\DragonScene;
use LotGD\Module\Forest\Module as ForestModule;
use LotGD\Module\Forest\SceneTemplates\Forest;
use LotGD\Module\NewDay\Module as NewDayModule;
use LotGD\Module\Res\Fight\Module as ResFightModule;

class Module implements ModuleInterface {
    const ModuleIdentifier = "lotgd/module-dragon-kills";

    const CharacterPropertyDragonKills = self::ModuleIdentifier . "/dk";
    const CharacterPropertySeenDragon = self::ModuleIdentifier . "/seenDragon";

    const DragonKilledEvent = "e/" . self::ModuleIdentifier . "/kill";

    const GeneratedSceneProperty = "generatedScenes";

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $event = $context->getEvent();

        return match ($event) {
            ForestModule::HookForestNavigation => DragonScene::forestNavigationHook($g, $context),
            "h/lotgd/core/navigate-to/" . DragonScene::getNavigationEvent() => DragonScene::navigateToScene($g, $context),
            NewDayModule::HookAfterNewDay => self::handleAfterNewDayEvent($g, $context),
            ResFightModule::HookBattleOver => DragonScene::battleOver($g, $context),
            self::DragonKilledEvent => self::handleDragonKilledEvent($g, $context),
            default => $context,
        };
    }

    public static function handleAfterNewDayEvent(Game $g, EventContext $context): EventContext
    {
        $g->getCharacter()->setProperty(self::CharacterPropertySeenDragon, false);
        return $context;
    }

    public static function handleDragonKilledEvent(Game $g, EventContext $context): EventContext
    {
        // Save an entry in the DB for this DK.
        $dk = new DragonKill($g->getCharacter(), $g->getTimeKeeper()->getGameTime());
        $dk->save($g->getEntityManager());

        $character = $g->getCharacter();

        // For ease of access, also store the count on the character.
        $character->incrementDragonKillCount();

        // Reset character
        $character->setLevel(1);
        $character->setMaxHealth($character->getMaxHealth() - 140);
        $character->setHealth($character->getMaxHealth());

        // Reset experience
        $character->setCurrentExperience(0);
        $character->setRequiredExperience($character->calculateNeededExperience());

        return $context;
    }

    /**
     * @param Game $g
     * @param ModuleModel $module
     * @throws ArgumentException
     */
    public static function onRegister(Game $g, ModuleModel $module)
    {
        $em = $g->getEntityManager();

        /** @var Scene[] $forestScenes */
        $forestScenes = $em->getRepository(Scene::class)->findBy(["template" => Forest::class]);
        $generatedScenes = [];

        foreach ($forestScenes as $forestScene) {
            $dragonScene = DragonScene::getScaffold();

            $dragonSceneConnectionGroup = $dragonScene->getConnectionGroup(DragonScene::ActionGroups["back"][0]);
            $dragonSceneConnectionGroup->connect($forestScene, SceneConnectable::Unidirectional);

            $em->persist($dragonScene);
            $em->persist($dragonScene->getTemplate());
            $generatedScenes[] = $dragonScene->getId();
        }

        $module->setProperty(self::GeneratedSceneProperty, $generatedScenes);
    }

    /**
     * @param Game $g
     * @param ModuleModel $module
     */
    public static function onUnregister(Game $g, ModuleModel $module)
    {
        $em = $g->getEntityManager();

        $generatedScenes = $module->getProperty(self::GeneratedSceneProperty, []);

        // Get all dragon scenes
        /** @var Scene[] $scenes */
        $scenes = $em->getRepository(Scene::class)->findBy(["template" => DragonScene::class]);

        foreach($scenes as $scene) {
            if (in_array($scene->getId(), $generatedScenes)) {
                $g->getEntityManager()->remove($scene);
                $g->getEntityManager()->remove($scene->getTemplate());
            } else {
                $scene->setTemplate(null);
            }
        }
    }
}
