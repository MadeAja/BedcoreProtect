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

use DateTimeZone;
use matcracker\BedcoreProtect\Main;
use Particle\Validator\ValidationResult;
use Particle\Validator\Validator;
use pocketmine\world\World;

/**
 * It parses the plugin configuration to be an object.
 *
 * Class ConfigParser
 * @package matcracker\BedcoreProtect\utils
 */
final class ConfigParser
{
    private $data;

    public function __construct(Main $main)
    {
        $this->data = $main->getConfig()->getAll();
    }

    public function isSQLite(): bool
    {
        return $this->getDatabaseType() === 'sqlite';
    }

    public function getDatabaseType(): string
    {
        return (string)$this->data['database']['type'];
    }

    public function getTimezone(): string
    {
        return (string)$this->data['timezone'];
    }

    public function isEnabledWorld(World $world): bool
    {
        return in_array($world->getFolderName(), $this->getEnabledWorlds());
    }

    public function getEnabledWorlds(): array
    {
        return (array)$this->data['enabled-worlds'];
    }

    public function getCheckUpdates(): bool
    {
        return (bool)$this->data['check-updates'];
    }

    public function getDefaultRadius(): int
    {
        return (int)$this->data['default-radius'];
    }

    public function getMaxRadius(): int
    {
        return (int)$this->data['max-radius'];
    }

    public function getRollbackItems(): bool
    {
        return (bool)$this->data['rollback-items'];
    }

    public function getRollbackEntities(): bool
    {
        return (bool)$this->data['rollback-entities'];
    }

    public function getBlockPlace(): bool
    {
        return (bool)$this->data['block-place'];
    }

    public function getBlockBreak(): bool
    {
        return (bool)$this->data['block-break'];
    }

    public function getNaturalBreak(): bool
    {
        return (bool)$this->data['natural-break'];
    }

    public function getBlockMovement(): bool
    {
        return (bool)$this->data['block-movement'];
    }

    public function getBlockBurn(): bool
    {
        return (bool)$this->data['block-burn'];
    }

    public function getExplosions(): bool
    {
        return (bool)$this->data['explosions'];
    }

    public function getEntityKills(): bool
    {
        return (bool)$this->data['entity-kills'];
    }

    public function getSignText(): bool
    {
        return (bool)$this->data['sign-text'];
    }

    public function getBuckets(): bool
    {
        return (bool)$this->data['buckets'];
    }

    public function getLeavesDecay(): bool
    {
        return (bool)$this->data['leaves-decay'];
    }

    public function getLiquidTracking(): bool
    {
        return (bool)$this->data['liquid-tracking'];
    }

    public function getItemTransactions(): bool
    {
        return (bool)$this->data['item-transactions'];
    }

    public function getPlayerInteractions(): bool
    {
        return (bool)$this->data['player-interactions'];
    }

    public function validateConfig(): ValidationResult
    {
        $v = new Validator();

        $v->required('database.type')->string()->callback(function (string $value): bool {
            return $value === 'sqlite' || $value === 'mysql';
        });
        $v->required('database.sqlite.file')->string();
        //TODO: Check if mysql
        $v->required('database.mysql.host')->string()->callback(function (string $value): bool {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        });
        $v->required('database.mysql.username')->string();
        $v->required('database.mysql.password')->string()->allowEmpty(true);
        $v->required('database.mysql.schema')->string()->allowEmpty(true);
        $v->required('enabled-worlds')->isArray();
        $v->required('check-updates')->bool();
        $v->required('default-radius')->integer()->between(0, PHP_INT_MAX);
        $v->required('max-radius')->integer()->between(0, PHP_INT_MAX);
        $v->required('timezone')->string()->callback(function (string $value): bool {
            return in_array($value, array_values(DateTimeZone::listIdentifiers()));
        });

        foreach (array_slice(array_keys($this->data), 6) as $key) {
            $v->required($key)->bool();
        }

        return $v->validate($this->data);
    }

}