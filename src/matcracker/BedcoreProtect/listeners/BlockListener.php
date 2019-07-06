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
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockLegacyMetadata;
use pocketmine\block\Chest;
use pocketmine\block\Door;
use pocketmine\block\Flowable;
use pocketmine\block\Liquid;
use pocketmine\block\Sign;
use pocketmine\block\tile\Chest as TileChest;
use pocketmine\block\Water;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\math\Facing;

final class BlockListener extends BedcoreListener{
	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackBlockBreak(BlockBreakEvent $event) : void{
		$player = $event->getPlayer();
		if($this->configParser->isEnabledWorld($player->getWorld()) && $this->configParser->getBlockBreak()){
			$block = $event->getBlock();

			if(Inspector::isInspector($player)){ //It checks the block clicked
				$this->database->getQueries()->requestBlockLog($player, $block);
				$event->setCancelled();
			}else{
				$air = BlockUtils::getAir($block->asPosition());

				if($block instanceof Door){
					$top = $block->getMeta() & BlockLegacyMetadata::DOOR_FLAG_TOP;
					$other = $block->getSide($top ? Facing::DOWN : Facing::UP);
					if($other instanceof Door and $other->isSameType($block)){
						$this->database->getQueries()->addBlockLogByEntity($player, $other, $air, Action::BREAK(), $other->asPosition());
					}
				}elseif($block instanceof Bed){
					$other = $block->getOtherHalf();
					if($other instanceof Bed){
						$this->database->getQueries()->addBlockLogByEntity($player, $other, $air, Action::BREAK(), $other->asPosition());
					}
				}elseif($block instanceof Chest){
					$tileChest = $block->getWorld()->getTile($block);
					if($tileChest instanceof TileChest){
						$inventory = $tileChest->getRealInventory();
						if(count($inventory->getContents()) > 0){ //If not empty
							$this->database->getQueries()->addInventoryLogByPlayer($player, $inventory, $block->asPosition());
						}
					}
				}

				$this->database->getQueries()->addBlockLogByEntity($player, $block, $air, Action::BREAK());

				if($this->configParser->getNaturalBreak()){
					/**
					 * @var Block[] $sides
					 * Getting all blocks around the broken block that are @see Flowable
					 */
					$sides = array_filter($block->getAllSides(), function(Block $side){
						return $side instanceof Flowable || $side instanceof Sign;
					});
					/**
					 * @var Block[] $airs
					 */
					foreach($sides as $key => $side){
						$airs[$key] = BlockUtils::getAir($side->asPosition());
					}

					if(!empty($sides)){
						$this->database->getQueries()->addBlocksLogByEntity($player, $sides, $airs, Action::BREAK());
					}
				}
			}
		}
	}

	/**
	 * @param BlockPlaceEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackBlockPlace(BlockPlaceEvent $event) : void{
		$player = $event->getPlayer();
		if($this->configParser->isEnabledWorld($player->getWorld()) && $this->configParser->getBlockPlace()){
			$block = $event->getBlock();
			$replacedBlock = $event->getBlockReplaced();

			if(Inspector::isInspector($player)){ //It checks the block where the player places.
				$this->database->getQueries()->requestBlockLog($player, $replacedBlock);
				$event->setCancelled();
			}else{
				$otherHalfPos = null;
				if($block instanceof Bed){
					$facing = $player->getHorizontalFacing();
					$otherHalfPos = $block->getSide($block->isHeadPart() ? Facing::opposite($facing) : $facing)->asPosition();
				}else if($block instanceof Door){
					$otherHalfPos = $block->getSide(Facing::UP)->asPosition();
				}

				$this->database->getQueries()->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE());

				if($otherHalfPos !== null){
					$this->database->getQueries()->addBlockLogByEntity($player, $replacedBlock, $block, Action::PLACE(), $otherHalfPos);
				}
			}
		}
	}

	/**
	 * @param BlockSpreadEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackBlockSpread(BlockSpreadEvent $event) : void{
		$block = $event->getBlock();
		$source = $event->getSource();
		$newState = $event->getNewState();

		/*var_dump("SPREAD BLOCK: " . $event->getBlock()->getName());
		var_dump("SPREAD NEW STATE: " . $event->getNewState()->getName());
		var_dump("SPREAD SOURCE: " . $source->getName());*/

		/*print_r("SOURCE(" . $source->getName() . ")\n" . $source->asPosition());
		print_r("\nBLOCK(" . $block->getName() . ")\n" . $block->asPosition());
		print_r("\nNEW STATE(" . $newState->getName() . ")\n" . $newState->asPosition() . "\n\n");*/

		if($this->configParser->isEnabledWorld($block->getWorld())){
			if($source instanceof Liquid){
				//var_dump($source->getFlowVector());
				if(BlockUtils::isStillLiquid($source)){

					/*print_r("SOURCE(" . $source->getName() . ")\n" . $source->asPosition());
					print_r("\nBLOCK(" . $block->getName() . ")\n" . $block->asPosition());
					print_r("\nNEW STATE(" . $newState->getName() . ")\n" . $newState->asPosition() . "\n\n");*/

					$this->database->getQueries()->addBlockLogByBlock($source, $block, $source, Action::PLACE());
				} //TODO: Find player who place water

			}
		}
	}

	/**
	 * @param BlockBurnEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackBlockBurn(BlockBurnEvent $event) : void{
		$block = $event->getBlock();
		if($this->configParser->isEnabledWorld($block->getWorld()) && $this->configParser->getBlockBurn()){
			$cause = $event->getCausingBlock();

			$this->database->getQueries()->addBlockLogByBlock($cause, $block, $cause, Action::BREAK());
		}
	}

	/**
	 * @param BlockFormEvent $event
	 *
	 * @priority MONITOR
	 */
	public function trackBlockForm(BlockFormEvent $event) : void{
		$block = $event->getBlock();
		$result = $event->getNewState();

		if($this->configParser->isEnabledWorld($block->getWorld())){
			if($block instanceof Liquid && $this->configParser->getLiquidTracking()){
				$id = $block instanceof Water ? BlockLegacyIds::LAVA : BlockLegacyIds::WATER;
				$this->database->getQueries()->addBlockLogByBlock(BlockFactory::get($id), $block, $result, Action::PLACE(), $block->asPosition());
			}
		}

		/*var_dump("FORM BLOCK: " . $event->getBlock()->getName());
		var_dump("FORM NEW STATE: " . $event->getNewState()->getName());*/
	}

	/*public function testGrow(BlockGrowEvent $event) : void{
		var_dump("GROW BLOCK: " . $event->getBlock()->getName());
		var_dump("GROW NEW STATE: " . $event->getNewState()->getName());
	}*/

}