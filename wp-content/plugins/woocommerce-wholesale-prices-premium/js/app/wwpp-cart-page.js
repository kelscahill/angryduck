/** global jQuery */

jQuery( document ).ready( function( $ ) {

    var html = [];
    
    function filter_unique_wwpp_notices() {

        var $wc_info = $( this ).closest( '.woocommerce-info' );

        if ( $.inArray( $wc_info.html() , html ) === -1 )
            html.push( $wc_info.html() );
        else
            $wc_info.remove();

    }

    $( "body" ).on( 'updated_cart_totals' , function() {
        
        html = [];

        $( "body" ).find( ".woocommerce-info .wwpp-notice" ).each( filter_unique_wwpp_notices );

    } );

    $( "body" ).find( ".woocommerce-info .wwpp-notice" ).each( filter_unique_wwpp_notices );
    
} );