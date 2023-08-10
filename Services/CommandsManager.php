<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

use Woody\Lib\Attachments\Services\AttachmentsTableManager;

class CommandsManager
{
    private \Woody\Lib\Attachments\Services\AttachmentsTableManager $attachmentsTableManager;

    public function __construct(AttachmentsTableManager $attachmentsTableManager)
    {
        $this->attachmentsTableManager = $attachmentsTableManager;
    }

    public function warm($args, $assoc_args)
    {
        if (empty($assoc_args['posts'])) {
            $posts_to_get = 100;
            $count_posts = 0;
            $posts_ids = [];
            $offset = 0;

            $query = $this->attachmentsTableManager->getPosts($posts_to_get, $offset);
            if (!empty($query)) {
                while ($count_posts < $query->found_posts) {
                    $count_posts += $query->post_count;
                    $posts_ids = array_merge($posts_ids, $query->posts);
                    $offset += $query->post_count;
                    $query = $this->attachmentsTableManager->getPosts($posts_to_get, $offset);
                }

                output_h1(sprintf('Resquest is done. Found %s post to analyse', $count_posts));
            }
        } else {
            $posts_ids = explode(',', $assoc_args['posts']);
        }

        if (!empty($posts_ids)) {
            $field_names = $this->attachmentsTableManager->getAttachmentsFieldNames();
            $i = 0;
            foreach ($posts_ids as $post_id) {
                do_action('woody_async_add', 'get_attachments_by_post', ['post_id' => $post_id, 'field_names' => $field_names], 'post_' . $post_id, true);
                ++$i;
            }

            output_h1($i . ' async created');
        }
    }

    public function deleteByLang($assoc_args)
    {
        if(empty($assoc_args['lang'])) {
            output_error('Merci de spécifier la langue dans laquelle supprimer les médias');
            return;
        }

        define('KEEP_ATTACHMENTS_TRANSLATION', true);

        $count_posts = 0;
        $atts_ids = [];
        $offset = 0;
        $query = $this->attachmentsQuery($assoc_args['lang'], $offset);

        if (!empty($query)) {
            while ($count_posts < $query->found_posts) {
                $count_posts += $query->post_count;
                $atts_ids = array_merge($atts_ids, $query->posts);
                $offset += $query->post_count;
                $query = $this->attachmentsQuery($assoc_args['lang'], $offset);
            }
        }

        output_h1(sprintf('Resquest is done. Found %s attachments to delete', $count_posts));

        if(!empty($atts_ids)) {
            foreach ($atts_ids as $att_id) {
                wp_delete_post($att_id, true);
            }
        }
    }

    private function attachmentsQuery($lang, $offset)
    {
        if(empty($lang)) {
            return;
        }

        return new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'lang' => $lang,
            'posts_per_page' => 500,
            'fields' => 'ids',
            'offset' => $offset
        ]);
    }
}
