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

namespace matcracker\BedcoreProtect\storage\queries;

use matcracker\BedcoreProtect\commands\CommandParser;
use matcracker\BedcoreProtect\utils\Action;
use matcracker\BedcoreProtect\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\level\Position;
use poggit\libasynql\SqlError;

/**
 * It contains all the queries methods related to entities.
 *
 * Trait QueriesEntitiesTrait
 * @package matcracker\BedcoreProtect\storage\queries
 */
trait QueriesEntitiesTrait
{

    public function addLogEntityByEntity(Entity $damager, Entity $entity, Action $action): void
    {
        $this->addEntity($damager);
        $this->addEntity($entity);

        $this->addRawLog(Utils::getEntityUniqueId($damager), $entity, $action);
        $entity->saveNBT();
        $entity->namedtag->setFloat("Health", $entity->getMaxHealth());

        $this->connector->executeInsert(QueriesConst::ADD_ENTITY_LOG, [
            "uuid" => Utils::getEntityUniqueId($entity),
            "id" => $entity->getId(),
            "nbt" => Utils::serializeNBT($entity->namedtag)
        ]);
    }

    public function addEntity(Entity $entity): void
    {
        $this->addRawEntity(Utils::getEntityUniqueId($entity), Utils::getEntityName($entity), get_class($entity), ($entity instanceof Player) ? $entity->getNetworkSession()->getIp() : "127.0.0.1");
    }

    protected final function addRawEntity(string $uuid, string $name, string $classPath = "", string $address = "127.0.0.1"): void
    {
        $this->connector->executeInsert(QueriesConst::ADD_ENTITY, [
            "uuid" => $uuid,
            "name" => $name,
            "path" => $classPath,
            "address" => $address
        ]);
    }

    protected function rollbackEntities(Position $position, CommandParser $parser): int
    {
        return $this->executeEntitiesEdit(true, $position, $parser);
    }

    private function executeEntitiesEdit(bool $rollback, Position $position, CommandParser $parser): int
    {
        $query = $parser->buildEntitiesLogSelectionQuery($position, !$rollback);
        $totalRows = 0;
        $world = $position->getLevel();
        $this->connector->executeSelectRaw($query, [],
            function (array $rows) use ($rollback, $world, &$totalRows) {
                if (count($rows) > 0) {
                    $query = /**@lang text */
                        "UPDATE log_history SET rollback = '{$rollback}' WHERE ";

                    foreach ($rows as $row) {
                        $logId = (int)$row["log_id"];
                        $action = Action::fromType((int)$row["action"]);
                        if (($rollback && $action->equals(Action::SPAWN())) || (!$rollback && !$action->equals(Action::SPAWN()))) {
                            $id = (int)$row["entityfrom_id"];
                            $entity = $world->getEntity($id);
                            if ($entity !== null) {
                                $entity->close();
                            }
                        } else {
                            $entityClass = (string)$row["entity_classpath"];
                            $nbt = Utils::deserializeNBT($row["entityfrom_nbt"]);
                            $entity = Entity::createEntity($entityClass, $world, $nbt);
                            $this->updateEntityId($logId, $entity);
                            $entity->spawnToAll();
                        }

                        $query .= "log_id = '$logId' OR ";
                    }

                    $query = mb_substr($query, 0, -4) . ";";
                    $this->connector->executeInsertRaw($query);
                }
                $totalRows = count($rows);
            },
            static function (SqlError $error) {
                throw $error;
            }
        );
        $this->connector->waitAll();

        return $totalRows;
    }

    protected final function updateEntityId(int $logId, Entity $entity)
    {
        $this->connector->executeInsert(QueriesConst::UPDATE_ENTITY_ID, [
            "log_id" => $logId,
            "entity_id" => $entity->getId()
        ]);
    }

    protected function restoreEntities(Position $position, CommandParser $parser): int
    {
        return $this->executeEntitiesEdit(false, $position, $parser);
    }
}