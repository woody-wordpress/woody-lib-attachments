<?php

/**
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Addon\Boilerplate\Configurations;

class Services
{
    private static $definitions;

    private static function definitions()
    {
        return [
            'boilerplate.manager' => [
                'class'     => \Woody\Addon\Boilerplate\Services\BoilerplateManager::class,
                'arguments' => []
            ]
        ];
    }

    public static function loadDefinitions()
    {
        if (!self::$definitions) {
            self::$definitions = self::definitions();
        }

        return self::$definitions;
    }
}
