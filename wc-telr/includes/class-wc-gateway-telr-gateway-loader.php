<?php
/**
 * Telr Plugin Loader.
 */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_telr_Gateway_Loader
{
    
    public function __construct()
    {
        $includes_path = wc_gateway_telr()->includes_path;

        require_once($includes_path . 'class-wc-gateway-telr.php');

        add_filter('woocommerce_payment_gateways', array($this, 'payment_gateways'));
    }

    /**
     * Register telr secure payment methods.
     *
     * @param array Payment methods.
     *
     * @return array Payment methods
     */
    public function payment_gateways($methods)
    {
        $methods[] = 'WC_Gateway_Telr';
        return $methods;
    }
}
