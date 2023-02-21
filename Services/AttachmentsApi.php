<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

use Woody\Lib\Attachments\Services\AttachmentsTableManager;

class AttachmentsApi
{
    private \Woody\Lib\Attachments\Services\AttachmentsTableManager $attachmentsTableManager;

    public function __construct(AttachmentsTableManager $attachmentsTableManager)
    {
        $this->attachmentsTableManager = $attachmentsTableManager;
    }

    public function getAttachmentTerms()
    {
        $return = [];
        $taxs = ['themes', 'places', 'seasons'];
        foreach ($taxs as $tax) {
            $terms = get_terms([
                'taxonomy' => $tax,
                'hide_empty' => false,
                'lang' => PLL_DEFAULT_LANG
            ]);

            foreach ($terms as $term) {
                if (!is_wp_error($term)) {
                    $return[$tax][] = [
                        'id' => $term->term_id,
                        'name' => $term->name
                    ];
                }
            }
        }

        return $return;
    }

    public function setAttachmentsTerms()
    {
        $attach_ids = explode(',', filter_input(INPUT_GET, 'attach_ids'));
        $term_ids = explode(',', filter_input(INPUT_GET, 'terms_ids'));

        if (!empty($attach_ids) && !empty($term_ids)) {
            foreach ($attach_ids as $attach_id) {
                foreach ($term_ids as $term_id) {
                    $term = get_term($term_id);

                    if (!is_wp_error($term)) {
                        wp_set_post_terms($attach_id, $term->term_id, $term->taxonomy, true);
                    }
                }
            }

            wp_send_json(true);
        } else {
            wp_send_json(false);
        }
    }

    public function replacePostsMeta()
    {
        $updates = [];
        global $wpdb;

        $results = [];
        $original_search = filter_input(INPUT_GET, 'search');
        $original_replace = filter_input(INPUT_GET, 'replace');
        $langs = pll_languages_list();

        // Lors du remplacement de fichier, on effectue le remplacement pour toutes les traductions
        if (!empty($langs)) {
            foreach ($langs as $lang) {
                $searches[$lang] = [
                    'search' => pll_get_post($original_search, $lang),
                    'replace' => pll_get_post($original_replace, $lang)
                ];
            }
        }

        if (!empty($searches)) {
            foreach ($searches as $search) {
                if (!empty($search['search'])) {
                    // On récupère toutes les clés/post_id de toutes les meta contenant l'id du média à remplacer
                    $req_str = "SELECT post_id, meta_key FROM {$wpdb->prefix}woody_attachments WHERE attachment_id = '{$search['search']}'";
                    $search_results = $wpdb->get_results($wpdb->prepare($req_str));

                    if (!empty($search_results) && is_array($search_results)) {
                        $results = array_merge($search_results, $results);
                    }
                }
            }

            if (!empty($results)) {
                // $post_ids servira à lancer l'action get_attachments_by_post
                $posts_ids = [];

                foreach ($results as $result) {
                    $posts_ids[$result->post_id] = $result->post_id;

                    $postmeta = get_post_meta($result->post_id, $result->meta_key, true);

                    if (is_array($postmeta)) {
                        // Les tableaux contenant ID sont des attachment acf, sinon cde sont des listes d'identifiants
                        if (empty($postmeta['ID'])) {
                            foreach ($postmeta as $key => $id) {
                                foreach ($searches as $search) {
                                    if ($id == $search['search']) {
                                        $postmeta[$key] = $search['replace'];
                                    }
                                }
                            }
                        } else {
                            $postmeta = acf_get_attachment(get_post($postmeta['ID']));
                        }
                    } else {
                        foreach ($searches as $search) {
                            // Les autres metas sont des str contenant l'id
                            $postmeta = str_replace($search['search'], $search['replace'], $postmeta);
                        }
                    }

                    $updates[$result->post_id . '-' . $result->meta_key] = update_post_meta($result->post_id, $result->meta_key, $postmeta);
                }


                // Pour chaque post modifié, on met à jour la table woody_attachments
                if (!empty($posts_ids) && is_array($posts_ids)) {
                    $field_names = $this->attachmentsTableManager->getAttachmentsFieldNames();
                    foreach ($posts_ids as $post_id) {
                        $this->attachmentsTableManager->getAttachmentsByPost(['post_id' => $post_id, 'field_names' => $field_names]);
                    }
                }
            }
        }

        wp_send_json($updates);
    }

    public function deleteAttachments(\WP_REST_Request $request)
    {
        $params = $request->get_params();
        $ids = $params['ids'] ?: [];
        $deleted = [];
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $post = get_post($id);
                $deleted[] = wp_delete_post($id, true);
            }
        }

        dropzone_delete('woody_attachments_unused_ids');
        wp_send_json($deleted);
    }

    public function exportAttachmentsData(\WP_REST_Request $request)
    {
        $filespath = '';

        $params = $request->get_params();

        $request_args = [
            'post_mime_type' => (empty($params['mimetype'][0])) ? [] : $this->filterMimeTypes($params['mimetype'][0]),
            'language' => (empty($params['language'][0])) ? [] : $params['language'][0]
        ];

        $attachments = [];
        $count_posts = 0;
        $query = $this->attachmentsQuery($request_args);
        $fields = (empty($params['fields'])) ? [] : $params['fields'];

        if (!empty($query) && !empty($query->posts)) {
            while ($count_posts < $query->found_posts) {
                $count_posts += $query->post_count;
                $attachments = array_merge($attachments, $this->getAttachmentsData($query->posts, $fields));
                $request_args['offset'] += $query->post_count;
                $query = $this->attachmentsQuery($request_args);
            }
        }

        if (!empty($attachments)) {
            $filespath= $this->arrayToCsv($attachments, $fields);
            dropzone_set('woody_export_attachments_files', ['path' => $filespath, 'timestamp' => time()]);
        }

        if (!empty($filespath)) {
            wp_send_json($filespath);
        }
    }

    public function attachmentsQuery($request_args)
    {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 200,
            'offset' => (empty($request_args['offset'])) ? 0 : $request_args['offset']
        ];

        if ($request_args['language'] == 'language_default') {
            $args['lang'] = PLL_DEFAULT_LANG;
        }

        if (!empty($request_args['post_mime_type'])) {
            $args['post_mime_type'] = $request_args['post_mime_type'];
        }

        $wpQuery = new \WP_Query($args);

        if ($wpQuery->have_posts()) {
            return $wpQuery;
        }
    }

    public function filterMimeTypes($mimetype)
    {
        $mimetypes = [];

        if ($mimetype == 'all') {
            return [];
        }

        $all_types = get_allowed_mime_types();
        if (!empty($all_types)) {
            foreach ($all_types as $type) {
                if (strpos($type, $mimetype) === 0) {
                    $mimetypes[] = $type;
                }
            }
        }

        return $mimetypes;
    }

    public function getAttachmentsData($posts, $fields)
    {
        $return = [];
        $post_fields = ['ID', 'post_name', 'post_title', 'post_excerpt'];
        $acf_fields = ['media_author', 'medias_rights_management', 'media_lat', 'media_lng'];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                foreach ($post_fields as $post_field) {
                    if (in_array($post_field, $fields)) {
                        $return[$post->ID][$post_field] = $post->{$post_field};
                    }
                }

                foreach ($acf_fields as $acf_field) {
                    if (in_array($acf_field, $fields)) {
                        $return[$post->ID][$acf_field] = get_field($acf_field, $post->ID);
                    }
                }

                if (in_array('url', $fields)) {
                    $return[$post->ID]['path'] = home_url() . str_replace('/home/admin/www/wordpress/current/web', '', get_attached_file($post->ID));
                }
            }
        }

        return $return;
    }

    public function arrayToCsv($attachments)
    {
        output_log('arrayToCsv');
        if (!empty($attachments)) {
            $csvhead = array_keys($attachments[0]);
            array_unshift($attachments, $csvhead);
            output_log($csvhead);
            $filepath = '/home/admin/www/wordpress/current/web/app/uploads/' . WP_SITE_KEY . '/media-export-' . time() . '.csv';
            output_log($filepath);
            $file = fopen($filepath, 'w');

            foreach ($attachments as $attachment) {
                fputcsv($file, $attachment);
            }

            fclose($fp);

            return $filepath;
        }
    }
}
