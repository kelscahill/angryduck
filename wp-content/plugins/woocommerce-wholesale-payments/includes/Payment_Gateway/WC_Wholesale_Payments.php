<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Payment_Gateway
 */

namespace RymeraWebCo\WPay\Payment_Gateway;

use Automattic\WooCommerce\Internal\DependencyManagement\ContainerException;
use Automattic\WooCommerce\Enums\OrderStatus;
use Automattic\WooCommerce\Internal\Utilities\HtmlSanitizer;
use RymeraWebCo\WPay\Factories\Payment_Plan;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\Payment_Plans;
use RymeraWebCo\WPay\Helpers\Stripe;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use RymeraWebCo\WPay\Helpers\WPAY_Invoices;
use WC_Order;
use WC_Payment_Gateway;
use WP_Post;

/**
 * WC_Wholesale_Payments class.
 *
 * @see   https://woo.com/document/payment-gateway-api/
 *
 * @since 1.0.0
 */
class WC_Wholesale_Payments extends WC_Payment_Gateway {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var WC_Wholesale_Payments $instance object
     */
    protected static $instance;

    /**
     * Flag indicating the order should be placed on hold due to overdue balance.
     *
     * @since 1.0.6
     * @var bool
     */
    private $overdue_on_hold = false;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {

        $this->id         = 'wc_wholesale_payments';
        $this->has_fields = true;

        $title_css = array(
            'background-color' => '#ffde92',
            'border'           => '1px solid #bb5504',
            'border-radius'    => '1rem',
            'color'            => '#bb5504',
            'display'          => 'inline-block',
            'font-size'        => '12px',
            'font-weight'      => 'bold',
            'padding'          => '0.2rem 0.5rem',
        );

        $title_css = implode(
            ';',
            array_map(
                function ( $key ) use ( $title_css ) {

                    return $key . ':' . $title_css[ $key ];
                },
                array_keys( $title_css )
            )
        );

        $this->title = sprintf(
            '%1$s%2$s',
            WPay::get_payment_method_name(),
            WPay::get_api_mode() === 'test'
                ? sprintf( ' <span class="wpay-test-mode-tag" style="%s">', $title_css ) .
                esc_html__( 'TEST MODE', 'woocommerce-wholesale-payments' ) . '</span>'
                : ''
        );

        $this->method_title       = __( 'Wholesale Payments', 'woocommerce-wholesale-payments' );
        $this->method_description = __( 'Allow your customers to make payment installments for their wholesale purchases.', 'woocommerce-wholesale-payments' );

        $this->enabled = $this->get_option( 'enabled' ) === 'yes' ? 'yes' : 'no';
    }

    /**
     * Get the gateway title.
     *
     * @since 1.0.0
     * @throws ContainerException If the container cannot be retrieved.
     * @return string|null
     */
    public function get_title() {

        global $current_screen, $pagenow;

        $tags = HtmlSanitizer::LOW_HTML_BALANCED_TAGS_NO_LINKS;

        /***************************************************************************
         * Customize allowed span style attribute
         ***************************************************************************
         *
         * We whitelist `style` attribute for `span` tag.
         */
        $tags['wp_kses_rules']['span']['style'] = true;

        $title = wc_get_container()->get( HtmlSanitizer::class )->sanitize( (string) $this->title, $tags );
        if ( is_admin() && $current_screen && str_contains( $title, 'TEST MODE' ) &&
            (
                ( 'woocommerce_page_wc-orders' === $current_screen->id && 'admin.php' === $pagenow ) // HPOS order screen.
                || ( 'shop_order' === $current_screen->id && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) // legacy order screen.
            )
        ) {
            $title = $this->method_title . ' **TEST MODE**';
        }

        return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
    }

    /**
     * Check if gateway needs setup.
     *
     * @since 1.0.0
     * @return bool
     */
    public function needs_setup() {

        $account_number = WPay::get_account_number();

        return empty( $account_number );
    }

    /**
     * Get the overdue restriction action for a given user.
     *
     * Returns the restriction action ('block', 'on_hold') if the user has overdue
     * invoices exceeding the configured threshold, or false if no restriction applies.
     *
     * @param int $user_id WordPress user ID.
     *
     * @since 1.0.6
     * @return string|false The restriction action or false if no restriction.
     */
    private function get_overdue_restriction( $user_id ) {
        if ( 'yes' !== get_option( 'wpay_overdue_restriction_enabled', 'no' ) ) {
            return false;
        }

        if ( empty( $user_id ) ) {
            return false;
        }

        $days_threshold = absint( get_option( 'wpay_overdue_days_threshold', 0 ) );

        if ( ! WPAY_Invoices::customer_has_overdue_invoices( $user_id, $days_threshold ) ) {
            return false;
        }

        return get_option( 'wpay_overdue_restriction_action', 'on_hold' );
    }

    /**
     * Check if a credit limit restriction applies to the given user.
     *
     * @param int $user_id WordPress user ID.
     *
     * @since 1.0.6
     * @return string|false The restriction action ('block' or 'on_hold'), or false if none.
     */
    public function get_credit_limit_restriction( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }

        if ( 'yes' !== get_option( 'wpay_credit_limit_enabled', 'no' ) ) {
            return false;
        }

        if ( ! WPAY_Invoices::customer_exceeds_credit_limit( $user_id ) ) {
            return false;
        }

        return get_option( 'wpay_credit_limit_behavior', 'block' );
    }

    /**
     * Check if gateway is available.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available() {

        $is_available = parent::is_available();

        if ( WC()->cart && 'yes' === $this->enabled ) {
            $total = $this->get_order_total();

            $payment_plans = $this->get_applicable_payment_plans( $total );
            $is_available  = ! empty( $payment_plans );
        }

        // Check overdue restriction — block action hides the gateway entirely.
        if ( $is_available && 'block' === $this->get_overdue_restriction( get_current_user_id() ) ) {
            $is_available = false;
        }

        if ( $is_available && 'block' === $this->get_credit_limit_restriction( get_current_user_id() ) ) {
            $is_available = false;
        }

        return $is_available;
    }

    /**
     * Get enabled payment plans.
     *
     * @param array $query_args Query args.
     *
     * @since 1.0.0
     * @return Payment_Plan[]|int[]|WP_Post[]
     */
    protected function get_enabled_payment_plans( $query_args = array() ) {

        $args = wp_parse_args(
            $query_args,
            array(
                'meta_key'       => 'enabled',
                'meta_value'     => 'yes',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
            )
        );

        $payment_plans_query = Payment_Plans::get_payment_plans( $args );

        return $payment_plans_query->posts;
    }

    /**
     * Get payment plans applicable for the order total.
     *
     * @param float $total Total amount of items in cart.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_applicable_payment_plans( $total ) {

        $enabled_plans = $this->get_enabled_payment_plans();
        $float_total   = floatval( $total );

        $plans = array();
        if ( ! empty( $enabled_plans ) ) {
            $plans = array_filter(
                $enabled_plans,
                function ( $plan ) use ( $float_total ) {

                    return $plan->is_applicable( $float_total );
                }
            );
        }

        return $plans;
    }

    /**
     * Load payment fields.
     *
     * @since 1.0.0
     * @return void
     */
    public function payment_fields() {

        $total = $this->get_order_total();

        $payment_plans = $this->get_applicable_payment_plans( $total );

        $payment_plans_style = apply_filters(
            'wpay_payment_plans_style',
            array(
                'margin-top: 10px;',
                'margin-left: 10px;',
                'padding-left: 0;',
            )
        );

        $payment_plan_items_style = apply_filters(
            'wpay_payment_plan_items_style',
            array(
                'padding: 0.2rem 0;',
                'display: flex;',
                'align-items: center;',
                'margin-bottom: 0.5rem;',
            )
        );

        $payment_plan_charge_status_style = apply_filters(
            'wpay_payment_plan_charge_status_style',
            array(
                'background-color: #E6D96A;',
                'border-radius: 1rem;',
                'color: #000;',
                'font-size: 14px;',
                'padding: 0.2rem 0.5rem;',
                'margin-left: 10px;',
            )
        );

        // Check if the customer has overdue invoices for on_hold action (block hides gateway via is_available).
        $has_overdue_balance = false !== $this->get_overdue_restriction( get_current_user_id() );

        $credit_limit_notice = ( 'on_hold' === $this->get_credit_limit_restriction( get_current_user_id() ) )
            ? WPAY_Invoices::get_credit_limit_notice_message()
            : '';

        require_once Helper::locate_front_template_part( 'payment-fields' );
    }

    /**
     * Get the selected plan from the session.
     *
     * @since 1.0.0
     * @return int
     */
    public function wpay_get_selected_plan() {

        if ( ! WC()->session ) {
            WC()->initialize_session();
        }

        return WC()->session->get( 'wpay_selected_plan', 0 );
    }

    /**
     * Set the selected plan in the session.
     *
     * @since 1.0.0
     * @return void
     */
    private function wpay_set_to_empty_plan() {

        if ( ! WC()->session ) {
            WC()->initialize_session();
        }
        WC()->session->set( 'wpay_selected_plan', 0 );
    }

    /**
     * Process payment.
     *
     * @param int $order_id Order ID.
     *
     * @since 1.0.0
     * @since 1.0.6 Added functionality: Check if customer has WooCommerce payment tokens for this gateway.
     *            Synchronize Stripe payment methods with WooCommerce tokens.
     *            Check if customer has default payment method and WooCommerce tokens.
     *            Create a WooCommerce payment token from Stripe payment method data.
     * @return array|void
     */
    public function process_payment( $order_id ) {

        // Reset per-invocation state to prevent stale values from prior calls in the same request
        // (e.g. Action Scheduler bulk processing, subscription renewals, admin-created orders).
        $this->overdue_on_hold = false;

        // Get nonce from request.
        $checkout_nonce = filter_input( INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $pay_nonce      = filter_input( INPUT_POST, 'woocommerce-pay-nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $nonce          = $checkout_nonce ?? $pay_nonce;
        $is_checkout    = (bool) $checkout_nonce;
        $order          = new WC_Order( $order_id );
        $redirect       = $checkout_nonce ? wc_get_checkout_url() : $order->get_checkout_payment_url();
        $redirect       = apply_filters( 'wpay_process_payment_redirect', $redirect, $order_id );
        $redirect_fail  = array(
            'result'   => 'fail',
            'redirect' => $redirect,
        );
        $redirect_fail  = apply_filters( 'wpay_process_payment_redirect_fail', $redirect_fail, $order_id );

        if ( $nonce ) {
            /***************************************************************************
             * Check if nonce is valid
             ***************************************************************************
             *
             * The nonce being checked here is from \WC_Checkout::process_checkout.
             *
             * @see \WC_Checkout::process_checkout()
             */
            if ( ! wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) && ! wp_verify_nonce( $nonce, 'woocommerce-pay' ) ) {
                wc_add_notice( __( 'Security error: Request seems to be invalid. Please reload the checkout page and try again.', 'woocommerce-wholesale-payments' ), 'error' );

                if ( $is_checkout ) {
                    return $redirect_fail;
                } else {
                    wp_safe_redirect( $redirect );
                    exit;
                }
            }

            /***************************************************************************
             * Get payment plan post ID
             ***************************************************************************
             *
             * We require the payment plan post ID to be passed in the request.
             */
            $wpay_plan_id = filter_input( INPUT_POST, 'wpay_plan', FILTER_VALIDATE_INT );
        } else {
            // Check if plan was selected in block.
            $wpay_plan_id = $this->wpay_get_selected_plan();
        }

        // Check if payment plan exists and is enabled.
        if ( ! $wpay_plan_id ) {
            wc_add_notice( __( 'Payment error: Please select a payment plan.', 'woocommerce-wholesale-payments' ), 'error' );

            if ( $is_checkout ) {
                return $redirect_fail;
            } else {
                wp_safe_redirect( $redirect );
                exit;
            }
        }

        /***************************************************************************
         * Check if payment plan exists and is enabled
         ***************************************************************************
         *
         * We check if the payment plan exists and is enabled.
         */
        $payment_plan = Payment_Plan::get_instance( $wpay_plan_id );
        if ( ! $payment_plan || ! $payment_plan->is_enabled() ) {
            wc_add_notice( __( 'Payment error: Payment method is not configured properly. Please contact the store manager.', 'woocommerce-wholesale-payments' ), 'error' );

            if ( $is_checkout ) {
                return $redirect_fail;
            } else {
                wp_safe_redirect( $redirect );
                exit;
            }
        }

        $order_total          = (float) $order->get_total( 'edit' );
        $order_total_in_cents = absint( $order_total * 100 );

        $customer_id_meta_key = '_wpay_stripe_customer_id';
        $order_description    = '';

        /***************************************************************************
         * Check overdue restriction
         ***************************************************************************
         *
         * Uses the order's customer ID (not get_current_user_id()) to correctly
         * handle admin-created orders on behalf of customers.
         *
         * @since 1.0.6
         */
        $restriction_action = $this->get_overdue_restriction( $order->get_customer_id() );
        if ( 'block' === $restriction_action ) {
            // Context: process_payment() block action — prevents order submission entirely.
            // Intentionally different wording from on_hold notices (payment-fields.php, wpay-payment-method.js).
            wc_add_notice(
                __( 'You have an outstanding overdue balance. Please pay your overdue invoices before placing a new order with a payment plan.', 'woocommerce-wholesale-payments' ),
                'error'
            );

            if ( $is_checkout ) {
                return $redirect_fail;
            } else {
                wp_safe_redirect( $redirect );
                exit;
            }
        } elseif ( 'on_hold' === $restriction_action ) {
            $this->overdue_on_hold = true;
        }

        // Check if auto-charge is enabled.
        $auto_charge = WPAY_Invoices::check_auto_charge( $payment_plan );

        /***************************************************************************
         * Save payment plan to order meta
         ***************************************************************************
         *
         * We record the payment plan selected for this order at the time of
         * checkout.
         */
        $order->update_meta_data( '_wpay_auto_charge', $auto_charge ? 'yes' : 'no' );
        $order->update_meta_data( '_wpay_plan', $payment_plan->to_json() );
        $order->save_meta_data();

        // Determine if payment plan schedule should be deferred to order completion.
        $schedule_start = get_option( 'wpay_payment_plan_start_date', 'order_date' );
        $order->update_meta_data( '_wpay_schedule_start_date', $schedule_start );

        $is_deferred                 = 'completed_date' === $schedule_start;
        $is_deferred_auto_charge     = $is_deferred && $auto_charge;
        $is_deferred_non_auto_charge = $is_deferred && ! $auto_charge;

        /***************************************************************************
         * Check if customer id exists for this order
         ***************************************************************************
         *
         * We check if the customer id already exists for this order.
         */
        // Get user ID from order for token operations (use get_customer_id() consistently).
        $user_id = $order->get_customer_id();

        $customer_id = $order->get_meta( $customer_id_meta_key );
        if ( ! $customer_id ) {
            /***************************************************************************
             * Check if customer ID exists in user meta (from integrated payment flow)
             ***************************************************************************
             *
             * First check if the user already has a customer ID stored in user meta
             * (this would be set by the integrated payment method capture flow).
             */
            if ( $user_id ) {
                $customer_id = get_user_meta( $user_id, '_wpay_stripe_customer_id', true );
            }

            if ( ! $customer_id ) {
                /***************************************************************************
                 * Check if customer already exists in Stripe
                 ***************************************************************************
                 *
                 * We check if the customer already exists in Stripe. If not, we create a
                 * new customer.
                 */
                $search_customer = Stripe::instance()->search_customer( array( 'email' => $order->get_billing_email() ) );

                // Check if search returned an error.
                if ( is_wp_error( $search_customer ) ) {
                    wc_add_notice(
                        sprintf(
                            /* translators: %s = Stripe API error message */
                            __( 'Payment error: Unable to verify customer in Stripe. %s', 'woocommerce-wholesale-payments' ),
                            $search_customer->get_error_message()
                        ),
                        'error'
                    );

                    if ( $is_checkout ) {
                        return $redirect_fail;
                    } else {
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }

                $customer = array();
                if ( is_array( $search_customer ) && empty( $search_customer['data'] ) ) {
                    $customer = Stripe::instance()->create_customer(
                        $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        $order->get_billing_email()
                    );

                    // Check if creation returned an error.
                    if ( is_wp_error( $customer ) ) {
                        wc_add_notice(
                            sprintf(
                                /* translators: %s = Stripe API error message */
                                __( 'Payment error: Unable to create customer in Stripe. %s', 'woocommerce-wholesale-payments' ),
                                $customer->get_error_message()
                            ),
                            'error'
                        );

                        if ( $is_checkout ) {
                            return $redirect_fail;
                        } else {
                            wp_safe_redirect( $redirect );
                            exit;
                        }
                    }
                } elseif ( is_array( $search_customer ) && ! empty( $search_customer['data'] ) ) {
                    $customer = $search_customer['data'][0];
                }

                $customer_id = isset( $customer['id'] ) ? $customer['id'] : null;

                // Validate customer_id was successfully obtained.
                if ( empty( $customer_id ) ) {
                    wc_add_notice(
                        __( 'Payment error: Unable to obtain customer ID from Stripe. Please contact the store manager.', 'woocommerce-wholesale-payments' ),
                        'error'
                    );

                    if ( $is_checkout ) {
                        return $redirect_fail;
                    } else {
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }
            }

            /***************************************************************************
             * Record Stripe customer ID to order meta
             ***************************************************************************
             *
             * We record the Stripe customer ID to the order meta.
             */
            if ( $customer_id ) {
                $order->update_meta_data( $customer_id_meta_key, $customer_id );
                $order->save_meta_data();

                // Also ensure it's stored in user meta for future orders.
                if ( $user_id && ! get_user_meta( $user_id, '_wpay_stripe_customer_id', true ) ) {
                    update_user_meta( $user_id, '_wpay_stripe_customer_id', $customer_id );
                }
            }
        }

        $invoice_id_meta_key = '_wpay_stripe_invoice_id';

        /***************************************************************************
         * Check Stripe invoice
         ***************************************************************************
         *
         * We check if there's already a Stripe invoice associated for this order.
         */
        $invoice_id = $order->get_meta( $invoice_id_meta_key );

        // If invoice already exists and auto-charge is enabled, retrieve breakdown IDs from order meta.
        if ( $invoice_id && $auto_charge ) {
            $breakdown_ids = $order->get_meta( '_wpay_invoice_breakdown' );
            if ( ! is_array( $breakdown_ids ) ) {
                $breakdown_ids = array();
            }
        }

        /***************************************************************************
         * Require default payment method for auto-charge plans
         * **************************************************************************
         *
         * If auto-charge is enabled for the selected plan, ensure the Stripe
         * customer has a default payment method set.
         *
         * Check for wpay_pm_attached flag: If JavaScript just attached a payment
         * method during checkout, trust it and skip the Stripe API check to avoid
         * timing/synchronization issues.
         */
        if ( $auto_charge && ! empty( $customer_id ) ) {
            // Check if payment method was just attached by frontend JavaScript.
            // For classic checkout: check POST parameter.
            // For WooCommerce Blocks: check session flag, POST array directly, and payment_data.
            $pm_attached_post     = filter_input( INPUT_POST, 'wpay_pm_attached', FILTER_VALIDATE_BOOLEAN );
            $pm_attached_post_raw = filter_input( INPUT_POST, 'wpay_pm_attached', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
            // filter_input returns null when input doesn't exist, false when it exists but is falsy.
            if ( null === $pm_attached_post_raw ) {
                $pm_attached_post_raw = false;
            }
            $pm_attached_session = \RymeraWebCo\WPay\Integrations\WPay_Block::wpay_get_pm_attached_flag();

            // Check payment_data from Blocks checkout REST API.
            // Blocks checkout converts paymentData object into payment_data array format.
            $pm_attached_payment_data = false;
            if ( ! $pm_attached_post && ! $pm_attached_post_raw && ! $pm_attached_session ) {
                // Blocks checkout sends payment_data as array in REST request.
                // Use filter_input with FILTER_REQUIRE_ARRAY to safely access nested array data.
                $payment_data_raw = filter_input( INPUT_POST, 'payment_data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
                if ( is_array( $payment_data_raw ) ) {
                    foreach ( $payment_data_raw as $data ) {
                        if (
                            isset( $data['key'], $data['value'] ) &&
                            'wpay_pm_attached' === $data['key']
                        ) {
                            $pm_attached_payment_data = filter_var( $data['value'], FILTER_VALIDATE_BOOLEAN );
                            break;
                        }
                    }
                }
            }

            $pm_attached = $pm_attached_post || $pm_attached_post_raw || $pm_attached_session || $pm_attached_payment_data;

            // Skip validation if frontend confirmed payment method was just attached.
            if ( ! $pm_attached ) {
                $has_default_pm = Stripe::instance()->customer_has_default_payment_method( $customer_id, $user_id );

                if ( is_wp_error( $has_default_pm ) ) {
                    wc_add_notice( $has_default_pm->get_error_message(), 'error' );

                    if ( $is_checkout ) {
                        return $redirect_fail;
                    } else {
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }

                if ( empty( $has_default_pm ) ) {
                    $payment_methods_url = wc_get_account_endpoint_url( 'payment-methods' );
                    $message             = sprintf(
                        /* translators: %s = My Account payment methods URL */
                        __( 'Auto-charge requires a saved payment method. Please <a href="%s">add a payment method</a> first, then try again.', 'woocommerce-wholesale-payments' ),
                        esc_url( $payment_methods_url )
                    );
                    wc_add_notice( $message, 'error' );

                    if ( $is_checkout ) {
                        return $redirect_fail;
                    } else {
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }
            } else {
                // Clear the session flag after using it.
                \RymeraWebCo\WPay\Integrations\WPay_Block::wpay_clear_pm_attached_flag();
            }
        }
        if ( ! $invoice_id ) {
            $amounts_due      = array();
            $fixed_items      = array();
            $percentage_items = false;

            foreach ( $payment_plan->breakdown as $item ) {
                if ( 'fixed' === $item['due']['type'] ) {
                    $fixed_items[] = $item;
                } elseif ( ! $percentage_items ) {
                    $percentage_items = true;
                }
            }

            $percentage_base_total = 0;
            if ( ! empty( $fixed_items ) && $percentage_items ) {
                $percentage_base_total = $order_total_in_cents;
                foreach ( $fixed_items as $fixed_item ) {
                    $value = absint( $fixed_item['due']['value'] * 100 );

                    $percentage_base_total -= $value;
                }
            }

            $breakdown_ids = array();
            $sum_amount    = 0;
            foreach ( $payment_plan->breakdown as $i => $item ) {
                /***************************************************************************
                 * Convert due amount to cents
                 ***************************************************************************
                 *
                 * We convert the due amount to cents.
                 */
                if ( 'percentage' === $item['due']['type'] ) {
                    if ( count( $payment_plan->breakdown ) - 1 === $i ) {
                        $amount = $order_total_in_cents - $sum_amount;
                    } elseif ( $percentage_base_total ) {
                        $amount = absint( $percentage_base_total * $item['due']['value'] / 100 );
                    } else {
                        $amount = absint( $order_total_in_cents * $item['due']['value'] / 100 );
                    }
                } else {
                    $amount = absint( $item['due']['value'] * 100 );
                }

                switch ( $item['day_format'] ) {
                    case 'custom':
                    case 'timestamp':
                        $filter_name = 'wpay_parse_' . str_replace( '-', '_', sanitize_key( $item['day'] ) );
                        $days        = WPay::parse_day( $item['day'] );
                        if ( null === $days ) {
                            if ( current_user_can( 'manage_woocommerce' ) ) {
                                wc_add_notice(
                                    sprintf(/* translators: %s = filter name for 'custom' day format */
                                        __( 'Payment error: "%s" filter needs to return non-null value.', 'woocommerce-wholesale-payments' ),
                                        $filter_name
                                    ),
                                    'error'
                                );
                            } else {
                                wc_add_notice( __( 'Payment error: Payment method is not configured properly. Please contact the store manager.', 'woocommerce-wholesale-payments' ), 'error' );
                            }

                            if ( $is_checkout ) {
                                return $redirect_fail;
                            } else {
                                wp_safe_redirect( $redirect );
                                exit;
                            }
                        }
                        break;
                    default:
                        $days = absint( $item['day'] );
                }

                $sum_amount += $amount;

                $amount_args = array(
                    'amount'      => $amount,
                    'description' => sprintf(/* translators: %1$d = order id; %2$d = payment number */
                        __( 'Order #%1$d - Payment #%2$d', 'woocommerce-wholesale-payments' ),
                        $order_id,
                        $i + 1
                    ),
                );

                switch ( $item['day_format'] ) {
                    case 'timestamp':
                        $amount_args['due_date'] = $days;
                        break;
                    case 'custom':
                    default:
                        $amount_args['days_until_due'] = $days;
                }

                $amounts_due[ $i ] = $amount_args;

                if ( $auto_charge && ! $is_deferred_auto_charge ) {
                    // Save breakdown to wpay table per line item.
                    $breakdown_ids[] = WPAY_Invoices::add_invoice_breakdown( $customer_id, $i, $order_id, $amount_args );
                }
            }

            if ( $auto_charge && ! $is_deferred_auto_charge ) {
                $order->update_meta_data( '_wpay_invoice_breakdown', $breakdown_ids );
            }

            if ( $order_total_in_cents !== $sum_amount ) {
                wc_add_notice( __( 'Payment error: Sum amount does not match total amount due. Please contact the store manager.', 'woocommerce-wholesale-payments' ), 'error' );

                if ( $is_checkout ) {
                    return $redirect_fail;
                } else {
                    wp_safe_redirect( $redirect );
                    exit;
                }
            }

            /***************************************************************************
             * Validate Stripe customer ID before creating invoice
             ***************************************************************************
             *
             * As a fail-safe, ensure customer_id exists before attempting invoice creation.
             */
            if ( empty( $customer_id ) ) {
                wc_add_notice(
                    __( 'Payment error: Customer ID not found. Please contact the store manager.', 'woocommerce-wholesale-payments' ),
                    'error'
                );

                if ( $is_checkout ) {
                    return $redirect_fail;
                } else {
                    wp_safe_redirect( $redirect );
                    exit;
                }
            }

            if ( $is_deferred_auto_charge ) {
                /***************************************************************************
                 * Defer auto-charge invoice creation until order completion
                 ***************************************************************************
                 *
                 * When "Completed Date" is selected, we skip invoice row insertion and
                 * Stripe invoice creation at checkout. The breakdown data is stored in
                 * order meta and replayed when the order is marked as completed.
                 *
                 * @since 1.0.6
                 */
                $order->update_meta_data( '_wpay_schedule_deferred', 'yes' );
                $order->update_meta_data( '_wpay_deferred_breakdown', $amounts_due );
                $order->save_meta_data();

                $order->add_order_note(
                    __( 'Payment plan schedule deferred — installments will begin when this order is marked as completed.', 'woocommerce-wholesale-payments' )
                );
            } else {
                /***************************************************************************
                 * Create Stripe invoice
                 ***************************************************************************
                 *
                 * We create the Stripe invoice with the payload for this order.
                 */
                $invoice_response = Stripe::instance()->create_invoice( $customer_id, $amounts_due, $auto_charge, $is_deferred_non_auto_charge );
                if ( is_wp_error( $invoice_response ) ) {
                    wc_add_notice( $invoice_response->get_error_message(), 'error' );

                    if ( $is_checkout ) {
                        return $redirect_fail;
                    } else {
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }

                $invoice_id = $invoice_response['id'];

                /***************************************************************************
                 * Record Stripe invoice ID to order meta
                 ***************************************************************************
                 *
                 * We record the Stripe invoice ID to the order meta.
                 */
                if ( $auto_charge ) {
                    $invoice_ids = array( $invoice_id );
                    $order->update_meta_data( $invoice_id_meta_key, $invoice_ids );
                } else {
                    $order->update_meta_data( $invoice_id_meta_key, $invoice_id );
                }

                if ( $is_deferred_non_auto_charge ) {
                    $order->update_meta_data( '_wpay_schedule_deferred', 'yes' );
                }

                $order->save_meta_data();
            }
        }

        /***************************************************************************
         * Get finalized invoice data from response
         ***************************************************************************
         *
         * The create_invoice() method already finalizes non-auto-charge invoices
         * and adds all invoice items internally. For auto-charge invoices, they
         * remain in draft state and will be auto-finalized by Stripe when charged.
         *
         * If invoice already existed, retrieve it from Stripe instead of using
         * the response from create_invoice().
         */
        if ( ! $is_deferred_auto_charge ) {
            if ( isset( $invoice_response ) ) {
                // Invoice was just created, use the response.
                $finalized = $invoice_response;
            } else {
                // Invoice already existed, retrieve it from Stripe.
                // Handle both string (non-auto-charge) and array (auto-charge) invoice IDs.
                $invoice_id_to_fetch = is_array( $invoice_id ) ? $invoice_id[0] : $invoice_id;
                $finalized           = Stripe::instance()->get_invoice( $invoice_id_to_fetch );
                if ( is_wp_error( $finalized ) ) {
                    wc_add_notice( $finalized->get_error_message(), 'error' );

                    if ( $is_checkout ) {
                        return $redirect_fail;
                    } else {
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }
            }
        }

        if ( ! $is_deferred_auto_charge ) {

            /***************************************************************************
             * Record finalized invoice to order meta
             ***************************************************************************
             *
             * We record the finalized invoice to the order meta.
             */
            $status = '';
            if ( $auto_charge ) {
                $status          = ! empty( $finalized['status'] ) ? $finalized['status'] : '';
                $finalized_items = array( $finalized );

                $order->update_meta_data( '_wpay_stripe_invoice', $finalized_items );

                // Extract invoice item ID from the finalized invoice lines.
                // For draft invoices (auto-charge), lines['data'] won't be populated until finalization.
                $invoice_item_id = '';
                if ( isset( $finalized['status'] ) && 'draft' !== $finalized['status'] ) {
                    // Only extract item ID if invoice is finalized (has lines data).
                    if ( isset( $finalized['lines']['data'][0]['id'] ) ) {
                        $invoice_item_id = $finalized['lines']['data'][0]['id'];
                    }
                }

                // Normalize invoice_id to always be a string for consistency.
                // For auto-charge orders, invoice_id may be stored as an array in order meta.
                $normalized_invoice_id = is_array( $invoice_id ) ? $invoice_id[0] : $invoice_id;

                $invoice_data = array(
                    'invoice_id'            => $normalized_invoice_id,
                    'invoice_item_id'       => $invoice_item_id,
                    'stripe_invoice'        => wp_json_encode( $finalized ),
                    'stripe_invoice_status' => $status,
                    'status'                => 'created',
                );

                // Ensure breakdown_ids is available and not empty before updating invoice data.
                if ( ! empty( $breakdown_ids ) && is_array( $breakdown_ids ) ) {
                    WPAY_Invoices::update_invoice_data( 'id', $breakdown_ids[0], $invoice_data );

                    // Update the last index of the breakdown.
                    WPAY_Invoices::update_invoice_data( 'id', end( $breakdown_ids ), array( 'breakdown_last_index' => 1 ) );
                }

                $stripe_invoices = WPAY_Invoices::get_invoices( $order_id );
                $statusses       = array();
                if ( ! empty( $stripe_invoices ) ) {
                    foreach ( $stripe_invoices as $stripe_invoice ) {
                        $statusses[] = ! empty( $stripe_invoice->stripe_invoice_status ) ? $stripe_invoice->stripe_invoice_status : 'pending';
                    }
                }

                $status = ! empty( $statusses ) && is_array( $statusses ) ? end( $statusses ) : $status;
            } else {
                $status = WPay::get_invoice_payment_progress_status( $finalized, 'status' );

                $order->update_meta_data( '_wpay_stripe_invoice', $finalized );
            }

            $order->update_meta_data( '_wpay_stripe_invoice_status', $status );
            $order->save_meta_data();
        }

        // Add order note with invoice number (if available).
        if ( ! $is_deferred_auto_charge ) {
            if ( isset( $finalized['number'] ) && isset( $finalized['hosted_invoice_url'] ) ) {
                $invoice_number = $finalized['number'];
                $order_link     = $finalized['hosted_invoice_url'];
                $order_note     = sprintf(
                    /* translators: %1$s = invoice number; %2$s = order link */
                    __( 'Invoice #%1$s has been created. <a href="%2$s" target="_blank">View Invoice</a>.', 'woocommerce-wholesale-payments' ),
                    $invoice_number,
                    $order_link
                );
                $order->add_order_note( $order_note );
            } else {
                // Fallback for draft invoices or incomplete data.
                // Handle both string (non-auto-charge) and array (auto-charge) invoice IDs.
                $invoice_id_for_note = is_array( $invoice_id ) ? implode( ', ', $invoice_id ) : $invoice_id;
                $order->add_order_note(
                    sprintf(
                        /* translators: %s = invoice ID */
                        __( 'Stripe invoice created with ID: %s', 'woocommerce-wholesale-payments' ),
                        $invoice_id_for_note
                    )
                );
            }
        }

        if ( $this->overdue_on_hold ) {
            $order->update_status( 'on-hold', __( 'Order placed on hold due to outstanding overdue payment balance.', 'woocommerce-wholesale-payments' ) );
        } elseif ( 'on_hold' === $this->get_credit_limit_restriction( $order->get_customer_id() ) ) {
            $order->update_status( 'on-hold', __( 'Order placed on hold: customer has exceeded their credit limit.', 'woocommerce-wholesale-payments' ) );
        } else {
            $order->update_status( 'processing' );
        }

        // Schedule email reminders for this order (skip if deferred — will be scheduled on completion).
        if ( ! $is_deferred ) {
            \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler::schedule_order_reminders_on_creation( $order_id, $payment_plan );
        }

        WC()->cart->empty_cart();
        $this->wpay_set_to_empty_plan();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}
