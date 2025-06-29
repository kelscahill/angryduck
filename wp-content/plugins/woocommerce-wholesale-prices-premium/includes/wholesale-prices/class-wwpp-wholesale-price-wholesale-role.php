<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Wholesale_Price_Wholesale_Role' ) ) {

    /**
     * Model that houses the logic of applying wholesale role level wholesale pricing.
     *
     * @since 1.16.0
     */
    class WWPP_Wholesale_Price_Wholesale_Role {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_Wholesale_Price_Wholesale_Role.
         *
         * @since 1.16.0
         * @access private
         * @var WWPP_Wholesale_Price_Wholesale_Role
         */
        private static $_instance;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_Wholesale_Price_Wholesale_Role constructor.
         *
         * @since 1.16.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Price_Wholesale_Role model.
         */
        public function __construct( $dependencies ) {
        }

        /**
         * Ensure that only one instance of WWPP_Wholesale_Price_Wholesale_Role is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.16.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Price_Wholesale_Role model.
         * @return WWPP_Wholesale_Price_Wholesale_Role
         */
        public static function instance( $dependencies = array() ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Render wholesale role cart quantity based wholesale discount table markup.
         * Support ignore role/cat level wholesale pricing feature.
         *
         * @since 1.16.0
         * @since 1.21.1  Use regular price instead of wholesale price for Quantity Based Wholesale Discount. Apply taxing properly.
         * @since 1.27.10 Fix if qty based wholesale discount feature is disabled, the qty based wholesale discount table still shows on the product page.
         *                The shop owner could set the qty based wholesale discount on general level and on per user level,
         *                However, if there's no general qty based wholesale discount defined for the user's associated wholesale role,
         *                And qty based discount on per user level is set, the user is stil able to get the qty based discount,
         *                The override general qty based discount on User level should only active if there is general qty based wholesale discount defined for the user's associated wholesale role.
         * @access public
         *
         * @param string     $wholesale_price_html       Wholesale price html.
         * @param string     $price                      Active price html( non wholesale ).
         * @param WC_Product $product                    WC_Product object.
         * @param array      $user_wholesale_role        Array user wholesale roles.
         * @param string     $wholesale_price_title_text Wholesale price title text.
         * @param string     $raw_wholesale_price        Raw wholesale price.
         * @param string     $source                     Source of the wholesale price being applied.
         * @return string Filtered wholesale price html.
         */
        public function render_wholesale_role_cart_quantity_based_wholesale_discount_table_markup( $wholesale_price_html, $price, $product, $user_wholesale_role, $wholesale_price_title_text, $raw_wholesale_price, $source ) {

            global $wc_wholesale_prices_premium;

            // Only apply this to single product pages and proper ajax request
            // When a variable product have lots of variations, WC will not load variation data on variable product page load on front end
            // Instead it will load variations data as you select them on the variations select box
            // We need to support this too.
            if ( ! empty( $user_wholesale_role ) &&
                ( ( get_option( 'wwpp_settings_hide_quantity_discount_table', false ) !== 'yes' && ( is_product() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) && ( in_array( WWP_Helper_Functions::wwp_get_product_type( $product ), array( 'simple', 'variation', 'composite', 'bundle' ), true ) ) ) ||
                    apply_filters( 'render_cart_quantity_based_wholesale_discount_per_wholesale_role_level_table_markup', false ) ) ) {

                // Check if this feature is even enabled.
                if ( get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount' ) !== 'yes' ) {
                    return $wholesale_price_html;
                }

                // condition check for WWOF.
                if ( apply_filters( 'wwof_hide_per_wholesale_role_table_on_wwof_form', false ) ) {
                    return $wholesale_price_html;
                }

                $post_id = ( 'variation' === $product->get_type() ) ? $product->get_parent_id() : $product->get_id();

                $disregard_role_level_discount = apply_filters( 'wwpp_disregard_role_level_discount', get_post_meta( $post_id, 'wwpp_ignore_role_level_wholesale_discount', true ) );
                if ( 'yes' === $disregard_role_level_discount ) {
                    return $wholesale_price_html;
                }

                // Make sure that the wholesale price being applied is on per wholesale role or per user level.
                // We change the condition to remove the level checker to make sure pass to user level discount.
                if ( empty( $raw_wholesale_price ) ) {
                    return $wholesale_price_html;
                }

                // Return if product level wholesale price is set.
                $have_wholesale_price = get_post_meta( $post_id, $user_wholesale_role[0] . '_have_wholesale_price', true );
                if ( ! empty( $have_wholesale_price ) && 'yes' === $have_wholesale_price ) {
                    return $wholesale_price_html;
                }

                $user_id                   = $this->get_current_user_id();
                $cart_qty_discount_mapping = array();

                if ( get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) === 'yes' ) {

                    $puwd = get_user_meta( $user_id, 'wwpp_wholesale_discount', true );

                    if ( ! empty( $puwd ) ) {

                        switch ( get_user_meta( $user_id, 'wwpp_override_wholesale_discount_qty_discount_mapping', true ) ) {

                            case 'dont_use_general_per_wholesale_role_qty_mapping':
                                break; // Do nothing.

                            case 'use_general_per_wholesale_role_qty_mapping':
                                $cart_qty_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                                if ( ! is_array( $cart_qty_discount_mapping ) ) {
                                    $cart_qty_discount_mapping = array();
                                }

                                break; // Do nothing.

                            case 'specify_general_per_wholesale_role_qty_mapping':
                                // Get the general qty based wholesale discount mapping.
                                $cart_qty_general_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                                if ( ! is_array( $cart_qty_general_discount_mapping ) ) {
                                    $cart_qty_general_discount_mapping = array();
                                }

                                if ( ! empty( $cart_qty_general_discount_mapping ) ) {
                                    // Check if wholesale user has general qty based wholesale discount for the wholesale role.
                                    $has_cart_qty_general_discount_mapping = array_search( $user_wholesale_role[0], array_column( $cart_qty_general_discount_mapping, 'wholesale_role' ), true );

                                    if ( is_numeric( $has_cart_qty_general_discount_mapping ) ) {
                                        // Get the user level override qty based wholesale discount mapping.
                                        $cart_qty_discount_mapping = get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true );
                                        if ( ! is_array( $cart_qty_discount_mapping ) ) {
                                            $cart_qty_discount_mapping = array();
                                        }
                                    }
                                }

                                break;

                        }
                    }
                } else {

                    // Check if base wholesale role discount is set. Per qty discount is based on this so if this is not set, then let return out now.
                    $wholesale_role_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );
                    if ( ! is_array( $wholesale_role_discount ) ) {
                        $wholesale_role_discount = array();
                    }

                    if ( ! array_key_exists( $user_wholesale_role[0], $wholesale_role_discount ) ) {
                        return $wholesale_price_html;
                    }

                    $cart_qty_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                    if ( ! is_array( $cart_qty_discount_mapping ) ) {
                        $cart_qty_discount_mapping = array();
                    }
                }

                if ( empty( $cart_qty_discount_mapping ) ) {
                    return $wholesale_price_html;
                }

                // We need to check if there are "any sort" of wholesale pricing on the per product level or per category level. If there is, we skip this then
                // Process per qty discount table markup.
                $use_regular_price = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );

                if ( $product->is_on_sale() && 'yes' !== $use_regular_price ) {
                    $product_active_price = $product->get_sale_price();
                } else {
                    $product_active_price = $product->get_regular_price();
                }

                // WCML Compatibility.
                $product_active_price = WWPP_Helper_Functions::get_product_default_currency_price( $product_active_price, $product );

                $product_active_price = trim( apply_filters( 'wwp_pass_wholesale_price_through_taxing', $product_active_price, $product->get_id(), $user_wholesale_role ) );

                $has_range_discount = false;
                $desc_text          = $this->get_wholesale_price_qty_discount_description( $product );

                // Table view.
                $headers = apply_filters(
                    'wwpp_quantity_based_discount_headers',
                    array(
                        'qty'   => __( 'Qty', 'woocommerce-wholesale-prices-premium' ),
                        'price' => __( 'Price', 'woocommerce-wholesale-prices-premium' ),
                        'save'  => __( 'Save', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    $source
                );

                // Description.
                $qty_table  = '<div class="qty-based-discount-table-description">';
                $qty_table .= '<p class="desc">' . $desc_text . '</p>';
                $qty_table .= '</div>';

                // Qty Table.
                $qty_table .= '<table class="order-quantity-based-wholesale-pricing-view table-view" data-wholesale_price="' . $product_active_price . '" data-product_quantity_mapping="' . htmlspecialchars( wp_json_encode( $wc_wholesale_prices_premium->wwpp_wc_product_on->reformat_qty_mapping( $cart_qty_discount_mapping, 'general' ) ), ENT_QUOTES, 'UTF-8' ) . '">';
                $qty_table .= '<thead>';
                $qty_table .= '<tr>';

                // Headers.
                foreach ( $headers as $header ) {
                    $qty_table .= "<th>{$header}</th>";
                }

                $qty_table .= '</tr>';
                $qty_table .= '</thead>';
                $qty_table .= '<tbody>';

                foreach ( $cart_qty_discount_mapping as $index => $mapping_data ) {

                    if ( isset( $mapping_data['wholesale_role'] ) && $user_wholesale_role[0] === $mapping_data['wholesale_role'] ) {

                        if ( ! $has_range_discount ) {
                            $has_range_discount = true;
                        }

                        $product_computed_price = $product_active_price - ( ( $mapping_data['percent_discount'] / 100 ) * $product_active_price );
                        $product_computed_price = WWP_Helper_Functions::wwp_formatted_price( $product_computed_price );

                        if ( isset( $mapping_data['end_qty'] ) && '' !== $mapping_data['end_qty'] ) {
                            $qty_range = $mapping_data['start_qty'] . ' - ' . $mapping_data['end_qty'];
                        } else {
                            $qty_range = $mapping_data['start_qty'] . '+';
                        }

                        $qty_table .= '<tr>';
                        if ( isset( $headers['qty'] ) ) {
                            $qty_table .= '<td>' . $qty_range . '</td>';
                        }
                        if ( isset( $headers['price'] ) ) {
                            $qty_table .= '<td>' . $product_computed_price . '</td>';
                        }

                        if ( isset( $headers['save'] ) ) {
                            $qty_table .= '<td>' . $mapping_data['percent_discount'] . '%</td>';
                        }

                        $qty_table .= '</tr>';

                    }
                }

                $qty_table .= '</tbody>';
                $qty_table .= '</table>';

                if ( $has_range_discount ) {
                    return apply_filters( 'wwpp_qty_based_table_general_level', $wholesale_price_html . $qty_table, $cart_qty_discount_mapping, $product, $user_wholesale_role, $source );
                }
            }

            return $wholesale_price_html;
        }

        /**
         * Quantity based discount description.
         *
         * @since 1.25.2
         * @param WC_Product $product Product object.
         *
         * @return string
         */
        public function get_wholesale_price_qty_discount_description( $product ) {

            $user_id   = $this->get_current_user_id();
            $desc_text = '';

            if ( get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount_mode_2' ) === 'yes' ) {
                $desc_text = WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variation' ? __( 'Quantity based discounts available based on how many of this variation are in your cart.', 'woocommerce-wholesale-prices-premium' ) : __( 'Quantity based discounts available based on how many of this product are in your cart.', 'woocommerce-wholesale-prices-premium' );
            } else {
                $desc_text = __( 'Quantity based discounts available based on how many items are in your cart.', 'woocommerce-wholesale-prices-premium' );
            }

            if ( get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) === 'yes' &&
                get_user_meta( $user_id, 'wwpp_override_wholesale_discount_qty_discount_mapping', true ) === 'specify_general_per_wholesale_role_qty_mapping' ) {

                if ( get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping_mode_2', true ) === 'yes' ) {
                    $desc_text = WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variation' ? __( 'Quantity based discounts available based on how many of this variation are in your cart.', 'woocommerce-wholesale-prices-premium' ) : __( 'Quantity based discounts available based on how many of this product are in your cart.', 'woocommerce-wholesale-prices-premium' );
                } else {
                    $desc_text = __( 'Quantity based discounts available based on how many items are in your cart.', 'woocommerce-wholesale-prices-premium' );
                }
            }

            return apply_filters( 'wwpp_per_wholesale_role_level_qty_discount_table_desc', $desc_text );
        }

        /**
         * Apply wholesale role general discount to the product being purchased by this user.
         * Only applies if
         * General discount is set for this wholesale role
         * No category level discount is set
         * No wholesale price is set
         *
         * @since 1.2.0
         * @since 1.16.0
         * Now calculates price with wholesale role cart quantity based wholesale discount.
         * This function was previously named as 'applyWholesaleRoleGeneralDiscount' and was from class-wwpp-wholesale-prices.php.
         * Support ignore role/cat level wholesale pricing feature.
         * @since 1.23.5 Display correct wholesale when woocommerce multilingual is enabled.
         * @since 1.27.9 Replace round with wc_format_decimal function
         * @access public
         *
         * @param array        $wholesale_price_arr Wholesale price array data.
         * @param int          $product_id          Product id.
         * @param array        $user_wholesale_role User wholesale roles.
         * @param null|array   $cart_item           Cart item. Null if this callback is being called by the 'wwp_filter_wholesale_price_shop' filter.
         * @param null|WC_Cart $cart_object         Cart object. Null if this callback is being called by the 'wwp_filter_wholesale_price_shop' filter.
         * @return array Filtered wholesale price array data.
         */
        public function apply_wholesale_role_general_discount( $wholesale_price_arr, $product_id, $user_wholesale_role, $cart_item, $cart_object ) {

            if ( ! empty( $user_wholesale_role ) && isset( $user_wholesale_role[0] ) && empty( $wholesale_price_arr['wholesale_price'] ) ) {

                $product = wc_get_product( $product_id );

                if ( $product->get_type() === 'variable' ) {
                    return $wholesale_price_arr;
                }

                $post_id = ( 'variation' === $product->get_type() ) ? $product->get_parent_id() : $product_id;

                // Only show wholesale products to wholesale users.
                $only_show_to_wholesale_users = get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ? true : false;

                // General discount.
                $wholesale_role_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

                // Ignore role level discount.
                $disregard_role_level_discount = apply_filters( 'wwpp_disregard_role_level_discount', get_post_meta( $post_id, 'wwpp_ignore_role_level_wholesale_discount', true ) );

                // Variations that has wholesale price.
                $variations_with_wholesale_price = isset( $user_wholesale_role[0] ) ? get_post_meta( $post_id, $user_wholesale_role[0] . '_variations_with_wholesale_price' ) : array();

                // Only Show Wholesale Products To Wholesale Customers is enabled
                // No General Discount
                // Has General Level Wholesale Pricing
                // Has variations with wholesale price set in product level.
                if ( $only_show_to_wholesale_users && ! array_key_exists( $user_wholesale_role[0], $wholesale_role_discount ) && ! empty( $variations_with_wholesale_price ) ) {
                    return $wholesale_price_arr;
                }

                if ( 'yes' === $disregard_role_level_discount ) {
                    return $wholesale_price_arr;
                }

                // Per user mapping.
                $current_user_id = $this->get_current_user_id();
                $puwd            = get_user_meta( $current_user_id, 'wwpp_wholesale_discount', true );

                // Check if theres general quantity discount.
                $user_wholesale_discount = $this->get_user_wholesale_role_level_discount( $current_user_id, $user_wholesale_role[0], $cart_item, $cart_object );

                if ( is_numeric( $user_wholesale_discount['discount'] ) && ! empty( $user_wholesale_discount['discount'] ) && ( isset( $wholesale_role_discount[ $user_wholesale_role[0] ] ) || ! empty( $puwd ) ) ) {

                    $use_regular_price = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );

                    if ( $product->is_on_sale() && 'yes' !== $use_regular_price ) {
                        $product_price = $product->get_sale_price();
                    } else {
                        $product_price = $product->get_regular_price();
                    }

                    // WOOCS & Product Bundle Compatibility.
                    $decimal_precission = wc_get_price_decimals();

                    if ( class_exists( 'WOOCS' ) && WWP_Helper_Functions::is_plugin_active( 'woocommerce-product-bundles/woocommerce-product-bundles.php' ) ) {

                        global $WOOCS, $post;

                        // Returns a map of bundled item IDs to product bundle IDs associated with a (bundled) product.
                        $bundle_ids = array_values( wc_pb_get_bundled_product_map( $product ) );

                        // Check if the product has been associated with bundled product.
                        if ( in_array( $post->ID, $bundle_ids, true ) ) {
                            // WOOCS has known issue with the product bundle, where the price will converted twice on bundled items.
                            // We will do back convert calculation to the price, so the price will be converted properly.
                            // Previously we will round the decimal to 2, but some values might shows discrepancy after doing back convert,
                            // So we will round the decimal to 4, so we get the proper decimal value after doing back convert.
                            if ( 'variation' === WWP_Helper_Functions::wwp_get_product_type( $product ) && wc_get_price_decimals() <= 4 ) {
                                if ( $WOOCS->current_currency !== $WOOCS->default_currency ) {

                                    if ( $WOOCS->is_multiple_allowed ) {
                                        $decimal_precission = 4;
                                    }
                                }
                            } elseif ( 'simple' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {
                                // Somehow WOOCS treated discounted bundled product as a sale product, even the Sale price is empty.
                                // This makes is_on_sale() is set to true, while the sale price is not exist.
                                // So we need to make the price use the regular price instead the sale price if the sale price is empty.
                                // This only occurs for simple product.
                                if ( $product->is_on_sale() && ! $product->get_sale_price() ) {
                                    $product_price = $product->get_regular_price();
                                }
                            }
                        }
                    }

                    // WCML Compatibility.
                    $product_price = WWPP_Helper_Functions::get_product_default_currency_price( $product_price, $product );

                    if ( is_numeric( $product_price ) && $product_price ) {

                        switch ( $user_wholesale_discount['source'] ) {

                            case 'wholesale_role_level_qty_based':
                                $wholesale_price_arr['wholesale_price'] = wc_format_decimal( $product_price - ( ( $user_wholesale_discount['discount'] / 100 ) * $product_price ), $decimal_precission );
                                break;

                            case 'wholesale_role_level':
                                $wholesale_price_arr['wholesale_price'] = wc_format_decimal( $product_price - ( ( $wholesale_role_discount[ $user_wholesale_role[0] ] / 100 ) * $product_price ), $decimal_precission );
                                break;

                            case 'per_user_level_qty_based':
                                $wholesale_price_arr['wholesale_price'] = wc_format_decimal( $product_price - ( ( $user_wholesale_discount['discount'] / 100 ) * $product_price ), $decimal_precission );
                                break;

                            case 'per_user_level':
                                $wholesale_price_arr['wholesale_price'] = wc_format_decimal( $product_price - ( ( $puwd / 100 ) * $product_price ), $decimal_precission );
                                break;

                        }

                        $wholesale_price_arr['source'] = $user_wholesale_discount['source'];

                    }
                }
            }

            return $wholesale_price_arr;
        }

        /**
         * Get specific user wholesale discount.
         *
         * @since 1.16.0
         * @since 1.27.10 - Don't run per user level wholesale discount if there's no general wholesale discount defined
         * @access public
         *
         * @param int    $user_id             User id.
         * @param string $user_wholesale_role User wholesale role.
         * @param array  $cart_item           Cart item.
         * @param object $cart_object         Cart object.
         * @return array Wholesale discount array data.
         */
        public function get_user_wholesale_role_level_discount( $user_id, $user_wholesale_role, $cart_item = null, $cart_object = null ) {

            $user_wholesale_discount                      = array(
                'source'   => false,
                'discount' => false,
            );
            $user_wholesale_discount_from_general_mapping = false;

            if ( get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount_mode_2' ) === 'yes' && ! is_null( $cart_item ) ) {
                $total_items = $cart_item['quantity'];
            } else {
                $total_items = ! is_null( $cart_object ) ? $cart_object->get_cart_contents_count() : 0;
            }

            $wholesale_role_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );
            if ( ! is_array( $wholesale_role_discount ) ) {
                $wholesale_role_discount = array();
            }

            $user_wholesale_role = empty( $user_wholesale_role ) ? '' : $user_wholesale_role;

            if ( array_key_exists( $user_wholesale_role, $wholesale_role_discount ) && ! empty( $wholesale_role_discount[ $user_wholesale_role ] ) ) {

                $user_wholesale_discount = array(
                    'source'   => 'wholesale_role_level',
                    'discount' => $wholesale_role_discount[ $user_wholesale_role ],
                );

                // Maybe process cart qty based wholesale role discount.
                if ( ! empty( $user_wholesale_discount['discount'] ) && ! is_null( $cart_item ) && ! is_null( $cart_object ) && get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount' ) === 'yes' ) {

                    $cart_qty_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                    if ( ! is_array( $cart_qty_discount_mapping ) ) {
                        $cart_qty_discount_mapping = array();
                    }

                    $temp_value = $this->_get_discount_from_qty_mapping( $cart_qty_discount_mapping, $total_items, $user_wholesale_role );

                    if ( false !== $temp_value ) {

                        $user_wholesale_discount                      = array(
                            'source'   => 'wholesale_role_level_qty_based',
                            'discount' => $temp_value,
                        );
                        $user_wholesale_discount_from_general_mapping = array(
                            'source'   => 'wholesale_role_level_qty_based',
                            'discount' => $temp_value,
                        );

                    }
                }

                if ( 'yes' === get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) ) {

                    $puwd = get_user_meta( $user_id, 'wwpp_wholesale_discount', true );

                    if ( is_numeric( $puwd ) || empty( $puwd ) ) {
                        $user_wholesale_discount = array(
                            'source'   => 'per_user_level',
                            'discount' => $puwd,
                        );
                    }

                    if ( ! empty( $user_wholesale_discount['discount'] ) && ! is_null( $cart_item ) && ! is_null( $cart_object ) && get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount' ) === 'yes' ) {

                        switch ( get_user_meta( $user_id, 'wwpp_override_wholesale_discount_qty_discount_mapping', true ) ) {

                            case 'dont_use_general_per_wholesale_role_qty_mapping':
                                break;

                            case 'use_general_per_wholesale_role_qty_mapping':
                                $user_wholesale_discount = false !== $user_wholesale_discount_from_general_mapping ? $user_wholesale_discount_from_general_mapping : $user_wholesale_discount;
                                break;

                            case 'specify_general_per_wholesale_role_qty_mapping':
                                // Get the general qty based wholesale discount mapping.
                                $cart_qty_general_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                                if ( ! is_array( $cart_qty_general_discount_mapping ) ) {
                                    $cart_qty_general_discount_mapping = array();
                                }

                                if ( ! empty( $cart_qty_general_discount_mapping ) ) {
                                    // Check if wholesale user has general qty based wholesale discount for the wholesale role.
                                    $has_cart_qty_general_discount_mapping = array_search( $user_wholesale_role, array_column( $cart_qty_general_discount_mapping, 'wholesale_role' ), true );

                                    if ( is_numeric( $has_cart_qty_general_discount_mapping ) ) {
                                        $total_items = get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping_mode_2', true ) === 'yes' ? $total_items = $cart_item['quantity'] : $cart_object->get_cart_contents_count();

                                        // Get the user level override qty based wholesale discount mapping.
                                        $cart_qty_discount_mapping = get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true );
                                        if ( ! is_array( $cart_qty_discount_mapping ) ) {
                                            $cart_qty_discount_mapping = array();
                                        }

                                        $temp_value = $this->_get_discount_from_qty_mapping( $cart_qty_discount_mapping, $total_items, $user_wholesale_role );

                                        if ( false !== $temp_value ) {
                                            $user_wholesale_discount = array(
                                                'source'   => 'per_user_level_qty_based',
                                                'discount' => $temp_value,
                                            );
                                        }
                                    }
                                }
                                break;

                        }
                    }
                }
            }

            return $user_wholesale_discount;
        }

        /*
        |--------------------------------------------------------------------------------------------------------------------
        | Helper Functions
        |--------------------------------------------------------------------------------------------------------------------
         */

        /**
         * Get wholesale discount of a cart quantity from the set quantity discount mapping.
         *
         * @since 1.16.0
         * @access private
         *
         * @param array  $cart_qty_discount_mapping Array of qty discount mapping.
         * @param int    $cart_total_items          Total items on cart.
         * @param string $user_wholesale_role       User wholesale role.
         * @return boolean|string Boolean false if mapping is empty or no entry on mapping, string of discount when there is an entry on the mapping.
         */
        private function _get_discount_from_qty_mapping( $cart_qty_discount_mapping, $cart_total_items, $user_wholesale_role ) {

            if ( ! empty( $cart_qty_discount_mapping ) ) {

                foreach ( $cart_qty_discount_mapping as $mapkey => $mapping ) {

                    if ( 'rawTable' !== $mapkey ) {
                        if ( $user_wholesale_role === $mapping['wholesale_role'] && $cart_total_items >= $mapping['start_qty'] &&
                            ( empty( $mapping['end_qty'] ) || $cart_total_items <= $mapping['end_qty'] ) &&
                            ! empty( $mapping['percent_discount'] ) ) {

                            return $mapping['percent_discount'];

                        }
                    }
                }
            }

            return false;
        }

        /**
         * Return current logged-in user id.
         *
         * @since 1.25.2
         * @since 1.27      Rename function and filter to suite with its function.
         * @access private
         */
        private function get_current_user_id() {

            return apply_filters( 'wwpp_get_current_user_id', get_current_user_id() );
        }

        /*
        |--------------------------------------------------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.16.0
         * @access public
         */
        public function run() {

            add_filter( 'wwp_filter_wholesale_price_html', array( $this, 'render_wholesale_role_cart_quantity_based_wholesale_discount_table_markup' ), 200, 7 );
            add_filter( 'wwp_filter_wholesale_price_shop', array( $this, 'apply_wholesale_role_general_discount' ), 200, 5 );
            add_filter( 'wwp_filter_wholesale_price_cart', array( $this, 'apply_wholesale_role_general_discount' ), 200, 5 );
        }
    }
}
