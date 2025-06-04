const { useState, useEffect } = wp.element;
const telrSettings = window.wc.wcSettings.getSetting('wctelr_data', {});
const label = window.wp.htmlEntities.decodeEntities(telrSettings.title) || window.wp.i18n.__('Telr for woocommerce', 'wctelr');
const paymentMode = telrSettings.paymentMode || 0;
const supportedNetworks = telrSettings.supportNetworks || '';

const iconPaths = {
    VISA: { src: telrSettings.iconPath, alt: 'VISA', className: 'logo_visa' },
    MASTERCARD: { src: telrSettings.iconPath, alt: 'MASTERCARD', className: 'logo_mastercard' },
    JCB: { src: telrSettings.iconPath, alt: 'JCB', className: 'logo_jcb' },
    MADA: { src: telrSettings.iconPath, alt: 'MADA', className: 'logo_mada' },
    AMEX: { src: telrSettings.iconPath, alt: 'AMEX', className: 'logo_amex' },
    MAESTRO: { src: telrSettings.iconPath, alt: 'MAESTRO', className: 'logo_masterpass' },
    PayPal: { src: telrSettings.iconPath, alt: 'PayPal', className: 'logo_paypal' },
    UnionPay: { src: telrSettings.iconPath, alt: 'UnionPay', className: 'logo_cup' },
    ApplePay: { src: telrSettings.iconPath, alt: 'ApplePay', className: 'logo_applepay' },
    STCPAY: { src: telrSettings.iconPath, alt: 'STCPAY', className: 'logo_stcpay' },
    URPAY: { src: telrSettings.iconPath, alt: 'URPAY', className: 'logo_urpay' },
    Tabby: { src: telrSettings.iconPath, alt: 'Tabby', className: 'logo_tabby' },
};

let telrContent;
if(paymentMode == 0){
    telrContent = () => {
        return (window.wp.htmlEntities.decodeEntities(telrSettings.description || ''));
    };
}else{
    telrContent = (props) => {
        const { eventRegistration, emitResponse } = props;
        const { onCheckoutSuccess } = eventRegistration;
        useEffect(() => {
            // Setup checkout success handling
            const unsubscribeCheckout = onCheckoutSuccess((response) => {
                // This code runs after a successful checkout
                console.log("Checkout completed successfully!");
                const iframeUrl = response.processingResponse.paymentDetails.iframe_url;
                const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button");
                const existingIframe = document.getElementById("telr_iframe");
                const networkIcons = document.getElementById("network_icons");
                if (iframeUrl) {
                    existingIframe.src = iframeUrl;
                    existingIframe.height = "550";
                    existingIframe.style.display = "block";
                    placeOrderButton.style.display = "none";
                    networkIcons.style.display = "none";
                }
            });
            // Cleanup functions for observers
            return () => {
                unsubscribeCheckout();
            };
        }, [emitResponse, onCheckoutSuccess]);

        return  Object(window.wp.element.createElement)(window.wp.element.Fragment, null,
                    Object(window.wp.element.createElement)('span', null,
                        window.wp.htmlEntities.decodeEntities(telrSettings.description || '')
                    ),
                    Object(window.wp.element.createElement)('iframe', {
                        src: "",
                        id: "telr_iframe",
                        width: "100%",
                        height: "0",
                        frameBorder: "0",
                        allowFullScreen: true,
                        style:{ display: 'none' },
                    })
                );
    };
}
// Register the payment method block
const Block_Gateway = {
    name: 'wctelr',
    label: Object(window.wp.element.createElement)(
        'span',
        { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
        Object(window.wp.element.createElement)('span', null, label),
        Object(window.wp.element.createElement)(
            'div',
            { style: { display: 'flex'}, id:"network_icons" },
            ...supportedNetworks.map(network => {
                if (iconPaths[network]) {
                    return Object(window.wp.element.createElement)('img', {
                        src: iconPaths[network].src,
                        alt: iconPaths[network].alt,
                        className: iconPaths[network].className,
                        style: { maxHeight: '30px' }
                    });
                }
                return null;
            })
        )
    ),
    content: Object(window.wp.element.createElement)(telrContent, null),
    edit: Object(window.wp.element.createElement)(telrContent, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: telrSettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

document.addEventListener("change", (event) => { console.log(event.target.name);
    const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button__text");
    const orderButton = document.querySelector(".wc-block-components-checkout-place-order-button");
    if (event.target.name === 'radio-control-wc-payment-method-options' && placeOrderButton) {
        const selectedMethod = event.target.value;
        placeOrderButton.textContent = selectedMethod === 'wctelr' ? telrSettings.orderButtonText : "Place Order";
    }
    if (placeOrderButton && placeOrderButton.classList.contains('wc-block-components-checkout-place-order-button__text--visually-hidden')) {
       location.reload();
    }
}); 