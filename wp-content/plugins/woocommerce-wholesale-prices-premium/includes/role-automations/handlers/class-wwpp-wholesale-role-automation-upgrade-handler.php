<?php
/**
 * Wholesale Role Automation Upgrade Handler.
 *
 * Concrete `Transition_Handler` for the `upgrade` direction. Delegates the
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
 * Class WWPP_Wholesale_Role_Automation_Upgrade_Handler.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Upgrade_Handler extends WWPP_Wholesale_Role_Automation_Transition_Handler {

    /**
     * Direction label for the upgrade transition.
     *
     * @since 2.1.0
     *
     * @return string Always `upgrade`.
     */
    protected function direction(): string {
        return 'upgrade';
    }

    /**
     * Email action name fired after a successful upgrade.
     *
     * @since 2.1.0
     *
     * @return string Always `wwpp_wholesale_role_automation_upgraded`.
     */
    protected function email_action(): string {
        return 'wwpp_wholesale_role_automation_upgraded';
    }
}
