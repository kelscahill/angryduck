<?php
/**
 * Wholesale Role Automation Transition Handler (abstract base).
 *
 * Encapsulates the canonical order of operations for any user role transition
 * — role swap, audit append (before email), email action dispatch, and
 * recent-change transient. Concrete subclasses provide only their direction
 * label and email action name.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Handlers
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Transition_Handler.
 *
 * Abstract base for `Upgrade_Handler` and `Downgrade_Handler`.
 *
 * @since 2.1.0
 */
abstract class WWPP_Wholesale_Role_Automation_Transition_Handler {

    /**
     * Transient key prefix for the recent-change My Account notice payload.
     *
     * Shared with the notice renderer that reads and deletes the transient.
     *
     * @since 2.1.0
     *
     * @var string
     */
    public const TRANSIENT_KEY_PREFIX = 'wwpp_wholesale_role_automation_recent_change_';

    /**
     * Role helper collaborator.
     *
     * @since  2.1.0
     * @access protected
     * @var    WWPP_Wholesale_Role_Automation_Role_Helper
     */
    protected $role_helper;

    /**
     * Audit log collaborator.
     *
     * @since  2.1.0
     * @access protected
     * @var    WWPP_Wholesale_Role_Automation_Audit_Log
     */
    protected $audit_log;

    /**
     * WWPP_Wholesale_Role_Automation_Transition_Handler constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Role_Helper $role_helper Role helper instance.
     * @param WWPP_Wholesale_Role_Automation_Audit_Log   $audit_log   Audit log instance.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Role_Helper $role_helper,
        WWPP_Wholesale_Role_Automation_Audit_Log $audit_log
    ) {
        $this->role_helper = $role_helper;
        $this->audit_log   = $audit_log;
    }

    /**
     * Direction label for this transition.
     *
     * Returns one of `upgrade` or `downgrade`. Used as the `direction` column
     * value in audit rows.
     *
     * @since 2.1.0
     *
     * @return string The direction label.
     */
    abstract protected function direction(): string;

    /**
     * Email action name for this transition.
     *
     * Returns the `do_action` name registered with `woocommerce_email_actions`
     * so WC's email pipeline picks it up.
     *
     * @since 2.1.0
     *
     * @return string The email action name.
     */
    abstract protected function email_action(): string;

    /**
     * Apply the role transition.
     *
     * Order of operations (matters for correctness):
     *  1. `Role_Helper::swap()` to change the role while preserving customer/subscriber.
     *  2. `Audit_Log::append()` BEFORE the email so the log is canonical even when email fails.
     *  3. `do_action( $this->email_action(), ... )` to flow through WC's email pipeline.
     *  4. Set transient `self::TRANSIENT_KEY_PREFIX . $user_id` for the My Account notice.
     *
     * @since 2.1.0
     *
     * @param int    $user_id      The user being transitioned.
     * @param string $from_role    The wholesale role key being removed, or the empty string for
     *                             retail-to-wholesale transitions. Callers MUST NOT pass a preserved
     *                             non-wholesale role (e.g. `customer`, `subscriber`): `Role_Helper::swap()`
     *                             would remove it, and the post-condition guard below would treat its
     *                             continued presence as a failed swap. The Action_Dispatcher routes
     *                             only well-formed wholesale-to-wholesale or retail-to-wholesale
     *                             decisions to `apply()`, so this is a caller contract, not a runtime
     *                             check.
     * @param string $to_role      The wholesale role key being added.
     * @param string $rule_id      The UUID of the rule that triggered this transition.
     * @param string $trigger_type One of `order_completed`, `refund`, `cancelled`, `scheduled`. The
     *                             `admin` value is reserved for `Pause_Handler` rows and is not a
     *                             valid input here.
     * @param string $trigger_ref  Order ID for the four transition triggers (or empty for `scheduled`);
     *                             capped at 32 chars by the audit-table schema. The brief's "actor
     *                             user ID" shape is the `admin` shape, which this handler does not
     *                             accept.
     *
     * @return bool True on success; false when the role swap did not take
     *              effect (e.g. `$user_id` no longer resolves to a real user,
     *              the target role is missing post-swap, or the source role
     *              was not removed). In any failure case no audit row is
     *              written, no email fires, and no transient is set.
     *
     * @throws InvalidArgumentException Propagated from `Audit_Log::append()` when `trigger_type` or the
     *                                  resolved direction is not in the allowed enum. Email is not fired
     *                                  and the transient is not set.
     * @throws RuntimeException         Propagated from `Audit_Log::append()` when the audit-table insert
     *                                  fails. Same suppression of downstream side effects as above.
     */
    public function apply( int $user_id, string $from_role, string $to_role, string $rule_id, string $trigger_type, string $trigger_ref = '' ): bool {
        $this->role_helper->swap( $user_id, $from_role, $to_role );

        // `Role_Helper::swap()` returns void and `add_role`/`remove_role` are
        // independent — one can succeed while the other fails, leaving the
        // user with both roles. Verify the swap actually took effect before
        // writing the audit row so the log can't claim a transition that
        // didn't happen.
        $user_after = get_userdata( $user_id );
        if ( ! $user_after instanceof WP_User ) {
            return false;
        }
        if ( ! in_array( $to_role, $user_after->roles, true ) ) {
            return false;
        }
        if ( '' !== $from_role && $from_role !== $to_role && in_array( $from_role, $user_after->roles, true ) ) {
            return false;
        }

        // Audit row BEFORE the email action so the log stays canonical when
        // email delivery fails.
        $this->audit_log->append(
            array(
                'user_id'      => $user_id,
                'rule_id'      => $rule_id,
                'from_role'    => $from_role,
                'to_role'      => $to_role,
                'direction'    => $this->direction(),
                'trigger_type' => $trigger_type,
                'trigger_ref'  => $trigger_ref,
            )
        );

        do_action( $this->email_action(), $user_id, $rule_id, $from_role, $to_role );

        set_transient(
            self::TRANSIENT_KEY_PREFIX . $user_id,
            array(
                'direction' => $this->direction(),
                'from_role' => $from_role,
                'to_role'   => $to_role,
                'rule_id'   => $rule_id,
            ),
            30 * DAY_IN_SECONDS
        );

        return true;
    }
}
