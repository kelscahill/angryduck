<?php
/**
 * Wholesale Role Automation Scheduler.
 *
 * Bridges WC order events and the daily sweep into Action Scheduler so all
 * evaluation work runs off-request. Owns the per-user worker callback and the
 * sweep batcher.
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
 * Class WWPP_Wholesale_Role_Automation_Scheduler.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Scheduler {

    /**
     * Action Scheduler group name for all role-automation actions.
     *
     * @since 2.1.0
     * @var   string
     */
    const AS_GROUP = 'wwpp-wholesale-role-automation';

    /**
     * Action Scheduler hook for a sweep chunk (a batch of up to
     * `SWEEP_CHUNK_SIZE` user IDs to fan out into per-user evaluations).
     *
     * @since 2.1.0
     * @var   string
     */
    const ACTION_SWEEP_CHUNK = 'wwpp_wholesale_role_automation_sweep_chunk';

    /**
     * Maximum number of user IDs per sweep-chunk async action. The daily
     * sweep splits the matched user list into chunks of this size to keep
     * each chunk's enqueue work bounded and to distribute per-user
     * enqueueing across multiple Action Scheduler ticks (matching the
     * brief's perf requirement for 100k+ customer stores).
     *
     * @since 2.1.0
     * @var   int
     */
    const SWEEP_CHUNK_SIZE = 100;

    /**
     * Trigger type emitted by the daily sweep for per-user evaluation actions.
     *
     * @since 2.1.0
     * @var   string
     */
    const TRIGGER_SCHEDULED = 'scheduled';

    /**
     * Trigger type emitted when an order moves to the completed status.
     *
     * @since 2.1.0
     * @var   string
     */
    const TRIGGER_ORDER_COMPLETED = 'order_completed';

    /**
     * Trigger type emitted when an order is refunded.
     *
     * @since 2.1.0
     * @var   string
     */
    const TRIGGER_ORDER_REFUNDED = 'refund';

    /**
     * Trigger type emitted when an order moves to the cancelled status.
     *
     * @since 2.1.0
     * @var   string
     */
    const TRIGGER_ORDER_CANCELLED = 'cancelled';

    /**
     * Evaluator collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Evaluator
     */
    private $evaluator;

    /**
     * Action dispatcher collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Action_Dispatcher
     */
    private $dispatcher;

    /**
     * Role helper collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Role_Helper
     */
    private $role_helper;

    /**
     * Rules repository collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Rules_Repository
     */
    private $rules_repository;

    /**
     * WWPP_Wholesale_Role_Automation_Scheduler constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Evaluator         $evaluator        Evaluator instance.
     * @param WWPP_Wholesale_Role_Automation_Action_Dispatcher $dispatcher       Action dispatcher instance.
     * @param WWPP_Wholesale_Role_Automation_Role_Helper       $role_helper      Role helper instance.
     * @param WWPP_Wholesale_Role_Automation_Rules_Repository  $rules_repository Rules repository instance.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Evaluator $evaluator,
        WWPP_Wholesale_Role_Automation_Action_Dispatcher $dispatcher,
        WWPP_Wholesale_Role_Automation_Role_Helper $role_helper,
        WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repository
    ) {
        $this->evaluator        = $evaluator;
        $this->dispatcher       = $dispatcher;
        $this->role_helper      = $role_helper;
        $this->rules_repository = $rules_repository;
    }

    /**
     * Enqueue an asynchronous evaluation for the supplied user.
     *
     * Dispatches an `as_enqueue_async_action` for `WWPP_ACTION_WHOLESALE_ROLE_AUTOMATION_EVAL_USER`.
     * Originating hooks (order completed/refunded/cancelled, admin save) call
     * this and return immediately so shopfront latency is unaffected.
     *
     * @since 2.1.0
     *
     * @param int    $user_id      The user to evaluate.
     * @param string $trigger_type One of `order_completed`, `refund`, `cancelled`, `scheduled`. The
     *                             `admin` trigger is reserved for `Pause_Handler`'s synchronous
     *                             audit writes and is not enqueued through this path.
     * @param string $trigger_ref  Compact identifier (order ID, refund ID, or scheduled-run ID);
     *                             capped at 32 chars by the audit-table schema.
     *
     * @return void
     */
    public function enqueue_user_evaluation( int $user_id, string $trigger_type, string $trigger_ref = '' ): void {
        if ( $user_id <= 0 ) {
            return;
        }

        if ( empty( $this->rules_repository->all() ) ) {
            return;
        }

        as_enqueue_async_action(
            WWPP_ACTION_WHOLESALE_ROLE_AUTOMATION_EVAL_USER,
            array( $user_id, $trigger_type, $trigger_ref ),
            self::AS_GROUP
        );
    }

    /**
     * WC `woocommerce_order_status_completed` callback.
     *
     * Resolves the customer ID from the order and enqueues an asynchronous
     * evaluation. Returns immediately; the actual evaluation runs on the next
     * Action Scheduler tick.
     *
     * @since 2.1.0
     *
     * @param int      $order_id The order ID. Unused — kept for hook signature compatibility.
     * @param WC_Order $order    The WC order object.
     *
     * @return void
     */
    public function on_order_completed( $order_id, $order ): void {
        unset( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $this->enqueue_user_evaluation(
            (int) $order->get_customer_id(),
            self::TRIGGER_ORDER_COMPLETED,
            (string) $order->get_id()
        );
    }

    /**
     * WC `woocommerce_order_refunded` callback.
     *
     * Resolves the parent order from the order ID — WooCommerce fires this
     * hook with `( $order_id, $refund_id )`, NOT with the order object as the
     * second argument (see `wc_create_refund()` in
     * `wc-order-functions.php`). Enqueues an asynchronous evaluation for the
     * parent order's customer.
     *
     * @since 2.1.0
     *
     * @param int $order_id  The parent order ID.
     * @param int $refund_id The refund post ID — recorded as `trigger_ref` so the audit row is
     *                       distinguishable from the parent order's `order_completed` row.
     *
     * @return void
     */
    public function on_order_refunded( $order_id, $refund_id ): void {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $this->enqueue_user_evaluation(
            (int) $order->get_customer_id(),
            self::TRIGGER_ORDER_REFUNDED,
            (string) $refund_id
        );
    }

    /**
     * WC `woocommerce_order_status_cancelled` callback.
     *
     * Resolves the customer ID from the order and enqueues an asynchronous
     * evaluation.
     *
     * @since 2.1.0
     *
     * @param int      $order_id The order ID. Unused — kept for hook signature compatibility.
     * @param WC_Order $order    The WC order object.
     *
     * @return void
     */
    public function on_order_cancelled( $order_id, $order ): void {
        unset( $order_id );

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $this->enqueue_user_evaluation(
            (int) $order->get_customer_id(),
            self::TRIGGER_ORDER_CANCELLED,
            (string) $order->get_id()
        );
    }

    /**
     * Action Scheduler callback for the daily sweep.
     *
     * Queries users in any role participating in an active rule, one page of
     * `SWEEP_CHUNK_SIZE` IDs at a time, and enqueues an `ACTION_SWEEP_CHUNK`
     * async action per page. Each chunk action then fans out per-user
     * evaluations on its own AS tick. The two-tier fan-out plus paginated
     * read keeps every individual AS tick bounded — memory per iteration is
     * O(SWEEP_CHUNK_SIZE), not O(total users) — so stores with 100k+
     * customers don't see a single sweep tick monopolise the AS worker or
     * the PHP memory limit.
     *
     * On exception, logs via `wc_get_logger()` and rethrows so Action
     * Scheduler records the action as failed (AS surfaces failures in the
     * Scheduled Actions UI; configurable retry is a per-host setting).
     *
     * @since 2.1.0
     *
     * @return void
     * @throws Exception When rule retrieval or user query fails. Rethrown after logging.
     */
    public function handle_sweep(): void {
        global $wpdb;

        try {
            $rules = $this->rules_repository->all();

            $role_keys = $this->derive_sweep_role_keys( $rules );

            if ( empty( $role_keys ) ) {
                return;
            }

            // `wp_capabilities` is the canonical user-role meta key. The capabilities
            // value is a serialized PHP array, so each role match is a LIKE on the
            // serialized form: a:1:{s:N:"<role>";b:1;}.
            $cap_key      = $wpdb->prefix . 'capabilities';
            $like_clauses = array();
            $like_args    = array();
            foreach ( $role_keys as $role_key ) {
                $like_clauses[] = 'm.meta_value LIKE %s';
                $like_args[]    = '%' . $wpdb->esc_like( '"' . $role_key . '"' ) . '%';
            }
            $like_sql = '( ' . implode( ' OR ', $like_clauses ) . ' )';

            // Cursor-based pagination over the user-ID primary key (`u.ID > %d ORDER BY u.ID ASC`).
            // Avoids the O(N²) deep-OFFSET cost of `get_users(['paged' => N])` for stores with
            // 100k+ users — each page does at most SWEEP_CHUNK_SIZE LIKE comparisons instead of
            // OFFSET + SWEEP_CHUNK_SIZE.
            $sweep_run_id = gmdate( 'Ymd-His' );
            $last_id      = 0;
            while ( true ) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                $sql = $wpdb->prepare(
                    "SELECT u.ID FROM {$wpdb->users} u
                     INNER JOIN {$wpdb->usermeta} m
                         ON m.user_id = u.ID AND m.meta_key = %s
                     WHERE u.ID > %d AND $like_sql
                     ORDER BY u.ID ASC
                     LIMIT %d",
                    array_merge( array( $cap_key, $last_id ), $like_args, array( self::SWEEP_CHUNK_SIZE ) )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                $user_ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Hand-built SQL using $wpdb->prepare above; uncacheable cursor query.

                $count = count( $user_ids );

                if ( 0 === $count ) {
                    break;
                }

                $last_id = (int) end( $user_ids );

                as_enqueue_async_action(
                    self::ACTION_SWEEP_CHUNK,
                    array( array_map( 'intval', $user_ids ), $sweep_run_id ),
                    self::AS_GROUP
                );

                if ( $count < self::SWEEP_CHUNK_SIZE ) {
                    break;
                }
            }
        } catch ( Exception $e ) {
            wc_get_logger()->warning(
                'WWPP role-automation sweep failed. See Action Scheduler logs for details.',
                array( 'source' => self::AS_GROUP )
            );
            throw $e;
        }
    }

    /**
     * Action Scheduler callback for a single sweep chunk.
     *
     * Receives a batch of up to `SWEEP_CHUNK_SIZE` user IDs and fans out a
     * per-user evaluation async action for each. The per-chunk fan-out
     * isolates each batch on its own AS tick so the worker never has to
     * insert more than `SWEEP_CHUNK_SIZE` AS rows in a single tick.
     *
     * @since 2.1.0
     *
     * @param array  $user_ids     Chunk of user IDs from the parent sweep.
     * @param string $sweep_run_id Sweep-run identifier from `handle_sweep()`; forwarded as
     *                             `trigger_ref` so the audit row is attributable to a specific
     *                             sweep run. Defaults to the empty string for backwards
     *                             compatibility with chunk actions enqueued by older versions.
     *
     * @return void
     */
    public function handle_sweep_chunk( array $user_ids, string $sweep_run_id = '' ): void {
        foreach ( $user_ids as $user_id ) {
            $this->enqueue_user_evaluation( (int) $user_id, self::TRIGGER_SCHEDULED, $sweep_run_id );
        }
    }

    /**
     * Action Scheduler callback for per-user evaluation.
     *
     * Loads the `WP_User`, calls `Evaluator::decide()`, and hands the result
     * to `Action_Dispatcher::dispatch()`. Skips silently when the user no
     * longer exists.
     *
     * @since 2.1.0
     *
     * @param int    $user_id      The user to evaluate.
     * @param string $trigger_type Trigger type passed by the caller.
     * @param string $trigger_ref  Trigger reference passed by the caller.
     *
     * @return void
     */
    public function handle_evaluate_user( int $user_id, string $trigger_type, string $trigger_ref ): void {
        $user = get_userdata( $user_id );

        if ( ! $user instanceof WP_User ) {
            return;
        }

        $from_role = $this->role_helper->current_wholesale_role( $user_id ) ?? '';
        $decision  = $this->evaluator->decide( $user );

        $this->dispatcher->dispatch( $user_id, $decision, $from_role, $trigger_type, $trigger_ref );
    }

    /**
     * Build the role-list for the sweep `get_users` query.
     *
     * Collects every wholesale role mentioned by an active rule's `scope_role_key`
     * and adds the retail `customer` role only when at least one rule has
     * `scope_type === 'any_non_wholesale'`. Returns a deduplicated list.
     *
     * @since 2.1.0
     *
     * @param array $rules Rule arrays from `Rules_Repository::all()`.
     *
     * @return array List of unique role keys for the `get_users` `role__in` query.
     */
    private function derive_sweep_role_keys( array $rules ): array {
        $role_keys                  = array();
        $has_any_non_wholesale_rule = false;

        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            if ( isset( $rule['enabled'] ) && false === $rule['enabled'] ) {
                continue;
            }

            $scope_type = isset( $rule['scope_type'] ) ? (string) $rule['scope_type'] : '';

            if ( 'any_non_wholesale' === $scope_type ) {
                $has_any_non_wholesale_rule = true;
                continue;
            }

            if ( 'wholesale_role' === $scope_type && ! empty( $rule['scope_role_key'] ) ) {
                $role_keys[] = (string) $rule['scope_role_key'];
            }
        }

        if ( $has_any_non_wholesale_rule ) {
            $role_keys[] = 'customer';
        }

        return array_values( array_unique( $role_keys ) );
    }
}
