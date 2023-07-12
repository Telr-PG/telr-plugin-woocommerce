<?php
/*
 * Telr Gateway for woocommercee
*/

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Telr extends WC_Telr_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'wctelr';

        parent::__construct();
    }
}
