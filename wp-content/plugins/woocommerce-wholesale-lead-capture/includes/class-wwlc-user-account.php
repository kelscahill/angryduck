<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_User_Account' ) ) {

    /**
     * Model that houses the logic of lead capture user account.
     *
     * @since 1.0.0
     * @since 2.0.0 - Remove unmoderated and inactive roles. Replace unmoderated with unapproved.
     */
    class WWLC_User_Account {

        /**
         * Class Constants
         */

        /**
         * Action label sent on the registration form for reCAPTCHA v3.
         *
         * @since 2.0.4.3
         * @var string
         */
        const RECAPTCHA_V3_REGISTER_ACTION = 'wwlc_register';

        /**
         * Action label sent on the login form for reCAPTCHA v3.
         *
         * @since 2.0.4.3
         * @var string
         */
        const RECAPTCHA_V3_LOGIN_ACTION = 'wwlc_login';

        /**
         * Default v3 score threshold when the option is unset, non-numeric, or out of range.
         *
         * @since 2.0.4.3
         * @var string
         */
        const RECAPTCHA_V3_DEFAULT_THRESHOLD = '0.5';

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_User_Account.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_User_Account
         */
        private static $_instance;

        /**
         * Property that holds the total number of unapproved users.
         *
         * @since 1.12
         * @access private
         * @var int
         */
        private $total_unapproved_users;

        /**
         * Class Methods
         */

        /**
         * WWLC_User_Account constructor.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct() {
            $this->total_unapproved_users = $this->get_total_unapproved_users();
        }

        /**
         * Ensure that only one instance of WWLC_User_Account is loaded or can be loaded (Singleton Pattern).
         *
         * @return WWLC_User_Account
         * @since 1.6.3
         */
        public static function instance() {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Handles user authentication when user logs in using wwlc login form.
         *
         * @since 1.6.3 WWLC-49
         * @since 1.15 "Invisible reCaptcha for WordPress" compatibility
         */
        public function wwlc_authenticate() {

            // "Invisible reCaptcha for WordPress" validation.
            $is_valid = apply_filters( 'google_invre_is_valid_request_filter', true );

            // If Log In is clicked and nonce is valid.
            if ( $is_valid && ( isset( $_POST['wwlc_login_form_nonce_field'] ) && wp_verify_nonce( sanitize_key( $_POST['wwlc_login_form_nonce_field'] ), 'wwlc_login_form' ) ) ) {

                $err = array();
                if ( empty( $_POST['wwlc_username'] ) ) {
                    $err[] = '<b>' . __( 'Username', 'woocommerce-wholesale-lead-capture' ) . '</b>';
                }

                if ( empty( $_POST['wwlc_password'] ) ) {
                    $err[] = '<b>' . __( 'Password', 'woocommerce-wholesale-lead-capture' ) . '</b>';
                }

                if ( ! empty( $err ) ) {

                    $login_error = implode( ' and ', $err );
                    add_action(
                        'wwlc_before_login_form',
                        function () use ( $login_error ) {
                            wc_print_notice(
                                sprintf(
                                    /* translators: %1$s: field names that are empty. */
                                    esc_html__( '%1$s must not be empty.', 'woocommerce-wholesale-lead-capture' ),
                                    wp_kses( $login_error, array( 'b' => array() ) )
                                ),
                                'error'
                            );
                        }
                    );

                } else {

                    $creds                  = array();
                    $creds['user_login']    = sanitize_text_field( $_POST['wwlc_username'] );
                    $creds['user_password'] = wp_unslash( $_POST['wwlc_password'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be sanitized as sanitize_text_field() strips special characters.
                    $creds['remember']      = isset( $_POST['rememberme'] ) ? true : false;
                    $user                   = wp_signon( $creds, is_ssl() );

                    if ( is_wp_error( $user ) ) {

                        $login_error = esc_html__( 'Invalid username or password.', 'woocommerce-wholesale-lead-capture' );
                        add_action(
                            'wwlc_before_login_form',
                            function () use ( $login_error ) {
                                wc_print_notice( $login_error, 'error' );
                            }
                        );

                    } elseif ( isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] ) ) {

                        $login_redirect = apply_filters( 'wwlc_login_redirect_url', esc_url( sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) ), $user );
                        wp_safe_redirect( $login_redirect, 301 );
                        exit;

                    } else {
                        $wholesale_role = wwlc_get_wholesale_role( $user );

                        if ( ! empty( $wholesale_role ) ) {

                            $wwlc_login_redirect = wwlc_get_url_of_page_option( 'wwlc_general_login_redirect_page' );

                            if ( ! empty( $wwlc_login_redirect ) ) {
                                $redirect_to = wp_unslash( $wwlc_login_redirect );
                            } elseif ( wc_get_raw_referer() ) {
                                $redirect_to = wc_get_raw_referer();
                            } else {
                                $redirect_to = wc_get_page_permalink( 'myaccount' );
                            }
                        } elseif ( user_can( $user->ID, 'manage_options' ) ) { // phpcs:ignore.
                            $redirect_to = admin_url();
                        } else {
                            $redirect_to = wc_get_page_permalink( 'myaccount' );
                        }

                        $login_redirect = apply_filters( 'wwlc_login_redirect_url', remove_query_arg( 'wc_error', $redirect_to ), $user );
                        wp_safe_redirect( $login_redirect, 301 );
                        exit;
                    }
                }
            }
        }

        /**
         * Approve the user when updated in the user edit screen and not via the listing.
         * The user is considered approve once the role is changed from Unapproved into any status.
         *
         * Only triggers when the current user has manage_options capability to prevent
         * third-party plugins from inadvertently approving users via wp_update_user().
         *
         * @param int     $userID The user id.
         * @param WP_User $old_user_data WP_User object.
         *
         * @since 1.6.2 WWLC-28
         * @since 2.0.0 Removed unused role.
         * @since 2.0.3.3 Added capability check to prevent unauthorized approval.
         * @since 2.0.5 Skip the WWLC approval flow when the admin has assigned a non-wholesale role
         *              from the Edit User screen, so the user does not also receive wholesale_customer.
         *              Note: the wwlc_allow_profile_update_approval filter is not consulted on this
         *              early-return path — it only applies to WWLC-managed role transitions.
         */
        public function wwlc_profile_update( $userID, $old_user_data ) {

            $user               = get_userdata( $userID );
            $old_role_check     = array_intersect( $old_user_data->roles, array( 'wwlc_unapproved' ) );
            $updated_role_check = array_intersect( $user->roles, array( 'wwlc_unapproved' ) );

            // Only mark approve when the updated role is not equal to 'wwlc_unapproved'
            // and the old role before the update is equal to 'wwlc_unapproved'.
            if ( ! empty( $old_role_check ) && empty( $updated_role_check ) ) {

                // If any role currently assigned to the user is outside the WWLC approval
                // set (unapproved, rejected, configured new-lead role), respect that explicit
                // choice and skip the approval flow — otherwise wwlc_approve_user() would
                // override it with the default new-lead role.
                $approval_roles = array(
                    WWLC_UNAPPROVED_ROLE,
                    WWLC_REJECTED_ROLE,
                    get_option( 'wwlc_general_new_lead_role', 'wholesale_customer' ),
                );
                foreach ( (array) $user->roles as $current_role ) {
                    if ( $current_role && ! in_array( $current_role, $approval_roles, true ) ) {
                        return;
                    }
                }

                // By default, require manage_options capability or a recognized non-interactive
                // context (WP-CLI, cron) to prevent third-party plugins from inadvertently
                // triggering approval via wp_update_user().
                $allow_approval = current_user_can( 'manage_options' )
                    || wp_doing_cron()
                    || ( defined( 'WP_CLI' ) && WP_CLI );

                /**
                 * Filters whether the profile_update hook is allowed to trigger user approval.
                 *
                 * Returning true allows the approval to proceed even when the current user
                 * lacks manage_options capability (e.g. REST API with application passwords).
                 *
                 * @since 2.0.3.3
                 *
                 * @param bool $allow_approval Whether approval is allowed.
                 * @param int  $userID         The user ID being approved.
                 */
                $allow_approval = apply_filters( 'wwlc_allow_profile_update_approval', $allow_approval, $userID );

                if ( $allow_approval ) {
                    $this->wwlc_approve_user(
                        array(
                            'userID'         => $userID,
                            'old_user_roles' => $old_user_data->roles,
                        )
                    );
                } else {
                    wc_get_logger()->warning(
                        sprintf(
                            'WWLC: Blocked automatic approval of user #%d via profile_update — role changed from wwlc_unapproved but current user lacks manage_options capability.',
                            $userID
                        ),
                        array( 'source' => 'woocommerce-wholesale-lead-capture' )
                    );
                }
            }
        }

        /**
         * This function is used for printing successful registration inline notice when there's no set thank you page in the settings.
         * The user is redirected to registration page, the notice is printed above the form.
         *
         * @param string $content String of success notice after registration.
         *
         * @since 1.6.2 WWLC-117
         * @since 1.7.0 Notice will now only display when registration is not redirected to the set thank you page.
         */
        public function wwlc_registration_form_print_notice( $content ) { // phpcs:ignore.

            $thankyou_page         = get_option( 'wwlc_general_registration_thankyou' );
            $always_display_notice = apply_filters( 'wwlc_always_display_message_after_registration', false );

            if ( ( true === $always_display_notice ) || ( empty( $thankyou_page ) ) ) {

                $message = apply_filters( 'wwlc_registration_success_message', isset( $_POST['inline_message'] ) ? wp_kses_post( wp_unslash( $_POST['inline_message'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in wwlc_create_user().
                $status  = apply_filters( 'wwlc_registration_status', 'success' );

                if ( ! empty( $message ) ) {
                    wc_add_notice( $message, $status );
                }
            }
        }

        /**
         * Generate random password.
         *
         * @param int $length The length of the generated passowrd.
         *
         * @return string
         * @since 1.0.0
         */
        private function _generate_password( $length = 16 ) {

            return wp_generate_password( $length, true, false );
        }

        /**
         * WWLC authentication filter. It checks if user is inactive, unapproved or rejected and kick
         * there asses.
         *
         * @param WP_User|WP_Error $user WP_User object or WP_Error object from previous authentication filters.
         * @param string           $password String of user lead capture password.
         *
         * @return WP_Error|WP_User
         * @since 1.0.0
         * @since 1.7.1 Created a separate error for unapproved/unmoderated roles.
         * @since 1.14  Multisite support. When logging in from a different site. Check the users role from the original site they registered. WWLC-286
         * @since 2.0.0 Removed wwlc_unmoderated role check.
         */
        public function wwlc_wholesale_lead_authenticate( $user, $password ) { // phpcs:ignore.

            // If user is not a WP_User object (e.g., it's a WP_Error), return it as is.
            if ( ! is_a( $user, 'WP_User' ) ) {
                return $user;
            }

            if ( is_multisite() ) {

                $user_blog   = get_blogs_of_user( $user->ID );
                $user_blog   = array_values( $user_blog );
                $blog_id     = $user_blog[0]->userblog_id; // User Blog ID where they registered.
                $wp_user_obj = '';

                // Find the roles from the original site the user was registered.
                if ( isset( $blog_id ) ) {
                    $wp_user_obj = new WP_User( $user->ID, '', $blog_id );
                }

                if ( is_a( $wp_user_obj, 'WP_User' ) && ! empty( $wp_user_obj->roles ) ) {
                    $user = &$wp_user_obj;
                }
            }

            if ( is_array( $user->roles ) && ( in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) ) {
                return new WP_Error( 'authentication_failed', __( 'Your account is still awaiting approval.', 'woocommerce-wholesale-lead-capture' ) );
            }

            if ( is_array( $user->roles ) && in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {
                return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Invalid Request', 'woocommerce-wholesale-lead-capture' ) );
            } else {
                return $user;
            }
        }

        /**
         * Redirect wholesale users after successful login accordingly.
         *
         * @param string           $redirect_to The redirect destination URL.
         * @param string           $request The requested redirect destination URL passed as a parameter.
         * @param WP_User|WP_Error $user WP_User object if login was successful, WP_Error object otherwise.
         *
         * @return mixed
         *
         * @since 1.2.0
         * @since 1.6.10 WWLC-177 : added conditions before foreach to check if $all_wholesale_roles is array
         * @since 1.8.0 Get page option url via wwlc_get_url_of_page_option function
         */
        public function wwlc_wholesale_lead_login_redirect( $redirect_to, $request, $user ) { // phpcs:ignore.

            $wholesale_login_redirect = wwlc_get_url_of_page_option( 'wwlc_general_login_redirect_page' );

            $wholesale_role = is_a( $user, 'WP_User' ) ? wwlc_get_wholesale_role( $user ) : array();

            if ( ! empty( $wholesale_role ) && ! empty( $wholesale_login_redirect ) ) {
                return $wholesale_login_redirect;
            } else {
                return $redirect_to;
            }
        }

        /**
         * Redirect wholesale user to specific page after logging out.
         *
         * @param string $logout_url The HTML-encoded logout URL.
         * @param string $redirect Path to redirect to on logout.
         *
         * @return string
         * @access public
         * @since 1.6.9  WWLC-175 : delete session after wholesale user logs out.
         * @since 1.6.10 WWLC-177 : added conditions before foreach to check if $all_wholesale_roles is array.
         * @since 1.8.0  Get page option url via wwlc_get_url_of_page_option function.
         *                  Updated the filter, used "logout_url" instead of "wp_logout".
         *                  This fixes the issue wehere WPML will get the english version of logout redirect page instead of the translated one.
         * @since 1.3.3
         */
        public function wwlc_wholesale_lead_logout_redirect( $logout_url, $redirect ) { // phpcs:ignore.

            $logout_redirect_page = wc_get_page_permalink( 'myaccount' );

            $user               = wp_get_current_user();
            $wholesale_customer = wwlc_get_wholesale_role();

            if ( $logout_redirect_page && ( ! empty( $wholesale_customer ) || in_array( 'customer', (array) $user->roles, true ) ) ) {

                $wholesale_logout_redirect = wwlc_get_url_of_page_option( 'wwlc_general_logout_redirect_page' );

                if ( ! empty( $wholesale_customer ) && $wholesale_logout_redirect ) {
                    $logout_redirect_page = $wholesale_logout_redirect;
                }

                $args = array(
					'action'      => 'logout',
					'redirect_to' => apply_filters( 'wwlc_filter_logout_redirect_url', $logout_redirect_page ),
                );

                $logout_url = add_query_arg( $args, site_url( 'wp-login.php', 'login' ) );
                $logout_url = wp_nonce_url( $logout_url, 'log-out' );

                return apply_filters( 'wwlc_wholesale_lead_logout_redirect', $logout_url );

            }

            return $logout_url;
        }

        /**
         * Clear user session when user logs-out.
         *
         * @since 1.3.3
         * @since 1.8.0 Refactored the codes surrounding logout. WWLC-245
         * @since 1.14.4 Check if destroy_session method exist in WC()->session before firing it to avoid the error "Call to a member function destroy_session()".
         * @access public
         */
        public function wwlc_clear_user_session_on_logout() {

            if ( WC() && method_exists( WC()->session, 'destroy_session' ) ) {
                WC()->session->destroy_session();
            }
        }

        /**
         * Allow custom external url when redirecting. Specifically for logout url in wwlc setting.
         *
         * @since 1.8.0
         * @access public
         *
         * @param string[] $allowed An array of allowed host names.
         * @return array
         */
        public function allow_custom_external_url_redirect( $allowed ) {

            $allowed[] = untrailingslashit( preg_replace( '(^https?://www.)', '', wwlc_get_url_of_page_option( 'wwlc_general_logout_redirect_page' ) ) );
            $allowed[] = untrailingslashit( preg_replace( '(^https?://)', '', wwlc_get_url_of_page_option( 'wwlc_general_logout_redirect_page' ) ) );

            return $allowed;
        }

        /**
         * Create New User.
         *
         * @param null|array  $user_data Array of user data submited form registration form.
         * @param WWLC_Emails $email_processor WWLC_Emails Object.
         *
         * @return bool|array
         * @since 1.0.0
         * @since 1.6.2 WWLC-117: If WWLC thank you page is not set at the settings, use message and display inline notice above the registration form.
         *                 Used defined( 'DOING_AJAX' ) && DOING_AJAX instead of declaring variable flag for ajax request.
         * @since 1.7.0 Added code to save the set custom role in the shortcode if present.
         *                Add support for WPML plugin.
         * @since 1.7.1 Removed code relating to saving/displaying passwords.
         * @since 2.0.2 - Removed duplicate user_data assignment.
         * @since 2.0.3 - Fixed security issue: Prevent race condition where users are temporarily created with administrator privileges.
         * @since 2.0.4.3 - Added v3 score-based reCAPTCHA verification branch.
         */
        public function wwlc_create_user( $user_data = array(), $email_processor = null ) {

            global $sitepress, $wp_roles;

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                if ( get_option( 'wwlc_security_enable_recaptcha' ) === 'yes' ) {
                    $recaptcha_secret = get_option( 'wwlc_security_recaptcha_secret_key' );
                    $recaptcha_token  = isset( $_POST['recaptcha_field'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.

                    if ( empty( $recaptcha_token ) ) {
                        $recaptcha_check = false;
                    } else {
                        $recaptcha_request = wp_remote_post(
                            'https://www.google.com/recaptcha/api/siteverify',
                            array(
                                'body' => array(
                                    'secret'   => $recaptcha_secret,
                                    'response' => $recaptcha_token,
                                    'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                                ),
                            )
                        );

                        if ( is_wp_error( $recaptcha_request ) ) {
                            $recaptcha_check = false;
                        } else {
                            $recaptcha_type     = get_option( 'wwlc_security_recaptcha_type', 'v2_im_not_a_robot' );
                            $recaptcha_response = json_decode( wp_remote_retrieve_body( $recaptcha_request ) );

                            if ( ! is_object( $recaptcha_response ) || empty( $recaptcha_response->success ) ) {
                                $recaptcha_check = false;
                            } elseif ( 'v3_score_based' === $recaptcha_type ) {
                                $threshold       = (float) get_option( 'wwlc_security_recaptcha_v3_score_threshold', self::RECAPTCHA_V3_DEFAULT_THRESHOLD );
                                $threshold       = max( 0.0, min( 1.0, $threshold ) );
                                $score           = isset( $recaptcha_response->score ) ? (float) $recaptcha_response->score : 0.0;
                                $action          = isset( $recaptcha_response->action ) ? $recaptcha_response->action : '';
                                $recaptcha_check = ( $score >= $threshold ) && ( self::RECAPTCHA_V3_REGISTER_ACTION === $action );
                            } else {
                                $recaptcha_check = true;
                            }
                        }
                    }
                } else {
                    $recaptcha_check = true;
                }

                if ( ! isset( $_POST['wwlc_register_user_nonce_field'] ) ||
                    ! wp_verify_nonce( sanitize_key( $_POST['wwlc_register_user_nonce_field'] ), 'wwlc_register_user' ) ||
                    true !== $recaptcha_check ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
							'status'        => 'fail',
							'error_message' => apply_filters( 'wwlc_inline_notice', __( 'Security check fail', 'woocommerce-wholesale-lead-capture' ), 'fail' ),
						)
                    );
                    die();

                }

                // WPML Support.
                if ( is_object( $sitepress ) ) {

                    $lang = $sitepress->get_language_from_url( esc_url_raw( home_url() ) );

                    $sitepress->switch_lang( $lang );

                    // save language of which user was registered to.
                    $user_data['wwlc_user_lang_wpml'] = $lang;

                }
            }

            $username                = $user_data['user_email'];
            $auto_generated_password = false;

            // Generate password.
            if ( isset( $user_data['wwlc_password'] ) && ! empty( $user_data['wwlc_password'] ) ) {
                $password = $user_data['wwlc_password'];
            } else {
                $password                = $this->_generate_password();
                $auto_generated_password = true;
            }

            if ( ! empty( $user_data['wwlc_username'] ) ) {
                $username = $user_data['wwlc_username'];
            }

            // handles upgrade existing customer.
            wc_do_deprecated_action(
                'wwlc_action_before_create_user',
                array(
					$user_data,
					$email_processor,
					$auto_generated_password,
					$password,
				),
                '1.17.4',
                'wwlc_action_before_create_wholesale_lead'
            );
            do_action( 'wwlc_action_before_create_wholesale_lead', $user_data, $auto_generated_password, $password );

            // Don't create if login as customer.
            if ( apply_filters( 'wwlc_stop_registering', false, $user_data ) ) {
                header( 'Content-Type: application/json' ); // specify we return json.
                echo wp_json_encode(
                    array(
						'status'        => 'fail',
						'error_message' => apply_filters( 'wwlc_fail_user_login', __( 'You are not allowed to register.', 'woocommerce-wholesale-lead-capture' ), 'fail' ),
					)
                );
                die();
            }

            if ( isset( $user_data['meta_data'] ) && ! empty( $user_data['meta_data'] ) ) {
                $meta_data = $user_data['meta_data'];
                unset( $user_data['meta_data'] );
            }

            /**
             * Prepare user data and set the correct role to prevent race condition.
             */
            $userdata = array(
				'user_login' => $username,
				'user_email' => $user_data['user_email'],
				'user_pass'  => $password,
				'role'       => WWLC_UNAPPROVED_ROLE,
            );
            $result   = wp_insert_user( $userdata );

            if ( ! is_wp_error( $result ) ) {

                // Get new user.
                $new_lead = new WP_User( $result );

                // Save registration form fields.
                $this->save_registration_form_fields( $result, $user_data, $auto_generated_password );

                // Save customer billing address.
                $this->wwlc_save_customer_billing_address( $result );

                // Transfer uploaded files from temporary folder to users wholesale folder.
                $this->_move_user_files_to_permanent( $result );

                // Process auto approve new leads. Returns whether the user was auto-approved.
                $auto_approved = $this->process_new_leads( $user_data, $new_lead, $password );

                wc_do_deprecated_action( 'wwlc_action_after_create_user', array( $new_lead ), '1.17.4', 'wwlc_action_after_create_wholesale_lead' );
                do_action( 'wwlc_action_after_create_wholesale_lead', $new_lead );

                $all_roles = $wp_roles->get_names();

                // Determine the correct role to assign during user creation.
                $lead_role = get_option( 'wwlc_general_new_lead_role', WWLC_UNAPPROVED_ROLE );
                if ( isset( $user_data['role'] ) && ! empty( $user_data['role'] ) ) {
                    $requested_role = sanitize_text_field( $user_data['role'] );
                    if ( wwlc_check_role( $requested_role ) ) {
                        $lead_role = $requested_role;
                    }
                }

                // Add user meta data.
                // Meta keys are already filtered by wwlc_sanitize_user_data() blocked list.
                if ( isset( $meta_data ) && ! empty( $meta_data ) ) {
                    foreach ( $meta_data as $meta ) {
                        $meta = (array) $meta;

                        if ( ! isset( $meta['key'] ) || ! isset( $meta['value'] ) ) {
                            continue;
                        }

                        $meta_key   = sanitize_text_field( $meta['key'] );
                        $meta_value = is_array( $meta['value'] )
                                ? array_map( 'sanitize_text_field', $meta['value'] )
                                : sanitize_text_field( $meta['value'] );

                        update_user_meta( $new_lead->ID, $meta_key, $meta_value );
                    }
                }

                // Display proper message based on actual approval outcome (not just the global setting).
                if ( $auto_approved && isset( $all_roles[ $lead_role ] ) ) {
                    /* translators: %1$s Wholesale lead capture role */
                    $success_message = sprintf( __( 'Thank you for your registration. You are now a %1$s.', 'woocommerce-wholesale-lead-capture' ), $all_roles[ $lead_role ] );
                } else {
                    $success_message = __( 'Thank you for your registration. We will be in touch shortly to discuss your account.', 'woocommerce-wholesale-lead-capture' );
                }

                $response = array(
					'status'          => 'success',
					'success_message' => apply_filters( 'wwlc_inline_notice', $success_message, 'success' ),
					'user_id'         => $new_lead->ID,
                );

            } else {

                wc_get_logger()->error(
                    sprintf( 'WWLC user creation failed: %s (Code: %s)', $result->get_error_message(), $result->get_error_code() ),
                    array( 'source' => 'woocommerce-wholesale-lead-capture' )
                );

                $safe_codes = array( 'existing_user_login', 'existing_user_email' );
                $user_msg   = in_array( $result->get_error_code(), $safe_codes, true )
                        ? $result->get_error_message()
                        : __( 'Registration could not be completed. Please try again or contact support.', 'woocommerce-wholesale-lead-capture' );

                $response = array(
					'status'        => 'fail',
					'error_message' => apply_filters( 'wwlc_inline_notice', $user_msg, 'fail' ),
					'form_data'     => $user_data,
                );

            }

            $response = apply_filters( 'wwlc_create_user_response_data', $response );

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                header( 'Content-Type: application/json' ); // specify we return json.
                echo wp_json_encode( $response );
                die();

            } else {
                return $response;
            }
        }

        /**
         * Handles saving registration fields.
         *
         * @param int   $user_id User ID.
         * @param array $user_data Registration form data.
         * @param bool  $auto_generated_password Auto generate password.
         *
         * @since 2.0.3.2 Added allowlist to prevent arbitrary user meta injection.
         * @access public
         *
         * @since 1.15
         */
        public function save_registration_form_fields( $user_id, $user_data, $auto_generated_password ) {

            // Build allowlist from built-in and custom registration fields.
            $allowed_keys = array_flip( $this->get_allowed_registration_field_keys() );

            // Update new user meta.
            if ( ! empty( $user_data ) ) {

                foreach ( $user_data as $key => $val ) {

                    if ( in_array( $key, array( 'user_email', 'wwlc_password', 'wwlc_password_confirm' ), true ) ) {
                        continue;
                    }

                    // Only save fields that are in the allowlist.
                    if ( ! isset( $allowed_keys[ $key ] ) ) {
                        continue;
                    }

                    // Sanitize before saving to db.
                    if ( is_array( $val ) ) {
                        update_user_meta( $user_id, $key, array_map( 'sanitize_text_field', wp_unslash( $val ) ) );
                    } else {
                        update_user_meta( $user_id, $key, sanitize_text_field( $val ) );
                    }
                }
            }

            // save custom role if set in form.
            if ( isset( $user_data['wwlc_role'] ) && self::sanitize_custom_role( $user_data['wwlc_role'] ) ) {
                update_user_meta( $user_id, 'wwlc_custom_set_role', sanitize_text_field( $user_data['wwlc_role'] ) );
            }

            // Auto generated password flag.
            if ( $auto_generated_password ) {
                update_user_meta( $user_id, 'wwlc_auto_generated_password', true );
            }
        }

        /**
         * Handles process new leads.
         *
         * @param array   $user_data Registration form data.
         * @param WP_User $new_lead WP User object.
         * @param string  $user_pass Password text.
         *
         * @return bool Whether the user was auto-approved.
         * @since 1.17.4
         * @since 2.0.3.3 Returns bool indicating whether the user was auto-approved.
         * @access public
         */
        public function process_new_leads( $user_data, $new_lead, $user_pass ) {

            global $sitepress;

            $wc_emails = WC()->mailer()->get_emails();

            // Get WWLC auto approve option.
            $auto_approve = get_option( 'wwlc_general_auto_approve_new_leads' ) === 'yes' ? true : false;

            // Get page ID from form data (for AJAX compatibility) or fallback to queried object.
            $page_id_from_form    = isset( $user_data['wwlc_page_id'] ) ? intval( $user_data['wwlc_page_id'] ) : 0;
            $page_id_from_context = get_queried_object_id();

            // For AJAX requests, validate the form-supplied page_id is a published page
            // containing the registration form (shortcode or Gutenberg block).
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                if ( $page_id_from_form > 0 ) {
                    if ( $this->page_has_registration_form( $page_id_from_form ) ) {
                        $page_id = $page_id_from_form;
                    } else {
                        $page_id = 0;
                        wc_get_logger()->warning(
                            sprintf(
                                'WWLC Security: AJAX registration claimed page_id=%d which does not contain the registration form or is not published.',
                                $page_id_from_form
                            ),
                            array( 'source' => 'woocommerce-wholesale-lead-capture' )
                        );
                    }
                } else {
                    $page_id = $page_id_from_context;
                }
            } else {
                // For non-AJAX requests, use context page_id and verify form page_id matches (if provided).
                $page_id = $page_id_from_context > 0 ? $page_id_from_context : $page_id_from_form;

                // Additional security: if form has page_id, it must match context (unless AJAX).
                if ( $page_id_from_form > 0 && $page_id_from_form !== $page_id_from_context && $page_id_from_context > 0 ) {
                    // Log potential cross-page attack attempt.
                    wc_get_logger()->warning(
                        sprintf(
                            'SECURITY: Potential cross-page attack - Form page_id (%d) does not match context (%d)',
                            $page_id_from_form,
                            $page_id_from_context
                        ),
                        array( 'source' => 'woocommerce-wholesale-lead-capture' )
                    );
                    // Use context page_id to prevent attack.
                    $page_id = $page_id_from_context;
                }
            }

            // Secure nonce-based verification for auto_approve from shortcode.
            // The auto_approve value is stored in post meta (cache-safe) and the nonce provides CSRF protection.
            if ( isset( $user_data['wwlc_auto_approve_nonce'] ) && $page_id > 0 ) {
                $nonce = sanitize_text_field( $user_data['wwlc_auto_approve_nonce'] );

                // Verify nonce matches page context.
                if ( wp_verify_nonce( $nonce, 'wwlc_auto_approve_' . $page_id ) ) {
                    $stored_value = get_post_meta( $page_id, '_wwlc_shortcode_auto_approve', true );
                    if ( '' !== $stored_value ) {
                        $auto_approve = 'yes' === $stored_value;
                    }
                }
            }

            // Log unauthorized attempts (when old direct value is present but nonce is missing).
            if ( isset( $user_data['wwlc_auto_approve'] ) && ! isset( $user_data['wwlc_auto_approve_nonce'] ) ) {
                wc_get_logger()->warning(
                    sprintf(
                        'SECURITY: Unauthorized auto_approve attempt detected - IP: %s, Email: %s, Page ID: %s, Timestamp: %s',
                        isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
                        isset( $user_data['user_email'] ) ? sanitize_email( $user_data['user_email'] ) : 'unknown',
                        get_queried_object_id(),
                        current_time( 'mysql' )
                    ),
                    array( 'source' => 'woocommerce-wholesale-lead-capture' )
                );
            }

            // Add unapprove role.
            $this->_add_unapproved_role( $new_lead );
            update_user_meta( $new_lead->ID, 'wwlc_is_user_active', 'no' );

            // WPML support.
            if ( is_object( $sitepress ) ) {
                $lang = get_user_meta( $new_lead->ID, 'wwlc_user_lang_wpml', true );
                if ( $lang ) {
                    $sitepress->switch_lang( $lang );
                }
            }

            if ( $auto_approve ) {

                $this->wwlc_approve_user( array( 'userObject' => $new_lead ) );

                // Send new wholesale lead auto approved email to admin(s).
                $wc_emails['WWLC_Email_New_Wholesale_Lead_Auto_Approved']->trigger( $new_lead );

                do_action( 'wwlc_action_auto_approve_user', $new_lead );

                // Logs in the user automatically.
                if ( apply_filters( 'wwlc_login_user_when_auto_approve', true, $new_lead, $user_data ) ) {
                    wp_clear_auth_cookie();
                    wp_set_current_user( $new_lead->ID );
                    wp_set_auth_cookie( $new_lead->ID );
                }
            } else {

                // Send new wholesale lead email to admin(s).
                $wc_emails['WWLC_Email_New_Wholesale_Lead']->trigger( $new_lead );

            }

            // Send successful registration email to new wholesale lead user.
            $wc_emails['WWLC_Email_Wholesale_Application_Received']->trigger( $new_lead, $user_data, $user_pass );

            do_action( 'wwlc_new_user_email', $new_lead->ID, $user_pass );

            return $auto_approve;
        }

        /**
         * Save customer billing address.
         *
         * @param int $user_ID The user ID.
         *
         * @since 1.4.0
         */
        public function wwlc_save_customer_billing_address( $user_ID ) {

            // User Registration Fields.
            $user_obj = get_userdata( $user_ID );
            $f_name   = get_user_meta( $user_ID, 'first_name', true );
            $l_name   = get_user_meta( $user_ID, 'last_name', true );
            $company  = get_user_meta( $user_ID, 'wwlc_company_name', true );
            $addr1    = get_user_meta( $user_ID, 'wwlc_address', true );
            $addr2    = get_user_meta( $user_ID, 'wwlc_address_2', true );
            $city     = get_user_meta( $user_ID, 'wwlc_city', true );
            $postcode = get_user_meta( $user_ID, 'wwlc_postcode', true );
            $country  = get_user_meta( $user_ID, 'wwlc_country', true );
            $state    = get_user_meta( $user_ID, 'wwlc_state', true );
            $phone    = get_user_meta( $user_ID, 'wwlc_phone', true );
            $email    = ( ! empty( $user_obj ) && ! empty( $user_obj->user_email ) ) ? $user_obj->user_email : '';

            if ( ! empty( $f_name ) ) {
                update_user_meta( $user_ID, 'billing_first_name', $f_name );
            }

            if ( ! empty( $l_name ) ) {
                update_user_meta( $user_ID, 'billing_last_name', $l_name );
            }

            if ( ! empty( $company ) ) {
                update_user_meta( $user_ID, 'billing_company', $company );
            }

            if ( ! empty( $addr1 ) ) {
                update_user_meta( $user_ID, 'billing_address_1', $addr1 );
            }

            if ( ! empty( $addr2 ) ) {
                update_user_meta( $user_ID, 'billing_address_2', $addr2 );
            }

            if ( ! empty( $city ) ) {
                update_user_meta( $user_ID, 'billing_city', $city );
            }

            if ( ! empty( $postcode ) ) {
                update_user_meta( $user_ID, 'billing_postcode', $postcode );
            }

            if ( ! empty( $country ) ) {
                update_user_meta( $user_ID, 'billing_country', $country );
            }

            if ( ! empty( $state ) ) {
                update_user_meta( $user_ID, 'billing_state', $state );
            }

            if ( ! empty( $phone ) ) {
                update_user_meta( $user_ID, 'billing_phone', $phone );
            }

            if ( ! empty( $email ) ) {
                update_user_meta( $user_ID, 'billing_email', $email );
            }
        }

        /**
         * Save customer shipping address.
         *
         * @param int   $user_ID          The user ID.
         * @param array $shipping_address Associative array of shipping address fields.
         *
         * @since 2.0.0 - Added this method to save the shipping address.
         */
        public function wwlc_save_customer_shipping_address( $user_ID, $shipping_address ) {

            if ( ! is_array( $shipping_address ) ) {
                return;
            }

            $same_as_billing = wc_string_to_bool( $shipping_address['same_as_billing'] ?? 'no' );

            $first_name = $same_as_billing ? get_user_meta( $user_ID, 'billing_first_name', true ) : ( $shipping_address['first_name'] ?? '' );
            $last_name  = $same_as_billing ? get_user_meta( $user_ID, 'billing_last_name', true ) : ( $shipping_address['last_name'] ?? '' );
            $address_1  = $same_as_billing ? get_user_meta( $user_ID, 'billing_address_1', true ) : ( $shipping_address['address_1'] ?? '' );
            $address_2  = $same_as_billing ? get_user_meta( $user_ID, 'billing_address_2', true ) : ( $shipping_address['address_2'] ?? '' );
            $city       = $same_as_billing ? get_user_meta( $user_ID, 'billing_city', true ) : ( $shipping_address['city'] ?? '' );
            $postcode   = $same_as_billing ? get_user_meta( $user_ID, 'billing_postcode', true ) : ( $shipping_address['postcode'] ?? '' );
            $country    = $same_as_billing ? get_user_meta( $user_ID, 'billing_country', true ) : ( $shipping_address['country'] ?? '' );
            $state      = $same_as_billing ? get_user_meta( $user_ID, 'billing_state', true ) : ( $shipping_address['state'] ?? '' );
            $company    = $same_as_billing ? get_user_meta( $user_ID, 'billing_company', true ) : ( $shipping_address['company'] ?? '' );
            $phone      = $same_as_billing ? get_user_meta( $user_ID, 'billing_phone', true ) : ( $shipping_address['phone'] ?? '' );
            $email      = $same_as_billing ? get_user_meta( $user_ID, 'billing_email', true ) : ( $shipping_address['email'] ?? '' );

            if ( ! empty( $first_name ) ) {
                update_user_meta( $user_ID, 'shipping_first_name', $first_name );
            }

            if ( ! empty( $last_name ) ) {
                update_user_meta( $user_ID, 'shipping_last_name', $last_name );
            }

            if ( ! empty( $address_1 ) ) {
                update_user_meta( $user_ID, 'shipping_address_1', $address_1 );
            }

            if ( ! empty( $address_2 ) ) {
                update_user_meta( $user_ID, 'shipping_address_2', $address_2 );
            }

            if ( ! empty( $city ) ) {
                update_user_meta( $user_ID, 'shipping_city', $city );
            }

            if ( ! empty( $postcode ) ) {
                update_user_meta( $user_ID, 'shipping_postcode', $postcode );
            }

            if ( ! empty( $country ) ) {
                update_user_meta( $user_ID, 'shipping_country', $country );
            }

            if ( ! empty( $state ) ) {
                update_user_meta( $user_ID, 'shipping_state', $state );
            }

            if ( ! empty( $company ) ) {
                update_user_meta( $user_ID, 'shipping_company', $company );
            }

            if ( ! empty( $phone ) ) {
                update_user_meta( $user_ID, 'shipping_phone', $phone );
            }

            if ( ! empty( $email ) ) {
                update_user_meta( $user_ID, 'shipping_email', $email );
            }
        }

        /**
         * Get states by country code.
         *
         * @param string $cc The country code provided by the form.
         *
         * @since 1.4.0
         */
        public function get_states( $cc ) {

            $states = new WC_Countries();
            $cc     = isset( $_POST['cc'] ) ? sanitize_text_field( wp_unslash( $_POST['cc'] ) ) : ''; // phpcs:ignore.
            $list   = $states->get_states( $cc );

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                if ( ! empty( $list ) ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
							'status' => 'success',
							'states' => $list,
						)
                    );
                    die();

                } else {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
							'status' => 'error',
						)
                    );
                    die();

                }
            } else {
                return false;
            }
        }

        /**
         * Set user as approved.
         *
         * @param array $user_data Array of user data.
         *
         * @return bool
         * @since 1.7.0 Added code to use the role set in the user meta (defined by registration form shortcode).
         * @since 1.7.0 Add WPML support.
         * @since 1.7.1 Removed code relating to displaying passwords.
         * @since 2.0.0 Update logic to remove unused roles. Added support for custom roles.
         * @since 2.0.2 Address security issue with role assignment.
         * checking if the role is valid and assign the default role if not.
         * @since 2.0.3.3 Added defense-in-depth role escalation protection.
         */
        public function wwlc_approve_user( $user_data ) {

            global $sitepress;

            $wc_emails = WC()->mailer()->get_emails();

            if ( array_key_exists( 'userID', $user_data ) ) {
                $user      = get_userdata( $user_data['userID'] );
                $userRoles = isset( $user_data['old_user_roles'] ) ? $user_data['old_user_roles'] : $user->roles;
            } else {
                $user      = &$user_data['userObject'];
                $userRoles = $user->roles;
            }

            if ( in_array( WWLC_UNAPPROVED_ROLE, (array) $userRoles, true ) ||
                in_array( WWLC_REJECTED_ROLE, (array) $userRoles, true ) ||
                isset( $user_data['role'] ) ) {

                do_action( 'wwlc_action_before_approve_user', $user );

                // WPML support.
                if ( is_object( $sitepress ) ) {
                    $lang = get_user_meta( $user->ID, 'wwlc_user_lang_wpml', true );
                    if ( $lang ) {
                        $sitepress->switch_lang( $lang );
                    }
                }

                // check if custom role is set and apply if true.
                $custom_role   = self::sanitize_custom_role( get_user_meta( $user->ID, 'wwlc_custom_set_role', true ) );
                $new_user_role = $custom_role ? $custom_role : trim( get_option( 'wwlc_general_new_lead_role' ) );

                // default to customer if new approved lead role is not set.
                if ( empty( $new_user_role ) || ! $new_user_role ) {
                    $new_user_role = 'customer';
                }

                if ( isset( $user_data['role'] ) ) {
                    $new_user_role = $this->maybe_assign_role( $user_data['role'] );
                }

                // Defense-in-depth: validate the final role through sanitize_custom_role()
                // to ensure no code path can assign a privileged role (e.g. administrator).
                $new_user_role = $this->_validate_and_sanitize_role( $new_user_role, $user->ID, 'approval' );

                $this->_remove_all_roles( $user );

                // Assign new user role.
                $user->add_role( $new_user_role );

                // Save approval date.
                update_user_meta( $user->ID, 'wwlc_approval_date', current_time( 'mysql' ) );
                update_user_meta( $user->ID, 'wwlc_is_user_active', 'yes' );

                // Delete rejection date.
                delete_user_meta( $user->ID, 'wwlc_rejection_date' );

                // Send successful registration email to new wholesale lead user.
                $wc_emails['WWLC_Email_Wholesale_Account_Approved']->trigger( $user );

                // @TODO WWLC-206 : This code needs to be removed on future versions.
                // Remove temp user pass
                delete_option( 'wwlc_password_temp_' . $user->ID );

                do_action( 'wwlc_action_after_approve_user', $user );

                return true;

            }

            return false;
        }

        /**
         * Check if the requested role is valid. If not, assign the default role.
         *
         * @param string $role The requested role.
         *
         * @return string The assigned role.
         * @since 2.0.2 - Added check to assign the default role if the requested role is not valid.
         */
        public function maybe_assign_role( $role ) {

            $role = sanitize_text_field( $role );

            if ( wwlc_check_role( $role ) ) {
                return $role;
            }

            return get_option( 'wwlc_general_new_lead_role', WWLC_UNAPPROVED_ROLE );
        }

        /**
         * Set user as rejected.
         *
         * @param array $user_data Array of user data.
         *
         * @return bool
         * @since 1.17.5 Automatically remove user after rejection.
         * @since 2.0.0 Update logic to remove unused roles.
         *
         * @since 1.0.0
         * @since 1.7.0 Add WPML support.
         */
        public function wwlc_reject_user( $user_data ) {

            global $sitepress;

            $wc_emails = WC()->mailer()->get_emails();

            if ( array_key_exists( 'userID', $user_data ) ) {
                $user = get_userdata( $user_data['userID'] );
            } else {
                $user = &$user_data['userObject'];
            }

            if ( ! $user instanceof WP_User ) {
                return false;
            }

            // Only reject a user if they are not already rejected.
            $already_rejected = in_array( WWLC_REJECTED_ROLE, (array) $user->roles, true );

            if ( WWLC_REJECTED_ROLE !== $user->roles[0] || ! $already_rejected ) {
                do_action( 'wwlc_action_before_reject_user', $user );

                // WPML support.
                if ( is_object( $sitepress ) ) {
                    $lang = get_user_meta( $user->ID, 'wwlc_user_lang_wpml', true );
                    if ( $lang ) {
                        $sitepress->switch_lang( $lang );
                    }
                }

                // Save rejection date.
                update_user_meta( $user->ID, 'wwlc_rejection_date', current_time( 'mysql' ) );
                delete_user_meta( $user->ID, 'wwlc_approval_date' );
                update_user_meta( $user->ID, 'wwlc_is_user_active', 'no' );

                // Send wholesale leads email.
                $wc_emails['WWLC_Email_Wholesale_Account_Rejected']->trigger( $user );

                // @TODO WWLC-206 : This code needs to be removed on future versions.
                // Remove temp user pass
                delete_option( 'wwlc_password_temp_' . $user->ID );

                $this->_remove_all_roles( $user );

                // Remove user.
                if ( apply_filters( 'wwlc_remove_user_after_rejection', get_option( 'wwlc_remove_user_after_rejection' ) === 'yes' ? true : false, $user ) ) {
                    wp_delete_user( $user->ID );
                } else {
                    $this->_add_rejected_role( $user );
                }

                do_action( 'wwlc_action_after_reject_user', $user );

                return true;

            } else {
                return false;
            }
        }

        /**
         * Activate user.
         *
         * @param array $user_data Array of user data.
         *
         * @return bool
         * @since 1.0.0
         * @since 2.0.0 Update logic to remove unused roles.
         */
        public function wwlc_activate_user( $user_data ) {

            if ( array_key_exists( 'userID', $user_data ) ) {
                $user = get_userdata( $user_data['userID'] );
            } else {
                $user = &$user_data['userObject'];
            }

            if ( ! $user instanceof WP_User ) {
                return false;
            }

            do_action( 'wwlc_action_before_activate_user', $user );

            // default to customer if new approved lead role is not set.
            $new_user_role = trim( get_option( 'wwlc_general_new_lead_role', 'customer' ) );

            // Defense-in-depth: validate the final role through sanitize_custom_role()
            // to ensure no code path can assign a privileged role (e.g. administrator).
            $new_user_role = $this->_validate_and_sanitize_role( $new_user_role, $user->ID, 'activation' );

            $this->_remove_all_roles( $user );

            if ( empty( $user->roles ) || ! in_array( $new_user_role, $user->roles, true ) ) {
                $user->add_role( $new_user_role );
            }

            $user_activated = update_user_meta( $user->ID, 'wwlc_is_user_active', 'yes' );

            if ( ! $user_activated ) {
                return false;
            }

            do_action( 'wwlc_action_after_activate_user', $user );

            return true;
        }

        /**
         * Deactivate user.
         *
         * @param array $user_data Array of user data.
         *
         * @return bool
         * @since 1.0.0
         * @since 2.0.0 Update logic to remove unused roles.
         * @since 2.0.5 Destroy active session tokens so a deactivated user is logged out
         *              of any open browser session and cannot continue placing orders.
         */
        public function wwlc_deactivate_user( $user_data ) {

            if ( array_key_exists( 'userID', $user_data ) ) {
                $user = get_userdata( $user_data['userID'] );
            } else {
                $user = &$user_data['userObject'];
            }

            if ( ! $user instanceof WP_User ) {
                return false;
            }

            do_action( 'wwlc_action_before_deactivate_user', $user );

            $this->_remove_all_roles( $user );
            $this->_add_customer_role( $user );

            $current_value    = get_user_meta( $user->ID, 'wwlc_is_user_active', true );
            $user_deactivated = update_user_meta( $user->ID, 'wwlc_is_user_active', 'no' );

            // Force-logout the deactivated user from any active browser sessions.
            // Runs unconditionally — a no-op on already-deactivated users with no tokens —
            // so repeated calls remain idempotent. Only WP auth tokens are invalidated; any
            // residual WC guest cart cookie is harmless because role-based wholesale pricing
            // is no longer applied once the wholesale roles are removed above.
            WP_Session_Tokens::get_instance( $user->ID )->destroy_all();

            do_action( 'wwlc_action_after_deactivate_user', $user );

            if ( $user_deactivated ) {
                return true;
            }

            // Return true if the user is already deactivated.
            if ( 'no' === $current_value ) {
                return true;
            }

            return false;
        }

        /**
         * Validate and sanitize a proposed user role, falling back to a safe default if blocked.
         *
         * Uses sanitize_custom_role() to ensure no privileged role (e.g. administrator) can be
         * assigned. If the proposed role is blocked, logs a security warning and falls back to
         * the configured default role or 'customer'.
         *
         * @param string $proposed_role The role to validate.
         * @param int    $user_id       The user ID (for logging).
         * @param string $context       Description of the operation (e.g. 'approval', 'activation') for log messages.
         *
         * @return string The safe role to assign.
         * @since 2.0.3.5
         */
        private function _validate_and_sanitize_role( $proposed_role, $user_id, $context ) {

            if ( empty( $proposed_role ) ) {
                return 'customer';
            }

            if ( 'customer' === $proposed_role ) {
                return $proposed_role;
            }

            $sanitized_role = self::sanitize_custom_role( $proposed_role );

            if ( $sanitized_role ) {
                return $sanitized_role;
            }

            wc_get_logger()->warning(
                sprintf(
                    /* translators: 1: role slug, 2: user ID, 3: operation context (e.g. "approval", "activation"). */
                    'WWLC Security: Blocked privileged role "%1$s" assignment for user #%2$d during %3$s. Falling back to default role.',
                    $proposed_role,
                    $user_id,
                    $context
                ),
                array( 'source' => 'woocommerce-wholesale-lead-capture' )
            );

            // Try the configured default role as fallback before resorting to 'customer'.
            $default_role = self::sanitize_custom_role( trim( get_option( 'wwlc_general_new_lead_role' ) ) );

            return $default_role ? $default_role : 'customer';
        }

        /**
         * Remove all roles from a user.
         *
         * @param WP_User $user The user object.
         *
         * @return void
         * @since 2.0.0 Added
         */
        public function _remove_all_roles( &$user ) {
            $roles = (array) $user->roles;

            foreach ( $roles as $role ) {
                $user->remove_role( $role );
            }
        }

        /**
         * Add customer role to user
         *
         * @param WP_User $user The user object.
         *
         * @return void
         * @since 2.0.0 Added
         */
        private function _add_customer_role( &$user ) {
            if ( ! in_array( 'customer', $user->roles, true ) ) {
                $user->add_role( 'customer' );
            }
        }

        /**
         * Add unapproved role to a user.
         *
         * @param WP_User $user WP_User object.
         *
         * @since 1.0.0
         */
        private function _add_unapproved_role( &$user ) {

            if ( ! in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {
                $user->add_role( WWLC_UNAPPROVED_ROLE );
            }
        }

        /**
         * Remove unapproved role to a user.
         *
         * @param WP_User $user WP_User object.
         *
         * @since 1.0.0
         */
        private function _remove_unapproved_role( &$user ) {

            if ( in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {
                $user->remove_role( WWLC_UNAPPROVED_ROLE );
            }
        }

        /**
         * Add rejected role to a user.
         *
         * @param WP_User $user WP_User object.
         *
         * @since 1.0.0
         */
        private function _add_rejected_role( &$user ) {

            if ( ! in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {
                $user->add_role( WWLC_REJECTED_ROLE );
            }
        }

        /**
         * Remove rejected role to a user.
         *
         * @param WP_User $user WP_User object.
         *
         * @since 1.0.0
         */
        private function _remove_rejected_role( &$user ) {

            if ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {
                $user->remove_role( WWLC_REJECTED_ROLE );
            }
        }

        /**
         * Get total number of unapproved users.
         *
         * @return int
         * @since 2.0.0 - Refactor count by role to a function.
         * @since 1.0.0
         */
        public function get_total_unapproved_users() {
            return $this->count_users_by_role( WWLC_UNAPPROVED_ROLE );
        }

        /**
         * Get total number of users with a specific role.
         *
         * @param string|array $roles The roles to check.
         *
         * @return int
         *
         * @since 2.0.0
         */
        public function count_users_by_role( $roles = 'all' ) {
            $args = array(
				'fields' => array( 'ID' ),
            );

            if ( 'all' !== $roles ) {
                $args['role__in'] = $roles;
            } else {
                $args['role__in'] = WWLC_Helper_Functions::get_lead_roles();
            }

            return count(
                get_users(
                    $args
                )
            );
        }

        /**
         * Total unapproved users bubble notification.
         *
         * @since 1.0.0
         */
        public function wwlc_total_unapproved_users_bubble_notification() {

            global $menu;
            $unapproved_users_total = $this->total_unapproved_users;

            if ( $unapproved_users_total ) {

                foreach ( $menu as $key => $value ) {

                    if ( 'users.php' === $menu[ $key ][2] ) {

                        $menu[ $key ][0] .= ' <span class="awaiting-mod count-' . $unapproved_users_total . '"><span class="unapproved-count">' . $unapproved_users_total . '</span></span>'; //phpcs:ignore

                        return;

                    }
                }
            }
        }

        /**
         * Total unapproved user admin notice.
         *
         * @since 1.0.0
         * @since 2.0.0 Renamed to use unapproved role.
         */
        public function wwlc_total_unapproved_users_admin_notice() {

            // Check if the current screen is the dashboard.
            $screen = get_current_screen();
            if ( 'dashboard' !== $screen->base ) {
                return;
            }

            global $current_user;
            $user_id = $current_user->ID;

            if ( ! get_user_meta( $user_id, 'wwlc_ignore_unapproved_users_notice', true ) ) {

                $unapproved_users_total = $this->total_unapproved_users;

                if ( $unapproved_users_total ) {
                    ?>

                    <div class="error">
                        <p>
                            <?php

                            /* translators: %1$s Total of unapproved users*/
                            $unapproved_user_text_notice = sprintf( _n( '%1$s Pending User', '%1$s Pending Users', $unapproved_users_total, 'woocommerce-wholesale-lead-capture' ), esc_html( $unapproved_users_total ) );
                            /* translators: %1$s Admin url */
                            $unapproved_user_text_notice .= ' | ' . sprintf( _n( '<a href="%1$s">View Users</a>', '<a href="%1$s">View Users</a>', $unapproved_users_total, 'woocommerce-wholesale-lead-capture' ), esc_url( get_admin_url( null, 'admin.php?page=wwlc-leads-admin-page' ) ) );

                            echo wp_kses_post( $unapproved_user_text_notice );
                            ?>
                            <a href="<?php echo esc_url( wp_nonce_url( '?wwlc_ignore_unapproved_users_notice=0', 'wwlc_dismiss_unapproved_notice', '_wwlc_nonce' ) ); ?>"
                                style="float: right;"
                                id="wwlc_dismiss_unapproved_user_notice"><?php esc_html_e( 'Hide Notice', 'woocommerce-wholesale-lead-capture' ); ?></a>
                        </p>
                    </div>
                    <?php

                }
            }
        }

        /**
         * Hide total unapproved users admin notice.
         *
         * @since 1.0.0
         */
        public function wwlc_hide_total_unapproved_users_admin_notice() {
            if ( ! isset( $_GET['wwlc_ignore_unapproved_users_notice'], $_GET['_wwlc_nonce'] ) ) {
                return;
            }

            if ( '0' !== sanitize_text_field( wp_unslash( $_GET['wwlc_ignore_unapproved_users_notice'] ) ) ) {
                return;
            }

            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wwlc_nonce'] ) ), 'wwlc_dismiss_unapproved_notice' ) ) {
                return;
            }

            $user_id = get_current_user_id();
            add_user_meta( $user_id, 'wwlc_ignore_unapproved_users_notice', 'true', true );
        }

        /**
         * Hide important notice about properly managing wholesale users.
         *
         * @since 1.3.1
         */
        public function wwlc_hide_important_proper_user_management_notice() {
            if ( ! isset( $_GET['wwlc_dismiss_important_user_management_notice'], $_GET['_wwlc_nonce'] ) ) {
                return;
            }

            if ( '0' !== sanitize_text_field( wp_unslash( $_GET['wwlc_dismiss_important_user_management_notice'] ) ) ) {
                return;
            }

            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wwlc_nonce'] ) ), 'wwlc_dismiss_mgmt_notice' ) ) {
                return;
            }

            $user_id = get_current_user_id();
            add_user_meta( $user_id, 'wwlc_dismiss_important_user_management_notice', 'true', true );
        }

        /**
         * Move files from temporary folder to their respective wholesale folder.
         * This should be run after the user has been created.
         *
         * @param int $userID The user ID.
         *
         * @since 1.6.0
         */
        public function _move_user_files_to_permanent( $userID ) {

            $wwlc_forms  = WWLC_Forms::instance();
            $file_fields = $wwlc_forms->wwlc_get_file_custom_fields();

            if ( ! is_array( $file_fields ) ) {
                return;
            }

            $temp_upload        = get_option( 'wwlc_temp_upload_directory' );
            $upload_dir         = wp_upload_dir();
            $user_wholesale_dir = $upload_dir['basedir'] . '/wholesale-customers/' . $userID;

            // if the user's wholesale directory doesn't exist, create it.
            if ( ! file_exists( $user_wholesale_dir ) ) {
                wp_mkdir_p( $user_wholesale_dir );
            }

            // Protect directory from direct web access.
            wwlc_protect_upload_directory( $upload_dir['basedir'] . '/wholesale-customers' );

            foreach ( $file_fields as $field ) {

                $file_name = get_user_meta( $userID, $field['name'], true );
                if ( ! empty( $file_name ) ) {
                    $temp_file = $temp_upload['dir'] . '/' . $file_name;
                    $move_to   = $user_wholesale_dir . '/' . $file_name;
                    $file_url  = $upload_dir['baseurl'] . '/wholesale-customers/' . $userID . '/' . $file_name;

                    if ( file_exists( $temp_file ) ) {
                        rename( $temp_file, $move_to ); // phpcs:ignore.
                    }
                    update_user_meta( $userID, $field['name'], $file_url, $file_name );
                }
            }
        }

        /**
         * Delete file from their respective wholesale folder.
         * This should be run after the user has been updated.
         *
         * @param int    $userID The user ID.
         * @param string $filename The file name.
         *
         * @since 1.17.5
         */
        public function _delete_user_file( $userID, $filename ) {

            $temp_upload = get_option( 'wwlc_temp_upload_directory' );
            $upload_dir  = wp_upload_dir();
            $file_path   = $upload_dir['basedir'] . '/wholesale-customers/' . $userID . '/' . $filename;

            // if the user wholesale directory does exist, remove file.
            if ( file_exists( $file_path ) ) {
                if ( unlink( $file_path ) ) { // phpcs:ignore.
                    return true;
                }
            }

            return false;
        }

        /**
         * Get file.
         *
         * @param int $userID The user ID.
         * @param int $fieldid The field id.
         *
         * @since 1.17.5
         */
        public function _get_user_file_name( $userID, $fieldid ) {
            $file_url  = esc_url( get_user_meta( $userID, $fieldid, true ) );
            $file_tmp  = explode( '/', $file_url );
            $file_name = end( $file_tmp );

            return $file_name;
        }

        /**
         * Fix an issue when an admin is updating a users password, it will send an email to the user even if its still in unapprove status.
         * Ticket: WWLC-112
         *
         * @param bool  $send Whether to send the email.
         * @param array $user The original user array.
         * @param array $user_data The updated user array.
         *
         * @return bool
         * @since 1.6.2
         * @since 1.6.3 Stop sending password change emails for users that has 'wwlc_unapproved' role.
         * @since 2.0.0 Remove wwlc_unmoderated role check.
         */
        public function wwlc_password_change_email( $send, $user, $user_data ) {

            // If the user has 'wwlc_unapproved' to their role we stop sending email.
            // Note: $user_data[ 'role' ] is a string not an array.
            if ( is_array( $user_data ) && isset( $user_data['role'] ) && in_array( $user_data['role'], array( 'wwlc_unapproved' ), true ) ) {
                return false;
            } else {
                return $send;
            }
        }

        /**
         * Block password reset for unapproved and rejected wholesale users.
         *
         * Prevents unapproved users from bypassing login restrictions via the
         * password reset flow, which would otherwise automatically log them in
         * after resetting their password.
         *
         * @param bool $allow   Whether to allow the password reset.
         * @param int  $user_id The ID of the user requesting the password reset.
         *
         * @return bool|WP_Error False or WP_Error to block, true to allow.
         * @since 2.0.4
         */
        public function wwlc_block_unapproved_password_reset( $allow, $user_id ) {

            $user = get_user_by( 'id', $user_id );

            if ( ! $user instanceof WP_User || ! is_array( $user->roles ) ) {
                return $allow;
            }

            if ( in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {
                return new WP_Error(
                    'wwlc_password_reset_not_allowed',
                    __( 'Password reset is not allowed. Your account is still awaiting approval.', 'woocommerce-wholesale-lead-capture' )
                );
            }

            if ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {
                return new WP_Error(
                    'wwlc_password_reset_not_allowed',
                    __( 'Password reset is not allowed for this account.', 'woocommerce-wholesale-lead-capture' )
                );
            }

            return $allow;
        }

        /**
         * Get the list of allowed registration field keys.
         *
         * Builds an allowlist from built-in registration fields and admin-configured
         * custom fields. Used to prevent arbitrary user meta injection during registration.
         *
         * @return array List of allowed field key strings.
         * @since 2.0.3.2
         * @access public
         */
        public function get_allowed_registration_field_keys() {
            global $WWLC_REGISTRATION_FIELDS;

            $allowed_keys = array();

            // Add built-in registration field names.
            if ( ! empty( $WWLC_REGISTRATION_FIELDS ) ) {
                foreach ( $WWLC_REGISTRATION_FIELDS as $field ) {
                    if ( isset( $field['name'] ) ) {
                        $allowed_keys[] = $field['name'];
                    }
                }
            }

            // Add admin-configured custom field IDs.
            $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            if ( ! empty( $custom_fields ) ) {
                $allowed_keys = array_merge( $allowed_keys, array_keys( $custom_fields ) );
            }

            // Allow internal fields that are set programmatically (not from user input).
            $allowed_keys[] = 'wwlc_user_lang_wpml';

            /**
             * Filter the list of allowed registration field keys for user meta storage.
             *
             * @param array $allowed_keys The allowed field keys.
             *
             * @since 2.0.3.2
             */
            return apply_filters( 'wwlc_allowed_registration_field_keys', $allowed_keys );
        }

        /**
         * Sanitize the set custom role to make sure it is only set to allowed roles.
         *
         * @param string $custom_role Custom role to sanitize.
         *
         * @return string Sanitized custom role.
         * @since 1.7.0
         * @since 2.0.3.2 Block roles with privileged capabilities.
         */
        public static function sanitize_custom_role( $custom_role ) {

            if ( ! $custom_role ) {
                return '';
            }

            if ( ! function_exists( 'get_editable_roles' ) ) {
                require_once ABSPATH . '/wp-admin/includes/user.php';
            }

            $available_roles = get_editable_roles();

            // Role must exist.
            if ( ! isset( $available_roles[ $custom_role ] ) ) {
                return '';
            }

            // Block any role that has privileged capabilities.
            $privileged_capabilities = apply_filters(
                'wwlc_restricted_role_capabilities',
                array(
					'manage_options',
					'manage_woocommerce',
					'edit_users',
					'create_users',
					'delete_users',
					'promote_users',
					'install_plugins',
					'edit_plugins',
					'activate_plugins',
					'edit_themes',
					'switch_themes',
				)
            );

            $role_capabilities = $available_roles[ $custom_role ]['capabilities'] ?? array();
            foreach ( $privileged_capabilities as $cap ) {
                if ( ! empty( $role_capabilities[ $cap ] ) ) {
                    return '';
                }
            }

            // Also apply an explicit blocklist for well-known privileged roles as defense-in-depth.
            // Only roles with truly privileged capabilities are blocked here. Content roles
            // (editor, author, contributor) are not blocked because admins may legitimately
            // configure them as the "New Lead Role" and they lack privileged capabilities.
            $restricted_roles = apply_filters(
                'wwlc_registration_restricted_roles',
                array(
					'administrator',
					'shop_manager',
				)
            );

            // Deprecation bridge: support the old filter name for backwards compatibility.
            if ( has_filter( 'wwlc_registration_allowed_roles' ) ) {
                _deprecated_hook( 'wwlc_registration_allowed_roles', '2.0.3.2', 'wwlc_registration_restricted_roles' );
                $restricted_roles = apply_filters( 'wwlc_registration_allowed_roles', $restricted_roles );
            }

            if ( in_array( $custom_role, $restricted_roles, true ) ) {
                return '';
            }

            return $custom_role;
        }

        /**
         * Check if a given page contains the WWLC registration form.
         *
         * Checks both classic shortcodes in post_content and Gutenberg blocks.
         * Also verifies the page is published.
         *
         * @param int $page_id The page ID to check.
         *
         * @return bool Whether the page contains the registration form.
         * @since 2.0.3.3
         */
        private function page_has_registration_form( $page_id ) {

            $page = get_post( $page_id );

            if ( ! $page || 'publish' !== $page->post_status ) {
                return false;
            }

            // Check classic shortcode.
            if ( has_shortcode( $page->post_content, 'wwlc_registration_form' ) ) {
                return true;
            }

            // Check Gutenberg block (e.g. shortcode block or custom WWLC block).
            if ( function_exists( 'has_block' ) && has_block( 'core/shortcode', $page->post_content ) ) {
                // The core/shortcode block embeds shortcodes in its innerHTML.
                // has_shortcode already checked raw content, but parse_blocks can reveal nested content.
                $blocks = parse_blocks( $page->post_content );
                foreach ( $blocks as $block ) {
                    if ( 'core/shortcode' === $block['blockName']
                        && ! empty( $block['innerHTML'] )
                        && has_shortcode( $block['innerHTML'], 'wwlc_registration_form' )
                    ) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Redirect user to my account page if viewing the registration page (set on settings) while logged in.
         *
         * @since 1.7.1
         * @since 1.8.0  Get page option url via wwlc_get_url_of_page_option function
         * @since 1.14.4 When user is in the backend don't translate the strings.
         * @since 1.17 Added !is_checkout() condition to avoid endless loop in checkout page.
         * @access public
         */
        public function registration_page_redirect_logged_in_user() {

            global $post;

            if ( ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) || ! is_checkout() ) { // phpcs:ignore.
                return;
            }

            $registration_page = url_to_postid( wwlc_get_url_of_page_option( 'wwlc_general_registration_page' ) );
            $my_account_page   = get_option( 'woocommerce_myaccount_page_id' );

            $show_registration_page = apply_filters( 'wwlc_upgrade_account', true );

            if ( $show_registration_page && $registration_page && $my_account_page && is_object( $post ) && $registration_page === $post->ID && $registration_page !== $my_account_page ) {

                wp_safe_redirect( get_permalink( $my_account_page ), 302 );
                exit;

            }
        }

        /**
         * Detects if auto login parameter is set in shortcode
         *
         * @param bool    $autologin Default value for auto login which is true if auto approve option is enabled.
         * @param WP_User $new_lead User Object Data.
         * @param array   $user_data Registration form field data.
         *
         * @return bool
         * @since 1.8.0
         * @access public
         */
        public function wwlc_auto_login_check( $autologin, $new_lead, $user_data ) {

            // Secure nonce-based verification for auto_login from shortcode.
            // The transient stores both the boolean value and page_id to prevent cross-page attacks.
            if ( isset( $user_data['wwlc_auto_login_nonce'] ) ) {
                $nonce = sanitize_text_field( $user_data['wwlc_auto_login_nonce'] );

                // Get page ID from form data (for AJAX compatibility) or fallback to queried object.
                $page_id_from_form    = isset( $user_data['wwlc_page_id'] ) ? intval( $user_data['wwlc_page_id'] ) : 0;
                $page_id_from_context = get_queried_object_id();

                // For AJAX requests, use form data; for direct requests, verify they match to prevent cross-page attacks.
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    $page_id = $page_id_from_form > 0 ? $page_id_from_form : $page_id_from_context;
                } else {
                    // For non-AJAX requests, use context page_id and verify form page_id matches (if provided).
                    $page_id = $page_id_from_context > 0 ? $page_id_from_context : $page_id_from_form;

                    // Additional security: if form has page_id, it must match context (unless AJAX).
                    if ( $page_id_from_form > 0 && $page_id_from_form !== $page_id_from_context && $page_id_from_context > 0 ) {
                        // Use context page_id to prevent attack.
                        $page_id = $page_id_from_context;
                    }
                }

                // Verify nonce matches page context.
                if ( $page_id > 0 && wp_verify_nonce( $nonce, 'wwlc_auto_login_' . $page_id ) ) {
                    // Get stored data from transient (contains both value and original page_id).
                    $stored_data = get_transient( 'wwlc_auto_login_' . $nonce );
                    if ( false !== $stored_data && is_array( $stored_data ) ) {
                        // Verify page_id matches to prevent cross-page attacks.
                        if ( isset( $stored_data['page_id'] ) && $stored_data['page_id'] === $page_id ) {
                            // Delete transient after use (single-use nonce).
                            delete_transient( 'wwlc_auto_login_' . $nonce );

                            return true;
                        }
                    }
                }
            }

            // Log unauthorized attempts (when old direct value is present but nonce is missing).
            if ( isset( $user_data['wwlc_auto_login'] ) && ! isset( $user_data['wwlc_auto_login_nonce'] ) ) {
                wc_get_logger()->warning(
                    sprintf(
                        'SECURITY: Unauthorized auto_login attempt detected - IP: %s, Email: %s, Page ID: %s, Timestamp: %s',
                        isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
                        isset( $user_data['user_email'] ) ? sanitize_email( $user_data['user_email'] ) : 'unknown',
                        get_queried_object_id(),
                        current_time( 'mysql' )
                    ),
                    array( 'source' => 'woocommerce-wholesale-lead-capture' )
                );
            }

            return $autologin;
        }

        /**
         * Redirects user properly. Triggers when loggin-in via wp-login.php and Wholesale Login Page.
         * Fix the issue when logging-in in a multisite, the user redirected to a blank page. WWLC-286
         *
         * @param string  $redirect Redirect URL.
         * @param WP_User $user WP_User object of the logged-in user.
         *
         * @since 1.14
         * @since 1.17.4 Prevent redirect to shop page if wwlc login redirect setting is note set.
         * @access public
         */
        public function wwlc_redirect_after_login( $redirect, $user ) {

            $wholesale_role = wwlc_get_wholesale_role( $user );

            if ( ! empty( $wholesale_role ) ) {

                $wwlc_login_redirect = wwlc_get_url_of_page_option( 'wwlc_general_login_redirect_page' );

                if ( ! empty( $wwlc_login_redirect ) ) {
                    $redirect_to = wp_unslash( $wwlc_login_redirect );
                } elseif ( wc_get_raw_referer() ) {
                    $redirect_to = wc_get_raw_referer();
                } else {
                    $redirect_to = wc_get_page_permalink( 'myaccount' );
                }
            }

            if ( isset( $redirect_to ) && $redirect_to ) {
                return wp_validate_redirect( apply_filters( 'wwlc_login_redirect_url', remove_query_arg( 'wc_error', $redirect_to ), $user ), wc_get_page_permalink( 'myaccount' ) );
            } else {
                return $redirect;
            }
        }

        /**
         * Currently when user tries to login via the My Account page from a different site, the account from original will be created to the subsite the user wants to login.
         * It will cause confusion for our WWLC plugin incase user is not yet approved from the other site but the created user in subsite was marked active.
         * Solution was to remove the user from subsite when login shows an error message.
         * Filters whether a user should be added to a site.
         *
         * @param bool|WP_Error $retval True if the user should be added to the site, false
         *                               or error object otherwise.
         * @param int           $user_id User ID.
         * @param string        $role User role.
         * @param int           $blog_id Site ID.
         *
         * @return bool
         * @since 1.14
         * @access public
         */
        public function wwlc_remove_user_from_blog( $retval, $user_id, $role, $blog_id ) { // phpcs:ignore.

            if ( is_multisite() ) {

                $username  = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in wwlc_create_user().
                $user_data = get_user_by( is_email( $username ) ? 'email' : 'login', $username );

                if ( $user_data && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
                    return false;
                }
            }

            return $retval;
        }

        /**
         * Sanitize the reCAPTCHA v3 score threshold option on save.
         *
         * Coerces non-numeric or out-of-range values to the default ('0.5') so a typo
         * in either settings UI (legacy WC or React) cannot persist a value that
         * silently disables the score gate at verification time.
         *
         * @param mixed $value Value submitted to update_option().
         * @return string Sanitized threshold as a numeric string in [0.0, 1.0].
         * @since 2.0.4.3
         */
        public function sanitize_recaptcha_v3_score_threshold( $value ) {

            if ( ! is_numeric( $value ) ) {
                return self::RECAPTCHA_V3_DEFAULT_THRESHOLD;
            }

            $float = (float) $value;
            if ( $float < 0.0 || $float > 1.0 ) {
                return self::RECAPTCHA_V3_DEFAULT_THRESHOLD;
            }

            return (string) $value;
        }

        /**
         * Execute model.
         *
         * @since 1.6.3
         * @access public
         */
        public function run() {

            // Authenticate User. Block Unapproved, Unapproved, Inactive and Reject Users.
            add_filter( 'wp_authenticate_user', array( $this, 'wwlc_wholesale_lead_authenticate' ), 10, 2 );

            // Redirect Wholesale User Accordingly After Successful Login. Fires only on wp-login.php.
            add_filter( 'login_redirect', array( $this, 'wwlc_wholesale_lead_login_redirect' ), 10, 3 );

            // Fires both on wp-login.php and Wholesale Login Page.
            add_filter( 'woocommerce_login_redirect', array( $this, 'wwlc_redirect_after_login' ), 20, 2 );

            // Fixes issue when user tries to login via the My Account page from different site, wc will add the user to the subsite if user doesn't exist there.
            add_filter( 'can_add_user_to_blog', array( $this, 'wwlc_remove_user_from_blog' ), 10, 4 );

            // Sanitize the v3 score threshold on save — coerce non-numeric / out-of-range values to the default
            // so a typo in the settings UI can't silently neutralise the score gate (e.g. "abc" → (float)0.0).
            add_filter( 'pre_update_option_wwlc_security_recaptcha_v3_score_threshold', array( $this, 'sanitize_recaptcha_v3_score_threshold' ) );

            // Redirect Wholesale User To Specific Page After Logging Out.
            add_filter( 'logout_url', array( $this, 'wwlc_wholesale_lead_logout_redirect' ), 10, 2 );
            add_action( 'wp_logout', array( $this, 'wwlc_clear_user_session_on_logout' ) );
            add_filter( 'allowed_redirect_hosts', array( $this, 'allow_custom_external_url_redirect' ), 10, 1 );

            // Total Unapproved Users Bubble Notification.
            add_action( 'admin_menu', array( $this, 'wwlc_total_unapproved_users_bubble_notification' ) );

            // Total Unapproved Users Admin Notice.
            add_action( 'admin_notices', array( $this, 'wwlc_total_unapproved_users_admin_notice' ) );

            // Hide Total Unapproved Users Admin Notice.
            add_action( 'admin_init', array( $this, 'wwlc_hide_total_unapproved_users_admin_notice' ) );

            // Hide Important Notice About Properly Managing Wholesale Users.
            add_action( 'admin_init', array( $this, 'wwlc_hide_important_proper_user_management_notice' ) );

            // Stop sending password change email if user is still in unapprove status.
            add_filter( 'send_password_change_email', array( $this, 'wwlc_password_change_email' ), 10, 3 );

            // Block password reset for unapproved and rejected wholesale users.
            add_filter( 'allow_password_reset', array( $this, 'wwlc_block_unapproved_password_reset' ), 10, 2 );

            // Handles user authentication when user logs in using wwlc login form.
            add_action( 'template_redirect', array( $this, 'wwlc_authenticate' ) );

            // Display inline success notice after registration.
            add_filter( 'wp', array( $this, 'wwlc_registration_form_print_notice' ) );

            // Approve user via user edit screen.
            add_action( 'profile_update', array( $this, 'wwlc_profile_update' ), 10, 2 );

            // Redirect user to my account page if viewing the registration page (set on settings) while logged in.
            add_action( 'wp', array( $this, 'registration_page_redirect_logged_in_user' ) );

            // Checks if auto auto login is set via shortcode.
            add_filter( 'wwlc_login_user_when_auto_approve', array( $this, 'wwlc_auto_login_check' ), 10, 3 );
        }
    }
}
