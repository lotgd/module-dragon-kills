<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Tests\Helpers;

use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;

class DragonKillsEvent
{
    public static $called = 0;

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        self::$called += 1;
        return $context;
    }
}