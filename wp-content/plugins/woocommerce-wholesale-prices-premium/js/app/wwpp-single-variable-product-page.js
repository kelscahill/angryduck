jQuery( document ).ready( function( $ ) {
    
    /**
     * Update variation price quantity field value.
     * 
     * @since 1.0.0
     * @since 1.16.7
     * WWPP-585
     * There are bugs when a variable product have more than 30 variations. 
     * 1.) The min order quantity and step values are not set on the quantity field.
     * 2.) The min order quantity and step values notice markup is not shown when variations of a variable product have the same price ( same regular and same wholesale price ).
     * 
     * The reason for this is if a variable product have more than 30 variations, it will have different behavior on getting variations data versus when it has only 30 or less variations.
     * When 30 or less variations, it will append the variation data on the form markup as a data attribute.
     * When more than 30, it will fetch the variations data via ajax on the backend.
     * 
     * We refactor the codebase of this function to efficiently cater both cases of non-ajax and ajax based retrieval of variations data.
     * 
     * @param object event     Event object.
     * @param object variation Variation data.
     */
    function update_variation_price_quantity_field_value( event , variation ) {

        var $variations_form = $( ".variations_form" ),
            $qty_field       = $variations_form.find( ".variations_button .qty" );

        if ( variation ) {

            $qty_field.val( 1 ).attr( 'step' , 1 ).attr( 'min' , 1 );

            if ( variation.input_value )
                $qty_field.val( variation.input_value );
            else
                $qty_field.val( variation.min_qty );

            $qty_field.attr( 'step' , variation.step );

            if ( variation.min_value )
                $qty_field.attr( 'min' , variation.min_value );
            else
                $qty_field.attr( 'min' , variation.min_qty );

            // WWPP-577
            $qty_field.trigger( 'change' );

        }
        
    }

    $( "body" ).on( "woocommerce_variation_has_changed" , ".variations_form" , update_variation_price_quantity_field_value );
    $( "body" ).on( "found_variation" , ".variations_form" , update_variation_price_quantity_field_value ); // Only triggered on ajax complete

} );
