<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Scenes;

use Doctrine\Common\Util\Debug;
use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Battle;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;
use LotGD\Core\Models\Viewpoint;
use LotGD\Module\DragonKills\Module as DragonKillsModule;
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\Scenes\Forest;
use LotGD\Module\Res\Fight\Fight;
use LotGD\Module\Village\Module as VillageModule;

class DragonScene
{
    const Template = DragonKillsModule::ModuleIdentifier . "/dragon";
    const ActionGroups = [
        "dragon" => [DragonKillsModule::ModuleIdentifier . "/dragon/dragon", "Dragon's Lair", 0],
        "back" => [DragonKillsModule::ModuleIdentifier . "/dragon/back", "Back", 100],
    ];
    const BattleContext = DragonKillsModule::ModuleIdentifier . "/greenDragonFight";

    public static function create(): Scene
    {
        $scene = Scene::create([
            "template" => self::Template,
            "title" => "The Green Dragon",
            "description" => <<<TXT
You approach the blackened entrance of a cave deep in the forest, though the trees are scorched to stumps for a hundred 
yards all around. A thin tendril of smoke escapes the root of the cave's entrance, and is whisked away by a suddenly cold
and brist wind. The mouth of the cave lies up a dozen feet from the forest floor, set in the side of a cliff, with debris
making a conical ramp to the opening. Stalactites and stalagmites near the entrance trigger your imagination to inspire
thoughts that the opening is really the mouth of a great leech.

You cautiously approach the entrance of the cave, and as you do, you hear, or perhaps feel a deep rumble that lasts thirty
seconds or so, before silencing to a breeze of sulfur-air which wafts out of the cave. The sound starts again, and stops again
in a regular rhythm.

You clamber up the debris pile leading to the mouth of the cave, your feet crunching on the apparent remains of previous heroes,
or perhaps hors d'oeuvre.

Every instinct in your body wants to run, and run quickly, back to the parts of the forest where it is warmer. What do you do?
TXT
        ]);

        foreach (self::ActionGroups as $actionGroup) {
            $actionGroup = new SceneConnectionGroup($actionGroup[0], $actionGroup[1]);
            $scene->addConnectionGroup($actionGroup);
        }

        return $scene;
    }

    /**
     * Adds an action point to seek out the dragon in the forest if the character is deemed ready.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function forestNavigationHook(Game $g, EventContext $context): EventContext
    {
        /** @var $viewpoint Viewpoint */
        $viewpoint = $context->getDataField("viewpoint");
        $character = $g->getCharacter();

        # Do nothing if lvl < 15 or character has seen dragon already today or if he is dead.
        if (
            $character->getLevel() < 15
            or $character->getProperty(DragonKillsModule::CharacterPropertySeenDragon, false) === true
            or $character->isAlive() === false
        ) {
           return $context;
        }

        # Get all connected scenes and save the connect dragonkill scenes in an array
        $scenes = $viewpoint->getScene()->getConnectedScenes();
        $dragonScenes = [];
        /** @var Scene $scene */
        foreach ($scenes as $scene) {
            if ($scene->getTemplate() == DragonScene::Template) {
                $dragonScenes[] = $scene;
            }
        }

        $fightGroup = $viewpoint->findActionGroupById(Forest::Groups["fight"][0]);

        # add an action to every dragon kill scene.
        foreach ($dragonScenes as $scene) {
            $fightGroup->addAction(new Action($scene->getId(), sprintf("Seek out %s", $scene->getTitle())));
        }

        return $context;
    }

    /**
     * Handles the event if character navigates to a DragonScene templated scene.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function navigateToScene(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $viewpoint */
        $viewpoint = $context->getDataField("viewpoint");
        /** @var array $parameters */
        $parameters = $context->getDataField("parameters");

        if (empty($parameters["subAction"])) {
            # No subAction => display intro.

            # Rename the back action
            /** @var Action $backAction */
            $backAction = $viewpoint->findActionGroupById(self::ActionGroups["back"][0])->getActions()[0];
            $backAction->setTitle("Run away like a baby");

            # Add the deeper action
            if ($viewpoint->hasActionGroup(self::ActionGroups["dragon"][0])) {
                $dragonActions = $viewpoint->findActionGroupById(self::ActionGroups["dragon"][0]);
            } else {
                $dragonActions = new ActionGroup(self::ActionGroups["dragon"][0], self::ActionGroups["dragon"][1], self::ActionGroups["dragon"][2]);
                $viewpoint->addActionGroup($dragonActions);
            }
            $dragonActions->addAction(new Action($viewpoint->getScene()->getId(), "Enter the cave", ["subAction" => "enter"]));

            # Set "user has seen dragon today" to true.
            $g->getCharacter()->setProperty(DragonKillsModule::CharacterPropertySeenDragon, true);
        } elseif ($parameters["subAction"] === "enter") {
            $viewpoint->setDescription("Fighting down every urge to flee, you cautiously enter the cave entrance, intent on catching "
                . "the great green dragon sleeping, so that you might slay it with a minimum of pain. Sadly, this is not to be the case, "
                . "as you round a corner within the cave you discover the great beast sitting on its haunches on a huge pile of gold, "
                . "picking its teeth with a rib.");

            $greenDragon = new Creature();
            $greenDragon->setName("The Green Dragon");
            $greenDragon->setWeapon("Great Flaming Maw");
            $greenDragon->setLevel(18);
            $greenDragon->setAttack(45);
            $greenDragon->setDefense(25);
            $greenDragon->setMaxHealth(300);
            $greenDragon->setHealth(300);

            $fight = Fight::start($g, $greenDragon, $viewpoint->getScene(), self::BattleContext);
            $fight->showFightActions();
            $fight->suspend();
        } elseif ($parameters["subAction"] === "epilogue") {

        }

        return $context;
    }

    /**
     * Handles the event if the battle is over.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function battleOver(Game $g, EventContext $context): EventContext
    {
        $battleIdentifier = $context->getDataField("battleIdentifier");

        if ($battleIdentifier === self::BattleContext) {
            /** @var Battle $battle */
            $battle = $context->getDataField("battle");
            /** @var Viewpoint $viewpoint */
            $viewpoint = $context->getDataField("viewpoint");
            $referrerSceneId = $context->getDataField("referrerSceneId");
            $character = $g->getCharacter();

            if ($battle->getWinner() === $character) {
                $viewpoint->setTitle("Victory!");
                $viewpoint->setDescription("With a mighty final blow, the Green Dragon lets out a tremendous bellow and falls at your feet, dead at last.");

                $defaultGroup = $viewpoint->findActionGroupById(ActionGroup::DefaultGroup);
                $defaultGroup->addAction(new Action($referrerSceneId, "Continue", ["subAction" => "epilogue"]));
            } else {
                $viewpoint->setTitle("Defeat!");
                $viewpoint->setDescription("You have been slain by the Green Dragon!!!");
                $viewpoint->addDescriptionParagraph("You might challenge him tomorrow again.");

                // Find village scene by getting the forest and find a connected village.
                /** @var Scene $dragonScene */
                $dragonScene = $g->getEntityManager()->getRepository(Scene::class)->find($referrerSceneId);
                $forestScene = $dragonScene->getConnectedScenes()->filter(function (Scene $scene) {
                    return ($scene->getTemplate() === Forest::Template);
                })->first();
                $villageScene = $forestScene->getConnectedScenes()->filter(function (Scene $scene) {
                    return ($scene->getTemplate() === VillageModule::VillageScene);
                })->first();

                $defaultGroup = $viewpoint->findActionGroupById(ActionGroup::DefaultGroup);
                $defaultGroup->addAction(new Action($villageScene->getId(), "Return to the village", ["subAction" => "epilogue"]));
            }
        }

        return $context;
    }
}