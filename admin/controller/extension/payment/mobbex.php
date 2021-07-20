<?php

require_once '../system/library/mobbex/helper.php';

class ControllerExtensionPaymentMobbex extends Controller
{
    /** @var MobbexHelper */
    public static $helper;

    public function index()
    {
        // First check that it's installed correctly
        $this->install();

        // load models and instance helper
        $this->load->model('setting/setting');
        $this->load->language('extension/payment/mobbex');
        $this->helper = new MobbexHelper($this->config);

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // Save configuration on post
            $this->saveConfig();
        } else {
            // Show configuration
            $this->showConfig();
        }
    }

    /**
     * Create Mobbex tables in database.
     * 
     * @return void 
     */
    private function install()
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX  . "mobbex_transaction` (
                `cart_id` INT(11) NOT NULL,
				`data` TEXT NOT NULL,
				PRIMARY KEY (`cart_id`));"
        );
  
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX  . "mobbex_custom_fields` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `row_id` INT(11) NOT NULL,
                `object` TEXT NOT NULL,
                `field_name` TEXT NOT NULL,
                `data` TEXT NOT NULL,
                PRIMARY KEY (`id`));"
        );
    }

    /**
     * Show plugin configuration form.
     * 
     * @return void 
     */
    private function showConfig()
    {
        // Set document head elements
        $this->document->setTitle($this->language->get('config_title'));

        $data = [
            // Get global text translations
            'heading_title' => $this->language->get('heading_title'),

            // Get template sections
            'header' 	    => $this->load->controller('common/header'),
            'footer' 	    => $this->load->controller('common/footer'),
            'column_left'   => $this->load->controller('common/column_left'),

            // Get button and action links
            'action'        => $this->url->link('extension/payment/mobbex', 'user_token=' . $this->session->data['user_token'], true),
            'cancel'        => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
            'breadcrumbs'   => [
                [
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
                ],
                [
                    'text' => $this->language->get('text_extension'),
                    'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
                ],
                [
                    'text' => $this->language->get('heading_title'),
                    'href' => $this->url->link('extension/payment/mobbex', 'user_token=' . $this->session->data['user_token'], true),
                ],
            ],

            // Mobbex config fields
            'payment_mobbex_status'       => $this->getFormConfig('status'),
            'payment_mobbex_test_mode'    => $this->getFormConfig('test_mode'),
            'payment_mobbex_api_key'      => $this->getFormConfig('api_key'),
            'payment_mobbex_access_token' => $this->getFormConfig('access_token'),

            'status_label'                => $this->language->get('status'),
            'test_mode_label'             => $this->language->get('test_mode'),
            'api_key_label'               => $this->language->get('api_key'),
            'access_token_label'          => $this->language->get('access_token'),

            // Plugin extra data
            'plugin_version'              => $this->helper::$version,
        ];

        // Return config view
        $this->response->setOutput($this->load->view('extension/payment/mobbex_config', $data));
    }

    /**
     * Save plugin configuration form data.
     * 
     * @return void 
     */
    private function saveConfig()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/mobbex'))
            $this->error['warning'] = $this->language->get('error_permission');

        // Save config
        $this->model_setting_setting->editSetting('payment_mobbex', $this->request->post);

        // Redirect to payment extensions page
        $this->session->data['success'] = $this->language->get('success_save');
        $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
    }

    /**
     * Get config from $_POST or database.
     * Only use on plugin configuration form.
     * 
     * @param string $config 
     * 
     * @return mixed 
     */
    private function getFormConfig(string $config)
    {
        // If it just updated
        if (isset($this->request->post["payment_mobbex_$config"]))
            return $this->request->post["payment_mobbex_$config"];

        return $this->config->get("payment_mobbex_$config");
    }
}