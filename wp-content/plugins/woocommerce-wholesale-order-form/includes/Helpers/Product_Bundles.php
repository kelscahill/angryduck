<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * Product_Bundles helper class.
 *
 * Provides plugin-active detection for the WooCommerce Product Bundles
 * integration and a set of small utilities used by the integration class.
 *
 * @since 3.1.0
 */
class Product_Bundles {

    /**
     * The minimum supported version of the WooCommerce Product Bundles plugin.
     *
     * The programmatic add-to-cart API (`WC_PB()->cart->add_bundle_to_cart()`)
     * and the Store API extension (`WC_PB_Store_API`) were both verified
     * available in PB 8.3.
     *
     * @since 3.1.0
     * @var string
     */
    const MINIMUM_PB_VERSION = '8.3';

    /**
     * Check if WooCommerce Product Bundles is active and at the required version.
     *
     * @since 3.1.0
     * @return bool
     */
    public static function is_active() {

        if ( ! class_exists( 'WC_Bundles' ) ) {
            return false;
        }

        if ( defined( 'WC_PB_VERSION' ) && version_compare( WC_PB_VERSION, self::MINIMUM_PB_VERSION, '<' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the default variation id for a variable bundled child item.
     *
     * `WC_Bundled_Item` does not expose a `get_default_variation_id()` method.
     * Use the attribute set returned by `get_selected_product_variation_attributes()`
     * and look up the matching variation via the product data store.
     *
     * @param \WC_Bundled_Item $bundled_item The bundled item instance.
     *
     * @since 3.1.0
     * @return int The resolved variation id, or 0 if no match is found.
     */
    public static function get_default_variation_id( $bundled_item ) {

        $parent = $bundled_item->product;

        if ( ! $parent || 'variable' !== $parent->get_type() ) {
            return 0;
        }

        $attributes = $bundled_item->get_selected_product_variation_attributes();

        if ( empty( $attributes ) ) {
            return 0;
        }

        $data_store   = \WC_Data_Store::load( 'product' );
        $variation_id = $data_store->find_matching_product_variation( $parent, $attributes );

        return (int) $variation_id;
    }

    /**
     * Get the wholesale price for a bundled child product for the current user role.
     *
     * `WC_Bundled_Item` price getters are not wholesale-aware and WWP does not hook
     * `woocommerce_product_get_price`. Source the wholesale price directly from
     * WWP's shop v3 helper so per-child pricing in the REST response is correct.
     *
     * WWP's helper ultimately calls `get_product_raw_wholesale_price()` which
     * reads `$user_wholesale_role[0] . '_wholesale_price'` — so it expects an
     * array even though `getUserWholesaleRole()` can return either form. A
     * plain string falls through to the first character and yields an empty
     * lookup. Wrap to guarantee the array shape WWP expects regardless of
     * what the caller passes.
     *
     * Variable children are NOT handled here — variable parents carry no
     * `{role}_wholesale_price` postmeta, and the bundled-items REST path
     * (`Integrations\Product_Bundles::add_bundle_data_to_response()`)
     * computes the per-variation map directly via
     * `get_variation_wholesale_price_map()` and derives the item-level
     * MIN via `min_from_wholesale_price_map()` — see those helpers for
     * the variable-child resolution.
     *
     * @param int             $child_product_id The child product id (simple / grouped / composite).
     * @param string|string[] $wholesale_role   The wholesale role slug or single-element array.
     *
     * @since 3.1.0
     * @return string The wholesale price, or empty string if none.
     */
    public static function get_wholesale_price_for_child( $child_product_id, $wholesale_role ) {

        if ( empty( $wholesale_role ) || ! class_exists( 'WWP_Wholesale_Prices' ) ) {
            return '';
        }

        $role_array = is_array( $wholesale_role ) ? $wholesale_role : array( $wholesale_role );
        $price_arr  = \WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $child_product_id, $role_array );

        return isset( $price_arr['wholesale_price'] ) ? (string) $price_arr['wholesale_price'] : '';
    }

    /**
     * Build a `variation_id => wholesale_price` map for a set of variation ids.
     *
     * Single-pass alternative to calling `get_wholesale_price_for_child()`
     * once per variation. Callers that need BOTH per-variation prices and
     * a derived aggregate (MIN/MAX/etc.) should build the map once and
     * reuse it instead of iterating twice — see
     * `Integrations\Product_Bundles::add_bundle_data_to_response()` where
     * the per-variation map feeds `available_variations[].price` AND the
     * item-level `wholesale_price` (via `min_from_wholesale_price_map()`)
     * in one pass.
     *
     * Variations without a wholesale price for the given role are
     * omitted from the map (rather than mapped to `''`) so callers can
     * use `isset()` / `??` to distinguish "no wholesale meta" from "zero
     * wholesale price". Iteration cost is bounded by the variation
     * count, which PB itself already iterates for
     * `get_product_variations()`. Each variation's first wholesale-price
     * lookup reads postmeta from the DB; WordPress's `update_meta_cache`
     * then caches that variation's full meta set, so subsequent reads of
     * the SAME variation's meta within the request hit the cache.
     *
     * @param array<int,int|string> $variation_ids  Variation post ids.
     * @param string|string[]       $wholesale_role Wholesale role slug or array.
     *
     * @since 3.1.0
     * @return array<int,string> Map of variation_id => wholesale_price.
     *                           Empty when no role / WWP missing / no
     *                           variation has a wholesale price set.
     */
    public static function get_variation_wholesale_price_map( $variation_ids, $wholesale_role ) {

        if ( empty( $wholesale_role ) || ! class_exists( 'WWP_Wholesale_Prices' ) || empty( $variation_ids ) ) {
            return array();
        }

        $role_array = is_array( $wholesale_role ) ? $wholesale_role : array( $wholesale_role );
        $map        = array();

        foreach ( $variation_ids as $variation_id ) {

            $variation_id = (int) $variation_id;

            if ( $variation_id <= 0 ) {
                continue;
            }

            $price_arr = \WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $variation_id, $role_array );

            if ( ! isset( $price_arr['wholesale_price'] ) || '' === (string) $price_arr['wholesale_price'] ) {
                continue;
            }

            $map[ $variation_id ] = (string) $price_arr['wholesale_price'];
        }

        return $map;
    }

    /**
     * Return the minimum value from a wholesale-price map as a money string.
     *
     * Returns `''` for an empty map so callers downstream fall through
     * to PB's retail `price_html` (matching the empty-wholesale_price
     * contract the rest of the integration uses).
     *
     * Output is formatted via `wc_format_decimal()` so the MIN string
     * matches the precision and separator convention WC uses for stored
     * prices — a plain `(string) $min` on a float can emit `"100"`
     * instead of `"100.00"`, surface FP-precision artifacts, or fall
     * back to scientific notation for extreme values. The per-variation
     * map values come from WWP as stored postmeta strings, so
     * formatting the derived MIN through `wc_format_decimal()` keeps
     * the item-level and per-variation strings on the same shape.
     *
     * @param array<int,string> $wholesale_price_map Map produced by
     *                                               `get_variation_wholesale_price_map()`.
     *
     * @since 3.1.0
     * @return string The minimum wholesale price as a `wc_format_decimal()`-formatted string, or '' if the map is empty.
     */
    public static function min_from_wholesale_price_map( $wholesale_price_map ) {

        if ( empty( $wholesale_price_map ) ) {
            return '';
        }

        $min = null;

        foreach ( $wholesale_price_map as $price ) {

            $price_float = (float) $price;

            if ( null === $min || $price_float < $min ) {
                $min = $price_float;
            }
        }

        if ( null === $min ) {
            return '';
        }

        return function_exists( 'wc_format_decimal' ) ? (string) wc_format_decimal( $min ) : (string) $min;
    }
}
