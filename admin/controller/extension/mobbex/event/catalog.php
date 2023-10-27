<?php

require_once DIR_SYSTEM . 'library/mobbex/sdk.php';
require_once DIR_SYSTEM . 'library/mobbex/config.php';
require_once DIR_SYSTEM . 'library/mobbex/logger.php';

class ControllerExtensionMobbexEventCatalog extends controller {

    /** @var array */
    public $config_views = [
        'multivendor',
    ];

    public function __construct()
    {
        parent::__construct(...func_get_args());
        
        // load lenguage
        $this->load->language('extension/mobbex/catalog_config');

        //Load mobbex models
        $this->mobbexConfig = new MobbexConfig($this->registry);
        $this->logger       = new MobbexLogger($this->registry);
        $this->customField  = new MobbexCustomField($this->registry);

        //Init sdk classes
        (new \MobbexSdk($this->registry))->init();
    }

    /**
     * Logic to execute after product|category admin form template loaded.
     * 
     * @param string $route
     * @param array $route
     * @param string $route
     * 
     * @return string
     */
    public function catalog_form_after(&$route, &$data, &$output)
    {
        //get catalog type
        $catalogType = strpos($route, 'product') ? 'product' : 'category';

        //Get the catalog id
        $id = $this->request->get[$catalogType.'_id'];

        //Return if there arent catalog id
        if(empty($id))
            return;

        //Get plans configurations
        $sources = $this->getCatalogSources($id, $catalogType);

        // Get Configs
        $configs = [
            'catalogType' => $catalogType,
            'vendor_uid'  => $this->mobbexConfig->getCatalogOptions($id, 'vendor_uid' , $catalogType),
        ];

        //Get translations
        $translations = [
            'mobbex_title'         => $this->language->get('mobbex_title'),
            'plans_config_title'   => $this->language->get('plans_config_title'),
            'common_plans_label'   => $this->language->get('common_plans_label'),
            'advanced_plans_label' => $this->language->get('advanced_plans_label'),
            'multivendor_title'    => $this->language->get('multivendor_title'),
            'multivendor_label'    => $this->language->get('multivendor_label'),
        ];

        //Set template data
        $templateData = array_merge($sources, $translations, $configs);
        
        //add templates
        $templateData['templates'] = [
                'plans_filter' => $this->load->view('extension/mobbex/catalog/plans_filter', $templateData),
                'multivendor'  => $this->load->view('extension/mobbex/catalog/multivendor', $templateData),
        ];

        // Get the base template
        $template = $this->load->view('extension/mobbex/catalog/settings', $templateData);

        // Insert the snippet after the output
        $output = str_replace('</body>', $template . '</body>', $output);
    }

    /**
     * Logic to execute after product|category configs get saved
     * 
     * @param string $route
     * @param array $route
     * @param string $route
     * 
     */
    public function save_catalog_after(&$route, &$data, &$output)
    {
        //get catalog type
        $catalogType = strpos($route, 'product') ? 'product' : 'category';

        //Get the catalog id
        $id = $this->request->get[$catalogType . '_id'];

        //Return if there arent product id
        if(empty($id))
            return;

        $commonPlans = $advancedPlans = array();
            
        //Get plans selected
        foreach ($data[1] as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no')
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            else if (strpos($key, 'advanced_plan_') !== false && $value === 'on')
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
        }

        //Prepare mobbex configs
        $configs = [
            'common_plans'   => serialize($commonPlans),
            'advanced_plans' => serialize($advancedPlans),
            'vendor_uid'     => isset($data[1]['mbbx-multivendor']) ? $data[1]['mbbx-multivendor'] : '',
        ];

        //Save mobbex configs in custom fields
        foreach ($configs as $key => $value)
            $this->customField->save($id, $catalogType, $key, $value);
    }

    /**
     * Get Mobbex sources for a product or category
     * 
     * @param int|string $id
     * @param string $catalogType Object type 
     * 
     * @return array
     */
    public function getCatalogSources($id, $catalogType)
    {
        try {
            //get catalog plans
            extract($this->mobbexConfig->getCatalogPlans($id, $catalogType, true));

            //return filtered plans
            return \Mobbex\Repository::getPlansFilterFields($id, $common_plans, $advanced_plans);

        } catch (\Mobbex\Exception $e) {

            //log info
            $this->logger->log('error', "Template > getContent | " . $e->getMessage());

            //return empty data to avoid errors
            return ['commonFields' => [], 'advancedFields' => [], 'sourceNames' => []];

        }
    }
}