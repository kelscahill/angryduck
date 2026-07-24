<?php
/**
 * Wholesale Role Automation Downgrade Handler.
 *
 * Concrete `Transition_Handler` for the `downgrade` direction. Delegates the
 * audit-before-email flow to the abstract base; carries only the direction
 * label and the email action name.
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
 * Class WWPP_Wholesale_Role_Automation_Downgrade_Handler.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Downgrade_Handler extends WWPP_Wholesale_Role_Automation_Transition_Handler {

    /**
     * Direction label for the downgrade transition.
     *
     * @since 2.1.0
     *
     * @return string Always `downgrade`.
     */
    protected function direction(): string {
        return 'downgrade';
    }

    /**
     * Email action name fired after a successful downgrade.
     *
     * @since 2.1.0
     *
     * @return string Always `wwpp_wholesale_role_automation_downgraded`.
     */
    protected function email_action(): string {
        return 'wwpp_wholesale_role_automation_downgraded';
    }
}
