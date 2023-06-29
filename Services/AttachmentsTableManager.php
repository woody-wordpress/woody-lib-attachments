<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

use Symfony\Component\Finder\Finder;
use JsonException;

class AttachmentsTableManager
{
    /**
     * @var mixed[]|mixed
     */
    public $image_fields;

    public function upgrade()
    {
        $saved_version = (int) get_option('woody_attachments_db_version');
        if ($saved_version < 100 && $this->upgrade_100()) {
            update_option('woody_attachments_db_version', 100);
        }
    }

    private function upgrade_100()
    {
        global $wpdb;

        // Apply upgrade
        $sql = [];
        $charset_collate = $wpdb->get_charset_collate();
        $sql[] = "CREATE TABLE `{$wpdb->base_prefix}woody_attachments` (
            `post_id` bigint(20) unsigned NOT NULL,
            `attachment_id` bigint(20) unsigned NOT NULL,
            `attachment_type` longtext CHARACTER SET utf8 NOT NULL,
            `meta_key` longtext CHARACTER SET utf8 NOT NULL
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        if (empty($wpdb->last_error)) {
            output_success('+ woody-lib-attachments upgrade_100');
            return true;
        } else {
            output_error('+ woody-lib-attachments upgrade_100');
            return false;
        }
    }

    public function getAttachmentsFieldNames()
    {
        $this->image_fields = [];

        $json_paths = array_values(array_unique(apply_filters('woody_acf_save_paths', [])));

        $finder = new Finder();
        $finder->name('*.json')->files()->in($json_paths);

        if (!empty($finder) && is_iterable($finder)) {
            foreach ($finder as $file) {
                $file_path = $file->getRealPath();
                try {
                    $data = json_decode(file_get_contents($file_path), true, 512, JSON_THROW_ON_ERROR);
                    $this->getMatchingFields($data['fields']);
                } catch (JsonException $e) {
                    output_error(sprintf('[getAttachmentsFieldNames] %s', $file_path));
                }
            }
        }

        return array_unique($this->image_fields);
    }

    private function getMatchingFields($fields)
    {
        $matching_types = ['image', 'gallery', 'file'];
        $loop_types = ['group', 'repeater'];
        if (!empty($fields) && is_array($fields)) {
            foreach ($fields as $field) {
                if (in_array($field['type'], $loop_types)) {
                    $this->getMatchingFields($field['sub_fields']);
                } elseif (in_array($field['type'], $matching_types)) {
                    $this->image_fields[] = $field['name'];
                }
            }
        }
    }

    public function getPosts($posts_per_page = 1000, $offset = 0)
    {
        $posts_types = get_post_types(['public' => true, '_builtin' => false]);
        unset($posts_types['touristic_sheet']);
        unset($posts_types['short_link']);
        $posts_types['page'] = 'page';

        $args = [
            'post_type' => $posts_types,
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => $posts_per_page,
            'paged' => 1,
            'offset' => $offset,
            'fields' => 'ids'
        ];

        $args = apply_filters('woody_lib_attachments_getposts_args', $args);

        $wpQuery = new \WP_Query($args);

        if ($wpQuery->have_posts()) {
            return $wpQuery;
        }
    }

    public function getAttachmentsByPost($args)
    {
        $post_id = $args['post_id'];

        if (empty($post_id)) {
            output_error('Missing argument post_id');
            return;
        }

        $field_names = empty($args['field_names']) ? $this->getAttachmentsFieldNames() : $args['field_names'];

        output_h1('Getting attachments for post ' . $post_id);

        $attachments_ids = [];
        $root_values = $this->getFieldsValues($field_names, $post_id, '', true);

        // Check page hero movies files
        $hero_movie_mp4 = get_post_meta($post_id, 'page_heading_movie_mp4_movie_file', true);
        if (!empty($hero_movie_mp4)) {
            $root_values[] = [
                'id' => $hero_movie_mp4,
                'meta_key' => 'page_heading_movie_mp4_movie_file'
            ];
        }

        // Check page hero movies files
        $hero_movie_ogg = get_post_meta($post_id, 'page_heading_movie_movie_ogg_file', true);
        if (!empty($hero_movie_ogg)) {
            $root_values[] = [
                'id' => $hero_movie_ogg,
                'meta_key' => 'page_heading_movie_movie_ogg_file'
            ];
        }

        // Check page hero movies files
        $hero_movie_webm = get_post_meta($post_id, 'page_heading_movie_movie_webm_file', true);
        if (!empty($hero_movie_webm)) {
            $root_values[] = [
                'id' => $hero_movie_webm,
                'meta_key' => 'page_heading_movie_movie_webm_file'
            ];
        }

        if (!empty($root_values)) {
            $attachments_ids = array_merge($root_values, $attachments_ids);
        }

        $sections = get_field('section', $post_id);

        if (!empty($sections) && is_array($sections)) {
            foreach ($sections as $section_key => $section) {
                $sections_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key);
                if (is_array($sections_values)) {
                    $attachments_ids = array_merge($sections_values, $attachments_ids);
                }

                if (!empty($section['section_content']) && is_array($section['section_content'])) {
                    foreach ($section['section_content'] as $layout_key => $layout) {
                        $section_content_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key);
                        if (is_array($section_content_values)) {
                            $attachments_ids = array_merge($section_content_values, $attachments_ids);
                        }

                        if ($layout['acf_fc_layout'] == 'tabs_group' && !empty($layout['tabs']) && is_array($layout['tabs'])) {
                            foreach ($layout['tabs'] as $tab_key => $tab) {
                                $tabs_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_tabs_' . $tab_key);
                                if (is_array($tabs_values)) {
                                    $attachments_ids = array_merge($tabs_values, $attachments_ids);
                                }

                                if (!empty($tab['light_section_content']) && is_array($tab['light_section_content'])) {
                                    foreach ($tab['light_section_content'] as $tab_layout_key => $tab_layout) {
                                        $tabs_content_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_tabs_' . $tab_key . '_light_section_content_' . $tab_layout_key);
                                        if (is_array($tabs_content_values)) {
                                            $attachments_ids = array_merge($tabs_content_values, $attachments_ids);
                                        }
                                        if ($tab_layout['acf_fc_layout'] == 'manual_focus' && !empty($tab_layout['content_selection']) && is_array($tab_layout['content_selection'])) {
                                            foreach ($tab_layout['content_selection'] as $content_selection_key => $content_selection) {
                                                if ($content_selection['content_selection_type'] === 'custom_content') {
                                                    $content_selection_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_tabs_' . $tab_key . '_light_section_content_' . $tab_layout_key . '_content_selection_' . $content_selection_key . '_custom_content');
                                                    if (is_array($content_selection_values)) {
                                                        $attachments_ids = array_merge($content_selection_values, $attachments_ids);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if ($layout['acf_fc_layout'] == 'manual_focus' && !empty($layout['content_selection']) && is_array($layout['content_selection'])) {
                            foreach ($layout['content_selection'] as $content_selection_key => $content_selection) {
                                if ($content_selection['content_selection_type'] === 'custom_content') {
                                    $content_selection_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_content_selection_' . $content_selection_key . '_custom_content');
                                    if (is_array($content_selection_values)) {
                                        $attachments_ids = array_merge($content_selection_values, $attachments_ids);
                                    }
                                }
                            }
                        }

                        if ($layout['acf_fc_layout'] == 'interactive_gallery' && !empty($layout['interactive_gallery_items']) && is_array($layout['interactive_gallery_items'])) {
                            foreach (array_keys($layout['interactive_gallery_items']) as $gallery_key) {
                                $gallery_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_interactive_gallery_items_' . $gallery_key);
                                if (is_array($gallery_values)) {
                                    $attachments_ids = array_merge($gallery_values, $attachments_ids);
                                }
                            }
                        }

                        if ($layout['acf_fc_layout'] == 'links' && !empty($layout['links']) && is_array($layout['links'])) {
                            foreach (array_keys($layout['links']) as $link_key) {
                                $links_values = $this->getFieldsValues($field_names, $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_links_' . $link_key);
                                if (is_array($links_values)) {
                                    $attachments_ids = array_merge($links_values, $attachments_ids);
                                }
                            }
                        }
                    }
                }
            }
        }


        $wysiwyg_medias_ids = [];
        $wysiwyg_medias_ids = $this->wysiwygMediasIds($post_id, $wysiwyg_medias_ids);
        $wysiwyg_medias_ids = $this->wysiwygMediasIdsByNames($post_id, $wysiwyg_medias_ids);
        $wysiwyg_medias_ids = array_values($wysiwyg_medias_ids);

        if (!empty($wysiwyg_medias_ids)) {
            $attachments_ids = array_merge($wysiwyg_medias_ids, $attachments_ids);
        }

        if (!empty($attachments_ids)) {
            output_success('Found ' . count($attachments_ids) . ' attachments used in post ' . $post_id);
            $this->insertAttachmentPost($attachments_ids, $post_id);
        } else {
            output_log('Post ' . $post_id . " isn't using any attachment");
        }
    }

    private function wysiwygMediasIds($post_id, $ids = [])
    {
        global $wpdb;

        // On récupère toutes les postmeta contenant une référence à wp-json/woody/crop et on en extrait le/les identifiant(s) d'attachment
        $req = "SELECT p.ID, pm.meta_key, pm.meta_value FROM {$wpdb->prefix}postmeta as pm LEFT JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID WHERE p.ID = {$post_id} AND pm.meta_value LIKE '%/wp-json/woody/crop/%'";
        $results = $wpdb->get_results($wpdb->prepare($req));

        if (!empty($results)) {
            foreach ($results as $result) {
                $matches = [];
                preg_match_all('#wp-json/woody/crop/(\d+)/ratio#', $result->meta_value, $matches, PREG_SET_ORDER);
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        unset($match[0]);
                        if (!empty($match)) {
                            foreach ($match as $match_value) {
                                $ids[] = [
                                    'id' => $match_value,
                                    'meta_key' => $result->meta_key
                                ];
                            }
                        }
                    }
                }
            }
        }

        return array_unique($ids);
    }

    private function wysiwygMediasIdsByNames($post_id, $ids = [])
    {
        global $wpdb;

        // On récupère toutes les postmeta contenant une référence à app/uploads/sitekey et on en extrait le/les identifiant(s) d'attachment
        $site_key = WP_SITE_KEY;
        $req = "SELECT p.ID, pm.meta_key, pm.meta_value FROM {$wpdb->prefix}postmeta as pm LEFT JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID WHERE p.ID = {$post_id} AND pm.meta_value LIKE '%/app/uploads/{$site_key}/%' AND pm.meta_value NOT LIKE '%/wp-json/woody/crop/%'";
        $results = $wpdb->get_results($wpdb->prepare($req));
        if (!empty($results)) {
            foreach ($results as $result) {
                $matches = [];
                preg_match_all('#/app/uploads/'. WP_SITE_KEY .'/[0-9]{4}/[0-9]{2}/([^" ]+)#', $result->meta_value, $matches, PREG_SET_ORDER);
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $file_path = $this->filterFilePath($match[0]);
                        $att_ids_req = "SELECT ID FROM {$wpdb->prefix}posts WHERE guid LIKE '%{$file_path}'";
                        $att_ids_results = $wpdb->get_results($wpdb->prepare($att_ids_req));

                        foreach ($att_ids_results as $att_id_result) {
                            $ids[] = [
                                'id' => $att_id_result->ID,
                                'meta_key' => $result->meta_key
                            ];
                        }
                    }
                }
            }
        }

        return array_unique($ids);
    }

    private function filterFilePath($file_path)
    {
        $file_path = str_replace('/thumbs', '', $file_path);
        preg_match('#(-\d+x\d+-[^.]+).#', $file_path, $matches);
        if (is_array($matches) && !empty($matches[1])) {
            $file_path = str_replace($matches[1], '', $file_path);
        } else {
            preg_match('#(-\d+x\d+).#', $file_path, $matches);
            if (is_array($matches) && !empty($matches[1])) {
                $file_path = str_replace($matches[1], '', $file_path);
            }
        }

        return $file_path;
    }


    public function getFieldsValues($field_names, $post_id, $meta_key = '', $acf = false)
    {
        $return = [];
        if (!empty($field_names) && is_array($field_names)) {
            foreach ($field_names as $field_name) {
                $full_meta_key = ($acf) ? $field_name : $meta_key . '_' . $field_name;
                $value = ($acf) ? get_field($field_name, $post_id) : get_post_meta($post_id, $full_meta_key, true);

                if (!empty($value)) {
                    if ((is_string($value) || is_int($value))) {
                        $return[] = [
                        'id' => $value,
                        'meta_key' => $full_meta_key
                    ];
                    } elseif (is_array($value)) {
                        if (is_int($value['ID'])) {
                            $return[] = [
                                'id' => $value['ID'],
                                'meta_key' => $full_meta_key
                            ];
                        } else {
                            foreach ($value as $attachement_id) {
                                $return[] = [
                                    'id' => $attachement_id,
                                    'meta_key' => $full_meta_key
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }

    private function insertAttachmentPost($attachments_fields, $post_id)
    {
        global $wpdb;

        output_log('Delete ' . $wpdb->base_prefix . 'woody_attachments rows with post_id = ' . $post_id);
        $this->deleteAttachmentPost($post_id);

        if (!empty($attachments_fields) && is_array($attachments_fields)) {
            foreach ($attachments_fields as $attachment_field) {
                $wpdb->insert(
                    $wpdb->base_prefix . 'woody_attachments',
                    [
                        'post_id' => $post_id,
                        'attachment_id' => $attachment_field['id'],
                        'attachment_type' => get_post_mime_type($attachment_field['id']),
                        'meta_key' => $attachment_field['meta_key']
                    ]
                );
            }

            output_log('Inserted ' . (is_countable($attachments_fields) ? count($attachments_fields) : 0) . ' rows in ' . $wpdb->base_prefix . 'woody_attachments');
        }
    }

    private function deleteAttachmentPost($post_id)
    {
        global $wpdb;
        $wpdb->delete(
            $wpdb->base_prefix . 'woody_attachments',
            [
                'post_id' => $post_id,
            ]
        );
    }
}
