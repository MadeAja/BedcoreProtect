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

namespace matcracker\BedcoreProtect;

use matcracker\BedcoreProtect\commands\BCPCommand;
use matcracker\BedcoreProtect\listeners\BlockListener;
use matcracker\BedcoreProtect\listeners\EntityListener;
use matcracker\BedcoreProtect\listeners\PlayerListener;
use matcracker\BedcoreProtect\listeners\WorldListener;
use matcracker\BedcoreProtect\storage\Database;
use matcracker\BedcoreProtect\utils\ConfigParser;
use pocketmine\plugin\PluginBase;
use UnexpectedValueException;

final class Main extends PluginBase
{
    public const PLUGIN_NAME = "BedcoreProtect";
    public const PLUGIN_TAG = "[" . self::PLUGIN_NAME . "]";
    public const MESSAGE_PREFIX = "&3" . self::PLUGIN_NAME . " &f- ";

    /**@var Database */
    private $database;

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            throw new UnexpectedValueException("Database connection it's not established!");
        }
        return $this->database;
    }

    protected function onEnable(): void
    {
        if (!extension_loaded("curl")) {
            $this->getLogger()->error("Extension 'curl' is missing. Enable it on php.ini file.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        include_once($this->getFile() . "/vendor/autoload.php");

        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->saveResource("bedcore_database.db");

        $validation = $this->getParsedConfig()->validateConfig();
        if (!$validation->isValid()) {
            $this->getLogger()->warning("Configuration's file is not correct.");
            foreach ($validation->getFailures() as $failure) {
                $this->getLogger()->warning($failure->format());
            }
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        date_default_timezone_set($this->getParsedConfig()->getTimezone());
        $this->getLogger()->debug('Set default timezone to: ' . date_default_timezone_get());
        $this->database = new Database($this);

        $this->getLogger()->info("Establishing database connection using {$this->getParsedConfig()->getDatabaseType()}...");
        if (!$this->database->isConnected()) {
            $this->getLogger()->alert("Could not connect to the database.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->getLogger()->info("Connection established.");

        $this->database->getQueries()->init();

        $this->getServer()->getCommandMap()->register("bedcoreprotect", new BCPCommand($this));

        $this->getServer()->getPluginManager()->registerEvents(new BlockListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new EntityListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new WorldListener($this), $this);
    }

    public function getParsedConfig(): ConfigParser
    {
        return new ConfigParser($this);
    }

    protected function onDisable(): void
    {
        if (($this->database !== null)) {
            $this->database->getQueries()->endTransaction();
            $this->database->close();
        }
        Inspector::clearCache();
    }
}