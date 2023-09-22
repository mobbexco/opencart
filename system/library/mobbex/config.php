<?php

require_once DIR_SYSTEM . 'library/mobbex/custom_field.php';

/**
 * MobbexConfig
 * 
 * A model to manage the options, configurations & info of the plugin.
 * 
 */
class MobbexConfig extends Model
{
    /** @var string */
    public static $version = '2.0.0';
    
    public $settings = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('setting/setting');

        //Set mobbex settings as properties
        $this->formatSettings('payment_mobbex');
        $this->formatSettings('module_mobbex_finance_widget');

        //Load classes
        $this->customField = new MobbexCustomField($registry);
    }

    /**
     * Format the Mobbex configs array for compatibility with php plugins sdk & set configs has properties.
     * 
     * @param string $replace Config key to replace.
     */
    private function formatSettings($replace)
    {
        foreach ($this->model_setting_setting->getSetting($replace) as $key => $value) {
            $configKey = str_replace($replace . '_', '', $key);
            $this->settings[$configKey] = $this->$configKey = $value;
        }
    }

    /**
     * Get a Mobbex catalog option.
     * 
     * @param int|string $id Catalog id.
     * @param string $object Type of searched config.
     * @param string $catalogType Catalog type.
     */
    public function getCatalogOptions($id, $object, $catalogType = 'product')
    {
        if (strpos($object, '_plans'))
            return unserialize($this->customField->get($id, $catalogType, $object)) ?: [];

        return $this->customField->get($id, $catalogType, $object) ?: '';
    }

    /**
     * Get all active plans from a given product and his categories
     * 
     * @param string $id product or category id
     * @param string $catalogType type of object
     * @param bool $admin If is called in admin section
     * 
     * @return array
     * 
     */
    public function getCatalogPlans($id, $catalogType = 'product', $admin = false)
    {
        $common_plans = $advanced_plans = [];

        foreach (['common_plans', 'advanced_plans'] as $value) {
            //Get catalog active plans
            ${$value} = array_merge($this->getCatalogOptions($id, $value, $catalogType), ${$value});
            
            //If is product in checkout get plans active in the categories
            if(!$admin)
                foreach ($this->getProdCategories($id) as $categoryId)
                    ${$value} = array_merge(${$value}, $this->getCatalogOptions($categoryId, $value, 'category'));
        }

        // Avoid duplicated plans
        $common_plans   = array_unique($common_plans);
        $advanced_plans = array_unique($advanced_plans);

        return compact('common_plans', 'advanced_plans');
    }

    /**
     * Get the vendor uid asigned to a product.
     * 
     * @param string $id
     * 
     * @return string
     */
    public function getProductVendor($id)
    {
        if(!$this->multivendor)
            return '';

        //Get entity from product
        if ($this->getCatalogOptions($id, 'vendor_uid'))
            return $this->getCatalogOptions($id, 'vendor_uid');

        //get entity from categories
        foreach ($this->getProdCategories($id) as $categoryId)
            if ($this->getCatalogOptions($categoryId, 'vendor_uid', 'category'))
                return $this->getCatalogOptions($categoryId, 'vendor_uid', 'category');

        return '';
    }

    /**
     * Get product categories id from product id.
     * 
     * @param string $product_id
     * 
     * @return array
     */
    public function getProdCategories($product_id)
    {
        //get the categories
        $result = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        //Return categories in an array
        return array_column($result->rows, 'category_id');
    }

    /**
     * Get all plans from given products ids
     * 
     * @param array $products
     * 
     * @return array $array
     * 
     */
    public function getProductsPlans($products)
    {
        $common_plans = $advanced_plans = array();

        foreach ($products as $product) {
            // Merge all product plans
            $product_plans  = $this->getCatalogPlans($product);

            // Merge all catalog plans
            $common_plans   = array_merge($common_plans, $product_plans['common_plans']);
            $advanced_plans = array_merge($advanced_plans, $product_plans['advanced_plans']);
        }

        return compact('common_plans', 'advanced_plans');
    }
}
