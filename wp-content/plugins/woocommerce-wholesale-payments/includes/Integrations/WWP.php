<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Helpers\RCS;
use WP_Error;

/**
 * WWP class.
 *
 * @since 1.0.0
 */
class WWP extends Abstract_Class {

    /**
     * Add WPAY settings tab to the admin settings page.
     *
     * @param array $tabs The existing tabs.
     *
     * @since 1.0.0
     * @since 1.0.1 Added Stripe tab.
     * @return mixed
     */
    public function admin_settings_tab( $tabs ) {

        $tabs['wpay'] = array(
            'label' => __( 'Wholesale Payments', 'woocommerce-wholesale-payments' ),
            'child' => array(
                'general'         => array(
                    'sort'     => 2,
                    'key'      => 'general',
                    'label'    => __( 'General', 'woocommerce-wholesale-payments' ),
                    'sections' => array(
                        'api_settings' => array(
                            'label' => __( 'API Settings', 'woocommerce-wholesale-payments' ),
                            'desc'  => '',
                        ),
                    ),
                ),
                'checkout'        => array(
                    'sort'     => 2,
                    'key'      => 'checkout',
                    'label'    => __( 'Checkout', 'woocommerce-wholesale-payments' ),
                    'sections' => array(
                        'checkout_settings' => array(
                            'label' => __( 'Checkout Settings', 'woocommerce-wholesale-payments' ),
                            'desc'  => '',
                        ),
                    ),
                ),
                'email_reminders' => array(
                    'sort'     => 3,
                    'key'      => 'email_reminders',
                    'label'    => __( 'Email Reminders', 'woocommerce-wholesale-payments' ),
                    'sections' => array(
                        'email_reminders' => array(
                            'label' => '',
                            'desc'  => sprintf(
                                /* translators: 1: Store name, */
                                __( 'Send email reminders to customers when their payment is due. Your customers will receive emails from your %1$s Store and from Stripe. We recommend you disable email reminders in %2$s if you want to send emails from your Store only.', 'woocommerce-wholesale-payments' ),
                                get_bloginfo( 'name' ),
                                sprintf( '<a href="%1$s" target="_blank">%2$s</a>', 'https://dashboard.stripe.com/settings/emails', __( 'Stripe Dashboard', 'woocommerce-wholesale-payments' ) ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        return $tabs;
    }

    /**
     * Add WPAY settings to the admin settings page.
     *
     * @param array $controls The existing controls.
     *
     * @since 1.0.0
     * @since 1.0.1 Added Stripe settings.
     * @return mixed
     */
    public function wpay_admin_settings( $controls ) {

        $controls['wpay']['general'] = array(
            'api_settings' => array(
                array(
                    'type'    => 'select',
                    'label'   => __( 'API Mode', 'woocommerce-wholesale-payments' ),
                    'id'      => 'wpay_api_mode',
                    'default' => WPay::get_api_mode(),
                    'options' => array(
                        'live' => __( 'Live', 'woocommerce-wholesale-payments' ),
                        'test' => __( 'Test', 'woocommerce-wholesale-payments' ),
                    ),
                ),
            ),
        );

        $enabled_email_reminders = get_option( WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION, 'yes' );

        $hide_reminder_settings = 'yes' === $enabled_email_reminders ? false : true;

        $controls['wpay']['email_reminders'] = array(
            'email_reminders' => array(
                array(
                    'type'        => 'switch',
                    'label'       => __( 'Enable Email Reminders', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                    'default'     => $enabled_email_reminders,
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-payments' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-payments' ),
                    ),
                    'description' => __( 'When enabled, your customers will receive emails from your Store when payment is due.', 'woocommerce-wholesale-payments' ),
                ),
                array(
                    'type'        => 'number',
                    'label'       => __( 'Days Before Payment Due', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::DAYS_BEFORE_PAYMENT_DUE_OPTION,
                    'min'         => 0,
                    'max'         => 30,
                    'default'     => get_option( WPay_Emails::DAYS_BEFORE_PAYMENT_DUE_OPTION, 7 ),
                    'description' => __( 'Days before payment due to send the reminder email reminders.', 'woocommerce-wholesale-payments' ),
                    'hide'        => $hide_reminder_settings,
                    'condition'   => array(
                        array(
                            'key'   => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'number',
                    'label'       => __( 'Days After Payment Due', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::DAYS_AFTER_PAYMENT_DUE_OPTION,
                    'default'     => get_option( WPay_Emails::DAYS_AFTER_PAYMENT_DUE_OPTION, 3 ),
                    'min'         => 0,
                    'max'         => 30,
                    'description' => __( 'Days after payment due to send the reminder email reminders.', 'woocommerce-wholesale-payments' ),
                    'hide'        => $hide_reminder_settings,
                    'condition'   => array(
                        array(
                            'key'   => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable Payment Due Today Reminder', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::DUE_TODAY_REMINDER_ENABLED_OPTION,
                    'default'     => get_option( WPay_Emails::DUE_TODAY_REMINDER_ENABLED_OPTION, 'yes' ),
                    'description' => __( 'Send email reminders to customers when their payment is due today.', 'woocommerce-wholesale-payments' ),
                    'hide'        => $hide_reminder_settings,
                ),
                array(
                    'type'         => 'button',
                    'id'           => 'wpay_schedule_reminders',
                    'button_label' => __( 'Schedule Email Reminders Now', 'woocommerce-wholesale-payments' ),
                    'action'       => 'schedule_email_reminders',
                    'description'  => __( 'Manually trigger the scheduling of email reminders for all pending payments.', 'woocommerce-wholesale-payments' ),
                    'hide'         => $hide_reminder_settings,
                ),
                array(
                    'type'         => 'button',
                    'id'           => 'wpay_view_scheduled_actions',
                    'button_label' => __( 'View Scheduled Actions', 'woocommerce-wholesale-payments' ),
                    'action'       => 'view_scheduled_actions',
                    'description'  => __( 'View all scheduled email reminders in WooCommerce Status.', 'woocommerce-wholesale-payments' ),
                    'hide'         => $hide_reminder_settings,
                ),
            ),
        );

        $enabled_email_reminders = get_option( WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION, 'yes' );

        $hide_reminder_settings = 'yes' === $enabled_email_reminders ? false : true;

        $controls['wpay']['email_reminders'] = array(
            'email_reminders' => array(
                array(
                    'type'        => 'switch',
                    'label'       => __( 'Enable Email Reminders', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                    'default'     => $enabled_email_reminders,
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-payments' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-payments' ),
                    ),
                    'description' => __( 'When enabled, your customers will receive emails from your Store when payment is due.', 'woocommerce-wholesale-payments' ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable Stripe Invoice Reminders', 'woocommerce-wholesale-payments' ),
                    'id'          => 'wpay_enable_stripe_invoice_reminders',
                    'default'     => get_option( 'wpay_enable_stripe_invoice_reminders', 'no' ),
                    'description' => __( 'If enabled, we will create new invoice to allow you to send reminders from your Store or Stripe. If disabled, the default Stripe invoice behavior will be used. Disable this if you want to send reminders from your Store only.', 'woocommerce-wholesale-payments' ),
                ),
                array(
                    'type'        => 'number',
                    'label'       => __( 'Days Before Payment Due', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::DAYS_BEFORE_PAYMENT_DUE_OPTION,
                    'min'         => 0,
                    'max'         => 30,
                    'default'     => get_option( WPay_Emails::DAYS_BEFORE_PAYMENT_DUE_OPTION, 7 ),
                    'description' => __( 'Days before payment due to send the reminder email reminders.', 'woocommerce-wholesale-payments' ),
                    'hide'        => $hide_reminder_settings,
                    'condition'   => array(
                        array(
                            'key'   => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'number',
                    'label'       => __( 'Days After Payment Due', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::DAYS_AFTER_PAYMENT_DUE_OPTION,
                    'default'     => get_option( WPay_Emails::DAYS_AFTER_PAYMENT_DUE_OPTION, 3 ),
                    'min'         => 0,
                    'max'         => 30,
                    'description' => __( 'Days after payment due to send the reminder email reminders.', 'woocommerce-wholesale-payments' ),
                    'hide'        => $hide_reminder_settings,
                    'condition'   => array(
                        array(
                            'key'   => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable Payment Due Today Reminder', 'woocommerce-wholesale-payments' ),
                    'id'          => WPay_Emails::DUE_TODAY_REMINDER_ENABLED_OPTION,
                    'default'     => get_option( WPay_Emails::DUE_TODAY_REMINDER_ENABLED_OPTION, 'yes' ),
                    'description' => __( 'Send email reminders to customers when their payment is due today.', 'woocommerce-wholesale-payments' ),
                    'hide'        => $hide_reminder_settings,
                    'condition'   => array(
                        array(
                            'key'   => WPay_Emails::EMAIL_REMINDERS_ENABLED_OPTION,
                            'value' => 'yes',
                        ),
                    ),
                ),
            ),
        );

        if ( WPay::get_account_number() ) {
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Stripe Connect Account', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_account_number',
                'default'  => WPay::get_account_number(),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Live Access Token', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_access_token',
                'default'  => WPay::get_masked_token( 'access_token', false ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Live Publishable Key', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_publishable_key',
                'default'  => WPay::get_masked_token( 'publishable_key', false ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Test Access Token', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_test_access_token',
                'default'  => WPay::get_masked_token( 'access_token', true ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Test Publishable Key', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_test_publishable_key',
                'default'  => WPay::get_masked_token( 'publishable_key', true ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'         => 'button',
                'id'           => 'wpay_test_connection',
                'button_label' => __( 'Test Connection', 'woocommerce-wholesale-payments' ),
                'action'       => 'test_stripe_connection',
                'description'  => __( 'This will check your connection to stripe.', 'woocommerce-wholesale-payments' ),
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'         => 'button',
                'id'           => 'wpay_test_connection',
                'button_label' => __( 'Reset Connection', 'woocommerce-wholesale-payments' ),
                'action'       => 'reset_stripe_connection',
                'description'  => __( 'This will reset your connection to stripe.', 'woocommerce-wholesale-payments' ),
            );
        } else {
            $controls['wpay']['general']['api_settings'][] = array(
                'type'         => 'button',
                'label'        => __( 'Stripe API Settings', 'woocommerce-wholesale-payments' ),
                'id'           => 'wpay_connect_stripe_account',
                'button_label' => __( 'Connect with Stripe', 'woocommerce-wholesale-payments' ),
                'action'       => 'connect_stripe_account',
            );
        }

        $controls['wpay']['checkout'] = array(
            'checkout_settings' => array(
                array(
                    'type'    => 'text',
                    'label'   => __( 'Payment Method Name', 'woocommerce-wholesale-payments' ),
                    'id'      => WPay::get_payment_method_name( 'key' ),
                    'default' => WPay::get_payment_method_name(),
                ),
            ),
        );

        // Allow to enable/disable auto-charge invoices.
        $auto_charge_invoices = apply_filters( 'wpay_auto_charge_invoices', true );
        if ( $auto_charge_invoices ) {
            $auto_charge_invoices                                = get_option( 'wpay_stripe_auto_charge_invoices', 'no' );
            $controls['wpay']['checkout']['checkout_settings'][] = array(
                'type'        => 'switch',
                'label'       => __( 'Auto Charge Invoices', 'woocommerce-wholesale-payments' ),
                'id'          => 'wpay_stripe_auto_charge_invoices',
                'description' => __( 'Automatically charge invoices for wholesale payments.', 'woocommerce-wholesale-payments' ),
                'options'     => array(
                    'yes' => __( 'Enabled', 'woocommerce-wholesale-payments' ),
                    'no'  => __( 'Disabled', 'woocommerce-wholesale-payments' ),
                ),
                'default'     => $auto_charge_invoices,
            );
        }

        return $controls;
    }

    /**
     * Connect to Stripe account.
     *
     * @return array|WP_Error
     */
    public function connect_stripe_account() {
        $response = RCS::instance()->connect();

        if ( is_wp_error( $response ) ) {
            return array(
                'status'  => 'error',
                'message' => $response->get_error_message(),
            );
        }

        return array(
            'status'   => 'success',
            'message'  => __( 'Redirecting to Stripe onboarding page. Please wait...', 'woocommerce-wholesale-payments' ),
            'redirect' => $response['onboarding_url'],
        );
    }

    /**
     * Test the stripe connection.
     *
     * @return array|WP_Error
     */
    public function test_stripe_connection() {
        $response = RCS::instance()->get_stripe_account();

        if ( is_wp_error( $response ) ) {
            if ( str_contains( $response->get_error_message(), 'account does not exist' ) ||
                str_contains( $response->get_error_message(), 'may have been revoked' ) ||
                str_contains( $response->get_error_message(), 'not found' ) ) {
                WPay::save_account_number( '' );
            }

            return array(
                'status'  => 'error',
                'message' => $response->get_error_message(),
            );
        }

        $status  = 'error';
        $message = __( 'Stripe account not connected!', 'woocommerce-wholesale-payments' );
        if ( WPay::get_account_number() === $response['account_number'] ) {
            $status  = 'success';
            $message = __( 'Stripe account connected!', 'woocommerce-wholesale-payments' );
        }

        return array(
            'status'  => $status,
            'message' => $message,
        );
    }

    /**
     * Reset the stripe connection.
     *
     * @return array
     */
    public function reset_stripe_connection() {
        $reset_fields = array(
            'account_number',
            'display_name',
            'live_access_token',
            'live_publishable_key',
            'live_scope',
            'live_token_type',
            'live_webhook_secret',
            'refresh_token',
            'test_access_token',
            'test_publishable_key',
            'test_scope',
            'test_token_type',
            'test_webhook_secret',
        );

        foreach ( $reset_fields as $reset_field ) {
            update_option( "wpay_$reset_field", '' );
        }

        return array(
            'status'   => 'success',
            'message'  => __( 'Connection reset! Please connect a Stripe account.', 'woocommerce-wholesale-payments' ),
            'redirect' => admin_url( 'admin.php?page=wholesale-settings&tab=wpay' ),
        );
    }

    /**
     * Schedule email reminders manually.
     *
     * @since 1.0.4
     * @return array
     */
    public function schedule_email_reminders() {
        if ( ! class_exists( 'ActionScheduler' ) ) {
            return array(
                'status'  => 'error',
                'message' => __( 'Action Scheduler is not available.', 'woocommerce-wholesale-payments' ),
            );
        }

        $scheduler = new \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler();
        $scheduler->check_payment_reminders();

        return array(
            'status'  => 'success',
            'message' => __( 'Email reminders have been scheduled successfully.', 'woocommerce-wholesale-payments' ),
        );
    }

    /**
     * Redirect to view scheduled actions.
     *
     * @since 1.0.4
     * @return array
     */
    public function view_scheduled_actions() {
        return array(
            'status'   => 'success',
            'message'  => __( 'Redirecting to scheduled actions...', 'woocommerce-wholesale-payments' ),
            'redirect' => admin_url( 'admin.php?page=wc-status&tab=action-scheduler&s=wpay_email_reminders' ),
        );
    }

    /**
     * Run the integration.
     */
    public function run() {

        add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'admin_settings_tab' ) );
        add_filter( 'wwp_admin_setting_default_controls', array( $this, 'wpay_admin_settings' ) );

        // Add the Stripe API settings.
        add_action( 'wwp_trigger_connect_stripe_account', array( $this, 'connect_stripe_account' ), 10, 1 );
        add_action( 'wwp_trigger_test_stripe_connection', array( $this, 'test_stripe_connection' ), 10, 1 );
        add_action( 'wwp_trigger_reset_stripe_connection', array( $this, 'reset_stripe_connection' ), 10, 1 );

        // Add email reminder actions.
        add_action( 'wwp_trigger_schedule_email_reminders', array( $this, 'schedule_email_reminders' ), 10, 1 );
        add_action( 'wwp_trigger_view_scheduled_actions', array( $this, 'view_scheduled_actions' ), 10, 1 );
    }
}
