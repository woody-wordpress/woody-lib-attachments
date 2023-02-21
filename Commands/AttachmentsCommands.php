<?php

/**
 * Woody Lib Attachments
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Commands;

use Woody\Lib\Attachments\Services\CommandsManager;
use Woody\Lib\Attachments\Services\AttachmentsDataExport;

class AttachmentsCommands
{
    private \Woody\Lib\Attachments\Services\CommandsManager $commandManager;
    private \Woody\Lib\Attachments\Services\AttachmentsDataExport $attachmentsDataExport;

    public function __construct(CommandsManager $commandsManager, AttachmentsDataExport $attachmentsDataExport)
    {
        $this->commandManager = $commandsManager;
        $this->attachmentsDataExport = $attachmentsDataExport;
    }

    public function warm($args, $assoc_args)
    {
        $this->commandManager->warm($args, $assoc_args);
    }

    public function clean_exports($args, $assoc_args)
    {
        $this->attachmentsDataExport->deleteMediaExportFiles();
    }
}
