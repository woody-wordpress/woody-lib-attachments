<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class ImagesMetadata
{
    public function acfSavePost($attachment_id)
    {
        // Pour une image le hook "save_post" n'est pas appelé mais le hook "acf/save_post" oui !
        // Nous pouvons donc lancer saveAttachment à la modification dans le back-office
        //output_log(['acfSavePost']);
        $this->saveAttachment($attachment_id);
    }

    public function addAttachment($attachment_id)
    {
        // On assigne par défaut ce tag à tout média ajouté manuellement
        //output_log(['addAttachment']);
        wp_set_object_terms($attachment_id, 'Média ajouté manuellement', 'attachment_types', false);
    }

    public function saveAttachment($attachment_id)
    {
        if(wp_attachment_is_image($attachment_id)) {
            //output_log(['saveAttachment', $attachment_id]);

            // On traduit l'image dans toutes les langues si ce n'est pas déjà fait
            $this->translateAttachment($attachment_id);

            // Sync Fields and Terms
            $this->syncAttachment($attachment_id);
        }
    }

    public function syncAttachment($attachment_id)
    {
        if(wp_attachment_is_image($attachment_id)) {
            //output_log(['syncAttachment', $attachment_id]);

            $translations = pll_get_post_translations($attachment_id);
            $source_lang = pll_get_post_language($attachment_id);

            // Sync media_linked_video
            $attachment_terms = wp_get_post_terms($attachment_id, 'attachment_types', ['fields' => 'slugs' ]);
            if (!empty(get_field('media_linked_video', $attachment_id)) && !in_array('media_linked_video', $attachment_terms)) {
                $attachment_terms[] = 'media_linked_video';
                wp_set_object_terms($attachment_id, 'media_linked_video', 'attachment_types', true);
            } elseif (empty(get_field('media_linked_video', $attachment_id)) && in_array('media_linked_video', $attachment_terms)) {
                wp_remove_object_terms($attachment_id, 'media_linked_video', 'attachment_types');
            }

            // Get ACF Fields (Author, Lat, Lng)
            $fields = get_fields($attachment_id);
            if (!empty($fields)) {
                foreach ($translations as $target_lang => $t_attachment_id) {
                    if($target_lang != $source_lang) {
                        foreach ($fields as $selector => $value) {
                            if ($selector != 'media_linked_page') {
                                update_field($selector, $value, $t_attachment_id);
                            }

                        }
                    }
                }
            }

            // Sync attachment taxonomies
            $tags = [];
            $sync_taxonomies = ['attachment_types', 'attachment_hashtags', 'attachment_categories'];
            foreach ($sync_taxonomies as $taxonomy) {
                foreach ($translations as $target_lang => $t_attachment_id) {
                    if($target_lang != $source_lang) {
                        $terms = wp_get_post_terms($attachment_id, $taxonomy);
                        wp_set_post_terms($t_attachment_id, $terms, $taxonomy, false);

                    }
                }
            }

            // Cleanup
            dropzone_delete('woody_attachments_unused_ids');
        }
    }

    public function updatedPostmeta($meta_id, $attachment_id, $meta_key, $meta_value)
    {
        global $wpdb;
        if($meta_key == '_wp_attachment_metadata' && wp_attachment_is_image($attachment_id)) {

            // On ne lance la traduction des attachements après la 1ère mise à jour des attachment_metadata
            // Avant de lancer la traduction, on supprime l'entrée "create" qui indique que c'est la 1ère mise à jour
            $meta_value = maybe_unserialize($meta_value);
            if(!empty($meta_value['create'])) {
                //output_log(['updatedPostmeta CREATE', $meta_id, $attachment_id, $meta_key, $meta_value]);

                // On supprime le flag "create" avant de lancer la trad, il ne faut pas que les traductions aient cette entrée
                // Sinon on repasse dans ce hook en boucle
                // On fait ça avec une requête sinon on repasse dans ce hook pour rien
                unset($meta_value['create']);
                $wpdb->update($wpdb->postmeta, ['meta_value' => maybe_serialize($meta_value)], ['meta_id' => $meta_id]);
                wp_cache_delete($attachment_id, 'post_meta');

                // Dupliquer l'image dans toutes les langues
                $this->translateAttachment($attachment_id);
            } else {
                //output_log(['updatedPostmeta UPDATE', $meta_id, $attachment_id, $meta_key, $meta_value]);

                // Si les attachment_metadata sont modifiées sur la langue par défaut, on met à jour les trads
                $source_lang = pll_get_post_language($attachment_id);
                if($source_lang == PLL_DEFAULT_LANG) {
                    $translations = pll_get_post_translations($attachment_id);
                    foreach ($translations as $target_lang => $t_attachment_id) {
                        if($target_lang != $source_lang) {
                            wp_update_attachment_metadata($t_attachment_id, $meta_value);
                        }
                    }
                }
            }
        }
    }

    public function generateAttachmentMetadata($metadata, $attachment_id, $context)
    {
        if (wp_attachment_is_image($attachment_id) && $context == 'create' && empty($metadata['sizes'])) {
            //output_log(['generateAttachmentMetadata', $metadata, $attachment_id, $context]);

            // Crop API
            global $_wp_additional_image_sizes;
            $_wp_additional_image_sizes['thumbnail'] = ['height' => 150, 'width' => 150, 'crop' => true];
            $_wp_additional_image_sizes['medium'] = ['height' => 300, 'width' => 300, 'crop' => true];
            $_wp_additional_image_sizes['large'] = ['height' => 1024, 'width' => 1024, 'crop' => true];

            // Get Mime-Type
            $mime_type = mime_content_type(WP_UPLOAD_DIR . '/' . $metadata['file']);
            foreach ($_wp_additional_image_sizes as $ratio => $size) {
                if (empty($metadata['sizes'][$ratio])) {
                    $metadata['sizes'][$ratio] = [
                        'file' => '../../../../../wp-json/woody/crop/' . $attachment_id . '/' . $ratio,
                        'height' => $size['height'],
                        'width' => $size['width'],
                        'mime-type' => $mime_type,
                    ];
                }
            }

            // Added full size
            $filename = explode('/', $metadata['file']);
            $filename = end($filename);
            $metadata['sizes']['full'] = [
                'file' => $filename,
                'height' => $metadata['height'],
                'width' => $metadata['width'],
                'mime-type' => $mime_type
            ];

            // Added flag to only translate on created
            $metadata['create'] = true;

            // Updated fields
            $this->updateAttachmentFields($attachment_id);
        }

        //output_log(['-', 'generateAttachmentMetadata', $metadata]);
        return $metadata;
    }

    private function updateAttachmentFields($attachment_id)
    {
        //output_log(['updateAttachmentFields', $attachment_id]);
        $file = get_attached_file($attachment_id);
        $meta = wp_read_image_metadata($file);
        $attachment = get_post($attachment_id);

        if (!empty($meta['title'])) {
            $post_title = $meta['title'];
        } else {
            $post_title = ucwords(strtolower(preg_replace('#\s*[-_\s]+\s*#', ' ', $post_title)));
        }

        if (!empty($meta['description'])) {
            $post_excerpt = $meta['description'];
        } elseif (!empty($meta['caption'])) {
            $post_excerpt = $meta['caption'];
        } else {
            $post_excerpt = $post_title;
        }

        if (!empty($meta['caption'])) {
            $post_content = $meta['caption'];
        } else {
            $post_content = $post_excerpt;
        }

        // Set the image Alt-Text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_excerpt);

        // Set the image meta (e.g. Title, Excerpt, Content)
        wp_update_post([
            'ID' => $attachment_id,
            'post_title' => $post_title,
            'post_excerpt' => $post_excerpt,
            'post_content' => $post_content,
        ]);

        // Defined attachement lang
        $lang = pll_current_language();
        $lang = (empty($lang)) ? PLL_DEFAULT_LANG : $lang;
        pll_set_post_language($attachment_id, pll_current_language());

        // Set ACF Fields (Credit)
        if (!empty($meta['credit'])) {
            update_field('media_author', $meta['credit'], $attachment_id);
        }

        if (!empty($meta['latitude'])) {
            update_field('media_lat', $meta['latitude'], $attachment_id);
        }

        if (!empty($meta['longitude'])) {
            update_field('media_lng', $meta['longitude'], $attachment_id);
        }

        // Import tags
        if (!empty($meta['city']) || !empty($meta['state']) || !empty($meta['country']) || !empty($meta['keywords'])) {
            $terms_places = get_terms('places', ['hide_empty' => false]);
            foreach ($terms_places as $term_places) {
                if (!empty($meta['city']) && sanitize_title($meta['city']) == $term_places->slug) {
                    wp_set_object_terms($attachment_id, $term_places->slug, 'places', true);
                } elseif (!empty($meta['state']) && sanitize_title($meta['state']) == $term_places->slug) {
                    wp_set_object_terms($attachment_id, $term_places->slug, 'places', true);
                } elseif (!empty($meta['country']) && sanitize_title($meta['country']) == $term_places->slug) {
                    wp_set_object_terms($attachment_id, $term_places->slug, 'places', true);
                } elseif (!empty($meta['keywords'])) {
                    foreach ($meta['keywords'] as $keyword) {
                        if (sanitize_title($keyword) == $term_places->slug) {
                            wp_set_object_terms($attachment_id, $term_places->slug, 'places', true);
                        }
                    }
                }
            }
        }

        if (!empty($meta['keywords'])) {
            $terms_attachment_categories = get_terms('attachment_categories', ['hide_empty' => false]);
            if (!empty($terms_attachment_categories)) {
                foreach ($terms_attachment_categories as $term_attachment_categories) {
                    foreach ($meta['keywords'] as $keyword) {
                        if (sanitize_title($keyword) == $term_attachment_categories->slug) {
                            wp_set_object_terms($attachment_id, $term_attachment_categories->slug, 'attachment_categories', true);
                        }
                    }
                }
            }

            $terms_themes = get_terms('themes', ['hide_empty' => false]);
            if (!empty($terms_themes)) {
                foreach ($terms_themes as $term_themes) {
                    foreach ($meta['keywords'] as $keyword) {
                        if (sanitize_title($keyword) == $term_themes->slug) {
                            wp_set_object_terms($attachment_id, $term_themes->slug, 'themes', true);
                        }
                    }
                }
            }

            $terms_seasons = get_terms('seasons', ['hide_empty' => false]);
            if (!empty($terms_seasons)) {
                foreach ($terms_seasons as $term_seasons) {
                    foreach ($meta['keywords'] as $keyword) {
                        if (sanitize_title($keyword) == $term_seasons->slug) {
                            wp_set_object_terms($attachment_id, $term_seasons->slug, 'seasons', true);
                        }
                    }
                }
            }
        }
    }

    private function translateAttachment($attachment_id)
    {
        //output_log(['translateAttachment', $attachment_id]);
        $translations = pll_get_post_translations($attachment_id);

        // Si le média n'a pas de langue car uploadé en mode "Afficher toutes les langues.
        // On lui donne la langue par défaut
        $source_lang = pll_get_post_language($attachment_id);
        if(empty($source_lang)) {
            $source_lang = PLL_DEFAULT_LANG;
            pll_set_post_language($attachment_id, $source_lang);
        }

        $languages = pll_languages_list();
        foreach ($languages as $target_lang) {
            if (!array_key_exists($target_lang, $translations) && $target_lang != $source_lang) {
                // Duplicate media with Polylang Method
                $translations[$target_lang] = woody_pll_create_media_translation($attachment_id, $source_lang, $target_lang);
            }
        }
    }

    private function imageLinkedVideo($attachment_id)
    {
        $attachment_terms = wp_get_post_terms($attachment_id, 'attachment_types', ['fields' => 'slugs' ]);
        if (!empty(get_field('media_linked_video', $attachment_id)) && !in_array('media_linked_video', $attachment_terms)) {
            $attachment_terms[] = 'media_linked_video';
            wp_set_object_terms($attachment_id, 'media_linked_video', 'attachment_types', true);
        } elseif (empty(get_field('media_linked_video', $attachment_id)) && in_array('media_linked_video', $attachment_terms)) {
            wp_remove_object_terms($attachment_id, 'media_linked_video', 'attachment_types');
        }
    }

    /* ------------------------ */
    /* Read EXIF/IPTC Metadatas */
    /* ------------------------ */
    public function readImageMetadata($meta, $file, $image_type, $iptc, $exif)
    {
        // EXIF
        $meta = $this->getEXIFData($meta, $exif);

        // IPTC
        $meta = $this->getIPTCData($meta, $iptc);

        // Empty Fields
        if(empty($meta['title'])) {
            if(!empty($meta['description'])) {
                $meta['title'] = $meta['description'];
            } elseif(!empty($meta['caption'])) {
                $meta['title'] = $meta['caption'];
            } else {
                $meta['title'] = ucwords(strtolower(preg_replace('#\s*[-_\s]+\s*#', ' ', pathinfo(basename($file), PATHINFO_FILENAME))));
            }
        }

        if(empty($meta['description'])) {
            if(!empty($meta['caption'])) {
                $meta['description'] = $meta['caption'];
            } elseif(!empty($meta['title'])) {
                $meta['description'] = $meta['title'];
            }
        }

        if(empty($meta['caption'])) {
            if(!empty($meta['description'])) {
                $meta['caption'] = $meta['description'];
            } elseif(!empty($meta['title'])) {
                $meta['caption'] = $meta['title'];
            }
        }

        // Gestion des crédits / Copyright
        if (empty($meta['credit']) && !empty($meta['copyright'])) {
            $meta['credit'] = $meta['copyright'];
        } elseif (empty($meta['copyright']) && !empty($meta['credit'])) {
            $meta['copyright'] = $meta['credit'];
        }

        return $meta;
    }

    private function getEXIFData($meta, $exif)
    {
        if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLatitudeRef'])) {
            $lat_deg = $this->calc($exif['GPSLatitude'][0]);
            $lat_min = $this->calc($exif['GPSLatitude'][1]);
            $lat_sec = $this->calc($exif['GPSLatitude'][2]);
            $meta['latitude'] = $this->dmsToDecimal($lat_deg, $lat_min, $lat_sec, $exif['GPSLatitudeRef']);
        }

        if (!empty($exif['GPSLongitude']) && !empty($exif['GPSLongitudeRef'])) {
            $lng_deg = $this->calc($exif['GPSLongitude'][0]);
            $lng_min = $this->calc($exif['GPSLongitude'][1]);
            $lng_sec = $this->calc($exif['GPSLongitude'][2]);
            $meta['longitude'] = $this->dmsToDecimal($lng_deg, $lng_min, $lng_sec, $exif['GPSLongitudeRef']);
        }

        return $meta;
    }

    private function getIPTCData($meta, $iptc)
    {
        if ((empty($meta['title']) || $meta['title'] == $meta['caption']) && !empty($iptc['2#085'])) {
            $meta['title'] = ucfirst(strtolower(current($iptc['2#085'])));
        }

        if (empty($meta['city']) && !empty($iptc['2#090'])) {
            $meta['city'] = ucfirst(strtolower(current($iptc['2#090'])));
        }

        if (empty($meta['state']) && !empty($iptc['2#095'])) {
            $meta['state'] = ucfirst(strtolower(current($iptc['2#095'])));
        }

        if (empty($meta['country']) && !empty($iptc['2#101'])) {
            $meta['country'] = ucfirst(strtolower(current($iptc['2#101'])));
        }

        return $meta;
    }

    private function calc($val)
    {
        $val = explode('/', $val);
        return $val[0] / $val[1];
    }

    private function dmsToDecimal($deg, $min, $sec, $ref)
    {
        $direction = 1;
        if (strtoupper($ref) == "S" || strtoupper($ref) == "W" || $deg < 0) {
            $direction = -1;
            $deg = abs($deg);
        }

        return ($deg + ($min / 60) + ($sec / 3600)) * $direction;
    }
}
