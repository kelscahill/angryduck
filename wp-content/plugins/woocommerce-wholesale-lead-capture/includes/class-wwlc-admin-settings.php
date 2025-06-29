<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Admin_Settings' ) ) {

    /**
     * Model that houses the logic of WooCommerce Wholesale Prices Premium Settings page.
     *
     * @since 1.0.0
     */
    class WWLC_Admin_Settings {

        /**
         * Property that holds the single main instance of WWPP_Dashboard.
         *
         * @since  2.0
         * @access private
         * @var WWLC_Dashboard
         */
        private static $_instance;

        /**
         * WWLC_Admin_Settings constructor.
         *
         * @since  2.0
         * @access public
         */
        public function __construct() {
        }

        /**
         * Ensure that only one instance of WWPP_Admin_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         *
         * @return WWPP_Admin_Settings
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get the value of a setting.
         *
         * @param array $tabs Settings tabs.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_tabs( $tabs ) {

            $tabs['wholesale_lead'] = array(
                'label' => __( 'Wholesale Lead Capture', 'woocommerce-wholesale-lead-capture' ),
                'child' => array(),
            );

            // Check if license is active.
            $is_show_interstitial = WWLC_WWS_License_Manager::show_license_interstitial();
            if ( $is_show_interstitial ) {

                $license_btn_label    = __( 'Enter License Now', 'woocommerce-wholesale-lead-capture' );
                $license_link         = admin_url( 'admin.php?page=wws-license-settings&tab=wwlc' );
                $license_action_label = __( 'Don’t have a license yet? Purchase here.', 'woocommerce-wholesale-lead-capture' );
                $license_action_link  = esc_url( 'https://wholesalesuiteplugin.com/bundle/?utm_source=wwlc&utm_medium=drm&utm_campaign=wwlcdrmnoticepurchaselink' );
                $status_text          = sprintf(
                    /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                    __( '%1$sUrgent!%2$s Your Wholesale Lead Capture license is missing!', 'woocommerce-wholesale-lead-capture' ),
                    '<span style="color: #a00;">',
                    '</span>'
                );
                if ( 'expired' === $is_show_interstitial ) {
                    $license_btn_label    = __( 'Renew License', 'woocommerce-wholesale-lead-capture' );
                    $license_link         = WWLC_WWS_License_Manager::get_license_management_url();
                    $license_action_label = __( 'Enter a new license', 'woocommerce-wholesale-lead-capture' );
                    $license_action_link  = admin_url( 'admin.php?page=wws-license-settings&tab=wwlc' );
                    $status_text          = sprintf(
                        /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                        __( '%1$sUrgent!%2$s Your Wholesale Lead Capture license has expired!', 'woocommerce-wholesale-lead-capture' ),
                        '<span style="color: #a00;">',
                        '</span>'
                    );
                } elseif ( 'disabled' === $is_show_interstitial ) {
                    $license_btn_label    = __( 'Repurchase New License', 'woocommerce-wholesale-lead-capture' );
                    $license_link         = esc_url( 'https://wholesalesuiteplugin.com/bundle/?utm_source=wwlc&utm_medium=drm&utm_campaign=wwlcdrminterstitialrepurchaselink' );
                    $license_action_label = __( 'Enter a new license', 'woocommerce-wholesale-lead-capture' );
                    $license_action_link  = admin_url( 'admin.php?page=wws-license-settings&tab=wwlc' );
                    $status_text          = sprintf(
                        /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                        __( '%1$sUrgent!%2$s Your Wholesale Lead Capture license is disabled!', 'woocommerce-wholesale-lead-capture' ),
                        '<span style="color: #a00;">',
                        '</span>'
                    );
                }

                $tabs['wholesale_lead']['interstitial']         = true;
                $tabs['wholesale_lead']['interstitial_details'] = array(
                    'title'                => $status_text,
                    'description'          => __( 'Without an active license, your website front end will still continue to receive leads but premium functionality has been disabled until a valid license is entered.', 'woocommerce-wholesale-lead-capture' ),
                    'license_btn_label'    => $license_btn_label,
                    'license_btn_link'     => $license_link,
                    'license_action_label' => $license_action_label,
                    'license_action_link'  => $license_action_link,
                );
            }

            // General Options.
            $tabs['wholesale_lead']['child']['general'] = array(
                'sort'  => 1,
                'key'   => 'general',
                'label' => __( 'General', 'woocommerce-wholesale-lead-capture' ),
            );

            $tabs['wholesale_lead']['child']['general']['sections'] = array(
                'general_pages'        => array(
                    'label' => __( 'Pages', 'woocommerce-wholesale-lead-capture' ),
                    'desc'  => __( 'Set the pages related to wholesale registration, login and your wholesale program’s terms.', 'woocommerce-wholesale-lead-capture' ),
                ),
                'general_lead_actions' => array(
                    'label' => __( 'Lead Actions', 'woocommerce-wholesale-lead-capture' ),
                    'desc'  => __( 'These settings describe what happens to when processing a new wholesale lead.', 'woocommerce-wholesale-lead-capture' ),
                ),
            );

            // Registration Form Options.
            $tabs['wholesale_lead']['child']['registration'] = array(
                'sort'    => 2,
                'key'     => 'registration',
                'label'   => __( 'Registration Form', 'woocommerce-wholesale-lead-capture' ),
                'no_save' => true,
            );

            $tabs['wholesale_lead']['child']['registration']['sections'] = array(
                'registration_fields' => array(
                    'label' => __( 'Registration Fields', 'woocommerce-wholesale-lead-capture' ),
                    'desc'  => __( 'Built-in fields are fields that are common to many different types of wholesale industries. There are some fields that must appear on your registration form in order to create the user. There are also some built-in fields that are optional, meaning that can be omitted from the form if you don’t need to collect them.', 'woocommerce-wholesale-lead-capture' ),
                ),
            );

            // Emails Options.
            $tabs['wholesale_lead']['child']['emails'] = array(
                'sort'  => 3,
                'key'   => 'emails',
                'label' => __( 'Emails', 'woocommerce-wholesale-lead-capture' ),
            );

            $tabs['wholesale_lead']['child']['emails']['sections'] = array(
                'email_options' => array(
                    'label' => __( 'Emails Options', 'woocommerce-wholesale-lead-capture' ),
                    'desc'  => '',
                ),
            );

            // Security Options.
            $tabs['wholesale_lead']['child']['security'] = array(
                'sort'  => 4,
                'key'   => 'security',
                'label' => __( 'Security', 'woocommerce-wholesale-lead-capture' ),
            );

            $tabs['wholesale_lead']['child']['security']['sections'] = array(
                'security_options' => array(
                    'label' => __( 'Security', 'woocommerce-wholesale-lead-capture' ),
                    'desc'  => '',
                ),
            );

            // Help Options.
            $tabs['wholesale_lead']['child']['help'] = array(
                'sort'  => 5,
                'key'   => 'help',
                'label' => __( 'Help', 'woocommerce-wholesale-lead-capture' ),
            );

            $help_options_dec = sprintf(
            // translators: %1$s link to premium add-on, %2$s </a> tag.
                __(
                    'Looking for documentation? Please see our growing %1$sKnowledge Base%2$s.',
                    'woocommerce-wholesale-lead-capture'
                ),
                '<a target="_blank" href="https://wholesalesuiteplugin.com/knowledge-base/?utm_source=wwlc&utm_medium=settings&utm_campaign=KnowledgeBase"> ',
                '</a>',
            );
            $tabs['wholesale_lead']['child']['help']['sections'] = array(
                'help_options' => array(
                    'label' => __( 'Knowledge Base', 'woocommerce-wholesale-lead-capture' ),
                    'desc'  => $help_options_dec,
                ),
            );

            return $tabs;
        }

        /**
         * Filter controls in settings.
         *
         * @param array $controls Settings controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_controls( $controls ) {

            $controls['wholesale_lead']['general']      = $this->general_tab_controls();
            $controls['wholesale_lead']['registration'] = $this->registration_tab_controls();
            $controls['wholesale_lead']['emails']       = $this->email_options_tab_controls();
            $controls['wholesale_lead']['security']     = $this->security_options_tab_controls();
            $controls['wholesale_lead']['help']         = $this->help_options_tab_controls();

            return $controls;
        }

        /**
         * General tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function general_tab_controls() {
            global $wp_roles;

            if ( ! isset( $wp_roles ) ) {
                $wp_roles = new WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            }

            $general_controls = array();

            // General Pages Options - Section.
            $general_login_page            = get_option( 'wwlc_general_login_page' );
            $general_registration_page     = get_option( 'wwlc_general_registration_page' );
            $general_registration_thankyou = get_option( 'wwlc_general_registration_thankyou' );
            $general_login_redirect_page   = get_option( 'wwlc_general_login_redirect_page' );
            $general_logout_redirect_page  = get_option( 'wwlc_general_logout_redirect_page' );
            $enable_terms_and_condition    = get_option( 'wwlc_general_show_terms_and_conditions' );
            $terms_privacy_text            = get_option( 'wwlc_terms_privacy_text' );
            $terms_privacy_validation_text = get_option( 'wwlc_terms_privacy_validation_text' );

            $general_controls['general_pages'] = array(
                array(
                    'type'                => 'link',
                    'label'               => __( 'Wholesale Login', 'woocommerce-wholesale-lead-capture' ),
                    'description'         => __( 'This is the page where your wholesale customers login to their accounts.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                  => 'wwlc_general_login_page',
                    'with_text_condition' => true,
                    'custom_link'         => ( ! is_numeric( $general_login_page ) ) ? $general_login_page : '',
                    'default'             => ( is_numeric( $general_login_page ) ) ? $general_login_page : ( ! empty( $general_login_page ) ? 'custom' : '' ),
                    'options'             => $this->all_page_options(),
                    'links'               => $this->all_page_options( true ),
                ),
                array(
                    'type'                => 'link',
                    'label'               => __( 'Wholesale Registration', 'woocommerce-wholesale-lead-capture' ),
                    'description'         => __( 'This is the page where new leads can register for a wholesale account.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                  => 'wwlc_general_registration_page',
                    'with_text_condition' => true,
                    'custom_link'         => ( ! is_numeric( $general_registration_page ) ) ? $general_registration_page : '',
                    'default'             => ( is_numeric( $general_registration_page ) ) ? $general_registration_page : ( ! empty( $general_registration_page ) ? 'custom' : '' ),
                    'options'             => $this->all_page_options(),
                    'links'               => $this->all_page_options( true ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwlc_enable_admin_registration_page_view',
                    'input_label' => __( 'Allow logged-in admin and shop managers to view registration page', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $general_registration_page,
                ),
                array(
                    'type'                => 'link',
                    'label'               => __( 'Wholesale Registration Thank You', 'woocommerce-wholesale-lead-capture' ),
                    'description'         => __( 'This is where customers are redirected immediately after registering for their wholesale account.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                  => 'wwlc_general_registration_thankyou',
                    'with_text_condition' => true,
                    'custom_link'         => ( ! is_numeric( $general_registration_thankyou ) ) ? $general_registration_thankyou : '',
                    'default'             => ( is_numeric( $general_registration_thankyou ) ) ? $general_registration_thankyou : ( ! empty( $general_registration_thankyou ) ? 'custom' : '' ),
                    'options'             => $this->all_page_options(),
                    'links'               => $this->all_page_options( true ),
                ),
                array(
                    'type'                => 'link',
                    'label'               => __( 'Login Redirect', 'woocommerce-wholesale-lead-capture' ),
                    'description'         => __( 'This is where wholesale customers are redirected immediately after logging in.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                  => 'wwlc_general_login_redirect_page',
                    'with_text_condition' => true,
                    'custom_link'         => ( ! is_numeric( $general_login_redirect_page ) ) ? $general_login_redirect_page : '',
                    'default'             => ( is_numeric( $general_login_redirect_page ) ) ? $general_login_redirect_page : ( ! empty( $general_login_redirect_page ) ? 'custom' : '' ),
                    'options'             => $this->all_page_options(),
                    'links'               => $this->all_page_options( true ),
                ),
                array(
                    'type'                => 'link',
                    'label'               => __( 'Logout Redirect', 'woocommerce-wholesale-lead-capture' ),
                    'description'         => __( 'This is where wholesale customers are redirected immediately after logging out.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                  => 'wwlc_general_logout_redirect_page',
                    'with_text_condition' => true,
                    'custom_link'         => ( ! is_numeric( $general_logout_redirect_page ) ) ? $general_logout_redirect_page : '',
                    'default'             => ( is_numeric( $general_logout_redirect_page ) ) ? $general_logout_redirect_page : ( ! empty( $general_logout_redirect_page ) ? 'custom' : '' ),
                    'options'             => $this->all_page_options(),
                    'links'               => $this->all_page_options( true ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => 'Enable terms and condition',
                    'id'          => 'wwlc_general_show_terms_and_conditions',
                    'input_label' => __( 'If checked, displays the terms and condition in the registration page.', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $enable_terms_and_condition,
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Terms and conditions text', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_terms_privacy_text',
                    'editor'      => true,
                    'description' => __( 'Text to appear in user registration page.', 'woocommerce-wholesale-lead-capture' ),
                    'desc_tip'    => '',
                    'default'     => ( ! empty( $terms_privacy_text ) ) ? $terms_privacy_text : '',
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Terms and conditions validation text', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_terms_privacy_validation_text',
                    'description' => __( 'Text validation will appear in user registration page if terms and conditions is not accepted.', 'woocommerce-wholesale-lead-capture' ),
                    'desc_tip'    => '',
                    'default'     => ( ! empty( $terms_privacy_validation_text ) ) ? $terms_privacy_validation_text : 'You need to agree to the terms and conditions.',
                ),
            );

            // General Lead Actions Options - Section.
            $general_new_lead_role                = get_option( 'wwlc_general_new_lead_role' );
            $general_auto_approve_new_leads       = get_option( 'wwlc_general_auto_approve_new_leads' );
            $enable_account_upgrade               = get_option( 'wwlc_enable_account_upgrade' );
            $show_account_upgrade                 = get_option( 'wwlc_show_account_upgrade' );
            $remove_user_after_rejection          = get_option( 'wwlc_remove_user_after_rejection' );
            $general_terms_and_condition_page_url = get_option( 'wwlc_general_terms_and_condition_page_url' );
            $account_upgrade_message              = get_option( 'wwlc_account_upgrade_message' );

            /**
             * Whether to use non-wholesale roles.
             * Set to true if you want to use non-wholesale roles.
             *
             * @since 2.0.1
             *
             * @var bool
             */
            $use_non_wholesale_roles = apply_filters( 'wwlc_use_non_wholesale_roles', false );

            $lead_roles = ! $use_non_wholesale_roles ? WWLC_Helper_Functions::get_wholesale_lead_roles() : $wp_roles->get_names();

            if ( $use_non_wholesale_roles ) {
                if ( array_key_exists( 'administrator', $lead_roles ) ) {
                    unset( $lead_roles['administrator'] );
                }

                if ( array_key_exists( 'shop_manager', $lead_roles ) ) {
                    unset( $lead_roles['shop_manager'] );
                }
            }

            /**
             * Allow to add or remove specific roles to the lead role options.
             *
             * @since 2.0.1
             *
             * @param array $lead_roles The lead role options.
             */
            $lead_roles = apply_filters( 'wwlc_settings_new_lead_role_options', $lead_roles );

            $general_controls['general_lead_actions'] = array(
                array(
                    'type'                => 'select',
                    'label'               => __( 'New Lead Role', 'woocommerce-wholesale-lead-capture' ),
                    'description'         => __( 'This is the user role that your wholesale customers will receive once they have been approved.', 'woocommerce-wholesale-lead-capture' ),
                    'id'                  => 'wwlc_general_new_lead_role',
                    'with_text_condition' => true,
                    'default'             => $general_new_lead_role,
                    'options'             => $lead_roles,
                ),
                array(
                    'type'     => 'radio',
                    'label'    => __( 'Auto Approve New Leads', 'woocommerce-wholesale-lead-capture' ),
                    'id'       => 'wwlc_general_auto_approve_new_leads',
                    'options'  => array(
                        'no'  => __( 'Manual Approval Required – new registrations are held in a moderation queue and require an administrator or store manager to approve them.', 'woocommerce-wholesale-lead-capture' ),
                        'yes' => __( 'Automatic Approval – new registrations are automatically approved after submitting the wholesale registration form.', 'woocommerce-wholesale-lead-capture' ),
                    ),
                    'default'  => $general_auto_approve_new_leads,
                    'desc_tip' => __( 'Note: When it\'s set to Automatic Approval, users will be automatically logged-in after successful registration.', 'woocommerce-wholesale-lead-capture' ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Auto Remove User After Rejection', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_remove_user_after_rejection',
                    'input_label' => __( 'Automatically remove user after rejection. This will remove the user from the database after the admin rejects the user.', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $remove_user_after_rejection,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Account Upgrade', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_enable_account_upgrade',
                    'input_label' => __( 'Allow existing customers to request an account upgrade. Existing customers must fill-up and submit the lead capture registration form to request an upgrade. Note: once approved, the role will be based on the New Lead Role set above.', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $enable_account_upgrade,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Show Account Upgrade Message', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_show_account_upgrade',
                    'input_label' => __( 'Display account upgrade message and link to upgrade account registration form in "My account" page for standard customers.', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $show_account_upgrade,
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Upgrade Message', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_account_upgrade_message',
                    'editor'      => true,
                    'description' => '',
                    'desc_tip'    => __( 'Text to appear in user account page.', 'woocommerce-wholesale-lead-capture' ),
                    'default'     => ( ! empty( $account_upgrade_message ) ) ? $account_upgrade_message : 'You are registered as a standard customer. Click here to {link}request an account upgrade{/link}.',
                ),
            );

            return $general_controls;
        }

        /**
         * Get all pages.
         *
         * @param bool $link Whether to return the page link or not.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function all_page_options( $link = false ) {
            $allPages     = array(
				''       => __( 'Select a Page', 'woocommerce-wholesale-lead-capture' ),
				'custom' => __( '--- Custom Link ---', 'woocommerce-wholesale-lead-capture' ),
			);
            $allPagesList = get_pages();

            if ( $allPagesList ) {
                foreach ( $allPagesList as $page ) {
                    $allPages[ $page->ID ] = ( $link ) ? trim( get_permalink( $page->ID ) ) : $page->post_title;
                }
            }

            return $allPages;
        }

        /**
         * Registration tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function registration_tab_controls() {
            $registration_controls = array();

            // Registration Form Options - Section.
            $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

            $group_fields_data = array();

            // Terms & Conditions.
            $general_url         = admin_url( 'admin.php?page=wholesale-settings&tab=wholesale_lead' );
            $terms_condition_dec = sprintf(
            // translators: %1$s link to general WWLC settings, %2$s </a> tag.
                __(
                    'This field always appear at the bottom of the form. Please ensure you select a value for the Terms & Conditions Page on the %1$sGeneral Settings%2$s.',
                    'woocommerce-wholesale-lead-capture'
                ),
                '<a target="_blank" href="' . $general_url . '"> ',
                '</a>',
            );

            // Built-in fields.
            $built_in_fields = array(
                array(
                    'key'               => 1,
                    'field_name'        => __( 'First Name', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'first_name',
                    'field_type'        => 'text',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_first_name_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_first_name_field_order' ) ) : 1,
                    'field_placeholder' => ( get_option( 'wwlc_fields_first_name_field_placeholder' ) ) ? get_option( 'wwlc_fields_first_name_field_placeholder' ) : '',
                    'enabled'           => 'yes',
                    'required'          => 'yes',
                    'deletetable'       => 'no',
                    'disabled_field'    => array(
                        'field_name',
                        'field_type',
                        'default_value',
                        'checkout_display',
                        'enabled',
                        'required',
                    ),
                ),
                array(
                    'key'               => 2,
                    'field_name'        => __( 'Last Name', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'last_name',
                    'field_type'        => 'text',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_last_name_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_last_name_field_order' ) ) : 2,
                    'field_placeholder' => ( get_option( 'wwlc_fields_last_name_field_placeholder' ) ) ? get_option( 'wwlc_fields_last_name_field_placeholder' ) : '',
                    'enabled'           => 'yes',
                    'required'          => 'yes',
                    'deletetable'       => 'no',
                    'disabled_field'    => array(
                        'field_name',
                        'field_type',
                        'default_value',
                        'checkout_display',
                        'enabled',
                        'required',
                    ),
                ),
                array(
                    'key'               => 3,
                    'field_name'        => __( 'Phone', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'wwlc_phone',
                    'field_type'        => 'phone',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_phone_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_phone_field_order' ) ) : 3,
                    'field_placeholder' => ( get_option( 'wwlc_fields_phone_field_placeholder' ) ) ? get_option( 'wwlc_fields_phone_field_placeholder' ) : '',
                    'enabled'           => 'yes',
                    'required'          => ( 'yes' === get_option( 'wwlc_fields_require_phone_field' ) ) ? 'yes' : 'no',
                    'deletetable'       => 'no',
                    'additional_fields' => array(
                        'phone_pattern' => ( get_option( 'wwlc_fields_phone_mask_pattern' ) ) ? get_option( 'wwlc_fields_phone_mask_pattern' ) : '',
                    ),
                    'disabled_field'    => array(
                        'field_name',
                        'default_value',
                        'checkout_display',
                        'enabled',
                    ),
                ),
                array(
                    'key'               => 4,
                    'field_name'        => __( 'Email', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'user_email',
                    'field_type'        => 'email',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_email_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_email_field_order' ) ) : 4,
                    'field_placeholder' => ( get_option( 'wwlc_fields_email_field_placeholder' ) ) ? get_option( 'wwlc_fields_email_field_placeholder' ) : '',
                    'enabled'           => 'yes',
                    'required'          => 'yes',
                    'deletetable'       => 'no',
                    'disabled_field'    => array(
                        'field_name',
                        'field_type',
                        'default_value',
                        'checkout_display',
                        'enabled',
                        'required',
                    ),
                ),
                array(
                    'key'               => 5,
                    'field_name'        => __( 'Username', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'wwlc_username',
                    'field_type'        => 'text',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_username_order' ) ) ) ? intval( get_option( 'wwlc_fields_username_order' ) ) : 5,
                    'field_placeholder' => ( get_option( 'wwlc_fields_username_placeholder' ) ) ? get_option( 'wwlc_fields_username_placeholder' ) : '',
                    'enabled'           => ( 'yes' === get_option( 'wwlc_fields_username_active' ) ) ? 'yes' : 'no',
                    'required'          => 'yes',
                    'deletetable'       => 'no',
                    'disabled_field'    => array(
                        'field_name',
                        'field_type',
                        'default_value',
                        'checkout_display',
                        'required',
                    ),
                ),
                array(
                    'key'               => 6,
                    'field_name'        => __( 'Password', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'wwlc_password',
                    'field_type'        => 'password',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_password_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_password_field_order' ) ) : 6,
                    'field_placeholder' => ( get_option( 'wwlc_fields_password_field_placeholder' ) ) ? get_option( 'wwlc_fields_password_field_placeholder' ) : '',
                    'enabled'           => ( 'yes' === get_option( 'wwlc_fields_activate_password_field' ) ) ? 'yes' : 'no',
                    'required'          => ( 'yes' === get_option( 'wwlc_fields_require_password_field' ) ) ? 'yes' : 'no',
                    'enable_confirm'    => ( 'yes' === get_option( 'wwlc_fields_enable_confirm_password_field' ) ) ? 'yes' : 'no',
                    'password_strength' => ( 'yes' === get_option( 'wwlc_fields_minimum_password_strength_field' ) ) ? 'yes' : 'no',
                    'deletetable'       => 'no',
                    'disabled_field'    => array(
                        'field_name',
                        'field_type',
                        'default_value',
                        'checkout_display',
                    ),
                ),
                array(
                    'key'               => 7,
                    'field_name'        => __( 'Company Name', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'wwlc_company_name',
                    'field_type'        => 'text',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_company_name_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_company_name_field_order' ) ) : 7,
                    'field_placeholder' => ( get_option( 'wwlc_fields_company_field_placeholder' ) ) ? get_option( 'wwlc_fields_company_field_placeholder' ) : '',
                    'enabled'           => ( 'yes' === get_option( 'wwlc_fields_activate_company_name_field' ) ) ? 'yes' : 'no',
                    'required'          => ( 'yes' === get_option( 'wwlc_fields_require_company_name_field' ) ) ? 'yes' : 'no',
                    'deletetable'       => 'no',
                    'disabled_field'    => array(
                        'field_name',
                        'field_type',
                        'default_value',
                        'checkout_display',
                    ),
                ),
                array(
                    'key'               => 8,
                    'field_name'        => __( 'Address', 'woocommerce-wholesale-lead-capture' ),
                    'field_id'          => 'address',
                    'field_type'        => 'address',
                    'field_order'       => ( is_numeric( get_option( 'wwlc_fields_address_field_order' ) ) ) ? intval( get_option( 'wwlc_fields_address_field_order' ) ) : 8,
                    'field_placeholder' => get_option( 'wwlc_fields_address_placeholder' ) ? __( 'Street address', 'woocommerce-wholesale-lead-capture' ) : '',
                    'enabled'           => ( 'yes' === get_option( 'wwlc_fields_activate_address_field' ) ) ? 'yes' : 'no',
                    'required'          => ( 'yes' === get_option( 'wwlc_fields_require_address_field' ) ) ? 'yes' : 'no',
                    'deletetable'       => 'no',
                    'additional_fields' => array(
                        'address2_placeholder'  => get_option( 'wwlc_fields_address2_placeholder' ) ? get_option( 'wwlc_fields_address2_placeholder' ) : __( 'Apartment, suite, unit etc. (optional)', 'woocommerce-wholesale-lead-capture' ),
                        'enable_address2_label' => ( 'yes' === get_option( 'wwlc_fields_enable_address2_label' ) ) ? 'yes' : 'no',
                        'city_placeholder'      => get_option( 'wwlc_fields_city_placeholder' ) ? get_option( 'wwlc_fields_city_placeholder' ) : __( 'Town / City', 'woocommerce-wholesale-lead-capture' ),
                        'state_placeholder'     => get_option( 'wwlc_fields_state_placeholder' ) ? get_option( 'wwlc_fields_state_placeholder' ) : __( 'State / County', 'woocommerce-wholesale-lead-capture' ),
                        'postcode_placeholder'  => get_option( 'wwlc_fields_postcode_placeholder' ) ? get_option( 'wwlc_fields_postcode_placeholder' ) : __( 'Postcode / Zip', 'woocommerce-wholesale-lead-capture' ),
                    ),
                    'disabled_field'    => array(
                        'field_name',
                        'default_value',
                        'checkout_display',
                    ),
                ),
            );

            $group_fields_data = $built_in_fields;

            // Custom fields.
            $not_in_checkout = array(
                'phone',
                'hidden',
                'file',
                'address',
                'content',
                'terms_conditions',
            );
            if ( ! empty( $custom_fields ) ) {
                $i = count( $built_in_fields ) + 1;
                foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                    $field_data = array(
                        'key'           => $i,
                        'field_name'    => $custom_field['field_name'],
                        'field_id'      => $custom_field_id,
                        'field_type'    => $custom_field['field_type'],
                        'field_order'   => ! empty( $custom_field['field_order'] ) ? $custom_field['field_order'] : 1,
                        'default_value' => ! empty( $custom_field['default_value'] ) ? $custom_field['default_value'] : '',
                        'enabled'       => ( $custom_field['enabled'] ) ? 'yes' : 'no',
                        'required'      => ( $custom_field['required'] ) ? 'yes' : 'no',
                    );

                    if ( ! in_array( $custom_field['field_type'], $not_in_checkout, true ) ) {
                        $field_data['checkout_display'] = ( $custom_field['checkout_display'] ) ? 'yes' : 'no';
                    }

                    if ( 'number' === $custom_field['field_type'] ) {
                        $field_data['attributes_min']  = $custom_field['attributes']['min'];
                        $field_data['attributes_max']  = $custom_field['attributes']['max'];
                        $field_data['attributes_step'] = $custom_field['attributes']['step'];
                    }

                    if ( 'content' === $custom_field['field_type'] ) {
                        $field_data['content_display'] = ! empty( $custom_field['content_display'] ) ? $custom_field['content_display'] : '';
                    }

                    if ( ! empty( $custom_field['options'] ) ) {
                        $field_data['options'] = $custom_field['options'];
                    } else {
                        $field_data['field_placeholder'] = ! empty( $custom_field['field_placeholder'] ) ? $custom_field['field_placeholder'] : '';
                    }

                    if ( 'file' === $custom_field['field_type'] ) {
                        $field_data['field_allowed_filetypes'] = ! empty( $custom_field['field_allowed_filetypes'] ) ? $custom_field['field_allowed_filetypes'] : '';
                        $field_data['max_allowed_file_size']   = ! empty( $custom_field['max_allowed_file_size'] ) ? $custom_field['max_allowed_file_size'] : '';
                    }

                    if ( 'phone' === $custom_field['field_type'] ) {
                        $field_data['phone_pattern'] = ! empty( $custom_field['phone_pattern'] ) ? $custom_field['phone_pattern'] : '';
                    }

                    $group_fields_data[] = $field_data;

                    ++$i;
                }
            }

            $registration_controls['registration_fields'] = array(
                array(
                    'type'         => 'group_conditional',
                    'label'        => __( 'Registration fields', 'woocommerce-wholesale-lead-capture' ),
                    'id'           => 'wwlc_built_in_fields',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_registration_fields_mapping_save',
                    'fields'       => array(
                        array(
                            'type'    => 'text',
                            'label'   => __( 'Field Name', 'woocommerce-wholesale-lead-capture' ),
                            'id'      => 'field_name',
                            'default' => '',
                        ),
                        array(
                            'type'           => 'text',
                            'label'          => __( 'Field ID', 'woocommerce-wholesale-lead-capture' ),
                            'id'             => 'field_id',
                            'description'    => __( 'Must be unique. Letters, numbers and underscores only. Value will be automatically prepended with "wwlc_cf_"', 'woocommerce-wholesale-lead-capture' ),
                            'desc_tip'       => __( 'Example: wwlc_cf_your_field_id', 'woocommerce-wholesale-lead-capture' ),
                            'default'        => '',
                            'disable_edit'   => true,
                            'prefix'         => 'wwlc_cf_',
                            'exclude_prefix' => array(
                                'first_name',
                                'last_name',
                                'user_email',
                                'wwlc_username',
                                'wwlc_password',
                                'wwlc_company_name',
                                'wwlc_phone',
                                'address',
                            ),
                        ),
                        array(
                            'type'           => 'select',
                            'label'          => __( 'Field Type', 'woocommerce-wholesale-lead-capture' ),
                            'id'             => 'field_type',
                            'default'        => 'text',
                            'with_condition' => true,
                            'edit_options'   => array(
                                'password' => __( 'Password', 'woocommerce-wholesale-lead-capture' ),
                                'address'  => __( 'Address', 'woocommerce-wholesale-lead-capture' ),
                            ),
                            'options'        => array(
                                'text'             => __( 'Text', 'woocommerce-wholesale-lead-capture' ),
                                'textarea'         => __( 'Text Area', 'woocommerce-wholesale-lead-capture' ),
                                'number'           => __( 'Number', 'woocommerce-wholesale-lead-capture' ),
                                'password'         => __( 'Password', 'woocommerce-wholesale-lead-capture' ),
                                'email'            => __( 'Email', 'woocommerce-wholesale-lead-capture' ),
                                'phone'            => __( 'Phone', 'woocommerce-wholesale-lead-capture' ),
                                'url'              => __( 'Url', 'woocommerce-wholesale-lead-capture' ),
                                'select'           => __( 'Select', 'woocommerce-wholesale-lead-capture' ),
                                'radio'            => __( 'Radio', 'woocommerce-wholesale-lead-capture' ),
                                'checkbox'         => __( 'Checkbox', 'woocommerce-wholesale-lead-capture' ),
                                'hidden'           => __( 'Hidden', 'woocommerce-wholesale-lead-capture' ),
                                'file'             => __( 'File', 'woocommerce-wholesale-lead-capture' ),
                                'content'          => __( 'Content', 'woocommerce-wholesale-lead-capture' ),
                                'address'          => __( 'Address', 'woocommerce-wholesale-lead-capture' ),
                                'terms_conditions' => __( 'Terms & Conditions', 'woocommerce-wholesale-lead-capture' ),
                            ),
                        ),
                        array(
                            'type'      => 'options',
                            'label'     => __( 'Options', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'options',
                            'default'   => array(),
                            'hide'      => true,
                            'btn_label' => __( 'Add Option', 'woocommerce-wholesale-lead-capture' ),
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'select',
                                        'radio',
                                        'checkbox',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'number',
                            'label'     => __( 'Min:', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'attributes_min',
                            'hide'      => true,
                            'default'   => 0,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'number',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'number',
                            'label'     => __( 'Max:', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'attributes_max',
                            'hide'      => true,
                            'default'   => 0,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'number',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'number',
                            'label'     => __( 'Step:', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'attributes_step',
                            'hide'      => true,
                            'default'   => 0,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'number',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'select',
                            'label'     => __( 'Phone mask/pattern', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'phone_pattern',
                            'default'   => 'No format',
                            'hide'      => true,
                            'options'   => array(
								'No format'         => __( 'No format', 'woocommerce-wholesale-lead-capture' ),
								'(000) 000-0000'    => '(000) 000-0000',
								'+00 (000) 000-000' => '+00 (000) 000-000',
								'0 000 000 0000'    => '0 000 000 0000',
								'+0 000 000-000'    => '+0 000 000-000',
								'0-000-000-0000'    => '0-000-000-0000',
								'0 (000) 000-0000'  => '0 (000) 000-0000',
								'000-000-0000'      => '000-000-0000',
								'000.000.0000'      => '000.000.0000',
								'(00) 0000 0000'    => '(00) 0000 0000',
								'0000 000 000'      => '0000 000 000',
							),
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'phone',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'text',
                            'label'     => __( 'Placeholder', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'field_placeholder',
                            'default'   => '',
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'text',
                                        'textarea',
                                        'number',
                                        'email',
                                        'password',
                                        'url',
                                        'address',
                                        'phone',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'text',
                            'label'     => __( 'Default Value', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'default_value',
                            'default'   => '',
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'text',
                                        'textarea',
                                        'number',
                                        'email',
                                        'url',
                                        'hidden',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'text',
                            'label'     => __( 'Address Line 2 Placeholder', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'address2_placeholder',
                            'default'   => '',
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'address',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'checkbox',
                            'label'     => __( 'Add Address Line 2 Label.', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'enable_address2_label',
                            'multiple'  => false,
                            'hide'      => true,
                            'default'   => 'no',
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'address',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'text',
                            'label'     => __( 'Town/City Placeholder', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'city_placeholder',
                            'default'   => '',
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'address',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'text',
                            'label'     => __( 'State/County Placeholder', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'state_placeholder',
                            'default'   => '',
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'address',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'text',
                            'label'     => __( 'Postcode/Zip Placeholder', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'postcode_placeholder',
                            'default'   => '',
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'address',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'textarea',
                            'label'     => __( 'Content to display', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'content_display',
                            'default'   => '',
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'content',
                                    ),
                                ),
                            ),
                            'editor'    => true,
                        ),
                        array(
                            'type'      => 'textarea',
                            'label'     => __( 'Term and Condition Content', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'term_content_display',
                            'default'   => '',
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'terms_conditions',
                                    ),
                                ),
                            ),
                            'editor'    => true,
                        ),
                        array(
                            'type'        => 'text',
                            'label'       => __( 'Allowed File Types', 'woocommerce-wholesale-lead-capture' ),
                            'id'          => 'field_allowed_filetypes',
                            'default'     => __( 'doc,docx,xls,xlsx,pdf,jpg,png,gif,txt', 'woocommerce-wholesale-lead-capture' ),
                            'description' => __( 'Type in extension of allowed file types separated by comma', 'woocommerce-wholesale-lead-capture' ),
                            'hide'        => true,
                            'condition'   => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'file',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'        => 'number',
                            'label'       => __( 'Maximum Allowed File Size', 'woocommerce-wholesale-lead-capture' ),
                            'id'          => 'max_allowed_file_size',
                            'default'     => '20',
                            'description' => __( 'Enter a value in megabytes', 'woocommerce-wholesale-lead-capture' ),
                            'hide'        => true,
                            'condition'   => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'file',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'checkbox',
                            'label'     => __( 'Add Password Confirmation Field', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'enable_confirm',
                            'multiple'  => false,
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'password',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'checkbox',
                            'label'     => __( 'Strong password', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'password_strength',
                            'multiple'  => false,
                            'hide'      => true,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'password',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'checkbox',
                            'label'     => __( 'Show on Checkout Page', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'checkout_display',
                            'multiple'  => false,
                            'default'   => 'no',
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'text',
                                        'textarea',
                                        'number',
                                        'email',
                                        'url',
                                        'select',
                                        'radio',
                                        'checkbox',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'checkbox',
                            'label'     => __( 'Required', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'required',
                            'multiple'  => false,
                            'default'   => 'no',
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'text',
                                        'textarea',
                                        'number',
                                        'email',
                                        'password',
                                        'phone',
                                        'url',
                                        'select',
                                        'radio',
                                        'checkbox',
                                        'file',
                                        'address',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'      => 'checkbox',
                            'label'     => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
                            'id'        => 'enabled',
                            'default'   => 'no',
                            'multiple'  => false,
                            'condition' => array(
                                array(
                                    'key'   => 'field_type',
                                    'value' => array(
                                        'text',
                                        'textarea',
                                        'number',
                                        'password',
                                        'email',
                                        'phone',
                                        'url',
                                        'select',
                                        'radio',
                                        'checkbox',
                                        'hidden',
                                        'file',
                                        'content',
                                        'address',
                                        'terms_conditions',
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => false,
                        'editable'      => true,
                        'can_delete'    => true,
                        'drag_and_drop' => true,
                        'inline_edit'   => false,
                        'edit_action'   => 'group_registration_fields_mapping_edit',
                        'delete_action' => 'group_registration_fields_mapping_delete',
                        'sort_action'   => 'group_registration_fields_mapping_sort',
                        'show_in_table' => array(
                            'field_name',
                            'field_id',
                            'field_type',
                            'required',
                            'enabled',
                            'checkout_display',
                            'operation',
                        ),
                        'fields'        => array(
                            array(
                                'title'     => __( 'Field Name', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'field_name',
                                'field'     => 'field_name',
                                'key'       => 'field_name',
                            ),
                            array(
                                'title'     => __( 'Field ID', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'field_id',
                                'field'     => 'field_id',
                                'key'       => 'field_id',
                            ),
                            array(
                                'title'     => __( 'Field Type', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'field_type',
                                'field'     => 'field_type',
                                'key'       => 'field_type',
                            ),
                            array(
                                'title'     => __( 'Required', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'required',
                                'field'     => 'required',
                                'key'       => 'required',
                            ),
                            array(
                                'title'     => __( 'Enabled', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'enabled',
                                'field'     => 'enabled',
                                'key'       => 'enabled',
                            ),
                            array(
                                'title'     => __( 'Show on checkout', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'checkout_display',
                                'field'     => 'checkout_display',
                                'key'       => 'checkout_display',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-lead-capture' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_fields_data,
                    ),
                    'button_label' => __( 'Add Custom Field', 'woocommerce-wholesale-lead-capture' ),
                ),
            );

            return $registration_controls;
        }

        /**
         * Email Options tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function email_options_tab_controls() {
            $email_option_controls = array();

            // Email Options - Section.
            $email_allow_managing_of_users = get_option( 'wwlc_email_allow_managing_of_users' );
            $emails_main_recipient         = get_option( 'wwlc_emails_main_recipient' );
            $emails_cc                     = get_option( 'wwlc_emails_cc' );
            $emails_bcc                    = get_option( 'wwlc_emails_bcc' );

            $email_option_controls['email_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Allow managing of users via email', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_email_allow_managing_of_users',
                    'input_label' => __( 'If enabled, the admin will see accept and reject action links in their email. Available on HTML email type only.', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $email_allow_managing_of_users,
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Admin Email Recipient', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_emails_main_recipient',
                    'description' => __( 'If blank, then WordPress admin email will be used', 'woocommerce-wholesale-lead-capture' ),
                    'default'     => ( $emails_main_recipient ) ? $emails_main_recipient : '',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Carbon Copy (CC)', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_emails_cc',
                    'description' => __( 'If blank, then WordPress admin email will be used', 'woocommerce-wholesale-lead-capture' ),
                    'default'     => ( $emails_cc ) ? $emails_cc : '',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Blind Carbon Copy (BCC)', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_emails_bcc',
                    'description' => __( 'If blank, then WordPress admin email will be used', 'woocommerce-wholesale-lead-capture' ),
                    'default'     => ( $emails_bcc ) ? $emails_bcc : '',
                ),
            );

            return $email_option_controls;
        }

        /**
         * Security Options tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function security_options_tab_controls() {
            $security_option_controls = array();

            // Security Options - Section.
            $security_enable_recaptcha     = get_option( 'wwlc_security_enable_recaptcha' );
            $security_recaptcha_type       = get_option( 'wwlc_security_recaptcha_type' );
            $security_recaptcha_type       = ! empty( $security_recaptcha_type ) ? $security_recaptcha_type : 'v2_im_not_a_robot';
            $security_recaptcha_site_key   = get_option( 'wwlc_security_recaptcha_site_key' );
            $security_recaptcha_secret_key = get_option( 'wwlc_security_recaptcha_secret_key' );

            $security_enable_dec = sprintf(
            // translators: %1$s link to recaptcha, %2$s </a> tag.
                __(
                    'If checked, this will add the recaptcha field on the registration form. You can get your Recaptcha keys by going to %1$shttps://www.google.com/recaptcha/%2$s.',
                    'woocommerce-wholesale-lead-capture'
                ),
                '<a target="_blank" href="https://www.google.com/recaptcha/"> ',
                '</a>',
            );

            $security_option_controls['security_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable Recaptcha', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_security_enable_recaptcha',
                    'input_label' => $security_enable_dec,
                    'multiple'    => false,
                    'default'     => $security_enable_recaptcha,
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'reCAPTCHA type', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_security_recaptcha_type',
                    'description' => __( 'Select your reCAPTCHA type. Make sure to use site key and secret key for your selected type.', 'woocommerce-wholesale-lead-capture' ),
                    'options'     => array(
                        'v2_im_not_a_robot' => __( 'V2 I\'m not a robot', 'woocommerce-wholesale-lead-capture' ),
                        'v2_invisible'      => __( 'V2 Invisible', 'woocommerce-wholesale-lead-capture' ),
                    ),
                    'default'     => $security_recaptcha_type,
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Recaptcha site key', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_security_recaptcha_site_key',
                    'description' => '',
                    'default'     => ( $security_recaptcha_site_key ) ? $security_recaptcha_site_key : '',
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Recaptcha secret key', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_security_recaptcha_secret_key',
                    'description' => '',
                    'default'     => ( $security_recaptcha_secret_key ) ? $security_recaptcha_secret_key : '',
                ),
            );

            return $security_option_controls;
        }

        /**
         * Help Options tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function help_options_tab_controls() {
            $help_option_controls = array();

            // Help Options - Section.
            $settings_help_clean_plugin_options_on_uninstall = get_option( 'wwlc_settings_help_clean_plugin_options_on_uninstall' );

            $help_option_controls['help_options'] = array(
                array(
                    'type'         => 'button',
                    'label'        => __( 'Create Necessary Pages', 'woocommerce-wholesale-lead-capture' ),
                    'id'           => 'wwlc_create_lead_pages',
                    'button_label' => __( 'Create Lead Pages', 'woocommerce-wholesale-lead-capture' ),
                    'action'       => 'create_lead_pages',
                    'desc_tip'     => __( 'Registration, Log In Form and Thank You Page', 'woocommerce-wholesale-lead-capture' ),
                    'confirmation' => false,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Clean up plugin options on un-installation', 'woocommerce-wholesale-lead-capture' ),
                    'id'          => 'wwlc_settings_help_clean_plugin_options_on_uninstall',
                    'input_label' => __( 'If checked, removes all plugin options when this plugin is uninstalled. <b>Warning:</b> This process is irreversible.', 'woocommerce-wholesale-lead-capture' ),
                    'multiple'    => false,
                    'default'     => $settings_help_clean_plugin_options_on_uninstall,
                ),
                array(
                    'type'         => 'button',
                    'label'        => __( 'Refetch Plugin Update Data', 'woocommerce-wholesale-lead-capture' ),
                    'id'           => 'wwlc_force_fetch_update_data',
                    'button_label' => __( 'Refetch Plugin Update Data', 'woocommerce-wholesale-lead-capture' ),
                    'action'       => 'force_fetch_update_data',
                    'description'  => __( 'This will refetch the plugin update data. Useful for debugging failed plugin update operations.', 'woocommerce-wholesale-lead-capture' ),
                    'confirmation' => true,
                ),
            );

            return $help_option_controls;
        }

        /**
         * Sanitizes field options preserving HTML in content fields.
         *
         * @param mixed $options Field's options.
         *
         * @return string[]
         */
        public function sanitize_custom_field( $options ) {
            return array_map(
                function ( $value ) use ( $options ) {
                    // If this is a content or terms_conditions field's content, preserve HTML.
                    if (
                        isset( $options['field_type'] )
                        && in_array( $options['field_type'], array( 'content', 'terms_conditions' ), true )
                        && in_array( $value, array( $options['content_display'], $options['term_content_display'] ), true )
                    ) {
                        return wp_kses_post( $value );
                    }

                    // Otherwise sanitize as text.
                    return sanitize_text_field( $value );
                },
                $options
            );
        }

        /**
         * Group form settings mapping save.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @since  2.0.1 Custom sanitization function that preserves HTML in content fields.
         * @access public
         *
         * @return array
         */
        public function group_registration_fields_mapping_save( $options ) {
            // Remove action.
            unset( $options['action'] );

            $custom_field = $this->sanitize_custom_field( $options );

            $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

            // Format options.
            if ( isset( $custom_field['options'] ) ) {
                $custom_field['options'] = ! empty( $custom_field['options'] ) ? json_decode( $custom_field['options'], true ) : array();
            }

            // Validate required fields.
            $validate_msg = array();
            if ( empty( $custom_field['field_name'] ) ) {
                $validate_msg[] = __( 'Field Name', 'woocommerce-wholesale-lead-capture' );
            }
            if ( empty( $custom_field['field_id'] ) ) {
                $validate_msg[] = __( 'Field ID', 'woocommerce-wholesale-lead-capture' );
            }
            if ( empty( $custom_field['field_type'] ) ) {
                $validate_msg[] = __( 'Field Type', 'woocommerce-wholesale-lead-capture' );
            } elseif ( 'select' === $custom_field['field_type'] || 'radio' === $custom_field['field_type'] || 'checkbox' === $custom_field['field_type'] ) {
                if ( empty( $custom_field['options'] ) ) {
                    $validate_msg[] = __( 'Options', 'woocommerce-wholesale-lead-capture' );
                }
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-lead-capture' ), implode( ', ', $validate_msg ) ),
                );
            } else {
                $field_id = $custom_field['field_id'];
                unset( $custom_field['field_id'] );

                // Strip extra slashes before saving.
                $custom_field = wwlc_strip_slashes( $custom_field );

                $field_id = str_replace( 'wwlc_cf_', '', $field_id );
                $field_id = 'wwlc_cf_' . $field_id;

                if ( ! ctype_alnum( str_replace( '_', '', $field_id ) ) ) {
                    $response = array(
                        'status'  => 'error',
                        /* translators: %1$s field ID */
                        'message' => sprintf( __( 'Field id %1$s contains none alpha numeric character/s', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                    );
                } elseif ( ! array_key_exists( $field_id, $registration_form_custom_fields ) ) {

                    // convert field value to boolean.
                    $custom_field['enabled']          = ( ! empty( $custom_field['enabled'] ) && 'yes' === $custom_field['enabled'] ) ? '1' : '0';
                    $custom_field['required']         = ( ! empty( $custom_field['required'] ) && 'yes' === $custom_field['required'] ) ? '1' : '0';
                    $custom_field['checkout_display'] = ( ! empty( $custom_field['checkout_display'] ) && 'yes' === $custom_field['checkout_display'] ) ? '1' : '0';

                    // Conditional fields.
                    if ( 'number' === $custom_field['field_type'] ) {
                        $custom_field['attributes']['min']  = ! empty( $custom_field['attributes_min'] ) ? $custom_field['attributes_min'] : '';
                        $custom_field['attributes']['max']  = ! empty( $custom_field['attributes_max'] ) ? $custom_field['attributes_max'] : '';
                        $custom_field['attributes']['step'] = ! empty( $custom_field['attributes_step'] ) ? $custom_field['attributes_step'] : '';
                    }

                    if ( 'hidden' === $custom_field['field_type'] ) {
                        $custom_field['field_placeholder'] = ! empty( $custom_field['default_value'] ) ? $custom_field['default_value'] : '';
                    }

                    if ( 'content' === $custom_field['field_type'] ) {
                        $custom_field['default_value'] = ! empty( $custom_field['content_display'] ) ? $custom_field['content_display'] : '';
                    }

                    if ( 'terms_conditions' === $custom_field['field_type'] ) {
                        $custom_field['default_value'] = ! empty( $custom_field['term_content_display'] ) ? $custom_field['term_content_display'] : '';
                    }

                    if ( 'file' === $custom_field['field_type'] ) {
                        $custom_field['field_allowed_filetypes'] = ! empty( $custom_field['field_allowed_filetypes'] ) ? $custom_field['field_allowed_filetypes'] : 'doc,docx,xls,xlsx,pdf,jpg,png,gif,txt';
                        $custom_field['max_allowed_file_size']   = ! empty( $custom_field['max_allowed_file_size'] ) ? $custom_field['max_allowed_file_size'] : '20';
                    }

                    // Set to 0 to avoid conflict with built-in fields.
                    $custom_field['field_order'] = 0;

                    // Saved custom fields.
                    $registration_form_custom_fields[ $field_id ] = $custom_field;

                    update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );

                    $response = array(
                        'status'  => 'success',
                        'message' => __( 'Custom field saved successfully!', 'woocommerce-wholesale-lead-capture' ),
                    );
                } else {
                    $response = array(
                        'status'  => 'error',
                        /* translators: %1$s field ID */
                        'message' => sprintf( __( 'Duplicate field, %1$s already exists.', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group form settings mapping edit.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_registration_fields_mapping_edit( $options ) {
            // Remove action.
            unset( $options['action'] );

            $custom_field                    = isset( $options['field_type'] ) && 'content' !== $options['field_type'] ? array_map( 'sanitize_text_field', $options ) : $options;
            $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            $excluded_field_type_req_ids     = array(
                'terms_and_conditions',
            );

            // Format options.
            if ( isset( $custom_field['options'] ) ) {
                $custom_field['options'] = ! empty( $custom_field['options'] ) ? json_decode( $custom_field['options'], true ) : array();
            }

            // Get field id.
            $field_id = $custom_field['field_id'];

            // Validate required fields.
            $validate_msg = array();
            if ( empty( $custom_field['field_name'] ) ) {
                $validate_msg[] = __( 'Field Name', 'woocommerce-wholesale-lead-capture' );
            }
            if ( empty( $custom_field['field_type'] ) && ! in_array( $field_id, $excluded_field_type_req_ids, true ) ) {
                $validate_msg[] = __( 'Field Type', 'woocommerce-wholesale-lead-capture' );
            } elseif ( 'select' === $custom_field['field_type'] || 'radio' === $custom_field['field_type'] || 'checkbox' === $custom_field['field_type'] ) {
                if ( empty( $custom_field['options'] ) ) {
                    $validate_msg[] = __( 'Options', 'woocommerce-wholesale-lead-capture' );
                }
            }

            if ( ! empty( $validate_msg ) ) {
                $response = array(
                    'status'  => 'error',
                    /* translators: %s: required fields */
                    'message' => sprintf( _n( '%s is required', '%s are required', count( $validate_msg ), 'woocommerce-wholesale-lead-capture' ), implode( ', ', $validate_msg ) ),
                );
            } else {
                unset( $custom_field['field_id'] );

                // Strip extra slashes before updating.
                $custom_field = wwlc_strip_slashes( $custom_field );

                if ( ! in_array( $field_id, $this->get_built_in_fields(), true ) ) {
                    $field_id = str_replace( 'wwlc_cf_', '', $field_id );
                    $field_id = 'wwlc_cf_' . $field_id;
                }

                // Unset field order.
                unset( $custom_field['field_order'] );

                $edited = true;
                if ( array_key_exists( $field_id, $registration_form_custom_fields ) ) {

                    // convert field value to boolean.
                    $custom_field['enabled']          = ( ! empty( $custom_field['enabled'] ) && 'yes' === $custom_field['enabled'] ) ? '1' : '0';
                    $custom_field['required']         = ( ! empty( $custom_field['required'] ) && 'yes' === $custom_field['required'] ) ? '1' : '0';
                    $custom_field['checkout_display'] = ( ! empty( $custom_field['checkout_display'] ) && 'yes' === $custom_field['checkout_display'] ) ? '1' : '0';

                    // Conditional fields.
                    if ( 'number' === $custom_field['field_type'] ) {
                        $custom_field['attributes']['min']  = ! empty( $custom_field['attributes_min'] ) ? $custom_field['attributes_min'] : '';
                        $custom_field['attributes']['max']  = ! empty( $custom_field['attributes_max'] ) ? $custom_field['attributes_max'] : '';
                        $custom_field['attributes']['step'] = ! empty( $custom_field['attributes_step'] ) ? $custom_field['attributes_step'] : '';
                    }

                    if ( 'hidden' === $custom_field['field_type'] ) {
                        $custom_field['field_placeholder'] = ! empty( $custom_field['default_value'] ) ? $custom_field['default_value'] : '';
                    }

                    if ( 'content' === $custom_field['field_type'] || 'terms_conditions' === $custom_field['field_type'] ) {
                        $custom_field['default_value'] = ! empty( $custom_field['content_display'] ) ? $custom_field['content_display'] : '';
                    }

                    // Set to 0 to avoid conflict with built-in fields.
                    $custom_field['field_order'] = ! empty( $registration_form_custom_fields[ $field_id ]['field_order'] ) ? $registration_form_custom_fields[ $field_id ]['field_order'] : 0;

                    // Saved custom fields.
                    $registration_form_custom_fields[ $field_id ] = $custom_field;
                    update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );
                } else { // phpcs:ignore
                    // Update built-in fields.
                    if ( 'first_name' === $field_id ) {
                        update_option( 'wwlc_fields_first_name_field_placeholder', $custom_field['field_placeholder'] );
                    } elseif ( 'last_name' === $field_id ) {
                        update_option( 'wwlc_fields_last_name_field_placeholder', $custom_field['field_placeholder'] );
                    } elseif ( 'wwlc_phone' === $field_id ) {
                        update_option( 'wwlc_fields_require_phone_field', $custom_field['required'] );
                        update_option( 'wwlc_fields_phone_field_placeholder', $custom_field['field_placeholder'] );
                        update_option( 'wwlc_fields_phone_mask_pattern', ! empty( $custom_field['phone_pattern'] ) ? $custom_field['phone_pattern'] : 'No format' );
                    } elseif ( 'user_email' === $field_id ) {
                        update_option( 'wwlc_fields_email_field_placeholder', $custom_field['field_placeholder'] );
                    } elseif ( 'wwlc_username' === $field_id ) {
                        update_option( 'wwlc_fields_username_placeholder', $custom_field['field_placeholder'] );
                        update_option( 'wwlc_fields_username_active', $custom_field['enabled'] );
                    } elseif ( 'wwlc_company_name' === $field_id ) {
                        update_option( 'wwlc_fields_company_field_placeholder', $custom_field['field_placeholder'] );
                        update_option( 'wwlc_fields_activate_company_name_field', $custom_field['enabled'] );
                        update_option( 'wwlc_fields_require_company_name_field', $custom_field['required'] );
                    } elseif ( 'address' === $field_id ) {
                        update_option( 'wwlc_fields_activate_address_field', $custom_field['enabled'] );
                        update_option( 'wwlc_fields_require_address_field', $custom_field['required'] );
                        update_option( 'wwlc_fields_enable_address2_label', ! empty( $custom_field['enable_address2_label'] ) ? $custom_field['enable_address2_label'] : '' );
                        update_option( 'wwlc_fields_address_placeholder', $custom_field['field_placeholder'] );
                        update_option( 'wwlc_fields_address2_placeholder', ! empty( $custom_field['address2_placeholder'] ) ? $custom_field['address2_placeholder'] : '' );
                        update_option( 'wwlc_fields_city_placeholder', ! empty( $custom_field['city_placeholder'] ) ? $custom_field['city_placeholder'] : '' );
                        update_option( 'wwlc_fields_state_placeholder', ! empty( $custom_field['state_placeholder'] ) ? $custom_field['state_placeholder'] : '' );
                        update_option( 'wwlc_fields_postcode_placeholder', ! empty( $custom_field['postcode_placeholder'] ) ? $custom_field['postcode_placeholder'] : '' );
                    } elseif ( 'wwlc_password' === $field_id ) {
                        update_option( 'wwlc_fields_password_field_placeholder', $custom_field['field_placeholder'] );
                        update_option( 'wwlc_fields_activate_password_field', $custom_field['enabled'] );
                        update_option( 'wwlc_fields_require_password_field', $custom_field['required'] );
                        update_option( 'wwlc_fields_enable_confirm_password_field', ! empty( $custom_field['enable_confirm'] ) ? $custom_field['enable_confirm'] : '' );
                        update_option( 'wwlc_fields_minimum_password_strength_field', ! empty( $custom_field['password_strength'] ) ? $custom_field['password_strength'] : '' );
                    } else {
                        $edited = false;
                    }
                }

                if ( $edited ) {
                    $response = array(
                        'status'  => 'success',
                        'message' => __( 'Custom field updated successfully!', 'woocommerce-wholesale-lead-capture' ),
                    );
                } else {
                    $response = array(
                        'status'  => 'error',
                        /* translators: %1$s field ID */
                        'message' => sprintf( __( 'Duplicate field, %1$s already exists.', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                    );
                }
            }

            return $response;
        }

        /**
         * Group form settings mapping delete.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_registration_fields_mapping_delete( $options ) {
            // Remove action.
            unset( $options['action'] );

            $custom_field                    = array_map( 'sanitize_text_field', $options );
            $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

            $field_id = $custom_field['field_id'];

            if ( array_key_exists( $field_id, $registration_form_custom_fields ) ) {
                unset( $registration_form_custom_fields[ $field_id ] );
                update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Custom field deleted successfully!', 'woocommerce-wholesale-lead-capture' ),
                );
            } else {
                $response = array(
                    'status'  => 'error',
                    /* translators: %1$s field ID */
                    'message' => sprintf( __( 'Field %1$s does not exists.', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                );
            }

            return $response;
        }

        /**
         * Get built-in fields.
         *
         * @since  2.0
         * @access private
         *
         * @return array
         */
        private function get_built_in_fields() {
            return array(
                'first_name',
                'last_name',
                'wwlc_phone',
                'user_email',
                'wwlc_username',
                'wwlc_company_name',
                'address',
                'wwlc_password',
            );
        }

        /**
         * Group form settings mapping sort.
         *
         * @param array $options Options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function group_registration_fields_mapping_sort( $options ) {
            // Remove action.
            unset( $options['action'] );

            $sort_fields                     = $options;
            $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            $new_list_order                  = array();

            if ( ! empty( $sort_fields['list'] ) ) {
                foreach ( $sort_fields['list'] as $index => $sort_field ) {
                    $sort_order = $index + 1;
                    $field_id   = $sort_field['field_id'];

                    // Set new field order.
                    $sort_field['field_order'] = $sort_order;

                    if ( array_key_exists( $field_id, $registration_form_custom_fields ) ) {
                        $registration_form_custom_fields[ $field_id ]['field_order'] = $sort_order;
                    }

                    // Sort built-in fields.
                    $value = wc_clean( $sort_order );
                    if ( 'first_name' === $field_id ) {
                        update_option( 'wwlc_fields_first_name_field_order', $value );
                    } elseif ( 'last_name' === $field_id ) {
                        update_option( 'wwlc_fields_last_name_field_order', $value );
                    } elseif ( 'wwlc_phone' === $field_id ) {
                        update_option( 'wwlc_fields_phone_field_order', $value );
                    } elseif ( 'user_email' === $field_id ) {
                        update_option( 'wwlc_fields_email_field_order', $value );
                    } elseif ( 'wwlc_username' === $field_id ) {
                        update_option( 'wwlc_fields_username_order', $value );
                    } elseif ( 'wwlc_company_name' === $field_id ) {
                        update_option( 'wwlc_fields_company_name_field_order', $value );
                    } elseif ( 'address' === $field_id ) {
                        update_option( 'wwlc_fields_address_field_order', $value );
                    } elseif ( 'wwlc_password' === $field_id ) {
                        update_option( 'wwlc_fields_password_field_order', $value );
                    }

                    $new_list_order[] = $sort_field;
                }
            }

            update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );

            $response = array(
                'status'  => 'success',
                'message' => __( 'Custom fields sorted!', 'woocommerce-wholesale-lead-capture' ),
                'list'    => $new_list_order,
            );

            return $response;
        }

        /**
         * Force fetch the latest update data from our server.
         *
         * @since 1.16.2
         * @access public
         */
        public function wwlc_force_fetch_update_data() {
            /**
             * Force check and fetch the update data of the plugin.
             * Will save the update data into the WWLC_UPDATE_DATA option.
             */
            $this->ping_for_new_version( true ); // Force check.

            /**
             * Get the update data from the WWLC_UPDATE_DATA option.
             * Returned data is pre-formatted.
             */
            $update_data       = $this->inject_plugin_update(); // Inject new update data if there are any.
            $installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION ) : get_option( WWLC_OPTION_INSTALLED_VERSION );

            /**
             * Get wp update transient data.
             * Automatically unserializes the returned value.
             */
            $update_transient = is_multisite() ? get_site_option( '_site_transient_update_plugins', false ) : get_option( '_site_transient_update_plugins', false );

            // If the plugin is up to date then put the plugin in no update.
            if ( $update_data && isset( $update_data['value'] ) && version_compare( $update_data['value']->new_version, $installed_version, '==' ) ) {

                unset( $update_transient->response[ $update_data['key'] ] );
                $update_transient->no_update[ $update_data['key'] ] = $update_data['value'];

            } elseif ( $update_data && $update_transient && isset( $update_transient->response ) && is_array( $update_transient->response ) ) {

                // Inject into the wp update data our latest plugin update data.
                $update_transient->response[ $update_data['key'] ] = $update_data['value'];

            }

            // Update wp update data transient.
            if ( is_multisite() ) {
                update_site_option( '_site_transient_update_plugins', $update_transient );
            } else {
                update_option( '_site_transient_update_plugins', $update_transient );
            }

            $response = array(
                'status'  => 'success',
                'message' => __( 'Successfully re-fetch plugin update data!', 'woocommerce-wholesale-lead-capture' ),
            );

            return $response;
        }

        /**
         * Ping plugin for new version. Ping static file first, if indeed new version is available, trigger update data request.
         *
         * @param bool $force Flag to determine whether to "forcefully" fetch the latest update data from the server.
         *
         * @since 1.11
         * @since 1.16.2 We added new parameter $force. This will serve as a flag if we are going to "forcefully" fetch the latest update data from the server.
         * @access public
         */
        public function ping_for_new_version( $force = false ) {

            $license_activated = is_multisite() ? get_site_option( WWLC_LICENSE_ACTIVATED ) : get_option( WWLC_LICENSE_ACTIVATED );

            if ( 'yes' !== $license_activated ) {

                if ( is_multisite() ) {
                    delete_site_option( WWLC_UPDATE_DATA );
                } else {
                    delete_option( WWLC_UPDATE_DATA );
                }

                return;

            }

            $retrieving_update_data = is_multisite() ? get_site_option( WWLC_RETRIEVING_UPDATE_DATA ) : get_option( WWLC_RETRIEVING_UPDATE_DATA );
            if ( 'yes' === $retrieving_update_data ) {
                return;
            }

            /**
             * Only attempt to get the existing saved update data when the operation is not forced.
             * Else, if it is forced, we ignore the existing update data if any
             * and forcefully fetch the latest update data from our server.
             *
             * @since 1.16.2
             */
            if ( ! $force ) {
                $update_data = is_multisite() ? get_site_option( WWLC_UPDATE_DATA ) : get_option( WWLC_UPDATE_DATA );
            } else {
                $update_data = null;
            }

            /**
             * Even if the update data is still valid, we still go ahead and do static json file ping.
             * The reason is on WooCommerce 3.3.x , it seems WooCommerce do not regenerate the download url every time you change the downloadable zip file on WooCommerce store.
             * The side effect is, the download url is still valid, points to the latest zip file, but the update info could be outdated coz we check that if the download url
             * is still valid, we don't check for update info, and since the download url will always be valid even after subsequent release of the plugin coz WooCommerce is reusing the url now
             * then there will be a case our update info is outdated. So that is why we still need to check the static json file, even if update info is still valid.
             */

            $option = apply_filters(
                'wwlc_plugin_new_version_ping_options',
                array(
                    'timeout' => 10, // seconds coz only static json file ping.
                    'headers' => array(
                        'Accept' => 'application/json',
                    ),
                )
            );

            $response = wp_remote_retrieve_body( wp_remote_get( apply_filters( 'wwlc_plugin_new_version_ping_url', WWLC_STATIC_PING_FILE ), $option ) );

            if ( ! empty( $response ) ) {

                $response = json_decode( $response );

                if ( ! empty( $response ) && property_exists( $response, 'version' ) ) {

                    $installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION ) : get_option( WWLC_OPTION_INSTALLED_VERSION );

                    if ( ( ! $update_data && version_compare( $response->version, $installed_version, '>' ) ) ||
                        ( $update_data && version_compare( $response->version, $update_data->latest_version, '>' ) ) ) {

                        if ( is_multisite() ) {
                            update_site_option( WWLC_RETRIEVING_UPDATE_DATA, 'yes' );
                        } else {
                            update_option( WWLC_RETRIEVING_UPDATE_DATA, 'yes' );
                        }

                        // Fetch software product update data.
                        if ( is_multisite() ) {
                            $this->_fetch_software_product_update_data( get_site_option( WWLC_OPTION_LICENSE_EMAIL ), get_site_option( WWLC_OPTION_LICENSE_KEY ), home_url() );
                        } else {
                            $this->_fetch_software_product_update_data( get_option( WWLC_OPTION_LICENSE_EMAIL ), get_option( WWLC_OPTION_LICENSE_KEY ), home_url() );
                        }

                        if ( is_multisite() ) {
                            delete_site_option( WWLC_RETRIEVING_UPDATE_DATA );
                        } else {
                            delete_option( WWLC_RETRIEVING_UPDATE_DATA );
                        }
                    }
                }
            }
        }

        /**
         * When WordPress fetch 'update_plugins' transient ( Which holds various data regarding plugins, including which have updates ),
         * we inject our plugin update data in, if any. It is saved on WWLC_UPDATE_DATA option.
         * It is important we dont delete this option until the plugin have successfully updated.
         * The reason is we are hooking ( and we should do it this way ), on transient read.
         * So if we delete this option on first transient read, then subsequent read will not include our plugin update data.
         *
         * It also checks the validity of the update url. There could be edge case where we stored the update data locally as an option,
         * then later on the store, the product was deleted or any action occurred that would deem the update data invalid.
         * So we check if update url is still valid, if not, we remove the locally stored update data.
         *
         * @since 1.11
         * Refactor codebase to adapt being called on set_site_transient function.
         * We don't need to check for software update data validity as its already been checked on ping_for_new_version
         * and this function is immediately called right after that.
         * @access public
         *
         * @return array Filtered plugin updates data.
         */
        public function inject_plugin_update() {

            $license_activated = is_multisite() ? get_site_option( WWLC_LICENSE_ACTIVATED ) : get_option( WWLC_LICENSE_ACTIVATED );
            if ( 'yes' !== $license_activated ) {
                return false;
            }

            $software_update_data = is_multisite() ? get_site_option( WWLC_UPDATE_DATA ) : get_option( WWLC_UPDATE_DATA );

            if ( $software_update_data ) {

                $update = new \stdClass();

                $update->id                   = $software_update_data->download_id;
                $update->slug                 = 'woocommerce-wholesale-lead-capture';
                $update->plugin               = WWLC_PLUGIN_BASE_NAME;
                $update->new_version          = $software_update_data->latest_version;
                $update->url                  = WWLC_PLUGIN_SITE_URL;
                $update->package              = $software_update_data->download_url;
                $update->tested               = $software_update_data->tested_up_to;
                $update->{'update-supported'} = true;
                $update->update               = false;
                $update->icons                = array(
                    '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-128x128.jpg',
                    '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-256x256.jpg',
                    'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-256x256.jpg',
                );

                $update->banners = array(
                    '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-772x250.jpg',
                    '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-1544x500.jpg',
                    'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-1544x500.jpg',
                );

                return array(
                    'key'   => WWLC_PLUGIN_BASE_NAME,
                    'value' => $update,
                );

            }

            return false;
        }

        /**
         * Create lead pages. Necessary pages for the plugin to work correctly.
         *
         * @return bool
         * @since 1.0.0
         * @since 1.10 Show an error that there pages in the trash that contains the login and registration shortcode
         */
        public function wwlc_create_lead_pages() {

            $registration_page_creation_status = $this->wwlc_create_registration_page();
            $log_in_page_creation_status       = $this->wwlc_create_log_in_page();
            $thank_you_page_creation_status    = $this->wwlc_create_thank_you_page();

            if ( $registration_page_creation_status && $log_in_page_creation_status && $thank_you_page_creation_status ) {

                $registration_page_id = get_option( WWLC_OPTIONS_REGISTRATION_PAGE_ID );
                $login_page_id        = get_option( WWLC_OPTIONS_LOGIN_PAGE_ID );
                $thank_you_page_id    = get_option( WWLC_OPTIONS_THANK_YOU_PAGE_ID );

                $wwlc_lead_pages = array(
                    array(
                        'name' => get_the_title( $registration_page_id ),
                        'url'  => admin_url( 'post.php?post=' . $registration_page_id . '&action=edit' ),
                    ),
                    array(
                        'name' => get_the_title( $login_page_id ),
                        'url'  => admin_url( 'post.php?post=' . $login_page_id . '&action=edit' ),
                    ),
                    array(
                        'name' => get_the_title( $thank_you_page_id ),
                        'url'  => admin_url( 'post.php?post=' . $thank_you_page_id . '&action=edit' ),
                    ),
                );

                $response = array(
                    'status'  => 'success',
                    'message' => __( 'Lead pages creted successfully!', 'woocommerce-wholesale-lead-capture' ),
                );
            } else {
                $response = array(
                    'status'  => 'error',
                    'message' => __( 'Error: There are pages in the Trash that contain the login/registration shortcodes. Please permanently delete those pages or restore them first.', 'woocommerce-wholesale-lead-capture' ),
                );
            }

            return $response;
        }

        /**
         * Create registration page.
         *
         * @return bool
         * @since 1.0.0
         * @since 1.10 Checked if page status is not publish or not trash only then we create
         */
        public function wwlc_create_registration_page() {

            $registration_page_status = get_post_status( get_option( WWLC_OPTIONS_REGISTRATION_PAGE_ID ) );

            if ( 'publish' === $registration_page_status ) {
                return true;
            }

            if ( ! in_array( $registration_page_status, array( 'publish', 'trash' ), true ) ) {

                $wholesale_page = array(
                    'post_content' => '[wwlc_registration_form]', // The full text of the post.
                    'post_title'   => __( 'Wholesale Registration Page', 'woocommerce-wholesale-lead-capture' ), // The title of your post.
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                );

                $result = wp_insert_post( $wholesale_page );

                if ( 0 === $result || is_wp_error( $result ) ) {

                    return false;

                } else {

                    update_option( WWLC_OPTIONS_REGISTRATION_PAGE_ID, $result );
                    return true;

                }
            } else {
                return false;
            }
        }

        /**
         * Create log in page.
         *
         * @return bool
         * @since 1.0.0
         * @since 1.10 Checked if page status is not publish or not trash only then we create
         */
        public function wwlc_create_log_in_page() {

            $login_page_status = get_post_status( get_option( WWLC_OPTIONS_LOGIN_PAGE_ID ) );

            if ( 'publish' === $login_page_status ) {
                return true;
            }

            if ( ! in_array( get_post_status( get_option( WWLC_OPTIONS_LOGIN_PAGE_ID ) ), array( 'publish', 'trash' ), true ) ) {

                $wholesale_page = array(
                    'post_content' => '[wwlc_login_form]', // The full text of the post.
                    'post_title'   => __( 'Wholesale Log In Page', 'woocommerce-wholesale-lead-capture' ), // The title of your post.
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                );

                $result = wp_insert_post( $wholesale_page );

                if ( 0 === $result || is_wp_error( $result ) ) {

                    return false;

                } else {

                    update_option( WWLC_OPTIONS_LOGIN_PAGE_ID, $result );
                    return true;

                }
            } else {
                return false;
            }
        }

        /**
         * Create Thank You page.
         *
         * @return bool
         * @since 1.4.0
         * @since 1.10 Checked if page status is not publish or not trash only then we create
         */
        public function wwlc_create_thank_you_page() {

            $thankyou_page_status = get_post_status( get_option( WWLC_OPTIONS_THANK_YOU_PAGE_ID ) );

            if ( 'publish' === $thankyou_page_status ) {
                return true;
            }

            if ( ! in_array( get_post_status( get_option( WWLC_OPTIONS_THANK_YOU_PAGE_ID ) ), array( 'publish', 'trash' ), true ) ) {

                $wholesale_page = array(
                    'post_content' => 'Thank you for your registration. We will be in touch shortly to discuss your account.', // The full text of the post.
                    'post_title'   => __( 'Wholesale Thank You Page', 'woocommerce-wholesale-lead-capture' ), // The title of your post.
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                );

                $result = wp_insert_post( $wholesale_page );

                if ( 0 === $result || is_wp_error( $result ) ) {

                    return false;

                } else {

                    update_option( WWLC_OPTIONS_THANK_YOU_PAGE_ID, $result );
                    return true;

                }
            } else {
                return false;
            }
        }

        /**
         * Execute model.
         *
         * @since  2.0
         * @access public
         */
        public function run() {
            // Filter settings tabs.
            add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'admin_settings_tabs' ), 10, 1 );

            // Filter tab controls.
            add_filter( 'wwp_admin_setting_default_controls', array( $this, 'admin_settings_controls' ), 10, 1 );

            // Group form settings save mapping.
            add_action( 'wwp_group_settings_group_registration_fields_mapping_save', array( $this, 'group_registration_fields_mapping_save' ), 10, 1 );
            add_action( 'wwp_group_settings_group_registration_fields_mapping_edit', array( $this, 'group_registration_fields_mapping_edit' ), 10, 1 );
            add_action( 'wwp_group_settings_group_registration_fields_mapping_delete', array( $this, 'group_registration_fields_mapping_delete' ), 10, 1 );
            add_action( 'wwp_group_settings_group_registration_fields_mapping_sort', array( $this, 'group_registration_fields_mapping_sort' ), 10, 1 );

            // Trigger actions.
            add_action( 'wwp_trigger_force_fetch_update_data', array( $this, 'wwlc_force_fetch_update_data' ), 10, 1 );
            add_action( 'wwp_trigger_create_lead_pages', array( $this, 'wwlc_create_lead_pages' ), 10, 1 );
        }
    }
}
