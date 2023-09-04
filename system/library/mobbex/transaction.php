<?php

/**
 * MobbexTransaction
 * 
 * A model to manage transaction data
 * 
 */

class MobbexTransaction extends Model 
{
     /** Mobbex Transaction Table Name */
    public $tableName;

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->tableName = DB_PREFIX . "mobbex_transaction";
    }

    /**
     * Insert transaction data in mobbex transaction table
     * 
     * @param array $data
     * 
     */
    public function saveTransaction($data)
    {
        // Get column names in db
        $columns = $this->db->query("SHOW COLUMNS FROM {$this->tableName} ");

        $names = $values = [];

        // Assign data to the corresponding column
        foreach ($columns->rows as $column) {
            $names[]  = $column['column_name'];
            $values[] = $data[$column['column_name']];
        }

        $queryValues = '';

        foreach ($values as $key => $value)
            $queryValues .= $key == 0 ? "'$value'" : ", '$value'";

        // Set query
        $query = "INSERT INTO {$this->tableName} (" . implode(', ', $names) . ") VALUES ($queryValues);";

        $this->db->query($query);
    }

    /**
     * Format the webhook data to save in db.
     * 
     * @param array $webhookData
     * @param int $cartId
     * 
     * @return array
     */
    public function formatWebhookData($webhookData, $cartId)
    {
        $data = [
            'cart_id'            => $cartId,
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