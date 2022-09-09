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
        $mime_type = str_replace('/', '_', get_post_mime_type($post->ID));
        $actions['linked_pages_list'] = sprintf('<a href="/wp/wp-admin/admin.php?page=woody-pages-using-media&attachment_id=%s&mime_type=%s">Utilisation et remplacement</a>', $post->ID, $mime_type);
        return $actions;
    }

    public function ListPagesUsingMedia()
    {
        $attachment_id = filter_input(INPUT_GET, 'attachment_id', FILTER_SANITIZE_STRING);
        $results = $this->getResults($attachment_id);

        $mimetype_arr = explode('/', get_post_mime_type($attachment_id));
        $type = (empty($mimetype_arr)) ? 'Unknown' : $mimetype_arr[0];
        switch($type) {
            case 'audio':
                $icon = 'media-audio';
                break;
            case 'video':
                $icon = 'media-video';
                break;
            case 'text':
                $icon = 'media-text';
                break;
            case 'font':
                $icon = 'media-archive';
                break;
            case 'application':
                $icon = 'media-spreadsheet';
                break;
            default:
                $icon = 'media-default';
                break;
        }

        require_once(WOODY_LIB_ATTACHMENTS_DIR_RESOURCES . '/Templates/media-pages-list.php');
    }

    private function getResults($attachment_id)
    {
        global $wpdb;

        $req_str = "SELECT p.post_type, p.post_title, p.ID, wa.meta_key FROM {$wpdb->prefix}woody_attachments as wa LEFT JOIN {$wpdb->prefix}posts as p ON wa.post_id = p.ID WHERE wa.attachment_id = '$attachment_id' AND p.post_type != 'revision'";
        $results = $wpdb->get_results($wpdb->prepare($req_str));
        if (!empty($results)) {
            foreach ($results as $results_key => $result) {
                $results[$results_key]->position = $this->metaKeyToPosition($result->meta_key);
                $results[$results_key]->post_type = $this->postTypeLabel($result->post_type);
            }
        }

        return array_reverse($results);
    }

    private function postTypeLabel($post_type)
    {
        $post_type_obj = get_post_type_object($post_type);
        $return = (empty($post_type_obj->labels->singular_name)) ? $post_type : $post_type_obj->labels->singular_name;

        return $return;
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
