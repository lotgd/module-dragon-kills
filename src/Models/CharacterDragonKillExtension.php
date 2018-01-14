<?php

namespace LotGD\Module\DragonKills\Models;

use LotGD\Core\Doctrine\Annotations\Extension;
use LotGD\Core\Doctrine\Annotations\ExtensionMethod;
use LotGD\Core\Models\Character;
use LotGD\Module\DragonKills\Module as DragonKillModule;

/**
 * Additional API helpers for managing dragon kills on a user account.
 * @package LotGD\Module\DragonKills\Models
 * @Extension(of="LotGD\Core\Models\Character")
 */
class CharacterDragonKillExtension
{
    /**
     * Returns the dragon kill count for a given character.
     * @param Character $character
     * @return int
     * @ExtensionMethod(as="getDragonKillCount")
     */
    public static function getDragonKillCountForCharacter(Character $character): int
    {
        return $character->getProperty(DragonKillModule::CharacterPropertyDragonKills, 0);
    }

    /**
     * Sets the dragon kill count for a given character.
     * @param Character $character
     * @param int $kills
     * @ExtensionMethod(as="setDragonKillCount")
     */
    public static function setDragonKillCountForCharacter(Character $character, int $kills): void
    {
        $character->setProperty(DragonKillModule::CharacterPropertyDragonKills, $kills);
    }

    /**
     * Increments the dragon kill count for a given character by a specified amount.
     * @param Character $character
     * @param int $additional_kills
     * @ExtensionMethod(as="incrementDragonKillCount")
     */
    public static function incrementDragonKillCountForCharacter(Character $character, int $additional_kills = 1): void
    {
        $currentKills = $character->getProperty(DragonKillModule::CharacterPropertyDragonKills, 0);
        $currentKills += $additional_kills;
        $character->setProperty(DragonKillModule::CharacterPropertyDragonKills, $currentKills);
    }

}