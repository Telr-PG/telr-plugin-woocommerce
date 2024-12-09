<?php
/**
* Checkout handler class.
*/

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}
$includes_path = wc_gateway_telr()->includes_path;
require_once($includes_path. 'abstracts/abstract-wc-gateway-telr.php');
require_once($includes_path. 'abstracts/abstract-wc-gateway-telr-apple.php');																			 

class WC_Gateway_Telr_Checkout_Handler
{
    public $telr_payment_gateway;
    public $telr_apple_payment_gateway;
    public $payment_mode;
    public $payment_mode_woocomm;
    public $subs_method;
    public $tran_type;

    public function __construct()
    {        		
        $this->telr_payment_gateway = new WC_Telr_Payment_Gateway();
        $this->telr_apple_payment_gateway = new WC_Telr_Apple_Payment_Gateway();	
        $this->payment_mode = wc_gateway_telr()->settings->__get('payment_mode');
        $this->payment_mode_woocomm = wc_gateway_telr()->settings->__get('payment_mode');
        $this->subs_method = wc_gateway_telr()->settings->__get('subscription_method');
        $this->tran_type = wc_gateway_telr()->settings->__get('tran_type');
    }
    
    /*
    * Process payment for checkout
    *
    * @param order id (int)
    * @access public
    * @return array
    */
    public function process_payment($order_id)
    {
        $order    = new WC_Order($order_id);
      
        if($this->subs_method == 'telr' || $this->subs_method == 'woocomm'){

            if(!$this->validateOrderProducts($order_id)){
                wc_add_notice("Only 1 Repeat Billing product is allowed per transaction.", 'error');
                return array(
                    'result'    => 'failure',
                    'redirect'  => false,
                
                );
            }
        }

        if($this->payment_mode_woocomm == '10' && (!isset($_POST['telr_payment_token']) || trim($_POST['telr_payment_token']) == '')){
            $error_message = "Unable to process payment. Card details are not valid.";
            wc_add_notice($error_message, 'error');
            return array(
                'result'    => 'failure',
                'redirect'  => false,
            );
        }

        $result   = $this->generate_request($order);
        $telr_ref = trim($result['order']['ref']);
        $telr_url = trim($result['order']['url']);

        if (empty($telr_ref) || empty($telr_url)) {
            $error_message = "Payment has been failed, Please try again.";
            if (isset($result['error']) and !empty($result['error'])) {
                $error_message = $result['error']['message'].'.';
                $error_message = str_replace('E05', 'Error', $error_message);
            }
            
            wc_add_notice($error_message, 'error');
            return array(
                'result'    => 'failure',
                'redirect'  => false,
            
            );
        }
        
        $order->update_meta_data('_telr_ref',$telr_ref);
        $order->update_status('wc-pending');
		
        if ($order->get_meta('_telr_tran_type',true)) {
            $order->delete_meta_data('_telr_tran_type',true);
        }
        $order->add_meta_data('_telr_tran_type',$this->tran_type);

        if(is_ssl() && ($this->payment_mode_woocomm == 2 || $this->payment_mode_woocomm == '9' || $this->payment_mode_woocomm == '10') && !isset($_POST['woocommerce_change_payment'])) {
            if ($order->get_meta('_telr_url',true)) {
                $order->delete_meta_data('_telr_url',true);
            }
            $order->add_meta_data('_telr_url',$telr_url);
            if($this->payment_mode_woocomm == '9' || $this->payment_mode_woocomm == '10'){
                return array(
                    'result'    => 'success',
                    'redirect'    => false,
                    'iframe_url'  => $telr_url,
                );
            }else{
                $telr_url = $order->get_checkout_payment_url(true);
            }
        }
        $order->save();
		
        return array(
            'result'    => 'success',
            'redirect'  => $telr_url,
            
        );
    }
    
    
    /*
    * api request to telr server
    *
    * @parem request data(array)
    * @access public
    * @return array
    */
    public function api_request($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://secure.telr.com/gateway/order.json');
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $results = curl_exec($ch);
        curl_close($ch);
        $results = json_decode($results, true);
        return $results;
    }
    
    /*
    * generate iframe when framed display is selected
    *
    * @parem @param order id (int)
    * @access public
    * @return array
    */
    public function receipt_page($order_id)
    {
        $order    = new WC_Order($order_id);
		
        $payment_url = $order->get_meta('_telr_url',true);
        $style = '#telr {width: 100%; height: 600px; border: none;}';
        $style .= ".order_details {display: none;}";
        echo "<style>$style</style>";
        echo ' <iframe id= "telr" src= "'.$payment_url.'"  sandbox="allow-forms allow-modals allow-popups-to-escape-sandbox allow-popups allow-scripts allow-top-navigation allow-same-origin"></iframe>';
    }
        
    /*
    * generate request for api request
    *
    * @parem @param order id (int)
    * @access public
    * @return array
    */
    public function generate_request($order)
    {
        global $woocommerce;

        $order_id = $order->get_id();
        $items = $order->get_items();

        $prefix=$productnames="";
        foreach ( $items as $item ) {
           $productnames .= $prefix.$item->get_name();
           $prefix = ', ';
        }
 
        $productinorder = substr($productnames,0,63);

        $ivp_lang = wc_gateway_telr()->settings->__get('language');

        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            if(ICL_LANGUAGE_CODE == 'en' || ICL_LANGUAGE_CODE == 'ar'){
                $ivp_lang = ICL_LANGUAGE_CODE;
            }
        }

        $cart_id   = $order_id."_".uniqid();
        if ($order->get_meta('_telr_cartid',true)) {
            $order->delete_meta_data('_telr_cartid',true);
        }
        $order->add_meta_data('_telr_cartid',$cart_id);
        
        $cart_desc = trim(wc_gateway_telr()->settings->__get('cart_desc'));
        if (empty($cart_desc)) {
            $cart_desc ='Order {order_id}';
        }
        $cart_desc  = preg_replace('/{order_id}/i', $order_id, $cart_desc);


        $cart_desc = str_replace("&amp;", "&", $cart_desc);

        $cart_desc = preg_replace('/{product_names}/i', $productinorder, $cart_desc);

        $cart_desc = substr($cart_desc,0,63);

             
        $order->add_meta_data('_telr_cartdesc',$cart_desc);
        $order->save();
		
        $test_mode  = (wc_gateway_telr()->settings->__get('testmode') == 'yes') ? 1 : 0;
        
        if(!isset($_POST['woocommerce_change_payment'])){
            if (!is_ssl() && ($this->payment_mode_woocomm == '2' || $this->payment_mode_woocomm == '9')) {
                $this->payment_mode = 0;
            }else{
                if(is_ssl() && ($this->payment_mode_woocomm == '9' || $this->payment_mode_woocomm == '10')){
                    $this->payment_mode = 2;
                }
            }
        }else{
            $this->payment_mode = 0;
        }
        
        $return_url = get_site_url() . "/wc-api/payment-response?order_id=" . $order_id;
        $ivp_callback_url = get_site_url() . "/wc-api/ivp-callback?cart_id=" . $cart_id;
        $cancel_url = get_site_url() . "/wc-api/payment-response?order_id=" . $order_id;
        
        $payAmount = $order->get_total();
        
        $data = array(
            'ivp_method'      => "create",
            'ivp_source'      => 'WooCommerce '.$woocommerce->version,
            'ivp_store'       => wc_gateway_telr()->settings->__get('store_id') ,
            'ivp_authkey'     => wc_gateway_telr()->settings->__get('store_secret'),
            'ivp_cart'        => $cart_id,
            'ivp_test'        => $test_mode,
            'ivp_framed'      => $this->payment_mode,
            'ivp_amount'      => $payAmount,
            'ivp_lang'        => $ivp_lang,
            'ivp_currency'    => get_woocommerce_currency(),
            'ivp_trantype'    => $this->tran_type,
            'ivp_desc'        => $cart_desc,
            'return_auth'     => $return_url,
            'return_can'      => $cancel_url,
            'return_decl'     => $return_url,
            'bill_fname'      => $order->get_billing_first_name(),
            'bill_sname'      => $order->get_billing_last_name(),
            'bill_addr1'      => $order->get_billing_address_1(),
            'bill_addr2'      => $order->get_billing_address_2(),
            'bill_city'       => $order->get_billing_city(),
            'bill_region'     => $order->get_billing_state(),
            'bill_zip'        => $order->get_billing_postcode(),
            'bill_country'    => $order->get_billing_country(),
            'bill_email'      => $order->get_billing_email(),
            'bill_tel'        => $order->get_billing_phone(),
            'ivp_update_url'  => $ivp_callback_url,
        );
        if($this->payment_mode_woocomm == '10' && isset($_POST['telr_payment_token']) && trim($_POST['telr_payment_token']) != ''){
            $data['ivp_paymethod'] = 'card';
            $data['card_token'] = trim($_POST['telr_payment_token']);
        }

        if (is_ssl() && is_user_logged_in()) {
            $data['bill_custref'] = get_current_user_id();
        }
        
        // Check for Repeat Billig Product
        $order_items = $order->get_items();
        foreach ($order_items as $product_data) {
				
            $productInfo = $product_data->get_data();
            $productId = $productInfo['product_id'];
            $productQuantity = $productInfo['quantity'];
            $productTotal = $productInfo['total'];
            $isSubProduct = get_post_meta($productId, '_subscription_telr', true);
            $product = wc_get_product( $productId );
				
            if ( class_exists( 'WC_Subscriptions_Order' ) && $this->subs_method == 'woocomm' && 
                ($product->get_type() == 'subscription' || $product->get_type() == 'variable-subscription')) {
                $recurrCount = get_post_meta($productId, '_subscription_length', true);
                if(empty(get_post_meta($productId, '_sale_price', true)) || get_post_meta($productId, '_sale_price', true) <= 0 ){
                    $recurrAmount = get_post_meta($productId, '_subscription_price', true);
                }else{
                    $recurrAmount = get_post_meta($productId, '_sale_price', true);
                }
                $recurrInterval = get_post_meta($productId, '_subscription_period_interval', true);
                $recurrIntUnit = get_post_meta($productId, '_subscription_period', true);
					
                $data['repeat_amount'] = $recurrAmount * $productQuantity;
                $data['repeat_period'] = $recurrIntUnit;
                $data['repeat_interval'] = $recurrInterval;
                $data['repeat_start'] = 'next';
                $data['repeat_term'] = $recurrCount;
                $data['repeat_auto'] = 0;
                $data['repeat_type'] = 'recurring';
					
            }elseif($this->subs_method == 'telr' && $isSubProduct == 'yes'){
                $recurrCount = get_post_meta($productId, '_continued_of', true);
                $recurrAmount = get_post_meta($productId, '_payment_of', true);
                $recurrInterval = get_post_meta($productId, '_every_number_of', true);
                $recurrIntUnit = get_post_meta($productId, '_for_number_of', true);
                $finalAmount = get_post_meta($productId, '_final_payment_of', true);
                $scheduleDate = "";
				
                $data['repeat_amount'] = $recurrAmount * $productQuantity;
                $data['repeat_period'] = $recurrIntUnit;
                $data['repeat_interval'] = $recurrInterval;
                $data['repeat_start'] = 'next';
                $data['repeat_term'] = $recurrCount;
                $data['repeat_auto'] = 1;
                $data['repeat_type'] = 'recurring';
                if( $finalAmount > 0){
                    $data['repeat_final'] = $finalAmount * $productQuantity;    
                }
            }
        }
        // End Check for Repeat Billing Product

        $response = $this->api_request($data);
        return $response;
	}
	
	/*
    * generate apple pay request for api request
    *
    * @parem @param order id (int)
    * @access public
    * @return array
    */
    public function generate_applepay_request($order,$applePayData)
    {
        global $woocommerce;

        $order_id = $order->get_id();
        $items = $order->get_items();

        $prefix=$productnames="";
        foreach ( $items as $item ) {
           $productnames .= $prefix.$item->get_name();
           $prefix = ', ';
        }
 
        $productinorder = substr($productnames,0,63);

        $ivp_lang = wc_gateway_telr()->settings->__get('language');

        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            if(ICL_LANGUAGE_CODE == 'en' || ICL_LANGUAGE_CODE == 'ar'){
                $ivp_lang = ICL_LANGUAGE_CODE;
            }
        }

        $cart_id   = $order_id."_".uniqid();
        if ($order->get_meta('_telr_cartid',true)) {
            $order->delete_meta_data('_telr_cartid',true);
        }
        $order->add_meta_data('_telr_cartid',$cart_id);
        
        $cart_desc = trim(wc_gateway_telr()->settings->__get('cart_desc'));
        if (empty($cart_desc)) {
            $cart_desc ='Order {order_id}';
        }
        $cart_desc  = preg_replace('/{order_id}/i', $order_id, $cart_desc);
        $cart_desc = str_replace("&amp;", "&", $cart_desc);
        $cart_desc = preg_replace('/{product_names}/i', $productinorder, $cart_desc);
        $cart_desc = substr($cart_desc,0,63);             
        $order->add_meta_data('_telr_cartdesc',$cart_desc);
        $order->save();        
        $payAmount = $order->get_total();
        
	    $params = array(
	       'ivp_method'      => 'applepay',
	       'ivp_store'       => wc_gateway_telr()->settings->__get('store_id'),
	       'ivp_authkey'     => wc_gateway_telr()->settings->__get('remote_v2_auth_key'),
	       'ivp_amount'      => $payAmount,
	       'ivp_test'        => '0',
	       'ivp_desc'        => $cart_desc,
	       'ivp_currency'    => get_woocommerce_currency(),
	       'ivp_cart'        => $cart_id,
	       'ivp_trantype'    => $this->tran_type,
	       'ivp_tranclass'   => 'ecom',
	       'bill_fname'      => $order->get_billing_first_name(),
           'bill_sname'      => $order->get_billing_last_name(),
           'bill_addr1'      => $order->get_billing_address_1(),
           'bill_addr2'      => $order->get_billing_address_2(),
           'bill_city'       => $order->get_billing_city(),
           'bill_region'     => $order->get_billing_state(),
           'bill_zip'        => $order->get_billing_postcode(),
           'bill_country'    => $order->get_billing_country(),
           'bill_email'      => $order->get_billing_email(),
	       'bill_tel'        => $order->get_billing_phone(),
	       'applepay_enc_version'  => $applePayData['applepayversion'],
	       'applepay_enc_paydata'  => urlencode($applePayData['applepaydata']),
	       'applepay_enc_paysig'   => urlencode($applePayData['applepaysignature']),
	       'applepay_enc_pubkey'   => urlencode($applePayData['applepaykey']),
	       'applepay_enc_keyhash'  => $applePayData['applepaykeyhash'],
	       'applepay_tran_id'      => $applePayData['applepaytransactionid'],
	       'applepay_card_desc'    => $applePayData['applepaytype'],
	       'applepay_card_scheme'  => $applePayData['applepaydisplayname'],
	       'applepay_card_type'    => $applePayData['applepaynetwork'],
	       'applepay_tran_id2'     => $applePayData['applepaytransactionidentifier']
	    );
		
		// Check for Repeat Billig Product
        $order_items = $order->get_items();
        foreach ($order_items as $product_data) {
				
            $productInfo = $product_data->get_data();
            $productId = $productInfo['product_id'];
            $productQuantity = $productInfo['quantity'];
            $productTotal = $productInfo['total'];
            $isSubProduct = get_post_meta($productId, '_subscription_telr', true);
            $product = wc_get_product( $productId );
				
            if ( class_exists( 'WC_Subscriptions_Order' ) && $this->subs_method == 'woocomm' && 
                ($product->get_type() == 'subscription' || $product->get_type() == 'variable-subscription')) {
                $recurrCount = get_post_meta($productId, '_subscription_length', true);
                if(empty(get_post_meta($productId, '_sale_price', true)) || get_post_meta($productId, '_sale_price', true) <= 0 ){
                    $recurrAmount = get_post_meta($productId, '_subscription_price', true);
                }else{
                    $recurrAmount = get_post_meta($productId, '_sale_price', true);
                }				
                $recurrInterval = get_post_meta($productId, '_subscription_period_interval', true);
                $recurrIntUnit = get_post_meta($productId, '_subscription_period', true);
					
                $params['repeat_amount'] = $recurrAmount * $productQuantity;
                $params['repeat_period'] = $recurrIntUnit;
                $params['repeat_interval'] = $recurrInterval;
                $params['repeat_start'] = 'next';
                $params['repeat_term'] = $recurrCount;
                $params['repeat_auto'] = 0;
                $params['repeat_type'] = 'recurring';
					
            }elseif($this->subs_method == 'telr' && $isSubProduct == 'yes'){
                $recurrCount = get_post_meta($productId, '_continued_of', true);
                $recurrAmount = get_post_meta($productId, '_payment_of', true);
                $recurrInterval = get_post_meta($productId, '_every_number_of', true);
                $recurrIntUnit = get_post_meta($productId, '_for_number_of', true);
                $finalAmount = get_post_meta($productId, '_final_payment_of', true);
                $scheduleDate = "";
				
                $params['repeat_amount'] = $recurrAmount * $productQuantity;
                $params['repeat_period'] = $recurrIntUnit;
                $params['repeat_interval'] = $recurrInterval;
                $params['repeat_start'] = 'next';
                $params['repeat_term'] = $recurrCount;
                $params['repeat_auto'] = 1;
                $params['repeat_type'] = 'recurring';
                if( $finalAmount > 0){
                    $params['repeat_final'] = $finalAmount * $productQuantity;    
                }
            }
        }
        // End Check for Repeat Billing Product
		
        $response = $this->remoteApiRequest($params);
        return $response;			 
    }

	function remoteApiRequest($data)
    {
	    $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://secure.telr.com/gateway/remote.json');
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $results = curl_exec($ch);
        curl_close($ch);
        $results = json_decode($results, true);
        return $results;
    }

    private function validateOrderProducts($order_id){
        global $wpdb;

        $order = new WC_Order($order_id);
        $order_items = $order->get_items();

        $repeatBilling = false;

        foreach ($order_items as $product_data) {
            $productInfo = $product_data->get_data();
            $productId = $productInfo['product_id'];
            $productQuantity = $productInfo['quantity'];
            $productTotal = $productInfo['total'];
            $product = wc_get_product( $productId );
            $isSubProduct = get_post_meta($productId, '_subscription_telr', true);
            if($isSubProduct == 'yes' || $product->get_type() == 'subscription' || $product->get_type() == 'variable-subscription'){
                $repeatBilling = true;
            }
        }

        if($repeatBilling && count($order_items) > 1){
            return false;
        }
        else{
            return true;
        }
    }
}
