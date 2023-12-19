<?php
/**
 * Telr Payment gateway .
 */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Telr_Payment_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->has_fields             = false;  // No additional fields in checkout page
        $this->method_title           = __('Telr', 'wctelr');
        $this->method_description     = __('Telr Checkout', 'wctelr');
        $this->order_button_text      = __('Proceed to Pay', 'wctelr');
        $this->supports = array(
            'subscriptions',
            'multiple_subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'refunds', 
        );
    
        // Load the settings.
        $this->init_form_fields();
        
        // Configure page fields
        $this->init_settings();

        $this->enabled              = wc_gateway_telr()->settings->__get('enabled');
        

        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            if(ICL_LANGUAGE_CODE == 'en') {
				$this->title                = wc_gateway_telr()->settings->__get('title');
                $this->description 			= wc_gateway_telr()->settings->__get('description');
            } else if(ICL_LANGUAGE_CODE == 'ar') {
				$this->title                = wc_gateway_telr()->settings->__get('title_arabic');
				$this->description 			= wc_gateway_telr()->settings->__get('description_arabic');
			} else {
				$this->title                = wc_gateway_telr()->settings->__get('title');
				$this->description 			= wc_gateway_telr()->settings->__get('description');
			}
        } else {
			$this->title                = wc_gateway_telr()->settings->__get('title');
			$this->description 			= wc_gateway_telr()->settings->__get('description');
		}		
		
        $this->store_id             = wc_gateway_telr()->settings->__get('store_id');
        $this->store_secret         = wc_gateway_telr()->settings->__get('store_secret');
        $this->remote_store_secret  = wc_gateway_telr()->settings->__get('remote_store_secret');																						 
        $this->testmode             = wc_gateway_telr()->settings->__get('testmode');
        $this->debug                = wc_gateway_telr()->settings->__get('debug');
        $this->order_status         = wc_gateway_telr()->settings->__get('order_status');
        $this->cart_desc            = wc_gateway_telr()->settings->__get('cart_desc');
        $this->payment_mode         = wc_gateway_telr()->settings->__get('payment_mode');
        $this->language             = wc_gateway_telr()->settings->__get('language');
        $this->default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
        $this->payment_mode_woocomm = wc_gateway_telr()->settings->__get('payment_mode');
        
        //actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page'));

        add_action( 'woocommerce_api_payment-response', array( $this, 'payment_response' ) );
        add_action( 'woocommerce_api_telr-requery', array( $this, 'payment_requery' ) );
        add_action( 'woocommerce_api_ivp-callback', array( $this, 'ivp_check_function' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'wpcheckout_dequeue_and_then_enqueue'), 11 );

        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
        }
    }

    function wpcheckout_dequeue_and_then_enqueue() {
        global $wp_scripts;
        $wp_scripts->registered['wc-checkout']->src = plugin_dir_url( __FILE__ ) . 'js/checkout.min.js';
    }

    public function payment_response() {
        $order_id = 0;
        if(isset($_GET['order_id']) && $_GET['order_id'] > 0){
            $order_id = $_GET['order_id'];
            $order        = new WC_Order($order_id);

            $order_status = $this->check_order($order_id);
            
            if ($order_status == 'processing') {
                $order->payment_complete();
                $default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
                if ($default_order_status != 'none') {
                    $order->update_status($default_order_status);
                }
                WC()->cart->empty_cart();
            } else if($order_status == 'active'){
                $subscription_obj = new WC_Subscription($order_id);
                $subscription_obj->update_status('active');
                wp_redirect(home_url() . "/my-account/subscriptions");
                exit;
            } else {
                $order->update_status($order_status);
            }
            wp_redirect($this->get_return_url($order));
            exit;
        }else{
            echo "Invalid Request!";
            exit;
        }
    }

    function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
        $this->process_subscription_payment( $renewal_order, $amount_to_charge );
    }

    public function process_subscription_payment( $order, $amount ) {
        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        $telr_txnref = reset(get_post_meta($order_id, '_telr_auth_tranref'));

        if ( $telr_txnref ) {
            $test_mode  = (wc_gateway_telr()->settings->__get('testmode') == 'yes') ? 1 : 0;
            $cart_desc = trim(wc_gateway_telr()->settings->__get('cart_desc'));
             if (empty($cart_desc)) {
                $cart_desc ='Order {order_id}';
            }
            $cart_desc  = preg_replace('/{order_id}/i', $order_id, $cart_desc);

            $items = $order->get_items();

            $prefix=$productnames="";
            foreach ( $items as $item ) {
              $productnames .= $prefix.$item->get_name();
               $prefix = ', ';
            }
     
            $productinorder = substr($productnames,0,63);
            $cart_desc = preg_replace('/{product_names}/i', $productinorder, $cart_desc);
            $cart_desc = substr($cart_desc,0,63);

            $data =array(
                'ivp_store' => wc_gateway_telr()->settings->__get('store_id'),
                'ivp_authkey' => wc_gateway_telr()->settings->__get('remote_store_secret'),
                'ivp_trantype' => 'sale',
                'ivp_tranclass' => 'cont',
                'ivp_desc' => $cart_desc,
                'ivp_cart' => $order_id."_".uniqid(),
                'ivp_currency' => get_woocommerce_currency(),
                'ivp_amount' => $amount,
                'ivp_test' => $test_mode,
                'tran_ref'     => $telr_txnref 
            );

            $response = $this->remote_api_request($data);
            parse_str($response, $parsedResponse);
            $authStatus = $parsedResponse['auth_status'];
            $authMessage = $parsedResponse['auth_message'];
            $txnRef = $parsedResponse['auth_tranref'];

            if ($authStatus == 'A') {
                $order->payment_complete( $txnRef );
                $message = sprintf( __( 'Recurring Payment via Telr successful (Transaction Reference: %s)', 'wctelr' ), $txnRef );
                $order->add_order_note( $message );

                $default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
                if ($default_order_status != 'none') {
                    $order->update_status($default_order_status);
                }
                return true;
            }else{
                $gateway_response = __( 'Telr payment failed with Transaction ID: ' . $txnRef . ' Reason: ' . $authMessage, 'wctelr' );
                $order->update_status( 'failed', $gateway_response );
                return new WP_Error( 'telr_error', $gateway_response );
            }
        } else {
            $gateway_response = __( 'Telr payment failed due to non availability of linked transaction reference.', 'wctelr' );
            $order->update_status( 'failed', $gateway_response );
            return new WP_Error( 'telr_error', $gateway_response );
        }
        $order->update_status( 'failed', __( 'This subscription can&#39;t be renewed automatically.', 'wctelr' ) );
        return new WP_Error( 'telr_error', __( 'This subscription can&#39;t be renewed automatically.', 'wctelr' ) );
    }

    public function remote_api_request($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://secure.telr.com/gateway/remote.html');
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $results = curl_exec($ch);
        curl_close($ch);
        return $results;
    }

    /**
     *  Endpoint to handle Transaction Advice Service Requests.
     */
    //ToDo: Check this function if its working correctly.
    public function ivp_check_function()
    {
        if (isset($_GET['cart_id']) && !empty($_GET['cart_id'])) {
            // proceed to update order payment details:
            $cartIdExtract = explode("_", $_POST['tran_cartid']);
            $order_id = $cartIdExtract[0];
            
            $cart_id = get_post_meta($order_id, '_telr_cartid', true);
            if ($cart_id == $_GET['cart_id'] and $cart_id = $_POST['tran_cartid']) {
                try {
                    $order = new WC_Order($order_id);
                    //checking for default order status. If set, apply the default

                    $tranType = $_POST['tran_type'];
                    $tranStatus = $_POST['tran_authstatus'];
                    $tranRef = $_POST['tran_ref'];
                    $tranAmount = $_POST['tran_amount'];

                    if ($tranStatus == 'A') {
                        switch ($tranType) {
                            case '1':
                            case '4':
                            case '7':
                                $orderType = get_post_type($order_id);
                                
                                if($orderType == 'shop_subscription'){
                                    delete_post_meta($order_id, '_telr_auth_tranref');
                                    add_post_meta($order_id, '_telr_auth_tranref', $tranRef);
                                    $subscription_obj = new WC_Subscription($order_id);
                                    $subscription_obj->update_status('active');
                                }else{
                                    add_post_meta($order_id, '_telr_auth_tranref', $tranRef);
                                    if ( class_exists( 'WC_Subscriptions_Order' ) ) {
                                        $subscriptions_ids = wcs_get_subscriptions_for_order( $order_id );
                                        foreach( $subscriptions_ids as $subscription_id => $subscription_obj ){
                                            add_post_meta($subscription_id, '_telr_auth_tranref', $tranRef);
                                        }
                                    }
                                    $order->payment_complete();
                                    $default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
                                    if ($default_order_status != 'none') {
                                        $order->update_status($default_order_status);
                                    }
                                }
                                break;

                            case '2':
                            case '6':
                            case '8':
                                $newOrderStatus = 'cancelled';
                                $order->update_status($newOrderStatus);
                                break;

                            case '3':
                                if($tranAmount ==  $order->get_total()){
                                    $newOrderStatus = 'refunded';
                                    $order->update_status($newOrderStatus);    
                                }else{
                                    if(get_post_meta($order_id, 'is_plugin_refund')){						
                                        if(get_post_meta($order_id, 'is_plugin_refund', true) == '1'){											
                                            delete_post_meta($order_id, 'is_plugin_refund');
                                        }								
                                    }else{
                                        $refund = wc_create_refund(array('amount' => $tranAmount, 'reason' => 'Order Refunded From Telr Panel ', 'order_id' => $order_id, 'line_items' => array()));
                                    }
                                }
                                break;

                            default:
                                // No action defined
                                break;
                        }
                    }
                } catch (Exception $e) {
                    // Error Occurred While processing request.
                     die('Error Occurred While processing request');
                }
            } else {
                 die('Cart id mismatch');
            }
            exit;
        }
    }

    public function payment_requery($query) {
        $query = new WC_Order_Query( array(
            'limit' => 100,
            'status' => 'pending',
            'order' => 'ASC',
            'return' => 'ids',
            'payment_method' => 'wctelr',
            'date_created' => '>' . ( time() - 172800 )
        ) );
        $orders = $query->get_orders();
        foreach ($orders as $order_id) {
            $order = new WC_Order($order_id);
            $order_status = $this->check_order($order_id);
            echo "<br/>Order # " . $order_id . "New Order Status: " . $order_status ;
            if ($order_status == 'processing') { 
                $order->payment_complete();
                $default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
                if ($default_order_status != 'none') {
                    $order->update_status($default_order_status);
                }
            } else {
                $order->update_status($order_status);
            }
        }
        exit;
    }
    
    /*
    * show telr settings in woocommerce checkout settings
    */
    public function admin_options()
    {
        if (wc_gateway_telr()->admin->is_valid_for_use()) {
            $this->show_admin_options();
            return true;
        }
        
        wc_gateway_telr()->settings->__set('enabled', 'no');
        wc_gateway_telr()->settings->save();
        ?>
        <div class="inline error"><p><strong><?php _e('Gateway disabled', 'wctelr'); ?></strong>: <?php _e('Telr Payments does not support your store currency.', 'wctelr'); ?></p></div>
        <?php
    }
    
    public function show_admin_options()
    {
        $plugin_data = get_plugin_data(ABSPATH. 'wp-content/plugins/wc-telr/wc-telr.php');
        $plugin_version = $plugin_data['Version'];
 
    ?>
        <h3><?php _e('Telr ', 'wctelr'); ?><span><?php echo 'Version '.$plugin_version; ?></span> </h3>
        <div id="wc_get_started">
            <span class="main"><?php _e('Telr Hosted Payment Page', 'wctelr'); ?></span>
            <span><a href="https://www.telr.com/" target="_blank">Telr</a> <?php _e('are a PCI DSS Level 1 certified payment gateway. We guarantee that we will handle the storage, processing and transmission of your customer\'s cardholder data in a manner which meets or exceeds the highest standards in the industry.', 'wctelr'); ?></span>
            <span><br><b>NOTE: </b> You must enter your store ID and authentication key</span>
        </div>

        <table class="form-table">
        <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }
    
    public function api_request($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://secure.telr.com/gateway/order.json');
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $results = curl_exec($ch);
        curl_close($ch);
        $results = json_decode($results, true);
        return $results;
    }
    
    /*
    *Update order status on return
    *
    * @access public
    * @param (int)order id
    * @return void
    */
    public function update_order_status($order_id)
    {
        $order        = new WC_Order($order_id);
        $order_status = $this->default_order_status;
        
        //checking for default order status. If set, apply the default
        
        $saveCard = get_post_meta($order_id, '_telr_save_token', true);
        $order_status = $this->check_order($order_id, $saveCard);
        if ($order_status == 'processing') {
            $order->payment_complete();
        } else {
            $order->update_status($order_status);
        }
    }

    /**
    * Process the payment and return the result.
    *
    * @access public
    * @param (int)order id
    * @return array
    */
    public function process_payment($order_id)
    {
        return wc_gateway_telr()->checkout->process_payment($order_id);
    }

    
    /**
    * check order status.
    *
    * @access public
    * @param (int)order id
    * @return bool
    */
    public function check_order($order_id)
    {
        $order_ref = get_post_meta($order_id, '_telr_ref', true);

        $data = array(
            'ivp_method'  => "check",
            'ivp_store'   => $this->store_id ,
            'order_ref'   => $order_ref,
            'ivp_authkey' => $this->store_secret,
        );

        $response = wc_gateway_telr()->checkout->api_request($data);
        if (array_key_exists("order", $response)) {
            $order_status       = $response['order']['status']['code'];
            $transaction_status = $response['order']['transaction']['status'];
            $transaction_ref = $response['order']['transaction']['ref'];
            
            $payMethod = (isset($response['order']['paymethod'])) ? $response['order']['paymethod'] : "";
            $cardType = "";
            $cardLast4 = "";
            $cardCountry = "";
            $cardFirst6 = "";
            $cardExpiryMonth = "";
            $cardExpiryYear = "";
            
            if(isset($response['order']['card'])){
                $cardType = (isset($response['order']['card']['type'])) ? $response['order']['card']['type'] : "";
                $cardLast4 = (isset($response['order']['card']['last4'])) ? $response['order']['card']['last4'] : "";
                $cardCountry = (isset($response['order']['card']['country'])) ? $response['order']['card']['country'] : "";
                $cardFirst6 = (isset($response['order']['card']['first6'])) ? $response['order']['card']['first6'] : "";
                $cardExpiryMonth = (isset($response['order']['card']['expiry'])) ? $response['order']['card']['expiry']['month'] : "";
                $cardExpiryYear = (isset($response['order']['card']['expiry'])) ? $response['order']['card']['expiry']['year'] : "";
            }


            if (get_post_meta($order_id, '_telr_auth_tranref')) {
                delete_post_meta($order_id, '_telr_auth_tranref');
            }

            if ($transaction_status == 'A') {
                switch ($order_status) {
                    case '2':
                    case '3':

                        $orderType = get_post_type($order_id);

                        if($orderType == 'shop_subscription'){
                            delete_post_meta($order_id, '_telr_auth_tranref');
                            add_post_meta($order_id, '_telr_auth_tranref', $transaction_ref);
                            return "active";
                        }else{
                            add_post_meta($order_id, '_telr_auth_tranref', $transaction_ref);
                            
                            add_post_meta($order_id, '_telr_auth_pay_method', $payMethod);
                            add_post_meta($order_id, '_telr_auth_card_type', $cardType);
                            add_post_meta($order_id, '_telr_auth_card_last4', $cardLast4);
                            add_post_meta($order_id, '_telr_auth_card_country', $cardCountry);
                            add_post_meta($order_id, '_telr_auth_card_first6', $cardFirst6);
                            add_post_meta($order_id, '_telr_auth_card_expiry_month', $cardExpiryMonth);
                            add_post_meta($order_id, '_telr_auth_card_expiry_year', $cardExpiryYear);
                            
                            if ( class_exists( 'WC_Subscriptions_Order' ) ) {
                                $subscriptions_ids = wcs_get_subscriptions_for_order( $order_id );
                                foreach( $subscriptions_ids as $subscription_id => $subscription_obj ){
                                    add_post_meta($subscription_id, '_telr_auth_tranref', $transaction_ref);
                                }
                            }
                            return 'processing';
                        }
                        break;

                    case '-1':
                        if($orderType == 'shop_subscription'){
                            return "active";
                        }else{
                            return 'failed';
                        }
                        break;

                    case '-2':
                        if($orderType == 'shop_subscription'){
                            return "active";
                        }else{
                            return 'cancelled';
                        }
                        break;
                        
                    case '-3':
                        if($orderType == 'shop_subscription'){
                            return "active";
                        }else{
                            return 'cancelled';
                        }
                        break;

                    default:
                        // No action defined
                        break;
                }
            } else if ($transaction_status == 'H') {
                switch ($order_status) {
                    case '2':
                        return 'on-hold';
                        break;

                    default:
                        // No action defined
                        break;
                }
            } else{
                $txnMessage = (isset($response['order']['transaction']['message'])) ? $response['order']['transaction']['message'] : "Payment Failed";
                wc_add_notice( $txnMessage, 'error' );
                return 'failed';
            }
        }
        return 'pending';
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
        wc_gateway_telr()->checkout->receipt_page($order_id);
    }
    
    
    /**
     * initialize Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = wc_gateway_telr()->admin->init_form_fields();
    }

    public function payment_fields() {
        if($this->payment_mode_woocomm == '10'){
            $frameHeight = 320;
			$lange = wc_gateway_telr()->settings->__get('language');	
            //$iframeUrl = "https://secure.telr.com/jssdk/v2/token_frame.html?token=" . rand(1111,9999);
			$iframeUrl = "https://secure.telr.com/jssdk/v2/token_frame.html?token=" . rand(1111,9999). "&lang=".$lange;
            $test_mode  = (wc_gateway_telr()->settings->__get('testmode') == 'yes') ? 1 : 0;
            $savedCards = [];
            if (is_ssl() && is_user_logged_in()) {
                $currentUserId = get_current_user_id();
                $savedCards = $this->getTelrSavedCards($currentUserId);

                if(count($savedCards) > 0){
                    $frameHeight += 30;
                    $frameHeight += (count($savedCards) * 110);
                }
            }
        ?>
            <iframe id="telr_iframe" src="<?php echo $iframeUrl; ?>" style="width: 100%; height: <?php echo $frameHeight; ?>px; border: 0;margin-top: 20px;" sandbox="allow-forms allow-modals allow-popups-to-escape-sandbox allow-popups allow-scripts allow-top-navigation allow-same-origin"></iframe>
            <input id="telr_payment_token" type="hidden" name="telr_payment_token"/>
            <script type="text/javascript">
                $ = jQuery;
                var store_id = '<?php echo $this->store_id; ?>';
                var currency = '<?php echo get_woocommerce_currency(); ?>';
                var test_mode = '<?php echo $test_mode; ?>';
                var saved_cards = <?php echo json_encode($savedCards); ?>;
                window.telrInit = false;

                var telrMessage = {
                    "message_id": "init_telr_config",
                    "store_id": store_id,
                    "currency": currency,
                    "test_mode": test_mode,
                    "saved_cards": saved_cards
                }

                if (typeof window.addEventListener != 'undefined') {
                    window.addEventListener('message', function(e) {
                        var message = e.data;
                         if(message != ""){
                            var isJson = true;
                            try {
                                JSON.parse(str);
                            } catch (e) {
                                isJson = false;
                            }
                            if(isJson || (typeof message === 'object' && message !== null)){
                                var telrMessage = (typeof message === 'object') ? message : JSON.parse(message);
                                if(telrMessage.message_id != undefined){
                                    switch(telrMessage.message_id){
                                        case "return_telr_token": 
                                            var payment_token = telrMessage.payment_token;
                                            console.log("Telr Token Received: " + payment_token);
                                            $("#telr_payment_token").val(payment_token);
                                        break;
                                    }
                                }
                            }
                        }
                        
                    }, false);
                    
                } else if (typeof window.attachEvent != 'undefined') { // this part is for IE8
                    window.attachEvent('onmessage', function(e) {
                        var message = e.data;
                         if(message != ""){
                             try {
                                JSON.parse(str);
                            } catch (e) {
                                isJson = false;
                            }
                            if(isJson || (typeof message === 'object' && message !== null)){
                                var telrMessage = (typeof message === 'object') ? message : JSON.parse(message);
                                if(telrMessage.message_id != undefined){
                                    switch(telrMessage.message_id){
                                        case "return_telr_token": 
                                            var payment_token = telrMessage.payment_token;
                                            console.log("Telr Token Received: " + payment_token);
                                            $("#telr_payment_token").val(payment_token);
                                        break;
                                    }
                                }
                            }
                        }
                        
                    });
                }

                jQuery(document).ready(function(){
                    $('#telr_iframe').on('load', function(){
                        var initMessage = JSON.stringify(telrMessage);
                        setTimeout(function(){
                            if(!window.telrInit){
                                document.getElementById('telr_iframe').contentWindow.postMessage(initMessage,"*");
                                window.telrInit = true;
                            }
                        }, 1500);
                    });
                });
            </script>
            <?php
        }
    }

    protected function getTelrSavedCards($custId)
    {
        $telrCards = array();

        $storeId = $this->store_id;
        $authKey = $this->store_secret;
        $testMode  = (wc_gateway_telr()->settings->__get('testmode') == 'yes') ? 1 : 0;

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://secure.telr.com/gateway/savedcardslist.json",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => 0,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "api_storeid=" . $storeId . "&api_authkey=" . $authKey . "&api_testmode=" . $testMode . "&api_custref=" . $custId,
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded",
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if (!$err) {
            $resp = json_decode($response, true);
            if(isset($resp['SavedCardListResponse']) && $resp['SavedCardListResponse']['Code'] == 200){
                if(isset($resp['SavedCardListResponse']['data'])){
                    foreach ($resp['SavedCardListResponse']['data'] as $key => $row) {
                        $telrCards[] = array(
                            'txn_id' => $row['Transaction_ID'],
                            'name' => $row['Name']
                        );
                    }
                }
            }
        }

        return $telrCards;
    }
	 
	 /**
	 * Try to refund the payment for an order via the gateway.
	 *
	 * @since 3.0.0
	 * @throws Exception Throws exceptions when fail to refund, but returns WP_Error instead.
	 * @param WC_Order $order  Order instance.
	 * @param string   $amount Amount to refund.
	 * @param string   $reason Refund reason.
	 * @return bool|WP_Error
	 */
	 
	public function process_refund($order_id,$amount = null, $reason = ''){
		 		 
        $order = wc_get_order($order_id);

        // Check if the order exists
        if (!$order) {
            return false;
        }
		
        if($this->remote_store_secret == null || $this->remote_store_secret == ''){
            $order->add_order_note('Please check that the Remote API Authentication Key is not blank or incorrect.');
            return false;
        }
				
	    $url = "https://secure.telr.com/gateway/remote.xml";
		
	    $store_id        = $this->store_id;
        $store_secret    = $this->remote_store_secret;
        $testmode        = $this->testmode == 'yes' ? 1 : 0;
	    $refund_currency = $order->get_currency();
	    $order_ref = get_post_meta($order_id, '_telr_auth_tranref', true);
		
	    add_post_meta($order_id, 'is_plugin_refund', '1');
		
        $this->debug                = wc_gateway_telr()->settings->__get('debug');
        $this->order_status         = wc_gateway_telr()->settings->__get('order_status');
        $this->cart_desc            = wc_gateway_telr()->settings->__get('cart_desc');
        $this->payment_mode         = wc_gateway_telr()->settings->__get('payment_mode');
        $this->language             = wc_gateway_telr()->settings->__get('language');
        $this->default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
        $this->payment_mode_woocomm = wc_gateway_telr()->settings->__get('payment_mode');
		
        $xmlData = "<?xml version='1.0' encoding='UTF-8'?>
					<remote>
						<store>$store_id</store>
						<key>$store_secret</key>
						<tran>
							<type>refund</type>
							<class>ecom</class>
							<cartid>$order_id</cartid>
							<description>$reason</description>
							<test>$testmode</test>
							<currency>$refund_currency</currency>
							<amount>$amount</amount>
							<ref>$order_ref</ref>
						</tran>
					</remote>";
						
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/xml',
			'Content-Length: ' . strlen($xmlData)
		));
		
		$results = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
				
		if (!$err && $results !== false) {
			$xml = simplexml_load_string($results);
			$json = json_encode($xml);
			$responseArray = json_decode($json, true);				
			
			if ($responseArray !== null) {					
				if($responseArray['auth']['status'] == 'A'){
					$order->add_order_note('Refunded ' . $amount . ' for reason: ' . $reason);
					return true;
				}else{
					$order->add_order_note($responseArray['auth']['message']);					
				}
			}
		}
		
		$order->add_order_note('Refund failed');
		delete_post_meta($order_id, 'is_plugin_refund');
		return false;
	}
}