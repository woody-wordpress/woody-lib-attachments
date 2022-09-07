<?php

/**
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Configurations;

class Services
{
    private static $definitions;

    private static function definitions()
    {
        return [
            'attachments.manager' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsManager::class,
                'arguments' => []
            ],
            'attachments.api' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsApi::class,
                'arguments' => []
            ],
            'attachments.wp.settings' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsWpSettings::class,
                'arguments' => []
            ],
            'images.metadata' => [
                'class'     => \Woody\Lib\Attachments\Services\ImagesMetadata::class,
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
