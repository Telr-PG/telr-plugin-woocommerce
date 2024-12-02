<?php
/*
 * Plugin Name: Telr Secure Payments
 * Plugin URI: https://www.telr.com/
 * Description: Telr Secure Payments with Woocommerce Subscriptions & Seamless Mode Payments
 * Version: 8.5
 * Author: Telr
 * Author URI: https://www.telr.com/
 * License: GPL2
 * WC requires at least: 3.0.0
 * WC tested up to: 6.3.1
*/

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

function wc_gateway_telr()
{
    static $plugin;

    if (!isset($plugin)) {
        require_once('includes/class-wc-gateway-telr-plugin.php');
 
        $plugin = new WC_Gateway_Telr_Plugin(__FILE__);
    }

    return $plugin;
}
 
wc_gateway_telr()->maybe_run();

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil'))
    {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('woocommerce_blocks_loaded', 'telr_woocommerce_block_support');

function telr_woocommerce_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'))
    {
        require_once dirname( __FILE__ ) . '/includes/checkout-block.php';
		require_once dirname(__FILE__) . '/includes/checkout-block-apple.php';

        add_action(
          'woocommerce_blocks_payment_method_type_registration',
          function(PaymentMethodRegistry $payment_method_registry) {
            $container = Automattic\WooCommerce\Blocks\Package::container();
            $container->register(
                WC_Telr_Blocks::class,
                function() {
                    return new WC_Telr_Blocks();
                }
            );
            $payment_method_registry->register($container->get(WC_Telr_Blocks::class));

            $container->register(
                WC_Telr_Apple_Blocks::class,
                function() {
                    return new WC_Telr_Apple_Blocks();
                }
            );
            $payment_method_registry->register($container->get(WC_Telr_Apple_Blocks::class));
          },
          5
        );
    }
}