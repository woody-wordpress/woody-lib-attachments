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
            'upload.php', // Creating page not displayed in menu by setting parent slug to null
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

        global $wpdb;

        // On récupère les identifiants des posts de type attachment qui ne sont pas présents dans la table woody-attachment
        // Cette table répertorie, pour chaque image, les pages sur lesquelles elle est utilisée => image absente de la table == non utilisée
        // Les posts correspondant à ces identifiants pourront alors être supprimés.
        $unsused_ids_req = "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'attachment' AND ID NOT IN (SELECT attachment_id FROM {$wpdb->prefix}woody_attachments)";
        $unsused_ids_results = $wpdb->get_results($wpdb->prepare($unsused_ids_req));

        if (!empty($unsused_ids_results)) {
            $unused_ids = array_map([$this, 'getResultsID'], $unsused_ids_results);
            $data['total_unused'] = count($unused_ids);
            $data['unused_ids'] = implode(',', $unused_ids);
        }

        // On récupère les identifiants des médias ajouté dans les champs WYSIWYG afin de les exclure de la commande de suppression
        $wysiwyg_medias_ids = [];
        $wysiwyg_medias_ids = $this->wysiwygMediasIds($wysiwyg_medias_ids);
        $wysiwyg_medias_ids = $this->wysiwygMediasIdsByNames($wysiwyg_medias_ids);
        $wysiwyg_medias_ids = array_values($wysiwyg_medias_ids);
        $data['total_wysiwyg'] = count($wysiwyg_medias_ids);

        // On retire les identifiants des médias utilisés dans les blocs WYSIWYG des id à supprimer
        $ids_to_remove = array_diff($unused_ids, $wysiwyg_medias_ids);

        // On récupère la liste des langues afin de pondérer le nombre d'images inutilisées
        // (l'utilisateur n'a pas consicence de la duplication des attachments par langue)
        $langs = pll_languages_list();
        if (is_countable($langs) && is_countable($unused_ids)) {
            $data['total_langs'] = count($langs);
            $data['total_remove'] = count($ids_to_remove);

            // On récupère le nombre total d'attachments pour l'afficher à l'utilisateur
            $data['total_attachments'] = 0;
            $attachments_details = wp_count_attachments();
            if (!empty($attachments_details)) {
                foreach ($attachments_details as $count) {
                    $data['total_attachments'] += $count;
                }
            }

            $data['total_media_fields'] = $data['total_attachments'] - $data['total_unused'];
        }

        return \Timber::render('attachments-unused.twig', $data);

        require_once(WOODY_LIB_ATTACHMENTS_DIR_RESOURCES . '/Templates/attachments-unused.php');
    }

    private function getResultsID($obj)
    {
        return $obj->ID;
    }

    private function wysiwygMediasIds($ids = [])
    {
        global $wpdb;

        // On récupère toutes les postmeta contenant une référence à wp-json/woody/crop et on en extrait le/les identifiant(s) d'attachment
        $req = "SELECT p.ID, pm.meta_key, pm.meta_value FROM {$wpdb->prefix}postmeta as pm LEFT JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID WHERE p.post_type = 'page' AND p.post_status = 'publish' AND pm.meta_value LIKE '%/wp-json/woody/crop/%'";
        $results = $wpdb->get_results($wpdb->prepare($req));

        if (!empty($results)) {
            foreach ($results as $result) {
                $matches = [];
                preg_match_all('#wp-json/woody/crop/([0-9]+)/ratio#', $result->meta_value, $matches, PREG_SET_ORDER);
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        unset($match[0]);
                        $ids = array_merge($match, $ids);
                    }
                }
            }
        }

        return array_unique($ids);
    }

    private function wysiwygMediasIdsByNames($ids = [])
    {
        global $wpdb;

        // On récupère toutes les postmeta contenant une référence à app/uploads/sitekey et on en extrait le/les identifiant(s) d'attachment
        $site_key = WP_SITE_KEY;
        $req = "SELECT p.ID, pm.meta_key, pm.meta_value FROM {$wpdb->prefix}postmeta as pm LEFT JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID WHERE p.post_type = 'page' AND p.post_status = 'publish' AND pm.meta_value LIKE '%/app/uploads/{$site_key}/%' AND pm.meta_value NOT LIKE '%/wp-json/woody/crop/%'";
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
                            $ids[] = $att_id_result->ID;
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
        preg_match('#(-[0-9]+x[0-9]+-[^.]+).#', $file_path, $matches);
        if (is_array($matches) && !empty($matches[1])) {
            $file_path = str_replace($matches[1], '', $file_path);
        } else {
            preg_match('#(-[0-9]+x[0-9]+).#', $file_path, $matches);
            if (is_array($matches) && !empty($matches[1])) {
                $file_path = str_replace($matches[1], '', $file_path);
            }
        }

        return $file_path;
    }
}
