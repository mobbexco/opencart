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
     * 
     * @param array $data
     * @param string $output
     * 
     * @return string
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
                'class'      => 'mobbex-method',
            ];
        }

        if ($this->mobbexConfig->wallet){
            foreach ($checkout->cards as $key => $card) {
                //Dont add card if doest have installments
                if(empty($card['installments']))
                    continue;

                // Add card
                $data['payment_methods']["mobbex_wallet_card_$key"] = [
                    'code'         => "mobbex_wallet_card_$key",
                    'title'        => $card['name'],
                    'terms'        => '',
                    'sort_order'   => '',
                    'icon'         => $card['source']['card']['product']['logo'],
                    'class'        => 'mobbex-card',
                    'installments' => $card['installments'],
                    'cardNumber'   => $card['card']['card_number'],
                    'maxLenght'    => $card['source']['card']['product']['code']['length'],
                    'placeholder'  => $card['source']['card']['product']['code']['name'],
                ];
            }
        }

        //Store mobbex methods in session
        $this->session->data['payment_methods'] = array_merge($this->session->data['payment_methods'], $data['payment_methods']);
    }

    /**
     * Modify Mobbex payment methods insputs to add extra data
     * 
     * @param array $data
     * @param string $output
     * 
     * @return string
     */
    public function validate_methods(&$route, &$data, &$output)
    {
        if(!$this->mobbexConfig->methods)
            return;
        
        //add mobbex data to methods
        $output = str_replace('name="payment_method" value="mobbex_', 'name="payment_method" value="mobbex" data-mobbex="', $output);

        //Add classes
        foreach ($data['payment_methods'] as $key => $method) {
            if (strpos($key, 'mobbex_') !== false) {
                $id = str_replace('mobbex_', '', $key);
                $output = str_replace('data-mobbex="' . $id . '"', 'data-mobbex="' . $id . '" class="' . $method['class'] . '"', $output);
            }
        }

        //Add methods icons
        if($this->mobbexConfig->methods_icon)
            $output = $this->add_icons($data, $output);

        return $output;
    }

    /**
     * Add payment method images
     * 
     * @param array $data
     * @param string $output
     * 
     * @return string
     */
    private function add_icons($data, &$output) {

        foreach ($data['payment_methods'] as $method) {
            if (!isset($method['icon']))
                continue;

            //Add icon
            $output = str_replace($method['title'], "<img src='{$method['icon']}' style='width:35px;border-radius:100%;margin-right:5px;background-color:#6f00ff;'>{$method['title']}", $output);
            //center icon
            $output = str_replace('<label>', '<label style="display:flex;align-items:center;">', $output);
        }

        return $output;
    }
}
