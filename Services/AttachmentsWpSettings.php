<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments\Services;

class AttachmentsWpSettings
{
    public function wpImageEditors()
    {
        return ['WP_Image_Editor_GD'];
    }

    public function removeAutoThumbs($sizes, $metadata)
    {
        return [];
    }

    // Register the new image sizes for use in the add media modal in wp-admin
    // This is the place where you can set readable names for images size
    public function imageSizeNamesChoose($sizes)
    {
        return array(
            'ratio_8_1' => __('Pano A (1920x240)'),
            'ratio_4_1' => __('Pano B (1920x480)'),
            'ratio_3_1' => __('Pano C (1920x640)'),
            'ratio_2_1' => __('Paysage A (1920x960)'),
            'ratio_16_9' => __('Paysage B (1920x1080)'),
            'ratio_4_3' => __('Paysage C (1920x1440)'),
            'ratio_3_4_medium' => __('Portrait A (1200x1600)'),
            'ratio_10_16_medium' => __('Portrait B (1200x1920)'),
            'ratio_a4_medium' => __('Format A4'),
            'ratio_square' => __('Carré'),
            'ratio_free' => __('Proportions libres'),
            'medium' => __('Moyenne')
        );
    }

    public function maxUploadSize($file)
    {
        if (WP_SITE_KEY == 'crt-bretagne') {
            $limit = 20000;
            $limit_output = '20Mo';
        } else {
            $limit = 10000;
            $limit_output = '10Mo';
        }

        $size = $file['size'];
        $size /= 1024;

        $type = $file['type'];
        $is_image = strpos($type, 'image') !== false;
        if ($is_image && $size > $limit) {
            $file['error'] = 'Une image doit faire moins de ' . $limit_output;
        }

        return $file;
    }

    public function uploadMimes($mime_types)
    {
        $mime_types['eml'] = 'message/rfc822';
        $mime_types['gpx'] = 'text/xml';
        $mime_types['kml'] = 'text/xml';
        $mime_types['kmz'] = 'text/xml';
        $mime_types['xliff'] = 'text/xml';
        $mime_types['json'] = 'text/plain';
        $mime_types['geojson'] = 'text/plain';
        $mime_types['webp'] = 'image/webp';

        return $mime_types;
    }

    public function handleOverridesForGeoJSON($overrides, $file)
    {
        if ($file['type'] == "application/geo+json") {
            $overrides['test_type'] = false;
        }

        return $overrides;
    }

    public function restrictFilenameSpecialChars($special_chars)
    {
        $special_chars[] = '©';

        return $special_chars;
    }
}
