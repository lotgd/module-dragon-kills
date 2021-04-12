<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Tests;

use Doctrine\Common\Util\Debug;
use LotGD\Core\Action;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Core\Models\ModuleProperty;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\Tests\ModelTestCase;
use LotGD\Module\DragonKills\Tests\Helpers\DragonKillsEvent;
use LotGD\Module\Res\Fight\Fight;
use LotGD\Module\Res\Fight\Models\CharacterResFightExtension;
use LotGD\Module\Res\Fight\Tests\helpers\EventRegistry;
use LotGD\Module\Res\Fight\Module as ResFightModule;

use LotGD\Module\DragonKills\Module as DragonKillModule;
use LotGD\Module\Res\Wealth\Models\CharacterResWealthExtension;
use PHPUnit\Framework\InvalidArgumentException;

class ModuleTest extends ModuleTestCase
{
    const Library = 'lotgd/module-dragon-kills';

    public function testHandleUnknownEvent()
    {
        // Always good to test a non-existing event just to make sure nothing happens :).
        $context = new EventContext(
            "e/lotgd/tests/unknown-event",
            "none",
            EventContextData::create([])
        );

        $newContext = DragonKillModule::handleEvent($this->g, $context);
        $this->assertSame($context, $newContext);
    }

    protected function goToForest(string $characterId, callable $executeBeforeTakingActionToForest = null): array
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find($characterId);
        $game->setCharacter($character);

        // New day
        $v = $game->getViewpoint();

        $this->assertSame("It is a new day!", $v->getTitle());
        // Village
        $action = $v->getActionGroups()[0]->getActions()[0];
        $game->takeAction($action->getId());
        $this->assertSame("Village", $v->getTitle());
        // Forest
        $this->assertHasAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000005"], "Outside");
        $action = $this->getAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000005"], "Outside");

        if ($executeBeforeTakingActionToForest !== null) {
            $executeBeforeTakingActionToForest($game, $v, $character);
        }

        $game->takeAction($action->getId());
        $this->assertSame("Forest", $v->getTitle());

        return [$game, $v, $character];
    }

    public function getDragonSceneId()
    {
        $em = $this->getEntityManager();
        /** @var ModuleModel $module */
        $module = $em->getRepository(ModuleModel::class)->find(self::Library);
        $sceneIds = $module->getProperty(DragonKillModule::GeneratedSceneProperty);
        return $sceneIds[0];
    }

    public function testIfConnectionToDragonIsPresentIfCharacterIsLevel15AndHasNotYetSeenDragonAndIfConnectionIsGoneIfHeLeaves()
    {
        [$game, $v, $character] = $this->goToForest("10000000-0000-0000-0000-000000000001");

        // Assert action to green dragon and go there
        $this->assertHasAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $action = $this->getAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $game->takeAction($action->getId());
        $this->assertSame("The Green Dragon", $v->getTitle());

        // Assert action back
        $this->assertHasAction($v, ["getTitle", "Enter the cave"], "Dragon's Lair");
        $this->assertHasAction($v, ["getTitle", "Run away like a baby"], "Back");
        $this->assertHasAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000005"], "Back");
        $action3 = $this->getAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000005"], "Back");

        $game->takeAction($action3->getId());
        $this->assertSame("Forest", $v->getTitle());

        // Assert action to green dragon disappeared
        $this->assertNotHasAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
    }

    public function testIfConnectionToDragonIsPresentIfCharacterHadNewDayReset()
    {
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000002");
        $character->setProperty(DragonKillModule::CharacterPropertySeenDragon, true);
        $this->assertTrue($character->getProperty(DragonKillModule::CharacterPropertySeenDragon, null));
        [$game, $v, $character] = $this->goToForest("10000000-0000-0000-0000-000000000002");

        // Assert action to green dragon it not there.
        $this->assertHasAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $this->assertFalse($character->getProperty(DragonKillModule::CharacterPropertySeenDragon, null));
    }

    public function testIfDragonSceneConnectsToVillageIfCharacterLost()
    {
        /** @var Game $game */
        /** @var Viewpoint $v */
        /** @var Character $character */
        [$game, $v, $character] = $this->goToForest("10000000-0000-0000-0000-000000000003");

        // Go to the cave
        $this->assertHasAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $action = $this->getAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $game->takeAction($action->getId());
        $this->assertSame("The Green Dragon", $v->getTitle());

        // Challenge the dragon
        $this->assertHasAction($v, ["getTitle", "Enter the cave"], "Dragon's Lair");
        $action = $this->getAction($v, ["getTitle", "Enter the cave"], "Dragon's Lair");

        // Fight until we die
        $character->setLevel(1);
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(ResFightModule::CharacterPropertyBattleState) !== null){
                $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
                $action = $this->getAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertFalse($character->isAlive());
        $this->assertSame("Defeat!", $v->getTitle());
        $this->assertHasAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000001"]);
        $action1 = $this->getAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000001"]);
        $this->assertHasAction($v, ["getTitle", "Return to the village"]);

        // Assert we are back in the village.
        $game->takeAction($action1->getId());
        $this->assertSame("Village", $v->getTitle());
    }

    public function testIfDragonSceneConnectsToNewDayIfCharacterHasWonAndIfEverythingIsBackProperly()
    {
        /** @var Game $game */
        /** @var Viewpoint $v */
        /** @var Character $character */
        [$game, $v, $character] = $this->goToForest("10000000-0000-0000-0000-000000000004");
        $game->getEventManager()->subscribe(
            pattern: "#".DragonKillModule::DragonKilledEvent."#",
            class: DragonKillsEvent::class,
            library: "test"
        );

        // Set experience and required experience
        CharacterResFightExtension::setCurrentExperienceForCharacter($character, 30000);
        CharacterResFightExtension::setRequiredExperienceForCharacter($character, 31000);

        $character->setGold(5000);

        // Go to the cave
        $this->assertHasAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $action = $this->getAction($v, ["getDestinationSceneId", $this->getDragonSceneId()], "Fight");
        $game->takeAction($action->getId());
        $this->assertSame("The Green Dragon", $v->getTitle());

        // Challenge the dragon
        $this->assertHasAction($v, ["getTitle", "Enter the cave"], "Dragon's Lair");
        $action = $this->getAction($v, ["getTitle", "Enter the cave"], "Dragon's Lair");

        // Fight until we win
        do {
            $game->takeAction($action->getId());
            // Make sure we win by healing every round
            $character->setHealth($character->getMaxHealth());

            if ($character->getProperty(ResFightModule::CharacterPropertyBattleState) !== null) {
                $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
                $action = $this->getAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertTrue($character->isAlive());
        $this->assertSame("Victory!", $v->getTitle());
        $dragonSceneId = $this->getDragonSceneId();
        $this->assertHasAction($v, ["getDestinationSceneId", $dragonSceneId]);
        $action1 = $this->getAction($v, ["getDestinationSceneId", $dragonSceneId]);
        $this->assertHasAction($v, ["getTitle", "Continue"]);

        $eventCountBefore = DragonKillsEvent::$called;

        $game->takeAction($action1->getId());

        $this->assertSame($eventCountBefore+1, DragonKillsEvent::$called);
        $this->assertSame(1, $character->getLevel());
        $this->assertSame(10, $character->getMaxHealth());
        $this->assertSame(0, $character->getGold());

        $this->assertSame(0, CharacterResFightExtension::getCurrentExperienceForCharacter($character));
        $this->assertSame(100, CharacterResFightExtension::getRequiredExperienceForCharacter($character));

        $this->assertHasAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000001"]);
        $action1 = $this->getAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000001"]);
        $this->assertHasAction($v, ["getTitle", "It is a new day"]);

        // Take action - since we reset new day, it should lead to a new day
        $game->takeAction($action1->getId());
        $this->assertSame("It is a new day!", $v->getTitle());
    }
}
