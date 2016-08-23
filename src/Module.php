<?php
declare(strict_types=1);

namespace LotGD\Modules\DragonKills;

use LotGD\Core\Game;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Modules\DragonKills\Models\DragonKill;

class Module implements ModuleInterface {
    const DragonKillsProperty = 'lotgd/dragon-kills/dk';
    const DragonKilledEvent = 'e/lotgd/dragon-kills/kill';
    private $g;

    public static function handleEvent(Game $g, string $event, array &$context)
    {
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
    }
    public static function onRegister(Game $g, ModuleModel $module) { }
    public static function onUnregister(Game $g, ModuleModel $module) { }

    public function __construct(Game $g)
    {
        $this->g = $g;
    }

    public function getDragonKillsForUser(Character $c)
    {
        return $c->getProperty(self::DragonKillsProperty, 0);
    }

    public function setDragonKillsForUser(Character $c, int $count)
    {
        $c->setProperty(self::DragonKillsProperty, $count);
    }
}
