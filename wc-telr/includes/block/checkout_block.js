const { useState, useEffect } = wp.element;
const settings = window.wc.wcSettings.getSetting('wctelr_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Telr for woocommerce', 'wctelr');
const paymentMode = settings.paymentMode || 0;
const supportedNetworks = settings.supportNetworks || '';

const iconPaths = {
    VISA: { src: settings.iconPath, alt: 'VISA', className: 'logo_visa' },
    MASTERCARD: { src: settings.iconPath, alt: 'MASTERCARD', className: 'logo_mastercard' },
    JCB: { src: settings.iconPath, alt: 'JCB', className: 'logo_jcb' },
    MADA: { src: settings.iconPath, alt: 'MADA', className: 'logo_mada' },
    AMEX: { src: settings.iconPath, alt: 'AMEX', className: 'logo_amex' },
    MAESTRO: { src: settings.iconPath, alt: 'MAESTRO', className: 'logo_masterpass' },
    PayPal: { src: settings.iconPath, alt: 'PayPal', className: 'logo_paypal' },
    UnionPay: { src: settings.iconPath, alt: 'UnionPay', className: 'logo_cup' },
    ApplePay: { src: settings.iconPath, alt: 'ApplePay', className: 'logo_applepay' },
    STCPAY: { src: settings.iconPath, alt: 'STCPAY', className: 'logo_stcpay' },
    URPAY: { src: settings.iconPath, alt: 'URPAY', className: 'logo_urpay' },
    Tabby: { src: settings.iconPath, alt: 'Tabby', className: 'logo_tabby' },
};

let Content;
if(paymentMode == 0){
    Content = () => {
        return (window.wp.htmlEntities.decodeEntities(settings.description || ''));
    };
}else{
    Content = (props) => {
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
                    existingIframe.previousElementSibling.remove();
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
                        window.wp.htmlEntities.decodeEntities(settings.description || '')
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
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

document.addEventListener("change", (event) => { console.log(event.target.name);
    const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button");
    if (event.target.name === 'radio-control-wc-payment-method-options' && placeOrderButton) {
        const selectedMethod = event.target.value;
        placeOrderButton.textContent = selectedMethod === 'wctelr' ? settings.orderButtonText : "Place Order";
    }
}); 