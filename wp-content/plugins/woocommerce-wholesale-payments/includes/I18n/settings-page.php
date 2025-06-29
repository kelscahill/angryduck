<?php
/**
 * Settings page translations.
 *
 * @package RymeraWebCo\WPay
 */

use RymeraWebCo\WPay\Helpers\WPay;

defined( 'ABSPATH' ) || exit;

$nav = array(
    array(
        'key'   => 'payment_plans',
        'label' => __( 'Payment Plans', 'woocommerce-wholesale-payments' ),
    ),
    array(
        'key'   => 'invoices',
        'label' => __( 'Invoices', 'woocommerce-wholesale-payments' ),
    ),
);

return array(
    'button'          => array(
        'connecting'        => __( 'Connecting...', 'woocommerce-wholesale-payments' ),
        'connectWithStripe' => __( 'Connect with Stripe', 'woocommerce-wholesale-payments' ),
        'createPaymentPlan' => __( 'Create a custom plan', 'woocommerce-wholesale-payments' ),
        'creating'          => __( 'Creating...', 'woocommerce-wholesale-payments' ),
        'ok'                => __( 'OK', 'woocommerce-wholesale-payments' ),
        'cancel'            => __( 'Cancel', 'woocommerce-wholesale-payments' ),
        'edit'              => __( 'Edit', 'woocommerce-wholesale-payments' ),
        'create'            => __( 'Create New Plan', 'woocommerce-wholesale-payments' ),
        'createBtnLabel'    => __( 'Create Plan', 'woocommerce-wholesale-payments' ),
        'testConnection'    => __( 'Test Connection', 'woocommerce-wholesale-payments' ),
        'testingConnection' => __( 'Testing connection...', 'woocommerce-wholesale-payments' ),
        'resetConnection'   => __( 'Reset Connection', 'woocommerce-wholesale-payments' ),
        'apiMode'           => __( 'API Mode', 'woocommerce-wholesale-payments' ),
        'testMode'          => __( 'Test', 'woocommerce-wholesale-payments' ),
        'liveMode'          => __( 'Live', 'woocommerce-wholesale-payments' ),
        'viewOnStripe'      => __( 'View on Stripe', 'woocommerce-wholesale-payments' ),
        'viewOrder'         => __( 'View Order', 'woocommerce-wholesale-payments' ),
        'loading'           => __( 'Refreshing', 'woocommerce-wholesale-payments' ),
    ),
    'heading'         => array(
        'wholesaleSuite'        => __( 'Wholesale Suite', 'woocommerce-wholesale-payments' ),
        'stripeLogo'            => __( 'Stripe', 'woocommerce-wholesale-payments' ),
        'stripeApiSettings'     => __( 'Stripe API Settings', 'woocommerce-wholesale-payments' ),
        'wholesalePaymentPlans' => array(
            'label'      => __( 'Payment Plans', 'woocommerce-wholesale-payments' ),
            'desc'       => __( 'Edit your existing wholesale payment plans or create a new one.', 'woocommerce-wholesale-payments' ),
            'noPlanDesc' => __( 'Create your wholesale payment plans.', 'woocommerce-wholesale-payments' ),
        ),
        'setupNewPaymentPlan'   => array(
            'label' => __( 'Create New Plan', 'woocommerce-wholesale-payments' ),
            'desc'  => __( 'Get started quickly with a commonly used payment template, or configure a custom payment plan. Payment plans will be available to your wholesale customers.', 'woocommerce-wholesale-payments' ),
        ),
        'editPaymentPlan'       => array(
            'label' => __( 'Edit Payment Plan', 'woocommerce-wholesale-payments' ),
            'desc'  => __( 'Configure your payment plan.', 'woocommerce-wholesale-payments' ),
        ),
        'wpayLogoLink'          => esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wpay', 'upsell', 'wpaylogolink' ) ),
        'invoiceLists'          => array(
            'label'  => __( 'Invoices', 'woocommerce-wholesale-payments' ),
            'filter' => array(
                'quickActions'       => __( 'Quick Actions', 'woocommerce-wholesale-payments' ),
                'all'                => __( 'All', 'woocommerce-wholesale-payments' ),
                'pending'            => __( 'Pending', 'woocommerce-wholesale-payments' ),
                'overdue'            => __( 'Overdue', 'woocommerce-wholesale-payments' ),
                'paid'               => __( 'Paid', 'woocommerce-wholesale-payments' ),
                'void'               => __( 'Void', 'woocommerce-wholesale-payments' ),
                'searchPlaceholder'  => __( 'Search Invoices', 'woocommerce-wholesale-payments' ),
                'allDates'           => __( 'All Dates', 'woocommerce-wholesale-payments' ),
                'registeredCustomer' => __( 'Filter by registered customer', 'woocommerce-wholesale-payments' ),
                'applyFilters'       => __( 'Filter', 'woocommerce-wholesale-payments' ),
                'dateFilter'         => array(
                    'today'      => __( 'Today', 'woocommerce-wholesale-payments' ),
                    'yesterday'  => __( 'Yesterday', 'woocommerce-wholesale-payments' ),
                    'last7days'  => __( 'Last 7 days', 'woocommerce-wholesale-payments' ),
                    'last30days' => __( 'Last 30 days', 'woocommerce-wholesale-payments' ),
                    'thismonth'  => __( 'This month', 'woocommerce-wholesale-payments' ),
                    'lastmonth'  => __( 'Last month', 'woocommerce-wholesale-payments' ),
                ),
            ),
        ),
        'invoiceSummary'        => array(
            'label'     => __( 'Summary', 'woocommerce-wholesale-payments' ),
            'dateOrder' => __( 'Date of Order', 'woocommerce-wholesale-payments' ),
            'billedTo'  => __( 'Billed to', 'woocommerce-wholesale-payments' ),
            'currency'  => __( 'Currency', 'woocommerce-wholesale-payments' ),
        ),
        'invoiceDetails'        => array(
            'label'           => __( 'Details', 'woocommerce-wholesale-payments' ),
            'created'         => __( 'Created', 'woocommerce-wholesale-payments' ),
            'finalized'       => __( 'Finalized', 'woocommerce-wholesale-payments' ),
            'paymentPage'     => __( 'Payment Page', 'woocommerce-wholesale-payments' ),
            'viewPaymentPage' => __( 'Customer Payment Page', 'woocommerce-wholesale-payments' ),
        ),
        'invoiceBreakdown'      => array(
            'label'           => __( 'Total Breakdown', 'woocommerce-wholesale-payments' ),
            'subtotal'        => __( 'Subtotal', 'woocommerce-wholesale-payments' ),
            'totalExcTax'     => __( 'Total excluding Tax', 'woocommerce-wholesale-payments' ),
            'tax'             => __( 'Tax', 'woocommerce-wholesale-payments' ),
            'totalTax'        => __( 'Total', 'woocommerce-wholesale-payments' ),
            'amountPaid'      => __( 'Amount Paid', 'woocommerce-wholesale-payments' ),
            'amountRemaining' => __( 'Amount Remaining', 'woocommerce-wholesale-payments' ),
        ),
        'invoicePaymentPlan'    => array(
            'label' => __( 'Payment Plan', 'woocommerce-wholesale-payments' ),
            'table' => array(
                'name'     => __( 'Name', 'woocommerce-wholesale-payments' ),
                'amount'   => __( 'Amount', 'woocommerce-wholesale-payments' ),
                'currency' => __( 'Currency', 'woocommerce-wholesale-payments' ),
                'status'   => __( 'Status', 'woocommerce-wholesale-payments' ),
                'due'      => __( 'Due', 'woocommerce-wholesale-payments' ),
                'paid'     => __( 'Paid', 'woocommerce-wholesale-payments' ),
            ),
        ),
        'invoicePayments'       => array(
            'label' => __( 'Payments', 'woocommerce-wholesale-payments' ),
            'table' => array(
                'amount'   => __( 'Amount', 'woocommerce-wholesale-payments' ),
                'currency' => __( 'Currency', 'woocommerce-wholesale-payments' ),
                'status'   => __( 'Status', 'woocommerce-wholesale-payments' ),
                'date'     => __( 'Date', 'woocommerce-wholesale-payments' ),
            ),
        ),
    ),
    'planEditor'      => array(
        'button'      => array(
            'addPayment'        => __( 'Add Payment', 'woocommerce-wholesale-payments' ),
            'savePaymentPlan'   => __( 'Save Changes', 'woocommerce-wholesale-payments' ),
            'cancelPaymentPlan' => __( 'Cancel', 'woocommerce-wholesale-payments' ),
            'deletePlan'        => __( 'Delete Payment Plan', 'woocommerce-wholesale-payments' ),
        ),
        'label'       => array(
            'yes'                   => __( 'Yes', 'woocommerce-wholesale-payments' ),
            'no'                    => __( 'No', 'woocommerce-wholesale-payments' ),
            'back'                  => __( 'Back', 'woocommerce-wholesale-payments' ),
            'planName'              => __( 'Plan Title', 'woocommerce-wholesale-payments' ),
            'planNameTooltip'       => __( 'Note: This title will be visible on the cart/checkout page. Enter a clear and concise title for your payment plan. This title will help you easily identify the plan in your list and should reflect the key details or purpose of the plan.', 'woocommerce-wholesale-payments' ),
            'planDesc'              => __( 'Plan Description', 'woocommerce-wholesale-payments' ),
            'planDescTooltip'       => __( 'Provide a detailed description of the payment plan. Include key information such as payment intervals (e.g., monthly, quarterly), total subscription duration, and any special terms or discounts.', 'woocommerce-wholesale-payments' ),
            'planBreakdown'         => __( 'Plan Breakdown:', 'woocommerce-wholesale-payments' ),
            /* translators: %d = number of days */
            'day'                   => __( 'Day %d', 'woocommerce-wholesale-payments' ),
            'noPaymentReq'          => __( 'No payment required', 'woocommerce-wholesale-payments' ),
            /* translators: %d = percentage number */
            'nPercentPayment'       => __( '%d% payment', 'woocommerce-wholesale-payments' ),
            /* translators: %1$s = currency symbol; %2$d = fixed amount number */
            'nFixedPayment'         => __( '%1$s%2$d payment', 'woocommerce-wholesale-payments' ),
            'breakdown'             => array(
                'dayAfterOrder' => __( 'Days after order:', 'woocommerce-wholesale-payments' ),
                'amountDue'     => __( 'Amount Due:', 'woocommerce-wholesale-payments' ),
            ),
            'selectFormat'          => __( 'Select format', 'woocommerce-wholesale-payments' ),
            'paymentReminder'       => __( 'Payment Reminder:', 'woocommerce-wholesale-payments' ),
            'overdueReminder'       => __( 'Overdue Reminder:', 'woocommerce-wholesale-payments' ),
            'dayBeforeDue'          => __( 'Day before due date', 'woocommerce-wholesale-payments' ),
            'daysBeforeDue'         => __( 'Days before due date', 'woocommerce-wholesale-payments' ),
            'dayAfterDue'           => __( 'Day after due date', 'woocommerce-wholesale-payments' ),
            'daysAfterDue'          => __( 'Days after due date', 'woocommerce-wholesale-payments' ),
            'reminderBefore'        => __( 'Send a reminder email before the payment is due', 'woocommerce-wholesale-payments' ),
            'reminderAfter'         => __( 'Send a reminder email if payment is not received', 'woocommerce-wholesale-payments' ),
            'applyRestrictions'     => __( 'Apply payment plan restrictions', 'woocommerce-wholesale-payments' ),
            'applyAutoCharge'       => __( 'Apply auto charge invoices', 'woocommerce-wholesale-payments' ),
            'allowedWsRoles'        => __( 'Allowed wholesale roles:', 'woocommerce-wholesale-payments' ),
            'allowedWsRolesToolTip' => __( 'Select the wholesale roles that are permitted to use this payment plan during checkout. Only users with the selected roles will see and be able to apply this plan to their purchases.', 'woocommerce-wholesale-payments' ),
            'allowedUsers'          => __( 'Allowed wholesale users:', 'woocommerce-wholesale-payments' ),
            'allowedUsersToolTip'   => __( 'Only approved wholesale customers can access this payment plan during checkout. Ensure your account has the necessary permissions to apply wholesale pricing and complete the purchase with this plan.', 'woocommerce-wholesale-payments' ),
            'planEnabled'           => __( 'Payment Plan Status:', 'woocommerce-wholesale-payments' ),
            'planDeleteBreakdown'   => __( 'Remove', 'woocommerce-wholesale-payments' ),
        ),
        'input'       => array(
            'day'        => __( 'Day', 'woocommerce-wholesale-payments' ),
            'custom'     => __( 'Custom', 'woocommerce-wholesale-payments' ),
            'timestamp'  => __( 'Timestamp', 'woocommerce-wholesale-payments' ),
            'none'       => __( 'No Payment Required', 'woocommerce-wholesale-payments' ),
            'fixed'      => __( 'Fixed amount', 'woocommerce-wholesale-payments' ),
            'percentage' => __( 'Percentage', 'woocommerce-wholesale-payments' ),
            'enabled'    => __( 'Enabled', 'woocommerce-wholesale-payments' ),
            'disabled'   => __( 'Disabled', 'woocommerce-wholesale-payments' ),
            'anyRole'    => __( 'Any Role', 'woocommerce-wholesale-payments' ),
        ),
        'placeholder' => array(
            /* translators: %d = percentage number */
            'day'                    => __( 'Day %d', 'woocommerce-wholesale-payments' ),
            'planName'               => __( 'Enter a name for your payment plan', 'woocommerce-wholesale-payments' ),
            'planDesc'               => __( 'Enter a description for your payment plan', 'woocommerce-wholesale-payments' ),
            'selectRoleRestrictions' => __( 'Select roles this payment plan should only be displayed and available to.', 'woocommerce-wholesale-payments' ),
            'selectAllowedUsers'     => __( 'Search and select users this payment plan should only be displayed and available to.', 'woocommerce-wholesale-payments' ),
        ),
        'desc'        => array(
            'planDesc'      => __( 'This is shown to eligible users on the checkout screen.', 'woocommerce-wholesale-payments' ),
            /* translators: %s = the entered text */
            'customDesc'    => __( 'Please make sure to provide filter hook handler for "wpay_parse_%s" which calculates and returns the number of days.', 'woocommerce-wholesale-payments' ),
            /* translators: %s = the entered text */
            'timestampDesc' => __( 'Please make sure to provide filter hook handler for "wpay_parse_%s" which calculates and returns an exact date in Unix epoch time format.', 'woocommerce-wholesale-payments' ),
            'noAddlPayment' => __( 'Your payment plan adds up to 100%, no additional payments are required.', 'woocommerce-wholesale-payments' ),
        ),
        'notice'      => array(
            'deleteBreakdown'    => __( 'Are you sure you sure you want to delete this payment breakdown?', 'woocommerce-wholesale-payments' ),
            'deletePlan'         => __( 'Are you sure you sure you want to delete this payment plan?', 'woocommerce-wholesale-payments' ),
            'pausePlan'          => __( 'Are you sure you sure you want to disable this payment plan?', 'woocommerce-wholesale-payments' ),
            'unsavedChanges'     => __( 'Unsaved changes', 'woocommerce-wholesale-payments' ),
            'unsavedChangesDesc' => __( 'You have unsaved changes. Are you sure you want to leave this page?', 'woocommerce-wholesale-payments' ),
        ),
    ),
    'nav'             => $nav,
    'notice'          => array(
        'success'                  => __( 'Success', 'woocommerce-wholesale-payments' ),
        'error'                    => __( 'Error', 'woocommerce-wholesale-payments' ),
        'confirmAction'            => __( 'Are you sure?', 'woocommerce-wholesale-payments' ),
        'redirectingToOnboarding'  => __( 'Redirecting to Stripe onboarding page...', 'woocommerce-wholesale-payments' ),
        'preConfiguredPaymentPlan' => array(
            'confirmCreate' => array(
                'title'       => __( 'Create from pre-configured payment plan', 'woocommerce-wholesale-payments' ),
                /* translators: %s = title of pre-configured payment plan */
                'content'     => __( 'Creating new payment plan based on %s.', 'woocommerce-wholesale-payments' ),
                'placeholder' => array(
                    'title'       => __( 'Enter a title for your new payment plan', 'woocommerce-wholesale-payments' ),
                    'description' => __( 'Enter a description for your new payment plan', 'woocommerce-wholesale-payments' ),
                ),
                'label'       => array(
                    'title'       => __( 'New Payment Plan Title', 'woocommerce-wholesale-payments' ),
                    'description' => __( 'New Payment Plan Description', 'woocommerce-wholesale-payments' ),
                ),
            ),
            'success'       => __( 'Payment plan created successfully!', 'woocommerce-wholesale-payments' ),
        ),
        'stripeAccountNotLinked'   => __( 'Your Stripe account seems to be not linked. Please reconnect your Stripe account.', 'woocommerce-wholesale-payments' ),
        'stripeResetWarning'       => __( 'This will disconnect the account currently connected with Stripe. Proceed?', 'woocommerce-wholesale-payments' ),
        /* translators: %1$s = opening <code> tag; %2$s = closing </code> tag */
        'disabledApiMode'          => __( 'API mode is permanently set to %1$stest%2$s mode since %1$sWP_ENVIRONMENT_TYPE%2$s constant is defined and is set to %1$slocal%2$s.', 'woocommerce-wholesale-payments' ),
        'noPlans'                  => __( 'No payment plans found.', 'woocommerce-wholesale-payments' ),
        'stripeNotConnected'       => __( 'Your Stripe account is not connected. Please connect your Stripe account.', 'woocommerce-wholesale-payments' ),
        'noPayments'               => __( 'No payments found.', 'woocommerce-wholesale-payments' ),
    ),
    'stripe'          => array(
        'connected'           => __( 'Stripe Connect Account:', 'woocommerce-wholesale-payments' ),
        'liveAccessToken'     => __( 'Live Access Token:', 'woocommerce-wholesale-payments' ),
        'livePublishableKey'  => __( 'Live Publishable Key:', 'woocommerce-wholesale-payments' ),
        'testAccessToken'     => __( 'Test Access Token:', 'woocommerce-wholesale-payments' ),
        'testPublishableKey'  => __( 'Test Publishable Key:', 'woocommerce-wholesale-payments' ),
        'stripeSettingsLabel' => __( 'Go to Settings', 'woocommerce-wholesale-payments' ),
        'stripeSettingsLink'  => admin_url( 'admin.php?page=wholesale-settings&tab=wpay' ),
    ),
    'disabledLicense' => array(
        'cancelled' => array(
            /* translators: %1$s and %2$s opening and closing strong tags respectively. */
            'title'             => __( '%1$sUrgent!%2$s Wholesale Payments license is disabled!', 'woocommerce-wholesale-payments' ),
            'content'           => __( 'Without an active license, your payment plans will still continue to work on your checkout page on the front end but the functionality to edit payment plans has been disabled until a valid license is entered.', 'woocommerce-wholesale-payments' ),
            'repurchaseLicense' => __( 'Repurchase New License', 'woocommerce-wholesale-payments' ),
            'enterLicense'      => __( 'Enter a new license', 'woocommerce-wholesale-payments' ),
        ),
    ),
    'expired'         => array(
        /* translators: %1$s and %2$s opening and closing strong tags respectively. */
        'title'          => __( '%1$sUrgent!%2$s Wholesale Payments license has expired!', 'woocommerce-wholesale-payments' ),
        'content'        => __( 'Without an active license, your payment plans will still continue to work on your checkout page on the front end but the functionality to edit payment plans has been disabled until a valid license is entered.', 'woocommerce-wholesale-payments' ),
        'renewLicense'   => __( 'Renew License', 'woocommerce-wholesale-payments' ),
        'enterLicense'   => __( 'Enter a new license', 'woocommerce-wholesale-payments' ),
        'refreshLicense' => __( 'Refresh license status', 'woocommerce-wholesale-payments' ),
        'refreshing'     => __( 'Refreshing', 'woocommerce-wholesale-payments' ),
    ),
    'noLicense'       => array(
        /* translators: %1$s and %2$s opening and closing strong tags respectively. */
        'title'           => __( '%1$sUrgent!%2$s Wholesale Payments license is missing!', 'woocommerce-wholesale-payments' ),
        'content'         => __( 'Without an active license, your payment plans will still continue to work on your checkout page on the front end but the functionality to edit payment plans has been disabled until a valid license is entered.', 'woocommerce-wholesale-payments' ),
        'purchaseLicense' => __( 'Don\'t have a license yet? Purchase here.', 'woocommerce-wholesale-payments' ),
        'enterLicense'    => __( 'Enter License Now', 'woocommerce-wholesale-payments' ),
    ),
    'table'           => array(
        'header'  => array(
            'planName'       => __( 'Plans', 'woocommerce-wholesale-payments' ),
            'planStatus'     => __( 'Status', 'woocommerce-wholesale-payments' ),
            'numPayments'    => __( '# of Payments', 'woocommerce-wholesale-payments' ),
            'planDuration'   => __( 'Plan Duration', 'woocommerce-wholesale-payments' ),
            'activeOrders'   => __( 'Active Orders', 'woocommerce-wholesale-payments' ),
            'actions'        => __( 'Actions', 'woocommerce-wholesale-payments' ),
            'OrderID'        => __( 'Order ID', 'woocommerce-wholesale-payments' ),
            'OrderName'      => __( 'Name', 'woocommerce-wholesale-payments' ),
            'amount'         => __( 'Amount', 'woocommerce-wholesale-payments' ),
            'currency'       => __( 'Currency', 'woocommerce-wholesale-payments' ),
            'dueDate'        => __( 'Due', 'woocommerce-wholesale-payments' ),
            'paid'           => __( 'Paid', 'woocommerce-wholesale-payments' ),
            'status'         => __( 'Status', 'woocommerce-wholesale-payments' ),
            'date'           => __( 'Date', 'woocommerce-wholesale-payments' ),
            'invoiceID'      => __( 'Invoice ID', 'woocommerce-wholesale-payments' ),
            'orderDate'      => __( 'Order Date', 'woocommerce-wholesale-payments' ),
            'customerName'   => __( 'Customer Name', 'woocommerce-wholesale-payments' ),
            'paymentPlan'    => __( 'Payment Plan', 'woocommerce-wholesale-payments' ),
            'nextPaymentDue' => __( 'Next Payment Due', 'woocommerce-wholesale-payments' ),
            'amountDue'      => __( 'Amount Due', 'woocommerce-wholesale-payments' ),
        ),
        'content' => array(
            'orderLabel'           => __( 'Order', 'woocommerce-wholesale-payments' ),
            'status'               => array(
                'yes'     => __( 'Active', 'woocommerce-wholesale-payments' ),
                'no'      => __( 'Inactive', 'woocommerce-wholesale-payments' ),
                'pending' => __( 'Pending', 'woocommerce-wholesale-payments' ),
                'overdue' => __( 'Overdue', 'woocommerce-wholesale-payments' ),
                'paid'    => __( 'Paid', 'woocommerce-wholesale-payments' ),
                'void'    => __( 'Void', 'woocommerce-wholesale-payments' ),
            ),
            'paymentLabel'         => __( 'Payments', 'woocommerce-wholesale-payments' ),
            'daysLabel'            => __( 'Days', 'woocommerce-wholesale-payments' ),
            'activeOrderLabel'     => __( 'Active Orders', 'woocommerce-wholesale-payments' ),
            'pauseAction'          => __( 'Pause', 'woocommerce-wholesale-payments' ),
            'continueAction'       => __( 'Continue', 'woocommerce-wholesale-payments' ),
            'editAction'           => __( 'Edit', 'woocommerce-wholesale-payments' ),
            'deleteAction'         => __( 'Delete', 'woocommerce-wholesale-payments' ),
            'noPlans'              => __( 'No payment plans found.', 'woocommerce-wholesale-payments' ),
            'sendReminderAction'   => __( 'Send Reminder', 'woocommerce-wholesale-payments' ),
            'collectPaymentAction' => __( 'Collect Payment', 'woocommerce-wholesale-payments' ),
            'viewDetailsAction'    => __( 'View Details', 'woocommerce-wholesale-payments' ),
            'RefreshAction'        => __( 'Refresh', 'woocommerce-wholesale-payments' ),
        ),
    ),
);
