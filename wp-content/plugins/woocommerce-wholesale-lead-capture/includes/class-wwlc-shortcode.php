<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Shortcode' ) ) {

    /**
     * Class WWLC_Shortcode
     *
     * @since 1.6.3
     */
    class WWLC_Shortcode {

        /**
         * Property that holds the single main instance of WWLC_Shortcode.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Shortcode
         */
        private static $_instance;

        /**
         * Get instance of WWLC_Forms class
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Forms
         */
        private $_wwlc_forms;

        /**
         * Flag that tells if the registration form shortcode is already loaded or not on a page.
         * This is to make sure the registration form is only loaded once in a single page.
         *
         * @since 1.7.0
         * @access private
         * @var boolean
         */
        private $_wwlc_registration_form_loaded = false;

        /**
         * Flag that tells if the login form shortcode is already loaded or not on a page.
         * This is to make sure the login form is only loaded once in a single page.
         *
         * @since 1.7.0
         * @access private
         * @var boolean
         */
        private $_wwlc_login_form_loaded = false;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWLC_Shortcode constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Shortcode model.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct( $dependencies ) {
            $this->_wwlc_forms = $dependencies['WWLC_Forms'];
        }

        /**
         * Ensure that only one instance of WWLC_Shortcode is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Shortcode model.
         *
         * @return WWLC_Shortcode
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Render log in form.
         *
         * @since 1.0.0
         * @since 1.6.3 WWLC-49 : Stopped using wp_login_form, setup a custom form for WWLC login instead.
         *                 Reason is we can't set form action using wp_login_form which is used to redirect the user when login button is clicked.
         *                 This function is transferred from WWLC_Forms to this class.
         * @since 1.8.0 Get page option url via wwlc_get_url_of_page_option function
         *
         * @param array $atts Shortcode attributes.
         * @return string
         */
        public function wwlc_login_form( $atts ) {

            global $wp;

            ob_start();

            if ( is_user_logged_in() ) {

                $this->_wwlc_forms->_load_template(
                    'wwlc-logout-page.php',
                    array(),
                    WWLC_TEMPLATES_ROOT_DIR
                );

            } else {

                // WWLC-195 : due to WooCommerce running shortcodes twice on product page content when short description is not defined
                // we need to disable the flag condition ($this->_wwlc_login_form_loaded) for product pages by adding ! is_product() check.
                // WWLC-106 : Add a filter to override $_wwlc_login_form_loaded properties.
                if ( $this->wwlc_login_form_loaded() && ! is_product() ) {
                    return;
                }

                $atts = shortcode_atts(
                    array(
						'redirect' => '',
                    ),
                    $atts,
                    'wwlc_login_form'
                );

                $current_page = get_permalink( get_queried_object_id() );

                $login_form_args = array(
                    'form_id'        => 'wwlc_loginform',
                    'form_method'    => 'post',
                    'form_action'    => apply_filters( 'wwlc_filter_login_form_action', $current_page ),
                    'label_username' => apply_filters( 'wwlc_filter_login_field_label_username', __( 'Username', 'woocommerce-wholesale-lead-capture' ) ),
                    'label_password' => apply_filters( 'wwlc_filter_login_field_label_password', __( 'Password', 'woocommerce-wholesale-lead-capture' ) ),
                    'label_remember' => apply_filters( 'wwlc_filter_login_field_label_remember_me', __( 'Remember Me', 'woocommerce-wholesale-lead-capture' ) ),
                    'label_log_in'   => apply_filters( 'wwlc_filter_login_field_label_login', __( 'Log In', 'woocommerce-wholesale-lead-capture' ) ),
                    'id_username'    => 'user_login',
                    'id_password'    => 'user_pass',
                    'id_remember'    => 'rememberme',
                    'id_submit'      => 'wp-submit',
                    'remember'       => true,
                    'value_username' => isset( $_POST['wwlc_username'] ) ? $_POST['wwlc_username'] : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    'value_remember' => isset( $_POST['rememberme'] ) ? $_POST['rememberme'] : false, // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    'recaptcha'      => $this->_wwlc_forms->_get_recaptcha_field(),
                );

                $this->_wwlc_forms->_load_template(
                    'wwlc-login-form.php',
                    array(
                        'args'          => apply_filters( 'wwlc_login_form_args', $login_form_args ),
                        'formProcessor' => $this->_wwlc_forms,
                    ),
                    WWLC_TEMPLATES_ROOT_DIR
                );

                $this->_wwlc_login_form_loaded = true;

            }

            return ob_get_clean();
        }

        /**
         * Render registration form.
         *
         * @since 1.0.0
         * @since 1.6.3 This function is transferred from WWLC_Forms to this class.
         * @since 1.7.0 Add shortcode attribute for 'redirect'. Setting value to 'current_page' will set form to stay on page.
         *                Added code to make sure shortcode is loaded on a single page only once.
         *                Added feature to define user role to be used via the 'role' shortcode attribute.
         * @since 1.7.1 When logged in user views the page, then show the wwlc-logout-page.php template instead.
         * @since 1.7.3 Only enqueue one recaptcha js to avoid conflict
         * @since 1.8.0 Separate the render registration form fields in a different function.
         *                 Reason for this is so we can render it without altering the original WWLC registration form behaviour. Will be used by MOFW plugin.
         *
         * @param array $atts Shortcode attributes.
         * @return string
         */
        public function wwlc_registration_form( $atts ) {

            // WWLC-195 : due to WooCommerce running shortcodes twice on product page content when short description is not defined
            // we need to disable the flag condition ($this->_wwlc_registration_form_loaded) for product pages by adding ! is_product() check.
            // WWLC-106 : Add a filter to override $_wwlc_registration_form_loaded properties.
            if ( $this->wwlc_registration_form_loaded() && ! is_product() ) {
                return;
            }

            $atts = shortcode_atts(
                array(
					'redirect'     => '',
					'role'         => '',
					'auto_approve' => '',
					'auto_login'   => '',
                ),
                $atts,
                'wwlc_registration_form'
            );

            $show_registration_page = apply_filters( 'wwlc_upgrade_account', true );

            if ( is_user_logged_in() && $show_registration_page ) {

                ob_start();

                $this->_wwlc_forms->_load_template(
                    'wwlc-logout-page.php',
                    array(),
                    WWLC_TEMPLATES_ROOT_DIR
                );

                return ob_get_clean();

            } else {

                $this->_wwlc_registration_form_loaded = true;

                return $this->wwlc_registration_form_fields( $atts );

            }
        }

        /**
         * Render registration form.
         *
         * @since 1.8.0
         * @access public
         *
         * @param array $atts Shortcode attributes.
         * @return string
         */
        public function wwlc_registration_form_fields( $atts ) {

            global $WWLC_REGISTRATION_FIELDS;

            // enqueue registration form JS script.
            wp_enqueue_script( 'wwlc_RegistrationForm_js' );

            // enqueue password meter script if password field is enabled.
            if ( get_option( 'wwlc_fields_activate_password_field' ) === 'yes' ) {
                wp_enqueue_script( 'wwlc_password_meter_js' );
            }

            // enqueue recaptcha script if recaptcha field is enabled.
            if ( get_option( 'wwlc_security_enable_recaptcha' ) === 'yes' ) {

                add_action(
                    'wp_footer',
                    function () {
                        // if Contact Form 7 is enqueuing google recaptcha js then we skip enqueuing ours
                        // we only need to enqueue one recaptcha js to avoid conflict.
                        if ( ! $this->_is_wpcf7_recaptcha_conflict() ) {
                            wp_enqueue_script( 'wwlc_recaptcha_api_js' );
                        }
                    }
                );

            }

            $custom_fields            = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            $has_custom_select_fields = false;

            if ( ! empty( $custom_fields ) ) {
                foreach ( $custom_fields as $key => $value ) {

                    if ( 'select' === $value['field_type'] ) {
                        $has_custom_select_fields = true;
                        break;
                    }
                }
            }

            if ( $has_custom_select_fields || get_option( 'wwlc_fields_activate_address_field' ) === 'yes' ) {
                wp_enqueue_script( 'select2' );
            }

            $custom_fields            = $this->_wwlc_forms->_get_formatted_custom_fields();
            $recaptcha_field          = $this->_wwlc_forms->_get_recaptcha_field();
            $registration_form_fields = array_merge( $WWLC_REGISTRATION_FIELDS, $custom_fields, $recaptcha_field );

            usort( $registration_form_fields, array( $this->_wwlc_forms, '_usort_callback' ) );

            ob_start();

            do_action( 'before_registration_form', $registration_form_fields );

            // Load product listing template.
            $this->_wwlc_forms->_load_template(
                'wwlc-registration-form.php',
                array(
                    'formProcessor' => $this->_wwlc_forms,
                    'formFields'    => apply_filters( 'wwlc_registration_form_fields', $registration_form_fields ),
                    'redirect'      => ( $atts['redirect'] && filter_var( $atts['redirect'], FILTER_VALIDATE_URL ) ) ? $atts['redirect'] : '',
                    'options'       => array(
                        'role'        => WWLC_User_Account::sanitize_custom_role( $atts['role'] ),
                        'autoApprove' => $atts['auto_approve'],
                        'autoLogin'   => $atts['auto_login'],
                    ),
                ),
                WWLC_TEMPLATES_ROOT_DIR
            );

            do_action( 'after_registration_form', $registration_form_fields );

            return ob_get_clean();
        }

        /**
         * Check if Contact Form 7 recaptcha is enabled and the version used is in conflict with WWLC recaptcha JS.
         *
         * @since 1.13
         * @access private
         *
         * @return bool True if recaptcha version is conflict, false otherwise.
         */
        private function _is_wpcf7_recaptcha_conflict() {

            // if contact form 7 is not enabled, then we return false.
            if ( ! class_exists( 'WPCF7' ) ) {
                return false;
            }

            // if contact form 7 version is less than 5.1, then we return the check for the google-recaptcha script enqueue.
            if ( version_compare( WPCF7_VERSION, '5.1', '<' ) ) {
                return wp_script_is( 'google-recaptcha', 'enqueued' );
            }

            // if recaptcha V2 addon is enabled and V2 version, then we return the check for the google-recaptcha script enqueue.
            if ( class_exists( 'IQFix_WPCF7_Deity' ) && WPCF7::get_option( 'iqfix_recaptcha' ) === 2 ) {
                return wp_script_is( 'google-recaptcha', 'enqueued' );
            }

            return false;
        }

        /**
         * Add a filter to fix WWLC conflicting shortcodes when AIO SEO plugin is active.
         *
         * @since 1.17
         * @access public
         *
         * @param array $conflictingShortcodes Array of conflicting shortcodes.
         * @return array
         */
        public function aioseo_wwlc_conflicting_shortcodes( $conflictingShortcodes ) {
            $conflictingShortcodes = array_merge(
                $conflictingShortcodes,
                array(
					'Wholesale Log In Page'       => '[wwlc_login_form]',
					'Wholesale Registration Page' => '[wwlc_registration_form]',
                )
            );

            return $conflictingShortcodes;
        }

        /**
         * Add a filter to check if the _wwlc_registration_form_loaded to override  when to display the registration form.
         *
         * @since 1.16.3
         * @access public
         *
         * @return bool False on load.
         */
        public function wwlc_registration_form_loaded() {
            // Landing Page Builder by PluginOps integration.
            if ( is_plugin_active( 'page-builder-add/page-builder-add.php' ) ) {
                global $post, $wpdb;

                // Check if 'Set as front page' has been set in Landing page builder advanced option,
                // because if the option is checked the plugin will replace the content of the front page but the $post variable is still using default wp front page setting.
                $ulpb_front_page_id = $wpdb->get_row( "SELECT pm.post_id FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_key = 'ULPB_FrontPage' AND pm.meta_value = 'true'" );

                // Check if post type is Landing Page Builder.
                if ( metadata_exists( 'post', $post->ID, 'ULPB_DATA' ) || ( ! empty( $ulpb_front_page_id ) && is_front_page() ) ) {
                    $this->_wwlc_registration_form_loaded = false;
                }
            }

            return apply_filters( 'wwlc_registration_form_loaded', $this->_wwlc_registration_form_loaded );
        }

        /**
         * Add a filter to check if the _wwlc_login_form_loaded to override  when to display the login form.
         *
         * @since 1.16.3
         * @access public
         *
         * @return bool False on load.
         */
        public function wwlc_login_form_loaded() {
            // Landing Page Builder by PluginOps integration.
            if ( is_plugin_active( 'page-builder-add/page-builder-add.php' ) ) {
                global $post, $wpdb;

                // Check if 'Set as front page' has been set in Landing page builder advanced option,
                // because if the option is checked the plugin will replace the content of the front page but the $post variable is still using default wp front page setting.
                $ulpb_front_page_id = $wpdb->get_row( "SELECT pm.post_id FROM {$wpdb->prefix}postmeta pm WHERE pm.meta_key = 'ULPB_FrontPage' AND pm.meta_value = 'true'" );

                // Check if post type is Landing Page Builder.
                if ( metadata_exists( 'post', $post->ID, 'ULPB_DATA' ) || ( ! empty( $ulpb_front_page_id ) && is_front_page() ) ) {
                    $this->_wwlc_login_form_loaded = false;
                }
            }

            return apply_filters( 'wwlc_login_form_loaded', $this->_wwlc_login_form_loaded );
        }

        /**
         * Set as WooCommerce page.
         * For the page that has the WWLC shortcode, set it as WooCommerce page so that the WooCommerce template will be used.
         *
         * @since 1.17.7
         * @access public
         *
         * @param bool $is_wc_page Is WooCommerce page.
         * @return bool True if the current page has the WWLC shortcode, false otherwise.
         */
        public function set_woocommerce_page( $is_wc_page ) {
            // Check if post has our target shortcode.
            if ( wc_post_content_has_shortcode( 'wwlc_registration_form' ) || wc_post_content_has_shortcode( 'wwlc_login_form' ) ) {
                $is_wc_page = true;
            }

            return $is_wc_page;
        }

        /**
         * Execute model.
         *
         * @since 1.6.3
         * @access public
         */
        public function run() {

            // Registration Form.
            add_shortcode( 'wwlc_registration_form', array( $this, 'wwlc_registration_form' ) );

            // Login Form.
            add_shortcode( 'wwlc_login_form', array( $this, 'wwlc_login_form' ) );

            // Set as woocommerce page.
            add_filter( 'is_woocommerce', array( $this, 'set_woocommerce_page' ) );

            // All In One SEO plugin  - Conflicting shorcodes fix.
            if ( is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) {
                add_filter( 'aioseo_conflicting_shortcodes', array( $this, 'aioseo_wwlc_conflicting_shortcodes' ) );
            }
        }
    }
}
