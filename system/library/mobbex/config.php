<?php

/**
 * MobbexConfig
 * 
 * A model to manage the options, configurations & info of the plugin.
 * 
 */
class MobbexConfig extends Model
{
    /** @var string */
    public static $version = '1.0.0';
    
    public $settings = array();

    public function __construct($registry)
    {

        parent::__construct($registry);
        $this->load->model('setting/setting');

        //Set mobbex settings as properties
        $this->formatSettings('payment_mobbex');
        $this->formatSettings('module_mobbex_finance_widget');
    }

    /**
     * Format the Mobbex configs array for compatibility with php plugins sdk & set configs has properties.
     * 
     * @param string $replace Config key to replace.
     */
    private function formatSettings($replace)
    {
        foreach ($this->model_setting_setting->getSetting($replace) as $key => $value) {
            $configKey = str_replace($replace . '_', '', $key);
            $this->settings[$configKey] = $this->$configKey = $value;
        }
    }
}
