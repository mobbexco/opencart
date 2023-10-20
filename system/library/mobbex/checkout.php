<?php

/**
 * MobbexConfig
 * 
 * A model to manage the options, configurations & info of the plugin.
 * 
 */
class MobbexCheckout extends Model
{
    /** @var MobbexConfig */
    public static $mobbexConfig;

    /** @var MobbexLogger */
    public static $mobbexLogger;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('setting/setting');
        $this->mobbexConfig = new MobbexConfig($this->registry);
        $this->logger       = new MobbexLogger($this->registry);

        //Init sdk classes
        (new \MobbexSdk($this->registry))->init();
    }

    public function getCheckout($order = null)
    {
        $currency = $order ? $order['currency_code'] : $this->session->data['currency'];
        
        $this->load->model('localisation/currency');
        $this->currency_info = $this->model_localisation_currency->getCurrencyByCode($currency);

        // Check currency support
        if (!in_array($currency, ['ARS', 'ARG']))
            $this->log->write($this->language->get('currency_error'));

        //Get products ids
        $products_ids = array_column($this->cart->getProducts(), 'product_id');

        
        try {
            //Get products plans
            extract($this->mobbexConfig->getProductsPlans($products_ids));

            //Create Mobbex Checkout
            $mobbexCheckout = new \Mobbex\Modules\Checkout(
                $order ? $order['order_id'] : $this->getCartId(),
                $this->currency->format($this->cart->getTotal(), $this->session->data['currency'], $this->currency_info['value'], false),
                $this->getOrderEndpointUrl($order, 'callback'),
                $this->getOrderEndpointUrl($order, 'webhook'),
                $this->getItems($currency),
                \Mobbex\Repository::getInstallments($this->cart->getProducts(), $common_plans, $advanced_plans),
                \Mobbex\Repository::getInstallments($this->cart->getProducts(), $common_plans, $advanced_plans),
                $order ? $this->getCustomerFromOrder($order) : [],
                $order ? $this->getAddressesFromOrder($order) : [],
                $order ? 'all' : 'none',
                'mobbexCheckoutRequest'
            );
        } catch (\Mobbex\Exception $e) {
            $this->logger->log('debug', "Helper Mobbex > getCheckoutFromQuote | Checkout Response: ", $mobbexCheckout->response);
            return false;
        }

        return $mobbexCheckout;
    }

    /**
     * Get cart_id from product
     * 
     * @return string $cartId
     */
    private function getCartId()
    {
        // Search cart_id in products
        foreach ($this->cart->getProducts() as $product)
            $cartId = $product['cart_id'];

        return $cartId;
    }

    /**
     * Get order endpoint URLs.
     * 
     * @param array $order
     * @param string $endpoint
     * 
     * @return string
     */
    public function getOrderEndpointUrl($order, $endpoint)
    {
        // Crates an array with necesary query params
        $args = [
            'mobbex_token' => \Mobbex\Repository::generateToken(),
            'platform'     => 'opencart',
            'version'      => MobbexConfig::$version,
            'order_id'     => $order ? $order['order_id'] : $this->getCartId(),
            'cart_id'      => $this->getCartId()
        ];

        //Add Xdebug as query if debug mode is active
        if ($this->mobbexConfig->debug_mode)
            $args['XDEBUG_SESSION_START'] = 'PHPSTORM';

        return $this->url->link("extension/payment/mobbex/$endpoint", '', true) . '&' . http_build_query($args);
    }

    /**
     * Get order product items.
     * 
     * @return array
     */
    private function getItems()
    {
        $items = [];

        foreach ($this->cart->getProducts() as $product) {
            $items[] = [
                'image'       => HTTPS_SERVER . 'image/' . $product['image'],
                'quantity'    => $product['quantity'],
                'description' => $product['name'],
                'total'       => $this->currency->format($product['price'], $this->session->data['currency'], $this->currency_info['value'], false),
            ];
        }

        return $items;
    }

    /**
     * Get order customer data.
     * 
     * @param array $order
     * 
     * @return array
     */
    private function getCustomerFromOrder($order)
    {
        return [
            'identification' => isset($order['dni']) ? $order['dni'] : '',
            'email'          => isset($order['email']) ? $order['email'] : '',
            'phone'          => isset($order['telephone']) ? $order['telephone'] : '',
            'uid'            => $this->customer->getId(),
            'name'           => $order['payment_firstname'] . ' ' . $order['payment_lastname'],
        ];
    }

    /**
     * Get customer DNI.
     * 
     * @param array  $customFields
     * 
     * @return string $value DNI value
     * 
     */
    public function getDni($customFields)
    {
        if (!$customFields)
            return '';

        $dniField = $this->getDniValue($customFields);

        return $dniField;
    }

    /**
     *  Find DNI custom field and get its value
     * 
     * @param array  $customFields
     * 
     * @return string $value DNI value
     * 
     */
    public function getDniValue($customFields)
    {
        foreach ($customFields as $key => $value)
            // Find the custom field with DNI name
            $name = $this->db->query("SELECT name FROM `" . DB_PREFIX . "custom_field_description` WHERE custom_field_id = " . $key . ";")->row['name'];
        if ($name == 'DNI')
            // Gets DNI value from DNI custom field
            return $value;
    }

    /**
     * Get Addresses data for Mobbex Checkout from order.
     * 
     * @param mixed $order
     * 
     * @return array $addresses
     */
    private function getAddressesFromOrder($order)
    {
        $addresses = [];

        foreach (['payment' => 'billing', 'shipping' => 'shipping'] as $key => $type) {
            $street = isset($order[$key . '_address_1']) ? $order[$key . '_address_1'] : '';
            $addresses[] = [
                'type'         => $type,
                'country'      => isset($order[$key . '_iso_code_3']) ? $order[$key . '_iso_code_3'] : '',
                'street'       => trim(preg_replace('/(\D{0})+(\d*)+$/', '', trim($street))),
                'streetNumber' => str_replace(preg_replace('/(\D{0})+(\d*)+$/', '', trim($street)), '', trim($street)),
                'streetNotes'  => '',
                'zipCode'      => isset($order[$key . '_postcode']) ? $order[$key . '_postcode'] : '',
                'city'         => isset($order[$key . '_city']) ? $order[$key . '_city'] : '',
                'state'        => isset($order[$key . '_zone_code']) ? $this->getStateCode($order[$key . '_zone']) : '',
            ];
        }
        return $addresses;
    }

    /**
     * Return the code for a given state.
     * 
     * @param string $state State name.
     * 
     * @return string
     */
    private function getStateCode($state)
    {
        $states = [
            "Distrito Federal" => 'C',
            "Bueno Aires"      => 'B',
            "Catamarca"        => 'K',
            "Chaco"            => 'H',
            "Chubut"           => 'U',
            "Córdoba"          => 'X',
            "Entre Rios"       => 'E',
            "Formosa"          => 'P',
            "Jujuy"            => 'Y',
            "La Pampa"         => 'L',
            "La Rioja"         => 'F',
            "Mendoza"          => 'M',
            "Misiones"         => 'N',
            "Neuquén"          => 'Q',
            "Río Negro"        => 'R',
            "Salta"            => 'A',
            "San Juan"         => 'J',
            "San Luis"         => 'D',
            "Santa Cruz"       => 'Z',
            "Sante Fe"         => 'S',
            "Sante del Estero" => 'G',
            "Tierra del fuego" => 'V',
            "Tucumán"          => 'T',
        ];

        return isset($states[$state]) ? $states[$state] : '';
    }
}