<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/logger.php';

class MobbexSdk
{
    /**
     * Allow to use Mobbex php plugins sdk classes.
     * 
     * @param MobbexConfig $config Mobbex Config Class
     */
    public static function init($config)
    {
        // Set platform information
        \Mobbex\Platform::init(
            'opencart',
            MobbexConfig::$version,
            HTTPS_SERVER,
            [
                'platform' => VERSION,
                'sdk'      => class_exists('\Composer\InstalledVersions') && \Composer\InstalledVersions::isInstalled('mobbexco/php-plugins-sdk') ? \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk') : '',
            ], 
            $config->settings, 
            null,
            [new MobbexLogger($config), 'log']
        );

        // Init api conector
        \Mobbex\Api::init();
    }
} 