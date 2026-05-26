jQuery( document ).ready( function ( $ ) {

    var isUpdating = false;

    // Trigger update when payment method is changed on order pay page
    $( document.body ).on( 'change', 'input[name="payment_method"]', function() {
        var $this = $( this );
        var paymentMethod = $this.val();
        var $placeOrderBtn = $( '#place_order' );
        var originalText = $placeOrderBtn.text();
        
        // Prevent multiple simultaneous requests
        if ( isUpdating || $placeOrderBtn.prop( 'disabled' ) ) {
            return;
        }
        
        isUpdating = true;
        
        // Show loading indicator
        $placeOrderBtn.prop( 'disabled', true ).text( 'Updating...' );
        
        // Send AJAX request to update the session and recalculate surcharges
        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wwpp_update_order_pay_payment_method',
                payment_method: paymentMethod,
                order_id: wc_checkout_params.order_id,
                order_key: wc_checkout_params.order_key || '',
                nonce: wc_checkout_params.update_order_pay_payment_method_nonce
            },
            success: function( response ) {
                if ( response.success ) {
                    // Reload the page to show updated totals
                    window.location.reload();
                } else {
                    // Show error message
                    $placeOrderBtn.prop( 'disabled', false ).text( originalText );
                    isUpdating = false;
                    alert( response.data.message || 'Error updating payment method. Please try again.' );
                }
            },
            error: function() {
                // Show error message
                $placeOrderBtn.prop( 'disabled', false ).text( originalText );
                isUpdating = false;
                alert( 'Error updating payment method. Please try again.' );
            }
        });
    });

    // Show a brief message when page loads to indicate surcharge functionality
    if ( $( 'input[name="payment_method"]' ).length > 0 ) {
        var $notice = $( '<div class="woocommerce-info" style="margin-bottom: 20px;">' +
            '<p><strong>Note:</strong> Payment method surcharges will be automatically applied or removed when you change payment methods.</p>' +
            '</div>' );
        $( '.woocommerce-order-pay' ).prepend( $notice );
        
        // Auto-hide the notice after 5 seconds
        setTimeout( function() {
            $notice.fadeOut();
        }, 5000 );
    }

} ); 