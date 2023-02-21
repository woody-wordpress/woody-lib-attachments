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
                'arguments' => [
                    ['service' => 'attachments.table.manager']
                ]
            ],
            'attachments.wp.settings' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsWpSettings::class,
                'arguments' => []
            ],
            'attachments.pageslist' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsPageslist::class,
                'arguments' => []
            ],
            'attachments.table.manager' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsTableManager::class,
                'arguments' => []
            ],
            'images.metadata' => [
                'class'     => \Woody\Lib\Attachments\Services\ImagesMetadata::class,
                'arguments' => []
            ],
            'attachments.command.manager' => [
                'class'     => \Woody\Lib\Attachments\Services\CommandsManager::class,
                'arguments' => [
                    ['service' => 'attachments.table.manager']
                ]
            ],
            'attachments.data.export' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsDataExport::class,
                'arguments' => []
            ],
            'attachments.commands' => [
                'class'     => \Woody\Lib\Attachments\Commands\AttachmentsCommands::class,
                'arguments' => [
                    ['service' => 'attachments.command.manager'],
                    ['service' => 'attachments.data.export']
                ]
            ],
            'attachments.unused' => [
                'class'     => \Woody\Lib\Attachments\Services\AttachmentsUnused::class,
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
