<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Tests\Helpers;

use LotGD\Core\EventHandler;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Module\DragonKills\Module;

class DragonKillsEvent implements EventHandler
{
    public static $called = 0;

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $event = $context->getEvent();

        if ($event === Module::DragonKilledEvent) {
            self::$called += 1;
        }

        return $context;
    }
}