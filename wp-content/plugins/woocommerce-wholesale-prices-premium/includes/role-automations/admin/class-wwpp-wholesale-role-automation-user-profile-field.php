<?php
/**
 * Wholesale Role Automation User Profile Lock Field.
 *
 * Renders and persists the per-user "lock from automation" checkbox on the WP
 * core user-edit screen via `show_user_profile` / `edit_user_profile`. The
 * actual state change is delegated to `Pause_Handler::lock_user()` /
 * `unlock_user()`.
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
 * Class WWPP_Wholesale_Role_Automation_User_Profile_Field.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_User_Profile_Field {

    /**
     * Pause handler collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Pause_Handler
     */
    private $pause_handler;

    /**
     * WWPP_Wholesale_Role_Automation_User_Profile_Field constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Pause_Handler $pause_handler Pause handler instance.
     */
    public function __construct( WWPP_Wholesale_Role_Automation_Pause_Handler $pause_handler ) {
        $this->pause_handler = $pause_handler;
    }

    /**
     * Render the lock checkbox on the user profile screen.
     *
     * Hooked on `show_user_profile` and `edit_user_profile`. Renders nothing
     * unless the viewer holds the `edit_users` admin capability AND can
     * `edit_user` for the target — so a non-admin viewing their own profile
     * never sees the toggle even though core grants them `edit_user` for
     * themselves. The field is wrapped in a dedicated nonce so the save
     * handler can verify intent independently of WordPress's own user-edit
     * nonce (defence in depth).
     *
     * @since 2.1.0
     *
     * @param WP_User $user The user being viewed/edited.
     *
     * @return void
     */
    public function render_lock_field( WP_User $user ): void {
        if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $is_locked = $this->pause_handler->is_user_paused( $user->ID );
        ?>
        <h2><?php esc_html_e( 'Wholesale role automation', 'woocommerce-wholesale-prices-premium' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wwpp_wholesale_role_automation_locked">
                        <?php esc_html_e( 'Exempt from automation', 'woocommerce-wholesale-prices-premium' ); ?>
                    </label>
                </th>
                <td>
                    <?php wp_nonce_field( 'wwpp_wholesale_role_automation_user_lock_' . $user->ID, 'wwpp_wholesale_role_automation_user_lock_nonce' ); ?>
                    <input
                        type="checkbox"
                        id="wwpp_wholesale_role_automation_locked"
                        name="wwpp_wholesale_role_automation_locked"
                        value="yes"
                        <?php checked( $is_locked ); ?>
                    />
                    <span class="description">
                        <?php esc_html_e( 'Exempt this user from automatic wholesale role upgrades and downgrades. Manual role changes still work.', 'woocommerce-wholesale-prices-premium' ); ?>
                    </span>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Persist the lock checkbox value when the user profile is saved.
     *
     * Hooked on `personal_options_update` and `edit_user_profile_update`.
     * Requires both the `edit_users` admin capability and `edit_user` on the
     * target — `edit_user` alone is granted by core for self-edits, so a
     * subscriber posting a forged form could otherwise lock their own
     * account. Delegates state mutations to `Pause_Handler::lock_user()` or
     * `unlock_user()` based on the submitted checkbox value — never writes
     * the user meta directly, so the audit row is always recorded with the
     * correct `trigger_ref`. The pre-call `is_user_paused()` check makes
     * repeated submits idempotent: no new audit row is appended when the
     * checkbox state is unchanged.
     *
     * @since 2.1.0
     *
     * @param int $user_id The user being edited.
     *
     * @return void
     */
    public function save_lock_field( int $user_id ): void {
        if ( ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $nonce = isset( $_POST['wwpp_wholesale_role_automation_user_lock_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['wwpp_wholesale_role_automation_user_lock_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, 'wwpp_wholesale_role_automation_user_lock_' . $user_id ) ) {
            return;
        }

        $checked   = ! empty( $_POST['wwpp_wholesale_role_automation_locked'] );
        $is_locked = $this->pause_handler->is_user_paused( $user_id );

        if ( $checked && ! $is_locked ) {
            $this->pause_handler->lock_user( $user_id, get_current_user_id() );
        } elseif ( ! $checked && $is_locked ) {
            $this->pause_handler->unlock_user( $user_id, get_current_user_id() );
        }
    }
}
