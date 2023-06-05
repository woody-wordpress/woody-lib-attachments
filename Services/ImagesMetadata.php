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

    public function saveAttachment($attachment_id)
    {
        // Lors de la création d'une traduction, on passe dans ce hook mais on ne rentre pas dans le if suivant
        // Car à la création du post de traduction les métas du post source ne sont pas encore enregistrées
        // Et donc le post traduit ne sait pas encore qu'il est une image.
        // Ce comportement est celui souhaité !

        output_log(['saveAttachment_Meta', $attachment_id]);
        if (wp_attachment_is_image($attachment_id)) {
            $file = get_attached_file($attachment_id);
            $attachment = get_post($attachment_id);
            output_log(['saveAttachment_Meta attachment', $attachment]);
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
            output_log([' - saveAttachment_Meta ALT', $attachment->post_excerpt]);
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment->post_excerpt);

            // Set the image meta (e.g. Title, Excerpt, Content)
            output_log([' - saveAttachment_Meta POST', $attachment->post_title]);
            wp_update_post([
                'ID' => $attachment_id,
                'post_title' => $attachment->post_title,
                'post_excerpt' => $attachment->post_excerpt,
                'post_content' => $attachment->post_content,
            ]);

            // Set ACF Fields (Credit)
            if (!empty($meta['credit'])) {
                output_log([' - saveAttachment_Meta CREDIT', $meta['credit']]);
                update_field('media_author', $meta['credit'], $attachment_id);
            }

            if (!empty($meta['latitude'])) {
                output_log([' - saveAttachment_Meta LAT', $meta['latitude']]);
                update_field('media_lat', $meta['latitude'], $attachment_id);
            }

            if (!empty($meta['longitude'])) {
                output_log([' - saveAttachment_Meta LONG', $meta['longitude']]);
                update_field('media_lng', $meta['longitude'], $attachment_id);
            }

            // Import tags
            output_log([' - saveAttachment_Meta PLACES']);
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
                output_log([' - saveAttachment_Meta CAT']);
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

                output_log([' - saveAttachment_Meta THEMES']);
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

                output_log([' - saveAttachment_Meta SEASONS']);
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

        output_log(['readImageMetadata_meta', $meta]);
        return $meta;
    }

    public function generateAttachmentMetadata($metadata, $attachment_id)
    {
        output_log(['generateAttachmentMetadata', $metadata, $attachment_id]);
        if (wp_attachment_is_image($attachment_id) && empty($metadata['sizes'])) {

            // Crop API
            global $_wp_additional_image_sizes;
            // Added default sizes
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
        }

        return $metadata;
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
