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

use LotGD\Module\DragonKills\Module;

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

        Module::handleEvent($this->g, $context);
    }
}
