<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class ImagesMetadata
{
    public function wpHandleUpload($array, $var)
    {
        // if ($array['type'] !== 'image/jpeg') {
        //     //output_error('Color Fixer: Whoops, file is not image compatible');
        //     return $array;
        // }

        // exec('which jpgicc 2>&1', $output, $result);
        // if (empty($output)) {
        //     output_error('Color Fixer: Whoops, jpgicc is not installed');
        //     return $array;
        // }

        // try {
        //     $path = $array['file'];
        //     $target = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME) . '_icc.' . pathinfo($path, PATHINFO_EXTENSION);
        //     exec(sprintf('jpgicc -v %s %s && mv -f %s %s', $path, $target, $target, $path), $output, $result);
        // } catch (Exception $exception) {
        //     output_error('Color Fixer: Whoops, failed to convert image color space');
        // }

        return $array;
    }

    public function addAttachment($attachment_id)
    {
        output_log(['addAttachment', $attachment_id]);
        wp_set_object_terms($attachment_id, 'Média ajouté manuellement', 'attachment_types', false);
    }

    public function saveAttachment($attachment_id)
    {
        // Save metadata to all languages
        output_log(['saveAttachment']);
        if (function_exists('pll_get_post_language') && !empty(PLL_DEFAULT_LANG)) {
            $current_lang = pll_get_post_language($attachment_id);
            if ($current_lang == PLL_DEFAULT_LANG) {
                $attachment_metadata = wp_get_attachment_metadata($attachment_id);
                if (!empty($attachment_metadata)) {
                    $translations = pll_get_post_translations($attachment_id);
                    if (!empty($translations)) {
                        foreach ($translations as $lang => $t_attachment_id) {
                            if($current_lang != $lang) {
                                wp_update_attachment_metadata($t_attachment_id, $attachment_metadata);
                                output_log(['wp_update_attachment_metadata', $t_attachment_id, $attachment_metadata, $attachment_id, $lang]);
                            }
                        }
                    }
                }
            }
        }
    }

    public function updatedPostmeta($meta_id, $object_id, $meta_key, $meta_value)
    {
        output_log(['updated_postmeta', $meta_id, $object_id, $meta_key, $meta_value]);

        // Si "sizes" n'existe pas, c'est que nous sommes sur la création du média
        if($meta_key == '_wp_attachment_metadata' && wp_attachment_is_image($object_id) && empty($meta_value['sizes'])) {
            $this->createImage($object_id);
        }
    }

    private function createImage($attachment_id)
    {
        output_log(['createImage', $attachment_id]);

        // Le hook add_attachment est appelé à chaque wp_insert_post
        // Il ne faut pas définir toutes les métas suivantes pour une création
        // C'est la traduction qui synchronise les métas depuis l'image source (lib-polylang > woodyPllCreateMediaTranslation)
        if($this->isNeverTranslate($attachment_id)) {

            $file = get_attached_file($attachment_id);
            $attachment = get_post($attachment_id);
            $meta = wp_read_image_metadata($file);

            if (!empty($meta['title'])) {
                $attachment->post_title = $meta['title'];
            } else {
                $attachment->post_title = ucwords(strtolower(preg_replace('#\s*[-_\s]+\s*#', ' ', $post->post_title)));
            }

            if (!empty($meta['description'])) {
                $attachment->post_excerpt = $meta['description'];
            } elseif (!empty($meta['caption'])) {
                $attachment->post_excerpt = $meta['caption'];
            } else {
                $attachment->post_excerpt = $attachment->post_title;
            }

            if (!empty($meta['caption'])) {
                $attachment->post_content = $meta['caption'];
            } else {
                $attachment->post_content = $attachment->post_excerpt;
            }

            // Set the image Alt-Text
            output_log([' - addAttachment ALT', $attachment->post_excerpt]);
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment->post_excerpt);

            // Set the image meta (e.g. Title, Excerpt, Content)
            output_log([' - addAttachment POST', $attachment->post_title]);
            wp_update_post([
                'ID' => $attachment_id,
                'post_title' => $attachment->post_title,
                'post_excerpt' => $attachment->post_excerpt,
                'post_content' => $attachment->post_content,
            ]);

            // Defined attachement lang
            $lang = pll_current_language();
            $lang = (empty($lang)) ? PLL_DEFAULT_LANG : $lang;
            pll_set_post_language($attachment_id, pll_current_language());

            // Set ACF Fields (Credit)
            if (!empty($meta['credit'])) {
                //output_log([' - addAttachment CREDIT', $meta['credit']]);
                update_field('media_author', $meta['credit'], $attachment_id);
            }

            if (!empty($meta['latitude'])) {
                //output_log([' - addAttachment LAT', $meta['latitude']]);
                update_field('media_lat', $meta['latitude'], $attachment_id);
            }

            if (!empty($meta['longitude'])) {
                //output_log([' - addAttachment LONG', $meta['longitude']]);
                update_field('media_lng', $meta['longitude'], $attachment_id);
            }

            // Import tags
            //output_log([' - addAttachment PLACES']);
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
                //output_log([' - addAttachment CAT']);
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

                //output_log([' - addAttachment THEMES']);
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

                //output_log([' - addAttachment SEASONS']);
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

            // Déclaration des crops
            $this->updateAttachmentSizes($attachment_id, $file);

            // Dupliquer l'image dans toutes les langues
            $this->translateAttachment($attachment_id);

            // Linked Video
            $this->imageLinkedVideo($attachment_id);

            // Cleanup
            dropzone_delete('woody_attachments_unused_ids');

        }
    }

    public function attachmentFieldsToSave($post, $attachment)
    {
        if (!empty($post['ID'])) {
            $this->saveAttachment($post['ID']);
        }

        return $post;
    }

    private function updateAttachmentSizes($attachment_id, $file)
    {
        // Added default sizes
        global $_wp_additional_image_sizes;
        $_wp_additional_image_sizes['thumbnail'] = ['height' => 150, 'width' => 150, 'crop' => true];
        $_wp_additional_image_sizes['medium'] = ['height' => 300, 'width' => 300, 'crop' => true];
        $_wp_additional_image_sizes['large'] = ['height' => 1024, 'width' => 1024, 'crop' => true];

        // Get Mime-Type
        $mime_type = mime_content_type($file);
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

        // Get Image-Size
        $getimagesize = getimagesize($file);
        if ($getimagesize !== false) {
            $width = $getimagesize[0];
            $height = $getimagesize[1];

            // Added full size
            $filename = explode('/', $file);
            $filename = end($filename);
            $metadata['sizes']['full'] = [
                'file' => $filename,
                'height' => $height,
                'width' => $width,
                'mime-type' => $mime_type
            ];

            // Save Metadata
            output_log([' - updateAttachmentSizes', $attachment_id, $metadata]);
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }

    private function isNeverTranslate($attachment_id)
    {
        return (empty(pll_get_post_translations($attachment_id)));
    }

    private function translateAttachment($attachment_id)
    {
        $translations = pll_get_post_translations($attachment_id);
        $source_lang = pll_get_post_language($attachment_id);

        //output_log(['translateAttachment', $attachment_id, $source_lang, $translations]);
        $languages = pll_languages_list();
        foreach ($languages as $target_lang) {
            // Duplicate media with Polylang Method
            if (!array_key_exists($target_lang, $translations)) {
                output_log(['woody_pll_create_media_translation', $attachment_id, $source_lang, $target_lang]);
                woody_pll_create_media_translation($attachment_id, $source_lang, $target_lang);
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

        //output_log(['readImageMetadata_meta', $meta]);
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
