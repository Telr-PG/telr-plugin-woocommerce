<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Telr_Blocks extends AbstractPaymentMethodType 
{
    protected $name = 'wctelr';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_wctelr_settings', []);
    }

    public function get_payment_method_script_handles()
    {		
        switch($this->settings['payment_mode']){
            case 10:
                $checkout_block_file = 'block/seamless_checkout_block.js?v=1';
                break;
            default:
                $checkout_block_file = 'block/checkout_block.js?v=2';
            break;
        }
		
        wp_register_script(
            'wctelr-blocks-integration',
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
            wp_set_script_translations('wctelr-blocks-integration');
        }

        return ['wctelr-blocks-integration'];
    }

    public function get_payment_method_data()
    {	
        $telrSupportedNetworks = array();
        $title = $this->settings['title'];
        $description = $this->settings['description'];
        $language = $this->settings['language'];
        $order_button_text = "Place Order";

        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            if(ICL_LANGUAGE_CODE == 'en' || ICL_LANGUAGE_CODE == 'ar'){
                $language = ICL_LANGUAGE_CODE;
            }
        }
		
        if($language == 'ar'){
            $title = $this->settings['title_arabic'];
            $description = $this->settings['description_arabic'];
            $order_button_text = "المتابعة للدفع";
        }

        $path = plugins_url( '../assets/images/', __FILE__ );		
        $url = parse_url($path, PHP_URL_PATH);
        $realPath = realpath($_SERVER['DOCUMENT_ROOT'] . $url);
        if ($realPath && is_dir($realPath)) {
            $telrSupportedNetworks = wc_gateway_telr()->admin->getTelrSupportedNetworks();
        }

        return [
            'title' => $title,
            'description' => $description,
            'storeId' => $this->settings['store_id'],
            'currency' => get_woocommerce_currency(),
            'testMode' => $this->settings['testmode'] === 'yes' ? 1 : 0,
            'paymentMode'=>$this->settings['payment_mode'],
            'savedCards' => array(),
            'language' => $language,
            'supportNetworks'=>$telrSupportedNetworks,
            'orderButtonText' =>$order_button_text,
            'iconPath'=>$path.'spacer.gif'
        ]; 
    }
}
