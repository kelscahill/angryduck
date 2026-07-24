jQuery( document ).ready( function ( $ ) {

    var isUpdating = false;

    // Track the payment method whose surcharge is already reflected in the order totals.
    // Initialised to the method selected on page load (its surcharge is applied
    // server-side during the initial render). This guards against firing a redundant
    // request for a method that is already current — most importantly the programmatic
    // `change` events that some 3rd party gateways (e.g. MercadoPago, WebPay Plus) emit
    // during their own JS initialisation, which previously caused an infinite reload loop.
    var appliedPaymentMethod = $( 'input[name="payment_method"]:checked' ).val() || '';

    // Update the order totals inline when the payment method is changed on the order pay page.
    $( document.body ).on( 'change', 'input[name="payment_method"]', function () {
        var $this          = $( this );
        var paymentMethod  = $this.val();
        var $placeOrderBtn = $( '#place_order' );
        var originalText   = $placeOrderBtn.text();

        // Skip when the method has not actually changed, or a request is already in flight.
        if ( paymentMethod === appliedPaymentMethod || isUpdating || $placeOrderBtn.prop( 'disabled' ) ) {
            return;
        }

        isUpdating = true;

        // Show loading indicator.
        $placeOrderBtn.prop( 'disabled', true ).text( wp.i18n.__( 'Updating...', 'woocommerce-wholesale-prices-premium' ) );

        // Send AJAX request to update the session and recalculate surcharges.
        $.ajax( {
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wwpp_update_order_pay_payment_method',
                payment_method: paymentMethod,
                order_id: wc_checkout_params.order_id,
                order_key: wc_checkout_params.order_key || '',
                nonce: wc_checkout_params.update_order_pay_payment_method_nonce
            },
            success: function ( response ) {
                if ( response.success && response.data && response.data.totals_html ) {
                    // Update the order totals inline (no full-page reload) using the
                    // markup returned by the server. Only mark the method as applied once
                    // the totals were actually swapped in, so a success response with an
                    // empty `totals_html` (e.g. a failed order re-fetch server-side) leaves
                    // the method un-applied and re-selecting it retries.
                    $( '#order_review table.shop_table tfoot' ).html( response.data.totals_html );
                    appliedPaymentMethod = paymentMethod;

                    $placeOrderBtn.prop( 'disabled', false ).text( originalText );
                    isUpdating = false;
                } else {
                    // Show error message.
                    $placeOrderBtn.prop( 'disabled', false ).text( originalText );
                    isUpdating = false;
                    window.alert( ( response.data && response.data.message ) || wp.i18n.__( 'Error updating payment method. Please try again.', 'woocommerce-wholesale-prices-premium' ) );
                }
            },
            error: function () {
                // Show error message.
                $placeOrderBtn.prop( 'disabled', false ).text( originalText );
                isUpdating = false;
                window.alert( wp.i18n.__( 'Error updating payment method. Please try again.', 'woocommerce-wholesale-prices-premium' ) );
            }
        } );
    } );

    // Show a brief message when page loads to indicate surcharge functionality.
    if ( $( 'input[name="payment_method"]' ).length > 0 ) {
        var $notice = $( '<div class="woocommerce-info" style="margin-bottom: 20px;"><p><strong>' +
            wp.i18n.__( 'Note:', 'woocommerce-wholesale-prices-premium' ) +
            '</strong> ' +
            wp.i18n.__( 'Payment method surcharges will be automatically applied or removed when you change payment methods.', 'woocommerce-wholesale-prices-premium' ) +
            '</p></div>' );
        $( '.woocommerce-order-pay' ).prepend( $notice );

        // Auto-hide the notice after 5 seconds.
        setTimeout( function () {
            $notice.fadeOut();
        }, 5000 );
    }

} );
