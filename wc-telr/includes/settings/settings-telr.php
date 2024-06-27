<?php
/**
 * Card payment method settings class.
 *
 * @package wc_telr
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings for Telr Gateway.
 */
 
 class WC_Telr_Cards_Settings {
	 
	/**
	* Telr admin core settings fields
	*
	* @return mixed
	*/
    public static function core_settings() {
	
		return array(
			'enabled' => array(
				'title'     => __('Enable/Disable', 'wctelr'),
				'type'      => 'checkbox',
				'label'     => __('Enable Telr', 'wctelr'),
				'default'   => 'yes'
			),
			'testmode' => array(
				'title'         => __('Test Mode', 'wctelr'),
				'type'          => 'checkbox',
				'label'         => __('Generate transactions in test mode', 'wctelr'),
				'default'       => 'yes',
				'description'   => __('Use this while testing your integration. You must disable test mode when you are ready to take live transactions')
			),
			'title' => array(
				'title'         => __('Title', 'wctelr'),
				'type'          => 'text',
				'description'   => __('This controls the title which the user sees during checkout.', 'wctelr'),
				'default'       => __('Pay using a credit or debit card via Online Payments', 'wctelr'),
				'desc_tip'      => true,
			),
			'title_arabic' => array(
				'title'         => __('Title Arabic', 'wctelr'),
				'type'          => 'text',
				'description'   => __('This controls the title which the user sees during checkout.', 'wctelr'),
				'default'       => __('ادفع باستخدام بطاقة ائتمان أو خصم عبر المدفوعات عبر الإنترنت', 'wctelr'),
				'desc_tip'      => true,
			),
			'description' => array(
				'title'         => __('Description', 'wctelr'),
				'type'          => 'textarea',
				'description'   => __('This controls the description which the user sees during checkout.', 'wctelr'),
				'default'       => __('Pay using a credit or debit card via Online Payments', 'wctelr'),
				'desc_tip'      => true,
			),
			'description_arabic' => array(
				'title'         => __('Description Arabic', 'wctelr'),
				'type'          => 'textarea',
				'description'   => __('This controls the description which the user sees during checkout.', 'wctelr'),
				'default'       => __('ادفع باستخدام بطاقة ائتمان أو خصم عبر المدفوعات عبر الإنترنت', 'wctelr'),
				'desc_tip'      => true,
			),
			'cart_desc' => array(
				'title'         => __('Transaction description', 'wctelr'),
				'type'          => 'text',
				'description'   => __('This controls the transaction description shown within the hosted payment page.', 'wctelr'),
				'default'       => __('Your order from Store Name', 'wctelr'),
				'desc_tip'      => true,
			),
			'store_id' => array(
				'title'         => __('Store ID', 'wctelr'),
				'type'          => 'text',
				'description'   => __('Enter your Telr Store ID.', 'wctelr'),
				'default'       => '',
				'desc_tip'      => true,
				'placeholder'   => '[StoreID]'
			),
			'store_secret' => array(
				'title'         => __('Authentication Key', 'wctelr'),
				'type'          => 'text',
				'description'   => __('This value must match the value configured in the hosted payment page V2 settings', 'wctelr'),
				'default'       => '',
				'desc_tip'      => true,
				'placeholder'   => '[Authentication Key]'
			),
			'remote_store_secret' => array(
				'title'         => __('Remote API Authentication Key', 'wctelr'),
				'type'          => 'text',
				'description'   => __('Required for Subscription Payments. This value must match the value configured in the Remote API settings for Recurring Payments', 'wctelr'),
				'default'       => '',
				'desc_tip'      => false,
				'placeholder'   => '[Remote API Authentication Key]'
			),
			'tran_type' => array(
				'title'           => __('Transaction Type', 'wctelr'),
				'type'            => 'select',
				'options'         => array(
					'sale'       => __( 'Sale', 'wctelr' ),
					'auth'          => __( 'Auth', 'wctelr' ),
				),
				'default'         => 'sale',
				'desc_tip'        => false
			),
			'payment_mode' => array(
				'title'           => __('Payment Mode', 'wctelr'),
				'type'            => 'select',
				'options'         => array(
				  '0'               => __( 'Standard Mode', 'wctelr' ),
				  '2'               => __( 'iFrame on New Page', 'wctelr' ),
				  '9'               => __( 'iFrame on Checkout Page', 'wctelr' ),
				  '10'              => __( 'Seamless Payment Mode', 'wctelr' ),
				),
				'label'           => __('Select Payment mode by which user will pay', 'wctelr'),
				'default'         => '0',
				'desc_tip'        => true,
				'description'     => __('Use iframe mode if SSL installed on server otherwise select Standard Mode.')
			),
			'subscription_method' => array(
				'title'           => __('Subscription Method', 'wctelr'),
				'type'            => 'select',
				'options'         => array(
				  'woocomm'       => __( 'Woocommerce Subscriptions', 'wctelr' ),
				  'telr'          => __( 'Telr Agreement Model', 'wctelr' ),
				),
				'label'           => __('Select Subscription method for recurring payments', 'wctelr'),
				'default'         => 'woocomm',
				'desc_tip'        => true
			),
			'language' => array(
				'title'         => __('Language', 'wctelr'),
				'type'          => 'select',
				'options'       => array(
				  'en'              => __('English', 'wctelr'),
				  'ar'              => __('Arabic', 'wctelr')),
				'label'         => __('Select Payment mode by which user will pay', 'wctelr'),
				'default'       => 'en',
				'desc_tip'      => true,
				'description'   => __('The Language used in Payment Page interface.')
			),
			'default_order_status' => array(
				'title'         => __('Default Order Status', 'wctelr'),
				'type'          => 'select',
				'options'       => array(
				  'none'            => __('Default', 'wctelr'),
				  'on-hold'         => __('On Hold', 'wctelr'),
				  'processing'      => __('Processing', 'wctelr'),
				  'completed'       => __('Completed', 'wctelr'),
				  'pending'         => __('pending', 'wctelr'),
				 ),
				'default'       => 'none',
				'description'   => __('The default order status after payment.', 'wctelr')
			),	
		);
	}
	
	/**
	* Telr admin Apple Pay settings fields
	*
	* @return mixed
	*/
    public static function apple_pay_settings() {
		
	return array(    	
		     	
		'remote_v2_auth_key'  => array(
			'title'       => __( 'Telr Apple Auth Key', 'wctelr' ),
			'type'        => 'text',
			'description' => __( 'This value must match the value configured in the Remote V2 settings', 'wctelr' ),
			'desc_tip'    => true,
			'default'     => '',
		),
		'apple_pay_title' => array(
			'title'       => __( 'Title', 'wctelr' ),
			'type'        => 'text',
			'label'       => __( 'Card payment title', 'wctelr' ),
			'description' => __( 'Title that will be displayed on the checkout page', 'wctelr' ),
			'desc_tip'    => true,
			'default'     => 'Apple Pay',
		),
		'apple_pay_description' => array(
			'title'       => __( 'Description', 'wctelr' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'wctelr' ),
			'default'     => 'Pay with Apple Pay.',
			'desc_tip'    => true,
		),
		'apple_mercahnt_id' => array(
			'title'     => __( 'Merchant Identifier', 'wctelr' ),
			'type'        => 'text',
			/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
			'description' => sprintf( __( 'You can find this in your apple pay developer portal', 'wctelr' ) ),
			'default'     => '',
		),
		'apple_certificate' => array(
			'title'       => __( 'Merchant Certificate', 'wctelr' ),
			'type'        => 'text',
			'description' => __( 'The absolute path to your .pem certificate.', 'wctelr' ),
			'desc_tip'    => true,
			'default'     => '',
		),
		'apple_key'         => array(
			'title'       => __( 'Merchant Certificate Key', 'wctelr' ),
			'type'        => 'text',
			'description' => __( 'The absolute path to your .key certificate key.', 'wctelr' ),
			'desc_tip'    => true,
			'default'     => '',
		),
        'domain' => array(
			'title'       => __( 'Domain', 'wctelr' ),
			'type'        => 'text',
			'label'       => __( 'Domain', 'wctelr' ),
			'description' => __( 'Domain that verified for apple pay', 'wctelr' ),
			'desc_tip'    => true,
			'default'     => '',
		),
        'display_name' => array(
			'title'       => __( 'Display Name', 'wctelr' ),
			'type'        => 'text',
			'label'       => __( 'Display Name', 'wctelr' ),
			'description' => __( 'Domain display name', 'wctelr' ),
			'desc_tip'    => true,
			'default'     => '',
		),
		'apple_type'        => array(
			'title'   => __( 'Button Type', 'wctelr' ),
			'type'    => 'select',
			'options' => array(
				'apple-pay-button-text-buy'       => __( 'Buy', 'wctelr' ),
				'apple-pay-button-text-check-out' => __( 'Checkout out', 'wctelr' ),
				'apple-pay-button-text-book'      => __( 'Book', 'wctelr' ),
				'apple-pay-button-text-donate'    => __( 'Donate', 'wctelr' ),
				'apple-pay-button'                => __( 'Plain', 'wctelr' ),
			),
		),
		'apple_theme'       => array(
			'title'   => __( 'Button Theme', 'wctelr' ),
			'type'    => 'select',
			'options' => array(
				'apple-pay-button-black-with-text' => __( 'Black', 'wctelr' ),
				'apple-pay-button-white-with-text' => __( 'White', 'wctelr' ),
				'apple-pay-button-white-with-line-with-text' => __( 'White with outline', 'wctelr' ),
			),
		)		
	);
		
    }	
 }
