const apple_settings = window.wc.wcSettings.getSetting('wc_telr_apple_pay_data', {});
const is_admin = apple_settings.is_admin;
if(is_admin){
	const apple_label = window.wp.htmlEntities.decodeEntities(apple_settings.title) || window.wp.i18n.__('Telr for woocommerce', 'wc_telr_apple_pay');
	const Content_apple = () => {
		return window.wp.htmlEntities.decodeEntities(apple_settings.description || '');
	};
	const Block_Gateway = {
		name: 'wc_telr_apple_pay',
		label: apple_label,
		content: Object(window.wp.element.createElement)(Content_apple, null ),
		edit: Object(window.wp.element.createElement)(Content_apple, null ),
		canMakePayment: () => true,
		ariaLabel: apple_label,
		supports: {
			features: apple_settings.supports,
		},
	};
	window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
}else{
	const apple_label = window.wp.htmlEntities.decodeEntities(apple_settings.title) || window.wp.i18n.__('Telr for woocommerce', 'wc_telr_apple_pay');
	const apple_mercahnt_id = apple_settings.apple_mercahnt_id;
	const apple_type = apple_settings.apple_type;
	const apple_theme = apple_settings.apple_theme;

	var applePayButtonId = 'telr_applePay';

	if (window.ApplePaySession) {
		var canMakePayments = ApplePaySession.canMakePayments(apple_mercahnt_id);
		if ( canMakePayments ) {
			setTimeout( function() {
				const Content_apple = (props) => {
					const { eventRegistration, emitResponse } = props;
					const { onPaymentSetup, onCheckoutSuccess, onCheckoutFail } = eventRegistration;
					const processingMessage = document.getElementById('processingMessage');
					
					useEffect(() => {
						// Setup payment token handling
						const unsubscribePayment = onPaymentSetup(async () => {			
						   
							const applePayVersion = document.querySelector("#applepayversion").value;
							const applePayData = document.querySelector("#applepaydata").value;
							const applePaySignature = document.querySelector("#applepaysignature").value;
							const applePayTransactionId = document.querySelector("#applepaytransactionid").value;
							const applePayType = document.querySelector("#applepaytype").value;
							const applePayNetwork = document.querySelector("#applepaynetwork").value;
							const applePayDisplayName = document.querySelector("#applepaydisplayname").value;
							const applePayKeyHash = document.querySelector("#applepaykeyhash").value;
							const applePayKey = document.querySelector("#applepaykey").value;
							const applePayTransactionIdentifier = document.querySelector("#applepaytransactionidentifier").value;				
							const customDataIsValid = !!applePayVersion.length;
							const apple_pay_version = applePayVersion;
   							
							processingMessage.style.display = 'block';

							if (customDataIsValid) { console.log('appleData Validated');
                                                                const paymentMethodData = {   //apple_pay_version,
                                                                                              applePayVersion,
                                                                                              applePayData,
                                                                                              applePaySignature,
                                                                                              applePayTransactionId,
                                                                                              applePayType,
                                                                                              applePayNetwork,
                                                                                              applePayDisplayName,
                                                                                              applePayKeyHash,
                                                                                              applePayKey,
                                                                                              applePayTransactionIdentifier,
                                                                                         };
                                                               console.log('paymentMethodData:', paymentMethodData);

								return {
									type: emitResponse.responseTypes.SUCCESS,
									meta: { paymentMethodData },
								};
							}
							return {
								type: emitResponse.responseTypes.ERROR,
								message: 'There was an error',
							};
						});			
						const unsubscribeCheckout = onCheckoutSuccess((response) => {
							processingMessage.style.display = 'none';
						});
						const unsubscribeCheckoutError = onCheckoutFail((error) => {
                                                        //console.log(error);
							processingMessage.style.display = 'none';
                                                });
						// Cleanup functions for both observers
						return () => {
							unsubscribePayment();
							unsubscribeCheckout();
							unsubscribeCheckoutError();
						};
					}, [emitResponse, onPaymentSetup, onCheckoutSuccess, onCheckoutFail]);

					return  Object(window.wp.element.createElement)(window.wp.element.Fragment, null,
								Object(window.wp.element.createElement)('span', null, 
									window.wp.htmlEntities.decodeEntities(apple_settings.description || '')
								),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepayversion",
									id: "applepayversion"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaydata",
									id: "applepaydata"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaysignature",
									id: "applepaysignature"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaytransactionid",
									id: "applepaytransactionid"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaytype",
									id: "applepaytype"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaynetwork",
									id: "applepaynetwork"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaydisplayname",
									id: "applepaydisplayname"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaykeyhash",
									id: "applepaykeyhash"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaykey",
									id: "applepaykey"
								}),
								Object(window.wp.element.createElement)('input', {
									type: "hidden",
									name: "applepaytransactionidentifier",
									id: "applepaytransactionidentifier"
								}),
						                Object(window.wp.element.createElement)('div', {
       									id: "processingMessage",
        								style: {
            									display: "none",
            									position: "fixed",
            									top: "50%",
            									left: "50%",
            									transform: "translate(-50%, -50%)",
            									backgroundColor: "rgba(0,0,0,0.8)",
            									color: "white",
            									padding: "20px",
            									borderRadius: "8px",
            									textAlign: "center",
        								},
    								}, "Order Processing... Please wait."),
							);
				};

				// Register the payment method block
				const Block_Gateway_Apple = {
					name: 'wc_telr_apple_pay',
					label: Object(window.wp.element.createElement)(
						'span',
						{ style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
						Object(window.wp.element.createElement)('span', null, apple_label),
						Object(window.wp.element.createElement)(
							'div',
							{ style: { display: 'flex', gap: '5px' } },
							Object(window.wp.element.createElement)('img', {
								src: apple_settings.iconPath,
								alt: 'ApplePay',
								className: 'logo_applepay',
								style: { maxHeight: '32px' },
							})
						)
					),
					content: Object(window.wp.element.createElement)(Content_apple, null),
					edit: Object(window.wp.element.createElement)(Content_apple, null),
					canMakePayment: () => true,
					ariaLabel: label,
					supports: {
						features: apple_settings.supports,
					},
				};
				window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway_Apple);
				jQuery('.wc-block-checkout__actions_row').append('<button id="' + applePayButtonId + '" class="apple-pay-button  ' + apple_type + " " + apple_theme  + '""></button>');
			}, 500 );
		}	
	}

	setTimeout(() => {
        const applePayOption = document.querySelector('#radio-control-wc-payment-method-options-wc_telr_apple_pay');
		const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button");
		const applePayButton = document.querySelector("#telr_applePay");
		const allPaymentOptions = document.querySelectorAll('[name="radio-control-wc-payment-method-options"]');
		// Function to toggle the button visibility
		function togglePlaceOrderButton() {
			const isApplePaySelected = applePayOption.checked;
			const isOnlyApplePayOption = allPaymentOptions.length === 1 && applePayOption;

			if (isApplePaySelected || isOnlyApplePayOption) {
				placeOrderButton.style.display = 'none';
                applePayButton.style.display = 'block';
			} else {
				placeOrderButton.style.display = 'block';
                applePayButton.style.display = 'none';
			}
		}
		if (applePayOption) {
			togglePlaceOrderButton();
			allPaymentOptions.forEach(option => {
				option.addEventListener('change', togglePlaceOrderButton);
			});
		}	
	}, 1500); // Adjust delay as needed

	jQuery( document ).off( 'click', '#' + applePayButtonId );
	jQuery( document ).on( 'click', '#' + applePayButtonId, function () {
		var checkoutFields = apple_settings.checkout_fields;
		var result = isValidFormField(checkoutFields);
		if(result){
			var applePaySession = new ApplePaySession(3, getApplePayRequestPayload());
			handleApplePayEvents(applePaySession);
			applePaySession.begin();
		}
		return false;
	});
}

function isValidFormField(fieldList) {
	var result = {error: false, messages: []};
	var fields = JSON.parse(fieldList);
	if(apple_settings.subscriptionProduct > 1){
		result.error = true;
		result.messages.push({target: false, message : 'Only 1 Repeat Billing product is allowed per transaction.'});
	}
	if(jQuery('#terms').length === 1 && jQuery('#terms:checked').length === 0){
		result.error = true;
		result.messages.push({target: 'terms', message : 'You must accept our Terms & Conditions.'});
	}
	if(jQuery('#email').length === 1){
		var reg     = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
		var correct = reg.test(jQuery('#email').val());
		if (!correct) {
			result.error = true;
			result.messages.push({target: name, message : value.label + ' is not correct email.'});
		}
	}
	if (fields) {
		jQuery.each(fields, function(group, groupValue) {
			if (group === 'shipping') {
				jQuery.each(groupValue, function(name, value ) {
					name = name.replace('shipping_', 'shipping-');
					var inputValue = jQuery('#' + name).length > 0 && jQuery('#' + name).val().length > 0 ? jQuery('#' + name).val() : '';
					if(value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).hasClass('wc-block-components-combobox')){
						if(jQuery('#' + name + ' input.components-combobox-control__input').val().length === 0){
							result.error = true;
							result.messages.push({target: name, message : value.label + ' is a required field.'});	
						}						
					}else if (value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).val().length === 0) {						
						result.error = true;
						result.messages.push({target: name, message : value.label + ' is a required field.'});
					}
					if (value.hasOwnProperty('type')) {
						switch (value.type) {							
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
				
			}
			var isChecked = jQuery('.wc-block-checkout__use-address-for-billing input[type="checkbox"]').is(':checked')
			if( group === 'billing' && isChecked === false) {
				jQuery.each(groupValue, function(name, value ) {
					name = name.replace('billing_', 'billing-');
					if (!value.hasOwnProperty('required')) {
						return true;
					}
					if (name === 'account_password' && jQuery('#createaccount:checked').length === 0) {
						return true;
					}
					var inputValue = jQuery('#' + name).length > 0 && jQuery('#' + name).val().length > 0 ? jQuery('#' + name).val() : '';
					if(value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).hasClass('wc-block-components-combobox')){
						if(jQuery('#' + name + ' input.components-combobox-control__input').val().length === 0){
							result.error = true;
							result.messages.push({target: name, message : value.label + ' is a required field.'});	
						}						
					}else if (value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).val().length === 0) {						
						result.error = true;
						result.messages.push({target: name, message : value.label + ' is a required field.'});
					}
					if (value.hasOwnProperty('type')) {
						switch (value.type) {							
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
			}	
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
		jQuery('form.wc-block-components-form.wc-block-checkout__form').prepend('<div class="woocommerce-error">' + value.message + '</div>');
	});
	jQuery('html, body').animate({
		scrollTop: (jQuery('form.wc-block-components-form.wc-block-checkout__form').offset().top - 100 )
	}, 1000 );
	jQuery(document.body).trigger('checkout_error');
	return false;
}

/**
 * Get the configuration needed to initialise the Apple Pay session.
 *
 * @param {function} callback
 */
function getApplePayRequestPayload() {
    var networksSupported = apple_settings.supported_networks;
    var merchantCapabilities = apple_settings.merchant_capabilities;

    // Initialize the payload object
    var payload = {
        currencyCode: apple_settings.currency,
        countryCode: apple_settings.country_code,
        merchantCapabilities: merchantCapabilities,
        supportedNetworks: networksSupported,
        total: {
            label: window.location.host,
            amount: apple_settings.cartTotal,
            type: 'final'
        }
    };

    // Conditionally add the recurring payment request if subscriptionProduct is true
    if (apple_settings.subscriptionProduct === true) {
        payload.recurringPaymentRequest = {
            paymentDescription: apple_settings.cart_desc,
            regularBilling: {
                label: '',
                amount: apple_settings.amount,
                recurringPaymentStartDate: new Date(apple_settings.currDate),
                recurringPaymentIntervalUnit: apple_settings.recurrIntUnit,
                recurringPaymentIntervalCount: apple_settings.recurrInterval,
                paymentTiming: 'recurring'
            },
            managementURL: apple_settings.site_url
        };
    }

    return payload;
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
			amount: apple_settings.cartTotal,
		};
		var newLineItems = [
			{
				type: 'final',
				label: 'Subtotal',
				amount: apple_settings.cartSubTotal
			},
			{
				type: 'final',
				label: 'Shipping - ' + apple_settings.chosen_shipping,
				amount: apple_settings.shipping_amount
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
				sendPaymentToTelr(paymentData);
				session.completePayment();
			} else {
				status = ApplePaySession.STATUS_FAILURE;
				session.completePayment(status);
			}
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
	jQuery('#applepayversion').val(data.paymentData.version);
	jQuery('#applepaydata').val(data.paymentData.data);
	jQuery('#applepaysignature').val(data.paymentData.signature);
	jQuery('#applepaytransactionid').val(data.paymentData.header.transactionId);
	jQuery('#applepaytype').val(data.paymentMethod.type);
	jQuery('#applepaynetwork').val(data.paymentMethod.network);
	jQuery('#applepaydisplayname').val(data.paymentMethod.displayName);
	jQuery('#applepaykeyhash').val(data.paymentData.header.publicKeyHash);
	jQuery('#applepaykey').val(data.paymentData.header.ephemeralPublicKey);
	jQuery('#applepaytransactionidentifier').val(data.transactionIdentifier);
	
	jQuery('.wc-block-components-checkout-place-order-button').prop("disabled",false);
	jQuery('.wc-block-components-checkout-place-order-button').trigger('click');
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
		url: apple_settings.session_url,
		data: {
			url: valURL,
		},
		success: function (outcome) {
			var data = JSON.parse(outcome);
			callback(data);
		}
	});
}
