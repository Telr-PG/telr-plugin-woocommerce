<?php
/*
 * Telr plugin for woocommercee
*/

//directory access forbidden
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Telr_Plugin
{
    const DEPENDENCIES_UNSATISFIED  = 1;

    public function __construct($file)
    {
        $this->file = $file;
        $this->plugin_path   = trailingslashit(plugin_dir_path($this->file));
        $this->plugin_url    = trailingslashit(plugin_dir_url($this->file));
        $this->plugin_url    = trailingslashit(plugin_dir_url($this->file));
        $this->includes_path = $this->plugin_path . trailingslashit('includes');
    }

    /**
     * Maybe run the plugin.
    */
    public function maybe_run()
    {
        register_activation_hook($this->file, array($this, 'activate'));
        register_deactivation_hook($this->file, array($this, 'deactivate'));
        //add_action( 'admin_menu', array( $this, 'wpa_add_menu' ));
        add_action('plugins_loaded', array($this, 'bootstrap'));
        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'plugin_action_links'));
        add_action('init', array($this, 'init_ssl_check'));

        $telrSettings = (array) get_option('woocommerce_wctelr_settings', array());
        if($telrSettings['subscription_method'] == 'telr'){
            add_filter( 'product_type_options', array($this, 'subscription_product_option') );
            add_action( 'woocommerce_process_product_meta_simple', array($this, 'save_telr_subscription_option_fields')  );
            add_action( 'woocommerce_process_product_meta_variable', array($this, 'save_telr_subscription_option_fields')  );
            add_filter( 'woocommerce_product_data_panels', array($this, 'recurring_options_product_tab_content') ); 
            add_action('admin_enqueue_scripts', array($this, 'admin_load_js'));
            add_action( 'woocommerce_process_product_meta', array($this, 'woo_add_custom_general_fields_save') );


        }   
    }

    public function bootstrap()
    {
        try {
            $this->_check_dependencies();
            $this->_run();
            delete_option('wc_gateway_telr_bootstrap_warning_message');
        } catch (Exception $e) {
            if (in_array($e->getCode(), array(self::DEPENDENCIES_UNSATISFIED))) {

                update_option('wc_gateway_telr_bootstrap_warning_message', $e->getMessage());
            }
            add_action('admin_notices', array($this, 'show_bootstrap_warning'));
        }
    }

    protected function _check_dependencies()
    {
        if (!function_exists('WC')) {
            throw new Exception(__('Telr Secure payments for WooCommerce requires WooCommerce to be activated', 'telr-for-woocommerce'), self::DEPENDENCIES_UNSATISFIED);
        }

        if (version_compare(WC()->version, '3.0', '<')) {
            throw new Exception(__('Telr Secure payments for WooCommerce requires WooCommerce version 3.0 or greater', 'telr-for-woocommerce'), self::DEPENDENCIES_UNSATISFIED);
        }

        if (!function_exists('curl_init')) {
            throw new Exception(__('Telr Secure payments for WooCommerce requires cURL to be installed on your server', 'telr-for-woocommerce'), self::DEPENDENCIES_UNSATISFIED);
        }
    }

    function subscription_product_option( $product_type_options ) {
        global $post;
        $isSubscriptionOn = get_post_meta($post->ID, '_subscription_telr', true);

        $product_type_options['Recurring Enabled'] = array(
            'id'            => '_subscription_telr',
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label'         => __( 'Recurring Enabled', 'woocommerce' ),
            'description'   => __( '', 'woocommerce' ),
            'default'       => !empty($isSubscriptionOn) ? $isSubscriptionOn : 'no'


        );

        return $product_type_options;
    }

    
    function save_telr_subscription_option_fields( $post_id ) {
        $is_telr_subscription = isset( $_POST['_subscription_telr'] ) ? 'yes' : 'no';
        if( !empty( $is_telr_subscription ) )
            update_post_meta( $post_id, '_subscription_telr', esc_attr( $is_telr_subscription ) );
        else {
            update_post_meta( $post_id, '_subscription_telr',  'no' );
        }
    }

    /**
     * Contents of the recurring options product tab.
     */
    function recurring_options_product_tab_content() {
        global $post;

        // Note the 'id' attribute needs to match the 'target' parameter set above
        ?>
        <div id='recurring_options' class='panel woocommerce_options_panel <?php echo $isSubscriptionOn; ?>' > 
        <?php
            ?><div class='options_group'><?php

                $continued[] = 'Continued';
                for ($i=1; $i < 101; $i++) { 
                    $continued[] = $i;
                }

                woocommerce_wp_select(
                    array(
                        'id' => '_continued_of', 
                        'options' => $continued, 
                        'label' => __('Payment details :', 'woothemes'),
                        'description' => __('', 'woothemes')
                    )
                );
                woocommerce_wp_text_input( array(
                    'id'                => '_payment_of',
                    'label'             => __( 'payments of', 'woocommerce' ),
                    'desc_tip'          => 'true',
                    'type'              => 'text',
                ) );

                for ($i=1; $i < 13; $i++) { 
                    $daysArray[$i] = $i;
                }

                woocommerce_wp_select(
                    array(
                        'id' => '_every_number_of', 
                        'options' => $daysArray, 
                        'label' => __('every', 'woothemes'),
                        'description' => __('', 'woothemes')
                    )
                );

                woocommerce_wp_select(
                    array(
                        'id' => '_for_number_of', 
                        'options' => array('W'=>'Week(s)','M'=>'Month(s)'), 
                    )
                );

                woocommerce_wp_text_input( array(
                    'id'                => '_final_payment_of',
                    'label'             => __( 'with final payment of ', 'woocommerce' ),
                    'desc_tip'          => 'true',
                    'type'              => 'text',
                ) );

                woocommerce_wp_select(
                    array(
                        'id' => '_payment_day', 
                        'options' => array(
                                        'same_day_of_init'=>'Same day of each month as the initial payment',
                                        'start_of_each_month'=>'Start of each month',
                                        'end_of_each_month'=>'End of each month'
                                    ), 
                        'label' => __('Payment day : ', 'woothemes'),
                        'description' => __('', 'woothemes')
                    )
                );
            ?></div>

        </div><?php
    }

    function admin_load_js(){
        wp_enqueue_script( 'custom_js', plugins_url( '../assets/js/custom.js', __FILE__ ), array('jquery') );
    }

    // Save Fields
    function woo_add_custom_general_fields_save( $post_id ){

    // Select
        $woocommerce_select = $_POST['_continued_of'];
        if( !empty( $woocommerce_select ) )
            update_post_meta( $post_id, '_continued_of', esc_attr( $woocommerce_select ) );
        else {
            update_post_meta( $post_id, '_continued_of',  '0' );
        }

        $woocommerce_select = $_POST['_payment_of'];
        if( !empty( $woocommerce_select ) )
            update_post_meta( $post_id, '_payment_of', esc_attr( $woocommerce_select ) );
        else {
            update_post_meta( $post_id, '_payment_of',  '0' );
        }

        $woocommerce_select = $_POST['_every_number_of'];
        if( !empty( $woocommerce_select ) )
            update_post_meta( $post_id, '_every_number_of', esc_attr( $woocommerce_select ) );
        else {
            update_post_meta( $post_id, '_every_number_of',  '0' );
        }

        $woocommerce_select = $_POST['_for_number_of'];
        if( !empty( $woocommerce_select ) )
            update_post_meta( $post_id, '_for_number_of', esc_attr( $woocommerce_select ) );
        else {
            update_post_meta( $post_id, '_for_number_of',  '0' );
        }

        $woocommerce_select = $_POST['_final_payment_of'];
        if( !empty( $woocommerce_select ) )
            update_post_meta( $post_id, '_final_payment_of', esc_attr( $woocommerce_select ) );
        else {
            update_post_meta( $post_id, '_final_payment_of',  '0' );
        }

        $woocommerce_select = $_POST['_payment_day'];
        if( !empty( $woocommerce_select ) )
            update_post_meta( $post_id, '_payment_day', esc_attr( $woocommerce_select ) );
        else {
            update_post_meta( $post_id, '_payment_day',  '' );
        }
    }

    public function telr_process_cont_auth() {
        global $wpdb;

        $test_mode  = (wc_gateway_telr()->settings->__get('testmode') == 'yes') ? 1 : 0;
        $table_billing = $wpdb->prefix . 'telr_repeat_billing';
        $table_billing_txn = $wpdb->prefix . 'telr_repeat_billing_transactions';
        
        $todayBilling = $wpdb->get_results("SELECT * FROM " . $table_billing . " where enabled = 1 and next_schedule = date_format(NOW(), '%Y-%m-%d')", ARRAY_A);

        foreach ($todayBilling as $billingRow) {
            extract($billingRow);
            // Available variables: id, order_id, cart_id, product_id, cart_desc, telr_txnref, initial_payment, recurring_amount, 
            //interval_value, interval_unit, final_amount, recurring_count, next_schedule, created_on, enabled

            //Get count of previous success transactions
            $successBilling = $wpdb->get_results("SELECT id FROM " . $table_billing_txn . " where billing_id = " . $id . " AND (payment_status = 'A' or payment_status = 'H')", ARRAY_A);
            $prevCount = count($successBilling);

            $txnAmount = 0;
            if($prevCount < $recurring_count - 1){
                $txnAmount = $recurring_amount;
            }

            if($prevCount == $recurring_count - 1){
                $txnAmount = $final_amount;
            }

            if($txnAmount > 0){
                $data =array(
                    'ivp_store' => wc_gateway_telr()->settings->__get('store_id'),
                    'ivp_authkey' => wc_gateway_telr()->settings->__get('remote_store_secret'),
                    'ivp_trantype' => 'sale',
                    'ivp_tranclass' => 'cont',
                    'ivp_desc' => $cart_desc,
                    'ivp_cart' => $cart_id,
                    'ivp_currency' => get_woocommerce_currency(),
                    'ivp_amount' => $txnAmount,
                    'ivp_test' => $test_mode,
                    'tran_ref'     => $telr_txnref 
                );

                $response = wc_gateway_telr()->checkout->remote_api_request($data);

                parse_str($response, $parsedResponse);
                
                $authStatus = $parsedResponse['auth_status'];
                $authMessage = $parsedResponse['auth_message'];
                $txnRef = $parsedResponse['auth_tranref'];

                $insData = $wpdb->insert( 
                    $table_billing_txn, 
                      array( 
                        'billing_id' => $id,
                        'transaction_amount' => $txnAmount,
                        'telr_txnref_orig' => $telr_txnref,
                        'telr_txnref' => $txnRef,
                        'api_request' => json_encode($data),
                        'api_response' => json_encode($parsedResponse),
                        'payment_status' => $authStatus,
                        'failure_reason' => $authMessage,
                      ) 
                    );

                $successBillingAfter = $wpdb->get_results("SELECT id FROM " . $table_billing_txn . " where billing_id = " . $id . " AND (payment_status = 'A' or payment_status = 'H')", ARRAY_A);

                $updateBilling = array();
                if($recurring_count == count($successBillingAfter)){
                    $updateBilling['enabled'] = 2;
                } else{
                    $scheduleDate = "";
                    if($authStatus != 'A' && $authStatus != 'H'){
                        // Set billing for tomorrow
                        $date = new DateTime();
                        $date->add(new DateInterval('P1D'));
                        $scheduleDate = $date->format('Y-m-d');
                    } else{
                        //Set billing for next cycle
                        $date = new DateTime();
                        switch ($interval_unit) {
                            case 'days':
                                $date->add(new DateInterval('P' . $interval_value . 'D'));
                                $scheduleDate = $date->format('Y-m-d');
                                break;

                            case 'months':
                                $date->add(new DateInterval('P' . $interval_value . 'M'));
                                $scheduleDate = $date->format('Y-m-d');
                                break;
                        }
                    }
                    $updateBilling['next_schedule'] = $scheduleDate;
                }

                $wpdb->update(
                    $table_billing,
                    $updateBilling,
                    array('id' => $id)
                );
            } else{
                // Recurring Txn count reached
            }
        }
        echo "Telr Recurring Cron Run for " . count($todayBilling) . " transactions. on " . date("Y-m-d H:i:s") . "\n";
        exit;
    }

    public function show_bootstrap_warning()
    {
        $dependencies_message = get_option('wc_gateway_telr_bootstrap_warning_message', '');
        if (!empty($dependencies_message)) {
            ?>
            <div class="error fade">
                <p>
                    <strong><?php echo esc_html($dependencies_message); ?></strong>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Run the plugin.
     */
    protected function _run()
    {
        $this->_load_handlers();
    }

    protected function _load_handlers()
    {

        // Load handlers.
        require_once($this->includes_path . 'class-wc-gateway-telr-settings.php');
        require_once($this->includes_path . 'class-wc-gateway-telr-gateway-loader.php');
        require_once($this->includes_path . 'class-wc-gateway-telr-admin-handler.php');
        require_once($this->includes_path . 'class-wc-gateway-telr-checkout-handler.php');

        $this->settings       = new WC_Gateway_Telr_Settings();
        $this->gateway_loader = new WC_Gateway_telr_Gateway_Loader();
        $this->admin          = new WC_Gateway_Telr_Admin_Handler();
        $this->checkout       = new WC_Gateway_Telr_Checkout_Handler();
    }

    /**
     * Callback for activation hook.
     */
    public function activate()
    {
        if (!isset($this->setings)) {
            require_once($this->includes_path . 'class-wc-gateway-telr-settings.php');
            $this->settings = new WC_Gateway_Telr_Settings();
        }
        return true;
    }

    public function deactivate()
    {
        return true;
    }

    public function plugin_action_links($links)
    {
        $plugin_links = array();

        $setting_url = $this->get_admin_setting_link();
        $plugin_links[] = '<a href="' . esc_url($setting_url) . '">' . esc_html__('Settings', 'wctelr') . '</a>';


        return array_merge($plugin_links, $links);
    }

    /**
     * Link to settings screen.
     */
    public function get_admin_setting_link()
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=wctelr');
    }

    public function init_ssl_check()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) {
            $_SERVER['HTTPS'] = 'on';
        }
    }
}
