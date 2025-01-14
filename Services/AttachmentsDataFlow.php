<?php

/**
 * Woody Addon RoadBook
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

 namespace Woody\Lib\Attachments\Services;

class AttachmentsDataFlow
{

    const QUERY_POST_PER_PAGE = 500;

    private $dataFlowExportClass;

    public function __construct() {}

    public function adminInit($args) {

        // register dataflow export
        $this->dataFlowExportClass = $args['export.async.class'];

    }

    public function generateMenu()
    {
        if ($this->dataFlowExportClass) {
            add_submenu_page(
                'upload.php',
                'Exporter les données des médias',
                'Exporter les données des médias',
                'edit_posts',
                'woody-export-attachments-data',
                [$this, 'adminPageDataFlow'],
                20
            );
        }
    }

    public function adminPageDataFlow() {
        $dataFlowManager = new $this->dataFlowExportClass('attachments_export_', 'attachments_dataflow_export');
        $dataFlowManager->show_form([
            'title' => "Export des feuillets",
        ], [$this, 'setupForm']);
    }

    /**
     * @param Woody\Addon\DataFlow\Services\DataFlowForm $form
     */
    public function setupForm($form) {

        $form->addGroup('Types de médias');
        $form->addRadioList('mimetype', null, [
            'image' => 'Images',
            'video' => 'Vidéos',
            'text' => 'Documents',
            'audio' => 'Audios',
            'all' => 'Tous les médias',
        ])->setDefaultValue('image');


        $form->addGroup('Langues');
        $form->addRadioList('request_language', null, [
            'language_default' => 'Langue par défaut',
            'language_all' => 'Toutes les langues',
        ])->setDefaultValue('language_default');
        $form->getElementPrototype()->setHtml(`
            <p class="desc">Si vous vous choisissez toutes les langues, les données des images apparaîtront autant de fois que vous avez de langues sur le site</br>
            Dans le cas d'un export d'images, nous vous conseillons de n'exporter que la langue par défaut du site</br>
            Pour les autres types de médias, nous vous conseillons un export dans toutes les langues</p>
        `);

        $export_fields = $this->defineExportFields();
        if (!empty($export_fields)) {
            $form->addGroup('Champs à faire apparaître dans l\'export');
            $checkbox_list = [];
            $checkbox_list_selected = [];
            foreach ($export_fields as $name => $export_field) {
                $checkbox_list[$name] = $export_field['label'];
                if ($export_field['default'] === true) {
                    $checkbox_list_selected[] = $name;
                }
            }
            $form->addCheckboxList('export_fields', null, $checkbox_list)->setDefaultValue($checkbox_list_selected);
        }

        return $form;
    }

    public function defineExportFields()
    {
        $return = [
            'id' => [
                'label' => 'Identifiant',
                'field_type' => 'post_field',
                'field_name' => 'ID',
                'default' => true
            ],
            'name' => [
                'label' => 'Nom du média',
                'field_type' => 'post_field',
                'field_name' => 'post_name',
                'default' => true
            ],
            'title' => [
                'label' => 'Titre du média',
                'field_type' => 'post_field',
                'field_name' => 'post_title',
                'default' => true
            ],
            'url' => [
                'label' => 'Url du fichier',
                'field_type' => 'post_field',
                'field_name' => 'post_url',
                'default' => true
            ],
            'caption' => [
                'label' => 'Légende',
                'field_type' => 'post_field',
                'field_name' => 'post_excerpt',
                'default' => true
            ],
            'author' => [
                'label' => 'Auteur',
                'field_type' => 'acf_field',
                'field_name' => 'media_author',
                'default' => true
            ],
            'rights' => [
                'label' => 'Gestion des droits',
                'field_type' => 'acf_field',
                'field_name' => 'medias_rights_management',
                'default' => true
            ],
            'lat' => [
                'label' => 'Latitude',
                'field_type' => 'acf_field',
                'field_name' => 'media_lat',
                'default' => true
            ],
            'lng' => [
                'label' => 'Longitude',
                'field_type' => 'acf_field',
                'field_name' => 'media_lng',
                'default' => true
            ],
            'expired' => [
                'label' => 'Date d\'expiration',
                'field_type' => 'acf_field',
                'field_name' => 'attachment_expire',
                'default' => true
            ],
            'filesize' => [
                'label' => 'Taille du fichier',
                'field_type' => 'custom_field',
                'field_name' => 'filesize',
                'default' => true
            ]
        ];

        $return = apply_filters('woody_attachments_define_export_datas', $return);

        return $return;
    }

    private function filterMimeTypes($mimetype)
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

    private function filterLanguage($language) {
        if ($language == 'language_default') {
            return PLL_DEFAULT_LANG;
        }
        return $language;
    }

    public function export($csv, $data = []) {

        output_h1('Do attachments export - data : ' . var_export($data, true));
        if (!$data || !isset($data['export_fields']) || empty($data['export_fields'])) {
            output_error('AttachmentsDataFlow - export - no data or no export_fields : ' . var_export($data, true));
        }

        // On récupère la définition des champs demandés
        $requested_export_fields = $this->filterExportFields($data['export_fields']);
        // output_h1('Do attachments export - requested_export_fields : ' . var_export($requested_export_fields, true));

        // On récupère tous les attachments en fonctions des arguments passés(mimetype, lang)
        $attachments = [];
        output_h2('Requesting attachments');
        $data['offset'] = 0;
        $query = $this->attachmentsQuery($data);
        output_log(sprintf('0/%s ', $query->found_posts));

        // Tant que l'on a pas récupéré la totalité des attachments correspondant à la requête ($query->found_posts),
        // on continue à lancer des requêtes et à pousser les résultats formattés dans le a tableau $attachments
        if (!empty($query) && !empty($query->posts)) {
            while ($count_posts < $query->found_posts) {
                $attachments = array_merge($attachments, $this->getAttachmentsData($query->posts, $data));
                $data['offset'] += $query->post_count;
                output_log(sprintf('%s/%s ', $data['offset'], $query->found_posts));
                $query = $this->attachmentsQuery($data);
            }
        }

        // Construction du CSV
        $headers = [];
        foreach ($requested_export_fields as $field) {
            $headers[] = $field['label'];
        }
        $csv['headers'] = $headers;

        // Une fois les attachments récupérés, on convertit le tableau en un fichier csv que l'on stocke dans les uploads du site initiateur
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $line = [];
                $names = array_keys($requested_export_fields);
                foreach ($names as $name) {
                    $line[] = $attachment[$name];
                }
                $csv['lines'][] = $line;
            }
        }

        return $csv;
    }

    private function filterExportFields($requested_field_names) {
        $res = [];
        foreach ($requested_field_names as $requested_field_name) {
            $defined_export_fields = $this->defineExportFields();
            if (isset($defined_export_fields[$requested_field_name])) {
                $res[$requested_field_name] = $defined_export_fields[$requested_field_name];
            }
        }
        return $res;
    }

    private function attachmentsQuery($request_args)
    {
        $mimetype = $this->filterMimeTypes($request_args['mimetype']);
        $lang = $this->filterLanguage($request_args['request_language']);

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => self::QUERY_POST_PER_PAGE,
            'offset' => $request_args['offset']
        ];
        if (!empty($lang)) {
            $args['lang'] = $lang;
        }
        if (!empty($mimetype)) {
            $args['post_mime_type'] = $mimetype;
        }
        $wpQuery = new \WP_Query($args);

        if ($wpQuery->have_posts()) {
            return $wpQuery;
        }
    }

    private function getAttachmentsData($posts, $data)
    {
        $return = [];

        $requested_export_fields = $this->filterExportFields($data['export_fields']);

        if (!empty($requested_export_fields)) {
            foreach ($posts as $post) {

                $file_path = get_attached_file($post->ID);

                foreach ($requested_export_fields as $name => $field) {

                    $field_value = null;

                    // post fields
                    if ($field['field_type'] === 'post_field') {
                        $field_value = $post->{$field['field_name']};
                    }

                    // acf fields
                    else if ($field['field_type'] === 'acf_field') {
                        $field_value = get_field($field['field_name'], $post->ID);
                    }

                    // custom fields
                    else if ($field['field_type'] === 'custom_field') {
                        if ($name === 'url') {
                            $field_value = home_url() . str_replace('/home/admin/www/wordpress/current/web', '', $file_path);
                        } else if ($name === 'filesize') {
                            $field_value = $this->humanFileSize(filesize($file_path), "MB");
                        }
                    }

                    $return[$post->ID][$name] = $field_value;
                }
            }
        }

        return $return;
    }

    private function humanFileSize($size, $unit = "") {
        if (!$size) {
            return "";
        }
        if( (!$unit && $size >= 1<<30) || $unit == "GB") {
            return number_format($size/(1<<30),2)."GB";
        }
        if( (!$unit && $size >= 1<<20) || $unit == "MB") {
            return number_format($size/(1<<20),2)."MB";
        }
        if( (!$unit && $size >= 1<<10) || $unit == "KB") {
            return number_format($size/(1<<10),2)."KB";
        }
        return number_format($size)." bytes";
    }
}
