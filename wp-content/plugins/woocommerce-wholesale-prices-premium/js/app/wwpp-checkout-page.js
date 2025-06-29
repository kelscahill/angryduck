jQuery( document ).ready( function ( $ ) {

    // Trigger update of checkout calculation when payment gateway is changed
    $( document.body ).on( 'change' , 'input[name="payment_method"]' , function() {
        $( 'body' ).trigger( 'update_checkout' );
    } );

} );