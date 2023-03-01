<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsUnused
{
    public function generateUnusedList()
    {
        add_submenu_page(
            'upload.php',
            'Liste des médias inutilisés',
            'Médias inutilisés',
            'edit_posts',
            'woody-unused-attachments',
            [$this, 'unusedMediaList']
        );
    }


    public function unusedMediaList()
    {
        $data = [];
        $delete_ids = [];
        if (!empty($_POST) && is_array($_POST)) {
            foreach ($_POST as $id_key => $id) {
                if (strpos($id_key, 'cb-select-') === 0) {
                    $delete_ids[] = $id;
                    update_post_meta($id, 'woody_attachment_deleting', true, );
                }
            }
        }

        if (!empty($delete_ids)) {
            do_action('woody_async_add', 'delete_unsused_attachments', $delete_ids);
        }

        $unused_ids = $this->getUnusedIds();

        if (!empty($unused_ids)) {
            // On créé la pagination si besoin
            $items_length = 100;
            $pager = filter_input(INPUT_GET, 'pager', FILTER_VALIDATE_INT);
            $data['unused_ids'] = count($unused_ids);
            $data['max_num_pages'] = round($data['unused_ids'] / $items_length);
            $data['pager'] = empty($pager) ? 1 : $pager;
            $data['pagination'] = $this->createPagination($data['max_num_pages'], $data['pager']);

            // On récupère les items à afficher en fonction de la page courante
            $offset = $items_length * ($data['pager'] - 1);
            $posts_ids = array_slice($unused_ids, $offset, $items_length);
            $data['items'] = $this->getItems($posts_ids);
        }

        return \Timber::render('attachments-unused.twig', $data);
    }

    private function getUnusedIds()
    {
        $unused_ids = dropzone_get('woody_attachments_unused_ids');

        if (empty($unused_ids)) {
            global $wpdb;
            // On récupère la liste de tous les identifiants dont au moins l'une des traductions est utilisée
            $used_ids = $this->getUsedIds();
            $used_ids_str = (is_array($used_ids) && !empty($used_ids)) ? implode($used_ids, ',') : '';

            // On récupère les ids de tous les attachments et on retire les éléments dont l'une des traductions est utilisé
            if (!empty($used_ids_str)) {
                $unsused_ids_req = "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND ID NOT IN ({$used_ids_str})";
                $unsused_ids_results = $wpdb->get_results($wpdb->prepare($unsused_ids_req));

                if (!empty($unsused_ids_results)) {
                    $unused_ids = array_map([$this, 'getResultsID'], $unsused_ids_results);

                    // On ne montre que les attachment en langue par défaut => la suppression de l'un entrainera la suppression de toutes ses traductions
                    foreach ($unused_ids as $unused_id_key => $unused_id) {
                        $att_lang = pll_get_post_language($unused_id);
                        if ($att_lang !== PLL_DEFAULT_LANG) {
                            unset($unused_ids[$unused_id_key]);
                        }
                    }

                    dropzone_set('woody_attachments_unused_ids', $unused_ids);
                }
            }
        }

        return $unused_ids;
    }

    private function getUsedIds()
    {
        $ids = [];

        global $wpdb;

        // Retourne les identifiants sérialisés des toutes les traductions de tous les attachements qui sont dans la table woody_attachments
        $req_str = "SELECT tt.description
        FROM {$wpdb->prefix}terms AS t
        INNER JOIN {$wpdb->prefix}term_taxonomy AS tt
        ON t.term_id = tt.term_id
        WHERE t.term_id IN (SELECT DISTINCT t.term_id
        FROM {$wpdb->prefix}terms AS t
        INNER JOIN {$wpdb->prefix}term_taxonomy AS tt
        ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->prefix}term_relationships AS tr
        ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tt.taxonomy IN ('post_translations')
        AND tr.object_id IN (SELECT attachment_id FROM {$wpdb->prefix}woody_attachments)
        ORDER BY t.name ASC)";

        $results = $wpdb->get_results($wpdb->prepare($req_str));

        if (!empty($results)) {
            foreach ($results as $result) {
                $unserialized_ids = maybe_unserialize($result->description);
                if (!empty($unserialized_ids)) {
                    foreach ($unserialized_ids as $id) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return array_unique($ids);
    }

    private function getItems($posts_ids)
    {
        $items = [];
        if (!empty($posts_ids)) {
            foreach ($posts_ids as $post_id) {
                $items[] = [
                    'post_id' => $post_id,
                    'post_name' => get_post_field('post_name', $post_id),
                    'link' => wp_get_attachment_url($post_id),
                    'thumbnail' => wp_get_attachment_image($post_id, 'ratio_square_small'),
                    'deleting' => get_post_meta($post_id, 'woody_attachment_deleting', true)
                ];
            }
        }

        return $items;
    }

    private function createPagination($max_num_pages, $pager)
    {
        $pagination = [];

        if (!empty($max_num_pages) && $max_num_pages > 1) {
            for ($i=1; $i <= $max_num_pages; ++$i) {
                $min_visible = $pager - 5;
                $max_visible = $pager + 5;

                $pagination[$i] = [
                    'current' => $i == $pager,
                    'visible' => $i > $min_visible && $i < $max_visible,
                    'link' => admin_url('upload.php?page=woody-unused-attachments' . '&pager=' . $i)
                ];
            }

            $pagination['first'] = [
                'visible' => true,
                'link' => admin_url('upload.php?page=woody-unused-attachments')
            ];
            $pagination['last'] = [
                'visible' => true,
                'link' => admin_url('upload.php?page=woody-unused-attachments' . '&pager=' . $max_num_pages)
            ];
        }


        return $pagination;
    }

    private function getResultsID($obj)
    {
        return $obj->ID;
    }

    public function deleteAttachments($posts_ids)
    {
        if (!empty($posts_ids) && is_array($posts_ids)) {
            foreach ($posts_ids as $post_id) {
                output_log('Deleting post ' . $post_id);
                wp_delete_post($post_id, true);
            }
        }

        dropzone_delete('woody_attachments_unused_ids');
    }
}
