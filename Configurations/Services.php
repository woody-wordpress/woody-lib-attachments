<?php

/**
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Addon\Attachments\Configurations;

class Services
{
    private static $definitions;

    private static function definitions()
    {
        return [
            'attachments.manager' => [
                'class'     => \Woody\Addon\Attachments\Services\AttachmentsManager::class,
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
