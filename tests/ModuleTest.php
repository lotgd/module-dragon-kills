<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Tests;

use Doctrine\Common\Util\Debug;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\Tests\ModelTestCase;
use LotGD\Module\Res\Fight\Fight;
use LotGD\Module\Res\Fight\Tests\helpers\EventRegistry;
use LotGD\Module\Res\Fight\Module as ResFightModule;

use LotGD\Module\DragonKills\Module as DragonKillModule;

class ModuleTest extends ModuleTestCase
{
    const Library = 'lotgd/module-dragon-kills';

    protected function getDataSet(): \PHPUnit_Extensions_Database_DataSet_YamlDataSet
    {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', 'module.yml']));
    }

    public function testHandleUnknownEvent()
    {
        // Always good to test a non-existing event just to make sure nothing happens :).
        $context = new EventContext(
            "e/lotgd/tests/unknown-event",
            "none",
            EventContextData::create([])
        );

        DragonKillModule::handleEvent($this->g, $context);
    }

    protected function goToForest(int $characterId, callable $executeBeforeTakingActionToForest = null): array
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
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 5], "Outside");

        if ($executeBeforeTakingActionToForest !== NULL) {
            $executeBeforeTakingActionToForest($game, $v, $character);
        }

        $game->takeAction($action->getId());
        $this->assertSame("Forest", $v->getTitle());

        return [$game, $v, $character];
    }

    public function testIfConnectionToDragonIsPresentIfCharacterIsLevel15AndHasNotYetSeenDragonAndIfConnectionIsGoneIfHeLeaves()
    {
        [$game, $v, $character] = $this->goToForest(1);

        // Assert action to green dragon and go there
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 6], "Fight");
        $game->takeAction($action->getId());
        $this->assertSame("The Green Dragon", $v->getTitle());

        // Assert action back
        $action1 = $this->assertHasAction($v, ["getTitle", "Enter the cave"], "Dragon's Lair");
        $action2 = $this->assertHasAction($v, ["getTitle", "Run away like a baby"], "Back");
        $action3 = $this->assertHasAction($v, ["getDestinationSceneId", 5], "Back");

        $this->assertSame($action2, $action3);
        $game->takeAction($action3->getId());
        $this->assertSame("Forest", $v->getTitle());

        // Assert action to green dragon disappeared
        $action = $this->assertNotHasAction($v, ["getDestinationSceneId", 6], "Fight");
    }

    public function testIfConnectionToDragonIsPresentIfCharacterHadNewDayReset()
    {
        $character = $this->getEntityManager()->getRepository(Character::class)->find(2);
        $character->setProperty(DragonKillModule::CharacterPropertySeenDragon, true);
        $this->assertTrue($character->getProperty(DragonKillModule::CharacterPropertySeenDragon, null));
        [$game, $v, $character] = $this->goToForest(2);

        // Assert action to green dragon it not there.
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 6], "Fight");
        $this->assertFalse($character->getProperty(DragonKillModule::CharacterPropertySeenDragon, null));
    }
}
