<?php

require_once DIR_SYSTEM . 'library/mobbex/helper.php';

class ControllerExtensionPaymentMobbex extends Controller
{
    /** @var MobbexHelper */
    public static $helper;

    public function index()
    {
        // load models and instance helper
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mobbex');
        $this->helper = new MobbexHelper($this->config);

        // Get current order data
        $orderId = $this->session->data['order_id'];
        $order   = $this->model_checkout_order->getOrder($orderId);

        // Create Mobbex checkout
        $checkout = $this->getCheckout($order);
        $mbbxUrl  = isset($checkout['url']) ? $checkout['url'] : '';

        // Get page text translations
        $textTitle = $this->language->get('text_title');

        // Return view
        return $this->load->view('extension/payment/mobbex', compact('mbbxUrl', 'textTitle'));
    }

    public function callback()
    {
        // load models and instance helper
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mobbex');
        $this->helper = new MobbexHelper($this->config);

        // Get return data
        $id     = $this->request->get['order_id'];
        $status = $this->request->get['status'];
        $token  = $this->request->get['mobbex_token'];

        // If is empty, redirect to checkout with error
        if (empty($id) || empty($status) || empty($token)) {
            $this->session->data['error'] = $this->language->get('callback_error');
            $this->response->redirect($this->url->link('checkout/checkout'));
        }

        // If the token is invalid, redirect to checkout with error
        if ($token != $this->helper->generateToken()) {
            // Redirect to checkout with error
            $this->session->data['error'] = $this->language->get('token_error');
            $this->response->redirect($this->url->link('checkout/checkout'));
        }

        if ($status > 1 && $status < 400) {
            $this->response->redirect($this->url->link('checkout/success'));
        } else {
            $this->response->redirect($this->url->link('checkout/failure'));
        }
    }

    /**
     * Get Mobbex checkout.
     * 
     * @param mixed $order
     * 
     * @return array
     */
    private function getCheckout($order)
    {
        // Check currency support
        if (!in_array($order['currency_code'], ['ARS', 'ARG']))
            return;

        $data = [
            'uri'    => 'checkout',
            'method' => 'POST',
            'body'   => [
                'total'       => $order["total"],
                'currency'    => $order['currency_code'],
                'webhook'     => $this->getOrderEndpointUrl($order, 'webhook'),
                'return_url'  => $this->getOrderEndpointUrl($order, 'callback'),
                'reference'   => 'oc_order_' . $order['order_id'] . '_time_' . time(),
                'description' => 'Orden #' . $order['order_id'],
                'items'       => $this->getItems($order),
                'customer'    => $this->getCustomer($order),
                'test'        => $this->config->get('payment_mobbex_test_mode'),
                'timeout'     => 5,
                'options'     => [
                    'button'   => false,
                    'domain'   => HTTP_SERVER,
                    'redirect' => [
                        'success' => true,
                        'failure' => false,
                    ],
                    'platform' => [
                        'name'    => 'opencart',
                        'version' => $this->helper::$version,
                    ],
                ],
            ]
        ];

        return $this->helper->request($data);
    }

    /**
     * Get order product items.
     * 
     * @param mixed $order
     * 
     * @return array
     */
    private function getItems($order)
    {
        $items = [];

        foreach ($this->cart->getProducts() as $product) {
            $items[] = [
                'image'       => HTTP_SERVER . 'image/' . $product['image'],
                'quantity'    => $product['quantity'],
                'description' => $product['name'],
                'total'       => round($product['price'] * $order['currency_value'], 2),
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
    private function getCustomer($order)
    {
        return [
            'name'  => $order['payment_firstname'] . $order['payment_lastname'],
            'email' => $order['email'],
            'phone' => $order['telephone'],
            'uid'   => $this->customer->getId(),
        ];
    }

    /**
     * Get order endpoint URLs.
     * 
     * @param array $order
     * @param string $endpoint
     * 
     * @return string
     */
    private function getOrderEndpointUrl($order, $endpoint)
    {
        $args = [
            'mobbex_token' => $this->helper->generateToken(),
            'platform'     => 'opencart',
            'version'      => $this->helper::$version,
            'order_id'     => $order['order_id']
        ];

        return $this->url->link("extension/payment/mobbex/$endpoint", '', true) . '&' . http_build_query($args);
    }
}