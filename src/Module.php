<?php
declare(strict_types=1);

namespace LotGD\Modules\DragonKills;

use LotGD\Core\Game;
use LotGD\Core\Module;

use LotGD\Modules\DragonKills\Models\DragonKill;

class Module implements Module {
    public static function handleEvent(string $event, array $context)
    {
        $g = $context['g'];
        $c = $context['killer'];
        switch ($event) {
            case 'e/lotgd/dragon-kills/kill':
                // Save an entry in the DB for this DK.
                $dk = DragonKill::create([
                    'killer' => $c,
                    'killedAt' => $g->getTimeKeeper()->gameTime();
                ]);
                $dk->save();

                // For ease of access, also store the count on the character.
                $count = $c->getProperty('lotgd/dragon-kills/dk', 0);
                $count++;
                $c->setProperty('lotgd/dragon-kills/dk', $count);
                break;
        }
    }
    public static function onRegister(Game $g) { }
    public static function onUnregister(Game $g) { }
}
