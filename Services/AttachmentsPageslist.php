<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsPageslist
{
    public function generatePagesList()
    {
        add_submenu_page(
            null, // Creating page not displayed in menu by setting parent slug to null
            'Pages utilisant le média',
            'Pages utilisant le média',
            'edit_posts',
            'woody-pages-using-media',
            [$this, 'ListPagesUsingMedia']
        );
    }

    public function addPageListLinks($actions, $post, $detached)
    {
        $mimetype = get_post_mime_type($post->ID);
        if (strpos($mimetype, 'image') !== false) {
            $actions['linked_pages_list'] = sprintf('<a href="/wp/wp-admin/admin.php?page=woody-pages-using-media&attachment_id=%s">Voir les pages utilisant l\'image</a>', $post->ID);
        }
        return $actions;
    }

    public function ListPagesUsingMedia()
    {
        // global $wpdb;
        // $field_names = dropzone_get('woody_images_fields_names');
        // if (empty($field_names)) {
        //     $field_names = $this->getImagesFieldNames();
        //     dropzone_set('woody_images_fields_names', $field_names);
        // }
        // $att_id = filter_input(INPUT_GET, 'attachment_id', FILTER_SANITIZE_STRING);
        // $att_metadata = wp_get_attachment_metadata($att_id);

        // if (!empty($field_names)) {
        //     foreach ($field_names as $field_name) {
        //         $req_results = $wpdb->get_results($wpdb->prepare("SELECT p.post_type, p.post_title, pm.post_id, pm.meta_value FROM {$wpdb->prefix}postmeta as pm LEFT JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID WHERE pm.meta_key LIKE '%$field_name' AND pm.meta_value != '' AND p.post_type != 'revision'"));
        //         if (!empty($req_results)) {
        //             foreach ($req_results as $req_result) {
        //                 if (strpos($req_result->meta_value, $att_id) !== false) {
        //                     $results[$req_result->post_id] = $req_result;
        //                 }
        //             }
        //         }
        //     }
        // }

        require_once(WOODY_LIB_ATTACHMENTS_DIR_RESOURCES . '/Templates/media-pages-list.php');
    }
}
