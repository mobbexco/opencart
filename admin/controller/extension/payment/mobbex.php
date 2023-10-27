<?php

require_once DIR_SYSTEM . 'library/mobbex/sdk.php';
require_once DIR_SYSTEM . 'library/mobbex/config.php';

class ControllerExtensionPaymentMobbex extends Controller
{
    /** @var MobbexConfig */
    public static $config;

    /** Notices about tables creation */
    public $schemaNotices = [];

    private $events = array(
        'admin/view/catalog/product_form/after' => array(
            'extension/mobbex/event/catalog/catalog_form_after'
        ),
        'admin/view/catalog/category_form/after' => array(
            'extension/mobbex/event/catalog/catalog_form_after'
        ),
        'admin/model/catalog/product/editProduct/after' => array(
            'extension/mobbex/event/catalog/save_catalog_after'
        ),
        'admin/model/catalog/category/editCategory/after' => array(
            'extension/mobbex/event/catalog/save_catalog_after'
        ),
        'admin/view/common/header/before' => array(
            'extension/mobbex/event/script/add_scripts'
        ),
    );

    public function __construct()
    {
        parent::__construct(...func_get_args());

        // load models and instance helper
        $this->load->model('setting/setting');
        $this->load->language('extension/payment/mobbex');
        $this->mobbexConfig = new MobbexConfig($this->registry);
        $this->logger       = new MobbexLogger($this->registry);

        //Init sdk classes
        (new \MobbexSdk($this->registry))->init();
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
     * Create Mobbex tables in database and add dni field to customer form in checkout.
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

            //Show alert in case of error
            if(!$table->result)
                $this->schemaNotices['error'][] = str_replace('{TABLE}', $tableName, $this->language->get('error_table_creation'));
        }
        
        //Register Events
        $this->registerEvents();

        // Add dni field
        $this->installDniField();
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
            // Checks if current currency is supported by Mobbex
            'ars_currency'  => $this->config->get('config_currency') == 'ARS',

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
            'payment_mobbex_embed'        => $this->getFormConfig('embed'),
            'payment_mobbex_multicard'    => $this->getFormConfig('multicard'),
            'payment_mobbex_multivendor'  => $this->getFormConfig('multivendor'),

            // Labels
            'status_label'                 => $this->language->get('status'),
            'test_mode_label'              => $this->language->get('test_mode'),
            'api_key_label'                => $this->language->get('api_key'),
            'access_token_label'           => $this->language->get('access_token'),
            'debug_mode_label'             => $this->language->get('debug_mode'),
            'embed_label'                  => $this->language->get('embed'),
            'multicard_label'              => $this->language->get('multicard'),
            'multivendor_label'            => $this->language->get('multivendor'),
            'text_unified'                 => $this->language->get('multivendor_unified'),
            'text_active'                  => $this->language->get('multivendor_active'),

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

        return $this->mobbexConfig->$config;
    }

    /**
     * Register mobbex callbacks in opencart events.
     */
    public function registerEvents()
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('mobbex');
        foreach ($this->events as $trigger => $actions) {
            foreach ($actions as $action)
                $this->model_setting_event->addEvent('mobbex', $trigger, $action);
        }
    }

    /**
     * Add dni field to customer form at checkout if there is none
     * 
     */
    public function installDniField()
    {
        // Load model
        $this->load->model('customer/custom_field');

        // Create a query to the database
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "custom_field_description` WHERE name = 'DNI'")->num_rows;
        
        // Check if an DNI custom field exists, otherwise set and creates it
        if ($query != '0'){
            return;
        } else {
            // Customize an array containing the information needed to create the field
            $dniField = [
                "value"      => "", 
                "validation" => "",
                "status"     => "1", 
                "sort_order" => "5", 
                "type"       => "text",
                "location"   => "account",
                "custom_field_description"    => [
                    "1" => [ 
                        "name" => "DNI" 
                    ]
                ],
                "custom_field_customer_group" => [
                    [
                        "required"          => "1",
                        "customer_group_id" => "1",
                    ] 
                ],
            ];

            // Add dni custom field
            $this->model_customer_custom_field->addCustomField($dniField);
        }
    } 
}