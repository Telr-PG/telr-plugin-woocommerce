<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Telr_Apple_Blocks extends AbstractPaymentMethodType 
{
    protected $name = 'wc_telr_apple_pay';

    public function initialize()
    {
        $this->settings = array_merge((array) get_option('woocommerce_wc_telr_apple_pay_settings', array()),(array) get_option('woocommerce_wctelr_settings', array()));
        //print_r($this->settings); exit;
    }

    public function get_payment_method_script_handles()
    {
        $checkout_block_file = 'block/checkout_block_apple.js?v=1';
        wp_register_script(
            'wc_telr_apple_pay-blocks-integration',
            plugin_dir_url(__FILE__) . $checkout_block_file,
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
                'wp-blocks',
                'wp-components',
                'wp-data',
                'wp-hooks',
            ],
            null,
            true
        );

        if (function_exists('wp_set_script_translations')) 
        {
            wp_set_script_translations('wc_telr_apple_pay-blocks-integration');
        }

        return ['wc_telr_apple_pay-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        $telrSupportedNetworks = array();
        $title = isset($this->settings['apple_pay_title']) ? $this->settings['apple_pay_title'] : '';
        $description = isset($this->settings['apple_pay_description']) ? $this->settings['apple_pay_description'] : '';
        $language = isset($this->settings['language']) ? $this->settings['language'] : '';
        $subs_method = isset($this->settings['subscription_method']) ? $this->settings['subscription_method'] : '';
        $order_button_text = "Place Order";

        $apple_mercahnt_id = isset($this->settings['apple_mercahnt_id']) ? $this->settings['apple_mercahnt_id'] : '';
        $apple_type = isset($this->settings['apple_type']) ? $this->settings['apple_type'] : '';
        $apple_theme = isset($this->settings['apple_theme']) ? $this->settings['apple_theme'] : '';
        $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

        if (is_admin()) {
            return [
                'is_admin'=>true,
                'title' => $title,
                'description' => $description,
                'iconPath'=>plugins_url( '../assets/images/', __FILE__ ).'spacer.gif'
            ];
        }elseif(isset($payment_gateways['wc_telr_apple_pay'])){
            global $woocommerce;
            $subscriptionProduct = false;
            $subscriptionProductCount = 0;
            $amount = 0;
            $recurrIntUnit = '';
            $recurrInterval = '';
            $cart_desc = trim(wc_gateway_telr()->settings->__get('cart_desc'));
            $items = $woocommerce->cart->get_cart();

            foreach($items as $item => $values) {
                $productId = $values['data']->get_id();
                $_product =  wc_get_product($productId);
                $isSubProduct = get_post_meta($productId, '_subscription_telr', true);

                if ( class_exists( 'WC_Subscriptions_Order' ) && $subs_method == 'woocomm' && 
                ($_product->get_type() == 'subscription' || $_product->get_type() == 'variable-subscription'))
                {
                    $subscriptionProduct = true;
                    if(empty(get_post_meta($productId, '_sale_price', true)) || get_post_meta($productId, '_sale_price', true) <= 0 ){
                        $recurrAmount = get_post_meta($productId, '_subscription_price', true);
                    }else{
                        $recurrAmount = get_post_meta($productId, '_sale_price', true);
                    }
                    $amount = $recurrAmount * $values['quantity'];
                    $recurrInterval = get_post_meta($productId, '_subscription_period_interval', true);
                    $recurrIntUnit = get_post_meta($productId, '_subscription_period', true);
                    $subscriptionProductCount = $subscriptionProductCount + 1;
                }elseif($subs_method == 'telr' && $isSubProduct == 'yes'){
                    $subscriptionProduct = true;
                    $recurrIntUnit = get_post_meta($productId, '_for_number_of', true);
                    if($recurrIntUnit == 'W'){
                        $recurrIntUnit = 'week';
                    }elseif($recurrIntUnit == 'M'){
                        $recurrIntUnit = 'month';
                    }
                    $recurrInterval = get_post_meta($productId, '_every_number_of', true);
                    $recurrAmount = get_post_meta($productId, '_payment_of', true);
                    $amount = $recurrAmount * $values['quantity'];
                    $subscriptionProductCount = $subscriptionProductCount + 1;
                }
            }

            $cartTotal = $woocommerce->cart->total;
            $cartSubTotal = $woocommerce->cart->subtotal;
            $chosen_methods     = wc_get_chosen_shipping_method_ids();
            $chosen_shipping    = $chosen_methods[0] ?? '';
            $shipping_amount    = WC()->cart->get_shipping_total();
            $checkout_fields    = json_encode($woocommerce->checkout->checkout_fields, JSON_HEX_APOS);
            $session_url        = str_replace('https:', 'https:', add_query_arg( 'wc-api', 'wc_telr_session', home_url( '/' ) ) );
            $generate_token_url = str_replace('https:', 'https:', add_query_arg( 'wc-api', 'wc_telr_generate_token', home_url( '/' ) ) );
            $country_code          = WC()->customer->get_billing_country();
            $supported_networks    = ['masterCard','visa'];
            $merchant_capabilities = [ 'supports3DS', 'supportsCredit', 'supportsDebit' ];
            $telrSupportedNetworks = wc_gateway_telr()->admin->getTelrSupportedNetworks();

            if(!empty($telrSupportedNetworks)){
                if (in_array('APPLEPAY MADA',$telrSupportedNetworks)) {
                            array_push( $supported_networks, 'mada' );
                            $country_code = 'SA';
                }
                if (in_array('APPLEPAY AMEX',$telrSupportedNetworks)) {
                    array_push( $supported_networks, 'amex' );
                }
                if (in_array('APPLEPAY DISCOVER',$telrSupportedNetworks)) {
                    array_push( $supported_networks, 'discover' );
                }
                if (in_array('APPLEPAY JCB',$telrSupportedNetworks)) {
                    array_push( $supported_networks, 'jcb' );
                }
            }

            return [
                'title' => $title,
                'description' => $description,
                'storeId' => $this->settings['store_id'],
                'currency' => get_woocommerce_currency(),
                'country_code'=> $country_code,
                'testMode' => $this->settings['testmode'] === 'yes' ? 1 : 0,
                'paymentMode'=>$this->settings['payment_mode'],
                'language' => $language,
                'orderButtonText' =>$order_button_text,
                'iconPath'=> plugins_url( '../assets/images/', __FILE__ ).'spacer.gif',
                'subscriptionProductCount'=>$subscriptionProductCount,
                'apple_mercahnt_id'=>$apple_mercahnt_id,
                'apple_type'=>$apple_type,
                'apple_theme'=>$apple_theme,
                'checkout_fields'=>$checkout_fields,
                'supported_networks'=>$supported_networks,
                'merchant_capabilities'=>$merchant_capabilities,
                'subscriptionProduct' =>$subscriptionProduct,
                'cart_desc'=>$cart_desc,
                'amount'=>$amount,
                'recurrIntUnit'=>$recurrIntUnit,
                'recurrInterval'=>$recurrInterval,
                'site_url'=> get_site_url(),
                'cartTotal'=>$cartTotal,
                'cartSubTotal'=>$cartSubTotal,
                'chosen_shipping'=>$chosen_shipping,
                'shipping_amount'=>$shipping_amount,
                'session_url'=>$session_url,
                'applepay_enable'=>true
            ];
        }
    }
}
