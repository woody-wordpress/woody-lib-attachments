<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsManager
{
    public function addAttachment($attachment_id)
    {
        output_log(['addAttachment_Manager', $attachment_id]);

        // Added attachment_types
        wp_set_object_terms($attachment_id, 'Média ajouté manuellement', 'attachment_types', false);
    }

    public function deleteAttachment($attachment_id)
    {
        remove_action('delete_attachment', [$this, 'deleteAttachment']);

        $deleted_attachement = wp_cache_get('woody_deleted_attachement', 'woody');
        if (empty($deleted_attachement)) {
            $deleted_attachement = [];
        }

        if (wp_attachment_is_image($attachment_id) && is_array($deleted_attachement) && !in_array($attachment_id, $deleted_attachement)) {
            // Remove translations
            $translations = pll_get_post_translations($attachment_id);
            $deleted_attachement = array_merge($deleted_attachement, array_values($translations));
            wp_cache_set('woody_deleted_attachement', $deleted_attachement, 'woody');

            foreach ($translations as $t_attachment_id) {
                if ($t_attachment_id != $attachment_id) {
                    wp_delete_attachment($t_attachment_id);
                }
            }
        }
    }

    public function saveAttachment($attachment_id)
    {
        if (wp_attachment_is_image($attachment_id)) {
            $this->imageAutoTranslate($attachment_id);
            $this->imageLinkedVideo($attachment_id);
            dropzone_delete('woody_attachments_unused_ids');
        }
    }

    private function imageAutoTranslate($attachment_id)
    {
        output_log('imageAutoTranslate - ' . $attachment_id);
        $translations = pll_get_post_translations($attachment_id);
        $source_lang = pll_get_post_language($attachment_id);

        $languages = pll_languages_list();
        foreach ($languages as $target_lang) {
            // Duplicate media with Polylang Method
            if (!array_key_exists($target_lang, $translations)) {
                output_log(['woody_pll_create_media_translation', $attachment_id, $source_lang, $target_lang]);
                $t_attachment_id = woody_pll_create_media_translation($attachment_id, $source_lang, $target_lang);

                // Sync Meta and fields
                if (!empty($t_attachment_id) && $source_lang != $target_lang) {
                    $this->syncAttachmentFields($attachment_id, $t_attachment_id, $target_lang);
                }
            }
        }
    }

    public function imageLinkedVideo($attachment_id)
    {
        $attachment_terms = wp_get_post_terms($attachment_id, 'attachment_types', ['fields' => 'slugs' ]);

        if (!empty(get_field('media_linked_video', $attachment_id)) && !in_array('media_linked_video', $attachment_terms)) {
            $attachment_terms[] = 'media_linked_video';
            wp_set_object_terms($attachment_id, 'media_linked_video', 'attachment_types', true);
        } elseif (empty(get_field('media_linked_video', $attachment_id)) && in_array('media_linked_video', $attachment_terms)) {
            wp_remove_object_terms($attachment_id, 'media_linked_video', 'attachment_types');
        }
    }

    private function syncAttachmentFields($attachment_id, $t_attachment_id, $target_lang)
    {
        output_log(['syncAttachmentFields', $attachment_id, $t_attachment_id, $target_lang]);
        if (!empty($t_attachment_id) && !empty($attachment_id)) {
            // // Get metadatas (crop sizes)
            // $attachment_metadata = wp_get_attachment_metadata($attachment_id);

            // // Updated metadatas (crop sizes)
            // if (!empty($attachment_metadata)) {
            //     wp_update_attachment_metadata($t_attachment_id, $attachment_metadata);
            // }

            // Get ACF Fields (Author, Lat, Lng)
            $fields = get_fields($attachment_id);
            if (!empty($fields)) {
                foreach ($fields as $selector => $value) {
                    if ($selector == 'media_linked_page') {
                        continue;
                    }

                    output_log([' - syncAttachmentFields update_field', $selector, $value, $t_attachment_id]);
                    update_field($selector, $value, $t_attachment_id);
                }
            }

            // Sync attachment taxonomies
            $tags = [];
            $sync_taxonomies = ['attachment_types', 'attachment_hashtags', 'attachment_categories'];
            foreach ($sync_taxonomies as $taxonomy) {
                $terms = wp_get_post_terms($attachment_id, $taxonomy);
                $tags[$taxonomy] = [];
                if (!empty($terms)) {
                    foreach ($terms as $term) {
                        $tags[$taxonomy][] = $term->name;
                    }

                    // Si la photo a le tag Instagram, elle n'a que celui-là;
                    if (in_array('Instagram', $tags[$taxonomy])) {
                        $tags[$taxonomy] = ['Instagram'];
                    }

                    wp_set_post_terms($attachment_id, $tags[$taxonomy], $taxonomy, false);
                }
            }

            // Synchro Terms
            output_log(['** tags', $tags]);
            foreach ($tags as $taxonomy => $keywords) {
                wp_set_post_terms($t_attachment_id, $keywords, $taxonomy, false);
                output_log(['**** wp_set_post_terms', $t_attachment_id, $keywords, $taxonomy]);
            }

            // Si on lance une traduction en masse de la médiathèque, il faut lancer ce hook qui va synchroniser les taxonomies themes et places
            //if (defined('WP_CLI') && \WP_CLI) {
            do_action('pll_translate_media', $attachment_id, $t_attachment_id, $target_lang);
            //}
        }
    }

    public function attachmentFieldsToSave($post, $attachment)
    {
        if (!empty($post['ID'])) {
            $this->saveAttachment($post['ID']);
        }

        return $post;
    }

    public function timberRender($render)
    {
        return preg_replace('#http(s?):\/\/([a-zA-Z0-9-_.]*)\/app\/uploads\/([^\/]*)\/(\d*)\/(\d*)\/..\/..\/..\/..\/..\/wp-json\/woody\/crop\/(\d*)\/ratio_([a-z0-9-_]*)#', 'http$1://$2/wp-json/woody/crop/$6/ratio_$7', $render);
    }

    public function savePost($post_id, $post, $update)
    {
        $exclude = ['attachment', 'touristic_sheet', 'short_link', 'revision'];

        if (!empty('post') && !in_array($post->post_type, $exclude)) {
            do_action('woody_async_add', 'get_attachments_by_post', ['post_id' => $post_id], 'post_' . $post_id, true);
            dropzone_delete('woody_attachments_unused_ids');
        }
    }

    public function woodyExpiredMediaAddColumn($columns)
    {
        $columns['expire'] = 'Date d\'expiration';

        return $columns;
    }

    public function woodyExpiredMediaFillColumn($column_name, $post_id)
    {
        if ($column_name == 'expire') {
            $expire_date = get_field('attachment_expire', $post_id);
            $now = time();
            $expire_time = (empty($expire_date)) ? false : strtotime($expire_date);

            switch ($expire_time) {
                case false :
                    $color = 'transparent';
                    break;
                case $expire_time < $now:
                    $color = 'red';
                    break;
                case $expire_time < $now + 864000:
                    $color = 'orange';
                    break;
                default:
                    $color = 'green';
                    break;
            }

            $readable_date = (empty($expire_time)) ? '' : strftime('%d/%m/%Y', $expire_time);

            $expire_date = sprintf('<label style="background-color:%s; color:white; border-radius:3px; padding:3px">%s</label>', $color, $readable_date);

            echo empty($expire_date) ? '--' : $expire_date;
        }
    }

    public function woodyExpiredMediaSortColumn($columns)
    {
        $columns['expire'] = 'expire';

        return $columns;
    }

    public function woodyExpiredMediaSortRule($query)
    {
        if (!is_admin()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby == 'expire') {
            $query->set('meta_key', 'attachment_expire');
            $query->set('orderby', 'meta_value');
        }
    }
}
