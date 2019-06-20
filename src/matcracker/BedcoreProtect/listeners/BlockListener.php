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

namespace matcracker\BedcoreProtect\listeners;

use matcracker\BedcoreProtect\Inspector;
use matcracker\BedcoreProtect\storage\queries\QueriesConst;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Liquid;
use pocketmine\block\Sign;
use pocketmine\block\Water;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;

final class BlockListener extends BedcoreListener
{
    /**
     * @param BlockBreakEvent $event
     * @priority MONITOR
     */
    public function trackBlockBreak(BlockBreakEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getBlockBreak()) {
            $player = $event->getPlayer();
            $block = $event->getBlock();

            if (Inspector::isInspector($player)) { //It checks the block clicked //TODO: Move out of there this check in a properly listener
                $this->database->getQueries()->requestBlockLog($player, $block);
                $event->setCancelled();
            } else {
                if ($block instanceof Sign) {
                    if ($this->plugin->getParsedConfig()->getSignText()) {
                        $this->database->getQueries()->addSignLogByPlayer($player, $block);
                    }
                } else {
                    $air = BlockUtils::createAir($block->asPosition());
                    $this->database->getQueries()->addBlockLogByEntity($player, $block, $air, QueriesConst::BROKE);
                }
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority MONITOR
     */
    public function trackBlockPlace(BlockPlaceEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getBlockPlace()) {
            $player = $event->getPlayer();
            $block = $event->getBlock();
            $replacedBlock = $event->getBlockReplaced();

            if (Inspector::isInspector($player)) { //It checks the block where the player places. //TODO: Move out of there this check in a properly listener
                $this->database->getQueries()->requestBlockLog($player, $replacedBlock);
                $event->setCancelled();
            } else {
                /*if ($block instanceof Bed) {
                    $half = $block->getOtherHalf();
                    var_dump($half);
                    if ($half !== null) {
                        $this->database->getQueries()->logPlayer($player, $replacedBlock, $half, Queries::PLACED);
                    }
                } else if ($block instanceof Door) {
                    $upperDoor = BlockFactory::get($block->getId(), $block->getDamage() | 0x01, $block->asPosition())
                    $this->database->getQueries()->logPlayer($player, $replacedBlock, $upperDoor, Queries::PLACED);

                }*/
                $this->database->getQueries()->addBlockLogByEntity($player, $replacedBlock, $block, QueriesConst::PLACED);
            }
        }
    }

    /**
     * @param BlockSpreadEvent $event
     * @priority MONITOR
     */
    public function trackBlockSpread(BlockSpreadEvent $event): void
    {
        $block = $event->getBlock();
        $source = $event->getSource();
        $newState = $event->getNewState();

        /*print_r("SOURCE(" . $source->getName() . ")\n" . $source->asPosition());
        print_r("\nBLOCK(" . $block->getName() . ")\n" . $block->asPosition());
        print_r("\nNEW STATE(" . $newState->getName() . ")\n" . $newState->asPosition() . "\n\n");*/

        if ($source instanceof Liquid) {
            //var_dump($source->getFlowVector());
            if (BlockUtils::isStillLiquid($source)) {

                /*print_r("SOURCE(" . $source->getName() . ")\n" . $source->asPosition());
                print_r("\nBLOCK(" . $block->getName() . ")\n" . $block->asPosition());
                print_r("\nNEW STATE(" . $newState->getName() . ")\n" . $newState->asPosition() . "\n\n");*/

                $this->database->getQueries()->addBlockLogByBlock($source, $block, $source, QueriesConst::PLACED);
            } //TODO: Find player who place water

        }
    }

    /**
     * @param BlockBurnEvent $event
     * @priority MONITOR
     */
    public function trackBlockBurn(BlockBurnEvent $event): void
    {
        if ($this->plugin->getParsedConfig()->getBlockIgnite()) {
            $block = $event->getBlock();
            $cause = $event->getCausingBlock();

            $this->database->getQueries()->addBlockLogByBlock($cause, $block, $cause, QueriesConst::BROKE);
        }
    }

    /**
     * @param BlockFormEvent $event
     * @priority MONITOR
     */
    public function trackBlockForm(BlockFormEvent $event): void
    {
        $block = $event->getBlock();
        $result = $event->getNewState();

        if ($block instanceof Liquid) {
            $id = $block instanceof Water ? BlockLegacyIds::LAVA : BlockLegacyIds::WATER;
            $this->database->getQueries()->addBlockLogByBlock(BlockFactory::get($id), $block, $result, QueriesConst::PLACED, $block->asPosition());
        }
    }

    public function testGrow(BlockGrowEvent $event): void
    {
        //TODO
    }

}