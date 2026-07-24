<?php
/**
 * Wholesale Role Automation My Account Notice.
 *
 * Renders a one-time banner on the WC My Account page when a user's role has
 * recently changed. Reads the transient written by
 * `Transition_Handler::apply()` and deletes it after rendering so the notice
 * appears only once per change.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Admin
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_My_Account_Notice.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_My_Account_Notice {

    /**
     * Role helper collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Role_Helper
     */
    private $role_helper;

    /**
     * WWPP_Wholesale_Role_Automation_My_Account_Notice constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Role_Helper $role_helper Role helper instance.
     */
    public function __construct( WWPP_Wholesale_Role_Automation_Role_Helper $role_helper ) {
        $this->role_helper = $role_helper;
    }

    /**
     * Render the role-change notice on the My Account page.
     *
     * Hooked on `woocommerce_before_account_navigation` (priority 10). Reads
     * the `wwpp_wholesale_role_automation_recent_change_{user_id}` transient,
     * renders a translatable banner, then deletes the transient so the notice
     * appears only once per change.
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function render_notice(): void {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        $transient_key = WWPP_Wholesale_Role_Automation_Transition_Handler::TRANSIENT_KEY_PREFIX . $user_id;
        $payload       = get_transient( $transient_key );
        if ( false === $payload || ! is_array( $payload ) ) {
            return;
        }

        $message = $this->compose_message( $payload, $user_id );
        $type    = ( 'upgrade' === ( $payload['direction'] ?? 'upgrade' ) ) ? 'success' : 'notice';

        wc_print_notice( $message, $type );

        delete_transient( $transient_key );
    }

    /**
     * Return the pending role-change message for headless consumers.
     *
     * Exposes the recent-change message for stores that have replaced the WC
     * My Account page with a headless equivalent. Reads the transient WITHOUT
     * deleting it — headless consumers are responsible for calling
     * `delete_transient( 'wwpp_wholesale_role_automation_recent_change_' . $user_id )`
     * once they have rendered the message on their side. Filterable via
     * `wwpp_wholesale_role_automation_recent_change_message`.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user to look up.
     *
     * @return string|null The pending message, or null when nothing is pending.
     */
    public function get_pending_message( int $user_id ): ?string {
        if ( $user_id <= 0 ) {
            return null;
        }

        $transient_key = WWPP_Wholesale_Role_Automation_Transition_Handler::TRANSIENT_KEY_PREFIX . $user_id;
        $payload       = get_transient( $transient_key );
        if ( false === $payload || ! is_array( $payload ) ) {
            return null;
        }

        return $this->compose_message( $payload, $user_id );
    }

    /**
     * Compose the role-change message from a transient payload.
     *
     * Resolves the new role label via `Role_Helper::role_label()`, picks the
     * upgrade vs. downgrade copy based on `$payload['direction']`, and applies
     * the `wwpp_wholesale_role_automation_recent_change_message` filter so
     * headless consumers can override the rendered string.
     *
     * @since 2.1.0
     *
     * @param array $payload The transient payload written by `Transition_Handler::apply()`.
     * @param int   $user_id The user the message is being composed for.
     *
     * @return string The filtered message ready for display.
     */
    private function compose_message( array $payload, int $user_id ): string {
        $raw_to_role   = $payload['to_role'] ?? '';
        $raw_direction = $payload['direction'] ?? 'upgrade';
        $new_label     = $this->role_helper->role_label( is_string( $raw_to_role ) ? $raw_to_role : '' );
        $direction     = is_string( $raw_direction ) ? $raw_direction : 'upgrade';

        if ( 'upgrade' === $direction ) {
            $message = sprintf(
                /* translators: %s: the new wholesale role label. */
                __( '🎉 Congratulations — you\'ve been upgraded to %s. Your new pricing is active on your next order.', 'woocommerce-wholesale-prices-premium' ),
                esc_html( $new_label )
            );
        } else {
            $message = sprintf(
                /* translators: %s: the new wholesale role label. */
                __( 'Your wholesale role has changed to %s. This affects future orders. Existing subscriptions are unchanged.', 'woocommerce-wholesale-prices-premium' ),
                esc_html( $new_label )
            );
        }

        return (string) apply_filters( 'wwpp_wholesale_role_automation_recent_change_message', $message, $payload, $user_id );
    }
}
