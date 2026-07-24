<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Wholesale_Price_Percentage_Sync' ) ) {

    /**
     * Model that keeps the computed {role}_wholesale_price meta in sync with the regular price
     * whenever a product uses a per-product percentage wholesale discount
     * ({role}_wholesale_percentage_discount).
     *
     * The admin product editor recalculates the stored wholesale price in JavaScript, but
     * WooCommerce REST updates, CSV imports, and programmatic price changes never run that JS.
     * As a result the stored wholesale price can drift out of sync with the regular price.
     * Storefront pricing reads the stored {role}_wholesale_price meta, so wholesale customers
     * would otherwise see stale prices until someone re-saved the product in wp-admin.
     *
     * This model hooks into the canonical save actions (woocommerce_new_product /
     * woocommerce_update_product) so REST, imports, and programmatic saves are all covered,
     * recomputing the wholesale price from regular x (1 - %/100) using the same formula and
     * filters as the admin save path.
     *
     * @since 2.1.1
     */
    class WWPP_Wholesale_Price_Percentage_Sync {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_Wholesale_Price_Percentage_Sync.
         *
         * @since 2.1.1
         * @access private
         * @var WWPP_Wholesale_Price_Percentage_Sync
         */
        private static $_instance;

        /**
         * Instance of WWPP_Wholesale_Roles model.
         *
         * @since 2.1.1
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Re-entrancy guard. Set while firing the category-fallback action, whose handler performs
         * a full $product->save() that re-dispatches woocommerce_update_product. Prevents this hook
         * from recursing into itself during that nested save.
         *
         * @since 2.1.1
         * @access private
         * @var bool
         */
        private static $is_recalculating = false;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_Wholesale_Price_Percentage_Sync constructor.
         *
         * @since 2.1.1
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Price_Percentage_Sync model.
         */
        public function __construct( $dependencies = array() ) {

            if ( isset( $dependencies['WWPP_Wholesale_Roles'] ) ) {
                $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
            }
        }

        /**
         * Ensure that only one instance of WWPP_Wholesale_Price_Percentage_Sync is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.1.1
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Price_Percentage_Sync model.
         * @return WWPP_Wholesale_Price_Percentage_Sync
         */
        public static function instance( $dependencies = array() ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Recalculate the percentage based wholesale price for a product on save.
         *
         * Fired on woocommerce_new_product and woocommerce_update_product so REST API updates,
         * CSV imports, and programmatic saves keep {role}_wholesale_price in sync with the
         * regular price. Products that only use a fixed wholesale price (no percentage meta)
         * are left untouched.
         *
         * @since 2.1.1
         * @access public
         *
         * @param int             $product_id The product ID being saved.
         * @param WC_Product|null $product    The product object being saved. May be null on
         *                                     older WooCommerce versions that only pass the ID.
         */
        public function recalculate_percentage_wholesale_price( $product_id, $product = null ) {

            if ( ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( $product_id );
            }

            if ( ! is_a( $product, 'WC_Product' ) ) {
                return;
            }

            // The category-fallback action fired at the end of this method performs a full product
            // save, which re-dispatches woocommerce_update_product. Bail out of the nested re-entry
            // so the fallback resolution is not repeated (and never loops).
            if ( self::$is_recalculating ) {
                return;
            }

            /**
             * Allow merchants to opt out of the automatic percentage wholesale price recalculation.
             *
             * @since 2.1.1
             *
             * @param bool       $enabled Whether the recalculation should run. Default true.
             * @param WC_Product $product The product being saved.
             */
            if ( ! apply_filters( 'wwp_recalc_percentage_wholesale_enabled', true, $product ) ) {
                return;
            }

            /**
             * Product types whose percentage wholesale price should be recalculated on save.
             *
             * @since 2.1.1
             *
             * @param array $product_types Array of supported product type slugs.
             */
            $supported_types = apply_filters(
                'wwp_recalc_percentage_wholesale_product_types',
                array( 'simple', 'variation', 'subscription', 'variable-subscription', 'subscription_variation', 'bundle', 'composite' )
            );

            if ( ! in_array( $product->get_type(), $supported_types, true ) ) {
                return;
            }

            $roles = $this->get_registered_wholesale_roles();
            if ( empty( $roles ) ) {
                return;
            }

            $regular_price = $product->get_regular_price( 'edit' );
            if ( '' === $regular_price || null === $regular_price ) {
                return;
            }

            $regular_price = (float) wc_format_decimal( $regular_price );
            if ( $regular_price <= 0 ) {
                return;
            }

            // Resolve the product type used by the wwp_before_save filter, mirroring the admin save path.
            $product_type = class_exists( 'WWP_Helper_Functions' ) ? WWP_Helper_Functions::wwp_get_product_type( $product ) : $product->get_type();
            if ( ! is_string( $product_type ) || '0' === $product_type ) {
                $product_type = $product->get_type();
            }

            $changed        = false;
            $fallback_roles = array();

            foreach ( array_keys( $roles ) as $role_key ) {

                // Only products configured with a per-product percentage discount are recalculated.
                // Reading through the CRUD/data-store layer returns an empty string when the meta
                // is absent, so the empty/non-numeric check below safely handles the skip without a
                // direct postmeta query.
                $percentage = $product->get_meta( $role_key . '_wholesale_percentage_discount', true );
                if ( '' === $percentage || ! is_numeric( $percentage ) ) {
                    continue;
                }

                $percentage = (float) $percentage;
                if ( $percentage < 0 || $percentage > 100 ) {
                    continue;
                }

                if ( $percentage >= 100 ) {
                    $new_wholesale_price = '0';
                } else {
                    $new_wholesale_price = wc_format_decimal( $regular_price - ( $regular_price * ( $percentage / 100 ) ) );
                }

                // WWP fires the variation filter with a 7-arg signature where the parent variable
                // product ID is the 4th argument, while the simple/other-type filter uses a 6-arg
                // signature without it. Branch so each matches WWP's contract and consumers (e.g.
                // the Aelia Currency Switcher) read the correct argument positions for variations.
                // WC_Product_Subscription_Variation extends WC_Product_Variation, so the is_a()
                // check also routes subscription variations (get_type() === 'subscription_variation')
                // through the variation filter, matching WWP's admin save path which always fires
                // wwp_before_save_variation_product_wholesale_price for every variation type.
                if ( is_a( $product, 'WC_Product_Variation' ) ) {
                    /**
                     * Filter the computed variation wholesale price before it is persisted, for parity with the admin save path.
                     *
                     * @since 2.1.1
                     *
                     * @param string $new_wholesale_price             The computed wholesale price.
                     * @param string $role_key                        The wholesale role key.
                     * @param int    $variation_id                    The variation product ID.
                     * @param int    $variable_id                     The parent variable product ID.
                     * @param bool   $aelia_currency_switcher_active  Whether the Aelia currency switcher is active.
                     * @param bool   $is_base_currency                Whether this is the base currency.
                     * @param mixed  $currency_code                   The currency code, or null.
                     */
                    $new_wholesale_price = apply_filters(
                        'wwp_before_save_variation_product_wholesale_price',
                        $new_wholesale_price,
                        $role_key,
                        $product->get_id(),
                        $product->get_parent_id(),
                        false,
                        true,
                        null
                    );
                } else {
                    /**
                     * Filter the computed wholesale price before it is persisted, for parity with the admin save path.
                     *
                     * @since 2.1.1
                     *
                     * @param string $new_wholesale_price             The computed wholesale price.
                     * @param string $role_key                        The wholesale role key.
                     * @param int    $product_id                      The product ID.
                     * @param bool   $aelia_currency_switcher_active  Whether the Aelia currency switcher is active.
                     * @param bool   $is_base_currency                Whether this is the base currency.
                     * @param mixed  $currency_code                   The currency code, or null.
                     */
                    $new_wholesale_price = apply_filters(
                        'wwp_before_save_' . $product_type . '_product_wholesale_price',
                        $new_wholesale_price,
                        $role_key,
                        $product->get_id(),
                        false,
                        true,
                        null
                    );
                }

                $new_wholesale_price = wc_clean( $new_wholesale_price );

                $product->update_meta_data( $role_key . '_wholesale_price', $new_wholesale_price );

                // A per-product percentage price now governs this role, so clear any stale
                // category-fallback flag, mirroring the admin save path (WWP
                // class-wwp-admin-custom-fields-simple-product.php). Otherwise pricing/visibility
                // logic that reads _have_wholesale_price_set_by_product_cat would keep treating the
                // product as category-priced. When the percentage resolves to no price the admin
                // path then re-evaluates the category fallback; that firing is queued below.
                $product->delete_meta_data( $role_key . '_have_wholesale_price_set_by_product_cat' );

                if ( is_numeric( $new_wholesale_price ) && (float) $new_wholesale_price > 0 ) {
                    $product->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                } else {
                    $product->update_meta_data( $role_key . '_have_wholesale_price', 'no' );

                    // The product's own percentage resolved to no wholesale price (e.g. a 100%
                    // discount). Mirror the admin simple-product save path and fall back to a
                    // category-level wholesale discount if one applies (fired after the save below).
                    // Variations are excluded: their parent's wholesale meta is reconciled by the
                    // parent refresh below, matching the admin variable-product save path.
                    if ( ! is_a( $product, 'WC_Product_Variation' ) ) {
                        $fallback_roles[] = $role_key;
                    }
                }

                $changed = true;
            }

            if ( ! $changed ) {
                return;
            }

            // Persist only the recalculated wholesale meta. Using save_meta_data() instead of the
            // full $product->save() avoids re-firing woocommerce_update_product / _variation, which
            // would double-run WWPP_Cache::product_update and any third-party listener on every
            // percentage product save. Wholesale prices live in postmeta (not the WC product lookup
            // table), so no CRUD lifecycle re-run is required, and this mirrors the lighter
            // parent-refresh path below. The category-fallback firing afterwards does perform a full
            // save, but that is isolated behind the re-entrancy guard set around it.
            //
            // Rotate the product hash that stamps the "wholesale price on shop" v3 cache entries
            // (WWPP_Cache), mirroring WWPP_Cache::set_product_meta_hash(). The hash-rotation hooks
            // (save_post_product / woocommerce_save_product_variation) do not fire on REST, import,
            // or programmatic variation saves, so without this the recomputed price would be written
            // to meta but the cache would keep serving the stale price to wholesale customers.
            $product->update_meta_data( 'wwpp_product_hash', uniqid( '', true ) );

            $product->save_meta_data();

            // Mirror the admin simple-product save path: for any role whose own wholesale price
            // resolved to nothing, fall back to a category-level wholesale discount when one
            // applies. The handler re-fetches and fully saves the product, so it runs last (its
            // category values win over the meta persisted above) and under the re-entrancy guard so
            // the resulting woocommerce_update_product does not recurse back into this method.
            if ( ! empty( $fallback_roles ) ) {
                self::$is_recalculating = true;
                try {
                    foreach ( $fallback_roles as $fallback_role_key ) {
                        do_action( 'wwp_set_have_wholesale_price_meta_prod_cat_wholesale_discount', $product->get_id(), $fallback_role_key );
                    }
                } finally {
                    self::$is_recalculating = false;
                }
            }

            // Refresh the variable parent min/max / have-wholesale meta, mirroring the admin variation
            // save. is_a() covers both plain and subscription variations (the latter reports
            // get_type() === 'subscription_variation', so is_type( 'variation' ) would miss it).
            if ( is_a( $product, 'WC_Product_Variation' ) ) {
                $this->sync_variable_parent_wholesale_meta( $product );
            }
        }

        /**
         * Refresh the parent variable product wholesale min/max / have-wholesale meta after a
         * variation's percentage wholesale price has been recalculated.
         *
         * Mirrors the parent meta refresh performed by the admin variable product save path.
         *
         * @since 2.1.1
         * @access private
         *
         * @param WC_Product_Variation $variation The variation product whose parent should be refreshed.
         */
        private function sync_variable_parent_wholesale_meta( $variation ) {

            if ( ! is_a( $variation, 'WC_Product_Variation' ) || ! class_exists( 'WWP_Helper_Functions' ) ) {
                return;
            }

            $parent_id = $variation->get_parent_id();
            if ( ! $parent_id ) {
                return;
            }

            $parent = wc_get_product( $parent_id );
            if ( ! $parent || ! $parent->is_type( array( 'variable', 'variable-subscription' ) ) ) {
                return;
            }

            $wholesale_roles = $this->get_registered_wholesale_roles();
            $children        = $parent->get_children();

            if ( empty( $wholesale_roles ) || empty( $children ) ) {
                return;
            }

            $wholesale_prices_per_role = WWP_Helper_Functions::get_wholesale_prices_per_role_from_variations( $children, $wholesale_roles );
            $needs_save                = false;

            foreach ( $wholesale_roles as $role_key => $role ) {
                // Clear any stale category-fallback flag on the parent, mirroring the admin variable
                // product save path (WWP class-wwp-admin-custom-fields-variable-product.php).
                $parent->delete_meta_data( $role_key . '_have_wholesale_price_set_by_product_cat' );

                if ( ! empty( $wholesale_prices_per_role[ $role_key ] ) ) {
                    $prices = $wholesale_prices_per_role[ $role_key ];
                    $parent->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                    $parent->update_meta_data( $role_key . '_min_wholesale_price', min( $prices ) );
                    $parent->update_meta_data( $role_key . '_max_wholesale_price', max( $prices ) );
                } else {
                    $parent->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                    $parent->delete_meta_data( $role_key . '_min_wholesale_price' );
                    $parent->delete_meta_data( $role_key . '_max_wholesale_price' );
                }

                $needs_save = true;
            }

            if ( $needs_save ) {
                // Rotate the parent's v3 "wholesale price on shop" cache hash too, mirroring the
                // parent-hash bump in WWPP_Cache::set_product_meta_hash(): the parent feeds its own
                // simple/variation cache entry and its price-range cache, neither of which is busted
                // on the REST/import variation save path.
                $parent->update_meta_data( 'wwpp_product_hash', uniqid( '', true ) );

                $parent->save_meta_data();

                if ( class_exists( 'WWP_Wholesale_Prices' ) && method_exists( 'WWP_Wholesale_Prices', 'clear_wholesale_price_range_cache' ) ) {
                    WWP_Wholesale_Prices::clear_wholesale_price_range_cache();
                }
            }
        }

        /**
         * Get all registered wholesale roles, preferring the injected WWPP_Wholesale_Roles model
         * and falling back to the global WWP instance when the dependency is unavailable.
         *
         * @since 2.1.1
         * @access private
         *
         * @return array Array of registered wholesale roles keyed by role key.
         */
        private function get_registered_wholesale_roles() {

            if ( is_object( $this->_wwpp_wholesale_roles ) && method_exists( $this->_wwpp_wholesale_roles, 'getAllRegisteredWholesaleRoles' ) ) {
                $roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            } elseif ( class_exists( 'WWP_Wholesale_Roles' ) ) {
                $roles = WWP_Wholesale_Roles::getInstance()->getAllRegisteredWholesaleRoles();
            } else {
                $roles = array();
            }

            return is_array( $roles ) ? $roles : array();
        }

        /**
         * Execute model. Register the save hooks.
         *
         * WooCommerce fires woocommerce_new_product / woocommerce_update_product for top-level
         * products (simple, variable, subscription, bundle, composite) and the dedicated
         * woocommerce_new_product_variation / woocommerce_update_product_variation actions for
         * variations, so all four are hooked to cover REST, imports, and programmatic saves.
         *
         * @since 2.1.1
         * @access public
         */
        public function run() {

            add_action( 'woocommerce_new_product', array( $this, 'recalculate_percentage_wholesale_price' ), 99, 2 );
            add_action( 'woocommerce_update_product', array( $this, 'recalculate_percentage_wholesale_price' ), 99, 2 );
            add_action( 'woocommerce_new_product_variation', array( $this, 'recalculate_percentage_wholesale_price' ), 99, 2 );
            add_action( 'woocommerce_update_product_variation', array( $this, 'recalculate_percentage_wholesale_price' ), 99, 2 );
        }
    }
}
