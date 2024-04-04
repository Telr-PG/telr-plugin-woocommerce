<?php
/**
 * Telr Payment gateway .
 */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Telr_Apple_Payment_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->has_fields             = false;  // No additional fields in checkout page
        $this->method_title           = __('Telr', 'wctelr');
        $this->method_description     = __('Telr Checkout', 'wctelr');
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
        $this->title                = wc_gateway_telr()->settings->__get('apple_pay_title');

        $this->description          = wc_gateway_telr()->settings->__get('apple_pay_description');
		
        $this->store_id             = wc_gateway_telr()->settings->__get('store_id');
        $this->subs_method          = wc_gateway_telr()->settings->__get('subscription_method');
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

        $this->enable_apple         = wc_gateway_telr()->settings->__get('enable_apple');
        $this->apple_mercahnt_id    = wc_gateway_telr()->settings->__get('apple_mercahnt_id');
        $this->apple_certificate    = wc_gateway_telr()->settings->__get('apple_certificate');
        $this->apple_key            = wc_gateway_telr()->settings->__get('apple_key');
        $this->domain               = wc_gateway_telr()->settings->__get('domain');
        $this->display_name         = wc_gateway_telr()->settings->__get('display_name');
        $this->apple_type           = wc_gateway_telr()->settings->__get('apple_type');
        $this->apple_theme          = wc_gateway_telr()->settings->__get('apple_theme');
        $this->enable_mada          = wc_gateway_telr()->settings->__get('enable_mada');
        $this->enable_amex          = wc_gateway_telr()->settings->__get('enable_amex');
        
        //actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page'));

        add_action( 'woocommerce_api_telr-requery', array( $this, 'payment_requery' ) );
        add_action( 'woocommerce_api_apple-ivp-callback', array( $this, 'ivp_check_function' ) );
        add_action( 'woocommerce_api_wc_telr_session', [ $this, 'applepay_sesion' ] );
        add_action( 'woocommerce_api_wc_telr_generate_token', [ $this, 'applepay_token' ] );
        add_action( 'wp_enqueue_scripts', array( $this, 'wpcheckout_dequeue_and_then_enqueue'), 11 );

        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
        }
    }

    function wpcheckout_dequeue_and_then_enqueue() {
        global $wp_scripts;
        $wp_scripts->registered['wc-checkout']->src = plugin_dir_url( __FILE__ ) . 'js/checkout.min.js';
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
        wc_gateway_telr()->settings->__set('enable_apple','no');
        wc_gateway_telr()->settings->save();
        ?>
        <div class="inline error"><p><strong><?php _e('Gateway disabled', 'wctelr'); ?></strong>: <?php _e('Telr Payments does not support your store currency.', 'wctelr'); ?></p></div>
        <?php
    }
    
    public function show_admin_options()
    {
        $plugin_data = get_plugin_data(ABSPATH. 'wp-content/plugins/wc-telr/wc-telr.php');
        $plugin_version = $plugin_data['Version'];
		wc_gateway_telr()->admin->generate_links();
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
		$order    = new WC_Order($order_id);
		$applePayData = $_POST;
		$results   = wc_gateway_telr()->checkout->generate_applepay_request($order,$applePayData);
		$objTransaction='';
		$objError='';
		if (isset($results['transaction'])) { $objTransaction = $results['transaction']; }
		if (isset($results['error'])) { $objError = $results['error']; }
		if (is_array($objError)) {
			$error_message = "Unable to process your payment. Error: " . $objError['message'] . ' ' . $objError['note'] . ' ' . $objError['details']; 
			wc_add_notice($error_message, 'error');
			return array(
                'result'    => 'failure',
                'redirect'  => false,            
            );
		}else{
			$transactionStatus = $objTransaction['status'];
			$orderType = OrderUtil::get_order_type($order_id);										 
			if ($transactionStatus == 'A') {				
				if ($order->get_meta('_telr_auth_tranref',true)) {
					$order->delete_meta_data('_telr_auth_tranref',true);
				}
				
				if($orderType == 'shop_subscription'){
					$order->add_meta_data('_telr_auth_tranref',$objTransaction['ref']);
					$order->save();
					$subscription_obj = new WC_Subscription($order_id);
					$subscription_obj->update_status('active');
					$return_url = home_url() . "/my-account/subscriptions";
					return array(
						'result'   => 'success',
						'redirect' => $return_url
					);
				}else{
					$order->add_meta_data('_telr_auth_tranref',$objTransaction['ref']);
					$order->save();
					
					if ( class_exists( 'WC_Subscriptions_Order' ) ) {
						$subscriptions_ids = wcs_get_subscriptions_for_order($order_id);
						foreach( $subscriptions_ids as $subscription_id => $subscription_obj ){
							$subscription_obj->add_meta_data( '_telr_auth_tranref', $transaction_ref );
							$subscription_obj->save();
						}
					}					
					$order->payment_complete();
					$default_order_status = wc_gateway_telr()->settings->__get('default_order_status');
					if ($default_order_status != 'none') {
						$order->update_status($default_order_status);
					}	
					WC()->cart->empty_cart();
					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order )
					);
					
				}				
			}else{
				wc_add_notice('Invalid Request!', 'error');
				return array(
					'result'    => 'failure',
					'redirect'  => false,            
				);
			}
		}		
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
        $this->form_fields = wc_gateway_telr()->admin->init_apple_form_fields();
    }

    public function payment_fields() {
        global $woocommerce;
        $subscriptionProduct = false;
        $subscriptionProductCount = 0;
        $cart_desc = trim(wc_gateway_telr()->settings->__get('cart_desc'));
        $items = $woocommerce->cart->get_cart();
        foreach($items as $item => $values) {
            $productId = $values['data']->get_id();
            $_product =  wc_get_product($productId);
            $isSubProduct = get_post_meta($productId, '_subscription_telr', true);			
            if ( class_exists( 'WC_Subscriptions_Order' ) && $this->subs_method == 'woocomm' && 
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
            }elseif($this->subs_method == 'telr' && $isSubProduct == 'yes'){
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

        $chosen_methods     = wc_get_chosen_shipping_method_ids();
        $chosen_shipping    = $chosen_methods[0] ?? '';
        $shipping_amount    = WC()->cart->get_shipping_total();
        $checkout_fields    = json_encode($woocommerce->checkout->checkout_fields, JSON_HEX_APOS);
        $session_url        = str_replace('https:', 'https:', add_query_arg( 'wc-api', 'wc_telr_session', home_url( '/' ) ) );
        $generate_token_url = str_replace('https:', 'https:', add_query_arg( 'wc-api', 'wc_telr_generate_token', home_url( '/' ) ) );
        $mada_enabled       = isset($this->enable_mada) && ('yes' === $this->enable_mada);
        $amex_enabled       = isset($this->enable_amex) && ('yes' === $this->enable_amex);

        if ( ! empty($this->description) ) {
            echo  $this->description;
        }

        // get country of current user.
        $country_code          = WC()->customer->get_billing_country();
        $supported_networks    = ['masterCard','visa'];
        $merchant_capabilities = [ 'supports3DS', 'supportsCredit', 'supportsDebit' ];

        if ( $mada_enabled ) {
            array_push( $supported_networks, 'mada' );
            $country_code = 'SA';
        }
		
        if ( $amex_enabled ) {
            array_push( $supported_networks, 'amex' );
        }

        ?>

        <!-- Input needed to sent the card token -->
        <input type="hidden" id="telr-apple-card-token" name="telr-apple-card-token" value="" />
        <input type="hidden" id="subscriptionProductCount" value="<?php echo $subscriptionProductCount; ?>" />																								

        <!-- ApplePay warnings -->
        <p style="display:none" id="telr_applePay_not_actived">ApplePay is possible on this browser, but not currently activated.</p>
        <p style="display:none" id="telr_applePay_not_possible">ApplePay is not available on this browser</p>
	<p class="telr_applePay_error"></p>
	<script type="text/javascript">
        var paymentData = {};
        var applePayOptionSelector = 'li.payment_method_wc_telr_apple_pay';
        var applePayButtonId = 'telr_applePay';           
        var applePayNotActivated = document.getElementById('telr_applePay_not_actived');
        var applePayNotPossible = document.getElementById('telr_applePay_not_possible');
        // Initially hide the Apple Pay as a payment option.
        hideAppleApplePayOption();
        // If Apple Pay is available as a payment option, and enabled on the checkout page, un-hide the payment option.
        if (window.ApplePaySession) {
            var canMakePayments = ApplePaySession.canMakePayments("<?php echo $this->apple_mercahnt_id; ?>");
            if ( canMakePayments ) {
                setTimeout( function() {
                    showAppleApplePayOption();
                }, 500 );
			} else {
				displayApplePayNotPossible();
			}
		} else {
			displayApplePayNotPossible();
		}
        // Display the button and remove the default place order.
        checkoutInitialiseApplePay = function () {
            jQuery('.place-order').append('<div id="' + applePayButtonId + '" class="apple-pay-button  ' + "<?php echo $this->apple_type; ?>" + " " + "<?php echo $this->apple_theme; ?>"  + '" style="float:right"></div>');
            jQuery('#telr_applePay').hide();
        };
        // Listen for when the Apple Pay button is pressed.
        jQuery( document ).off( 'click', '#' + applePayButtonId );
        jQuery( document ).on( 'click', '#' + applePayButtonId, function () {
            var checkoutFields = '<?php echo $checkout_fields; ?>';
            var result = isValidFormField(checkoutFields);
            if(result){
                var applePaySession = new ApplePaySession(3, getApplePayRequestPayload());
                handleApplePayEvents(applePaySession);
                applePaySession.begin();
            }
        });
		/**
		 * Get the configuration needed to initialise the Apple Pay session.
		 *
		 * @param {function} callback
		 */
		function getApplePayRequestPayload() {
			var networksSupported = <?php echo json_encode( $supported_networks ); ?>;
			var merchantCapabilities = <?php echo json_encode( $merchant_capabilities ); ?>;
			return {
				currencyCode: "<?php echo get_woocommerce_currency(); ?>",
				countryCode: "<?php echo $country_code; ?>",
				merchantCapabilities: merchantCapabilities,
				supportedNetworks: networksSupported,
				<?php if($subscriptionProduct == true){ ?>
				recurringPaymentRequest : {
					paymentDescription :"<?php echo $cart_desc; ?>",
					regularBilling : {
						label:'',
						amount:<?php echo $amount; ?>,
						recurringPaymentStartDate: new Date('<?php echo date('Y-m-d'); ?>'),
						recurringPaymentIntervalUnit:"<?php echo $recurrIntUnit; ?>",
						recurringPaymentIntervalCount:<?php echo $recurrInterval; ?>,
						paymentTiming:'recurring'
					},
					managementURL:"<?php echo get_site_url(); ?>",
				},
				<?php } ?>
				total: {
					label: window.location.host,
					amount: "<?php echo $woocommerce->cart->total; ?>",
					type: 'final'
				}
			}
		}
        /**
        * Handle Apple Pay events.
        */
        function handleApplePayEvents(session) {
            /**
            * An event handler that is called when the payment sheet is displayed.
            *
            * @param {object} event - The event contains the validationURL.
            */
            session.onvalidatemerchant = function (event) {
                performAppleUrlValidation(event.validationURL, function (merchantSession) {
                    session.completeMerchantValidation(merchantSession);
                });
            };
            /**
            * An event handler that is called when a new payment method is selected.
            *
            * @param {object} event - The event contains the payment method selected.
            */
            session.onpaymentmethodselected = function (event) {
                // base on the card selected the total can be change, if for example you.
                // plan to charge a fee for credit cards for example.
                var newTotal = {
                    type: 'final',
                    label: window.location.host,
                    amount: "<?php echo $woocommerce->cart->total; ?>",
                };
                var newLineItems = [
                    {
                        type: 'final',
                        label: 'Subtotal',
                        amount: "<?php echo $woocommerce->cart->subtotal; ?>"
                    },
                    {
                        type: 'final',
                        label: 'Shipping - ' + "<?php echo $chosen_shipping; ?>",
                        amount: "<?php echo $shipping_amount; ?>"
                    }
                ];
                session.completePaymentMethodSelection(newTotal, newLineItems);
            };
            /**
            * An event handler that is called when the user has authorized the Apple Pay payment
            *  with Touch ID, Face ID, or passcode.
		    */		
		    session.onpaymentauthorized = function (event) {			
                var promise = sendPaymentToken(event.payment.token);
                promise.then(function (success) {
                    var status;
                    if (success) {
				        status = ApplePaySession.STATUS_SUCCESS;				
                        sendPaymentToTelr(paymentData);
                    } else {
                        status = ApplePaySession.STATUS_FAILURE;
                    }
                    session.completePayment(status);
                }).catch(function (validationErr) {
                    jQuery(".telr_applePay_error").text('Unable to process Apple Pay payment. Please reload the page and try again. Error Code: E002');
                    setTimeout(function(){
                        jQuery(".telr_applePay_error").text('');
                    }, 5000);
                    session.abort();
                });
            }				
            /**
            * An event handler that is automatically called when the payment UI is dismissed.
            */
            session.oncancel = function (event) {
            // popup dismissed
            };
	    }

		function sendPaymentToken(paymentToken)
		{			
			return new Promise(function (resolve, reject) {
				paymentData = paymentToken;					
				resolve(true);
			}).catch(function (validationErr) {
				jQuery(".telr_applePay_error").text('Unable to process Apple Pay payment. Please reload the page and try again. Error Code: E003');
				setTimeout(function(){
					jQuery(".telr_applePay_error").text('');
				}, 5000);
				session.abort();
			});
		}
		
		function sendPaymentToTelr(data)
		{
			var applePayVersion       =  data.paymentData.version;
			var applePayData          =  data.paymentData.data;
			var applePaySignature     =  data.paymentData.signature;
			var applePayTransactionId =  data.paymentData.header.transactionId;
			var applePayType          =  data.paymentMethod.type;
			var applePayNetwork       =  data.paymentMethod.network;
			var applePayDisplayName   =  data.paymentMethod.displayName;
			var applePayKeyHash       =  data.paymentData.header.publicKeyHash;
			var applePayKey           =  data.paymentData.header.ephemeralPublicKey;
			var applePayTransactionIdentifier = data.transactionIdentifier;

			jQuery('<input>').attr({type: 'hidden',	id: 'applePayVersion', name: 'applePayVersion',	value: applePayVersion}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayData', name: 'applePayData',	value: applePayData}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePaySignature', name: 'applePaySignature',	value: applePaySignature}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayTransactionId', name: 'applePayTransactionId',	value: applePayTransactionId}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayType', name: 'applePayType',	value: applePayType}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayNetwork', name: 'applePayNetwork',	value: applePayNetwork}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayDisplayName', name: 'applePayDisplayName',	value: applePayDisplayName}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayKeyHash', name: 'applePayKeyHash',	value: applePayKeyHash}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayKey', name: 'applePayKey',	value: applePayKey}).appendTo(jQuery('.woocommerce-checkout'));
			jQuery('<input>').attr({type: 'hidden',	id: 'applePayTransactionIdentifier', name: 'applePayTransactionIdentifier',	value: applePayTransactionIdentifier}).appendTo(jQuery('.woocommerce-checkout'));
			
			jQuery('#place_order').prop("disabled",false);
			jQuery('#place_order').trigger('click');
		}

		/**
		 * Perform the session validation.
		 *
		 * @param {string} valURL validation URL from Apple
		 * @param {function} callback
		 */
		function performAppleUrlValidation(valURL, callback) {
			jQuery.ajax({
				type: 'POST',
				url: "<?php echo $session_url; ?>",
				data: {
					url: valURL,
				},
				success: function (outcome) {
				    var data = JSON.parse(outcome);
					callback(data);
				}
			});
		}            
		/**
		* This will display the Apple Pay not activated message.
		*/
		function displayApplePayNotActivated() {
			applePayNotActivated.style.display = '';
		}
		/**
		* This will display the Apple Pay not possible message.
		*/
		function displayApplePayNotPossible() {
			applePayNotPossible.style.display = '';
		}
		/**
		* Hide the Apple Pay payment option from the checkout page.
		*/
		function hideAppleApplePayOption() {
			jQuery(applePayOptionSelector).hide();
		}
		/**
		* Show the Apple Pay payment option on the checkout page.
		*/
		function showAppleApplePayOption() {
			jQuery( applePayOptionSelector ).show();			
			if ( jQuery( '.payment_method_wc_telr_apple_pay' ).is( ':visible' ) ) {
				if ( jQuery( '#payment_method_wc_telr_apple_pay' ).is( ':checked' ) ) { 
					jQuery( '#place_order' ).hide();
					jQuery( '#telr_applePay' ).show();
				} else {
					jQuery( '#place_order' ).show();
					jQuery( '#telr_applePay' ).hide();
				}				
			} else {
				jQuery( '#place_order' ).prop( "disabled", false );
			}			
			// On payment radio button click.
			jQuery( "input[name='payment_method']" ).on( 'click', function () {
					if ( this.value == 'wc_telr_apple_pay' ) {
							setTimeout(function(){
								jQuery( '#place_order' ).hide();
							}, 100);
							jQuery( '#telr_applePay' ).show();
					} else {
							jQuery( '#place_order' ).show();
							jQuery( '#telr_applePay' ).hide();
					}
			} );
		}
		// Initialise apple pay when page is ready
		jQuery( document ).ready(function() {
			checkoutInitialiseApplePay();
		});
		// Validate checkout form before submitting order
		function isValidFormField(fieldList) {
			var result = {error: false, messages: []};
			var fields = JSON.parse(fieldList);
			if(jQuery('#subscriptionProductCount').val() > 1){
				result.error = true;
				result.messages.push({target: false, message : 'Only 1 Repeat Billing product is allowed per transaction.'});
			}
			if(jQuery('#terms').length === 1 && jQuery('#terms:checked').length === 0){
				result.error = true;
				result.messages.push({target: 'terms', message : 'You must accept our Terms & Conditions.'});
			}
			if (fields) {
				jQuery.each(fields, function(group, groupValue) {
					if (group === 'shipping' && jQuery('#ship-to-different-address-checkbox:checked').length === 0) {
						return true;
					}
					jQuery.each(groupValue, function(name, value ) {
						if (!value.hasOwnProperty('required')) {
							return true;
						}
						if (name === 'account_password' && jQuery('#createaccount:checked').length === 0) {
							return true;
						}
						var inputValue = jQuery('#' + name).length > 0 && jQuery('#' + name).val().length > 0 ? jQuery('#' + name).val() : '';
						if (value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).val().length === 0) {
							result.error = true;
							result.messages.push({target: name, message : value.label + ' is a required field.'});
						}
						if (value.hasOwnProperty('type')) {
							switch (value.type) {
								case 'email':
									var reg     = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
									var correct = reg.test(inputValue);

									if (!correct) {
										result.error = true;
										result.messages.push({target: name, message : value.label + ' is not correct email.'});
									}

									break;
								case 'tel':
									var tel      = inputValue;
									var filtered = tel.replace(/[\s\#0-9_\-\+\(\)]/g, '').trim();

									if (filtered.length > 0) {
										result.error = true;
										result.messages.push({target: name, message : value.label + ' is not correct phone number.'});
									}

									break;
							}
						}
					});
				});
			} else {
				result.error = true;
				result.messages.push({target: false, message : 'Empty form data.'});
			}
			if (!result.error) {
				return true;
			}
			jQuery('.woocommerce-error, .woocommerce-message').remove();
			jQuery.each(result.messages, function(index, value) {
				jQuery('form.checkout').prepend('<div class="woocommerce-error">' + value.message + '</div>');
			});
			jQuery('html, body').animate({
				scrollTop: (jQuery('form.checkout').offset().top - 100 )
			}, 1000 );
			jQuery(document.body).trigger('checkout_error');
			return false;
		}
    </script>    
    <?php

    }


        /**
     * Apple pay session.
     *
     * @return void
     */
    public function applepay_sesion() {

        $url          = $_POST['url'];
        $domain       = $this->domain;
        $display_name = $this->display_name;
        $merchant_id     = $this->apple_mercahnt_id;
        $certificate     = $this->apple_certificate;
        $certificate_key = $this->apple_key;

        if (
            'https' === parse_url( $url, PHP_URL_SCHEME ) &&
            substr( parse_url( $url, PHP_URL_HOST ), - 10 ) === '.apple.com'
        ) {
            $ch = curl_init();
            $data =
                '{
                  "merchantIdentifier":"' . $merchant_id . '",
                  "domainName":"' . $domain . '",
                  "displayName":"' . $display_name . '"
              }';
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_SSLCERT, $certificate );
            curl_setopt( $ch, CURLOPT_SSLKEY, $certificate_key );
            curl_setopt( $ch, CURLOPT_POST, 1 );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

            if ( curl_exec( $ch ) === false ) {
                echo '{"curlError":"' . curl_error( $ch ) . '"}';
            }
            curl_close( $ch );
            exit();
        }
    }

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
        $order_ref = $order->get_meta('_telr_auth_tranref',true);
        $order->add_order_note($order_ref);
        $order->add_meta_data('is_plugin_refund','1');
        $order->save();

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
        $order->delete_meta_data('is_plugin_refund',true);
        $order->save();

	    return false;
    }
	 
}
