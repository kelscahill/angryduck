<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Scripts' ) ) {

    /**
     * Model that houses the scripts loaded to the site.
     *
     * @since 1.0.0
     */
    class WWLC_Scripts {
        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_Scripts.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Scripts
         */
        private static $_instance;

        /**
         * WooCommerce_Wholesale_Lead_Capture Version
         *
         * @since 1.6.3
         * @access private
         * @var string
         */
        private $_wwlc_version;

        /**
         * Get instance of WWLC_Forms class
         *
         * @since 1.7.0
         * @access private
         * @var WWLC_Forms
         */
        private $_wwlc_forms;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWLC_Scripts constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Scripts model.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct( $dependencies ) {
            $this->_wwlc_version = $dependencies['WWLC_Version'];
            $this->_wwlc_forms   = $dependencies['WWLC_Forms'];
        }

        /**
         * Ensure that only one instance of WWLC_Scripts is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Scripts model.
         *
         * @return WWLC_Scripts
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Load Admin or Backend Related Styles and Scripts.
         *
         * @param string $handle The handle of the script to be loaded.
         *
         * @since 1.0.0
         */
        public function wwlc_load_back_end_styles_and_scripts( $handle ) {

            $screen = get_current_screen();

            // Only load styles and js on the right time and on the right place.
            if ( 'users.php' === $handle ) {

                // User listing page
                // Styles.
                wp_enqueue_style( 'wwlc_toastr_css', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.css', array(), $this->_wwlc_version, 'all' );

                wp_enqueue_style( 'wwlc_Users_css', WWLC_CSS_ROOT_URL . 'Users.css', array(), $this->_wwlc_version, 'all' );

                // Scripts.
                wp_enqueue_script( 'wwlc_toastr_js', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwlc_version, false );

                wp_enqueue_script( 'wwlc_BackEndAjaxServices_js', WWLC_JS_ROOT_URL . 'app/modules/BackEndAjaxServices.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_enqueue_script( 'wwlc_UserListing_js', WWLC_JS_ROOT_URL . 'app/UserListing.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_localize_script(
                    'wwlc_UserListing_js',
                    'UserListingVars',
                    array(
                        'approving_failed_message'    => __( 'Approving User Failed', 'woocommerce-wholesale-lead-capture' ),
                        'rejecting_failed_message'    => __( 'Rejecting User Failed', 'woocommerce-wholesale-lead-capture' ),
                        'activating_failed_message'   => __( 'Activating User Failed', 'woocommerce-wholesale-lead-capture' ),
                        'deactivating_failed_message' => __( 'Deactivating User Failed', 'woocommerce-wholesale-lead-capture' ),
                    )
                );

            }
            if ( in_array( $screen->id, array( 'woocommerce_page_wc-settings' ), true ) ) {

                if ( ! isset( $_GET['section'] ) || ( isset($_GET['section'] ) && $_GET['section'] == '') ) { // phpcs:ignore.

                    // General Section
                    // Styles.
                    wp_enqueue_style( 'wwlc_chosen_css', WWLC_JS_ROOT_URL . 'lib/chosen/chosen.min.css', array(), $this->_wwlc_version, 'all' );

                    // Scripts.
                    wp_enqueue_script( 'wwlc_chosen_js', WWLC_JS_ROOT_URL . 'lib/chosen/chosen.jquery.min.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_GeneralSettings_js', WWLC_JS_ROOT_URL . 'app/GeneralSettings.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_localize_script(
                        'wwlc_GeneralSettings_js',
                        'GeneralSettingsVars',
                        array(
                            'view_page' => __( 'View Page', 'woocommerce-wholesale-lead-capture' ),
                        )
                    );

                } elseif ( isset( $_GET['section'] ) && 'wwlc_settings_help_section' === $_GET['section'] ) { // phpcs:ignore.

                    // Help Section
                    // Styles.
                    wp_enqueue_style( 'wwlc_toastr_css', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.css', array(), $this->_wwlc_version, 'all' );
                    wp_enqueue_style( 'wwlc_HelpSettings_css', WWLC_CSS_ROOT_URL . 'HelpSettings.css', array(), $this->_wwlc_version, 'all' );

                    // Scripts.
                    wp_enqueue_script( 'wwlc_toastr_js', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_BackEndAjaxServices_js', WWLC_JS_ROOT_URL . 'app/modules/BackEndAjaxServices.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_HelpSettings_js', WWLC_JS_ROOT_URL . 'app/HelpSettings.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_localize_script(
                        'wwlc_HelpSettings_js',
                        'HelpSettingsVars',
                        array(
                            'success_message' => __( 'The following pages were created successfully:', 'woocommerce-wholesale-lead-capture' ),
                            'error_message'   => __( 'Failed To Create Lead Pages', 'woocommerce-wholesale-lead-capture' ),
                            'success_force_fetch_update_data_txt' => __( 'Successfully re-fetched plugin update data', 'woocommerce-wholesale-prices-premium' ),
                            'failed_force_fetch_update_data_txt' => __( 'Failed to re-fetch plugin update data', 'woocommerce-wholesale-prices-premium' ),
                        )
                    );

                } elseif ( isset( $_GET['section'] ) && 'wwlc_setting_fields_section' === $_GET['section'] ) { // phpcs:ignore.

                    // Built-in Fields section
                    // JS.
                    wp_enqueue_script( 'wwlc_BuiltInFieldsControl_js', WWLC_JS_ROOT_URL . 'app/BuiltInFieldsControl.js', array( 'jquery' ), $this->_wwlc_version, false );

                } elseif ( isset( $_GET['section'] ) && 'wwlc_setting_custom_fields_section' === $_GET['section'] ) { // phpcs:ignore.

                    // Custom Fields Section
                    // CSS.
                    wp_enqueue_style( 'wwlc_toastr_css', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.css', array(), $this->_wwlc_version, 'all' );
                    wp_enqueue_style( 'wwlc_WWLCCustomFieldsControl_css', WWLC_CSS_ROOT_URL . 'WWLCCustomFieldsControl.css', array(), $this->_wwlc_version, 'all' );

                    // JS.
                    wp_enqueue_script( 'wwlc_FormValidator_js', WWLC_JS_ROOT_URL . 'app/modules/FormValidator.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_toastr_js', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_BackEndAjaxServices_js', WWLC_JS_ROOT_URL . 'app/modules/BackEndAjaxServices.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_WWLCCustomFieldsControl_js', WWLC_JS_ROOT_URL . 'app/WWLCCustomFieldsControl.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_localize_script(
                        'wwlc_WWLCCustomFieldsControl_js',
                        'WWLCCustomFieldsControlVars',
                        array(
                            'empty_fields_error_message' => __( 'Please Fill The Form Properly. The following fields have empty values.', 'woocommerce-wholesale-lead-capture' ),
                            'success_save_message'       => __( 'Custom Field Successfully Saved', 'woocommerce-wholesale-lead-capture' ),
                            'failed_save_message'        => __( 'Failed To Save Custom Field', 'woocommerce-wholesale-lead-capture' ),
                            'success_edit_message'       => __( 'Custom Field Successfully Edited', 'woocommerce-wholesale-lead-capture' ),
                            'failed_edit_message'        => __( 'Failed To Edit Custom Field', 'woocommerce-wholesale-lead-capture' ),
                            'failed_retrieve_message'    => __( 'Failed Retrieve Custom Field Data', 'woocommerce-wholesale-lead-capture' ),
                            'confirm_box_message'        => __( 'Clicking OK will remove the current custom role', 'woocommerce-wholesale-lead-capture' ),
                            'no_custom_field_message'    => __( 'No Custom Fields Found', 'woocommerce-wholesale-lead-capture' ),
                            'success_delete_message'     => __( 'Successfully Deleted Custom Role', 'woocommerce-wholesale-lead-capture' ),
                            'failed_delete_message'      => __( 'Failed To Delete Custom Field', 'woocommerce-wholesale-lead-capture' ),
                            'option_text'                => __( 'Option Text', 'woocommerce-wholesale-lead-capture' ),
                            'option_value'               => __( 'Option Value', 'woocommerce-wholesale-lead-capture' ),
                            'field_name'                 => __( 'Field Name', 'woocommerce-wholesale-lead-capture' ),
                            'field_id'                   => __( 'Field ID', 'woocommerce-wholesale-lead-capture' ),
                            'field_type'                 => __( 'Field Type', 'woocommerce-wholesale-lead-capture' ),
                            'allowed_file_types'         => __( 'Allowed File Types', 'woocommerce-wholesale-lead-capture' ),
                            'max_allowed_file_size'      => __( 'Maximum Allowed File Size', 'woocommerce-wholesale-lead-capture' ),
                            'field_value'                => __( 'Value', 'woocommerce-wholesale-lead-capture' ),
                            'field_order'                => __( 'Field Order', 'woocommerce-wholesale-lead-capture' ),
                            'select_option_value'        => __( 'Select Option Value', 'woocommerce-wholesale-lead-capture' ),
                            'radio_option_value'         => __( 'Radio Option Value', 'woocommerce-wholesale-lead-capture' ),
                            'checkbox_option_value'      => __( 'Checkbox Option Value', 'woocommerce-wholesale-lead-capture' ),
                            'email_default_value'        => __( 'Default Value, invalid email format', 'woocommerce-wholesale-lead-capture' ),
                            'true'                       => __( 'true', 'woocommerce-wholesale-lead-capture' ),
                            'false'                      => __( 'false', 'woocommerce-wholesale-lead-capture' ),
                        )
                    );

                } elseif ( ( isset( $_GET['section'] ) && 'wwlc_setting_email_section' === $_GET['section'] ) || ( ( isset( $_GET['tab'] ) && 'email' === $_GET['tab'] ) && str_starts_with( $_GET['section'], 'wwlc' ) ) ) { // phpcs:ignore.

                    // CSS.
                    wp_enqueue_style( 'wwlc_selectize_default_css', WWLC_JS_ROOT_URL . 'lib/selectize/selectize.default.css', array(), $this->_wwlc_version, 'all' );
                    wp_enqueue_style( 'wwlc_EmailSettings_css', WWLC_CSS_ROOT_URL . 'EmailSettings.css', array(), $this->_wwlc_version, 'all' );

                    // JS.
                    wp_enqueue_script( 'wwlc_selectize_js', WWLC_JS_ROOT_URL . 'lib/selectize/selectize.min.js', array( 'jquery' ), $this->_wwlc_version, false );
                    wp_enqueue_script( 'wwlc_EmailSettings_js', WWLC_JS_ROOT_URL . 'app/EmailSettings.js', array( 'jquery' ), $this->_wwlc_version, false );
                }
            } elseif ( ( isset( $_GET['page'] ) && 'wws-license-settings' === $_GET['page'] || 'toplevel_page_wws-ms-license-settings-network' === $screen->id ) && ( ( isset( $_GET['tab'] ) && 'wwlc' === $_GET['tab'] ) || 'wwlc' === WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN ) ) { // phpcs:ignore.

                // CSS.
                wp_enqueue_style( 'wwlc_toastr_css', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.css', array(), $this->_wwlc_version, 'all' );
                wp_enqueue_style( 'wwlc_WWSLicenseSettings_css', WWLC_CSS_ROOT_URL . 'WWSLicenseSettings.css', array(), $this->_wwlc_version, 'all' );

                // JS.
                wp_enqueue_script( 'wwlc_toastr_js', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_enqueue_script( 'wwlc_BackEndAjaxServices_js', WWLC_JS_ROOT_URL . 'app/modules/BackEndAjaxServices.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_enqueue_script( 'wwlc_WWSLicenseSettings_js', WWLC_JS_ROOT_URL . 'app/WWSLicenseSettings.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_localize_script(
                    'wwlc_WWSLicenseSettings_js',
                    'WWSLicenseSettingsVars',
                    array(
                        'nonce_activate_license' => wp_create_nonce( 'wwlc_activate_license' ),
                        'success_save_message'   => __( 'Wholesale Lead License Details Successfully Saved', 'woocommerce-wholesale-lead-capture' ),
                        'failed_save_message'    => __( 'Failed To Save Wholesale Lead License Details', 'woocommerce-wholesale-lead-capture' ),
                    )
                );

            } elseif ( 'profile' === $screen->id || 'user-edit' === $screen->id ) {

                // CSS.
                wp_enqueue_style( 'wwlc_Users_css', WWLC_CSS_ROOT_URL . 'Users.css', array(), $this->_wwlc_version, 'all' );

                // Scripts.
                wp_enqueue_script( 'wwlc_user_update_js', WWLC_JS_ROOT_URL . 'app/UserUpdate.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_localize_script(
                    'wwlc_user_update_js',
                    'UserObject',
                    array(
                        'file_size_is_empty'            => __( 'The file you selected is empty. Please upload something more substantial.', 'woocommerce-wholesale-lead-capture' ),
                        'file_size_exceeds_max_allowed' => __( 'The file you selected exceeds the maximum allowed file size', 'woocommerce-wholesale-lead-capture' ),
                        'file_format_not_supported'     => __( 'The format of the file you selected is not supported', 'woocommerce-wholesale-lead-capture' ),
                    )
                );

                wp_enqueue_script( 'wwlc_BackEndAjaxServices_js', WWLC_JS_ROOT_URL . 'app/modules/BackEndAjaxServices.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_enqueue_script( 'wwlc_FormActions_js', WWLC_JS_ROOT_URL . 'app/modules/FormActions.js', array( 'jquery' ), $this->_wwlc_version, false );

                wp_enqueue_script( 'wwlc_toastr_js', WWLC_JS_ROOT_URL . 'lib/toastr/toastr.min.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_enqueue_script( 'wwlc_UserListing_js', WWLC_JS_ROOT_URL . 'app/UserListing.js', array( 'jquery' ), $this->_wwlc_version, false );
                wp_localize_script(
                    'wwlc_UserListing_js',
                    'UserListingVars',
                    array(
                        'approving_failed_message'    => __( 'Approving User Failed', 'woocommerce-wholesale-lead-capture' ),
                        'rejecting_failed_message'    => __( 'Rejecting User Failed', 'woocommerce-wholesale-lead-capture' ),
                        'activating_failed_message'   => __( 'Activating User Failed', 'woocommerce-wholesale-lead-capture' ),
                        'deactivating_failed_message' => __( 'Deactivating User Failed', 'woocommerce-wholesale-lead-capture' ),
                    )
                );

            } elseif ( 'dashboard' === $screen->id && 'dashboard' === $screen->base ) {

                // Tooltip purpose.
                wp_enqueue_script( 'woocommerce_admin', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery', 'jquery-tiptip' ), WC_VERSION, false );
                wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );

            }

            // Notice shows up on wc pages in the backend unless the message is dismissed.
            if ( get_option( 'wwlc_admin_notice_getting_started_show' ) === 'yes' || get_option( 'wwlc_admin_notice_getting_started_show' ) === false ) {
                wp_enqueue_style( 'wwlc_getting_started_css', WWLC_CSS_ROOT_URL . 'GettingStarted.css', array(), $this->_wwlc_version, 'all' );
                wp_enqueue_script( 'wwlc_getting_started_js', WWLC_JS_ROOT_URL . 'app/GettingStarted.js', array( 'jquery' ), $this->_wwlc_version, true );
            }
        }

        /**
         * Load Frontend Related Styles and Scripts.
         *
         * @since 1.0.0
         * @since 1.7.0  Implemented sensible enqueueing for registration form shortcode.
         * @since 1.8.0  Get page option url via wwlc_get_url_of_page_option function
         * @since 1.14.4 Translate google recaptcha based on wp site language.
         */
        public function wwlc_load_front_end_styles_and_scripts() {
            $user             = wp_get_current_user();
            $allowed_roles    = array( 'administrator', 'shop_manager', 'customer' );
            $is_roles_allowed = array_intersect( $allowed_roles, $user->roles );

            if ( ! is_user_logged_in() || $is_roles_allowed ) {
                wp_enqueue_style( 'select2' );
                wp_enqueue_style( 'wwlc_RegistrationForm_css', WWLC_CSS_ROOT_URL . 'RegistrationForm.css', array(), $this->_wwlc_version, 'all' );
            }

            if ( is_user_logged_in() && is_checkout() ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( ! empty( $custom_fields ) ) {
                    $has_custom_fields_show_on_checkout = false;
                    foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                        if ( $custom_field['enabled'] > 0 && $custom_field['checkout_display'] > 0 ) {
                            $has_custom_fields_show_on_checkout = true;
                            break;
                        }
                    }

                    if ( true === $has_custom_fields_show_on_checkout ) {
                        global $wc_wholesale_prices;
                        $user_wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

                        if ( ! empty( $user_wholesale_role ) ) {
                            wp_enqueue_style( 'wwlc_CustomFields_css', WWLC_CSS_ROOT_URL . 'CustomFields.css', array(), $this->_wwlc_version, 'all' );
                        }
                    }
                }
            }

            wp_register_script( 'wwlc_FrontEndAjaxServices_js', WWLC_JS_ROOT_URL . 'app/modules/FrontEndAjaxServices.js', array( 'jquery' ), $this->_wwlc_version, false );
            wp_localize_script( 'wwlc_FrontEndAjaxServices_js', 'Ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
            wp_register_script( 'wwlc_FormValidator_js', WWLC_JS_ROOT_URL . 'app/modules/FormValidator.js', array( 'jquery', 'password-strength-meter' ), $this->_wwlc_version, false );
            wp_register_script( 'wwlc_FormActions_js', WWLC_JS_ROOT_URL . 'app/modules/FormActions.js', array( 'jquery' ), $this->_wwlc_version, false );
            wp_localize_script(
                'wwlc_FormActions_js',
                'wwlc_form_object',
                array(
                    'ajaxurl'       => admin_url( 'admin-ajax.php' ),
                    'nonce'         => wp_create_nonce( 'wwlc_form_nonce' ),
                    'select_option' => __( 'Select an option', 'woocommerce-wholesale-lead-capture' ),
                )
            );
            wp_register_script( 'wwlc_mask_js', WWLC_JS_ROOT_URL . 'lib/mask/jquery.mask.js', array( 'jquery' ), $this->_wwlc_version, false );
            wp_register_script( 'wwlc_RegistrationForm_js', WWLC_JS_ROOT_URL . 'app/RegistrationForm.js', array( 'jquery', 'wwlc_FrontEndAjaxServices_js', 'wwlc_FormValidator_js', 'wwlc_FormActions_js', 'wwlc_mask_js' ), $this->_wwlc_version, false );
            wp_localize_script(
                'wwlc_RegistrationForm_js',
                'RegistrationVars',
                array(
                    'registrationThankYouPage'            => get_option( 'wwlc_general_registration_thankyou', false ) ? wwlc_get_url_of_page_option( 'wwlc_general_registration_thankyou' ) : wwlc_get_url_of_page_option( WWLC_OPTIONS_REGISTRATION_PAGE_ID ),
                    'fill_form_appropriately_message'     => __( 'Please Fill The Form Appropriately', 'woocommerce-wholesale-lead-capture' ),
                    'failed_registration_process_message' => __( 'Failed To Process Registration', 'woocommerce-wholesale-lead-capture' ),
                    'registration_failed_message'         => __( 'Registration Failed', 'woocommerce-wholesale-lead-capture' ),
                    'settings_save_failed_message'        => __( 'Failed To Save Settings', 'woocommerce-wholesale-lead-capture' ),
                    'field_is_required'                   => __( 'This field is required', 'woocommerce-wholesale-lead-capture' ),
                    'email_invalid'                       => __( 'Please enter a valid email address', 'woocommerce-wholesale-lead-capture' ),
                    'phone_invalid'                       => __( 'Please enter a valid phone number', 'woocommerce-wholesale-lead-capture' ),
                    'checkbox_inline_error'               => __( 'Please check at least one option', 'woocommerce-wholesale-lead-capture' ),
                    'number_not_divisible_by_step'        => __( 'Please enter a number that from the minimum is divisible by ', 'woocommerce-wholesale-lead-capture' ),
                    'number_max_less_than_min'            => __( 'The maximum number must not be lesser than the minimum number. Please contact admin.', 'woocommerce-wholesale-lead-capture' ),
                    'number_less_than_min'                => __( 'Please enter a number that is not lesser than the allowed minimum', 'woocommerce-wholesale-lead-capture' ),
                    'number_greater_than_max'             => __( 'Please enter a number that is not greater than the allowed maximum', 'woocommerce-wholesale-lead-capture' ),
                    'empty_recaptcha'                     => __( 'Please prove that you are human', 'woocommerce-wholesale-lead-capture' ),
                    'file_size_is_empty'                  => __( 'The file you selected is empty. Please upload something more substantial.', 'woocommerce-wholesale-lead-capture' ),
                    'file_size_exceeds_max_allowed'       => __( 'The file you selected exceeds the maximum allowed file size', 'woocommerce-wholesale-lead-capture' ),
                    'file_format_not_supported'           => __( 'The format of the file you selected is not supported', 'woocommerce-wholesale-lead-capture' ),
                    'confirm_password_field_enabled'      => get_option( 'wwlc_fields_enable_confirm_password_field' ) === 'yes' ? true : false,
                    'confirm_password_error_message'      => __( 'Password does not match the confirm password.', 'woocommerce-wholesale-lead-capture' ),
                    'activate_password_field'             => ( get_option( 'wwlc_fields_activate_password_field' ) === 'yes' ) ? true : false,
                    'password_minimum_strength'           => ( get_option( 'wwlc_fields_minimum_password_strength_field' ) === 'yes' ) ? true : false,
                    'password_strength_error_message'     => __( 'Password must contain at least 12 characters, 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.', 'woocommerce-wholesale-lead-capture' ),
                    'agree_terms_conditions_error'        => get_option( 'wwlc_terms_privacy_validation_text' ) ?? __( 'You need to agree to the terms and conditions.', 'woocommerce-wholesale-lead-capture' ),
                    'wwlc_captcha_enabled'                => get_option( 'wwlc_security_enable_recaptcha' ) === 'yes' ? true : false,
                    'wwlc_captcha_type'                   => get_option( 'wwlc_security_recaptcha_type', 'v2_im_not_a_robot' ),
                )
            );

            if ( is_account_page() ) {
                wp_enqueue_style( 'wwlc_MyAccountForm_css', WWLC_CSS_ROOT_URL . 'MyAccountForm.css', array(), $this->_wwlc_version, 'all' );
                wp_register_script( 'wwlc_MyAccountForm_js', WWLC_JS_ROOT_URL . 'app/MyAccount.js', array( 'jquery', 'wwlc_FrontEndAjaxServices_js' ), $this->_wwlc_version, false );
                wp_enqueue_script( 'wwlc_MyAccountForm_js' );
                wp_localize_script(
                    'wwlc_MyAccountForm_js',
                    'MyAccountVars',
                    array(
                        'file_size_is_empty'            => __( 'The file you selected is empty. Please upload something more substantial.', 'woocommerce-wholesale-lead-capture' ),
                        'file_size_exceeds_max_allowed' => __( 'The file you selected exceeds the maximum allowed file size', 'woocommerce-wholesale-lead-capture' ),
                        'file_format_not_supported'     => __( 'The format of the file you selected is not supported', 'woocommerce-wholesale-lead-capture' ),
                    )
                );
            }

            wp_register_script( 'wwlc_recaptcha_api_js', 'https://www.google.com/recaptcha/api.js?hl=' . get_user_locale(), array( 'jquery' ), $this->_wwlc_version, false );
            wp_register_script( 'wwlc_login_form_js', WWLC_JS_ROOT_URL . 'app/LoginForm.js', array( 'jquery' ), $this->_wwlc_version, false );
            wp_localize_script(
                'wwlc_login_form_js',
                'wwlc_login_page',
                apply_filters(
                    'wwlc_login_page',
                    array(
						'empty_recaptcha'       => __( 'Please prove that you are human', 'woocommerce-wholesale-lead-capture' ),
						'wwlc_capatcha_enabled' => get_option( 'wwlc_security_enable_recaptcha' ) === 'yes' ? true : false,
						'wwlc_captcha_type'     => get_option( 'wwlc_security_recaptcha_type', 'v2_im_not_a_robot' ),
                    )
                )
            );

            wp_register_script( 'wwlc_password_meter_js', WWLC_JS_ROOT_URL . 'app/PasswordMeter.js', array( 'jquery', 'password-strength-meter' ), $this->_wwlc_version, false );
            wp_localize_script(
                'wwlc_password_meter_js',
                'wwlc_pword_meter',
                apply_filters(
                    'wwlc_password_meter',
                    array(
						'short'             => __( 'Very weak', 'woocommerce-wholesale-lead-capture' ),
						'bad'               => __( 'Weak', 'woocommerce-wholesale-lead-capture' ),
						'good'              => __( 'Medium', 'woocommerce-wholesale-lead-capture' ),
						'strong'            => __( 'Strong', 'woocommerce-wholesale-lead-capture' ),
						'mismatch'          => __( 'Mismatch', 'woocommerce-wholesale-lead-capture' ),
						'blacklisted_words' => array( 'black', 'listed', 'word' ),
                    )
                )
            );
        }

        /**
         * Execute model.
         *
         * @since 1.6.3
         * @access public
         */
        public function run() {
            // Load Backend CSS and JS.
            add_action( 'admin_enqueue_scripts', array( $this, 'wwlc_load_back_end_styles_and_scripts' ) );

            // Load Frontend CSS and JS.
            add_action( 'wp_enqueue_scripts', array( $this, 'wwlc_load_front_end_styles_and_scripts' ), 99 );
        }
    }
}
