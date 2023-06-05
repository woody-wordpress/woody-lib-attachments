<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsManager
{
    public function deleteAttachment($attachment_id)
    {
        remove_action('delete_attachment', [$this, 'deleteAttachment']);

        $deleted_attachement = wp_cache_get('woody_deleted_attachement', 'woody');
        if (empty($deleted_attachement)) {
            $deleted_attachement = [];
        }

        if (wp_attachment_is_image($attachment_id) && is_array($deleted_attachement) && !in_array($attachment_id, $deleted_attachement)) {
            // Remove translations
            $translations = pll_get_post_translations($attachment_id);
            $deleted_attachement = array_merge($deleted_attachement, array_values($translations));
            wp_cache_set('woody_deleted_attachement', $deleted_attachement, 'woody');

            foreach ($translations as $t_attachment_id) {
                if ($t_attachment_id != $attachment_id) {
                    wp_delete_attachment($t_attachment_id);
                }
            }
        }
    }

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

    public function timberRender($render)
    {
        return preg_replace('#http(s?):\/\/([a-zA-Z0-9-_.]*)\/app\/uploads\/([^\/]*)\/(\d*)\/(\d*)\/..\/..\/..\/..\/..\/wp-json\/woody\/crop\/(\d*)\/ratio_([a-z0-9-_]*)#', 'http$1://$2/wp-json/woody/crop/$6/ratio_$7', $render);
    }

    public function savePost($post_id, $post, $update)
    {
        $exclude = ['attachment', 'touristic_sheet', 'short_link', 'revision'];

        if (!empty('post') && !in_array($post->post_type, $exclude)) {
            do_action('woody_async_add', 'get_attachments_by_post', ['post_id' => $post_id], 'post_' . $post_id, true);
            dropzone_delete('woody_attachments_unused_ids');
        }
    }

    public function woodyExpiredMediaAddColumn($columns)
    {
        $columns['expire'] = 'Date d\'expiration';

        return $columns;
    }

    public function woodyExpiredMediaFillColumn($column_name, $post_id)
    {
        if ($column_name == 'expire') {
            $expire_date = get_field('attachment_expire', $post_id);
            $now = time();
            $expire_time = (empty($expire_date)) ? false : strtotime($expire_date);

            switch ($expire_time) {
                case false :
                    $color = 'transparent';
                    break;
                case $expire_time < $now:
                    $color = 'red';
                    break;
                case $expire_time < $now + 864000:
                    $color = 'orange';
                    break;
                default:
                    $color = 'green';
                    break;
            }

            $readable_date = (empty($expire_time)) ? '' : strftime('%d/%m/%Y', $expire_time);

            $expire_date = sprintf('<label style="background-color:%s; color:white; border-radius:3px; padding:3px">%s</label>', $color, $readable_date);

            echo empty($expire_date) ? '--' : $expire_date;
        }
    }

    public function woodyExpiredMediaSortColumn($columns)
    {
        $columns['expire'] = 'expire';

        return $columns;
    }

    public function woodyExpiredMediaSortRule($query)
    {
        if (!is_admin()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby == 'expire') {
            $query->set('meta_key', 'attachment_expire');
            $query->set('orderby', 'meta_value');
        }
    }
}
