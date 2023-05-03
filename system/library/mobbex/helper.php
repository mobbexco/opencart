<?php

class MobbexHelper
{
    /** @var string */
    public static $version = '1.0.0';

    /** Mobbex API base URL */
    public static $apiUrl = 'https://api.mobbex.com/p/';

    /** @var Config */
    public $config;

    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Make a request to Mobbex API.
     * 
     * @param array $data 
     * 
     * @return mixed
     */
    public function request($data)
    {
        if (!$this->isReady())
            return false;

        $curl = curl_init();

        // Set query params if needed
        if (!empty($data['params']))
            $data['uri'] = $data['uri'] . '?' . http_build_query($data['params']);

        curl_setopt_array($curl, [
            CURLOPT_URL            => self::$apiUrl . $data['uri'],
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $data['method'],
            CURLOPT_POSTFIELDS     => !empty($data['body']) ? json_encode($data['body']) : null,
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error) {
            error_log('Curl error in Mobbex request #:' . $error);
        } else {
            $result = json_decode($response, true);

            if (isset($result['data']))
                return $result['data'];
        }

        return false;
    }

    /**
     * Get headers to connect with Mobbex API.
     * 
     * @return string[] 
     */
    private function getHeaders()
    {
        return [
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . $this->config->get('payment_mobbex_api_key'),
            'x-access-token: ' . $this->config->get('payment_mobbex_access_token'),
            'x-ecommerce-agent: OpenCart/' . VERSION . ' Plugin/' . $this::$version,
        ];
    }

    /**
     * Check if plugin is ready to make requests.
     * 
     * @return bool 
     */
    public function isReady()
    {
        $enabled     = $this->config->get('payment_mobbex_status');
        $apiKey      = $this->config->get('payment_mobbex_api_key');
        $accessToken = $this->config->get('payment_mobbex_access_token');

        return ($enabled && !empty($apiKey) && !empty($accessToken));
    }

    /**
     * Create token to protect API endpoints.
     * 
     * @return string 
     */
    public function generateToken()
    {
        $apiKey      = $this->config->get('payment_mobbex_api_key');
        $accessToken = $this->config->get('payment_mobbex_access_token');

        return md5($apiKey . '|' . $accessToken);
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

    /**
     * Saves the transaction with order id and data
     * 
     * @param int   $order_id
     * @param array $data
     * 
     */
    public function saveTransaction($db, $cart_id, $data)
    {
        $db->query(
            "INSERT INTO " . DB_PREFIX . "mobbex_transaction (cart_id, data) VALUES (" . (int) $cart_id . ", '" . json_encode($data) . "');"
            );
    }

    /**
     * Format the webhook data to save in db.
     * 
     * @param array $webhookData
     * @param int $orderId
     * 
     * @return array
     */
    public function formatWebhookData($webhookData, $orderId)
    {
        $data = [
            'order_id'           => $orderId,
            'parent'             => isset($webhookData['payment']['id']),
            'childs'             => isset($webhookData['childs']) ? json_encode($webhookData['childs']) : '',
            'operation_type'     => isset($webhookData['payment']['operation']['type']) ? $webhookData['payment']['operation']['type'] : '',
            'payment_id'         => isset($webhookData['payment']['id']) ? $webhookData['payment']['id'] : '',
            'description'        => isset($webhookData['payment']['description']) ? $webhookData['payment']['description'] : '',
            'status_code'        => isset($webhookData['payment']['status']['code']) ? $webhookData['payment']['status']['code'] : '',
            'status_message'     => isset($webhookData['payment']['status']['message']) ? $webhookData['payment']['status']['message'] : '',
            'source_name'        => isset($webhookData['payment']['source']['name']) ? $webhookData['payment']['source']['name'] : 'Mobbex',
            'source_type'        => isset($webhookData['payment']['source']['type']) ? $webhookData['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($webhookData['payment']['source']['reference']) ? $webhookData['payment']['source']['reference'] : '',
            'source_number'      => isset($webhookData['payment']['source']['number']) ? $webhookData['payment']['source']['number'] : '',
            'source_expiration'  => isset($webhookData['payment']['source']['expiration']) ? json_encode($webhookData['payment']['source']['expiration']) : '',
            'source_installment' => isset($webhookData['payment']['source']['installment']) ? json_encode($webhookData['payment']['source']['installment']) : '',
            'installment_name'   => isset($webhookData['payment']['source']['installment']['description']) ? json_encode($webhookData['payment']['source']['installment']['description']) : '',
            'installment_amount' => isset($webhookData['payment']['source']['installment']['amount']) ? $webhookData['payment']['source']['installment']['amount'] : '',
            'installment_count'  => isset($webhookData['payment']['source']['installment']['count']) ? $webhookData['payment']['source']['installment']['count'] : '',
            'source_url'         => isset($webhookData['payment']['source']['url']) ? json_encode($webhookData['payment']['source']['url']) : '',
            'cardholder'         => isset($webhookData['payment']['source']['cardholder']) ? json_encode(($webhookData['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($webhookData['entity']['name']) ? $webhookData['entity']['name'] : '',
            'entity_uid'         => isset($webhookData['entity']['uid']) ? $webhookData['entity']['uid'] : '',
            'customer'           => isset($webhookData['customer']) ? json_encode($webhookData['customer']) : '',
            'checkout_uid'       => isset($webhookData['checkout']['uid']) ? $webhookData['checkout']['uid'] : '',
            'total'              => isset($webhookData['payment']['total']) ? $webhookData['payment']['total'] : '',
            'currency'           => isset($webhookData['checkout']['currency']) ? $webhookData['checkout']['currency'] : '',
            'risk_analysis'      => isset($webhookData['payment']['riskAnalysis']['level']) ? $webhookData['payment']['riskAnalysis']['level'] : '',
            'data'               => isset($webhookData) ? json_encode($webhookData) : '',
            'created'            => isset($webhookData['payment']['created']) ? $webhookData['payment']['created'] : '',
            'updated'            => isset($webhookData['payment']['updated']) ? $webhookData['payment']['created'] : '',
        ];

        return $data;
    }
}