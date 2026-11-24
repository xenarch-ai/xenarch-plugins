/**
 * XEN-681 — "Crypto: USDC" in the WooCommerce Checkout block.
 * Plain JS on WooCommerce's globals, no build step.
 */
( function () {
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var wcSettings = window.wc && window.wc.wcSettings;
	if ( ! registry || ! wcSettings || ! window.wp || ! window.wp.element ) {
		return;
	}
	var el = window.wp.element.createElement;
	var decode = ( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities ) || function ( s ) { return s; };
	var data = wcSettings.getSetting( 'xenarch_data', {} );
	var title = decode( data.title || 'Crypto: USDC' );

	function Content() {
		return el( 'div', null, decode( data.description || '' ) );
	}
	function Label( props ) {
		var PaymentMethodLabel = props.components && props.components.PaymentMethodLabel;
		return PaymentMethodLabel ? el( PaymentMethodLabel, { text: title } ) : el( 'span', null, title );
	}

	registry.registerPaymentMethod( {
		name: 'xenarch',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () { return true; },
		ariaLabel: title,
		placeOrderButtonLabel: decode( data.button || 'Pay with crypto' ),
		supports: { features: data.supports || [ 'products' ] }
	} );
} )();
