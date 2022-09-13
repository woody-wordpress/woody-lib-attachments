<?php

/**
 * Woody Lib Attachments
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Commands;

use Woody\Lib\Attachments\Services\CommandsManager;

class AttachmentsCommands
{
    private \Woody\Lib\Attachments\Services\CommandsManager $commandManager;

    public function __construct(CommandsManager $commandsManager)
    {
        $this->commandManager = $commandsManager;
    }

    public function warm($args, $assoc_args)
    {
        $this->commandManager->warm($args, $assoc_args);
    }
}
