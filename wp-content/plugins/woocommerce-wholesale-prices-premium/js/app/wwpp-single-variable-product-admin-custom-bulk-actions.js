/* global jQuery */
jQuery( document ).ready( function( $ ) {

    function register_wholesale_custom_data_bulk_action() {

        var wholesale_roles = wwpp_custom_bulk_actions_params.wholesale_roles;
    
        function wholesale_role_wholesale_sale_prices( event , data ) {
            
            var value = window.prompt( wp.i18n.__( 'Enter a value (leave blank to remove wholesale sale price)', 'woocommerce-wholesale-prices-premium' ) );
    
            if ( value === null || value === undefined ) {
                return;
            } else {
                data.value = value;
                return data;
            }
    
            return data;
    
        }
    
        function wholesale_role_wholesale_minimum_order_quantity( event , data ) {
    
            var value = window.prompt( wp.i18n.__( 'Enter a value (leave blank to remove min order quantity)', 'woocommerce-wholesale-prices-premium' ) );
    
            if ( value === null || value === undefined ) {
                return;
            } else {
                data.value = value;
                return data;
            }
    
            return data;
    
        }
    
        function wholesale_role_wholesale_order_quantity_step( event , data ) {
    
            var value = window.prompt( wp.i18n.__( 'Enter a value (leave blank to remove order quantity step)', 'woocommerce-wholesale-prices-premium' ) );
    
            if ( value === null || value === undefined ) {
                return;
            } else {
                data.value = value;
                return data;
            }
    
            return data;
    
        }
    
        for ( var role in wholesale_roles ) {
    
            if ( wholesale_roles.hasOwnProperty( role ) ) {
    
                $( 'select.variation_actions' ).on( role + "_wholesale_sale_price_ajax_data" , wholesale_role_wholesale_sale_prices );
                $( 'select.variation_actions' ).on( role + "_wholesale_min_order_qty_ajax_data" , wholesale_role_wholesale_minimum_order_quantity );
                $( 'select.variation_actions' ).on( role + "_wholesale_order_qty_step_ajax_data" , wholesale_role_wholesale_order_quantity_step );
    
            }
            
        }
    }

    register_wholesale_custom_data_bulk_action();

    // When variation attribute changes, re-register wholesale price bulk action
    $( '#variable_product_options' ).on( 'reload' , function() {

        register_wholesale_custom_data_bulk_action();

    } );

} );
