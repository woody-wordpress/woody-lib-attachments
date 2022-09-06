<?php

/**
 * @author Léo POIROUX
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
    }
}
