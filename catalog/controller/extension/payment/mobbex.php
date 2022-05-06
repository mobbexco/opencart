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

    public function webhook()
    {
        // load models and instance helper
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mobbex');
        $this->helper = new MobbexHelper($this->config);

        // Get and validate received data
        $id    = $this->request->get['order_id'];
        $token = $this->request->get['mobbex_token'];
        $data  = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true)['data'] : $this->request->post['data'];

        if (empty($id) || empty($token) || empty($data))
            die("WebHook Error: Empty ID, token or post body. v{$this->helper::$version}");

        if ($token != $this->helper->generateToken())
            die("WebHook Error: Empty ID, token or post body. v{$this->helper::$version}");

        if ($this->request->post['type'] != 'checkout')
            die("WebHook Error: This endpoint can only receive checkout type calls. v{$this->helper::$version}");

        // Get new order status
        $status      = $data['payment']['status']['code'];
        $state       = $this->helper->getState($status);
        $orderStatus = 0;

        if ($state == 'onhold') {
            $orderStatus = 1;
        } else if ($state == 'approved') {
            $orderStatus = 2;
        } else if ($state == 'failed') {
            $orderStatus = 10;
        } else if ($state == 'rejected') {
            $orderStatus = 8;
        }

        // Update order status
        $this->model_checkout_order->addOrderHistory($id, $orderStatus, $this->createOrderComment($data), $state == 'approved');
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
                'total'       => $order['total'],
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
                    'domain'   => HTTPS_SERVER,
                    'redirect' => [
                        'success' => true,
                        'failure' => false,
                    ],
                    'platform' => [
                        'name'             => 'opencart',
                        'version'          => $this->helper::$version,
                        'platform_version' => VERSION,
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
                'image'       => HTTPS_SERVER . 'image/' . $product['image'],
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
            'name'  => $order['payment_firstname'] . ' ' . $order['payment_lastname'],
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

    /**
     * Create order comment from Mobbex webhook post data.
     * 
     * @param array $data
     * 
     * @return string
     */
    private function createOrderComment($data)
    {
        // Get data from post
        $date          = date('d/m/Y h:i');
        $paymentId     = $data['payment']['id'];
        $paymentTotal  = $data['payment']['total'];
        $paymentMethod = $data['payment']['source']['name'];
        $installments  = '';
        $riskAnalysis  = isset($data['payment']['riskAnalysis']['level']) ? $data['payment']['riskAnalysis']['level'] : '';
        $entityUid     = isset($data['entity']['uid']) ? $data['entity']['uid'] : '';

        // Get installments info
        if ($data['payment']['source']['type'] == 'card') {
            $installmentDesc  = $data['payment']['source']['installment']['description'];
            $installmentQty   = $data['payment']['source']['installment']['count'];
            $installmentTotal = $data['payment']['source']['installment']['amount'];

            $installments = "en $installmentDesc. $installmentQty Cuota/s de $installmentTotal";
        }

        // Merge and return
        return str_replace(
            ['{date}', '{paymentID}', '{paymentTotal}', '{paymentMethod}', '{installments}', '{riskAnalysis}', '{entityUID}'],
            [$date, $paymentId, $paymentTotal, $paymentMethod, $installments, $riskAnalysis, $entityUid], 
            $this->language->get('order_comment')
        );
    }
}