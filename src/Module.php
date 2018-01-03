<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills;

use Composer\Script\Event;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\DragonKills\Models\DragonKill;

class Module implements ModuleInterface {
    const CharacterPropertyDragonKills = 'lotgd/dragon-kills/dk';
    const DragonKilledEvent = 'e/lotgd/dragon-kills/kill';
    private $g;

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $event = $context->getEvent();

        switch ($event) {
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

    }

    public static function onUnregister(Game $g, ModuleModel $module)
    {

    }
}
