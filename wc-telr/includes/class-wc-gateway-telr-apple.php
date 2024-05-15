<?php
/*
 * Telr Gateway for woocommercee
*/

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Telr_Apple extends WC_Telr_Apple_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'wc_telr_apple_pay';

        parent::__construct();
    }
}
