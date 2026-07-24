/*
 * Single Product Page
 * Position the quantity discount table at the merchant configured location.
 *
 * The table is rendered by the server inside a .wwpp-quantity-discount-table-position container. This script moves
 * that container to the chosen spot relative to the real product summary elements so the position is correct
 * regardless of how the active theme structures the single product summary. For variable products, where the table
 * is injected via ajax once a variation is selected, the variation table is moved into the container as well.
 */
jQuery( document ).ready( function( $ ) {

    var position = ( typeof wwpp_quantity_discount_table_position_params !== 'undefined' && wwpp_quantity_discount_table_position_params.position )
            ? wwpp_quantity_discount_table_position_params.position
            : 'after_price',
        $container = $( '.wwpp-quantity-discount-table-position' );

    if ( position === 'after_price' || ! $container.length ) {
        return;
    }

    var $summary = $( '.product .summary, .product .entry-summary' ).first();

    if ( ! $summary.length ) {
        return;
    }

    // Move the container next to the relevant summary element.
    var position_container = function() {

        if ( position === 'before_add_to_cart' ) {
            var $cart = $summary.find( 'form.cart' ).first();
            if ( $cart.length ) {
                $container.insertBefore( $cart );
                return;
            }
        }

        // 'after_short_description', and the fallback when there is no add to cart form.
        var $excerpt = $summary.find( '.woocommerce-product-details__short-description' ).first();
        if ( $excerpt.length ) {
            $container.insertAfter( $excerpt );
            return;
        }

        var $price = $summary.find( '.price' ).first();
        if ( $price.length ) {
            $container.insertAfter( $price );
        }

    };

    // Variable product tables are injected via ajax inside .single_variation_wrap, so move the selected variation's
    // table into the configured container. The container reflects the current variation: when the selected variation
    // has a table it is moved in (replacing any previous one), otherwise the container is cleared so a stale table
    // from a previously selected variation is not shown.
    var relocate_variation_table = function() {

        var $table = $summary.find( '.single_variation_wrap' ).find( '.order-quantity-based-wholesale-pricing-view' );

        if ( $table.length ) {
            $container.empty().append( $table );
        } else {
            $container.empty();
        }

    };

    position_container();

    // found_variation can fire before the table markup is injected, while show_variation fires once the variation is
    // fully rendered; hide_variation fires when no variation is selected. Handling all three keeps the container in
    // sync with the current selection.
    $summary.find( '.variations_form' ).on( 'found_variation show_variation hide_variation', relocate_variation_table );

} );
