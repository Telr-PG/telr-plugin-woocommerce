const { useState, useEffect } = wp.element;
const seamlessTelrSettings = window.wc.wcSettings.getSetting('wctelr_data', {});
const label = window.wp.htmlEntities.decodeEntities(seamlessTelrSettings.title) || window.wp.i18n.__('Telr Payments', 'wctelr');
const storeId = seamlessTelrSettings.storeId || '';
const currency = seamlessTelrSettings.currency || '';
const testMode = seamlessTelrSettings.testMode || '0';
const iframeUrl = `https://secure.telr.com/jssdk/v2/token_frame.html?token=${Math.floor(Math.random() * (9999 - 1111 + 1)) + 1111}&lang=${seamlessTelrSettings.language || 'en'}`;

window.telrInit = false;

const telrMessage = {
    message_id: "init_telr_config",
    store_id: storeId,
    currency: currency,
    test_mode: testMode
};

// Function to handle message event
const handleMessage = (e) => {
    const message = e.data;
    if (message !== "") {
        let isJson = true;
        try {
            JSON.parse(message);
        } catch (e) {
            isJson = false;
        }

        if (isJson || (typeof message === 'object' && message !== null)) {
            const telrMessage = (typeof message === 'object') ? message : JSON.parse(message);
            if (telrMessage.message_id !== undefined) {
                switch (telrMessage.message_id) {
                    case "return_telr_token":
                        const payment_token = telrMessage.payment_token;
                        if(payment_token != ""){
                            document.querySelector("#telr_payment_token").value = payment_token;
                        }
                        break;
                }
            }
        }
    }
};

// Adding event listener for modern browsers
if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('message', handleMessage, false);
} else if (typeof window.attachEvent !== 'undefined') {
    window.attachEvent('onmessage', handleMessage);
}

// Function to ensure iframe is ready and loaded
const initializeTelrIframe = () => {
    const telrIframe = document.querySelector('#telr_iframe');

    if (telrIframe) {
        // Wait for the iframe to fully load before posting the message
        telrIframe.addEventListener('load', function () {
            const initMessage = JSON.stringify(telrMessage);
            setTimeout(function () {
                if (!window.telrInit) {
                    telrIframe.contentWindow.postMessage(initMessage, "*");
                    window.telrInit = true;
                }
            }, 1500);
        });
    } else {
        // Retry if iframe is not found (can happen due to asynchronous loading)
        setTimeout(initializeTelrIframe, 500);
    }
};

// Initialize the iframe when the DOM content is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    initializeTelrIframe();
});

// Content function to create the iframe
const seamlessTelrContent = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;

    useEffect(() => {
        // Setup payment token handling
        const unsubscribePayment = onPaymentSetup(async () => {
            const telr_payment_token = document.querySelector("#telr_payment_token").value;
            const customDataIsValid = !!telr_payment_token.length;
            if (customDataIsValid) {
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            telr_payment_token,
                        },
                    },
                };
            }
            return {
                type: emitResponse.responseTypes.ERROR,
                message: 'There was an error',
            };
        });
        // Setup checkout success handling
        const unsubscribeCheckout = onCheckoutSuccess((response) => {
            // This code runs after a successful checkout
            console.log("Checkout completed successfully!");
            const iframeUrl = response.processingResponse.paymentDetails.iframe_url;
            const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button");
            const existingIframe = document.getElementById("telr_iframe");
            if (iframeUrl) {
                existingIframe.src = iframeUrl;
                placeOrderButton.style.display = "none";
            }
        });
        // Cleanup functions for both observers
        return () => {
            unsubscribePayment();
            unsubscribeCheckout();
        };
    }, [emitResponse, onPaymentSetup, onCheckoutSuccess]);

    return Object(window.wp.element.createElement)(window.wp.element.Fragment, null,
        Object(window.wp.element.createElement)('iframe', {
            src: iframeUrl,
            id: "telr_iframe",
            width: "100%",
            height: "300",
            frameBorder: "0",
            allowFullScreen: true
        }),
        Object(window.wp.element.createElement)('input', {
            type: "hidden",
            name: "telr_payment_token",
            id: "telr_payment_token"
        })
    );
};
// Register the payment method block
const Block_Gateway = {
    name: 'wctelr',
    label: label,
    content: Object(window.wp.element.createElement)(seamlessTelrContent, null),
    edit: Object(window.wp.element.createElement)(seamlessTelrContent, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: seamlessTelrSettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

document.addEventListener("change", (event) => { console.log(event.target.name);
    const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button__text");
    const telr_iframe = document.querySelector("#telr_iframe");
    if (event.target.name === 'radio-control-wc-payment-method-options' && placeOrderButton) {
        const selectedMethod = event.target.value;
        placeOrderButton.textContent = selectedMethod === 'wctelr' ? seamlessTelrSettings.orderButtonText : "Place Order";
    }
    if(telr_iframe) {
        window.telrInit = false;
        initializeTelrIframe();
    }
}); 
 