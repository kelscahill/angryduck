<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_API_Helpers' ) ) {

    /**
     * Model that houses the logic of the WWPP API Helpers.
     *
     * @since 1.0
     */
    class WWPP_API_Helpers {


        /**
         * Custom method that check if there is a wholesale percentage discount set via the General Discount options
         *
         * @param string $wholesale_role The wholesale role to check.
         *
         * @since 1.18
         * @since 1.27      Transferred this function from WWPP_REST_Wholesale_Products_V1_Controller into WWPP_API_Helpers
         * @access public
         * @return bool|int
         */
        public static function has_wholesale_general_discount( $wholesale_role ) {
            global $wc_wholesale_prices;

            $registered_wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            $wholesale_role_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

            if ( ! empty( $wholesale_role_discount ) && empty( $wholesale_role ) ) {
                return true;
            }

            if ( array_key_exists( $wholesale_role, $registered_wholesale_roles ) && array_key_exists( $wholesale_role, $wholesale_role_discount ) ) {
                return $wholesale_role_discount[ $wholesale_role ];
            }

            return false;
        }

        /**
         * Check if quantity discount is enabled
         *
         * @param int             $product_id           The product ID.
         * @param array           $wholesale_price_data The wholesale price data.
         * @param WC_Product      $product              The product object.
         * @param WP_REST_Request $request              The wholesale role.
         *
         * @since 1.25.2
         * @since 1.30.3 Skip checking if the product type is a variable product
         * @access public
         * @return array
         */
        public static function is_quantity_discount_rule_enabled( $product_id, $wholesale_price_data, $product, $request ) {
            // Hide the quantity discount table.
            if ( get_option( 'wwpp_settings_hide_quantity_discount_table' ) === 'yes' ) {
                return false;
            }

            global $wc_wholesale_prices_premium;

            $wholesale_role       = sanitize_text_field( $request['wholesale_role'] );
            $wholesale_price_data = self::get_wholesale_discount_source( $wholesale_price_data, $wholesale_role, $product, $request );

            if ( ( empty( $wholesale_price_data['source'] ) && ! isset( $wholesale_price_data['source'] ) ) || $product->get_type() === 'variable' ) {
                return false;
            }

            $enable_rule_mapping = false;

            switch ( $wholesale_price_data['source'] ) {
                case 'per_product_level':
                    $enable_rule_mapping = get_post_meta( $product_id, 'wwpp_post_meta_enable_quantity_discount_rule', true );
                    if ( empty( $enable_rule_mapping ) && $product->get_type() === 'variation' ) {
                        $enable_rule_mapping = get_post_meta( $product->get_parent_id(), 'wwpp_post_meta_enable_quantity_discount_rule', true );
                    }
                    break;
                case 'product_category_level':
                    $prodId                    = $product->get_type() === 'variation' ? $product->get_parent_id() : $product->get_id();
                    $base_term_id_and_discount = $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( $prodId, array( $wholesale_role ) );
                    $enable_rule_mapping       = get_term_meta( $base_term_id_and_discount['term_id'], 'wwpp_enable_quantity_based_wholesale_discount', true );
                    break;
                case 'wholesale_role_level':
                    // Override General Discount per user.
                    if ( get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount' ) === 'yes' ) {
                        $enable_rule_mapping = 'yes';
                    }
                    break;
                case 'per_user_level':
                    $user_id = sanitize_text_field( $request['uid'] );
                    if ( get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) === 'yes' ) {
                        $enable_rule_mapping = 'yes';
                    }
                    break;
                default:
                    return false;
            }

            return 'yes' === $enable_rule_mapping ? true : false;
        }

        /**
         * Get the quantity discount mapping.
         *
         * @param int             $product_id           The product ID.
         * @param array           $wholesale_price_data The wholesale price data.
         * @param WC_Product      $product              The product object.
         * @param WP_REST_Request $request              The wholesale role.
         *
         * @since 1.25.2
         * @access public
         * @return array
         */
        public static function get_quantity_discount_mapping( $product_id, $wholesale_price_data, $product, $request ) {
            global $wc_wholesale_prices_premium;

            $wholesale_role       = sanitize_text_field( $request['wholesale_role'] );
            $wholesale_price_data = self::get_wholesale_discount_source( $wholesale_price_data, $wholesale_role, $product, $request );

            if ( empty( $wholesale_price_data['source'] ) && ! isset( $wholesale_price_data['source'] ) ) {
                return false;
            }

            $qty_discount_rule_mapping = array();

            switch ( $wholesale_price_data['source'] ) {
                case 'per_product_level':
                    $qty_discount_rule_mapping = get_post_meta( $product_id, 'wwpp_post_meta_quantity_discount_rule_mapping', true );
                    if ( empty( $qty_discount_rule_mapping ) && $product->get_type() === 'variation' ) {
                        $qty_discount_rule_mapping = get_post_meta( $product->get_parent_id(), 'wwpp_post_meta_quantity_discount_rule_mapping', true );
                    }
                    break;
                case 'product_category_level':
                    $product_id                = $product->get_type() === 'variation' ? $product->get_parent_id() : $product->get_id();
                    $base_term_id_and_discount = $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( $product_id, array( $wholesale_role ) );
                    $qty_discount_rule_mapping = get_term_meta( $base_term_id_and_discount['term_id'], 'wwpp_quantity_based_wholesale_discount_mapping', true );
                    break;
                case 'wholesale_role_level':
                    $qty_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                    break;
                case 'per_user_level':
                    $user_id = sanitize_text_field( $request['uid'] );
                    switch ( get_user_meta( $user_id, 'wwpp_override_wholesale_discount_qty_discount_mapping', true ) ) {

                        case 'dont_use_general_per_wholesale_role_qty_mapping':
                            break;

                        case 'use_general_per_wholesale_role_qty_mapping':
                            $qty_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
                            break;

                        case 'specify_general_per_wholesale_role_qty_mapping':
                            $qty_discount_rule_mapping = get_user_meta( $user_id, 'wwpp_wholesale_discount_qty_discount_mapping', true );

                    }
                    break;
            }

            if ( ! is_array( $qty_discount_rule_mapping ) ) {
                $qty_discount_rule_mapping = array();
            }

            if ( ! empty( $qty_discount_rule_mapping ) ) {
                return self::set_pricing_quantity_discount_mapping( $qty_discount_rule_mapping, $wholesale_price_data, $product, $request );
            }

            return false;
        }

        /**
         * Quantity discount price calculation.
         *
         * @param array           $mapping              The quantity discount mapping.
         * @param array           $wholesale_price_data The wholesale price data.
         * @param WC_Product      $product              The product object.
         * @param WP_REST_Request $request              The wholesale role.
         *
         * @since 1.25.2
         * @since 1.27.9 Replace round with wc_format_decimal function
         * @access public
         * @return array
         */
        public static function set_pricing_quantity_discount_mapping( $mapping, $wholesale_price_data, $product, $request ) {
            $wholesale_role = sanitize_text_field( $request['wholesale_role'] );

            global $wc_wholesale_prices_premium;

            $source              = $wholesale_price_data['source'];
            $mapping_copy        = $mapping;
            $use_regular_price   = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );
            $qty_disc_base_price = $wholesale_price_data['wholesale_price'] ? $wholesale_price_data['wholesale_price'] : 0;

            if ( 'yes' === $use_regular_price ) {
                $qty_disc_base_price = $product->get_regular_price();
            }

            $qty_disc_base_price = apply_filters( 'wwpp_quantity_based_discount_base_price', $qty_disc_base_price, $mapping, $wholesale_price_data['wholesale_price'], $product, array( $wholesale_role ) );

            foreach ( $mapping_copy as $key => $map ) {

                // Filter by wholesale role when parameter is set.
                if ( ! empty( $wholesale_role ) && isset( $map['wholesale_role'] ) && $map['wholesale_role'] !== $wholesale_role ) {
                    unset( $mapping[ $key ] );
                } elseif ( 'per_product_level' === $source ) {
                    // Only display calculated price if wholesale price is not empty.
                    // For variable product we dont have wholesale price so we will hide the calculated price.
                    if ( ! empty( $wholesale_price_data['wholesale_price'] ) ) {
                        if ( isset( $map['price_type'] ) ) {

                            // We only apply taxing on fixed price, we don't need taxing on percent price since the wholesale price is already taxed.
                            if ( 'fixed-price' === $map['price_type'] ) {
                                $price = wc_format_decimal( $map['wholesale_price'], '' );
                                $price = $wc_wholesale_prices_premium->wwpp_wholesale_prices->get_product_shop_price_with_taxing_applied( $product, $price, array( 'currency' => get_woocommerce_currency() ), $wholesale_role );
                            } elseif ( 'percent-price' === $map['price_type'] ) {
                                $price = wc_format_decimal( $qty_disc_base_price - ( ( $map['wholesale_price'] / 100 ) * $qty_disc_base_price ), '' );
                                $price = WWP_Helper_Functions::wwp_formatted_price( $price );
                            }
                        } else {
                            $price = $wc_wholesale_prices_premium->wwpp_wholesale_prices->get_product_shop_price_with_taxing_applied( $product, $map['wholesale_price'], array( 'currency' => get_woocommerce_currency() ), $wholesale_role );
                        }

                        $mapping[ $key ]['calculated_price'] = $price;
                    }
                } else {

                    if ( 'product_category_level' === $source ) {
                        $wholesale_discount                    = $map['wholesale-discount'];
                        $mapping[ $key ]['wholesale_discount'] = $wholesale_discount;
                        unset( $mapping[ $key ]['wholesale-discount'] );
                    } else {
                        $wholesale_discount                    = $map['percent_discount'];
                        $mapping[ $key ]['wholesale_discount'] = $wholesale_discount;
                        unset( $mapping[ $key ]['percent_discount'] );
                    }

                    foreach ( $mapping[ $key ] as $map_key => $m ) {
                        if ( in_array( $map_key, array( 'wholesale-role', 'start-qty', 'end-qty' ), true ) ) {
                            $new_key                     = str_replace( '-', '_', $map_key );
                            $mapping[ $key ][ $new_key ] = $mapping[ $key ][ $map_key ];
                            unset( $mapping[ $key ][ $map_key ] );
                        }
                    }

                    // Dont display calculated price if wholesale price is empty.
                    // This also means that this product is a variable type.
                    if ( ! empty( $wholesale_price_data['wholesale_price'] ) ) {

                        if ( $product->is_on_sale() && 'yes' !== $use_regular_price ) {
                            $product_active_price = $product->get_sale_price();
                        } else {
                            $product_active_price = $product->get_regular_price();
                        }

                        // WCML Compatibility.
                        $product_active_price = WWPP_Helper_Functions::get_product_default_currency_price( $product_active_price, $product );

                        $product_id           = WWP_Helper_Functions::wwp_get_product_id( $product );
                        $product_active_price = trim( apply_filters( 'wwp_pass_wholesale_price_through_taxing', $product_active_price, $product_id, array( $wholesale_role ) ) );

                        $product_computed_price = $product_active_price - ( ( $wholesale_discount / 100 ) * $product_active_price );
                        $product_computed_price = WWP_Helper_Functions::wwp_formatted_price( $product_computed_price );

                        $mapping[ $key ]['calculated_price'] = $product_computed_price;

                    }
                }
            }

            // When filtering via wholesale role then reset index via array_values.
            return array_values( $mapping );
        }

        /**
         * Get quantity based description.
         *
         * @param array           $mapping              The mapping array.
         * @param array           $wholesale_price_data The wholesale price data.
         * @param WC_Product      $product              The product object.
         * @param WP_REST_Request $request              The request object.
         *
         * @since 1.25.2
         * @access public
         * @return array
         */
        public static function get_quantity_discount_description( $mapping, $wholesale_price_data, $product, $request ) {
            global $wc_wholesale_prices_premium;

            $wholesale_role       = sanitize_text_field( $request['wholesale_role'] );
            $wholesale_price_data = self::get_wholesale_discount_source( $wholesale_price_data, $wholesale_role, $product, $request );

            if ( empty( $wholesale_price_data['source'] ) && ! isset( $wholesale_price_data['source'] ) ) {
                return '';
            }

            switch ( $wholesale_price_data['source'] ) {
                case 'per_product_level':
                case 'wholesale_role_level':
                case 'per_user_level':
                    return $wc_wholesale_prices_premium->wwpp_wholesale_price_wholesale_role->get_wholesale_price_qty_discount_description( $product );
                case 'product_category_level':
                    // Get the base category term id.
                    $base_term_id_and_discount = $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_base_term_id_and_wholesale_discount( $product->get_id(), array( $wholesale_role ) );
                    return $wc_wholesale_prices_premium->wwpp_wholesale_price_product_category->get_wholesale_price_qty_discount_description( $product, $base_term_id_and_discount );
            }

            return '';
        }

        /**
         * Get wholesale discount source.
         *
         * @param array           $wholesale_price_data The wholesale price data.
         * @param string          $wholesale_role       The wholesale role.
         * @param WC_Product      $product              The product object.
         * @param WP_REST_Request $request              The request object.
         *
         * @since 1.27
         * @access public
         * @return array
         */
        public static function get_wholesale_discount_source( $wholesale_price_data, $wholesale_role, $product, $request ) {
            if ( ! empty( $wholesale_price_data['wholesale_price'] ) ) {
                return $wholesale_price_data;
            }

            $category_discount = $product->get_meta( $wholesale_role . '_have_wholesale_price_set_by_product_cat', true );

            if ( 'yes' === $category_discount ) {
                $wholesale_price_data['source'] = 'product_category_level';
            }

            if ( self::has_wholesale_general_discount( $wholesale_role ) ) {
                $wholesale_price_data['source'] = 'wholesale_role_level';
            }

            $user_id               = sanitize_text_field( $request['uid'] );
            $override_disc         = get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true );
            $override_disc_mapping = get_user_meta( $user_id, 'wwpp_override_wholesale_discount_qty_discount_mapping', true );

            if ( 'yes' === $override_disc && ! empty( $override_disc_mapping ) ) {
                $wholesale_price_data['source'] = 'per_user_level';
            }

            return $wholesale_price_data;
        }
    }
}
