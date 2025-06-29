<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Cart_Discounts' ) ) {

    /**
     * Model that houses the logic relating caching.
     *
     * @since 1.26
     */
    class WWPP_Cart_Discounts {

        /**
         * Property that holds the single main instance of WWPP_Cart_Discounts.
         *
         * @since 1.26
         * @access private
         * @var WWPP_Cart_Discounts
         */
        private static $_instance;

        /**
         * WWPP_Cart_Discounts constructor.
         *
         * @since 1.26
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Cart_Discounts model.
         */
        public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWPP_Cart_Discounts is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.26
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Cart_Discounts model.
         * @return WWPP_Cart_Discounts
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Apply additional discounts based on the cart subtotal.
         *
         * @since 1.26
         * @access public
         *
         * @param WC_Cart $cart Cart Object.
         */
        public function cart_subtotal_based_discount( $cart ) {

            global $wc_wholesale_prices;

            $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

            $min_order_requirement_check = $this->min_order_requirement_check( $cart, $wholesale_role );

            if ( ! empty( $wholesale_role ) && true === $min_order_requirement_check ) {

                $quantity_discount_rule_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );

                usort(
                    $quantity_discount_rule_mapping,
                    function ( $item1, $item2 ) {
                    return $item2['subtotal_price'] <=> $item1['subtotal_price'];
                    }
                );

                $subtotal        = $cart->cart_contents_total;
                $discount_amount = 0;
                $discount_title  = '';

                foreach ( $quantity_discount_rule_mapping as $mapping ) {
                    if ( $mapping['wholesale_role'] === $wholesale_role[0] && 0 === $discount_amount && $subtotal >= $mapping['subtotal_price'] ) {
                        if ( 'percent-discount' === $mapping['discount_type'] ) {
                            $discount_amount = $subtotal * ( $mapping['discount_amount'] / 100 );
                        } else {
                            $discount_amount = $mapping['discount_amount'];
                        }
                        $discount_title = $mapping['discount_title'];
                    }
                }

                if ( $discount_amount > 0 ) {
                    $subtotal_based_discount = apply_filters(
                        'wwpp_cart_subtotal_based_discount',
                        array(
							'discount_title'  => $discount_title,
							'discount_amount' => $discount_amount,
                        )
                    );
                    $cart->add_fee( $subtotal_based_discount['discount_title'], -$subtotal_based_discount['discount_amount'] );
                }
            }
        }

        /**
         * Check if the min order requirement is met.
         * Note: Most of the codes here can be seen in WWP function apply_product_wholesale_price_to_cart
         *
         * @since 1.26
         * @access public
         *
         * @param WC_Cart $cart           Cart Object.
         * @param array   $wholesale_role Wholesale Role.
         *
         * @return WWPP_Cart_Discounts
         */
        public function min_order_requirement_check( $cart, $wholesale_role ) {

            global $wc_wholesale_prices;

            $only_apply_discount_if_min_order_req_met = get_option( 'enable_wholesale_role_cart_only_apply_discount_if_min_order_req_met', 'no' );
            $cart_total                               = 0;
            $cart_items                               = 0;

            foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {

                $wholesale_price = '';

                if ( in_array( WWP_Helper_Functions::wwp_get_product_type( $cart_item['data'] ), array( 'simple', 'variation' ), true ) ) {
                    $wholesale_price = $wc_wholesale_prices->wwp_wholesale_prices::get_product_wholesale_price_on_cart( WWP_Helper_Functions::wwp_get_product_id( $cart_item['data'] ), $wholesale_role, $cart_item, $cart );
                } else {
                    $wholesale_price = apply_filters( 'wwp_filter_get_custom_product_type_wholesale_price', $wholesale_price, $cart_item, $wholesale_role, $cart );
                }

                if ( $wholesale_price ) {

                    if ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' ) {
                        $wp = wc_get_price_excluding_tax(
                            $cart_item['data'],
                            array(
								'qty'   => 1,
								'price' => $wholesale_price,
                            )
                        );
                    } else {
                        $wp = $wholesale_price;
                    }
                } else {
                    $wp = $cart_item['data']->get_price();
                }

                $cart_total += (float) $wp * (float) $cart_item['quantity'];
                $cart_items += (float) $cart_item['quantity'];

            }

            if ( 'yes' === $only_apply_discount_if_min_order_req_met ) {
                $apply_wholesale_price_cart_level = apply_filters( 'wwp_apply_wholesale_price_cart_level', true, $cart_total, $cart_items, $cart, $wholesale_role );
            } else {
                $apply_wholesale_price_cart_level = true;
            }

            return apply_filters( 'wwpp_min_order_requirement_check', $apply_wholesale_price_cart_level, $cart_total, $cart_items, $cart, $wholesale_role );
        }

        /**
         * Execute model.
         *
         * @since 1.26
         * @access public
         */
        public function run() {

            add_action( 'woocommerce_cart_calculate_fees', array( $this, 'cart_subtotal_based_discount' ), 10, 1 );
        }
    }

}
