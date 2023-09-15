<?php

class ControllerExtensionMobbexEventScript extends controller
{

    /**
     * Add mobbex scripts to header
     */
    public function add_scripts(&$route, &$data, &$code)
    {
        if(isset($_REQUEST['route']) && $_REQUEST['route'] === 'catalog/product/edit')
            $data['scripts'][] = 'view/javascript/mobbex/catalog_options.js';
    }
}
