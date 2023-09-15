<?php

require_once DIR_SYSTEM . 'library/mobbex/config.php';

class ControllerExtensionModuleMobbexFinanceWidget extends Controller
{

    public function index()
    {
        // load models and instance helper
        $this->load->model('setting/setting');
        $this->load->language('extension/mobbex/finance_widget');
        $this->document->setTitle($this->language->get('config_title'));
        $this->mobbexConfig = new MobbexConfig($this->registry);

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
        $this->load->model('setting/setting');

        $query = $this->db->query(
            "SELECT DISTINCT layout_id 
            FROM " . DB_PREFIX . "layout_route 
            WHERE route = 'product/product' OR route LIKE 'checkout/%'"
        );

        $layouts = $query->rows;

        foreach ($layouts as $layout) {
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "layout_module 
                SET layout_id = '" . (int)$layout['layout_id'] . "', 
                code = 'mobbex_finance_widget', 
                position = 'column_right', 
                sort_order = '0'"
            );
        }

        //Set default settings
        $setting['module_mobbex_finance_widget_status']        = 0;
        $setting['module_mobbex_finance_widget_button_text']   = 'Ver Financiación';
        $setting['module_mobbex_finance_widget_button_logo']   = 'https://res.mobbex.com/images/sources/mobbex.png';
        $setting['module_mobbex_finance_widget_button_styles'] = '
            #mbbxProductBtn {
                width: fit-content;
                min-height: 40px;
                border-radius: 6px;
                padding: 8px 18px;
                font-size: 16px;
                color: #6f00ff; 
                background-color: #ffffff;
                border: 1.5px solid #6f00ff;
                /*box-shadow: 2px 2px 4px 0 rgba(0, 0, 0, .2);*/
            }

            #mbbxProductBtn:hover {
                color: #ffffff;
                background-color: #6f00ff;
            }
        ';

        $this->model_setting_setting->editSetting('module_mobbex_finance_widget', $setting);
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
            'heading_title' => $this->language->get('heading_title_without_logo'),

            // Get template sections
            'header' 	    => $this->load->controller('common/header'),
            'footer' 	    => $this->load->controller('common/footer'),
            'column_left'   => $this->load->controller('common/column_left'),

            // Get button and action links
            'action'        => $this->url->link('extension/module/mobbex_finance_widget', 'user_token=' . $this->session->data['user_token'], true),
            'cancel'        => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
            'breadcrumbs'   => [
                [
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
                ],
                [
                    'text' => $this->language->get('text_extension'),
                    'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
                ],
                [
                    'text' => $this->language->get('heading_title_without_logo'),
                    'href' => $this->url->link('extension/module/mobbex_finance_widget', 'user_token=' . $this->session->data['user_token'], true),
                ],
            ],

            // Mobbex widget fields
            'module_mobbex_finance_widget_status_widget'  => $this->mobbexConfig->status_widget,
            'module_mobbex_finance_widget_active_product' => $this->mobbexConfig->active_product,
            'module_mobbex_finance_widget_active_cart'    => $this->mobbexConfig->active_cart,
            'module_mobbex_finance_widget_button_text'    => $this->mobbexConfig->button_text,
            'module_mobbex_finance_widget_button_logo'    => $this->mobbexConfig->button_logo,
            'module_mobbex_finance_widget_button_styles'  => $this->mobbexConfig->button_styles,
            
            //Fields labels
            'status_label'         => $this->language->get('status_label'),
            'active_product_label' => $this->language->get('active_product_label'),
            'active_cart_label'    => $this->language->get('active_cart_label'),
            'button_text_label'    => $this->language->get('button_text_label'),
            'button_logo_label'    => $this->language->get('button_logo_label'),
            'button_styles_label'  => $this->language->get('button_styles_label'),
            
            // Plugin extra data
            'plugin_version' => '1.0',
        ];

        // Return config view
        $this->response->setOutput($this->load->view('extension/mobbex/finance_widget', $data));
    }

    /**
     * Save plugin configuration form data.
     * 
     * @return void 
     */
    private function saveConfig()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/mobbex_finance_widget'))
            $this->error['warning'] = $this->language->get('error_permission');

        // Save config
        $this->model_setting_setting->editSetting('module_mobbex_finance_widget', $this->request->post);

        // Redirect to payment extensions page
        $this->session->data['success'] = $this->language->get('success_save');
        $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
    }

}