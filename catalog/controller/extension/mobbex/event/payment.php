<?php

require_once DIR_SYSTEM . 'library/mobbex/sdk.php';
require_once DIR_SYSTEM . 'library/mobbex/config.php';
require_once DIR_SYSTEM . 'library/mobbex/logger.php';
require_once DIR_SYSTEM . 'library/mobbex/checkout.php';

class ControllerExtensionMobbexEventPayment extends controller
{
    /** @var MobbexConfig */
    public static $mobbexConfig;

    /** @var MobbexCheckout */
    public static $mobbexCheckout;

    /** @var MobbexLogger */
    public static $mobbexLogger;

    public function __construct()
    {
        parent::__construct(...func_get_args());

        // load lenguage
        $this->load->language('extension/mobbex/catalog_config');

        //Load mobbex models
        $this->mobbexConfig   = new MobbexConfig($this->registry);
        $this->logger         = new MobbexLogger($this->registry);
        $this->mobbexCheckout = new MobbexCheckout($this->registry);

        //Init sdk classes
        (new \MobbexSdk($this->registry))->init();
    }

    /**
     * Get Mobbex Payment methods
     */
    public function get_methods(&$route, &$data, &$output)
    {
        if (!$this->mobbexConfig->methods)
            return;

        //Get mobbex checkout
        $checkout = $this->mobbexCheckout->getCheckout();

        //Delete mobbex unified method
        unset($data['payment_methods']['mobbex']);

        //Subdivision of Mobbex payment methods
        foreach ($checkout->methods as $method) {
            //Generate method code
            $code = "mobbex_{$method['group']}:{$method['subgroup']}";

            // Add method
            $data['payment_methods'][$code] = [
                'code'       => $code,
                'title'      => $method['subgroup_title'],
                'terms'      => '',
                'sort_order' => '',
                'icon'       => $method['subgroup_logo'],
            ];
        }
    }

    public function validate_methods(&$route, &$data, &$output)
    {
        if(!$this->mobbexConfig->methods)
            return;
        
        //add mobbex data to methods
        $output = str_replace('name="payment_method" value="mobbex_', 'class="mobbex-method" name="payment_method" value="mobbex" data-mobbex="', $output);
        
        if($this->mobbexConfig->methods_icon){
            foreach ($data['payment_methods'] as $method) {
                if(!isset($method['icon']))
                    continue;
                    
                //Add icon
                $output = str_replace($method['title'], "<img src='{$method['icon']}' style='width:35px;border-radius:100%;margin-right:5px;background-color:#6f00ff;'>{$method['title']}", $output);
                //center icon
                $output = str_replace('<label>', '<label style="display:flex;align-items:center;">', $output);
            }
        }

        return $output;
    }
}
