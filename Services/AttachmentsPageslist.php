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
        $req_str = "SELECT p.post_type, p.post_title, p.ID FROM {$wpdb->prefix}woody_attachments as wa LEFT JOIN {$wpdb->prefix}posts as p ON wa.post_id = p.ID WHERE wa.attachment_id = '$att_id' AND p.post_type != 'revision'";
        console_log($req_str);
        $results = $wpdb->get_results($wpdb->prepare($req_str));


        require_once(WOODY_LIB_ATTACHMENTS_DIR_RESOURCES . '/Templates/media-pages-list.php');
    }
}
