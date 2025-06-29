<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use Automattic\WooCommerce\Internal\Admin\WCAdminAssets;
use DateTime;
use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Classes\Settings_Page;
use RymeraWebCo\WPay\Factories\Vite_App;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Payment_Gateway\WC_Wholesale_Payments;
use WC_Order;
use WP_Query;

/**
 * WC class.
 *
 * @since 1.0.0
 */
class WC extends Abstract_Class {

    /**
     * My account orders.
     *
     * @since 1.0.0
     * @var WC_Order[]
     */
    private $my_account_orders;

    /**
     * Add the wholesale payments gateway to WooCommerce.
     *
     * @param array $methods The payment gateways.
     *
     * @since 1.0.0
     * @return array
     */
    public function add_wpay_gateway( $methods ) {

        $methods[] = 'RymeraWebCo\WPay\Payment_Gateway\WC_Wholesale_Payments';

        return $methods;
    }

    /**
     * Redirect the manage payment method URL to settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function redirect_manage_payment_method() {

        $definition   = array(
            'page'    => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'tab'     => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'section' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        );
        $defaults     = array(
            'page'    => '',
            'tab'     => '',
            'section' => '',
        );
        $query_string = wp_parse_args( filter_input_array( INPUT_GET, $definition ), $defaults );
        if ( ! wp_doing_ajax() && 'wc-settings' === $query_string['page'] && 'checkout' === $query_string['tab'] && WC_Wholesale_Payments::instance()->id === $query_string['section'] ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . Settings_Page::MENU_SLUG ) );

            exit;
        }
    }

    /**
     * Parse the end of month plus 30 days.
     *
     * @param null   $_       Not used.
     * @param string $context The context.
     *
     * @since 1.0.0
     * @return int
     */
    public function end_of_month_plus_30_days( $_ = null, $context = 'view' ) {

        $date         = new DateTime();
        $end_of_month = new DateTime( 'last day of this month' );
        $diff         = $date->diff( $end_of_month );
        $days         = absint( $diff->format( '%a' ) );

        return absint( $days ) + 30;
    }

    /**
     * Parse on the 21st of next month.
     *
     * @param null   $_       Not used.
     * @param string $context The context.
     *
     * @since 1.0.0
     * @return int Timestamp.
     */
    public function on_21st_of_next_month( $_ = null, $context = 'view' ) {

        if ( 'raw' === $context ) {
            return strtotime( gmdate( 'Y-m-21', strtotime( 'next month' ) ) );
        }

        return gmdate( 'F jS, Y', strtotime( gmdate( 'Y-m-21', strtotime( 'next month' ) ) ) );
    }

    /**
     * Display the payment method on the thankyou page.
     *
     * @param int $order_id The order ID.
     *
     * @since 1.0.0
     * @return void
     */
    public function thankyou_page_payment_method( $order_id ) {

        $order = wc_get_order( $order_id );

        include_once Helper::locate_front_template_part( 'thank-you-payment-method' );
    }

    /**
     * Customize order item totals.
     *
     * @param array    $total_rows The total rows.
     * @param WC_Order $order      The order.
     *
     * @return array
     */
    public function get_order_item_totals( $total_rows, $order ) {

        if ( $order->get_payment_method() === WC_Wholesale_Payments::instance()->id ) {
            $payment_plan = WPay::get_order_plan( $order );
            if ( ! empty( $payment_plan ) ) {
                $total_rows['payment_method']['value'] .= ':<br>' . nl2br( $payment_plan->title );
            }
        }

        return $total_rows;
    }

    /**
     * Get the admin order actions.
     *
     * @param array $views Order actions.
     *
     * @since 1.0.0
     * @return array
     */
    public function view_links( $views ) {

        $views['wpay-pending'] = sprintf(
            '<a href="%1$s" title="%3$s">%2$s</a>',
            esc_url( add_query_arg( array( 'wpay-invoice' => 'pending' ), admin_url( 'edit.php?post_type=shop_order' ) ) ),
            __( 'Pending Payment', 'woocommerce-wholesale-payments' ),
            esc_attr__( 'Wholesale Payments: Pending Payment', 'woocommerce-wholesale-payments' )
        );

        $views['wpay-overdue'] = sprintf(
            '<a href="%1$s" title="%3$s">%2$s</a>',
            esc_url( add_query_arg( array( 'wpay-invoice' => 'overdue' ), admin_url( 'edit.php?post_type=shop_order' ) ) ),
            __( 'Overdue', 'woocommerce-wholesale-payments' ),
            esc_attr__( 'Wholesale Payments: Overdue', 'woocommerce-wholesale-payments' )
        );

        $views['wpay-paid'] = sprintf(
            '<a href="%1$s" title="%3$s">%2$s</a>',
            esc_url( add_query_arg( array( 'wpay-invoice' => 'paid' ), admin_url( 'edit.php?post_type=shop_order' ) ) ),
            __( 'Paid', 'woocommerce-wholesale-payments' ),
            esc_attr__( 'Wholesale Payments: Paid in Full', 'woocommerce-wholesale-payments' )
        );

        return $views;
    }

    /**
     * Enqueue WooCommerce admin page scripts/styles.
     *
     * @param string $hook_suffix The hook suffix.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_scripts( $hook_suffix ) {

        global $pagenow, $typenow;

        if ( ( 'edit.php' === $hook_suffix && 'shop_order' === $typenow ) || // Regular orders list table.
            ( 'admin.php' === $pagenow && 'wc-orders' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) // HPOS orders list table.
        ) {
            wp_enqueue_style(
                'wpay-admin',
                plugins_url( 'dist/css/orders-list-table.css', WPAY_PLUGIN_FILE ),
                array(),
                filemtime( WPAY_PLUGIN_DIR_PATH . 'dist/css/orders-list-table.css' )
            );

            $l10n = Helper::vite_app_common_l10n(
                array(
                    'i18n' => array(
                        'checking' => esc_html__( 'Checking...', 'woocommerce-wholesale-payments' ),
                        'error'    => esc_html__( 'Error: ', 'woocommerce-wholesale-payments' ),
                        'status'   => array(
                            /* translators: %s = date */
                            'overdue' => esc_html__( 'Overdue: %s', 'woocommerce-wholesale-payments' ),
                            'paid'    => esc_html__( 'Paid', 'woocommerce-wholesale-payments' ),
                            /* translators: %s = date */
                            'pending' => esc_html__( 'Due: %s', 'woocommerce-wholesale-payments' ),
                        ),
                    ),
                )
            );

            wp_add_inline_script( 'wpay-admin-l10n', 'window.wpayObj = lodash.merge(window.wpayObj, ' . wp_json_encode( $l10n ) . ');' );
            $orders_list_table_script = new Vite_App(
                'wpay-orders-list-table',
                'src/vanilla/admin/orders/list-table.ts',
                array( 'jquery', 'wp-date', 'wp-i18n' ),
                null
            );
            $orders_list_table_script->enqueue();
        }
    }

    /**
     * Order list table columns.
     *
     * @param array $columns Current orders list table columns.
     *
     * @since 1.0.0
     * @return array
     */
    public function list_table_columns( $columns ) {

        $columns['wpay'] = esc_html__( 'Wholesale Payments', 'woocommerce-wholesale-payments' );

        return $columns;
    }

    /**
     * Render custom column data.
     *
     * @param string $column_name Column name.
     * @param int    $order_id    Order ID.
     *
     * @since 1.0.0
     * @return void
     */
    public function list_table_custom_column( $column_name, $order_id ) {

        if ( 'wpay' === $column_name ) {
            $payment_plan = WPay::get_order_plan( $order_id );
            if ( ! empty( $payment_plan ) ) {
                $order   = wc_get_order( $order_id );
                $invoice = (array) $order->get_meta( '_wpay_stripe_invoice' );
                if ( ! empty( $invoice['amounts_due'] ) ) {
                    $status = WPay::get_invoice_payment_progress_status( $invoice );
                    switch ( $status['status'] ) {
                        case 'overdue':
                            /* translators: %s = due date */
                            $status_text = __( 'Overdue: %s', 'woocommerce-wholesale-payments' );
                            break;
                        case 'paid':
                            $status_text = __( 'Paid', 'woocommerce-wholesale-payments' );
                            break;
                        case 'void':
                            $status_text = __( 'Voided', 'woocommerce-wholesale-payments' );
                            break;
                        case 'pending':
                        default:
                            /* translators: %s = due date */
                            $status_text = __( 'Due: %s', 'woocommerce-wholesale-payments' );
                    }
                    printf(
                        '<span class="wpay-status wpay-status-%1$s"><span class="status-text">%2$s</span>%3$s</span>',
                        esc_attr( $status['status'] ),
                        esc_html(
                            sprintf(
                                $status_text,
                                gmdate(
                                    'F d, Y',
                                    $status['ts']
                                )
                            )
                        ),
                        ( 'paid' !== $status['status'] ? ' <span class="dashicons dashicons-update order-wpay-status-check"></span>' : '' )
                    );
                } else {
                    printf( '<p class="description">%s</p>', esc_html__( 'No payment plan', 'woocommerce-wholesale-payments' ) );
                }
            } else {
                printf( '&mdash;' );
            }
        }
    }

    /**
     * Customize the orders list table query.
     *
     * @param WP_Query|array $query The query object or args array.
     *
     * @since 1.0.0
     * @since 1.0.4 Added support for HPOS.
     * @return WP_Query|array
     */
    public function orders_list_table_query( $query ) {

        // Handle traditional posts query (WP_Query object).
        if ( is_object( $query ) && method_exists( $query, 'is_main_query' ) ) {
            $is_traditional_orders = is_admin() && $query->is_main_query() &&
                filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === 'shop_order';

            if ( $is_traditional_orders ) {
                $invoice = filter_input( INPUT_GET, 'wpay-invoice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                if ( ! empty( $invoice ) ) {
                    $meta_query = $this->get_invoice_meta_query( $invoice );
                    if ( ! empty( $meta_query ) ) {
                        $query->set( 'meta_query', $meta_query );
                    }
                }
            }
            return $query;
        }

        // Handle HPOS query (array of args).
        if ( is_array( $query ) && is_admin() &&
            filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === 'wc-orders' ) {

            $invoice = filter_input( INPUT_GET, 'wpay-invoice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
            if ( ! empty( $invoice ) ) {
                $meta_query = $this->get_invoice_meta_query( $invoice );
                if ( ! empty( $meta_query ) ) {
                    $query['meta_query'] = $meta_query;
                }
            }
        }

        return $query;
    }

    /**
     * Get meta query for invoice filtering.
     *
     * @param string $invoice The invoice status.
     *
     * @since 1.0.4
     * @return array
     */
    private function get_invoice_meta_query( $invoice ) {

        $meta_query = array();
        switch ( $invoice ) {
            case 'pending':
                $meta_query[] = array(
                    'key'     => '_wpay_stripe_invoice_status',
                    'value'   => 'pending',
                    'compare' => '=',
                );
                break;
            case 'overdue':
                $meta_query[] = array(
                    'key'     => '_wpay_stripe_invoice_status',
                    'value'   => 'overdue',
                    'compare' => '=',
                );
                break;
            case 'paid':
                $meta_query[] = array(
                    'key'     => '_wpay_stripe_invoice_status',
                    'value'   => 'paid',
                    'compare' => '=',
                );
                break;
        }

        return $meta_query;
    }

    /**
     * Show the invoice payment progress on the order details page.
     *
     * @param WC_Order $order The order.
     *
     * @since 1.0.0
     * @return void
     */
    public function view_payments_progress( $order ) {

        $payment_plan = $order->get_meta( '_wpay_plan' );
        if ( empty( $payment_plan ) ) {
            return;
        }
        $invoice = $order->get_meta( '_wpay_stripe_invoice' );

        include_once Helper::locate_front_template_part( 'order-invoice-payments-progress' );
    }

    /**
     * Add View Payments action to the orders list table.
     *
     * @param array    $actions The actions.
     * @param WC_Order $order   The order.
     *
     * @since 1.0.0
     * @return array
     */
    public function orders_actions( $actions, $order ) {

        $plan = WPay::get_order_plan( $order );
        if ( ! empty( $plan ) && 'cancelled' !== $order->get_status( 'edit' ) ) {
            $this->my_account_orders[] = $order;

            $actions['view-wpay'] = array(
                'url'  => "#view-payments-{$order->get_id()}",
                'name' => __( 'View Payments', 'woocommerce-wholesale-payments' ),
            );
        }

        return $actions;
    }

    /**
     * Render the orders app entry.
     *
     * @param bool|int $has_orders     Whether the user has orders in `Orders` page or the order id if on `View Order`
     *                                 page.
     *
     * @since 1.0.0
     * @return void
     */
    public function orders_app_entry( $has_orders ) {

        if ( $has_orders && ! empty( $this->my_account_orders ) ) {
            $invoices       = array();
            $invoice_fields = array(
                'amounts_due'        => '',
                'hosted_invoice_url' => '',
            );
            foreach ( $this->my_account_orders as $account_order ) {
                $invoice = (array) $account_order->get_meta( '_wpay_stripe_invoice' );

                $invoices[ $account_order->get_id() ] = array_intersect_key( $invoice, $invoice_fields );
            }
            if ( ! empty( $invoices ) ) {
                wp_add_inline_script(
                    'wpay-view-payments',
                    sprintf(
                        'var wpayInvoices = %s;',
                        wp_json_encode( $invoices )
                    ),
                    'before'
                );
            }
        }
        printf( '<div id="view-payments-app"></div>' );
    }

    /**
     * Enqueue orders script.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_orders_script() {

        if ( is_account_page() || is_order_received_page() ) {
            $l10n = array(
                'i18n'       => array(
                    /* translators: %1$d = payment number; %2$s = status */
                    'payment'         => __( 'Payment #%1$d (%2$s)', 'woocommerce-wholesale-payments' ),
                    /* translators: %s = due date */
                    'due'             => __( 'Due: %s', 'woocommerce-wholesale-payments' ),
                    /* translators: %s = formatted price amount due */
                    'amount'          => __( 'Amount %s', 'woocommerce-wholesale-payments' ),
                    /* translators: %s = status */
                    'final'           => __( 'Final Payment (%s)', 'woocommerce-wholesale-payments' ),
                    'payNow'          => __( 'Pay Now', 'woocommerce-wholesale-payments' ),
                    'paid'            => __( 'Paid', 'woocommerce-wholesale-payments' ),
                    'overdue'         => __( 'Overdue', 'woocommerce-wholesale-payments' ),
                    'pending'         => __( 'Pending', 'woocommerce-wholesale-payments' ),
                    'paymentTimeline' => __( 'Payment Timeline', 'woocommerce-wholesale-payments' ),
                ),
                'wcSettings' => array(
                    'currency' => array(
                        'code'              => get_woocommerce_currency(),
                        'symbol'            => get_woocommerce_currency_symbol(),
                        'position'          => get_option( 'woocommerce_currency_pos' ),
                        'decimalSeparator'  => wc_get_price_decimal_separator(),
                        'thousandSeparator' => wc_get_price_thousand_separator(),
                        'numDecimals'       => wc_get_price_decimals(),
                    ),
                ),
            );
            $app  = new Vite_App(
                'wpay-view-payments',
                'src/apps/my-account/orders/index.ts',
                array( 'wp-date', 'wp-i18n', 'wp-element' ),
                Helper::vite_app_common_l10n( $l10n )
            );
            $app->enqueue();
        }
    }

    /**
     * Declare compatibility with WooCommerce custom order tables.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hpos_compatibility() {

        /**
         * Declare compatibility with WooCommerce custom order tables.
         *
         * @since 1.0.0
         */
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPAY_PLUGIN_FILE );
        }
    }

    /**
     * Pre kses.
     *
     * @param string $content The content.
     *
     * @since 1.0.0
     * @return string
     */
    public function pre_kses( $content ) {

        global $current_screen;

        //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( null !== $current_screen && 'woocommerce_page_wc-orders' === $current_screen->id ) {// order edit page.
            /***************************************************************************
             * Decode html entities in Order Edit page.
             ***************************************************************************
             *
             * We decode html entities in the Order Edit page to display the payment
             * plan title correctly specially in TEST MODE.
             */
            $content = html_entity_decode( $content );
        }

        return $content;
    }

    /**
     * Fix the order payment method title format.
     *
     * @param string   $title The payment method title.
     * @param WC_Order $order The order.
     *
     * @return mixed
     */
    public function order_payment_method_title( $title, $order ) {

        if ( WC_Wholesale_Payments::instance()->id === $order->get_payment_method( 'edit' ) && str_contains( $title, 'wpay-test-mode-tag' ) ) {
            $title = wp_strip_all_tags( $title );
            $title = str_replace( 'TEST MODE', '**TEST MODE**', $title );
        }

        return $title;
    }

    /**
     * Run the integration.
     *
     * @since 1.0.0
     * @return void
     */
    public function run() {

        add_action( 'admin_init', array( $this, 'redirect_manage_payment_method' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_wpay_gateway' ) );
        add_filter( 'wpay_parse_end_of_month_plus_30_days', array( $this, 'end_of_month_plus_30_days' ), 10, 2 );
        add_filter( 'wpay_parse_on_21st_of_next_month', array( $this, 'on_21st_of_next_month' ), 10, 2 );
        add_action( 'woocommerce_thankyou_wc_wholesale_payments', array( $this, 'thankyou_page_payment_method' ) );
        add_filter( 'woocommerce_get_order_item_totals', array( $this, 'get_order_item_totals' ), 10, 2 );
        add_filter( 'views_edit-shop_order', array( $this, 'view_links' ) );
        add_filter( 'views_woocommerce_page_wc-orders', array( $this, 'view_links' ) );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'list_table_columns' ) );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'list_table_columns' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'list_table_custom_column' ), 10, 2 );
        add_action(
            'manage_woocommerce_page_wc-orders_custom_column',
            array(
                $this,
                'list_table_custom_column',
            ),
            10,
            2
        );
        add_action( 'pre_get_posts', array( $this, 'orders_list_table_query' ) );
        add_filter( 'woocommerce_order_query_args', array( $this, 'orders_list_table_query' ) );
        add_filter( 'woocommerce_order_details_after_order_table', array( $this, 'view_payments_progress' ) );
        add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_actions' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_orders_script' ) );
        add_action( 'woocommerce_view_order', array( $this, 'orders_app_entry' ) );
        add_action( 'woocommerce_after_account_orders', array( $this, 'orders_app_entry' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'orders_app_entry' ) );
        add_action( 'before_woocommerce_init', array( $this, 'hpos_compatibility' ) );
        add_filter( 'pre_kses', array( $this, 'pre_kses' ), 100 );
        add_filter( 'woocommerce_order_get_payment_method_title', array( $this, 'order_payment_method_title' ), 10, 2 );
    }
}
