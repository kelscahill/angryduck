<?php
/**
 * Wholesale Role Automation Evaluator.
 *
 * Decides per-user role transitions by walking applicable rules in priority
 * order, computing the configured metric, and returning the winning action
 * shape. Owns the priority resolution and pause-skip semantics.
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
 * Class WWPP_Wholesale_Role_Automation_Evaluator.
 *
 * Returns one of `upgrade`, `downgrade`, or `no_op`. At most one winning rule
 * applies per evaluation cycle.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Evaluator {

    /**
     * Rules repository collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Rules_Repository
     */
    private $rules_repository;

    /**
     * Metric provider collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Metric_Provider
     */
    private $metric_provider;

    /**
     * Pause handler collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Pause_Handler
     */
    private $pause_handler;

    /**
     * Role helper collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Role_Helper
     */
    private $role_helper;

    /**
     * Window helper collaborator.
     *
     * Optional; lazily instantiated when not injected so that the existing
     * 4-argument bootstrap wiring continues to work while still allowing test
     * code to inject a mock.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Window_Helper|null
     */
    private $window_helper;

    /**
     * WWPP_Wholesale_Role_Automation_Evaluator constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Rules_Repository   $rules_repository Rules repository instance.
     * @param WWPP_Wholesale_Role_Automation_Metric_Provider    $metric_provider  Metric provider instance.
     * @param WWPP_Wholesale_Role_Automation_Pause_Handler      $pause_handler    Pause handler instance.
     * @param WWPP_Wholesale_Role_Automation_Role_Helper        $role_helper      Role helper instance.
     * @param WWPP_Wholesale_Role_Automation_Window_Helper|null $window_helper    Optional window helper; lazily constructed when null.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repository,
        WWPP_Wholesale_Role_Automation_Metric_Provider $metric_provider,
        WWPP_Wholesale_Role_Automation_Pause_Handler $pause_handler,
        WWPP_Wholesale_Role_Automation_Role_Helper $role_helper,
        ?WWPP_Wholesale_Role_Automation_Window_Helper $window_helper = null
    ) {
        $this->rules_repository = $rules_repository;
        $this->metric_provider  = $metric_provider;
        $this->pause_handler    = $pause_handler;
        $this->role_helper      = $role_helper;
        $this->window_helper    = $window_helper;
    }

    /**
     * Decide what action to take for the supplied user.
     *
     * Walks `Rules_Repository::applicable_for()` in canonical sort order,
     * skipping paused rules, computing each rule's metric, and returning the
     * first winning decision. A rule whose target role equals the user's
     * current role (or whose target is otherwise unusable) is *skipped* via
     * `continue` so a lower-priority rule still has a chance to win. The
     * `no_op` shape is returned only when the user is paused or when the
     * loop exhausts every candidate without producing a decision.
     *
     * @since 2.1.0
     *
     * @param WP_User $user The user to evaluate.
     *
     * @return array Decision shape: `{ action: string, target_role?: string, rule_id?: string }`.
     */
    public function decide( WP_User $user ): array {
        if ( $this->pause_handler->is_user_paused( $user->ID ) ) {
            return array( 'action' => 'no_op' );
        }

        $current_role = $this->role_helper->current_wholesale_role( $user->ID );
        $candidates   = $this->rules_repository->applicable_for( $user );

        foreach ( $candidates as $rule ) {
            // Defer to Pause_Handler instead of the rule's `enabled` flag — keeps pause
            // semantics centralized so future modes (TTL, scoped) only touch Pause_Handler.
            if ( $this->pause_handler->is_rule_paused( $rule['id'] ) ) {
                continue;
            }

            $metric = $this->compute_metric( $user, $rule );
            if ( null === $metric ) {
                continue;
            }
            $threshold = (float) ( $rule['threshold'] ?? 0 );

            if ( $metric >= $threshold ) {
                $target = $rule['upgrade_to_role_key'] ?? null;
                if ( null === $target || $current_role === $target ) {
                    continue;
                }
                if ( ! $this->is_valid_target( $target ) ) {
                    continue;
                }
                return array(
                    'action'      => 'upgrade',
                    'target_role' => $target,
                    'rule_id'     => $rule['id'],
                );
            }

            // Threshold not met: consider downgrade only when the user is in this rule's scope role.
            $scope_type     = $rule['scope_type'] ?? '';
            $scope_role_key = $rule['scope_role_key'] ?? '';

            if ( 'wholesale_role' === $scope_type && $current_role === $scope_role_key ) {
                $target = $rule['downgrade_to_role_key'] ?? null;
                if ( null === $target || $current_role === $target ) {
                    continue;
                }
                if ( ! $this->is_valid_target( $target ) ) {
                    continue;
                }
                return array(
                    'action'      => 'downgrade',
                    'target_role' => $target,
                    'rule_id'     => $rule['id'],
                );
            }
        }

        return array( 'action' => 'no_op' );
    }

    /**
     * Compute the metric value for a rule against a user.
     *
     * Routes to the lifetime or windowed `Metric_Provider` method depending on
     * the rule's `window_type`. Window bounds for non-lifetime windows are
     * resolved through `Window_Helper::bounds_for()`. Returns `null` when the
     * rule carries a malformed window descriptor that `bounds_for()` rejects;
     * callers treat that as "skip this rule" so a single bad entry in the
     * options table can't abort the whole evaluation pass.
     *
     * @since 2.1.0
     *
     * @param WP_User $user The user being evaluated.
     * @param array   $rule The rule whose metric is being computed.
     *
     * @return float|null The computed metric value (order counts cast to float for uniform
     *                    comparison), or `null` when the windowed-bounds resolution rejects
     *                    the rule's `window_type` / `window_days` pair.
     */
    private function compute_metric( WP_User $user, array $rule ): ?float {
        $metric      = $rule['metric'] ?? '';
        $window_type = $rule['window_type'] ?? 'lifetime';

        if ( 'lifetime' === $window_type ) {
            if ( 'order_count' === $metric ) {
                return (float) $this->metric_provider->order_count_lifetime( $user->ID );
            }
            return $this->metric_provider->total_spent_lifetime( $user->ID );
        }

        try {
            $window_days = isset( $rule['window_days'] ) ? (int) $rule['window_days'] : null;
            $bounds      = $this->window_helper()->bounds_for( $window_type, $window_days );
        } catch ( InvalidArgumentException $e ) {
            return null;
        }

        $from_ts = (int) ( $bounds['from_ts'] ?? 0 );
        $to_ts   = (int) ( $bounds['to_ts'] ?? 0 );

        if ( 'order_count' === $metric ) {
            return (float) $this->metric_provider->order_count_window( $user->ID, $from_ts, $to_ts );
        }

        return $this->metric_provider->total_spent_window( $user->ID, $from_ts, $to_ts );
    }

    /**
     * Check whether a target role key is valid for assignment.
     *
     * `customer` is allowed as a downgrade target (revoke wholesale entirely);
     * `Role_Helper::is_registered_wholesale_role()` returns false for it, so
     * we accept it explicitly here.
     *
     * @since 2.1.0
     *
     * @param string $target_role The role key being targeted.
     *
     * @return bool True when the target resolves to an allowed role.
     */
    private function is_valid_target( string $target_role ): bool {
        if ( 'customer' === $target_role ) {
            return true;
        }
        return $this->role_helper->is_registered_wholesale_role( $target_role );
    }

    /**
     * Resolve the Window_Helper instance, lazily constructing one when needed.
     *
     * @since 2.1.0
     *
     * @return WWPP_Wholesale_Role_Automation_Window_Helper The window helper instance.
     */
    private function window_helper(): WWPP_Wholesale_Role_Automation_Window_Helper {
        if ( null === $this->window_helper ) {
            $this->window_helper = new WWPP_Wholesale_Role_Automation_Window_Helper();
        }
        return $this->window_helper;
    }
}
