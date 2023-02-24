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
    public $attachmentsApi;

    public $attachmentsWpSettings;

    public $imagesMetadata;

    public $attachmentsPagesList;

    public $attachmentsTableManager;

    public $attachmentsCommands;

    public $attachmentsUnused;

    public $attachmentsDataExport;

    protected $attachmentsManager;

    protected static $key = 'woody_lib_attachments';

    public function initialize(ParameterManager $parameterManager, Container $container)
    {
        define('WOODY_LIB_ATTACHMENTS_VERSION', '1.1.0');
        define('WOODY_LIB_ATTACHMENTS_ROOT', __FILE__);
        define('WOODY_LIB_ATTACHMENTS_DIR_ROOT', dirname(WOODY_LIB_ATTACHMENTS_ROOT));
        define('WOODY_LIB_ATTACHMENTS_DIR_RESOURCES', WOODY_LIB_ATTACHMENTS_DIR_ROOT . '/Resources');

        parent::initialize($parameterManager, $container);
        $this->attachmentsManager = $this->container->get('attachments.manager');
        $this->attachmentsApi = $this->container->get('attachments.api');
        $this->attachmentsWpSettings = $this->container->get('attachments.wp.settings');
        $this->imagesMetadata = $this->container->get('images.metadata');
        $this->attachmentsPagesList = $this->container->get('attachments.pageslist');
        $this->attachmentsTableManager = $this->container->get('attachments.table.manager');
        $this->attachmentsCommands = $this->container->get('attachments.commands');
        $this->attachmentsUnused = $this->container->get('attachments.unused');
        $this->attachmentsDataExport = $this->container->get('attachments.data.export');

        $this->addImageSizes();
    }

    public function registerCommands()
    {
        \WP_CLI::add_command('woody:attachments', $this->attachmentsCommands);
    }

    public static function dependencyServiceDefinitions()
    {
        return \Woody\Lib\Attachments\Configurations\Services::loadDefinitions();
    }

    public function subscribeHooks()
    {
        add_filter('timber_locations', [$this, 'injectTimberLocation']);

        // DB actions
        add_action('woody_theme_update', [$this->attachmentsTableManager, 'upgrade'], 10);

        // Scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        add_action('woody_theme_update', [$this, 'woodyInsertTerms']);
        add_action('add_attachment', [$this->attachmentsManager, 'addAttachment'], 50);
        add_action('save_attachment', [$this->attachmentsManager, 'saveAttachment'], 50);

        add_action('save_post', [$this->attachmentsManager, 'savePost'], 10, 3);

        // Lors de la suppression d'une langue on doit supprimer tous ses attachments pour éviter qu'ils ne passent dans la langue par défaut
        // Pour cela on passe par une commande CLI et on ne veut surtout pas supprimer les traductions des médias supprimés
        if (!defined('WP_CLI')) {
            add_action('delete_attachment', [$this->attachmentsManager, 'deleteAttachment'], 1);
        }

        // Woody filters
        add_filter('timber_render', [$this->attachmentsManager, 'timberRender'], 1);
        add_filter('attachment_fields_to_save', [$this->attachmentsManager, 'attachmentFieldsToSave'], 12, 2); // Priority 12 ater polylang

        // Images metadata reading/setting
        add_filter('wp_read_image_metadata', [$this->imagesMetadata, 'readImageMetadata'], 10, 4);
        add_filter('wp_generate_attachment_metadata', [$this->imagesMetadata, 'generateAttachmentMetadata'], 10, 2);

        // WP Native settings
        add_filter('wp_image_editors', [$this->attachmentsWpSettings, 'wpImageEditors']);
        add_filter('intermediate_image_sizes_advanced', [$this->attachmentsWpSettings, 'removeAutoThumbs'], 10, 2);
        add_filter('image_size_names_choose', [$this->attachmentsWpSettings, 'imageSizeNamesChoose'], 10, 1);
        add_filter('wp_handle_upload_prefilter', [$this->attachmentsWpSettings, 'maxUploadSize']);
        add_filter('upload_mimes', [$this->attachmentsWpSettings, 'uploadMimes'], 10, 1);
        add_filter('big_image_size_threshold', '__return_false'); // Désactive la duplication  de photo (filename-scaled.jpg) depuis WP 5.3
        add_filter('wp_handle_upload_overrides', [$this->attachmentsWpSettings, 'handleOverridesForGeoJSON'], 10, 2);
        add_filter('sanitize_file_name_chars', [$this->attachmentsWpSettings, 'restrictFilenameSpecialChars'], 10, 1);

        // API
        add_action('rest_api_init', function () {
            register_rest_route('woody', 'attachments/terms/get', array(
                'methods' => 'GET',
                'callback' => [$this->attachmentsApi, 'getAttachmentTerms'],
                'permission_callback' => fn () => current_user_can('edit_posts')
            ));
            register_rest_route('woody', 'attachments/terms/set', array(
                'methods' => 'GET',
                'callback' => [$this->attachmentsApi, 'setAttachmentsTerms'],
                'permission_callback' => fn () => current_user_can('edit_posts')
            ));
            register_rest_route('woody', 'attachments/replace', array(
                'methods' => 'GET',
                'callback' => [$this->attachmentsApi, 'replacePostsMeta'],
                'permission_callback' => fn () => current_user_can('edit_posts')
            ));
            register_rest_route('woody', 'attachments/delete', array(
                'methods' => 'POST',
                'callback' => [$this->attachmentsApi, 'deleteAttachments'],
                'permission_callback' => fn () => current_user_can('delete_posts')
            ));
        });

        //Woody Actions
        add_action('get_attachments_by_post', [$this->attachmentsTableManager, 'getAttachmentsByPost']);

        // List pages linked to an image
        add_action('admin_menu', [$this->attachmentsPagesList, 'generatePagesList']);
        add_filter('media_row_actions', [$this->attachmentsPagesList, 'addPageListLinks'], 10, 3);

        // List unused attachments
        add_action('admin_menu', [$this->attachmentsUnused, 'generateUnusedList']);

        add_action('admin_menu', [$this->attachmentsDataExport, 'generateDataExportPage']);
        add_action('woody_theme_update', [$this->attachmentsDataExport, 'scheduleDeleteExportFiles']);
        add_action('woody_delete_medias_export_files', [$this->attachmentsDataExport, 'deleteMediaExportFiles']);
        add_action('attachments_do_export', [$this->attachmentsDataExport, 'attachmentsDoExport']);
    }

    public function injectTimberLocation($locations)
    {
        $locations[] = WOODY_LIB_ATTACHMENTS_DIR_RESOURCES . '/Views';

        return $locations;
    }

    public function enqueueAdminAssets()
    {
        // Enqueue the main Scripts
        $current_screen = get_current_screen();
        if ($current_screen->id == 'upload' && $current_screen->post_type == 'attachment') {
            wp_enqueue_style('admin-attachments-stylesheet', $this->addonAssetPath('woody-lib-attachments', 'scss/attachments-admin.css'), '', WOODY_LIB_ATTACHMENTS_VERSION);
            wp_enqueue_script('admin-attachments-javascripts', $this->addonAssetPath('woody-lib-attachments', 'js/attachments-admin.js'), ['admin-javascripts'], WOODY_LIB_ATTACHMENTS_VERSION, true);
        }

        if ($current_screen->id == 'admin_page_woody-pages-using-media') {
            wp_enqueue_media();
            wp_enqueue_style('replace-attachments-stylesheet', $this->addonAssetPath('woody-lib-attachments', 'scss/replace-attachment.css'), '', WOODY_LIB_ATTACHMENTS_VERSION);
            wp_enqueue_script('replace-attachment-javascripts', $this->addonAssetPath('woody-lib-attachments', 'js/replace-attachment.js'), ['admin-javascripts'], WOODY_LIB_ATTACHMENTS_VERSION, true);
        }

        if ($current_screen->id == 'media_page_woody-unused-attachments') {
            wp_enqueue_media();
            wp_enqueue_style('unused-attachments-stylesheet', $this->addonAssetPath('woody-lib-attachments', 'scss/unused-attachments.css'), '', WOODY_LIB_ATTACHMENTS_VERSION);
            wp_enqueue_script('unused-attachments-javascripts', $this->addonAssetPath('woody-lib-attachments', 'js/unused-attachments.js'), ['admin-javascripts'], WOODY_LIB_ATTACHMENTS_VERSION, true);
        }

        if ($current_screen->id == 'media_page_woody-export-attachments-data') {
            wp_enqueue_media();
            wp_enqueue_style('export-attachments-data-stylesheet', $this->addonAssetPath('woody-lib-attachments', 'scss/export-attachments-data.css'), '', WOODY_LIB_ATTACHMENTS_VERSION);
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
