<?php

class MobbexConfig
{
    /** @var string */
    public static $version = '1.0.0';
    
    public $settings = array();

    public function __construct($settings)
    {
        //Set mobbex settings as properties
        foreach ($settings as $key => $value){
            $configKey = str_replace('payment_mobbex_', '', $key);
            $this->settings[$configKey] = $this->$configKey = $value;
        }
    }
}