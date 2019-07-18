<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author matcracker
 * @link https://www.github.com/matcracker/BedcoreProtect
 *
*/

declare(strict_types=1);

namespace matcracker\BedcoreProtect\utils;

use pocketmine\block\Anvil;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BrewingStand;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\EnchantingTable;
use pocketmine\block\EnderChest;
use pocketmine\block\Furnace;
use pocketmine\block\Hopper;
use pocketmine\block\ItemFrame;
use pocketmine\block\StoneButton;
use pocketmine\block\tile\Tile;
use pocketmine\block\Trapdoor;
use pocketmine\block\TrappedChest;
use pocketmine\block\WoodenButton;
use pocketmine\block\WoodenDoor;
use pocketmine\block\WoodenTrapdoor;
use pocketmine\nbt\tag\CompoundTag;

final class BlockUtils
{
    private function __construct()
    {
    }

    /**
     * Returns true if the Block can be clicked
     *
     * @param Block $block
     *
     * @return bool
     */
    public static function canBeClicked(Block $block): bool
    {
        $blocks = [
            WoodenDoor::class, Door::class,
            WoodenTrapdoor::class, Trapdoor::class,//Remove Trapdoor and Door classes when PM-MP supports redstone.
            Bed::class, ItemFrame::class,
            WoodenButton::class, StoneButton::class
        ];

        return in_array(get_class($block), $blocks) || self::hasInventory($block);
    }

    /**
     * Returns true if the block contains inventory.
     *
     * @param Block $block
     *
     * @return bool
     */
    public static function hasInventory(Block $block): bool
    {
        $blocks = [
            EnderChest::class, TrappedChest::class,
            Chest::class, Furnace::class, EnchantingTable::class,
            Anvil::class, BrewingStand::class, Hopper::class
        ];

        return in_array(get_class($block), $blocks);
    }

    /**
     * Serialize a block (tile) NBT into base64. Returns null if block doesn't contain NBT.
     *
     * @param Block $block
     *
     * @return string|null
     */
    public static function serializeBlockTileNBT(Block $block): ?string
    {
        if (($tag = self::getCompoundTag($block)) !== null) {
            return Utils::serializeNBT($tag);
        }

        return null;
    }

    /**
     * Returns the CompoundTag of block if it exists, else returns null.
     *
     * @param Block $block
     *
     * @return CompoundTag|null
     */
    public static function getCompoundTag(Block $block): ?CompoundTag
    {
        if (($tile = self::asTile($block)) !== null) {
            return $tile->getCleanedNBT();
        }

        return null;
    }


    /**
     * Returns a Tile instance of the given block if it exists.
     *
     * @param Block $block
     *
     * @return Tile|null
     */
    public static function asTile(Block $block): ?Tile
    {
        if ($block->getWorld() === null) return null;

        return $block->getWorld()->getTile($block->asPosition());
    }
}