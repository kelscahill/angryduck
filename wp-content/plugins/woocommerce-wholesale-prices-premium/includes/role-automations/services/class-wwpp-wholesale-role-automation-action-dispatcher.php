<?php
/**
 * Wholesale Role Automation Action Dispatcher.
 *
 * Routes a decision returned by `Evaluator::decide()` to the correct
 * transition handler. Single place that knows about both decision shapes and
 * the audit log; writes diagnostic rows when a rule's target role no longer
 * exists.
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
 * Class WWPP_Wholesale_Role_Automation_Action_Dispatcher.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Action_Dispatcher {

    /**
     * Upgrade handler collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Upgrade_Handler
     */
    private $upgrade_handler;

    /**
     * Downgrade handler collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Downgrade_Handler
     */
    private $downgrade_handler;

    /**
     * Audit log collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Audit_Log
     */
    private $audit_log;

    /**
     * WWPP_Wholesale_Role_Automation_Action_Dispatcher constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Upgrade_Handler   $upgrade_handler   Upgrade handler instance.
     * @param WWPP_Wholesale_Role_Automation_Downgrade_Handler $downgrade_handler Downgrade handler instance.
     * @param WWPP_Wholesale_Role_Automation_Audit_Log         $audit_log         Audit log instance.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Upgrade_Handler $upgrade_handler,
        WWPP_Wholesale_Role_Automation_Downgrade_Handler $downgrade_handler,
        WWPP_Wholesale_Role_Automation_Audit_Log $audit_log
    ) {
        $this->upgrade_handler   = $upgrade_handler;
        $this->downgrade_handler = $downgrade_handler;
        $this->audit_log         = $audit_log;
    }

    /**
     * Dispatch the decision returned by the Evaluator.
     *
     * `decision['action'] === 'upgrade'` routes to the Upgrade Handler;
     * `'downgrade'` routes to the Downgrade Handler; `'no_op'` returns true
     * immediately with no side-effects. Pause is not routed through here.
     *
     * @since 2.1.0
     *
     * @param int    $user_id      The user being acted upon.
     * @param array  $decision     Decision returned by `Evaluator::decide()`.
     * @param string $from_role    The user's current wholesale role (or empty string for retail).
     * @param string $trigger_type One of `order_completed`, `refund`, `cancelled`, `scheduled`, `admin`.
     * @param string $trigger_ref  Compact identifier (order ID or actor user ID);
     *                             capped at 32 chars by the audit-table schema.
     *
     * @return bool True on a successful upgrade/downgrade, `no_op`, or invalid-target diagnostic audit row;
     *              false on unrecognized action or when the handler itself returns false.
     * @throws Exception Propagates exceptions from `Transition_Handler::apply()` or `Audit_Log::append()`
     *                   so Action Scheduler can retry.
     */
    public function dispatch( int $user_id, array $decision, string $from_role, string $trigger_type, string $trigger_ref = '' ): bool {
        // Diagnostic audit row: Evaluator skipped a rule because its target role
        // no longer resolves. Recorded with `to_role => null` so the audit UI
        // can render it as an "invalid target" entry. This branch is only
        // reached when a future Evaluator change passes skip metadata through;
        // the current Evaluator collapses skips into `no_op`.
        if ( isset( $decision['skipped_rule_id'] ) && isset( $decision['reason'] ) && 'invalid_target' === $decision['reason'] ) {
            $this->audit_log->append(
                array(
                    'user_id'      => $user_id,
                    'rule_id'      => $decision['skipped_rule_id'],
                    'from_role'    => $from_role,
                    'to_role'      => null,
                    'direction'    => $decision['attempted_direction'],
                    'trigger_type' => $trigger_type,
                    'trigger_ref'  => $trigger_ref,
                )
            );
            return true;
        }

        $action = $decision['action'] ?? 'no_op';

        if ( 'no_op' === $action ) {
            return true;
        }

        if ( 'upgrade' === $action ) {
            return $this->upgrade_handler->apply(
                $user_id,
                $from_role,
                $decision['target_role'],
                $decision['rule_id'],
                $trigger_type,
                $trigger_ref
            );
        }

        if ( 'downgrade' === $action ) {
            return $this->downgrade_handler->apply(
                $user_id,
                $from_role,
                $decision['target_role'],
                $decision['rule_id'],
                $trigger_type,
                $trigger_ref
            );
        }

        wc_get_logger()->warning(
            sprintf(
                /* translators: 1: unrecognized decision action key. 2: user ID. */
                __( 'Action_Dispatcher: unrecognized action "%1$s" for user %2$d', 'woocommerce-wholesale-prices-premium' ),
                $action,
                $user_id
            ),
            array( 'source' => 'wwpp-wholesale-role-automation' )
        );

        return false;
    }
}
