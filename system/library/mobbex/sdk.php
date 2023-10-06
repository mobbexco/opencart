<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

class MobbexSdk extends Model
{
    public function __construct($registry)
    {
        parent::__construct($registry);

        //Load models
        $this->mobbexConfig = new MobbexConfig($registry);
        $this->mobbexLogger = new MobbexLogger($registry);
        $this->mobbexDb     = new MobbexDb($registry);
    }
    /**
     * Allow to use Mobbex php plugins sdk classes.
     * 
     */
    public function init()
    {
        // Set platform information
        \Mobbex\Platform::init(
            'opencart',
            MobbexConfig::$version,
            MobbexConfig::EMBED_VERSION,
            HTTPS_SERVER,
            [
                'platform' => VERSION,
                'sdk'      => class_exists('\Composer\InstalledVersions') && \Composer\InstalledVersions::isInstalled('mobbexco/php-plugins-sdk') ? \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk') : '',
            ], 
            $this->mobbexConfig->settings, 
            null,
            [$this->mobbexLogger, 'log']
        );

        // Init api conector
        \Mobbex\Api::init();

        //Load models in sdk
        \Mobbex\Platform::loadModels(null, $this->mobbexDb);
    }
} 