<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that house various generic plugin helper functions.
 *
 * @since 1.12.8
 */
final class WWPP_Helper_Functions {

    /**
     * Check if specific user is wwpp tax exempted.
     *
     * @param int    $user_id             User id.
     * @param string $user_wholesale_role User wholesale role.
     *
     * @since  1.16.0
     * @since  1.30 Added integration with third party plugins.
     * @access public
     *
     * @return string 'yes' if wwpp tax exempted, 'no' if otherwise.
     */
    public static function is_user_wwpp_tax_exempted( $user_id, $user_wholesale_role ) {

        $wwpp_tax_exempted = get_option( 'wwpp_settings_tax_exempt_wholesale_users' );

        $wholesale_role_tax_option_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );
        if ( ! is_array( $wholesale_role_tax_option_mapping ) ) {
            $wholesale_role_tax_option_mapping = array();
        }

        if ( array_key_exists( $user_wholesale_role, $wholesale_role_tax_option_mapping ) ) {
            $wwpp_tax_exempted = $wholesale_role_tax_option_mapping[ $user_wholesale_role ]['tax_exempted'];
        }

        $user_tax_exempted = get_user_meta( $user_id, 'wwpp_tax_exemption', true );
        if ( 'global' !== $user_tax_exempted && in_array( $user_tax_exempted, array( 'yes', 'no' ), true ) ) {
            $wwpp_tax_exempted = $user_tax_exempted;
        }

        // Integrate tax exemption with third party plugins.
        if ( ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) && is_object( WC()->customer ) ) {
            $wc_tax_exempted = WC()->customer->get_is_vat_exempt();

            // If the tax exemption is set by thidr party plugins. Do not touch tax exemptions for this customer.
            if ( 'yes' !== $wwpp_tax_exempted && $wc_tax_exempted ) {
                if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-germanized-pro/woocommerce-germanized-pro.php' ) ) {
                    $wwpp_tax_exempted = 'yes';
                }

                // Third party plugins that only implement the tax exemption on the cart and checkout page.
                if ( ( is_cart() || is_checkout() ) &&
                    ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-eu-vat-number/woocommerce-eu-vat-number.php' ) ||
                        WWP_Helper_Functions::is_plugin_active( 'woocommerce-eu-vat-assistant/woocommerce-eu-vat-assistant.php' ) ||
                        WWP_Helper_Functions::is_plugin_active( 'woocommerce-shipping-multiple-address/woocommerce-shipping-multiple-address.php' ) ||
                        WWP_Helper_Functions::is_plugin_active( 'woocommerce-eu-vat-compliance-premium/eu-vat-compliance-premium.php' ) ) ) {
                    $wwpp_tax_exempted = 'yes';
                }
            }
        }

        return $wwpp_tax_exempted;
    }

    /**
     * Filter a given price to make sure it is a valid price value if
     * WC currency options is set other than the default.
     * Defaults are, thousand separator is comma, decimal separator is a dot.
     *
     * @param string $price Price.
     *
     * @since  1.16.7
     * @access public
     *
     * @return string Filtered price.
     */
    public static function filter_price_for_custom_wc_currency_options( $price ) {

        $thousand_sep = get_option( 'woocommerce_price_thousand_sep' );
        $decimal_sep  = get_option( 'woocommerce_price_decimal_sep' );

        if ( $thousand_sep ) {
            $price = str_replace( $thousand_sep, '', $price );
        }

        if ( $decimal_sep ) {
            $price = str_replace( $decimal_sep, '.', $price );
        }

        return $price;
    }

    /**
     * Check if the current user have override per user wholesale discount.
     *
     * @param array $user_wholesale_role Wholesale Role.
     *
     * @since  1.23.4
     * @access public
     *
     * @return bool
     */
    public static function _wholesale_user_have_override_per_user_discount( $user_wholesale_role ) { // phpcs:ignore.
        $user_id = apply_filters( 'wwpp_get_current_user_id', get_current_user_id() );

        if ( ! empty( $user_id ) && 'yes' === get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) ) {
            $wholesale_role_discount = get_user_meta( $user_id, 'wwpp_wholesale_discount', true );

            // Check first if Per User wholesale discount is set.
            if ( ! empty( $wholesale_role_discount ) && is_numeric( $wholesale_role_discount ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current wholesale user have general discount set.
     *
     * @param string $user_wholesale_role Wholesale Role.
     *
     * @since  1.23.4
     * @access public
     *
     * @return bool
     */
    public static function _wholesale_user_have_general_role_discount( $user_wholesale_role ) {

        global $wc_wholesale_prices_premium;
        $user_wholesale_discount = $wc_wholesale_prices_premium->wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( get_current_user_id(), $user_wholesale_role );

        return ! empty( $user_wholesale_discount['discount'] ) ? true : false;
    }

    /**
     * Show the correct pricing when woocommerce multilingual is enabled.
     * Show correct discount when set per product, per category, general and override per user.
     *
     * @param int    $price   Product price for current currency.
     * @param object $product WC Product Object.
     *
     * @since  1.23.5
     * @access public
     *
     * @return int
     */
    public static function get_product_default_currency_price( $price, $product ) {

        if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
            global $woocommerce_wpml, $woocommerce;

            if ( WCML_MULTI_CURRENCIES_INDEPENDENT !== $woocommerce_wpml->settings['enable_multi_currency'] ) {
                return $price;
            }

            if ( null !== $woocommerce->session ) {
                $product_id       = WWP_Helper_Functions::wwp_get_product_id( $product );
                $default_currency = wcml_get_woocommerce_currency_option();
                $current_currency = $woocommerce_wpml->multi_currency->get_client_currency();

                if ( ! empty( $current_currency ) && ! empty( $default_currency ) ) {
                    if ( $current_currency !== $default_currency ) {
                        $helper = new WWPP_Helper_Functions();

                        add_filter( 'woocommerce_product_get_price', array( $helper, 'get_regular_price' ), 10, 2 );
                        add_filter( 'woocommerce_product_variation_get_price', array( $helper, 'get_regular_price' ), 10, 2 );
                        $price = apply_filters( 'wcml_product_price_by_currency', $product_id, $default_currency );
                        remove_filter( 'woocommerce_product_variation_get_price', array( $helper, 'get_regular_price' ), 10, 2 );
                        remove_filter( 'woocommerce_product_get_price', array( $helper, 'get_regular_price' ), 10, 2 );
                    }
                }
            }
        }

        return $price;
    }

    /**
     * WCML uses get_price reason why Use Regular Price feature is not working.
     * When filter is used grab the price from the regular instead of the sale price.
     *
     * @param int    $price   Product price.
     * @param object $product WC Product Object.
     *
     * @since  1.23.5
     * @access public
     *
     * @return int
     */
    public function get_regular_price( $price, $product ) {

        $use_regular_price = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );

        return 'yes' === $use_regular_price ? $product->get_regular_price() : $price;
    }

    /**
     * Check if WWOF is active
     *
     * @since  1.24
     * @access public
     *
     * @return bool
     */
    public static function is_wwof_active() {

        return WWP_Helper_Functions::is_plugin_active( 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php' ) ? true : false;
    }

    /**
     * Check if WWLC is active
     *
     * @since  1.24
     * @access public
     *
     * @return bool
     */
    public static function is_wwlc_active() {

        return WWP_Helper_Functions::is_plugin_active( 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php' ) ? true : false;
    }

    /**
     * WOOCS Compatibility. Calculate prices based on the selected currency.
     *
     * @param int $price Product Price.
     *
     * @access public
     * @since  1.26.2
     * @access public
     *
     * @return int
     */
    public static function woocs_exchange( $price ) {

        global $WOOCS;

        return $WOOCS ? $WOOCS->woocs_exchange_value( $price ) : $price;
    }

    /**
     * WOOCS Compatibility. Convert to default currency prices based on the selected currency.
     *
     * @param int $price Product Price.
     *
     * @access public
     * @since  1.27.4
     * @access public
     *
     * @return int
     */
    public static function woocs_back_convert( $price ) {

        global $WOOCS;

        return $WOOCS ? $WOOCS->woocs_back_convert_price( $price ) : $price;
    }

    /**
     * Check if the specific product is restricted in the category level.
     *
     * @param int    $product_id     The product id.
     * @param string $wholesale_role The wholesale role.
     *
     * @access public
     * @since  1.27
     * @access public
     *
     * @return bool
     */
    public static function is_product_restricted_in_category( $product_id, $wholesale_role ) {

        global $post;

        $product_cat_terms                 = get_the_terms( $product_id, 'product_cat' );
        $product_cat_wholesale_role_filter = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER );
        $has_blocked_cat                   = false;

        // Wholesale role product category filter.
        if ( ! empty( $product_cat_terms ) && ! empty( $product_cat_wholesale_role_filter ) ) {
            $product_cat_term_ids = array();

            foreach ( $product_cat_terms as $pct ) {
                $product_cat_term_ids[] = $pct->term_id;
            }

            if ( ! empty( $wholesale_role ) ) {
                foreach ( $product_cat_term_ids as $t_id ) {
                    if ( array_key_exists( $t_id, $product_cat_wholesale_role_filter ) && ! in_array( $wholesale_role, $product_cat_wholesale_role_filter[ $t_id ], true ) ) {
                        $has_blocked_cat = true;
                        break;
                    }
                }
            } else {
                $filtered_cat_term_ids = array_keys( $product_cat_wholesale_role_filter );
                $blocked_cat_ids       = array_intersect( $product_cat_term_ids, $filtered_cat_term_ids );

                if ( ! empty( $blocked_cat_ids ) ) {
                    $has_blocked_cat = true;
                }
            }
        }

        return $has_blocked_cat;
    }

    /**
     * Takes a multi-dimensional array and sanitizes the values with sanitize_text_field
     *
     * @param array $array_data The array to sanitize.
     *
     * @since  1.30.1
     * @access public
     * @return array The sanitized array.
     */
    public static function sanitize_array( $array_data ) {

        foreach ( $array_data as &$value ) {
            if ( ! is_array( $value ) ) {
                // Sanitize if value is not an array.
                $value = sanitize_text_field( $value );
            } else {
                // Recurse if array is found.
                self::sanitize_array( $value );
            }
        }

        return $array_data;
    }

    /**
     * Sanitizes a string role key.
     *
     * Keys are used as internal identifiers. Lowercase alphanumeric characters,
     * dashes, and underscores are allowed.
     *
     * @param string $key String role key.
     *
     * @since 1.30.4
     *
     * @return string Sanitized role key.
     */
    public static function sanitize_role_key( $key ) {

        $sanitized_role_key = '';

        $special_chars = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr( 0 ) );

        if ( is_scalar( $key ) ) {
            $sanitized_role_key = remove_accents( $key );
            $sanitized_role_key = strtolower( $sanitized_role_key );
            $sanitized_role_key = str_replace( $special_chars, '', $sanitized_role_key );
            $sanitized_role_key = preg_replace( '/[^a-z0-9_\-]/', '_', $sanitized_role_key );
        }

        return $sanitized_role_key;
    }

    /**
     * Properly calculate product prices using its wholesale price with taxing applied.
     *
     * @param int     $cart_total          Cart Totals from WWP.
     * @param WC_Cart $cart_object         Cart Object.
     * @param array   $user_wholesale_role Wholesale Role.
     *
     * @since  1.23.5
     * @since  1.27.1 Make ACFW coupon discount on override compatible
     * @since  1.29   Include calculation of non-wholesale priced product if WC Tax is disabled
     * @access public
     *
     * @return int
     */
    public static function calculate_cart_totals( $cart_total, $cart_object, $user_wholesale_role ) {

        global $wc_wholesale_prices_premium;

        $cart_wholesale_price_total   = 0;
        $acfw_bogo_entries            = WC()->session->get( 'acfw_bogo_entries' );
        $acfw_bogo_deals              = array();
        $has_acfw_bogo_coupon_applied = self::check_if_has_acfw_bogo_coupon_applied();

        // Get acfw_bogo deals, set it to $acfw_bogo_deals.
        if ( ! empty( $acfw_bogo_entries ) && $has_acfw_bogo_coupon_applied ) {
            foreach ( $acfw_bogo_entries['matched'] as $matched_entry ) {
                if ( 'deal' === $matched_entry['type'] ) {
                    $acfw_bogo_deals[ $matched_entry['key'] ] = array(
                        'type'          => $matched_entry['type'],
                        'quantity'      => $matched_entry['quantity'],
                        'discount'      => $matched_entry['discount'],
                        'discount_type' => $matched_entry['discount_type'],
                    );
                }
            }
        }

        foreach ( $cart_object->cart_contents as $cart_item_key => $cart_item ) {

            $product_id      = WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] );
            $active_currency = get_woocommerce_currency();
            $quantity        = $cart_item['quantity'];

            if ( isset( $cart_item['acfw_add_product_discount_type'] ) && isset( $cart_item['acfw_add_product_discount_value'] ) ) {

                if ( 'override' === $cart_item['acfw_add_product_discount_type'] && $cart_item['acfw_add_product_discount_value'] >= 0 ) {
                    $wholesale_price = $cart_item['acfw_add_product_discount_value'];
                }
            } else {
                $wholesale_price = WWP_Wholesale_Prices::get_product_wholesale_price_on_cart( WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] ), $user_wholesale_role, $cart_item, $cart_object );
            }

            // If product doesn't have wholesale price, then use the retail product price.
            $price = $wholesale_price ? $wholesale_price : wc_get_product( $product_id )->get_price();

            add_filter( 'wc_price', array( 'WWPP_Helper_Functions', 'return_unformatted_price' ), 10, 4 );
            $unformatted_price = $wc_wholesale_prices_premium->wwpp_wholesale_prices->get_product_shop_price_with_taxing_applied( wc_get_product( $product_id ), $price, array( 'currency' => $active_currency ), $user_wholesale_role );

            if ( ! empty( $unformatted_price ) ) {

                // If cart item is a bogo deal, calculate the discount price based on the discount type.
                if ( $has_acfw_bogo_coupon_applied && ! empty( $acfw_bogo_deals ) && array_key_exists( $cart_item_key, $acfw_bogo_deals ) ) {
                    $acfw_bogo_price = 0;
                    if ( 'override' === $acfw_bogo_deals[ $cart_item_key ]['discount_type'] ) {
                        $acfw_bogo_price = $acfw_bogo_deals[ $cart_item_key ]['discount'];
                    } elseif ( 'fixed' === $acfw_bogo_deals[ $cart_item_key ]['discount_type'] ) {
                        $acfw_bogo_price = $price - $acfw_bogo_deals[ $cart_item_key ]['discount'];
                    } elseif ( 'percent' === $acfw_bogo_deals[ $cart_item_key ]['discount_type'] ) {
                        $acfw_bogo_price = $price * ( $acfw_bogo_deals[ $cart_item_key ]['discount'] / 100 );
                    }

                    // Avoid bogo deal product in the calculation by reducing the quantity.
                    $quantity = $quantity - $acfw_bogo_deals[ $cart_item_key ]['quantity'];

                    // Calculate the bogo deal price.
                    $acfw_bogo_price = $acfw_bogo_price * $acfw_bogo_deals[ $cart_item_key ]['quantity'];
                }

                $cart_item_price = $unformatted_price * $quantity;

                // Add bogo deal calculated price to the cart item price.
                if ( isset( $acfw_bogo_price ) && is_numeric( $acfw_bogo_price ) ) {
                    $cart_item_price += $acfw_bogo_price;
                }

                $cart_wholesale_price_total += $cart_item_price;
            }

            remove_filter( 'wc_price', array( 'WWPP_Helper_Functions', 'return_unformatted_price' ), 10, 4 );

        }

        return ! empty( $cart_wholesale_price_total ) ? $cart_wholesale_price_total : $cart_total;
    }

    /**
     * This is wc_price filter. Instead of formatted price, return the unformatted so we can calculate the wholesale
     * price totals properly.
     *
     * @param string $output            Formatted Price.
     * @param int    $price             Raw Price.
     * @param array  $args              WC Price Args.
     * @param int    $unformatted_price Unformatted Price.
     *
     * @since  1.23.5
     * @access public
     *
     * @return int
     */
    public static function return_unformatted_price( $output, $price, $args, $unformatted_price ) {

        return $unformatted_price;
    }

    /**
     * Validate if cart level requirements are meet or not.
     *
     * * Important Note: This does not use the raw cart total, this calculate the cart total by using the wholesale
     * price
     * * of each product on the cart. The idea is that so even after the cart is applied with wholesale price, it will
     * * still meet the minimum order price.
     *
     * * Important Note: We are retrieving the raw wholesale price, not wholesale price with applied tax. Just the raw
     * * wholesale price of the product.
     *
     * * Important Note: Minimum order price is purely based on product price. It does not include tax and shipping
     * costs.
     * * Just the total product price on the cart using wholesale price.
     *
     * @param float   $cart_total          Cart total calculation.
     * @param WC_Cart $cart_object         WC_Cart instance.
     * @param array   $user_wholesale_role Current user wholesale roles.
     *
     * @since  1.30.2
     * @access public
     *
     * @return boolean True if cart level requirements are meet, false otherwise.
     */
    public static function apply_wholesale_price_per_cart_level_min_condition( $cart_total, $cart_object, $user_wholesale_role ) {

        $user_id                                = get_current_user_id();
        $minimum_cart_items                     = trim( get_option( 'wwpp_settings_minimum_order_quantity' ) );
        $minimum_cart_price                     = trim( get_option( 'wwpp_settings_minimum_order_price' ) );
        $minimum_requirements_conditional_logic = get_option( 'wwpp_settings_minimum_requirements_logic' );
        $notices                                = array();
        $cart_total                             = self::calculate_cart_totals( $cart_total, $cart_object, $user_wholesale_role );
        $cart_items                             = WC()->cart->get_cart_contents_count();
        $coupon_codes                           = WC()->cart->applied_coupons;
        $acfw_add_product_quantity              = 0;
        $apply_wholesale_price                  = true;

        // If there's any product added by ACFW coupon, then don't count the added product so we can have an actual product order quantity.
        if ( ! empty( $coupon_codes ) ) {
            foreach ( $cart_object->cart_contents as $cart_item_key => $cart_item ) {
                if ( isset( $cart_item['acfw_add_product_quantity'] ) && $cart_item['acfw_add_product_quantity'] > 0 ) {
                    $acfw_add_product_quantity += $cart_item['acfw_add_product_quantity'];
                }
            }

            // Subtract total cart items with the quantity of the products added by coupon.
            if ( $acfw_add_product_quantity > 0 ) {
                $cart_items = ( $cart_items - $acfw_add_product_quantity );
            }
        }

        // Check if there is an option that overrides wholesale price order requirement per role.
        $override_per_wholesale_role = get_option( 'wwpp_settings_override_order_requirement_per_role', false );

        if ( 'yes' === $override_per_wholesale_role ) {

            $per_wholesale_role_order_requirement = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
            if ( ! is_array( $per_wholesale_role_order_requirement ) ) {
                $per_wholesale_role_order_requirement = array();
            }

            if ( ! empty( $user_wholesale_role ) && array_key_exists( $user_wholesale_role[0], $per_wholesale_role_order_requirement ) ) {

                // Use minimum order quantity set for this current wholesale role.
                $minimum_cart_items                     = $per_wholesale_role_order_requirement[ $user_wholesale_role[0] ]['minimum_order_quantity'] ?? 0;
                $minimum_cart_price                     = $per_wholesale_role_order_requirement[ $user_wholesale_role[0] ]['minimum_order_subtotal'] ?? 0;
                $minimum_requirements_conditional_logic = $per_wholesale_role_order_requirement[ $user_wholesale_role[0] ]['minimum_order_logic'] ?? 'and';
            }
        }

        $user_min_order_qty_applied   = false;
        $user_min_order_price_applied = false;

        // Check if min order qty is overridden per wholesale user.
        if ( get_user_meta( $user_id, 'wwpp_override_min_order_qty', true ) === 'yes' ) {

            $user_min_order_qty = get_user_meta( $user_id, 'wwpp_min_order_qty', true );

            if ( ( is_numeric( $user_min_order_qty ) || empty( $user_min_order_qty ) ) && $minimum_cart_items > 0 ) {

                $minimum_cart_items         = $user_min_order_qty;
                $user_min_order_qty_applied = true;

            }
        }

        // Check if min order price is overridden per wholesale user.
        if ( get_user_meta( $user_id, 'wwpp_override_min_order_price', true ) === 'yes' ) {

            $user_min_order_price = get_user_meta( $user_id, 'wwpp_min_order_price', true );

            if ( ( is_numeric( $user_min_order_price ) || empty( $user_min_order_price ) ) && $minimum_cart_price > 0 ) {

                $minimum_cart_price           = $user_min_order_price;
                $user_min_order_price_applied = true;

            }
        }

        // Check if min order logic is overridden per wholesale user.
        if ( $user_min_order_qty_applied && $user_min_order_price_applied ) {

            $user_min_order_logic = get_user_meta( $user_id, 'wwpp_min_order_logic', true );

            if ( in_array( $user_min_order_logic, array( 'and', 'or' ), true ) ) {
                $minimum_requirements_conditional_logic = $user_min_order_logic;
            }
        }

        /**
         * Make min order price requirement compatible with "Aelia Currency Switcher" plugin.
         */
        if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

            $active_currency    = get_woocommerce_currency();
            $shop_base_currency = get_option( 'woocommerce_currency' );

            if ( $active_currency !== $shop_base_currency ) {
                $minimum_cart_price = WWP_ACS_Integration_Helper::convert( $minimum_cart_price, $active_currency, $shop_base_currency );
            }
        }

        /**
         * Make min order price requirement compatible with WPML Multi Currency" plugin.
         */
        if ( is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {

            global $woocommerce_wpml;

            if ( ! defined( 'WCML_MULTI_CURRENCIES_INDEPENDENT' ) ) {
                include_once WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wpml-woocommerce' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'constants.php';
            }

            if ( WCML_MULTI_CURRENCIES_INDEPENDENT === $woocommerce_wpml->settings['enable_multi_currency'] ) {

                if ( $woocommerce_wpml->multi_currency->get_client_currency() !== get_option( 'woocommerce_currency' ) ) {
                    $minimum_cart_price = apply_filters( 'wcml_raw_price_amount', $minimum_cart_price, $woocommerce_wpml->multi_currency->get_client_currency() );
                }
            }
        }

        if ( is_numeric( $minimum_cart_items ) && ( ! is_numeric( $minimum_cart_price ) || strcasecmp( $minimum_cart_price, '' ) === 0 || ( (float) $minimum_cart_price <= 0 ) ) ) {

            $minimum_cart_items = (int) $minimum_cart_items;
            if ( $cart_items < $minimum_cart_items ) {
                $notices = array(
                    'type'    => 'notice',
                    'message' => sprintf(
                    /* translators: %1$s Notice wrapper, %2$s Minimum cart items, %3$s Notice wrapper */
                        __(
                            '%1$sYou have not met the minimum order quantity of %2$s to activate adjusted pricing.
                                Retail prices will be shown below until the minimum order threshold is met.%3$s',
                            'woocommerce-wholesale-prices-premium'
                        ),
                        '<span class="wwpp-notice">',
                        '<b>(' . $minimum_cart_items . ')</b>',
                        '</span>'
                    ),
                );
            }
        } elseif ( is_numeric( $minimum_cart_price ) && ( ! is_numeric( $minimum_cart_items ) || strcasecmp( $minimum_cart_items, '' ) === 0 || ( (int) $minimum_cart_items <= 0 ) ) ) {

            $minimum_cart_price = (float) $minimum_cart_price;
            if ( $cart_total < $minimum_cart_price ) {
                $notices = array(
                    'type'    => 'notice',
                    'message' => sprintf(
                    /* translators: %1$s Notice wrapper, %2$s Minimum cart price, %3$s Cart subtotal, %4$s Notice wrapper */
                        __(
                            '%1$sYou have not met the minimum order subtotal of %2$s to activate adjusted pricing.
                                Retail  prices will be shown below until the minimum order threshold is met.
                                The cart subtotal calculated with wholesale prices is %3$s%4$s',
                            'woocommerce-wholesale-prices-premium'
                        ),
                        '<span class="wwpp-notice">',
                        '<b>(' . wc_price( $minimum_cart_price ) . ')</b>',
                        '<b>(' . wc_price( $cart_total ) . ')</b>',
                        '</span>'
                    ),
                );
            }
        } elseif ( is_numeric( $minimum_cart_price ) && is_numeric( $minimum_cart_items ) ) {

            if ( strcasecmp( $minimum_requirements_conditional_logic, 'and' ) === 0 ) {

                if ( $cart_items < $minimum_cart_items || $cart_total < $minimum_cart_price ) {
                    $notices = array(
                        'type'    => 'notice',
                        'message' => sprintf(
                        /* translators: %1$s Notice wrapper, %2$s Minimum cart items, %3$s Minimum cart price, %4$s Cart subtotal, %5$s Notice wrapper */
                            __(
                                '%1$sYou have not met the minimum order quantity of %2$s and minimum order subtotal of %3$s to activate adjusted pricing.
                                    Retail prices will be shown below until the minimum order threshold is met.
                                    The cart subtotal calculated with wholesale prices is %4$s%5$s',
                                'woocommerce-wholesale-prices-premium'
                            ),
                            '<span class="wwpp-notice">',
                            '<b>(' . $minimum_cart_items . ')</b>',
                            '<b>(' . wc_price( $minimum_cart_price ) . ')</b>',
                            '<b>(' . wc_price( $cart_total ) . ')</b>',
                            '</span>',
                        ),
                    );
                }
            } elseif ( $cart_items < $minimum_cart_items && $cart_total < $minimum_cart_price ) {
                $notices = array(
                    'type'    => 'notice',
                    'message' => sprintf(
                    /* translators: %1$s Notice wrapper, %2$s Minimum cart items, %3$s Minimum cart price, %4$s Cart subtotal, %5$s Notice wrapper */
                        __(
                            '%1$sYou have not met the minimum order quantity of %2$s or minimum order subtotal of %3$s to activate adjusted pricing.
                                Retail prices will be shown below until the minimum order threshold is met.
                                The cart subtotal calculated with wholesale prices is %4$s%5$s',
                            'woocommerce-wholesale-prices-premium'
                        ),
                        '<span class="wwpp-notice">',
                        '<b>(' . $minimum_cart_items . ')</b>',
                        '<b>(' . wc_price( $minimum_cart_price ) . ')</b>',
                        '<b>(' . wc_price( $cart_total ) . ')</b>',
                        '</span>',
                    ),
                );
            }
        }

        $notices = apply_filters( 'wwpp_filter_wholesale_price_requirement_failure_notice', $notices, $minimum_cart_items, $minimum_cart_price, $cart_items, $cart_total, $cart_object, $user_wholesale_role );

        return ! empty( $notices ) ? $notices : $apply_wholesale_price;
    }

    /**
     * Filter if apply wholesale price per product level. Validate if per product level requirements are meet or not.
     *
     * Important Note: We are retrieving the raw wholesale price, not wholesale price with applied tax. Just the raw
     * wholesale price of the product.
     *
     * @param array   $cart_item           Cart item.
     * @param WC_Cart $cart_object         WC_Cart instance.
     * @param array   $user_wholesale_role Current user wholesale roles.
     * @param float   $wholesale_price     Wholesale price.
     *
     * @since  1.15.0
     * @since  1.16.0 Add support for wholesale order quantity step.
     * @access public
     *
     * @return array|boolean Array of error notices on if current cart item fails product requirement, boolean true if
     *                       passed and should apply wholesale pricing.
     */
    public static function apply_wholesale_price_per_product_level_min_condition( $cart_item, $cart_object, $user_wholesale_role, $wholesale_price ) {

        global $wc_wholesale_prices_premium;

        if ( ! apply_filters( 'wwp_include_cart_item_on_cart_totals_computation', true, $cart_item, $user_wholesale_role ) ) {
            return false;
        }

        $did_not_meet    = false;
        $product_id      = $cart_item['data']->get_id();
        $active_currency = get_woocommerce_currency();
        $wholesale_price = WWP_Wholesale_Prices::get_product_wholesale_price_on_cart( $product_id, $user_wholesale_role, $cart_item, $cart_object );

        if ( in_array( $cart_item['data']->get_type(), array( 'simple', 'bundle', 'composite' ), true ) ) {

            $moq = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_minimum_order_quantity', true );
            $moq = ( is_numeric( $moq ) ) ? (int) $moq : 0;

            if ( $cart_item['quantity'] < $moq ) {
                $did_not_meet = true;
            } elseif ( $cart_item['quantity'] !== $moq && $moq > 0 ) {

                $oqs = get_post_meta( $product_id, $user_wholesale_role[0] . '_wholesale_order_quantity_step', true );
                $oqs = ( is_numeric( $oqs ) ) ? (int) $oqs : 0;

                if ( $oqs ) {

                    $excess_qty = $cart_item['quantity'] - $moq;

                    if ( 0 !== $excess_qty % $oqs ) {
                        $did_not_meet = true;
                    }
                }
            }
        } elseif ( $cart_item['data']->get_type() === 'variation' ) {

            // Process variable level wholesale minimum order quantity.
            $variable_id    = $cart_item['data']->get_parent_id();
            $variation_id   = $cart_item['data']->get_id();
            $variable_total = 0;

            // Get total items of a variable product in cart ( Total items of its variations ).
            foreach ( $cart_object->cart_contents as $cart_item_key => $ci ) {
                $parent_product_type = $ci['data']->get_type();
                $parent_variable_id  = $ci['data']->get_parent_id();

                if ( 'variation' === $parent_product_type && $parent_variable_id === $variable_id ) {
                    $variable_total += $ci['quantity'];
                }
            }

            // Check variable product requirements.
            $check_result = self::check_if_variable_product_requirement_is_meet( $variable_id, $variation_id, $cart_item, $variable_total, $user_wholesale_role[0] );

            if ( is_array( $check_result ) ) {
                $did_not_meet = true;
            }
        }

        return $did_not_meet;
    }

    /**
     * Check if variable product requirement is meet.
     *
     * @param int    $variable_id         Variable Id.
     * @param int    $variation_id        Variation Id.
     * @param array  $cart_item           Cart item data.
     * @param int    $variable_total      Variable total.
     * @param string $user_wholesale_role User wholesale role.
     *
     * @since  1.16.0
     * @access public
     *
     * @return boolean|array True if passed, array of error data on failure.
     */
    public static function check_if_variable_product_requirement_is_meet( $variable_id, $variation_id, $cart_item, $variable_total, $user_wholesale_role ) {

        // Variable Level MOQ.
        $variable_level_moq = get_post_meta( $variable_id, $user_wholesale_role . '_variable_level_wholesale_minimum_order_quantity', true );
        $variable_level_moq = ( is_numeric( $variable_level_moq ) ) ? (int) $variable_level_moq : 0;

        if ( $variable_total >= $variable_level_moq ) {

            // Variable Level OQS.
            $excess_qty = $variable_total - $variable_level_moq;

            if ( $excess_qty ) {
                // Variable total is greater than variable level moq.
                $variable_level_oqs = get_post_meta( $variable_id, $user_wholesale_role . '_variable_level_wholesale_order_quantity_step', true );
                $variable_level_oqs = ( is_numeric( $variable_level_oqs ) ) ? (int) $variable_level_oqs : 0;

                if ( $variable_level_oqs && 0 !== $excess_qty % $variable_level_oqs ) {
                    return array(
                        'fail_type'          => 'variable_level_oqs',
                        'variable_level_oqs' => $variable_level_oqs,
                        'variable_level_moq' => $variable_level_moq,
                    );
                }
            }

            // Variation Level MOQ.
            $variation_level_moq = get_post_meta( $variation_id, $user_wholesale_role . '_wholesale_minimum_order_quantity', true );
            $variation_level_moq = ( is_numeric( $variation_level_moq ) ) ? (int) $variation_level_moq : 0;

            if ( $cart_item['quantity'] >= $variation_level_moq ) {

                if ( $variation_level_moq > 0 ) {

                    /**
                     * Only do Variation level OQS if Variation level MOQ is more than zero
                     */

                    $excess_qty = $cart_item['quantity'] - $variation_level_moq;

                    if ( $excess_qty ) {
                        // Variation qty is greater than variation level moq.
                        $variation_level_oqs = get_post_meta( $variation_id, $user_wholesale_role . '_wholesale_order_quantity_step', true );
                        $variation_level_oqs = ( is_numeric( $variation_level_oqs ) ) ? (int) $variation_level_oqs : 0;

                        if ( $variation_level_oqs && 0 !== $excess_qty % $variation_level_oqs ) {
                            return array(
                                'fail_type'           => 'variation_level_oqs',
                                'variation_level_oqs' => $variation_level_oqs,
                                'variation_level_moq' => $variation_level_moq,
                            );
                        }
                    }
                }
            } else {
                return array(
                    'fail_type'           => 'variation_level_moq',
                    'variation_level_moq' => $variation_level_moq,
                );
            }

            return true; // If passed through all filters, return true.

        } else {
            return array(
                'fail_type'          => 'variable_level_moq',
                'variable_level_moq' => $variable_level_moq,
            );
        }
    }

    /**
     * Check if cart has ACFW bogo coupon applied.
     *
     * @since  1.30.2
     * @access public
     *
     * @return boolean True if cart has ACFW bogo coupon applied, false otherwise.
     */
    public static function check_if_has_acfw_bogo_coupon_applied() {

        $has_acfw_bogo_coupon_applied = false;
        // Get acfw_bogo deals, set it to $acfw_bogo_deals.
        foreach ( \WC()->cart->get_applied_coupons() as $coupon_code ) {
            $coupon    = new WC_Coupon( $coupon_code );
            $bogo_deal = $coupon->is_type( 'acfw_bogo' );
            // break if coupon has no BOGO deal.
            if ( $coupon->is_type( 'acfw_bogo' ) ) {
                $has_acfw_bogo_coupon_applied = true;
                break;
            }
        }

        return $has_acfw_bogo_coupon_applied;
    }

    /**
     * Get composite product wholesale price.
     *
     * @param object $product             Product object.
     * @param array  $user_wholesale_role User wholesale role.
     *
     * @since  1.30.2
     * @access public
     *
     * @return float Wholesale price.
     */
    public static function wwpp_get_composite_product_wholesale_price_from( $product, $user_wholesale_role ) {

        $wholesale_price = 0;
        if ( $product->is_type( 'composite' ) ) {

            $wholesale_price_raw = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product->get_id(), $user_wholesale_role );
            if ( ! empty( $wholesale_price_raw ) ) {
                $wholesale_parent_price       = $wholesale_price_raw['wholesale_price_raw'];
                $product_wholesale_sale_price = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product->get_id(), $user_wholesale_role );
                $wholesale_sale_price         = isset( $product_wholesale_sale_price['wholesale_sale_price'] ) ? $product_wholesale_sale_price['wholesale_sale_price'] : 0;

                // If the product is on wholesale sale, then we need to check if the wholesale sale price is lower than the wholesale price.
                if ( $wholesale_sale_price > 0 && $wholesale_sale_price < $wholesale_parent_price ) {
                    $wholesale_parent_price = $wholesale_sale_price;
                }

                $wholesale_price += (float) $wholesale_parent_price;
            }

            $components = $product->get_components();
            foreach ( $components as $component ) {
                $component_id       = $component->get_id();
                $component_options  = $component->get_options();
                $component_settings = $component->get_data();

                if ( 'yes' === $component_settings['priced_individually'] ) {
                    $component_product = wc_get_product( $component_id );

                    if ( ! empty( $component_options ) ) {
                        // Get the minimum price of the first component.
                        $component_option = reset( $component_options );

                        $option_product    = wc_get_product( $component_option );
                        $option_product_id = $option_product->get_id();
                        if ( 'variable' === $option_product->get_type() ) {
                            $variation_prices  = $option_product->get_variation_prices();
                            $min_price         = min( $variation_prices['price'] );
                            $variation_id      = array_search( $min_price, $variation_prices['price'], true );
                            $option_product_id = $variation_id;
                        }

                        // Get selected product object.
                        $option_product_obj = wc_get_product( $option_product_id );

                        $price_arr = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $option_product_id, $user_wholesale_role );
                        if ( ! empty( $price_arr['wholesale_price'] ) ) {
                            $option_product_wholesale_price = $price_arr['wholesale_price'];

                            // Get wholesale sale price.
                            $product_wholesale_sale_price = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $option_product_id, $user_wholesale_role );
                            $wholesale_sale_price         = isset( $product_wholesale_sale_price['wholesale_sale_price'] ) ? $product_wholesale_sale_price['wholesale_sale_price'] : 0;

                            // If wholesale sale price is less than the wholesale price, then use the wholesale sale price.
                            if ( $wholesale_sale_price > 0 && $wholesale_sale_price < $price_arr['wholesale_price'] ) {
                                $option_product_wholesale_price = $wholesale_sale_price;
                            }

                            $wholesale_price += $option_product_wholesale_price;
                        } else {
                            $regular_price = $option_product_obj->get_regular_price();
                            $sale_price    = $option_product_obj->get_sale_price();

                            // check if sale price exists and is not empty.
                            if ( ! empty( $sale_price ) ) {
                                $price = $sale_price;
                            } else {
                                $price = $regular_price;
                            }

                            $wholesale_price += $price;
                        }
                    }
                }
            }
        }

        return $wholesale_price;
    }

    /**
     * Get wholesale price for a product.
     *
     * @param WC_Product $product             Product object.
     * @param array      $user_wholesale_role User wholesale role.
     * @param array      $cart_item           Cart item data.
     *
     * @since  1.30.0
     * @access public
     *
     * @return array Wholesale price.
     */
    public static function get_quantity_discount_mapping_price( $product, $user_wholesale_role, $cart_item ) {

        global $wc_wholesale_prices_premium;

        $wholesale_price_arr = array();
        $enabled             = 'no';

        if ( $product->is_type( 'variation' ) ) {
            $enabled = get_post_meta( $product->get_parent_id(), WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
        }

        // If the variable qty based discount is enabled we use its mapping else we use the per variation mapping.
        if ( 'yes' === $enabled ) {

            $mapping = get_post_meta( $product->get_parent_id(), WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );

        } else {

            $enabled = $product->get_meta( WWPP_POST_META_ENABLE_QUANTITY_DISCOUNT_RULE, true );
            $mapping = $product->get_meta( WWPP_POST_META_QUANTITY_DISCOUNT_RULE_MAPPING, true );

        }

        if ( ! is_array( $mapping ) ) {
            $mapping = array();
        }

        if ( 'yes' === $enabled && ! empty( $mapping ) ) {

            $base_currency_mapping = array();
            foreach ( $mapping as $map ) {

                // Skip non base currency mapping.
                if ( array_key_exists( 'currency', $map ) ) {
                    continue;
                }

                // Skip mapping not meant for the current user wholesale role.
                if ( $user_wholesale_role[0] !== $map['wholesale_role'] ) {
                    continue;
                }
                $base_currency_mapping[] = $map;
            }

            $wholesale_price              = $product->get_meta( $user_wholesale_role[0] . '_wholesale_price', true );
            $product_wholesale_sale_price = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product->get_id(), $user_wholesale_role );
            if ( ! empty( $product_wholesale_sale_price['is_on_sale'] ) && isset( $product_wholesale_sale_price['wholesale_sale_price'] ) ) {
                $wholesale_price = $product_wholesale_sale_price['wholesale_sale_price'];
            }

            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {

                $base_currency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product->get_id() );
                $active_currency = get_woocommerce_currency();

                if ( $base_currency === $active_currency ) {

                    $wholesale_price_arr['wholesale_price'] = self::get_cart_wholesale_price_from_mapping( $wholesale_price, $base_currency_mapping, array(), $cart_item, $base_currency, $active_currency, true );
                    $wholesale_price_arr['source']          = 'per_product_level_qty_based';

                } else {

                    // Get specific currency mapping.
                    $specific_currency_mapping = self::get_specific_currency_mapping( $mapping, $user_wholesale_role, $active_currency, $base_currency_mapping );

                    $wholesale_price_arr['wholesale_price'] = self::get_cart_wholesale_price_from_mapping( $wholesale_price, $base_currency_mapping, $specific_currency_mapping, $cart_item, $base_currency, $active_currency, false );
                    $wholesale_price_arr['source']          = 'per_product_level_qty_based';

                }
            } else {

                $wholesale_price_arr['wholesale_price'] = self::get_cart_wholesale_price_from_mapping( $wholesale_price, $base_currency_mapping, array(), $cart_item, get_woocommerce_currency(), get_woocommerce_currency(), true );
                $wholesale_price_arr['source']          = 'per_product_level_qty_based';

            }
        }

        return $wholesale_price_arr;
    }

    /**
     * Get wholesale price for a product from mapping.
     *
     * @param float  $wholesale_price           Wholesale price.
     * @param array  $base_currency_mapping     Base currency mapping.
     * @param array  $specific_currency_mapping Specific currency mapping.
     * @param array  $cart_item                 Cart item.
     * @param string $base_currency             Base currency.
     * @param string $active_currency           Active currency.
     * @param bool   $is_base_currency          Is base currency.
     *
     * @since  1.30.2
     * @access private
     *
     * @return float Wholesale price.
     */
    public static function get_cart_wholesale_price_from_mapping( $wholesale_price, $base_currency_mapping, $specific_currency_mapping, $cart_item, $base_currency, $active_currency, $is_base_currency ) {

        global $wc_wholesale_prices_premium;

        if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ), array( 'simple', 'variation' ), true ) ) {
            $cart_item['quantity'] = $wc_wholesale_prices_premium->wwpp_wholesale_prices->tally_product_qty_in_shopping_cart( $cart_item );
        }

        if ( ! $is_base_currency ) {

            foreach ( $base_currency_mapping as $baseMap ) {

                $price = '';

                /*
                 * First check if a price is set for this wholesale role : range pair in the specific currency mapping.
                 * If wholesale price is present, then use it.
                 */
                foreach ( $specific_currency_mapping as $specificMap ) {

                    if ( $cart_item['quantity'] >= $specificMap[ $active_currency . '_start_qty' ] && ( empty( $specificMap[ $active_currency . '_end_qty' ] ) || $cart_item['quantity'] <= $specificMap[ $active_currency . '_end_qty' ] ) && '' !== $specificMap[ $active_currency . '_wholesale_price' ] ) {

                        if ( isset( $specificMap[ $active_currency . '_price_type' ] ) ) {

                            if ( 'fixed-price' === $specificMap[ $active_currency . '_price_type' ] ) {
                                $price = $specificMap[ $active_currency . '_wholesale_price' ];
                            } elseif ( 'percent-price' === $specificMap[ $active_currency . '_price_type' ] ) {
                                $price = wc_format_decimal( $wholesale_price - ( ( $specificMap[ $active_currency . '_wholesale_price' ] / 100 ) * $wholesale_price ), '' );
                            }
                        } else {
                            $price = $specificMap[ $active_currency . '_wholesale_price' ];
                        }
                    }
                }

                /*
                 * Now if there is no mapping for this specific wholesale role : range pair in the specific currency mapping,
                 * since this range is present on the base map mapping. We derive the price by converting the price set on the
                 * base currency mapping to this active currency.
                 */
                if ( ! $price ) {

                    if ( $cart_item['quantity'] >= $baseMap['start_qty'] &&
                        ( empty( $baseMap['end_qty'] ) || $cart_item['quantity'] <= $baseMap['end_qty'] ) &&
                        '' !== $baseMap['wholesale_price'] ) {

                        if ( isset( $baseMap['price_type'] ) ) {

                            if ( 'fixed-price' === $baseMap['price_type'] ) {
                                $price = WWP_ACS_Integration_Helper::convert( $baseMap['wholesale_price'], $active_currency, $base_currency );
                            } elseif ( 'percent-price' === $baseMap['price_type'] ) {

                                $price = wc_format_decimal( $wholesale_price - ( ( $baseMap['wholesale_price'] / 100 ) * $wholesale_price ), '' );

                                /**
                                 * No need to use
                                 * $price = WWP_ACS_Integration_Helper::convert( $price , $active_currency , $base_currency );
                                 * to convert the price because the $wholesale_price variable is already using the converted wholesale price
                                 * WWPP-558
                                 */

                            }
                        } else {
                            $price = WWP_ACS_Integration_Helper::convert( $baseMap['wholesale_price'], $active_currency, $base_currency );
                        }
                    }
                }

                if ( $price ) {

                    $wholesale_price = $price;
                    break;

                }
            }
        } else {

            foreach ( $base_currency_mapping as $map ) {

                if ( $cart_item['quantity'] >= $map['start_qty'] &&
                    ( empty( $map['end_qty'] ) || $cart_item['quantity'] <= $map['end_qty'] ) &&
                    '' !== $map['wholesale_price'] ) {

                    if ( isset( $map['price_type'] ) ) {

                        if ( 'fixed-price' === $map['price_type'] ) {
                            $wholesale_price = $map['wholesale_price'];
                        } elseif ( 'percent-price' === $map['price_type'] ) {
                            $wholesale_price = wc_format_decimal( $wholesale_price - ( ( $map['wholesale_price'] / 100 ) * $wholesale_price ), '' );
                        }
                    } else {
                        $wholesale_price = $map['wholesale_price'];
                    }

                    break;

                }
            }
        }

        return $wholesale_price;
    }

    /**
     * Get the specific currency mapping from the wholesale price per order quantity mapping.
     *
     * @param array  $mapping               Quantity discount mapping data.
     * @param array  $user_wholesale_role   Arry of user wholesale roles.
     * @param string $active_currency       Active currency.
     * @param array  $base_currency_mapping Base currency mapping.
     *
     * @since 1.30.4
     *
     * @return array Specific currency mapping.
     */
    public static function get_specific_currency_mapping( $mapping, $user_wholesale_role, $active_currency, $base_currency_mapping ) {

        // Get specific currency mapping.
        $specific_currency_mapping = array();

        foreach ( $mapping as $map ) {

            // Skip base currency.
            if ( ! array_key_exists( 'currency', $map ) ) {
                continue;
            }

            // Skip mappings that are not for the active currency.
            if ( ! array_key_exists( $active_currency . '_wholesale_role', $map ) ) {
                continue;
            }

            // Skip mapping not meant for the currency user wholesale role.
            if ( $user_wholesale_role[0] !== $map[ $active_currency . '_wholesale_role' ] ) {
                continue;
            }

            // Only extract out mappings for this current currency that has equivalent mapping
            // on the base currency.
            foreach ( $base_currency_mapping as $base_map ) {

                if ( $base_map['start_qty'] === $map[ $active_currency . '_start_qty' ] && $base_map['end_qty'] === $map[ $active_currency . '_end_qty' ] ) {

                    $specific_currency_mapping[] = $map;
                    break;

                }
            }
        }

        return $specific_currency_mapping;
    }

    /**
     * Get default datetime format for display.
     *
     * @since  1.30.4
     * @access public
     *
     * @return string Datetime format.
     */
    public static function get_default_datetime_format() {

        return sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'g:i a' ) );
    }

    /**
     * Get datetime with site timezone.
     *
     * @param string $datetime Datetime string.
     * @param string $interval Datetime interval.
     *
     * @since  1.30.4
     * @access public
     *
     * @return \WC_DateTime Datetime object.
     */
    public static function get_datetime_with_site_timezone( $datetime, $interval = '' ) {

        $datetime = new \WC_DateTime( $datetime, new DateTimeZone( 'UTC' ) );
        $datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
        if ( ! empty( $interval ) ) {
            $interval = DateInterval::createFromDateString( $interval );
            $datetime->add( $interval ); // Add interval if needed.
        }

        return $datetime;
    }

    /**
     * Convert datetime to site standard format.
     * 1. Datetime must use site timezone.
     * 2. Datetime must use site default datetime format.
     * 3. Datetime must be localize using i18n.
     *
     * @param string $datetime Datetime string.
     * @param string $format   Datetime format.
     * @param string $interval Datetime interval.
     *
     * @since 1.30.4
     *
     * @return string
     */
    public static function convert_datetime_to_site_standard_format( $datetime, $format = '', $interval = '' ) {

        $standard = self::get_datetime_with_site_timezone( $datetime, $interval ); // Convert to site timezone.
        $format   = ! empty( $format ) ? $format : self::get_default_datetime_format();

        return $standard->date_i18n( $format ); // Convert to site default datetime format and localize.
    }

    /**
     * Checks if the page has a WooCommerce cart block.
     *
     * @param null|int|WP_Post $post Optional. Post ID or post object. Default is global $post.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function has_wc_cart_block( $post = null ) {

        return has_block( 'woocommerce/cart', $post );
    }

    /**
     * Checks if the page has a WooCommerce checkout block.
     *
     * @param null|int|WP_Post $post Optional. Post ID or post object. Default is global $post.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function has_wc_checkout_block( $post = null ) {

        return has_block( 'woocommerce/checkout', $post );
    }

    /**
     * Get only allow wholesale purchases setting.
     *
     * @param WP_User $user User object.
     *
     * @since 2.0.0
     * @return mixed|string
     */
    public static function only_allow_wholesale_purchases( $user = null ) {

        $user_wholesale_role            = WWPP_Wholesale_Roles::instance()->getUserWholesaleRole( $user );
        $all_registered_wholesale_roles = WWPP_Wholesale_Roles::instance()->getAllRegisteredWholesaleRoles();

        if ( ! isset( $user_wholesale_role[0] ) || ! isset( $all_registered_wholesale_roles[ $user_wholesale_role[0] ] ) ) {
            return 'no';
        }

        return isset( $all_registered_wholesale_roles[ $user_wholesale_role[0] ]['onlyAllowWholesalePurchases'] ) ? $all_registered_wholesale_roles[ $user_wholesale_role[0] ]['onlyAllowWholesalePurchases'] : 'no';
    }

    /**
     * Check if whether to show the saving amount on the page.
     *
     * @param string $page One of the following: 'shop', 'single', 'cart', 'checkout', 'order', 'invoice'.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function show_saving_amount( $page = null ) {

        $global_setting_active = get_option( 'wwpp_settings_show_saving_amount' ) === 'yes';
        $show                  = false;

        if ( ! $global_setting_active ) {
            return false;
        }

        $cart_checkout_order = array(
            'cart',
            'checkout',
            'order',
        );

        if ( in_array( $page, $cart_checkout_order, true ) ) {
            $show = true;
        }

        switch ( $page ) {
            case 'shop':
                $show = get_option( 'wwpp_settings_show_saving_amount_page_shop' ) === 'yes';
                break;
            case 'single':
                $show = get_option( 'wwpp_settings_show_saving_amount_page_single_product' ) === 'yes';
                break;
            case 'invoice':
                $show = get_option( 'wwpp_settings_show_saving_amount_page_invoice' ) === 'yes';
                break;
        }

        return $show;
    }

    /**
     * Check if product min step on cart page is enforced.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function enforce_product_min_step_on_cart_page() {

        return get_option( 'wwpp_settings_enforce_product_min_step_on_cart_page', false ) === 'yes';
    }

    /**
     * Check if allowed to add to cart below product minimum.
     *
     * @since 2.0.0
     * @return bool
     */
    public static function allow_add_to_cart_below_product_minimum() {

        return get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', false ) === 'yes';
    }

    /**
     * Get the global minimum order quantity.
     *
     * @since 2.0.0
     * @return false|mixed|null
     */
    public static function get_global_minimum_order_quantity() {

        return get_option( 'wwpp_settings_minimum_order_quantity', 0 );
    }
}
