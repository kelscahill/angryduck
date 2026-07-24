<?php
/**
 * Wholesale Role Automation Pause Handler.
 *
 * Owns the per-user lock (admin profile checkbox) and per-rule pause (rule
 * `enabled` toggle). Distinct shape from the transition handlers — no role
 * swap, no email; mutations append rows to the audit log with
 * `direction = pause | unpause`.
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
 * Class WWPP_Wholesale_Role_Automation_Pause_Handler.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Pause_Handler {

    /**
     * Rules repository collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Rules_Repository
     */
    private $rules_repository;

    /**
     * Audit log collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Audit_Log
     */
    private $audit_log;

    /**
     * WWPP_Wholesale_Role_Automation_Pause_Handler constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repository Rules repository instance.
     * @param WWPP_Wholesale_Role_Automation_Audit_Log        $audit_log        Audit log instance.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repository,
        WWPP_Wholesale_Role_Automation_Audit_Log $audit_log
    ) {
        $this->rules_repository = $rules_repository;
        $this->audit_log        = $audit_log;
    }

    /**
     * Lock a user from automation.
     *
     * Sets the user meta to `'yes'` and appends an audit row with
     * `direction = pause`, `trigger_type = admin`, `trigger_ref = $actor_user_id`.
     *
     * @since 2.1.0
     *
     * @param int $user_id       The user being locked.
     * @param int $actor_user_id The admin user performing the action.
     *
     * @return void
     */
    public function lock_user( int $user_id, int $actor_user_id ): void {
        update_user_meta( $user_id, WWPP_USER_META_WHOLESALE_ROLE_AUTOMATION_LOCKED, 'yes' );

        $this->append_user_audit_row( $user_id, $actor_user_id, 'pause' );
    }

    /**
     * Unlock a user.
     *
     * Deletes the lock meta and appends an audit row with
     * `direction = unpause`.
     *
     * @since 2.1.0
     *
     * @param int $user_id       The user being unlocked.
     * @param int $actor_user_id The admin user performing the action.
     *
     * @return void
     */
    public function unlock_user( int $user_id, int $actor_user_id ): void {
        delete_user_meta( $user_id, WWPP_USER_META_WHOLESALE_ROLE_AUTOMATION_LOCKED );

        $this->append_user_audit_row( $user_id, $actor_user_id, 'unpause' );
    }

    /**
     * Determine whether a user is currently paused.
     *
     * Read-only helper consulted by `Evaluator::decide()` to skip paused
     * users before rule resolution starts.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user to inspect.
     *
     * @return bool True when the user is paused.
     */
    public function is_user_paused( int $user_id ): bool {
        return 'yes' === get_user_meta( $user_id, WWPP_USER_META_WHOLESALE_ROLE_AUTOMATION_LOCKED, true );
    }

    /**
     * Pause a rule.
     *
     * Delegates to `Rules_Repository::set_enabled( $rule_id, false )` and
     * appends an audit row with `direction = pause`. Throws when the rule
     * does not exist so the audit log never records a state change that did
     * not actually happen (e.g. a race where another admin deleted the rule
     * before this call landed).
     *
     * @since 2.1.0
     *
     * @param string $rule_id       The rule UUID.
     * @param int    $actor_user_id The admin user performing the action.
     *
     * @return void
     * @throws RuntimeException When the rule does not exist.
     */
    public function pause_rule( string $rule_id, int $actor_user_id ): void {
        if ( ! $this->rules_repository->set_enabled( $rule_id, false ) ) {
            $this->throw_rule_not_found( $rule_id );
        }

        $this->append_rule_audit_row( $rule_id, $actor_user_id, 'pause' );
    }

    /**
     * Unpause a rule.
     *
     * Delegates to `Rules_Repository::set_enabled( $rule_id, true )` and
     * appends an audit row with `direction = unpause`. Throws when the rule
     * does not exist so the audit log never records a state change that did
     * not actually happen (e.g. a race where another admin deleted the rule
     * before this call landed).
     *
     * @since 2.1.0
     *
     * @param string $rule_id       The rule UUID.
     * @param int    $actor_user_id The admin user performing the action.
     *
     * @return void
     * @throws RuntimeException When the rule does not exist.
     */
    public function unpause_rule( string $rule_id, int $actor_user_id ): void {
        if ( ! $this->rules_repository->set_enabled( $rule_id, true ) ) {
            $this->throw_rule_not_found( $rule_id );
        }

        $this->append_rule_audit_row( $rule_id, $actor_user_id, 'unpause' );
    }

    /**
     * Determine whether a rule is currently paused.
     *
     * Read-only helper used by `Evaluator::decide()` to skip disabled rules.
     * Returns `false` for missing rules; the Evaluator skips missing rules
     * independently.
     *
     * @since 2.1.0
     *
     * @param string $rule_id The rule UUID.
     *
     * @return bool True when the rule is paused (its `enabled` flag is false).
     */
    public function is_rule_paused( string $rule_id ): bool {
        $rule = $this->rules_repository->find( $rule_id );

        if ( null === $rule ) {
            return false;
        }

        return empty( $rule['enabled'] );
    }

    /**
     * Throw a "rule not found" RuntimeException for the given UUID.
     *
     * Shared by `pause_rule` and `unpause_rule` so the exception construction
     * and its PHPCS-ignore justification live in one place.
     *
     * @since 2.1.0
     *
     * @param string $rule_id The rule UUID that could not be located.
     *
     * @return void
     * @throws RuntimeException Always — this helper exists only to raise.
     */
    private function throw_rule_not_found( string $rule_id ): void {
        // Exception messages are plain-text diagnostics for logs, CLI, and JSON responses;
        // HTML escaping is the responsibility of the presentation layer that renders the
        // message, not of this domain-layer throw.
        throw new RuntimeException( 'Rule not found: ' . $rule_id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    /**
     * Append a per-user pause/unpause audit row.
     *
     * Builds the row shape shared by `lock_user` and `unlock_user`:
     * null `from_role`/`to_role`, empty `rule_id`, `trigger_type = admin`.
     *
     * @since 2.1.0
     *
     * @param int    $user_id       The user being paused or unpaused.
     * @param int    $actor_user_id The admin user performing the action.
     * @param string $direction     Either `pause` or `unpause`.
     *
     * @return void
     */
    private function append_user_audit_row( int $user_id, int $actor_user_id, string $direction ): void {
        $this->audit_log->append(
            array(
                'user_id'      => $user_id,
                'rule_id'      => '',
                'from_role'    => null,
                'to_role'      => null,
                'direction'    => $direction,
                'trigger_type' => 'admin',
                'trigger_ref'  => (string) $actor_user_id,
            )
        );
    }

    /**
     * Append a per-rule pause/unpause audit row.
     *
     * Builds the row shape shared by `pause_rule` and `unpause_rule`:
     * null `from_role`/`to_role`, `user_id = 0`, `trigger_type = admin`.
     *
     * @since 2.1.0
     *
     * @param string $rule_id       The rule UUID being paused or unpaused.
     * @param int    $actor_user_id The admin user performing the action.
     * @param string $direction     Either `pause` or `unpause`.
     *
     * @return void
     */
    private function append_rule_audit_row( string $rule_id, int $actor_user_id, string $direction ): void {
        $this->audit_log->append(
            array(
                'user_id'      => 0,
                'rule_id'      => $rule_id,
                'from_role'    => null,
                'to_role'      => null,
                'direction'    => $direction,
                'trigger_type' => 'admin',
                'trigger_ref'  => (string) $actor_user_id,
            )
        );
    }
}
