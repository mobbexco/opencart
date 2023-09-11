<?php

class MobbexLogger
{
    /** @var Config */
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Save a Mobbex log in opencart log system.
     * 
     * Mode debug: Log data only if debug mode is active
     * Mode error: Always log data.
     * Mode critical: Always log data & stop code execution.
     * 
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function log($mode, $message, $data = [])
    {
        // Save log to db
        if ($mode != 'debug' || $this->config->get('payment_mobbex_debug_mode')) {
            //Create log
            $fileName = "mobbex_log_$mode". "_" . date('Y-m') . ".log";
            $log      = new \Log($fileName);
            //Write log
            $log->write($message.' | Data: '.json_encode($data));
        }

        if ($mode === 'critical')
            die($message);
    }

}