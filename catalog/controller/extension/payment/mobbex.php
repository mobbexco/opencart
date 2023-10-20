<?php

require_once DIR_SYSTEM . 'library/mobbex/config.php';
require_once DIR_SYSTEM . 'library/mobbex/sdk.php';
require_once DIR_SYSTEM . 'library/mobbex/checkout.php';
require_once DIR_SYSTEM . 'library/mobbex/logger.php';
require_once DIR_SYSTEM . 'library/mobbex/transaction.php';


class ControllerExtensionPaymentMobbex extends Controller
{
    /** @var MobbexConfig */
    public static $mobbexConfig;

    /** @var MobbexLogger */
    public static $mobbexLogger;

    /** @var MobbexCheckout */
    public static $mobbexCheckout;

    public function __construct()
    {
        parent::__construct(...func_get_args());
        // load models and instance helper
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/mobbex');
        $this->load->model('setting/setting');

        $this->mobbexConfig   = new MobbexConfig($this->registry);
        $this->logger         = new MobbexLogger($this->registry);
        $this->mobbexCheckout = new MobbexCheckout($this->registry);
        $this->transaction    = new MobbexTransaction($this->registry);

        //Init sdk classes
        (new \MobbexSdk($this->registry))->init();
    }

    public function index()
    {
        // Get current order data
        $orderId = $this->session->data['order_id'];
        $order   = $this->model_checkout_order->getOrder($orderId);
        
        // Sets dni field
        $order['dni'] = $this->mobbexCheckout->getDni($order['custom_field']); 

        // Checks if there´s a logged customer and if it has dni
        if ($this->customer->isLogged() && !$order['dni']){
            $link        = $this->url->link('account/edit');
            $dniRequired = $this->language->get('dni_required');
            $dniAlert    = $this->language->get('dni_alert');
            // Displays a button that redirects to customer account edit
            return $this->load->view('extension/mobbex/dni_required', compact('link', 'dniRequired', 'dniAlert'));
        }

        //Assign data to template
        $data = [
            'textTitle'  => $this->language->get('text_title'),
            'embed'      => (bool) $this->mobbexConfig->settings['embed'],
            'mobbexData' => json_encode([
                'checkoutUrl' => $this->url->link("extension/payment/mobbex/checkout", '', true) . '&' . http_build_query(['order_id' => $orderId]),
                'errorUrl'    => $this->url->link("extension/payment/mobbex/index", '', true),
                'returnUrl'   => $this->mobbexCheckout->getOrderEndpointUrl($order, 'callback'),
            ]),
        ];

        // Return view
        return $this->load->view('extension/payment/mobbex', $data);
    }

    /**
     * Endpoit to get Mobbex checkout.
     */
    public function checkout()
    {
        // Get order
        $orderId = $this->request->get['order_id'];
        $order   = $this->model_checkout_order->getOrder($orderId);

        //Get checkout
        $checkout = $this->mobbexCheckout->getCheckout($order);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($checkout->response));
    }

    /**
     * Endpoint to return payment.
     */
    public function callback()
    {
        // Get return data
        $id     = $this->request->get['order_id'];
        $status = $this->request->get['status'];
        $token  = $this->request->get['mobbex_token'];

        // If is empty, redirect to checkout with error
        if (empty($id) || empty($status) || empty($token)) {
            $this->session->data['error'] = $this->language->get('callback_error');
            $this->logger->log('error', "ControllerExtensionPaymentMobbex > callback | ". $this->language->get('callback_error'));
            $this->response->redirect($this->url->link('checkout/checkout'));
        }

        // If the token is invalid, redirect to checkout with error
        if (!\Mobbex\Repository::validateToken($token)) {
            // Redirect to checkout with error
            $this->session->data['error'] = $this->language->get('token_error');
            $this->logger->log('error', "ControllerExtensionPaymentMobbex > callback | " . $this->language->get('token_error'));
            $this->response->redirect($this->url->link('checkout/checkout'));
        }

        if ($status > 1 && $status < 400) {
            $this->response->redirect($this->url->link('checkout/success'));
        } else {
            $this->response->redirect($this->url->link('checkout/failure'));
        }
    }

    /**
     * Enpoint to process mobbex webhook.
     */
    public function webhook()
    {
        // Get and validate received data
        $id            = $this->request->get['order_id'];
        $token         = $this->request->get['mobbex_token'];
        $data          = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true)['data'] : $this->request->post['data'];
        $cartId        = $this->request->get['cart_id'];
        $mobbexVersion = MobbexConfig::$version;

        $this->logger->log('debug', "ControllerExtensionPaymentMobbex > webhook | Process Webhook", $data);

        if (!\Mobbex\Repository::validateToken($token) || empty($id) || empty($data) || !isset($data['payment']))
            $this->logger->log('critical', "ControllerExtensionPaymentMobbex > webhook | WebHook Error: Empty ID, token or post body. v{$this->helper::$version}");

        // Get new order status
        $status      = $data['payment']['status']['code'];
        $state       = $this->getState($status);
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

        // Format transaction data
        $trxData = $this->transaction->formatWebhookData($data, $cartId);

        // Save formated data in mobbex transaction table
        $this->transaction->saveTransaction($trxData);
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

    /**
     * Get payment state from Mobbex status code.
     * 
     * @param int|string $status
     * 
     * @return string "approved" | "onhold" | "rejected" | "failed"
     */
    public function getState($status)
    {
        if ($status == 2 || $status == 3 || $status == 100 || $status == 201) {
            return 'onhold';
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            return 'approved';
        } else if ($status == 604) {
            return 'rejected';
        } else {
            return 'failed';
        }
    }
}