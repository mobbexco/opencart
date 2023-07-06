<?php

class MobbexConfig
{
    /** @var string */
    public static $version = '1.0.0';
    
    public $settings = array();

    public function __construct($config)
    {
        $mobbexOptions = [
            'payment_mobbex_'               => $config->getSetting('payment_mobbex'),
            'module_mobbex_finance_widget_' => $config->getSetting('module_mobbex_finance_widget')
        ];
        

        foreach ( $mobbexOptions as $replace => $settings)
            $this->getSettings($settings, $replace);
    }

    /**
     * Set the mobbex configs as properties.
     * 
     * @param array $replace
     * @param string string to replace in array key.
     * 
     */
    private function getSettings($settings, $replace)
    {
        //Set mobbex settings as properties
        foreach ($settings as $key => $value) {
            $configKey = str_replace($replace, '', $key);
            $this->settings[$configKey] = $this->$configKey = $value;
        }
    }

}