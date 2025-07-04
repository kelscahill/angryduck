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
     * @return array|void
     */
    public function process_payment( $order_id ) {

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

        /***************************************************************************
         * Check if customer id exists for this order
         ***************************************************************************
         *
         * We check if the customer id already exists for this order.
         */
        $customer_id = $order->get_meta( $customer_id_meta_key );
        if ( ! $customer_id ) {
            /***************************************************************************
             * Check if customer already exists in Stripe
             ***************************************************************************
             *
             * We check if the customer already exists in Stripe. If not, we create a
             * new customer.
             */
            $search_customer = Stripe::instance()->search_customer( array( 'email' => $order->get_billing_email() ) );

            $customer = array();
            if ( is_array( $search_customer ) && empty( $search_customer['data'] ) ) {
                $customer = Stripe::instance()->create_customer(
                    $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    $order->get_billing_email()
                );
            } elseif ( is_array( $search_customer ) && ! empty( $search_customer['data'] ) ) {
                $customer = $search_customer['data'][0];
            }

            $customer_id = isset( $customer['id'] ) ? $customer['id'] : null;

            /***************************************************************************
             * Record Stripe customer ID to order meta
             ***************************************************************************
             *
             * We record the Stripe customer ID to the order meta.
             */
            if ( $customer_id ) {
                $order->update_meta_data( $customer_id_meta_key, $customer_id );
                $order->save_meta_data();
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

                if ( $auto_charge ) {
                    // Save breakdown to wpay table per line item.
                    $breakdown_ids[] = WPAY_Invoices::add_invoice_breakdown( $customer_id, $i, $order_id, $amount_args );
                }
            }

            if ( $auto_charge ) {
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
             * Create Stripe invoice
             ***************************************************************************
             *
             * We create the Stripe invoice with the payload for this order.
             */
            $invoice_response = Stripe::instance()->create_invoice( $customer_id, $amounts_due, $auto_charge );
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
                $order_total_in_cents = isset( $amounts_due[0]['amount'] ) ? $amounts_due[0]['amount'] : $order_total_in_cents; // get the first amounts due.
                $order_description    = isset( $amounts_due[0]['description'] ) ? $amounts_due[0]['description'] : '';
                $invoice_ids          = array( $invoice_id );

                $order->update_meta_data( $invoice_id_meta_key, $invoice_ids );
            } else {
                $order->update_meta_data( $invoice_id_meta_key, $invoice_id );
            }

            $order->save_meta_data();
        }

        $invoice_item_id_meta_key = '_wpay_stripe_invoice_item_id';

        /***************************************************************************
         * Check Stripe invoice item
         ***************************************************************************
         *
         * We check if there's already a Stripe invoice item associated for this
         * order.
         */
        $invoice_item_id = $order->get_meta( $invoice_item_id_meta_key );
        if ( ! $invoice_item_id ) {
            /***************************************************************************
             * Add Stripe invoice item
             ***************************************************************************
             *
             * We add the Stripe invoice item for this order.
             */
            $invoice_item = Stripe::instance()->add_invoice_item(
                $customer_id,
                $invoice_id,
                $order_total_in_cents,
                $order_description
            );
            if ( is_wp_error( $invoice_item ) ) {
                wc_add_notice( $invoice_item->get_error_message(), 'error' );

                if ( $is_checkout ) {
                    return $redirect_fail;
                } else {
                    wp_safe_redirect( $redirect );
                    exit;
                }
            }

            $invoice_item_id = $invoice_item['id'];

            if ( $auto_charge ) {
                $invoice_item_ids = array( $invoice_item_id );

                $order->update_meta_data( $invoice_item_id_meta_key, $invoice_item_ids );
            } else {
                $order->update_meta_data( $invoice_item_id_meta_key, $invoice_item_id );
            }

            $order->save_meta_data();
        }

        /***************************************************************************
         * Finalize invoice
         ***************************************************************************
         *
         * We finalize the invoice to send it to the customer.
         */
        $finalized = Stripe::instance()->finalize_invoice( $invoice_id );
        if ( is_wp_error( $finalized ) ) {
            if ( str_contains( $finalized->get_error_message(), 'already finalized' ) ) {
                $order->set_status( OrderStatus::PROCESSING, __( 'Invoice already finalized.', 'woocommerce-wholesale-payments' ) );
                $order->save();
                wp_safe_redirect( $order->get_checkout_order_received_url() );
                exit;
            }

            wc_add_notice( $finalized->get_error_message(), 'error' );

            if ( $is_checkout ) {
                return $redirect_fail;
            } else {
                wp_safe_redirect( $redirect );
                exit;
            }
        }

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

            $invoice_data = array(
                'invoice_id'            => $invoice_id,
                'invoice_item_id'       => $invoice_item_id,
                'stripe_invoice'        => wp_json_encode( $finalized ),
                'stripe_invoice_status' => $status,
                'status'                => 'created',
            );
            WPAY_Invoices::update_invoice_data( 'id', $breakdown_ids[0], $invoice_data );

            // Update the last index of the breakdown.
            WPAY_Invoices::update_invoice_data( 'id', end( $breakdown_ids ), array( 'breakdown_last_index' => 1 ) );

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

        // Add order note with invoice number.
        $invoice_number = $finalized['number'];
        $order_link     = $finalized['hosted_invoice_url'];
        $order_note     = sprintf(
            /* translators: %1$s = invoice number; %2$s = order link */
            __( 'Invoice #%1$s has been created. <a href="%2$s" target="_blank">View Invoice</a>.', 'woocommerce-wholesale-payments' ),
            $invoice_number,
            $order_link
        );
        $order->add_order_note( $order_note );

        $order->update_status( 'processing' );

        // Schedule email reminders for this order.
        \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler::schedule_order_reminders_on_creation( $order_id, $payment_plan );

        WC()->cart->empty_cart();
        $this->wpay_set_to_empty_plan();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}
