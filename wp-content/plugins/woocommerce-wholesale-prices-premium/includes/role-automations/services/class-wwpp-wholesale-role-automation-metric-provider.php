<?php
/**
 * Wholesale Role Automation Metric Provider.
 *
 * Single read surface for the metrics consumed by the Evaluator: lifetime and
 * windowed `total_spent` and `order_count`. Lifetime reads delegate to WC
 * core's user-meta cache; windowed reads use `wc_get_orders` with
 * `Window_Helper` for date math and `Currency_Helper` for base-currency
 * normalization.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Services
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Metric_Provider.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Metric_Provider {

    /**
     * Window helper collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Window_Helper
     */
    private $window_helper;

    /**
     * Currency helper collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Currency_Helper
     */
    private $currency_helper;

    /**
     * WWPP_Wholesale_Role_Automation_Metric_Provider constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Window_Helper   $window_helper   Window helper instance.
     * @param WWPP_Wholesale_Role_Automation_Currency_Helper $currency_helper Currency helper instance.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Window_Helper $window_helper,
        WWPP_Wholesale_Role_Automation_Currency_Helper $currency_helper
    ) {
        $this->window_helper   = $window_helper;
        $this->currency_helper = $currency_helper;
    }

    /**
     * Lifetime total spent for a customer in store base currency.
     *
     * Delegates to `wc_get_customer_total_spent()` so the result is read from
     * WC core's `_money_spent` / `wc_money_spent` user-meta cache. The
     * underlying SQL sums order totals across `wc_get_is_paid_statuses()`
     * (i.e. `processing` + `completed`). Fully-refunded orders are excluded
     * as a side-effect of their status moving to `refunded`; partial refunds
     * are NOT subtracted from the per-order total — the gross stored total
     * is counted unchanged. Matches the windowed sibling, which also sums
     * gross totals via `$order->get_total()` / `Currency_Helper::order_total_in_base()`.
     *
     * Multi-currency stores: the `woocommerce_customer_get_total_spent` filter
     * is the canonical seam where WCML and similar plugins convert the cached
     * value to base currency, so this method returns base-currency totals on
     * both single- and multi-currency installs.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user ID to inspect.
     *
     * @return float The lifetime total spent in store base currency.
     */
    public function total_spent_lifetime( int $user_id ): float {
        return (float) wc_get_customer_total_spent( $user_id );
    }

    /**
     * Lifetime order count for a customer.
     *
     * Delegates to `wc_get_customer_order_count()`, which counts orders
     * across every status returned by `wc_get_order_statuses()` — not just
     * `completed`. The Evaluator is expected to apply any status-narrowing
     * thresholds itself when needed.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user ID to inspect.
     *
     * @return int The lifetime order count.
     */
    public function order_count_lifetime( int $user_id ): int {
        return (int) wc_get_customer_order_count( $user_id );
    }

    /**
     * Windowed total spent for a customer in store base currency.
     *
     * Sums `Currency_Helper::order_total_in_base()` across orders whose
     * `status` is `completed` and whose `date_paid` falls inside
     * `[ $from_ts, $to_ts ]`.
     *
     * NOTE: this is a STRICTLY NARROWER status filter than
     * `total_spent_lifetime`, which delegates to WC core's
     * `wc_get_is_paid_statuses()` (i.e. `processing` + `completed`). An
     * in-window `processing` order contributes to lifetime spend but NOT
     * to windowed spend — the Evaluator must apply windowed thresholds
     * with this asymmetry in mind. Partial refunds are not subtracted
     * either side; gross totals are summed for symmetry with the lifetime
     * helper.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user ID to inspect.
     * @param int $from_ts Window start (inclusive) as a Unix timestamp.
     * @param int $to_ts   Window end (inclusive) as a Unix timestamp.
     *
     * @return float The total spent within the window in store base currency.
     */
    public function total_spent_window( int $user_id, int $from_ts, int $to_ts ): float {
        $orders = wc_get_orders( $this->build_completed_window_query_args( $user_id, $from_ts, $to_ts, 'objects' ) );

        $total = 0.0;
        foreach ( $orders as $order ) {
            $total += $this->currency_helper->order_total_in_base( $order );
        }

        return $total;
    }

    /**
     * Windowed completed-order count for a customer.
     *
     * Counts orders whose `status` is `completed` and whose `date_paid`
     * falls inside `[ $from_ts, $to_ts ]`.
     *
     * NOTE: this is a STRICTLY NARROWER status filter than
     * `order_count_lifetime`, which counts across every status returned by
     * `wc_get_order_statuses()` (all statuses, not just paid ones). An
     * in-window `processing`, `on-hold`, or `pending` order contributes to
     * the lifetime count but NOT to the windowed count — the Evaluator must
     * apply windowed thresholds with this asymmetry in mind.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user ID to inspect.
     * @param int $from_ts Window start (inclusive) as a Unix timestamp.
     * @param int $to_ts   Window end (inclusive) as a Unix timestamp.
     *
     * @return int The order count within the window.
     */
    public function order_count_window( int $user_id, int $from_ts, int $to_ts ): int {
        $ids = wc_get_orders( $this->build_completed_window_query_args( $user_id, $from_ts, $to_ts, 'ids' ) );

        return count( $ids );
    }

    /**
     * Build the shared `wc_get_orders` argument array used by both windowed methods.
     *
     * Single source of truth for the customer + completed-status + paid-date range
     * filter so `total_spent_window` and `order_count_window` cannot drift apart.
     *
     * @since 2.1.0
     *
     * @param int    $user_id     The user ID to inspect.
     * @param int    $from_ts     Window start (inclusive) as a Unix timestamp.
     * @param int    $to_ts       Window end (inclusive) as a Unix timestamp.
     * @param string $return_type `wc_get_orders` return shape — either `'objects'` or `'ids'`.
     *
     * @return array The argument array suitable for passing to `wc_get_orders`.
     */
    private function build_completed_window_query_args( int $user_id, int $from_ts, int $to_ts, string $return_type ): array {
        return array(
            'customer_id' => $user_id,
            'status'      => array( 'completed' ),
            'date_paid'   => $from_ts . '...' . $to_ts,
            'limit'       => -1,
            'return'      => $return_type,
        );
    }
}
