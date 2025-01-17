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
        // On traite les données du formulaire posté pour créer un job async qui va générer un csv avec les données de tous les médias
        if (!empty($_POST) && !empty($_POST['mimetype']) && !empty($_POST['request_language'])) {
            do_action(
                'woody_async_add',
                'attachments_do_export',
                [
                    'request_args' => [
                        'post_mime_type' => $this->filterMimeTypes($_POST['mimetype']),
                        'language' => $_POST['request_language']
                    ],
                    'fields' => $this->getExportFields($_POST)
                ]
            );
        }

        // Champs de cochables dans le BO
        $data['export_fields'] = $this->defineExportFields();
        $data['export_fields'] = array_merge($data['export_fields']['post_fields'], $data['export_fields']['acf_fields'], $data['export_fields']['custom_fields']);

        // On récupère la liste  des fichiers d'export encore valides pour afficher les liens de téléchargement
        $data['files'] = dropzone_get('woody_export_attachments_files');
        if (!empty($data['files'])) {
            date_default_timezone_set(WOODY_TIMEZONE);
            foreach ($data['files'] as $file_key => $file) {
                if (!empty($file)) {
                    if (!empty($file['timestamp'])) {
                        $data['files'][$file_key]['created'] = date('d/m à H:i', $file['timestamp']);
                    }

                    if (!empty($file['path'])) {
                        $data['files'][$file_key]['url'] = str_replace(WP_WEBROOT_DIR, home_url(), $file['path']);
                        $data['files'][$file_key]['name'] = str_replace(sprintf('%s/app/uploads/%s/', WP_WEBROOT_DIR, WP_SITE_KEY), '', $file['path']);
                    }
                }
            }
        }

        return \Timber::render('export-attachments-data.twig', $data);
    }

    public function getExportFields($request)
    {
        $return = [];
        if (!empty($request)) {
            foreach ($request as $param) {
                if (strpos($param, 'export_field_') !== -1) {
                    $return[] = $param;
                }
            }
        }

        return $return;
    }

    public function attachmentsDoExport($args)
    {
        output_h1('Do attachments export');
        if ($args['request_args'] && $args['fields']) {
            // On récupère tous les attachments en fonctions des arguments passés(mimetype, lang)
            $attachments = [];
            $count_posts = 0;
            output_h2('Requesting attachments');
            $query = $this->attachmentsQuery($args['request_args']);
            output_log(sprintf('0/%s ', $query->found_posts));

            // Tant que l'on a pas récupéré la totalité des attachments correspondant à la requête ($query->found_posts),
            // on continue à lancer des requêtes et à pousser les résultats formattés dans le a tableau $attachments
            if (!empty($query) && !empty($query->posts)) {
                while ($count_posts < $query->found_posts) {
                    $count_posts += $query->post_count;
                    $attachments = array_merge($attachments, $this->getAttachmentsData($query->posts, $args['fields']));
                    $args['request_args']['offset'] += $query->post_count;
                    output_log(sprintf('%s/%s ', $args['request_args']['offset'], $query->found_posts));
                    $query = $this->attachmentsQuery($args['request_args']);
                }
            }

            // Une fois les attachments récupérés, on convertit le tableau en un fichier csv que l'on stocke dans les uploads du site initiateur
            if (!empty($attachments)) {
                $time = time();
                $filespath = $this->arrayToCsv($attachments, $time);
                if ($filespath) {
                    // On réucpère la liste des fichiers existants pour la mettre à jour.
                    // Cette liste nous servira à afficher les liens de téléchargement
                    $existing_files = dropzone_get('woody_export_attachments_files');
                    $existing_files[] = ['path' => $filespath, 'timestamp' => $time];
                    dropzone_set('woody_export_attachments_files', $existing_files);
                    output_success(sprintf('csv file created from %s attachments matching the request.', $count_posts));
                }
            }
        }
    }

    public function attachmentsQuery($request_args)
    {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 200,
            'offset' => (empty($request_args['offset'])) ? 0 : $request_args['offset']
        ];

        if ($request_args['language'] == 'language_default') {
            $args['lang'] = PLL_DEFAULT_LANG;
        }

        if (!empty($request_args['post_mime_type'])) {
            $args['post_mime_type'] = $request_args['post_mime_type'];
        }

        $wpQuery = new \WP_Query($args);

        if ($wpQuery->have_posts()) {
            return $wpQuery;
        }
    }

    public function filterMimeTypes($mimetype)
    {
        $mimetypes = [];

        if ($mimetype == 'all') {
            return [];
        }

        $all_types = get_allowed_mime_types();
        if (!empty($all_types)) {
            foreach ($all_types as $type) {
                if (strpos($type, (string) $mimetype) === 0) {
                    $mimetypes[] = $type;
                }
            }
        }

        return $mimetypes;
    }

    public function getAttachmentsData($posts, $fields)
    {
        $return = [];
        $export_fields = $this->defineExportFields();
        $post_fields = $export_fields['post_fields'];
        $acf_fields = $export_fields['acf_fields'];
        $custom_fields = $export_fields['custom_fields'];

        if (!empty($fields)) {
            foreach ($posts as $post) {

                $file_path = get_attached_file($post->ID);

                foreach ($post_fields as $key => $post_field) {
                    if (in_array($key, $fields)) {
                        $return[$post->ID][$key] = $post->{$key};
                    }
                }

                foreach ($acf_fields as $key => $acf_field) {
                    if (in_array($key, $fields)) {
                        $return[$post->ID][$key] = get_field($key, $post->ID);
                    }
                }

                if (in_array('url', $fields)) {
                    $return[$post->ID]['path'] = home_url() . str_replace('/home/admin/www/wordpress/current/web', '', $file_path);
                }

                if (in_array('filesize', $fields)) {
                    if (file_exists($file_path)) {
                        $return[$post->ID]['filesize'] = filesize($file_path);
                    }
                }
            }
        }

        return $return;
    }

    public function arrayToCsv($attachments, $time)
    {
        if (!empty($attachments)) {
            $csvhead = array_keys($attachments[0]);
            array_unshift($attachments, $csvhead);
            $filepath = WP_UPLOAD_DIR . '/media-export-' . $time . '.csv';
            $file = fopen($filepath, 'w');

            foreach ($attachments as $attachment) {
                fputcsv($file, $attachment, ';');
            }

            fclose($file);

            return $filepath;
        }
    }

    public function scheduleDeleteExportFiles()
    {
        if (!wp_next_scheduled('woody_delete_medias_export_files')) {
            wp_schedule_event(time(), 'daily', 'woody_delete_medias_export_files');
            output_success('Schedule woody_delete_medias_export_files');
        }
    }

    public function deleteMediaExportFiles($args = [])
    {
        $files = dropzone_get('woody_export_attachments_files');
        if (!empty($files)) {
            foreach ($files as $file_key => $file) {
                if (!empty($file['path']) && !empty($file['timestamp'])) {
                    if ($args['force']) {
                        output_log('Force export files deletion');
                        output_log(sprintf('Unlink %s', $file['path']));
                        unlink($file['path']);
                        unset($files[$file_key]);
                    } elseif (!file_exists($file['path'])) {
                        unset($files[$file_key]);
                    } elseif ($file['timestamp'] < time() - 3600 * 24) {
                        output_log(sprintf('Unlink %s', $file['path']));
                        unlink($file['path']);
                        unset($files[$file_key]);
                    }
                }
            }
        }

        if (empty($files)) {
            dropzone_delete('woody_export_attachments_files');
        } else {
            dropzone_set('woody_export_attachments_files', $files);
        }
    }

    public function defineExportFields()
    {
        $return = [
            'post_fields' => [
                'ID' => [
                    'name' => 'id',
                    'label' => 'Identifiant'
                ],
                'post_name' => [
                    'name' => 'name',
                    'label' => 'Nom du média'
                ],
                'post_title' => [
                    'name' => 'title',
                    'label' => 'Titre du média'
                ],
                'post_url' => [
                    'name' => 'url',
                    'label' => 'Url du fichier'
                ],
                'post_excerpt' => [
                    'name' => 'caption',
                    'label' => 'Légende'
                ]
            ],
            'acf_fields' => [
                'media_author' => [
                    'name' => 'author',
                    'label' => 'Auteur'
                ],
                'medias_rights_management' => [
                    'name' => 'rights',
                    'label' => 'Gestion des droits'
                ],
                'media_lat' => [
                    'name' => 'lat',
                    'label' => 'Latitude'
                ],
                'media_lng' => [
                    'name' => 'lng',
                    'label' => 'Longitude'
                ],
                'attachment_expire' => [
                    'name' => 'expired',
                    'label' => 'Date d\'expiration'
                ]
            ],
            'custom_fields' => [
                'filesize' => [
                    'name' => 'filesize',
                    'label' => 'Taille du fichier'
                ]
            ]
        ];

        $return = apply_filters('woody_attachments_define_export_datas', $return);

        return $return;
    }
}
