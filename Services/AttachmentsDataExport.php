<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsDataExport
{
    public function generateDataExportPage()
    {
        add_submenu_page(
            'upload.php', // Creating page not displayed in menu by setting parent slug to null
            'Exporter les données des médias',
            'Exporter les données des médias',
            'edit_posts',
            'woody-export-attachments-data',
            [$this, 'exportDataPage']
        );
    }


    public function exportDataPage()
    {
        $data = [];

        return \Timber::render('export-attachments-data.twig', $data);
    }
}
