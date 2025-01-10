<?php

/**
 * Woody Addon RoadBook
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

 namespace Woody\Lib\Attachments\Services;

class AttachmentsDataFlow
{
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

        $form->addGroup('Champs à faire apparaître dans l\'export');

        // $data['export_fields'] = $this->defineExportFields();

        // $form->addHidden('hiddenfield', 'any-hidden-value');
        // $form->addText('name', 'Nom : ');
        return $form;
    }



    public function exportLeaflets($csv, $data = []) {

        global $wpdb;

        // base
        $sql = "SELECT `p`.* FROM `{$wpdb->prefix}posts` AS `p` WHERE `p`.`post_type` = %s";
        $values = ['woody_rdbk_leaflets'];

        $sql = $wpdb->prepare($sql, $values);
        // error_log("DataFlowRoadBook - exportLeaflets - sql query : " . $sql);

        // run
        $leaflets = $wpdb->get_results($sql);

        // csv headers
        $csv['headers'] = [
            "ID",
            "Status",
            "Titre",
            "Url",
            "Lang",
            "Catégories"
        ];

        // csv lines
        $lines = [];
        if (!empty($leaflets)) {
            foreach ($leaflets as $leaflet) {

                $categories = wp_get_post_terms($leaflet->ID, 'leaflet_category', ['fields' => 'names']);

                $csv['lines'][] = [
                    $leaflet->ID,
                    $leaflet->post_status,
                    $leaflet->post_title,
                    woody_get_permalink($leaflet->ID),
                    woody_pll_get_post_language($leaflet->ID),
                    implode(', ', $categories)
                ];
            }
        }

        return $csv;
    }
}
