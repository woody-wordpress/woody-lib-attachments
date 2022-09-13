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
                output_h1('Found ' . $query->found_posts . ' posts to analyse');
                $posts_ids = array_merge($posts_ids, $query->posts);

                while ($count_posts < $query->found_posts) {
                    $count_posts += $query->post_count;
                    $posts_ids = array_merge($posts_ids, $query->posts);
                    $offset += $query->post_count;
                    $query = $this->attachmentsTableManager->getPosts($posts_to_get, $offset);
                }
            }
        } else {
            $posts_ids = explode(',', $assoc_args['posts']);
        }

        if (!empty($posts_ids)) {
            $field_names = $this->attachmentsTableManager->getAttachmentsFieldNames();
            $i = 0;
            foreach ($posts_ids as $post_id) {
                do_action('woody_async_add', 'get_attachments_by_post', ['post_id' => $post_id, 'field_names' => $field_names]);
                ++$i;
            }

            output_h1($i . ' async created');
        }
    }
}
