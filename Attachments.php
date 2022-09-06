<?php

/**
 * @author Benoit BOUCHAUD
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Lib\Attachments;

use Woody\App\Container;
use Woody\Modules\Module;
use Woody\Services\ParameterManager;
use Symfony\Component\Finder\Finder;

final class Attachments extends Module
{
    protected $attachmentsManager;

    protected static $key = 'woody_lib_attachments';

    public function initialize(ParameterManager $parameterManager, Container $container)
    {
        define('WOODY_LIB_ATTACHMENTS_VERSION', '1.0.0');
        define('WOODY_LIB_ATTACHMENTS_ROOT', __FILE__);
        define('WOODY_LIB_ATTACHMENTS_DIR_ROOT', dirname(WOODY_LIB_ATTACHMENTS_ROOT));
        define('WOODY_LIB_ATTACHMENTS_DIR_RESOURCES', WOODY_LIB_ATTACHMENTS_DIR_ROOT . '/Resources');

        parent::initialize($parameterManager, $container);
        $this->attachmentsManager = $this->container->get('attachments.manager');
        $this->attachmentsApi = $this->container->get('attachments.api');

        $this->addImageSizes();
    }

    public static function dependencyServiceDefinitions()
    {
        return \Woody\Lib\Attachments\Configurations\Services::loadDefinitions();
    }

    public function subscribeHooks()
    {
        // Scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        add_action('woody_theme_update', [$this, 'woodyInsertTerms']);
        add_action('add_attachment', [$this->attachmentsManager, 'addAttachment'], 50);
        add_action('save_attachment', [$this->attachmentsManager, 'saveAttachment'], 50);

        // Lors de la suppression d'une langue on doit supprimer tous ses attachments pour éviter qu'ils ne passent dans la langue par défaut
        // Pour cela on passe par une commande CLI et on ne veut surtout pas supprimer les traductions des médias supprimés
        if (!defined('WP_CLI')) {
            add_action('delete_attachment', [$this->attachmentsManager, 'deleteAttachment'], 1);
        }

        add_filter('attachment_fields_to_save', [$this->attachmentsManager, 'attachmentFieldsToSave'], 12, 2); // Priority 12 ater polylang

        // Ajax actions
        add_action('rest_api_init', function () {
            register_rest_route('woody', 'attachments/terms', array(
                'methods' => 'GET',
                'callback' => [$this->attachmentsApi, 'getAttachmentTerms'],
            ));
        });
        add_action('wp_ajax_set_attachments_terms', [$this->attachmentsApi, 'setAttachmentsTerms']);
    }

    public function enqueueAdminAssets()
    {
        // Enqueue the main Scripts
        $current_screen = get_current_screen();
        if ($current_screen->id == 'upload' and $current_screen->post_type == 'attachment') {
            wp_enqueue_style('admin-attachments-stylesheet', $this->addonAssetPath('woody-lib-attachments', 'scss/attachments-admin.css'), '', WOODY_LIB_ATTACHMENTS_VERSION);
            wp_enqueue_script('admin-attachments-javascripts', $this->addonAssetPath('woody-lib-attachments', 'js/attachments-admin.js'), ['admin-javascripts'], WOODY_LIB_ATTACHMENTS_VERSION, true);
        }
    }

    public function addImageSizes()
    {
        // Ratio 8:1 => Pano A
        add_image_size('ratio_8_1_small', 360, 45, true);
        add_image_size('ratio_8_1_medium', 640, 80, true);
        add_image_size('ratio_8_1_large', 1200, 150, true);
        add_image_size('ratio_8_1', 1920, 240, true);

        // Ratio 4:1 => Pano B
        add_image_size('ratio_4_1_small', 360, 90, true);
        add_image_size('ratio_4_1_medium', 640, 160, true);
        add_image_size('ratio_4_1_large', 1200, 300, true);
        add_image_size('ratio_4_1', 1920, 480, true);

        // Ratio 3:1 => Pano C
        add_image_size('ratio_3_1_small', 360, 120, true);
        add_image_size('ratio_3_1_medium', 640, 214, true);
        add_image_size('ratio_3_1_large', 1200, 400, true);
        add_image_size('ratio_3_1', 1920, 640, true);

        // Ratio 2:1 => Paysage A
        add_image_size('ratio_2_1_small', 360, 180, true);
        add_image_size('ratio_2_1_medium', 640, 320, true);
        add_image_size('ratio_2_1_large', 1200, 600, true);
        add_image_size('ratio_2_1', 1920, 960, true);

        // Ratio 16:9 => Paysage B
        add_image_size('ratio_16_9_small', 360, 203, true);
        add_image_size('ratio_16_9_medium', 640, 360, true);
        add_image_size('ratio_16_9_large', 1200, 675, true);
        add_image_size('ratio_16_9', 1920, 1080, true);

        // Ratio 4:3 => Paysage C
        add_image_size('ratio_4_3_small', 360, 270, true);
        add_image_size('ratio_4_3_medium', 640, 480, true);
        add_image_size('ratio_4_3_large', 1200, 900, true);
        add_image_size('ratio_4_3', 1920, 1440, true);

        // Ratio 3:4 => Portrait A
        add_image_size('ratio_3_4_small', 360, 480, true);
        add_image_size('ratio_3_4_medium', 640, 854, true);
        add_image_size('ratio_3_4', 1200, 1600, true);

        // Ratio 10:16 => Portrait B
        add_image_size('ratio_10_16_small', 360, 576, true);
        add_image_size('ratio_10_16_medium', 640, 1024, true);
        add_image_size('ratio_10_16', 1200, 1920, true);

        // Ratio A4 => Brochure papier
        add_image_size('ratio_a4_small', 360, 509, true);
        add_image_size('ratio_a4_medium', 640, 905, true);
        add_image_size('ratio_a4', 1200, 1697, true);

        // Carré
        add_image_size('ratio_square_small', 360, 360, true);
        add_image_size('ratio_square_medium', 640, 640, true);
        add_image_size('ratio_square', 1200, 1200, true);

        // Free => Proportions libre
        add_image_size('ratio_free_small', 360);
        add_image_size('ratio_free_medium', 640);
        add_image_size('ratio_free_large', 1200);
        add_image_size('ratio_free', 1920);
    }

    public function woodyInsertTerms()
    {
        wp_insert_term('Vidéo externe', 'attachment_types', array('slug' => 'media_linked_video'));
    }
}
