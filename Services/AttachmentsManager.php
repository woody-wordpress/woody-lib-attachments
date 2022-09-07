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
        // Added attachment_types
        wp_set_object_terms($attachment_id, 'Média ajouté manuellement', 'attachment_types', false);

        // Duplicate all medias
        $this->saveAttachment($attachment_id);
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
        }
    }

    public function imageAutoTranslate($attachment_id)
    {
        $translations = pll_get_post_translations($attachment_id);
        $source_lang = pll_get_post_language($attachment_id);

        $languages = pll_languages_list();
        foreach ($languages as $target_lang) {
            // Duplicate media with Polylang Method
            if (!array_key_exists($target_lang, $translations)) {
                $t_attachment_id = woody_pll_create_media_translation($attachment_id, $source_lang, $target_lang);
            } else {
                $t_attachment_id = $translations[$target_lang];
            }

            // Sync Meta and fields
            if (!empty($t_attachment_id) && $source_lang != $target_lang) {
                $this->syncAttachmentMetadata($attachment_id, $t_attachment_id, $target_lang);
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

    private function syncAttachmentMetadata($attachment_id, $t_attachment_id, $target_lang)
    {
        if (!empty($t_attachment_id) && !empty($attachment_id)) {
            // Get metadatas (crop sizes)
            $attachment_metadata = wp_get_attachment_metadata($attachment_id);

            // Updated metadatas (crop sizes)
            if (!empty($attachment_metadata)) {
                wp_update_attachment_metadata($t_attachment_id, $attachment_metadata);
            }

            // Get ACF Fields (Author, Lat, Lng)
            $fields = get_fields($attachment_id);

            // Update ACF Fields (Author, Lat, Lng)
            if (!empty($fields)) {
                foreach ($fields as $selector => $value) {
                    if ($selector == 'media_linked_page') {
                        continue;
                    }
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
            if (!empty($tags)) {
                foreach ($tags as $taxonomy => $keywords) {
                    wp_set_post_terms($t_attachment_id, $keywords, $taxonomy, false);
                }
            }

            // Si on lance une traduction en masse de la médiathèque, il faut lancer ce hook qui va synchroniser les taxonomies themes et places
            if (defined('WP_CLI') && \WP_CLI) {
                do_action('pll_translate_media', $attachment_id, $t_attachment_id, $target_lang);
            }
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
        return preg_replace('/http(s?):\/\/([a-zA-Z0-9-_.]*)\/app\/uploads\/([^\/]*)\/([0-9]*)\/([0-9]*)\/..\/..\/..\/..\/..\/wp-json\/woody\/crop\/([0-9]*)\/ratio_([a-z0-9-_]*)/', 'http$1://$2/wp-json/woody/crop/$6/ratio_$7', $render);
    }
}
