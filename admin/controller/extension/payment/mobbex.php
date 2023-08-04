<?php

require_once DIR_SYSTEM . 'library/mobbex/sdk.php';
require_once DIR_SYSTEM . 'library/mobbex/config.php';

class ControllerExtensionPaymentMobbex extends Controller
{
    /** @var MobbexConfig */
    public static $config;

    /** Notices about tables creation */
    public $schemaNotices = [];

    public function __construct()
    {
        parent::__construct(...func_get_args());

        // load models and instance helper
        $this->load->model('setting/setting');
        $this->load->language('extension/payment/mobbex');
        $this->load->model('extension/mobbex/db');
        $this->mobbexConfig = new MobbexConfig($this->model_setting_setting->getSetting('payment_mobbex'));
        $this->logger       = new MobbexLogger($this->mobbexConfig);

        //Init sdk classes
        \MobbexSdk::init($this->mobbexConfig, $this->model_extension_mobbex_db->getDbModel());
    }

    public function index()
    {
        // First check that it's installed correctly
        $this->install();

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
    public function install()
    {
        foreach (['transaction', 'custom_fields'] as $tableName) {
            
            //Get definition
            $definition = \Mobbex\Model\Table::getTableDefinition($tableName);
            
            //Modify transaction definition
            if($tableName === 'transaction'){
                foreach ($definition as &$column)
                    if($column['Field'] === 'order_id')
                        $column['Field'] = 'cart_id';
            }

            //Create the table
            $table = new \Mobbex\Model\Table($tableName, $definition);

            //Show table warnings
            foreach ($table->warning as $warning)
                $this->schemaNotices['warning'][] = $warning;

            //Show alert in case of error
            if(!$table->result)
                $this->schemaNotices['error'][] = str_replace('{TABLE}', $tableName, $this->language->get('error_table_creation'));
        }
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
            'payment_mobbex_test'         => $this->getFormConfig('test'),
            'payment_mobbex_api_key'      => $this->getFormConfig('api_key'),
            'payment_mobbex_access_token' => $this->getFormConfig('access_token'),
            'payment_mobbex_debug_mode'   => $this->getFormConfig('debug_mode'),

            'status_label'                 => $this->language->get('status'),
            'test_mode_label'              => $this->language->get('test_mode'),
            'api_key_label'                => $this->language->get('api_key'),
            'access_token_label'           => $this->language->get('access_token'),
            'debug_mode_label'             => $this->language->get('debug_mode'),

            // Plugin extra data
            'plugin_version'              => \MobbexConfig::$version,

            //Schema notices
            'schema_notices'                => $this->schemaNotices,
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