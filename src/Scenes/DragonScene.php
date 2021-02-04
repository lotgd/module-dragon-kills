<?php
declare(strict_types=1);

namespace LotGD\Module\DragonKills\Scenes;

use DateTime;
use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Battle;
use LotGD\Core\Events\CharacterEventData;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;
use LotGD\Core\Models\SceneTemplate;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\SceneTemplates\SceneTemplateInterface;
use LotGD\Module\DragonKills\Module;
use LotGD\Module\DragonKills\Module as DragonKillsModule;
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\SceneTemplates\Forest;
use LotGD\Module\Res\Fight\Fight;
use LotGD\Module\Village\Module as VillageModule;
use LotGD\Module\NewDay\Module as NewDayModule;
use LotGD\Module\Village\SceneTemplates\VillageScene;

class DragonScene implements SceneTemplateInterface
{
    const Template = DragonKillsModule::ModuleIdentifier . "/dragon";
    const ActionGroups = [
        "dragon" => [DragonKillsModule::ModuleIdentifier . "/dragon/dragon", "Dragon's Lair", 0],
        "back" => [DragonKillsModule::ModuleIdentifier . "/dragon/back", "Back", 100],
    ];
    const BattleContext = DragonKillsModule::ModuleIdentifier . "/greenDragonFight";

    private static ?SceneTemplate $template = null;

    public static function getNavigationEvent(): string
    {
        return self::Template;
    }

    public static function getScaffold(): Scene
    {
        if (self::$template === null) {
            self::$template = new SceneTemplate(self::class, Module::ModuleIdentifier);
        }

        $scene = new Scene(
            title: "The Green Dragon",
            description: <<<TXT
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
            TXT,
            template: self::$template,
        );

        # Add action groups
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
            if ($scene->getTemplate()?->getClass() === DragonScene::class) {
                $dragonScenes[] = $scene;
            }
        }

        $fightGroup = $viewpoint->findActionGroupById(Forest::Groups["fight"][0]);

        # add an action to every dragon kill scene.
        foreach ($dragonScenes as $scene) {
            $g->getLogger()->debug("module-dragon-kills: {$character} can seek out the dragon.");
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
            $dragonActionGroup = $viewpoint->findActionGroupById(self::ActionGroups["dragon"][0]);
            if ($dragonActionGroup) {
                $dragonActions = $viewpoint->findActionGroupById(self::ActionGroups["dragon"][0]);
            } else {
                $dragonActions = new ActionGroup(self::ActionGroups["dragon"][0], self::ActionGroups["dragon"][1], self::ActionGroups["dragon"][2]);
                $viewpoint->addActionGroup($dragonActions);
            }
            $dragonActions->addAction(new Action($viewpoint->getScene()->getId(), "Enter the cave", ["subAction" => "enter"]));

            # Set "user has seen dragon today" to true.
            $g->getCharacter()->setProperty(DragonKillsModule::CharacterPropertySeenDragon, true);
        } elseif ($parameters["subAction"] === "enter") {
            $viewpoint->setDescription(<<<TXT
                Fighting down every urge to flee, you cautiously enter the cave entrance, intent on catching the great 
                green dragon sleeping, so that you might slay it with a minimum of pain. Sadly, this is not to be the 
                case, as you round a corner within the cave you discover the great beast sitting on its haunches on a 
                huge pile of gold, picking its teeth with a rib."
            TXT);

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
            $viewpoint->setTitle("Victory!");
            $viewpoint->setDescription(<<<TXT
                Before you, the great dragon lies immobile, its heavy breathing like acid to your lungs. You are 
                covered, head to toe, with the foul creature's thick black blood. The great beast begins to move its 
                mouth. You spring back, angry at yourself for having been fooled by its ploy of death, and watch for its 
                huge tail to come sweeping your way. But it does not. Instead the dragon begins to speak.
                
                "Why have you come here mortal? What have I done to you?" it says with obvious effort. "Always my kind 
                are sought out to be destroyed. Why? Because of stories from distant lands that tell of dragons preying 
                on the weak? I well you that these stories come only from misunderstanding of us, and not because we 
                devour your children." The beast pauses, breathing heavily before continuing, "I will tell you a secret.
                Behing me now are my eggs. They will hatch, and the young will battle each other. Only one will survive, 
                but she will be the strongest. She will quickly grow, and be as powerful as me." Breath comes shorter 
                and shallower for the great beast.
                
                "Why do you tell me this? Don't you know that I will destroy your eggs?" you ask.
                
                "No, you will not, for I know one more secret that you do not."
                
                "Pray tell oh mighty beast!"
                
                The great beast pauses, gathering the last of its energy. "Your kind cannot tolerate the blood of my 
                kind. Even if you survive, you will be a feeble creature, barely able to hold a weapon, your mind blank 
                of all that you have learned. No, you are no threat to my children, for you are already dead!"
                
                Realizing that already the edges of your vision are a little dim, you flee from the cave, bound to reach
                the healer's hut before it is too late. Somewhere along the way you lose your weapon, and finally you 
                trip on a stone in a shallow stream, sight now limited to only a small circle that seems to floar around
                your head. As you lay, staring up through the trees, you think that nearby you can hear the sounds of 
                the village. Your final thought is that although you defeated the dragon, you reflect on the irony that 
                it defeated you.
                
                As your vision winks out, far away in the dragon's lair, an egg shuffles to its side, and a small crack 
                appears in its thick leathery skin.
            TXT);

            $g->getEventManager()->publish(
                DragonKillsModule::DragonKilledEvent,
                CharacterEventData::create(["character" => $g->getCharacter(), "value" => null])
            );

            // Set back last new day and set action to village
            $g->getCharacter()->setProperty(
                NewDayModule::CharacterPropertyLastNewDay,
                new DateTime("now - 1 year")
            );


            self::addActionToVillage($g, $viewpoint, $viewpoint->getScene()->getId(), "It is a new day");
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

                $g->getLogger()->debug("module-dragon-kills: {$character} battled a dragon, and won.");
            } else {
                $viewpoint->setTitle("Defeat!");
                $viewpoint->setDescription("You have been slain by the Green Dragon!!!");
                $viewpoint->addDescriptionParagraph("You might challenge him tomorrow again.");

                self::addActionToVillage($g, $viewpoint, $referrerSceneId, "Return to the village");
                $g->getLogger()->debug("module-dragon-kills: {$character} battled a dragon, and lost.");
            }
        }

        return $context;
    }

    /**
     * Helper function to add an action to the village bound to the
     * @param Game $g
     * @param Viewpoint $viewpoint
     * @param int $referrerSceneId
     * @param string $title
     * @param array $parameters
     */
    private static function addActionToVillage(Game $g, Viewpoint $viewpoint, string $referrerSceneId, string $title, array $parameters = []): void
    {
        // Find village scene by getting the forest and find a connected village.
        /** @var Scene $dragonScene */
        $dragonScene = $g->getEntityManager()->getRepository(Scene::class)->find($referrerSceneId);
        $forestScene = $dragonScene->getConnectedScenes()->filter(function (Scene $scene) {
            return ($scene->getTemplate()?->getClass() === Forest::class);
        })->first();
        $villageScene = $forestScene->getConnectedScenes()->filter(function (Scene $scene) {
            return ($scene->getTemplate()?->getClass() === VillageScene::class);
        })->first();

        $defaultGroup = $viewpoint->findActionGroupById(ActionGroup::DefaultGroup);
        $defaultGroup->addAction(new Action($villageScene->getId(), $title, $parameters));
    }
}