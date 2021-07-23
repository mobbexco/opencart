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
}