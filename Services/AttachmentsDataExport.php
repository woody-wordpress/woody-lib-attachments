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

    public function scheduleDeleteExportFiles()
    {
        if (!wp_next_scheduled('woody_delete_medias_export_files')) {
            wp_schedule_event(time(), 'daily', 'woody_delete_medias_export_files');
            output_success('Schedule %woody_delete_medias_export_files');
        }
    }

    public function deleteMediaExportFiles()
    {
        $files = dropzone_get('woody_export_attachments_files');
        if (!empty($files) && !empty($files['paths']) && !empty($files['timestamp'])) {
            if ($files['timestamp'] < time() - 3600) {
                foreach ($files['paths'] as $path) {
                    unlink($path);
                }
            }
        }
    }
}
