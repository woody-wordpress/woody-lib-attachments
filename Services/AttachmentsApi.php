<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsApi
{
    public function getAttachmentTerms()
    {
        $return = [];
        $taxs = ['themes', 'places', 'seasons'];
        foreach ($taxs as $tax) {
            $terms = get_terms([
                'taxonomy' => $tax,
                'hide_empty' => false
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
        $attach_ids = filter_input(INPUT_POST, 'attach_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $term_ids = filter_input(INPUT_POST, 'term_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

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
}
