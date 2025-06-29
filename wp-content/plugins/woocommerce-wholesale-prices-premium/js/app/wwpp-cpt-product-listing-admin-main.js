/* global jQuery */
jQuery( document ).ready( function( $ ) {

    // we create a copy of the WP inline edit post function
	var $wp_inline_edit = inlineEditPost.edit;

    // and then we overwrite the function with our own code
	inlineEditPost.edit = function( id ) {

		// "call" the original WP edit function
		// we don't want to leave WordPress hanging
		$wp_inline_edit.apply( this , arguments );

		// now we take care of our business

		// get the post ID
		var $post_id = 0;
		if ( typeof( id ) == 'object' ) {
			$post_id = parseInt( this.getId( id ) );
		}

		if ( $post_id > 0 ) {
			// define the edit row
			var $edit_row = $( '#edit-' + $post_id ),
			    $post_row = $( '#post-' + $post_id );

			// get the data
            var $minimum_order_quantity_datas = $( '.wholesale_minimum_order_quantity_data' , $post_row ),
                $order_quantity_step_datas    = $( '.wholesale_order_quantity_step_data'    , $post_row ),
                $product_visibility_roles     = $( '.wholesale_product_visibility_data'     , $post_row ).data( 'selected_roles' );

            $minimum_order_quantity_datas.each( function() {

                var $role  = $( this ).data( 'role' ),
                    $value = $( this ).text();

                // populate the data
    			$( ':input[name="' + $role + '_simple_wholesale_minimum_order_quantity"]' , $edit_row ).val( $value );

            } );

            $order_quantity_step_datas.each( function() {

                var $role  = $( this ).data( 'role' ),
                    $value = $( this ).text()

                // populate the data
                $( ':input[name="' + $role + '_simple_wholesale_order_quantity_step"]' , $edit_row ).val( $value );

            } );

            // enable Chosen() on product visibility field and populate values
            $( "#wholesale-visibility-select" , $edit_row ).chosen().val( $product_visibility_roles ).trigger( 'chosen:updated' );

            /**
             * Only show wholesale price custom field for appropriate types of products (simple)
             */
            var $wc_inline_data = $( '#woocommerce_inline_' + $post_id ),
                $product_type = $wc_inline_data.find( '.product_type' ).text(),
                $allowed_product_types = $( '.wholesale_custom_quick_edit_fields_allowed_product_types' , $post_row ).data( 'product_types' );

            if ( jQuery.inArray( $product_type , $allowed_product_types ) >= 0 ) {
                $( '.wwpp_quick_edit_fields' , $edit_row ).show();
            } else {
                $( '.wwpp_quick_edit_fields' , $edit_row ).hide();
            }
		}
	};
} );
