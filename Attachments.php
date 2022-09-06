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
    }

    public static function dependencyServiceDefinitions()
    {
        return \Woody\Lib\Attachments\Configurations\Services::loadDefinitions();
    }

    public function subscribeHooks()
    {
        add_action('add_attachment', [$this->attachmentsManager, 'addAttachment'], 50);
        add_action('save_attachment', [$this->attachmentsManager, 'saveAttachment'], 50);

        // Lors de la suppression d'une langue on doit supprimer tous ses attachments pour éviter qu'ils ne passent dans la langue par défaut
        // Pour cela on passe par une commande CLI et on ne veut surtout pas supprimer les traductions des médias supprimés
        if (!defined('WP_CLI')) {
            add_action('delete_attachment', [$this->attachmentsManager, 'deleteAttachment'], 1);
        }

        add_filter('attachment_fields_to_save', [$this, 'attachmentFieldsToSave'], 12, 2); // Priority 12 ater polylang
    }
}
