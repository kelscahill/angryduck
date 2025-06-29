<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_WC_Subscriptions' ) ) {

    /**
     * Model that houses the logic of integrating with 'WooCommerce Subscription' plugin.
     *
     * @since 2.0.2
     */
    class WWPP_WC_Subscriptions {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
        */

        /**
         * Property that holds the single main instance of WWPP_WC_Subscriptions.
         *
         * @since 2.0.2
         * @access private
         * @var WWPP_WC_Subscriptions
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since  1.16.0
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWPP_WC_Subscriptions constructor.
         *
         * @since 2.0.2
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Subscriptions model.
         */
        public function __construct( $dependencies = array() ) {
            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_WC_Subscriptions is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.0.2
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Subscriptions model.
         * @return WWPP_WC_Subscriptions
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get curent user wholesale role.
         *
         * @since  1.16.0
         * @access private
         *
         * @return string User role string or empty string.
         */
        private function _get_current_user_wholesale_role() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $wholesale_role = ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';

            return apply_filters( 'wwpp_get_current_wholesale_role', $wholesale_role );
        }

        /**
         * Get wholesale subscription product price string.
         *
         * @param string     $subscription_string Subscription string.
         * @param WC_Product $product Product object.
         * @param bool       $include Include.
         *
         * @since 2.0.2
         * @return string
         */
        public function wholesale_subscriptions_product_price_string( $subscription_string, $product, $include ) { // phpcs:ignore

            $user_wholesale_role = $this->_get_current_user_wholesale_role();
            if ( ! empty( $user_wholesale_role ) ) {
                $wholesale_price = $this->get_wholesale_subscription_price( $product, $user_wholesale_role );

                if ( ! empty( $wholesale_price ) ) {
                    $original_price_string = '<del class="original-computed-price">' . $subscription_string . '</del>';
                    $price_string          = apply_filters( 'wwpp_filter_wholesale_subscription_price_string', $original_price_string, $wholesale_price, $product, $user_wholesale_role );
                    $price_string         .= $this->get_subscription_price_string( $product );
                    return $price_string;
                }
            }

            return $subscription_string;
        }

        /**
         * Get subscription product price string.
         *
         * @param WC_Product $product Product object.
         * @param string     $price Price string.
         * @param string     $wholesale_price_fee Wholesale price fee.
         * @param string     $user_wholesale_role User wholesale role.
         * @param bool       $with_wholesale_text No wholesale text.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_subscription_price_string( $product, $price = '', $wholesale_price_fee = '', $user_wholesale_role = '', $with_wholesale_text = true ) {
            global $wp_locale;

            if ( empty( $user_wholesale_role ) ) {
                $user_wholesale_role = $this->_get_current_user_wholesale_role();
            }

            $raw_wholesale_price = $price;

            if ( empty( $price ) ) {
                // Get the product price.
                $price = $product->get_price();

                // Get the wholesale price.
                $raw_wholesale_price = $this->get_wholesale_subscription_price( $product, $user_wholesale_role );
            }

            if ( strcasecmp( $raw_wholesale_price, '' ) !== 0 ) {
                $wholesale_price            = WWP_Helper_Functions::wwp_formatted_price( $raw_wholesale_price );
                $wholesale_price_title_text = '';

                if ( $with_wholesale_text ) {
                    $wholesale_price_title_text = __( 'Wholesale Price:', 'woocommerce-wholesale-prices-premium' );
                    $wholesale_price_title_text = apply_filters( 'wwp_filter_wholesale_price_title_text', $wholesale_price_title_text );

                    $wholesale_price_html = '<span style="display: block;" class="wholesale_price_container">
                    <span class="wholesale_price_title">' . $wholesale_price_title_text . '</span>
                    <ins>' . $wholesale_price . '</ins>
                    </span>';
                } else {
                    $wholesale_price_html = $wholesale_price;
                }

                $price = apply_filters( 'wwpp_filter_wholesale_subscription_price_html', $wholesale_price_html, $price, $product, array( $user_wholesale_role ), $wholesale_price_title_text, $raw_wholesale_price );
            } else {
                return '';
            }

            $include = array(
                'price'               => $price,
                'tax_calculation'     => get_option( 'woocommerce_tax_display_shop' ),
                'subscription_price'  => true,
                'subscription_period' => true,
                'subscription_length' => true,
                'sign_up_fee'         => true,
                'trial_length'        => true,
            );

            $include = apply_filters( 'woocommerce_subscriptions_product_price_string_inclusions', $include, $product );

            $base_price          = $this->get_price( $product );
            $billing_interval    = (int) $this->get_interval( $product );
            $billing_period      = $this->get_period( $product );
            $subscription_length = (int) $this->get_length( $product );
            $trial_length        = (int) $this->get_trial_length( $product );
            $trial_period        = $this->get_trial_period( $product );
            $sign_up_fee         = 0;
            $include_length      = $include['subscription_length'] && 0 !== $subscription_length;

            if ( empty( $billing_period ) ) {
                $billing_period = 'month';
            }

            if ( $include_length ) {
                $ranges = wcs_get_subscription_ranges( $billing_period );
            }

            if ( $include['sign_up_fee'] ) {
                $sign_up_fee = is_bool( $include['sign_up_fee'] ) ? $this->get_sign_up_fee( $product, $user_wholesale_role ) : $include['sign_up_fee'];
            }

            if ( $include['tax_calculation'] ) {
                if ( in_array( $include['tax_calculation'], array( 'exclude_tax', 'excl' ), true ) ) {
                    // Calculate excluding tax.
                    $price = isset( $include['price'] ) ? $include['price'] : wcs_get_price_excluding_tax( $product );
                    if ( true === $include['sign_up_fee'] ) {
                        $sign_up_fee = wcs_get_price_excluding_tax( $product, array( 'price' => $this->get_sign_up_fee( $product, $user_wholesale_role ) ) );
                    }
                } else {
                    // Calculate including tax.
                    $price = isset( $include['price'] ) ? $include['price'] : wcs_get_price_including_tax( $product );
                    if ( true === $include['sign_up_fee'] ) {
                        $sign_up_fee = wcs_get_price_including_tax( $product, array( 'price' => $this->get_sign_up_fee( $product, $user_wholesale_role ) ) );
                    }
                }
            } else {
                $price = isset( $include['price'] ) ? $include['price'] : wc_price( $base_price );
            }

            // Get the wholesale sign up fee.
            if ( ! empty( $wholesale_price_fee ) ) {
                $sign_up_fee = $wholesale_price_fee;
            }

            if ( is_numeric( $sign_up_fee ) ) {
                $sign_up_fee = wc_price( $sign_up_fee );
            }

            $price .= ' <span class="subscription-details">';

            $subscription_string = '';

            if ( $include['subscription_price'] && $include['subscription_period'] ) { // Allow extensions to not show price or billing period e.g. Name Your Price.
                if ( $include_length && $subscription_length === $billing_interval ) {
                    $subscription_string = $price; // Only for one billing period so show "$5 for 3 months" instead of "$5 every 3 months for 3 months".
                } elseif ( WC_Subscriptions_Synchroniser::is_product_synced( $product ) && in_array( $billing_period, array( 'week', 'month', 'year' ), true ) ) {
                    $subscription_string = '';

                    if ( WC_Subscriptions_Synchroniser::is_payment_upfront( $product ) && ! WC_Subscriptions_Synchroniser::is_today( WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product, 'timestamp' ) ) ) {
                        /* translators: %1$s refers to the price. This string is meant to prefix another string below, e.g. "$5 now, and $5 on March 15th each year" */
                        $subscription_string = sprintf( __( '%1$s now, and ', 'woocommerce-subscriptions' ), $price );
                    }

                    $payment_day = WC_Subscriptions_Synchroniser::get_products_payment_day( $product );
                    switch ( $billing_period ) {
                        case 'week':
                            $payment_day_of_week = WC_Subscriptions_Synchroniser::get_weekday( $payment_day );
                            if ( 1 === $billing_interval ) {
                                // translators: 1$: recurring amount string, 2$: day of the week (e.g. "$10 every Wednesday").
                                $subscription_string .= sprintf( __( '%1$s every %2$s', 'woocommerce-subscriptions' ), $price, $payment_day_of_week );
                            } else {
                                $subscription_string .= sprintf(
                                    // translators: 1$: recurring amount string, 2$: period, 3$: day of the week (e.g. "$10 every 2nd week on Wednesday").
                                    __( '%1$s every %2$s on %3$s', 'woocommerce-subscriptions' ),
                                    $price,
                                    wcs_get_subscription_period_strings( $billing_interval, $billing_period ),
                                    $payment_day_of_week
                                );
                            }
                            break;
                        case 'month':
                            if ( 1 === $billing_interval ) {
                                if ( $payment_day > 27 ) {
                                    // translators: placeholder is recurring amount.
                                    $subscription_string .= sprintf( __( '%s on the last day of each month', 'woocommerce-subscriptions' ), $price );
                                } else {
                                    $subscription_string .= sprintf(
                                        // translators: 1$: recurring amount, 2$: day of the month (e.g. "23rd") (e.g. "$5 every 23rd of each month").
                                        __( '%1$s on the %2$s of each month', 'woocommerce-subscriptions' ),
                                        $price,
                                        wcs_append_numeral_suffix( $payment_day )
                                    );
                                }
                            } elseif ( $payment_day > 27 ) {
                                    $subscription_string .= sprintf(
                                        // translators: 1$: recurring amount, 2$: interval (e.g. "3rd") (e.g. "$10 on the last day of every 3rd month").
                                        __( '%1$s on the last day of every %2$s month', 'woocommerce-subscriptions' ),
                                        $price,
                                        wcs_append_numeral_suffix( $billing_interval )
                                    );
                            } else {
                                $subscription_string .= sprintf(
                                    // translators: 1$: <price> on the, 2$: <date> day of every, 3$: <interval> month (e.g. "$10 on the 23rd day of every 2nd month").
                                    __( '%1$s on the %2$s day of every %3$s month', 'woocommerce-subscriptions' ),
                                    $price,
                                    wcs_append_numeral_suffix( $payment_day ),
                                    wcs_append_numeral_suffix( $billing_interval )
                                );
                            }
                            break;
                        case 'year':
                            if ( 1 === $billing_interval ) {
                                $subscription_string .= sprintf(
                                    // translators: 1$: <price> on, 2$: <date>, 3$: <month> each year (e.g. "$15 on March 15th each year").
                                    __( '%1$s on %2$s %3$s each year', 'woocommerce-subscriptions' ),
                                    $price,
                                    $wp_locale->month[ $payment_day['month'] ],
                                    wcs_append_numeral_suffix( $payment_day['day'] )
                                );
                            } else {
                                $subscription_string .= sprintf(
                                    // translators: 1$: recurring amount, 2$: month (e.g. "March"), 3$: day of the month (e.g. "23rd").
                                    __( '%1$s on %2$s %3$s every %4$s year', 'woocommerce-subscriptions' ),
                                    $price,
                                    $wp_locale->month[ $payment_day['month'] ],
                                    wcs_append_numeral_suffix( $payment_day['day'] ),
                                    wcs_append_numeral_suffix( $billing_interval )
                                );
                            }
                            break;
                    }
                } else {
                    $subscription_string = sprintf(
                        // translators: 1$: recurring amount, 2$: subscription period (e.g. "month" or "3 months") (e.g. "$15 / month" or "$15 every 2nd month").
                        _n( '%1$s / %2$s', '%1$s every %2$s', $billing_interval, 'woocommerce-subscriptions' ),
                        $price,
                        wcs_get_subscription_period_strings( $billing_interval, $billing_period )
                    );
                }
            } elseif ( $include['subscription_price'] ) {
                $subscription_string = $price;
            } elseif ( $include['subscription_period'] ) {
                $subscription_string = '<span class="subscription-details">' . sprintf(
                    // translators: billing period (e.g. "every week").
                    __( 'every %s', 'woocommerce-subscriptions' ),
                    wcs_get_subscription_period_strings( $billing_interval, $billing_period )
                );
            } else {
                $subscription_string = '<span class="subscription-details">';
            }

            // Add the length to the end.
            if ( $include_length ) {
                // translators: 1$: subscription string (e.g. "$10 up front then $5 on March 23rd every 3rd year"), 2$: length (e.g. "4 years").
                $subscription_string = sprintf( __( '%1$s for %2$s', 'woocommerce-subscriptions' ), $subscription_string, $ranges[ $subscription_length ] );
            }

            if ( $include['trial_length'] && 0 !== $trial_length ) {
                $trial_string = wcs_get_subscription_trial_period_strings( $trial_length, $trial_period );
                // translators: 1$: subscription string (e.g. "$15 on March 15th every 3 years for 6 years"), 2$: trial length (e.g.: "with 4 months free trial").
                $subscription_string = sprintf( __( '%1$s with %2$s free trial', 'woocommerce-subscriptions' ), $subscription_string, $trial_string );
            }

            if ( $include['sign_up_fee'] && $this->get_sign_up_fee( $product, $user_wholesale_role ) > 0 ) {
                // translators: 1$: subscription string (e.g. "$15 on March 15th every 3 years for 6 years with 2 months free trial"), 2$: signup fee price (e.g. "and a $30 sign-up fee").
                $subscription_string = sprintf( __( '%1$s and a %2$s sign-up fee', 'woocommerce-subscriptions' ), $subscription_string, $sign_up_fee );
            }

            $subscription_string .= '</span>';

            return $subscription_string;
        }

        /**
         * Returns the meta data for a product.
         *
         * @param mixed  $product A WC_Product object or product ID.
         * @param string $meta_key The meta key to retrieve.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_meta_data( $product, $meta_key ) {
            $prefixed_key = wcs_maybe_prefix_key( $meta_key );
            $meta_value   = '';
            if ( $product->meta_exists( $prefixed_key ) ) {
                $meta_value = $product->get_meta( $prefixed_key, true );
            }

            return $meta_value;
        }

        /**
         * Returns the active price per period for a product if it is a subscription.
         *
         * @param mixed $product A WC_Product object.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_price( $product ) {
            $subscription_price = $this->get_meta_data( $product, 'subscription_price' );
            $sale_price         = $this->get_sale_price( $product );
            $active_price       = ( $subscription_price ) ? $subscription_price : $this->get_regular_price( $product );

            // Ensure that $sale_price is non-empty because other plugins can use woocommerce_product_is_on_sale filter to
            // forcefully set a product's is_on_sale flag (like Dynamic Pricing ).
            if ( $product->is_on_sale() && '' !== $sale_price && $subscription_price > $sale_price ) {
                $active_price = $sale_price;
            }

            return apply_filters( 'woocommerce_subscriptions_product_price', $active_price, $product );
        }

        /**
         * Returns the sale price per period for a product if it is a subscription.
         *
         * @param mixed  $product A WC_Product object or product ID.
         * @param string $context Optional. What the value is for. Valid values are view and edit.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_regular_price( $product, $context = 'view' ) {

            if ( wcs_is_woocommerce_pre( '3.0' ) ) {
                $regular_price = $product->regular_price;
            } else {
                $regular_price = $product->get_regular_price( $context );
            }

            return apply_filters( 'woocommerce_subscriptions_product_regular_price', $regular_price, $product );
        }

        /**
         * Returns the regular price per period for a product if it is a subscription.
         *
         * @param mixed  $product A WC_Product object or product ID.
         * @param string $context Optional. What the value is for. Valid values are view and edit.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_sale_price( $product, $context = 'view' ) {

            if ( wcs_is_woocommerce_pre( '3.0' ) ) {
                $sale_price = $product->sale_price;
            } else {
                $sale_price = $product->get_sale_price( $context );
            }

            return apply_filters( 'woocommerce_subscriptions_product_sale_price', $sale_price, $product );
        }

        /**
         * Returns the subscription period for a product, if it's a subscription.
         *
         * @param mixed $product A WC_Product object.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_period( $product ) {
            $period = $this->get_meta_data( $product, 'subscription_period' );

            return apply_filters( 'woocommerce_subscriptions_product_period', $period, $product );
        }

        /**
         * Returns the subscription interval for a product, if it's a subscription.
         *
         * @param mixed $product A WC_Product object or product ID.
         *
         * @since 2.0.2
         * @return int An integer representing the subscription interval, or 1 if the product is not a subscription or there is no interval
         */
        public function get_interval( $product ) {
            $interval = $this->get_meta_data( $product, 'subscription_period_interval' );

            return apply_filters( 'woocommerce_subscriptions_product_period_interval', $interval, $product );
        }

        /**
         * Returns the length of a subscription product, if it is a subscription.
         *
         * @param mixed $product A WC_Product object.
         *
         * @since 2.0.2
         * @return int
         */
        public function get_length( $product ) {
            $length = $this->get_meta_data( $product, 'subscription_length' );

            return apply_filters( 'woocommerce_subscriptions_product_length', $length, $product );
        }

        /**
         * Returns the trial length of a subscription product, if it is a subscription.
         *
         * @param mixed $product A WC_Product object.
         *
         * @since 2.0.2
         * @return int
         */
        public function get_trial_length( $product ) {
            $trial_length = $this->get_meta_data( $product, 'subscription_trial_length' );

            return apply_filters( 'woocommerce_subscriptions_product_trial_length', $trial_length, $product );
        }

        /**
         * Returns the trial period of a subscription product, if it is a subscription.
         *
         * @param mixed $product A WC_Product object.
         *
         * @since 2.0.2
         * @return string
         */
        public function get_trial_period( $product ) {
            $trial_period = $this->get_meta_data( $product, 'subscription_trial_period' );

            return apply_filters( 'woocommerce_subscriptions_product_trial_period', $trial_period, $product );
        }

        /**
         * Returns the sign-up fee for a subscription, if it is a subscription.
         *
         * @param mixed  $product A WC_Product object.
         * @param string $user_wholesale_role User wholesale role.
         *
         * @since 2.0.2
         * @return int|string
         */
        public function get_sign_up_fee( $product, $user_wholesale_role = '' ) {

            if ( empty( $user_wholesale_role ) ) {
                $user_wholesale_role = $this->_get_current_user_wholesale_role();
            }

            $sign_up_fee         = $this->get_meta_data( $product, 'subscription_sign_up_fee' );
            $product_sign_up_fee = apply_filters( 'woocommerce_subscriptions_product_sign_up_fee', $sign_up_fee, $product );

            $wholesale_signup_price = $product->get_meta( $user_wholesale_role . '_wholesale_signup_fee', true );
            if ( ! empty( $wholesale_signup_price ) ) {
                $product_sign_up_fee = $wholesale_signup_price;
            }

            return ! empty( $product_sign_up_fee ) ? $product_sign_up_fee : 0;
        }

        /**
         * Returns the subscription price string for a variable subscription product.
         *
         * @param string     $price Price string.
         * @param WC_Product $product Product object.
         *
         * @since 2.0.2
         * @return string
         */
        public function wholesale_variable_subscription_price_html( $price, $product ) {

            $user_wholesale_role = $this->_get_current_user_wholesale_role();
            if ( ! empty( $user_wholesale_role ) && $product->is_type( 'variable-subscription' ) ) {
                $variations   = $product->get_available_variations();
                $lowest_price = null;
                $lowest_fee   = null;

                if ( ! empty( $variations ) ) {
                    foreach ( $variations as $variation ) {
                        $variation_obj       = wc_get_product( $variation['variation_id'] );
                        $raw_wholesale_price = $this->get_wholesale_subscription_price( $variation_obj, $user_wholesale_role );

                        if ( strcasecmp( $raw_wholesale_price, '' ) !== 0 ) {
                            $variation_price = $raw_wholesale_price;
                        } else {
                            $variation_price = $variation_obj->get_price();
                        }

                        // Check if it's the first price or if it's lower than the current lowest price.
                        if ( is_null( $lowest_price ) || $variation_price < $lowest_price ) {
                            $lowest_price = $variation_price;
                            $lowest_fee   = $this->get_sign_up_fee( $variation_obj );
                        }
                    }
                }

                $price_string  = '<del class="original-computed-price">' . $price . '</del>';
                $price_string .= $this->get_subscription_price_string( $product, $lowest_price, $lowest_fee );
                return $price_string;
            }

            return $price;
        }

        /**
         * Get wholesale subscription price.
         *
         * @param WC_Product $product Product object.
         * @param string     $user_wholesale_role User wholesale role.
         *
         * @since 2.0.2
         * @return float
         */
        public function get_wholesale_subscription_price( $product, $user_wholesale_role ) {
            $raw_wholesale_price  = WWP_Wholesale_Prices::get_product_raw_wholesale_price( $product->get_ID(), array( $user_wholesale_role ) );
            $wholesale_sale_price = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product->get_ID(), array( $user_wholesale_role ) );

            if ( ! empty( $wholesale_sale_price['wholesale_sale_price'] ) ) {
                $raw_wholesale_price = $wholesale_sale_price['wholesale_sale_price'];
            }

            return ! empty( $raw_wholesale_price ) ? $raw_wholesale_price : 0;
        }

        /**
         * Check if cart contains subscription.
         *
         * @since 2.0.2
         * @return bool
         */
        public function wholesale_cart_contains_subscription() {
            if ( ! empty( WC()->cart->cart_contents ) && ! wcs_cart_contains_renewal() ) {
                foreach ( WC()->cart->cart_contents as $cart_item ) {
                    if ( WC_Subscriptions_Product::is_subscription( $cart_item['data'] ) ) {
                        return true;
                    }
                }
            }
            return false;
        }

        /**
         * Apply wholesale cart items price.
         *
         * @since 2.0.2
         */
        public function apply_wholesale_cart_items_price() {
            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if ( empty( $user_wholesale_role ) ) {
                return;
            }

            if ( ! $this->wholesale_cart_contains_subscription() ) {
                return;
            }

            // Set which price should be used for calculation.
            add_filter( 'woocommerce_product_get_price', array( $this, 'set_subscription_prices_for_calculation' ), 100, 2 );
            add_filter( 'woocommerce_product_variation_get_price', array( $this, 'set_subscription_prices_for_calculation' ), 100, 2 );
            add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'subscriptions_product_sign_up_fee' ), 10, 2 );
            add_filter( 'wwpp_filter_wholesale_subscription_price_string', array( $this, 'filter_wholesale_subscription_price_string' ), 10, 4 );
        }

        /**
         * Filter subscription product price in cart paage.
         *
         * @param string     $original_price_string Original price string.
         * @param float      $wholesale_price Wholesale price.
         * @param WC_Product $product Product object.
         * @param string     $user_wholesale_role User wholesale role.
         *
         * @since 2.0.2
         * @return string
         */
        public function filter_wholesale_subscription_price_string( $original_price_string, $wholesale_price, $product, $user_wholesale_role ) { // phpcs:ignore
            return '';
        }

        /**
         * Removes the "set_subscription_prices_for_calculation" filter from the WC Product's woocommerce_get_price hook once
         * calculations are complete.
         *
         * @since 2.0.2
         */
        public function remove_calculation_price_filter() {
            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if ( ! empty( WC()->cart->cart_contents ) && ! empty( $user_wholesale_role ) ) {

                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                    $product = $cart_item['data'];

                    if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
                        $wholesale_price = $this->get_wholesale_subscription_price( $product, $user_wholesale_role );

                        $cart_item['data']->set_price( $wholesale_price );

                        // Get sign up fee.
                        $sign_up_fee = $this->get_sign_up_fee( $product, $user_wholesale_role );

                        $line_item_price = ( $wholesale_price + $sign_up_fee ) * $cart_item['quantity'];

                        WC()->cart->cart_contents[ $cart_item_key ]['line_subtotal'] = $line_item_price;
                        WC()->cart->cart_contents[ $cart_item_key ]['line_total']    = $line_item_price;
                    }
                }
            }

            remove_filter( 'woocommerce_product_get_price', array( $this, 'set_subscription_prices_for_calculation' ), 100 );
            remove_filter( 'woocommerce_product_variation_get_price', array( $this, 'set_subscription_prices_for_calculation' ), 100 );
        }

        /**
         * Set subscription prices for calculation.
         *
         * @param float      $price Price.
         * @param WC_Product $product Product object.
         *
         * @since 2.0.2
         * @return float
         */
        public function set_subscription_prices_for_calculation( $price, $product ) {
            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if ( empty( $user_wholesale_role ) ) {
                return $price;
            }

            if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
                $sign_up_fee  = $this->get_sign_up_fee( $product );
                $trial_length = (int) $this->get_trial_length( $product );

                // Calculate if the product has a sign-up fee.
                if ( empty( $sign_up_fee ) ) {
                    $sign_up_fee = 0;
                }

                if ( $trial_length > 0 ) {
                    $price = $sign_up_fee;
                } else {
                    $wholesale_price = $this->get_wholesale_subscription_price( $product, $user_wholesale_role );
                    $price           = (float) $wholesale_price + $sign_up_fee; // Casting to float for php8 compatibility.
                }
            }

            return $price;
        }

        /**
         * Filter wholesale price html for third party.
         *
         * @param string     $wholesale_price Wholesale price.
         * @param string     $price Product price.
         * @param WC_Product $product Product object.
         * @param string     $user_wholesale_role User wholesale role.
         *
         * @since 2.0.2
         */
        public function filter_wholesale_price_html_for_third_party( $wholesale_price, $price, $product, $user_wholesale_role ) {

            // Check if not in admin.
            if ( ! is_admin() ) {
                return $wholesale_price;
            }

            if ( WC_Subscriptions_Product::is_subscription( $product ) && 'yes' === $product->get_meta( $user_wholesale_role[0] . '_have_wholesale_price', true ) ) {

                // Check if subscription is simple.
                if ( $product->is_type( 'subscription' ) ) {
                    $wholesale_price = $this->get_subscription_price_string( $product, '', '', $user_wholesale_role[0], false );
                } elseif ( $product->is_type( 'variable-subscription' ) ) {
                    $variations   = $product->get_available_variations();
                    $lowest_price = null;
                    $lowest_fee   = null;

                    if ( ! empty( $variations ) ) {
                        foreach ( $variations as $variation ) {
                            $variation_obj       = wc_get_product( $variation['variation_id'] );
                            $raw_wholesale_price = $this->get_wholesale_subscription_price( $variation_obj, $user_wholesale_role[0] );

                            if ( strcasecmp( $raw_wholesale_price, '' ) !== 0 ) {
                                $variation_price = $raw_wholesale_price;
                            } else {
                                $variation_price = $variation_obj->get_price();
                            }

                            // Check if it's the first price or if it's lower than the current lowest price.
                            if ( is_null( $lowest_price ) || $variation_price < $lowest_price ) {
                                $lowest_price = $variation_price;
                                $lowest_fee   = $this->get_sign_up_fee( $variation_obj, $user_wholesale_role[0] );
                            }
                        }
                    }

                    $wholesale_price = $this->get_subscription_price_string( $product, $lowest_price, $lowest_fee, $user_wholesale_role[0], false );
                }
            }

            return $wholesale_price;
        }

        /**
         * Filter subscription product sign up fee.
         *
         * @param float      $fee Sign up fee.
         * @param WC_Product $product Product object.
         *
         * @since 2.0.2
         * @return float
         */
        public function subscriptions_product_sign_up_fee( $fee, $product ) {
            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
                    $fee = $this->get_meta_data( $product, 'subscription_sign_up_fee' );

                    $wholesale_signup_price = $product->get_meta( $user_wholesale_role . '_wholesale_signup_fee', true );
                    if ( ! empty( $wholesale_signup_price ) ) {
                        $fee = $wholesale_signup_price;
                    }
                }
            }

            return $fee;
        }

        /**
         * Filter cart item subtotal.
         *
         * @param string $subtotal Subtotal.
         * @param array  $cart_item Cart item.
         * @param string $cart_item_key Cart item key.
         *
         * @since 2.0.2
         * @return string
         */
        public function cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) { // phpcs:ignore.
            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $product = $cart_item['data'];

                if ( WC_Subscriptions_Product::is_subscription( $product ) ) {
                    $wholesale_price = $this->get_wholesale_subscription_price( $product, $user_wholesale_role );
                    $sign_up_fee     = $this->get_sign_up_fee( $product, $user_wholesale_role );
                    $line_item_price = ( $wholesale_price + $sign_up_fee ) * $cart_item['quantity'];

                    $subtotal = wc_price( $line_item_price );
                }
            }

            return $subtotal;
        }

        /**
         * Execute model.
         *
         * @since 2.0.2
         * @access public
         */
        public function run() {

            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {

                add_filter( 'woocommerce_subscriptions_product_price_string', array( $this, 'wholesale_subscriptions_product_price_string' ), 10, 3 );
                add_filter( 'woocommerce_variable_subscription_price_html', array( $this, 'wholesale_variable_subscription_price_html' ), 10, 2 );

                // Filter cart item subscription price.
                add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_wholesale_cart_items_price' ), 10 );
                add_action( 'woocommerce_calculate_totals', array( $this, 'remove_calculation_price_filter' ), 10 );
                add_action( 'woocommerce_after_calculate_totals', array( $this, 'remove_calculation_price_filter' ), 10 );

                add_filter( 'wwp_before_wholesale_price_html_filter', array( $this, 'filter_wholesale_price_html_for_third_party' ), 10, 4 );
                add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'cart_item_subtotal' ), 10, 3 );
            }
        }
    }
}
