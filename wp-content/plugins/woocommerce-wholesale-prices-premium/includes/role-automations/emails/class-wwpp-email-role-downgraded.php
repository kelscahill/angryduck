<?php
/**
 * Wholesale Role Automation — Role Downgraded customer email.
 *
 * `WC_Email` subclass for the downgrade notification. Registered with WC's
 * email pipeline via the `woocommerce_email_classes` filter and triggered
 * via the `wwpp_wholesale_role_automation_downgraded` action whitelisted in
 * `woocommerce_email_actions`.
 *
 * Behaviour shared with `WWPP_Email_Role_Upgraded` lives on the
 * `WWPP_Email_Role_Transition_Base` abstract — this subclass only declares
 * the direction-specific identity (`id`, copy, template paths, hook).
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Emails
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/abstract-class-wwpp-email-role-transition-base.php';

/**
 * Class WWPP_Email_Role_Downgraded.
 *
 * @since 2.1.0
 */
class WWPP_Email_Role_Downgraded extends WWPP_Email_Role_Transition_Base {

    /**
     * WWPP_Email_Role_Downgraded constructor.
     *
     * Sets the `id`, `customer_email` flag, default subject / heading, and
     * the HTML / plain template paths, binds the
     * `wwpp_wholesale_role_automation_downgraded_notification` shadow action
     * (fired by `WC_Emails::send_transactional_email()`) to `trigger()`, then
     * defers to the parent `WC_Email::__construct()` for hook registration
     * and form-field initialization.
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->id             = WWPP_EMAIL_ID_ROLE_DOWNGRADED;
        $this->customer_email = true;
        $this->title          = __( 'Wholesale role downgrade', 'woocommerce-wholesale-prices-premium' );
        $this->description    = __( 'Sent to a customer when their wholesale role is automatically downgraded.', 'woocommerce-wholesale-prices-premium' );
        $this->template_html  = 'emails/role-downgraded.php';
        $this->template_plain = 'emails/plain/role-downgraded.php';
        $this->template_base  = WWPP_VIEWS_PATH;

        $this->placeholders = array(
            '{user_display_name}' => '',
            '{old_role_label}'    => '',
            '{new_role_label}'    => '',
            '{rule_label}'        => '',
        );

        // Trigger for this email. WC fires the `_notification` shadow action
        // from `WC_Emails::send_transactional_email()` for every action listed
        // in `woocommerce_email_actions`.
        add_action( 'wwpp_wholesale_role_automation_downgraded_notification', array( $this, 'trigger' ), 10, 4 );

        parent::__construct();
    }

    /**
     * Direction key identifying this transition email.
     *
     * @since 2.1.0
     *
     * @return string
     */
    public function direction(): string {
        return 'downgrade';
    }

    /**
     * Get the default email subject.
     *
     * @since 2.1.0
     *
     * @return string
     */
    public function get_default_subject() {
        return __( 'Your wholesale role has been updated on {site_title}', 'woocommerce-wholesale-prices-premium' );
    }

    /**
     * Get the default email heading.
     *
     * @since 2.1.0
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Your wholesale role has been updated', 'woocommerce-wholesale-prices-premium' );
    }

    /**
     * Default additional content shown beneath the main email body.
     *
     * @since 2.1.0
     *
     * @return string
     */
    public function get_default_additional_content() {
        return __( 'If you have any questions, please reply to this email.', 'woocommerce-wholesale-prices-premium' );
    }
}
