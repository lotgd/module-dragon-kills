<?php
declare(strict_types=1);

use Monolog\Logger;
use Monolog\Handler\NullHandler;

use LotGD\Core\Configuration;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Core\Tests\ModelTestCase;

use LotGD\Modules\DragonKills\Module;

class ModuleTest extends ModelTestCase
{
    const Library = 'lotgd/module-dragon-kills';

    private $g;
    private $moduleModel;

    protected function getDataSet(): \PHPUnit_Extensions_Database_DataSet_YamlDataSet
    {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', 'module.yml']));
    }

    public function setUp()
    {
        parent::setUp();

        // Make an empty logger for these tests. Feel free to change this
        // to place log messages somewhere you can easily find them.
        $logger  = new Logger('test');
        $logger->pushHandler(new NullHandler());

        // Create a Game object for use in these tests.
        $this->g = new Game(new Configuration(getenv('LOTGD_TESTS_CONFIG_PATH')), $logger, $this->getEntityManager(), implode(DIRECTORY_SEPARATOR, [__DIR__, '..']));

        // Register and unregister before/after each test, since
        // handleEvent() calls may expect the module be registered (for example,
        // if they read properties from the model).
        $this->moduleModel = new ModuleModel(self::Library);
        Module::onRegister($this->g, $this->moduleModel);
    }

    public function tearDown()
    {
        parent::tearDown();

        Module::onUnregister($this->g, $this->moduleModel);
    }

    // TODO for LotGD staff: this test assumes the schema in their yaml file
    // reflects all columns in the core's models of characters, scenes and modules.
    // This is pretty fragile since every time we add a column, everyone's tests
    // will break.
    public function testUnregister()
    {
        Module::onUnregister($this->g, $this->moduleModel);

        // Assert that databases are the same before and after.
        // TODO for module author: update list of tables below to include the
        // tables you modify during registration/unregistration.
        $after = $this->getConnection()->createDataSet(['characters', 'scenes', 'modules']);
        $before = $this->getDataSet();

        $this->assertDataSetsEqual($before, $after);

        // Since tearDown() contains an onUnregister() call, this also tests
        // double-unregistering, which should be properly supported by modules.
    }

    public function testHandleUnknownEvent()
    {
        // Always good to test a non-existing event just to make sure nothing happens :).
        $context = [];
        Module::handleEvent($this->g, 'e/lotgd/tests/unknown-event', $context);
    }

    public function testSettersAndGetters()
    {
        $module = new Module($this->g);

        // Test the default.
        $user = $this->g->getEntityManager()->getRepository(Character::class)->find(1);
        $count = $module->getDragonKillsForUser($user);
        $this->assertEquals(0, $count);

        $user = $this->g->getEntityManager()->getRepository(Character::class)->find(2);
        $module->setDragonKillsForUser($user, 42);
        $count = $module->getDragonKillsForUser($user);
        $this->assertEquals(42, $count);
    }

    public function testDKEvent()
    {
        $originalCount = 42;
        
        $module = new Module($this->g);
        $user = $this->g->getEntityManager()->getRepository(Character::class)->find(1);
        $this->g->setCharacter($user);
        $module->setDragonKillsForUser($user, $originalCount);

        $context = [];
        Module::handleEvent($this->g, Module::DragonKilledEvent, $context);

        $count = $module->getDragonKillsForUser($user);
        $this->assertEquals($originalCount + 1, $count);
    }
}
