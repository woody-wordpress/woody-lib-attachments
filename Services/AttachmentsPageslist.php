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
        global $wpdb;

        $att_id = filter_input(INPUT_GET, 'attachment_id', FILTER_SANITIZE_STRING);
        $att_metadata = wp_get_attachment_metadata($att_id);
        $req_str = "SELECT p.post_type, p.post_title, p.ID, wa.meta_key FROM {$wpdb->prefix}woody_attachments as wa LEFT JOIN {$wpdb->prefix}posts as p ON wa.post_id = p.ID WHERE wa.attachment_id = '$att_id' AND p.post_type != 'revision'";
        $results = $wpdb->get_results($wpdb->prepare($req_str));

        if (!empty($results)) {
            foreach ($results as $results_key => $result) {
                $results[$results_key]->position = $this->metaKeyToPosition($result->meta_key);
            }
        }

        require_once(WOODY_LIB_ATTACHMENTS_DIR_RESOURCES . '/Templates/media-pages-list.php');
    }

    private function metaKeyToPosition($meta_key)
    {
        $return = '';
        $meta_key_arr = preg_split('/([0-9])+/', $meta_key, 0, PREG_SPLIT_DELIM_CAPTURE);

        if (!empty($meta_key_arr)) {
            if (is_string($meta_key_arr[1]) && $meta_key_arr[0] == 'section_') {
                $return = 'Section ' . ($meta_key_arr[1] + 1);
                if (is_string($meta_key_arr[3]) && $meta_key_arr[2] == '_section_content_') {
                    $return .= ' - Bloc ' . ($meta_key_arr[3] + 1);
                }
            } else {
                $return = 'Hors des sections';
            }
        }

        return $return;
    }
}
