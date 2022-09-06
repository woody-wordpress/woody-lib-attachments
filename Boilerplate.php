<?php

/**
 * @author Léo POIROUX
 * @copyright Raccourci Agency 2022
 */

namespace Woody\Addon\Boilerplate;

use Woody\App\Container;
use Woody\Modules\Module;
use Woody\Services\ParameterManager;
use Symfony\Component\Finder\Finder;

final class Boilerplate extends Module
{
    protected $boilerplateManager;

    protected static $key = 'woody_addon_boilerplate';

    public function initialize(ParameterManager $parameterManager, Container $container)
    {
        define('WOODY_ADDON_BOILERPLATE_VERSION', '1.0.0');
        define('WOODY_ADDON_BOILERPLATE_ROOT', __FILE__);
        define('WOODY_ADDON_BOILERPLATE_DIR_ROOT', dirname(WOODY_ADDON_BOILERPLATE_ROOT));
        define('WOODY_ADDON_BOILERPLATE_DIR_RESOURCES', WOODY_ADDON_BOILERPLATE_DIR_ROOT . '/Resources');

        parent::initialize($parameterManager, $container);
        $this->boilerplateManager = $this->container->get('boilerplate.manager');
    }

    public static function dependencyServiceDefinitions()
    {
        return \Woody\Addon\Boilerplate\Configurations\Services::loadDefinitions();
    }

    public function subscribeHooks()
    {
        // Admin settings
        add_action('members_register_caps', [$this, 'membersRegisterCaps']);
        // add_action('admin_menu', [$this, 'generateMenu']);

        // Enqueue scripts
        // add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        // add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Register Views folder as Timber locations
        add_filter('timber_locations', [$this, 'injectTimberLocation']);

        // ACF filters
        add_filter('acf/settings/load_json', [$this, 'acfJsonLoad']);
        add_filter('woody_acf_save_paths', [$this, 'acfJsonSave']);

        // Register translations
        add_action('after_setup_theme', [$this, 'loadThemeTextdomain']);
    }

    public function membersRegisterCaps()
    {
        members_register_cap('woody_boilerplate', array(
            'label' => _x('Woody Boilerplate', '', 'woody'),
            'group' => 'woody',
        ));
    }

    // public function generateMenu()
    // {
    //     acf_add_options_page([
    //         'page_title'    => 'Boilerplate',
    //         'menu_title'    => 'Boilerplate',
    //         'menu_slug'     => 'boilerplate-settings',
    //         'capability'    => 'edit_pages',
    //         'icon_url'      => 'dashicons-bell',
    //         'position'      => 40,
    //     ]);
    // }

    // public function enqueueAssets()
    // {
    //     wp_enqueue_script('addon-boilerplate-javascripts', $this->addonAssetPath('woody-addon-boilerplate', 'js/woody-addon-boilerplate.js'), ['jquery'], WOODY_ADDON_BOILERPLATE_VERSION, true);
    // }

    // public function enqueueAdminAssets()
    // {
    //     $screen = get_current_screen();
    //     if (!empty($screen->id) && strpos($screen->id, 'boilerplate-settings') !== false) {
    //         wp_enqueue_script('addon-admin-boilerplate-javascripts', $this->addonAssetPath('woody-addon-boilerplate', 'js/woody-admin-addon-boilerplate.js'), ['jquery'], WOODY_ADDON_BOILERPLATE_VERSION, true);
    //         wp_enqueue_style('addon-admin-boilerplate-stylesheet', $this->addonAssetPath('woody-addon-boilerplate', 'scss/woody-admin-addon-boilerplate.css'), [], null);
    //     }
    // }

    public function injectTimberLocation($locations)
    {
        $locations[] = WOODY_ADDON_BOILERPLATE_DIR_RESOURCES . '/Views' ;

        return $locations;
    }

    /**
     * Register ACF Json load directory
     *
     * @since 1.0.0
     */
    public function acfJsonLoad($paths)
    {
        $paths[] = WOODY_ADDON_BOILERPLATE_DIR_RESOURCES . '/ACF';
        return $paths;
    }

    /**
     * Register ACF Json Save directory
     *
     * @since 1.0.0
     */
    public function acfJsonSave($groups)
    {
        $acf_json_path = WOODY_ADDON_BOILERPLATE_DIR_RESOURCES . '/ACF';

        $finder = new Finder();
        $finder->files()->in($acf_json_path)->name('*.json');
        foreach ($finder as $file) {
            $filename = str_replace('.json', '', $file->getRelativePathname());
            $groups[$filename] = $acf_json_path;
        }

        return $groups;
    }

    public function loadThemeTextdomain()
    {
        load_theme_textdomain('woody-addon-boilerplate', WOODY_ADDON_BOILERPLATE_DIR_ROOT . '/Languages');
    }

    /**
     * @noRector
     * Commande pour créer automatiquement woody-addon-boilerplate.pot
     * A ouvrir ensuite avec PoEdit.app sous Mac
     * cd ~/www/wordpress/current/vendor/woody-wordpress-pro/woody-addon-boilerplate/
     * wp i18n make-pot . Languages/woody-addon-boilerplate.pot
     */
    private function twigExtractPot()
    {
    }
}
