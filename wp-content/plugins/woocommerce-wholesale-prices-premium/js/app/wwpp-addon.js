/* 
* Single Product Page
* Wholesale Quantity Based Discount + Addon Compatibility
*/
jQuery( 'document' ).ready( function( $ ) {
    
    var wholesale_price = null,
        qty = $( '.cart .quantity' ).find( 'input.qty' ).val(),
        product_wholesale_price = $( '.product .summary' ).find( '.order-quantity-based-wholesale-pricing-view' ).data( 'wholesale_price' ),
        product_qty_mapping     = $( '.product .summary' ).find( '.order-quantity-based-wholesale-pricing-view' ).data( 'product_quantity_mapping' ),
        wholesale_role          = wwpp_single_product_page_addon_params.wholesale_role,
        
        wholesale_price_update = function() {

            if( product_wholesale_price != "" && product_qty_mapping && wholesale_role != "" ) {
                
                wholesale_price = product_wholesale_price;

                jQuery.each( product_qty_mapping , function( i , val ) {

                    // Simple / Variable / Variation / Category / General Qty Discount Levels
                    if( ( qty >= parseFloat( val[ 'start_qty' ] ) ) &&
                        ( val[ 'end_qty' ] == "" || qty <= parseFloat( val[ 'end_qty' ] ) ) &&
                        ( val[ 'wholesale_price' ] != undefined ) && val[ 'wholesale_role' ] == wholesale_role ) {

                        if( val[ 'price_type' ] != '' ) {

                            switch( val[ 'price_type' ] ) {

                                case 'fixed-price':
                                    wholesale_price = val[ 'wholesale_price' ];
                                    break;

                                case 'percent-price':
                                    wholesale_price = product_wholesale_price - ( ( val[ 'wholesale_price' ] / 100 ) * product_wholesale_price );
                                    wholesale_price = wholesale_price.toFixed( 2 );
                                    break;

                            }

                        }

                    }

                } );

                if( wholesale_price != null ) {
                    
                    $( '#product-addons-total' ).data( 'price' , wholesale_price );
                    $( '#product-addons-total' ).data( 'raw-price' , wholesale_price );
                    
                    // Update price using addon function
                    $( 'body' ).find( '.cart:not(.cart_group)' ).trigger( 'woocommerce-product-addons-update' );
                }

            }

        };

    // On load check qty discount if met
    wholesale_price_update();

    // Quantity change
    $( '.cart .quantity' ).find( 'input.qty' ).bind( 'keyup mouseup' , function () {
        
        qty = $( this ).val();
        wholesale_price_update();

    } );

    // Variation Change
    $( '.cart .variations .value' ).find( 'select' ).on( 'change' , function( e ) {
            
        if( $( this ).val() ) {

            setTimeout( function() {

                qty = $( '.cart .quantity' ).find( 'input.qty' ).val();

                var wholesale_price  = $( '.single_variation_wrap' ).find( '.order-quantity-based-wholesale-pricing-view' ).data( 'wholesale_price' ),
                    prod_qty_mapping = $( '.single_variation_wrap' ).find( '.order-quantity-based-wholesale-pricing-view' ).data( 'product_quantity_mapping' );

                product_wholesale_price = wholesale_price;
                product_qty_mapping     = prod_qty_mapping;
                
                wholesale_price_update();
                
            } , 100 );

        }

    } );

} );