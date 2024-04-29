<?php
/**
 * Telr plugin admin handler
 */

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

include(dirname(__FILE__) . '/settings/settings-telr.php');

class WC_Gateway_Telr_Admin_Handler
{
    public function __construct()
    {
        $this->allowed_currencies   = include(dirname(__FILE__) . '/settings/supportd-currencies-telr.php');
        $this->testmode             = wc_gateway_telr()->settings->__get('testmode');
        $this->store_id             = wc_gateway_telr()->settings->__get('store_id');
        $this->remote_store_secret  = wc_gateway_telr()->settings->__get('remote_store_secret');
    }
    public function is_valid_for_use()
    {
        $currency_list = apply_filters('woocommerce_telr_supported_currencies', $this->allowed_currencies);
        return in_array(get_woocommerce_currency(), $currency_list);
    }
    public function init_form_fields()
    {
        return WC_Telr_Cards_Settings::core_settings();
    }
	
	public function init_apple_form_fields()
    {
        return WC_Telr_Cards_Settings::apple_pay_settings();
    }
	
	/**
	 * Generate HTML links for the settings page.	 
	 *
	 * @return void
	 */
	public static function generate_links() {

		$screen = ! empty( $_GET['screen'] ) ? sanitize_text_field( wp_unslash( $_GET['screen'] ) ) : '';
		if ( empty( $screen ) ) {
			$screen = ! empty( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		}
		?>		
		<div class="cko-admin-settings__links">
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wctelr' ) ); ?>"
                       class="<?php echo 'wctelr' === $screen ? 'current' : null; ?>">
                       <?php esc_html_e( 'Core Setting', 'wctelr' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_telr_apple_pay' ) ); ?>"
                       class="<?php echo 'wc_telr_apple_pay' === $screen ? 'current' : null; ?>">
                       <?php esc_html_e( 'Apple Pay', 'wctelr' ); ?></a></li>
			</ul>
		</div>
		<?php
	}
	
	public function create_capture_btn($order){
		
		$already_captured = 0;
		$already_releaseed = 0;
		if($order->get_meta('_transaction_captured_amt',true)){
			$already_captured = $order->get_meta('_transaction_captured_amt');
		}
		if($order->get_meta('_transaction_release_amt',true)){
			$already_releaseed = $order->get_meta('_transaction_release_amt');
		}
		
		$auth_remaining = $order->get_total() - ($already_captured + $already_releaseed);
		
		include(dirname(__FILE__) . "/template/display-capture-button.php");		
	}
	
	public function trigger_capture_payment(){
		global $wpdb;
		$table_name = $wpdb->prefix . 'telr_capture_transcations';
		
		if (isset($_POST['order_id'])) {			
			$order_id = $_POST['order_id'];
			$order    = new WC_Order($order_id);	
			$amount = ($_POST['amount'] == '') ? $order->get_total() :  $_POST['amount'];
			$release_amt = 0;
			
			// Check if the order exists
			if (!$order) {
				return false;
			}
			
			if($this->remote_store_secret == null || $this->remote_store_secret == ''){
				$order->add_order_note('Please check that the Remote API Authentication Key is not blank or incorrect.');
				return false;
			}
            $order->add_meta_data('is_plugin_captured','1');
            $order->save();
		
			if($order->get_meta('_telr_transaction_capture',true)){
				$order->delete_meta_data('_telr_transaction_capture',true);
				$order->save();
			}else{
				$order->add_meta_data('_telr_transaction_capture',true);
				$order->save();
				$results  =  $this->remote_xml_api_request('capture',$order_id,$amount,'Initiate capture request');
				if ($results !== false) {
					$xml = simplexml_load_string($results);
					$json = json_encode($xml);
					$responseArray = json_decode($json,true);
					
					if ($responseArray !== null) {					
						if($responseArray['auth']['status'] == 'A'){
							if($order->get_meta('_transaction_captured_amt',true)){
								$captured_amt = $order->get_meta('_transaction_captured_amt') + $amount;
								$order->update_meta_data('_transaction_captured_amt',$captured_amt);
								$order->save();
							}else{
								$captured_amt = $amount;
								$order->add_meta_data('_transaction_captured_amt',$amount);
								$order->save();
							}
							
							if($order->get_meta('_transaction_release_amt',true)){
								$release_amt = $order->get_meta('_transaction_release_amt');
							}
							
							$total_process_amt = $order->get_meta('_transaction_captured_amt') + $release_amt;
							
							if($total_process_amt == $order->get_total()){
								$order->update_meta_data('_telr_auth_tranref',$responseArray['auth']['tranref']);
								$order->add_meta_data('_telr_transaction_completed',true);
								$order->update_status('processing');
								$order->save();
								$order->payment_complete();
							}		
							$wpdb->insert( 
								$table_name, 
								array( 
									'order_id' => $order_id, 
									'capture_amt' => $amount, 
									'capture_date' => current_time('mysql'),
									'tran_ref' => $responseArray['auth']['tranref'], 
                                    'fully_refunded' => 0, 									
								) 
							);	
							$order->add_order_note('A payment of ' . $amount . ' was successfully captured using Telr Payments');							
							return true;
						}else{						
							$order->add_order_note($responseArray['auth']['message']);					
						}
					}
				}	
				$order->add_order_note('Capture failed');				
			}
		}		
		return false;
	}
	
	public function trigger_release_payment(){
		
		if (isset($_POST['order_id'])) {			
			$order_id = $_POST['order_id'];
			$order    = new WC_Order($order_id);	
			$amount = ($_POST['amount'] == '') ? $order->get_total() :  $_POST['amount'];
			$captured_amt = 0;		 
			
			// Check if the order exists
			if (!$order) {
				return false;
			}
			
			if($this->remote_store_secret == null || $this->remote_store_secret == ''){
				$order->add_order_note('Please check that the Remote API Authentication Key is not blank or incorrect.');
				return false;
			}
			
			$order->add_meta_data('is_plugin_release','1');
            $order->save();
			
			//if($order->get_meta('_telr_transaction_release',true)){
			//	$order->delete_meta_data('_telr_transaction_release',true);
			//	$order->save();
			//}else{
			//	$order->add_meta_data('_telr_transaction_release',true);
			//	$order->save();
				$results  =  $this->remote_xml_api_request('release',$order_id,$amount,'Initiate release payment request');
				if ($results !== false) {
					$xml = simplexml_load_string($results);
					$json = json_encode($xml);
					$responseArray = json_decode($json,true);
					
					if ($responseArray !== null) {					
						if($responseArray['auth']['status'] == 'A'){
							if($order->get_meta('_transaction_release_amt',true)){
								$captured_amt = $order->get_meta('_transaction_release_amt') + $amount;
								$order->update_meta_data('_transaction_release_amt',$captured_amt);
								$order->save();
							}else{
								$order->add_meta_data('_transaction_release_amt',$amount);
								$order->save();
							}
							if($order->get_meta('_transaction_captured_amt',true)){
								$captured_amt = $order->get_meta('_transaction_captured_amt');
							}
							
							$total_process_amt = $order->get_meta('_transaction_release_amt') + $captured_amt;
							
							if($order->get_meta('_transaction_release_amt') == $order->get_total()){
								$order->update_meta_data('_telr_auth_tranref',$responseArray['auth']['tranref']);
								$order->add_meta_data('_telr_transaction_completed',true);
								$order->update_status('cancelled');
								$order->save();
								$order->payment_complete();
							}elseif($total_process_amt == $order->get_total()){
								$order->add_meta_data('_telr_transaction_completed',true);
								$order->update_status('processing');
								$order->save();
								$order->payment_complete();
							}
							
							$order->add_order_note('A payment of ' . $amount . ' was successfully released using Telr Payments');							
							return true;
						}else{						
							$order->add_order_note($responseArray['auth']['message']);					
						}
					}
				}	
				$order->add_order_note('Released failed');				
			//}
		}
		return false;
	}
	
	public function trigger_refund($order_id,$amount,$reason,$tranRef = null){
		
		$order = wc_get_order($order_id);

        // Check if the order exists
        if (!$order) {
            return false;
        }
		
        if($this->remote_store_secret == null || $this->remote_store_secret == ''){
            $order->add_order_note('Please check that the Remote API Authentication Key is not blank or incorrect.');
            return false;
        }
		
        $order->add_meta_data('is_plugin_refund','1');
        $order->save();		
        $results  =  $this->remote_xml_api_request('refund',$order_id,$amount,$reason,$tranRef);					
		if ($results !== false) {
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
	
	public function remote_xml_api_request($tranType,$orderId,$amount,$reason,$tranRef = null){
		
        $order = wc_get_order($orderId);
        $url   = "https://secure.telr.com/gateway/remote.xml";
		
        $storeId        = $this->store_id;
        $storeSecret    = $this->remote_store_secret;
        $testmode       = $this->testmode == 'yes' ? 1 : 0;
	    $refundCurrency = $order->get_currency();
	    $orderRef       = $tranRef != null ? $tranRef : $order->get_meta('_telr_auth_tranref',true);		
		
        $xmlData = "<?xml version='1.0' encoding='UTF-8'?>
					<remote>
						<store>$storeId</store>
						<key>$storeSecret</key>
						<tran>
							<type>$tranType</type>
							<class>ecom</class>
							<cartid>$orderId</cartid>
							<description>$reason</description>
							<test>$testmode</test>
							<currency>$refundCurrency</currency>
							<amount>$amount</amount>
							<ref>$orderRef</ref>
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
		return $results;		
	}
}
