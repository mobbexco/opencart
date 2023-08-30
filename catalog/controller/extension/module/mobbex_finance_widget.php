<?php

require_once DIR_SYSTEM . 'library/mobbex/config.php';
require_once DIR_SYSTEM . 'library/mobbex/sdk.php';

class ControllerExtensionModuleMobbexFinanceWidget extends Controller
{
    /** @var MobbexHelper */
    public static $helper;

    public function index()
    {
        // load models
		$this->load->model('setting/setting');
		$this->mobbexConfig = new MobbexConfig($this->registry);

        if(
            (!$this->mobbexConfig->active_product && $this->request->get['route'] == 'product/product') 
            || (!$this->mobbexConfig->active_cart && $this->request->get['route'] == 'checkout/cart')
            || !$this->mobbexConfig->status_widget
        ) {
            return;
        }

        //Init sdk classes
        (new \MobbexSdk($this->registry))->init();

        $data = [
            'price'   => $this->getPrice($this->request->get['route']),
            'sources' => \Mobbex\Repository::getSources($this->getPrice($this->request->get['route'])),
			'theme'   => 'light',
            'button'  => [
                'custom_styles' => $this->mobbexConfig->button_styles,
                'text'          => $this->mobbexConfig->button_text,
                'logo'          => $this->mobbexConfig->button_logo,
            ]
        ];

        // Return view
        return $this->load->view('extension/mobbex/finance_widget', $data);
    }

    /**
     * Calculates the price to show in widget.
     * 
     * @param string $route
     * 
     * @return float
     */
    public function getPrice($route)
    {
        if($route == 'product/product'){

            $this->load->model('catalog/product');
            $product = $this->model_catalog_product->getProduct((int)$this->request->get['product_id']);
            $price   = $product['special'] ? (float)$product['special'] : (float)$product['price'];

            return (float)$this->tax->calculate($price, $product['tax_class_id'], $this->config->get('config_tax'));

        } else {
            return (float)$this->cart->getTotal();
        }
    }


}