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
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\BlockUtils;
use pocketmine\block\ItemFrame;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\BlockInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\world\Position;

final class PlayerListener extends BedcoreListener
{
    /**
     * @param PlayerJoinEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $this->database->getQueries()->addEntity($event->getPlayer());
    }

    /**
     * @param PlayerQuitEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        Inspector::removeInspector($event->getPlayer());
    }

    /**
     * @param PlayerBucketEvent $event
     *
     * @priority MONITOR
     */
    public function trackPlayerBucket(PlayerBucketEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->configParser->isEnabledWorld($player->getWorld()) && $this->configParser->getBuckets()) {
            $block = $event->getBlockClicked();
            $fireEmptyEvent = ($event instanceof PlayerBucketEmptyEvent);

            $bucketMeta = $fireEmptyEvent ? $event->getBucket()->getMeta() : $event->getItem()->getMeta();

            $liquid = VanillaBlocks::WATER();
            if ($bucketMeta === VanillaBlocks::LAVA()->getId()) {
                $liquid = VanillaBlocks::LAVA();
            }

            $liquid->position($block->getWorld(), $block->getFloorX(), $block->getFloorY(), $block->getFloorZ());

            if ($fireEmptyEvent) {
                $this->database->getQueries()->addBlockLogByEntity($player, $block, $liquid, Action::PLACE(), $block->asPosition());
            } else {
                $liquidPos = null;
                $face = $event->getBlockFace();
                if ($face === Facing::DOWN) {
                    $liquidPos = Position::fromObject($liquid->add(0, 1, 0), $liquid->getWorld());
                } else if ($face === Facing::UP) {
                    $liquidPos = Position::fromObject($liquid->subtract(0, 1, 0), $liquid->getWorld());
                }

                $this->database->getQueries()->addBlockLogByEntity($player, $liquid, $block, Action::BREAK(), $liquidPos);
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority MONITOR
     */
    public function trackPlayerInteraction(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->configParser->isEnabledWorld($player->getWorld())) {
            $clickedBlock = $event->getBlock();
            $item = $event->getItem();
            $action = $event->getAction();
            $face = $event->getFace();

            if (Inspector::isInspector($player)) {
                $event->setCancelled();
                if (BlockUtils::hasInventory($clickedBlock) || $clickedBlock instanceof ItemFrame) {
                    $this->database->getQueries()->requestTransactionLog($player, $clickedBlock);
                } else {
                    $this->database->getQueries()->requestBlockLog($player, $clickedBlock);
                }

                return;
            }

            if (!$event->isCancelled()) {
                if ($action === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                    $relativeBlock = $clickedBlock->getSide($face);
                    if ($this->plugin->getParsedConfig()->getBlockBreak() && $relativeBlock->isSameType(VanillaBlocks::FIRE())) {
                        $this->database->getQueries()->addBlockLogByEntity($player, $relativeBlock, VanillaBlocks::AIR(), Action::BREAK(), $relativeBlock->asPosition());
                    } else if ($clickedBlock instanceof ItemFrame) {
                        $framedItem = $clickedBlock->getFramedItem();
                        if ($framedItem !== null) {
                            $event->setCancelled();
                            $oldNbt = BlockUtils::getCompoundTag($clickedBlock);
                            if ($clickedBlock->onAttack($item, $face, $player)) {
                                //I consider the ItemFrame as fake inventory holder just only to log adding/removing framed item.
                                $this->database->getQueries()->addItemFrameLogByPlayer($player, $clickedBlock, $oldNbt, Action::REMOVE());
                            }
                        }
                    }

                } else if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                    if ($this->plugin->getParsedConfig()->getBlockPlace() && $item->equals(VanillaItems::FLINT_AND_STEEL(), false, false)) {
                        $this->database->getQueries()->addBlockLogByEntity($player, VanillaBlocks::AIR(), VanillaBlocks::FIRE(), Action::PLACE(), $clickedBlock->getSide($face)->asPosition());
                    } else if ($this->plugin->getParsedConfig()->getPlayerInteractions() && BlockUtils::canBeClicked($clickedBlock)) {
                        if ($clickedBlock instanceof ItemFrame) {
                            $framedItem = $clickedBlock->getFramedItem();
                            if ($framedItem === null) {
                                $event->setCancelled();
                                $oldNbt = BlockUtils::getCompoundTag($clickedBlock);
                                if ($clickedBlock->onInteract($item, $face, $player->getDirectionVector(), $player)) {
                                    //I consider the ItemFrame as fake inventory holder just only to log adding/removing framed item.
                                    $this->database->getQueries()->addItemFrameLogByPlayer($player, $clickedBlock, $oldNbt, Action::ADD());

                                    return;
                                }
                            }
                        }
                        $this->database->getQueries()->addBlockLogByEntity($player, $clickedBlock, $clickedBlock, Action::CLICK());
                    }

                }
            }
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     *
     * @priority MONITOR
     */
    public function trackInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();

        if ($this->configParser->isEnabledWorld($player->getWorld()) && $this->configParser->getItemTransactions()) {
            $actions = $transaction->getActions();

            foreach ($actions as $action) {
                if ($action instanceof SlotChangeAction && $action->getInventory() instanceof BlockInventory) {
                    $this->database->getQueries()->addInventorySlotLogByPlayer($player, $action);
                    break;
                }
            }
        }
    }
}