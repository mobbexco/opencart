<?php

class ModelExtensionPaymentMobbex extends Model
{
    public function getMethod()
    {
        $this->load->language('extension/payment/mobbex');

        return [
            'code'       => 'mobbex',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => ''
        ];
    }
}