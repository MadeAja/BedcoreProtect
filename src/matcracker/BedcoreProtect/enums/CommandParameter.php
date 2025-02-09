<?php

/*
 *     ___         __                 ___           __          __
 *    / _ )___ ___/ /______  _______ / _ \_______  / /____ ____/ /_
 *   / _  / -_) _  / __/ _ \/ __/ -_) ___/ __/ _ \/ __/ -_) __/ __/
 *  /____/\__/\_,_/\__/\___/_/  \__/_/  /_/  \___/\__/\__/\__/\__/
 *
 * Copyright (C) 2019-2021
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

namespace matcracker\BedcoreProtect\enums;

use dktapps\pmforms\element\CustomFormElement;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Slider;
use InvalidArgumentException;
use matcracker\BedcoreProtect\Main;
use matcracker\BedcoreProtect\utils\Utils;
use function array_map;
use function count;
use function in_array;
use function mb_strtolower;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever enum members are added, removed or changed.
 * @see EnumTrait::_generateMethodAnnotations()
 *
 * @method static self USERS()
 * @method static self TIME()
 * @method static self WORLD()
 * @method static self RADIUS()
 * @method static self ACTIONS()
 * @method static self INCLUDE ()
 * @method static self EXCLUDE()
 */
final class CommandParameter
{
    use EnumTrait {
        register as Enum_register;
        __construct as Enum___construct;
        fromString as Enum_fromString;
    }

    /** @var string[] */
    private array $aliases;
    private CustomFormElement $formElement;
    private string $example;

    public function __construct(string $enumName, array $aliases, CustomFormElement $formElement, string $example)
    {
        $this->Enum___construct($enumName);
        $this->aliases = $aliases;
        $this->formElement = $formElement;
        $this->example = $example;
    }

    public static function fromString(string $name): ?CommandParameter
    {
        try {
            return self::Enum_fromString($name);
        } catch (InvalidArgumentException $e) {
            foreach (self::getAll() as $enum) {
                if (in_array(mb_strtolower($name), $enum->getAliases())) {
                    return $enum;
                }
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public static function getAllNames(): array
    {
        return array_map(static function (CommandParameter $parameter): string {
            return $parameter->name();
        }, self::getAll());
    }

    public static function count(): int
    {
        return count(self::getAll());
    }

    protected static function setup(): void
    {
        $plugin = Main::getInstance();

        self::registerAll(
            new self(
                "users",
                ["user", "u"],
                new Input(
                    "users",
                    $plugin->getLanguage()->translateString("form.params.users"),
                    $plugin->getLanguage()->translateString("form.params.users-placeholder")
                ),
                "[u=shoghicp], [u=shoghicp,#zombie]"
            ),
            new self(
                "time",
                ["t"],
                new Input(
                    "time",
                    $plugin->getLanguage()->translateString("form.params.time"),
                    "1h3m10s"
                ),
                "[t=2w5d7h2m10s], [t=5d2h]"
            ),
            new self(
                "world",
                ["w"],
                new Dropdown(
                    "world",
                    $plugin->getLanguage()->translateString("form.params.world"),
                    Utils::getWorldNames()
                ),
                "[w=my_world], [w=faction]"
            ),
            new self(
                "radius",
                ["r"],
                new Slider(
                    "radius",
                    $plugin->getLanguage()->translateString("form.params.radius"),
                    0,
                    $plugin->getParsedConfig()->getMaxRadius(),
                    1.0,
                    $plugin->getParsedConfig()->getDefaultRadius(),
                ),
                "[r=15]"
            ),
            new self(
                "actions",
                ["action", "a"],
                new Dropdown(
                    "action",
                    $plugin->getLanguage()->translateString("form.params.actions"),
                    Action::COMMAND_ARGUMENTS
                ),
                "[a=block], [a=+block], [a=-block], [a=click,container], [a=block,kill]"
            ),
            new self(
                "include",
                ["i"],
                new Input(
                    "inclusions",
                    $plugin->getLanguage()->translateString("form.params.include"),
                    "stone,dirt,2:0"
                ),
                "[b=stone], [b=1,5,stained_glass:8]"
            ),
            new self(
                "exclude",
                ["e"],
                new Input(
                    "exclusions",
                    $plugin->getLanguage()->translateString("form.params.exclude"),
                    "stone,dirt,2:0"
                ),
                "[b=stone], [b=1,5,stained_glass:8]"
            )
        );
    }

    protected static function register(CommandParameter $member): void
    {
        self::Enum_register($member);
    }

    public function getExample(): string
    {
        return $this->example;
    }

    public function getFormElement(): CustomFormElement
    {
        return $this->formElement;
    }
}
