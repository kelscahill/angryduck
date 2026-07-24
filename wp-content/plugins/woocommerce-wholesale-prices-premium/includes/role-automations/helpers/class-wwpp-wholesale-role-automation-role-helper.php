<?php
/**
 * Wholesale Role Automation Role Helper.
 *
 * Stateless helper that centralizes safe wholesale role manipulation. Owns the
 * "swap" semantics that preserve `customer` / `subscriber` (never via
 * `wp_update_user( [ 'role' => ... ] )`) and the "current wholesale role"
 * resolution used by the Evaluator.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Helpers
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Role_Helper.
 *
 * Stateless utility consumed by transition handlers, the evaluator, and the My
 * Account notice.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Role_Helper {

    /**
     * Safely swap a user's wholesale role.
     *
     * Removes `$from_role` and adds `$to_role` while preserving non-wholesale
     * roles such as `customer` and `subscriber`. Never uses `wp_update_user`
     * because that wipes all secondary roles.
     *
     * @since 2.1.0
     *
     * @param int    $user_id   The user ID whose role is being swapped.
     * @param string $from_role The wholesale role key being removed (may be empty for retail-to-wholesale).
     * @param string $to_role   The wholesale role key being added. Caller MUST pass a non-empty,
     *                          registered wholesale-role key (validate via
     *                          `is_registered_wholesale_role()` first). Passing an empty string
     *                          silently removes `$from_role` without replacement and without a log entry.
     *
     * @return void
     */
    public function swap( int $user_id, string $from_role, string $to_role ): void {
        if ( $from_role === $to_role ) {
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            wc_get_logger()->warning(
                sprintf(
                    /* translators: %d: user ID that could not be resolved. */
                    __( 'Role_Helper::swap called for non-existent user %d', 'woocommerce-wholesale-prices-premium' ),
                    $user_id
                ),
                array( 'source' => 'wwpp-wholesale-role-automation' )
            );
            return;
        }

        $user->remove_role( $from_role );
        $user->add_role( $to_role );
    }

    /**
     * Resolve the current wholesale role for a user.
     *
     * Reads the user's roles, intersects with the registered wholesale roles,
     * and returns the first match in registration order. Logs a warning to the
     * `wwpp-wholesale-role-automation` log source if the user holds multiple
     * wholesale roles. Returns `null` without logging when the user ID does not
     * resolve, so this stays safe to call from evaluator hot paths.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user ID to inspect.
     *
     * @return string|null The role key, or null if the user holds no wholesale role.
     */
    public function current_wholesale_role( int $user_id ): ?string {
        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            return null;
        }

        $registered_keys = array_keys( $this->get_registered_wholesale_roles() );
        $user_roles      = $user->roles;

        $matches = array();
        foreach ( $registered_keys as $role_key ) {
            if ( in_array( $role_key, $user_roles, true ) ) {
                $matches[] = $role_key;
            }
        }

        if ( empty( $matches ) ) {
            return null;
        }

        if ( count( $matches ) > 1 ) {
            wc_get_logger()->warning(
                sprintf(
                    /* translators: 1: user ID. 2: comma-separated list of matched wholesale role keys. 3: role key being returned. */
                    __( 'Role_Helper::current_wholesale_role found multiple wholesale roles for user %1$d: %2$s — returning %3$s', 'woocommerce-wholesale-prices-premium' ),
                    $user_id,
                    implode( ', ', $matches ),
                    $matches[0]
                ),
                array( 'source' => 'wwpp-wholesale-role-automation' )
            );
        }

        return $matches[0];
    }

    /**
     * Resolve a wholesale role key to its human-readable label.
     *
     * Used for audit-table pre-rendered labels and email/notice copy. Logs a
     * warning to the `wwpp-wholesale-role-automation` log source when no label
     * is registered for the key and falls back to returning the key itself.
     *
     * @since 2.1.0
     *
     * @param string $role_key The wholesale role key to resolve.
     *
     * @return string The resolved label, or the role key itself if unresolved.
     */
    public function role_label( string $role_key ): string {
        $registered = $this->get_registered_wholesale_roles();

        if ( isset( $registered[ $role_key ]['roleName'] ) && is_string( $registered[ $role_key ]['roleName'] ) && '' !== $registered[ $role_key ]['roleName'] ) {
            return $registered[ $role_key ]['roleName'];
        }

        wc_get_logger()->warning(
            sprintf(
                /* translators: %s: wholesale role key whose label could not be resolved. */
                __( 'Role_Helper::role_label could not resolve label for role key %s — falling back to key', 'woocommerce-wholesale-prices-premium' ),
                $role_key
            ),
            array( 'source' => 'wwpp-wholesale-role-automation' )
        );

        return $role_key;
    }

    /**
     * Determine whether a role key is a registered wholesale role.
     *
     * @since 2.1.0
     *
     * @param string $role_key The role key to check.
     *
     * @return bool True when the key is in the registered wholesale roles list.
     */
    public function is_registered_wholesale_role( string $role_key ): bool {
        return array_key_exists( $role_key, $this->get_registered_wholesale_roles() );
    }

    /**
     * Fetch the registered wholesale roles map keyed by role key.
     *
     * Centralises the parent-class lookup used by `current_wholesale_role()`,
     * `role_label()`, and `is_registered_wholesale_role()`. We intentionally
     * read from `WWP_Wholesale_Roles` (the parent), not the WWPP subclass —
     * the subclass's `get_all_registered_wholesale_roles()` calls `wp_die()`
     * under `DOING_AJAX`, which we must not trigger from background workers.
     * Guards against a non-array return value in case a third-party
     * `wwp_registered_wholesale_roles` filter mutates the shape.
     *
     * @since 2.1.0
     *
     * @return array Map of role key => role data array (`roleName`, ...).
     */
    private function get_registered_wholesale_roles(): array {
        $registered = WWP_Wholesale_Roles::getInstance()->getAllRegisteredWholesaleRoles();

        return is_array( $registered ) ? $registered : array();
    }
}
