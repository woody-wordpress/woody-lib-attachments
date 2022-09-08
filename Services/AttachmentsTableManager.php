<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

use Symfony\Component\Finder\Finder;

class AttachmentsTableManager
{
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

        if (!empty($finder)) {
            foreach ($finder as $file) {
                $file_path = $file->getRealPath();
                $data = json_decode(file_get_contents($file_path), true);
                $this->getMatchingFields($data['fields']);
            }
        }

        return array_unique($this->image_fields);
    }

    private function getMatchingFields($fields)
    {
        $matching_types = ['image', 'gallery', 'file'];
        $loop_types = ['group', 'repeater'];
        if (is_array($fields) && !empty($fields)) {
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
        $args = [
            'post_type' => ['page', 'touristic_sheet'],
            'tax_query' => [
                array(
                    'taxonomy' => 'page_type',
                    'field' => 'slug',
                    'terms' => ['mirror_page'],
                    'operator' => 'NOT IN'
                )
            ],
            'orderby' => 'menu_order',
            'order'   => 'DESC',
            'posts_per_page' => $posts_per_page,
            'paged' => 1,
            'offset' => $offset,
            'fields' => 'ids'
        ];

        $args = apply_filters('woody_lib_attachments_getposts_args', $args);

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            return $query;
        }
    }

    public function getAttachmentsByPost($args)
    {
        $post_id = $args['post_id'];

        if (empty($post_id) || empty($args['field_names'])) {
            output_error('Missing argument post_id or field_names to explore');
            return;
        }

        output_h1('Getting attachments for post ' . $post_id);

        $attachments_ids = [];
        $root_values = $this->getFieldsValues($args['field_names'], $post_id, '', true);
        if (!empty($root_values)) {
            $attachments_ids = array_merge($root_values, $attachments_ids);
        }

        $sections = get_field('section', $post_id);

        if (!empty($sections)) {
            foreach ($sections as $section_key => $section) {
                $sections_values = $this->getFieldsValues($args['field_names'], $post_id, 'section_' . $section_key);
                if (is_array($sections_values)) {
                    $attachments_ids = array_merge($sections_values, $attachments_ids);
                }
                if (!empty($section['section_content'])) {
                    foreach ($section['section_content'] as $layout_key => $layout) {
                        $section_content_values = $this->getFieldsValues($args['field_names'], $post_id, 'section_' . $section_key . '_section_content_' . $layout_key);
                        if (is_array($section_content_values)) {
                            $attachments_ids = array_merge($section_content_values, $attachments_ids);
                        }

                        if ($layout['acf_fc_layout'] == 'tabs_group') {
                            foreach ($layout['tabs'] as $tab_key => $tab) {
                                $tabs_values = $this->getFieldsValues($args['field_names'], $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_tabs_' . $tab_key);
                                if (is_array($tabs_values)) {
                                    $attachments_ids = array_merge($tabs_values, $attachments_ids);
                                }
                                if (!empty($tab['light_section_content'])) {
                                    foreach ($tab['light_section_content'] as $tab_layout_key => $tab_layout) {
                                        $tabs_content_values = $this->getFieldsValues($args['field_names'], $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_tabs_' . $tab_key . '_light_section_content_' . $tab_layout_key);
                                        if (is_array($tabs_content_values)) {
                                            $attachments_ids = array_merge($tabs_content_values, $attachments_ids);
                                        }
                                    }
                                }
                            }
                        }

                        if ($layout['acf_fc_layout'] == 'interactive_gallery') {
                            foreach ($layout['interactive_gallery_items'] as $gallery_key => $gallery) {
                                $gallery_values = $this->getFieldsValues($args['field_names'], $post_id, 'section_' . $section_key . '_section_content_' . $layout_key . '_interactive_gallery_items_' . $gallery_key);
                                if (is_array($gallery_values)) {
                                    $attachments_ids = array_merge($gallery_values, $attachments_ids);
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($attachments_ids)) {
            output_success('Found ' . count($attachments_ids) . ' attachments used in post ' . $post_id);
            $this->insertAttachmentPost($attachments_ids, $post_id);
        } else {
            output_log('Post ' . $post_id . ' isn\'t using any attachment');
        }
    }

    public function getFieldsValues($field_names, $post_id, $meta_key = '', $acf = false)
    {
        $return = [];

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

        return $return;
    }

    private function insertAttachmentPost($attachments_fields, $post_id)
    {
        global $wpdb;

        $this->deleteAttachmentPost($post_id);

        if (!empty($attachments_fields)) {
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
