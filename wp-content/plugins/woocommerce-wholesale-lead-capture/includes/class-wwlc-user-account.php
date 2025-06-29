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

                    $_POST['login_error'] = implode( ' and ', $err );
                    add_action(
                        'wwlc_before_login_form',
                        function () {
                            wc_print_notice( sprintf( __( '%1$s  must not be empty.', 'woocommerce-wholesale-lead-capture' ), $_POST['login_error'] ), 'error' ); //phpcs:ignore
                        }
                    );

                } else {

                    $creds                  = array();
                    $creds['user_login']    = sanitize_text_field( $_POST['wwlc_username'] );
                    $creds['user_password'] = sanitize_text_field( $_POST['wwlc_password'] );
                    $creds['remember']      = isset( $_POST['rememberme'] ) ? true : false;
                    $user                   = wp_signon( $creds, is_ssl() );

                    if ( is_wp_error( $user ) ) {

                        $_POST['login_error'] = $user->get_error_message();
                        add_action(
                            'wwlc_before_login_form',
                            function () {
                                wc_print_notice( __( $_POST['login_error'] ), 'error' ); //phpcs:ignore
                            }
                        );

                    } elseif ( isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] ) ) {

                        $login_redirect = apply_filters( 'wwlc_login_redirect_url', esc_url( $_POST['redirect_to'] ), $user );
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
         * @param int     $userID        The user id.
         * @param WP_user $old_user_data WP_User object.
         *
         * @since 1.6.2 WWLC-28
         * @since 2.0.0 Removed unused role.
         */
        public function wwlc_profile_update( $userID, $old_user_data ) {

            $user               = get_userdata( $userID );
            $old_role_check     = array_intersect( $old_user_data->roles, array( 'wwlc_unapproved' ) );
            $updated_role_check = array_intersect( $user->roles, array( 'wwlc_unapproved' ) );

            // Only mark approve when the updated role is not equal to 'wwlc_unapproved'.
            // and the old role before the update is equal to 'wwlc_unapproved'.
            if ( ! empty( $old_role_check ) && empty( $updated_role_check ) ) {
                $this->wwlc_approve_user(
                    array(
                        'userID'         => $userID,
                        'old_user_roles' => $old_user_data->roles,
                    )
                );
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

                $message = apply_filters( 'wwlc_registration_success_message', isset( $_POST['inline_message'] ) ? $_POST['inline_message'] : '' ); //phpcs:ignore
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

            return substr( str_shuffle( MD5( microtime() ) ), 0, $length );
        }

        /**
         * WWLC authentication filter. It checks if user is inactive, unapproved or rejected and kick
         * there asses.
         *
         * @param WP_User $user     WP_User object.
         * @param string  $password String of user lead capture password.
         *
         * @return WP_Error|WP_User
         * @since 1.0.0
         * @since 1.7.1 Created a separate error for unapproved/unmoderated roles.
         * @since 1.14  Multisite support. When logging in from a different site. Check the users role from the original site they registered. WWLC-286
         * @since 2.0.0 Removed wwlc_unmoderated role check.
         */
        public function wwlc_wholesale_lead_authenticate( $user, $password ) { // phpcs:ignore.

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

            if ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {
                return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Invalid Request', 'woocommerce-wholesale-lead-capture' ) );
            } else {
                return $user;
            }
        }

        /**
         * Redirect wholesale users after successful login accordingly.
         *
         * @param string           $redirect_to The redirect destination URL.
         * @param string           $request     The requested redirect destination URL passed as a parameter.
         * @param WP_User|WP_Error $user        WP_User object if login was successful, WP_Error object otherwise.
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
         * @param string $redirect   Path to redirect to on logout.
         *
         * @since 1.3.3
         * @since 1.6.9  WWLC-175 : delete session after wholesale user logs out.
         * @since 1.6.10 WWLC-177 : added conditions before foreach to check if $all_wholesale_roles is array.
         * @since 1.8.0  Get page option url via wwlc_get_url_of_page_option function.
         *                  Updated the filter, used "logout_url" instead of "wp_logout".
         *                  This fixes the issue wehere WPML will get the english version of logout redirect page instead of the translated one.
         * @return string
         * @access public
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
         * @param null|array  $user_data       Array of user data submited form registration form.
         * @param WWLC_Emails $email_processor WWLC_Emails Object.
         *
         * @return bool|array
         * @since 1.0.0
         * @since 1.6.2 WWLC-117: If WWLC thank you page is not set at the settings, use message and display inline notice above the registration form.
         *                 Used defined( 'DOING_AJAX' ) && DOING_AJAX instead of declaring variable flag for ajax request.
         * @since 1.7.0 Added code to save the set custom role in the shortcode if present.
         *                Add support for WPML plugin.
         * @since 1.7.1 Removed code relating to saving/displaying passwords.
         */
        public function wwlc_create_user( array $user_data = null, WWLC_Emails $email_processor ) {

            global $sitepress, $wp_roles;

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                $user_data = $_POST['user_data']; //phpcs:ignore

                if (
                    get_option( 'wwlc_security_enable_recaptcha' ) === 'yes' &&
                    get_option( 'wwlc_security_recaptcha_type', 'v2_im_not_a_robot' ) === 'v2_im_not_a_robot'
                ) {
                    $recaptcha_secret  = get_option( 'wwlc_security_recaptcha_secret_key' );
                    $recaptcha_request = wp_remote_get( 'https://www.google.com/recaptcha/api/siteverify?secret=' . $recaptcha_secret . '&response=' . $_POST['recaptcha_field'] . '&remoteip=' . $_SERVER['REMOTE_ADDR'] ); //phpcs:ignore

                    if ( is_wp_error( $recaptcha_request ) ) {
                        $recaptcha_check = false;
                    } else {
                        $recaptcha_response = json_decode( wp_remote_retrieve_body( $recaptcha_request ) );
                        $recaptcha_check    = $recaptcha_response->success;
                    }
                } else {
                    $recaptcha_check = true;
                }

                if ( ! isset( $_POST['wwlc_register_user_nonce_field'] ) ||
                    ! wp_verify_nonce( $_POST['wwlc_register_user_nonce_field'], 'wwlc_register_user' ) ||
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
            wc_do_deprecated_action( 'wwlc_action_before_create_user', array( $user_data, $email_processor, $auto_generated_password, $password ), '1.17.4', 'wwlc_action_before_create_wholesale_lead' );
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

            // $result will either be the new user id or a WP_Error object on failure
            $result = wp_create_user( $username, $password, $user_data['user_email'] );

            if ( ! is_wp_error( $result ) ) {

                // Get new user.
                $new_lead = new WP_User( $result );

                // Remove all associated roles.
                $currentRoles = $new_lead->roles;

                foreach ( $currentRoles as $role ) {
                    $new_lead->remove_role( $role );
                }

                // Save registration form fields.
                $this->save_registration_form_fields( $result, $user_data, $auto_generated_password );

                // Save customer billing address.
                $this->wwlc_save_customer_billing_address( $result );

                // Transfer uploaded files from temporary folder to users wholesale folder.
                $this->_move_user_files_to_permanent( $result );

                // Process auto approve new leads.
                $this->process_new_leads( $user_data, $new_lead, $password );

                wc_do_deprecated_action( 'wwlc_action_after_create_user', array( $new_lead ), '1.17.4', 'wwlc_action_after_create_wholesale_lead' );
                do_action( 'wwlc_action_after_create_wholesale_lead', $new_lead );

                $all_roles = $wp_roles->get_names();
                $lead_role = get_option( 'wwlc_general_new_lead_role' );

                if ( isset( $user_data['role'] ) ) {
                    $lead_role = $user_data['role'];
                }

                // Add user meta data.
                if ( isset( $meta_data ) && ! empty( $meta_data ) ) {
                    foreach ( $meta_data as $meta ) {
                        // If meta is a string we use it as key and set a default value of 1.
                        if ( is_string( $meta['value'] ) ) {
                            update_user_meta( $new_lead->ID, $meta, 1 );
                            continue;
                        }

                        $meta = (array) $meta;

                        update_user_meta( $new_lead->ID, $meta['key'], maybe_serialize( $meta['value'] ) );
                    }
                }

                // Display proper message for auto approve.
                if ( get_option( 'wwlc_general_auto_approve_new_leads' ) === 'yes' && isset( $all_roles[ $lead_role ] ) ) {
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

                $response = array(
                    'status'        => 'fail',
                    'error_message' => apply_filters( 'wwlc_inline_notice', $result->get_error_message(), 'fail' ), // append inline notice.
                    'error_obj'     => $result,
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
         * @since 1.15
         * @access public
         *
         * @param int   $user_id                 User ID.
         * @param array $user_data               Registration form data.
         * @param bool  $auto_generated_password Auto generate password.
         */
        public function save_registration_form_fields( $user_id, $user_data, $auto_generated_password ) {

            // Update new user meta.
            if ( ! empty( $user_data ) ) {

                foreach ( $user_data as $key => $val ) {

                    if ( in_array( $key, array( 'user_email', 'wwlc_password', 'wwlc_password_confirm' ), true ) ) {
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
         * @since 1.17.4
         * @access public
         *
         * @param array  $user_data  Registration form data.
         * @param object $new_lead   WP User object.
         * @param string $user_pass  Password text.
         */
        public function process_new_leads( $user_data, $new_lead, $user_pass ) {

            global $sitepress;

            $wc_emails = WC()->mailer()->get_emails();

            // Get WWLC auto approve option.
            $auto_approve = get_option( 'wwlc_general_auto_approve_new_leads' ) === 'yes' ? true : false;

            // Respect WWLC auto approve is disabled via shortcode.
            if ( isset( $user_data['wwlc_auto_approve'] ) ) {
                $auto_approve = 'true' === $user_data['wwlc_auto_approve'] ? true : false;
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

                add_action( 'wwlc_action_auto_approve_user', $new_lead );

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
         * @param int    $user_ID The user ID.
         * @param string $shipping_address The shipping address.
         *
         * @since 2.0.0 - Added this method to save the shipping address.
         */
        public function wwlc_save_customer_shipping_address( $user_ID, $shipping_address ) {

            $same_as_billing = wc_string_to_bool( $shipping_address['same_as_billing'] );

            $first_name = $same_as_billing ? get_user_meta( $user_ID, 'billing_first_name', true ) : $shipping_address['first_name'];
            $last_name  = $same_as_billing ? get_user_meta( $user_ID, 'billing_last_name', true ) : $shipping_address['last_name'];
            $address_1  = $same_as_billing ? get_user_meta( $user_ID, 'billing_address_1', true ) : $shipping_address['address_1'];
            $address_2  = $same_as_billing ? get_user_meta( $user_ID, 'billing_address_2', true ) : $shipping_address['address_2'];
            $city       = $same_as_billing ? get_user_meta( $user_ID, 'billing_city', true ) : $shipping_address['city'];
            $postcode   = $same_as_billing ? get_user_meta( $user_ID, 'billing_postcode', true ) : $shipping_address['postcode'];
            $country    = $same_as_billing ? get_user_meta( $user_ID, 'billing_country', true ) : $shipping_address['country'];
            $state      = $same_as_billing ? get_user_meta( $user_ID, 'billing_state', true ) : $shipping_address['state'];
            $company    = $same_as_billing ? get_user_meta( $user_ID, 'billing_company', true ) : $shipping_address['company'];
            $phone      = $same_as_billing ? get_user_meta( $user_ID, 'billing_phone', true ) : $shipping_address['phone'];
            $email      = $same_as_billing ? get_user_meta( $user_ID, 'billing_email', true ) : $shipping_address['email'];

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
            $cc     = isset( $_POST['cc'] ) ? $_POST['cc'] : ''; //phpcs:ignore
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
                    $new_user_role = $user_data['role'];
                }

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
        }

        /**
         * Set user as rejected.
         *
         * @since 1.0.0
         * @since 1.7.0 Add WPML support.
         * @since 1.17.5 Automatically remove user after rejection.
         * @since 2.0.0 Update logic to remove unused roles.
         *
         * @param array $user_data Array of user data.
         * @return bool
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
         * @since 1.0.0
         * @since 2.0.0 Update logic to remove unused roles.
         *
         * @param array $user_data Array of user data.
         * @return bool
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
         * @since 1.0.0
         * @since 2.0.0 Update logic to remove unused roles.
         *
         * @param array $user_data Array of user data.
         * @return bool
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
         * Remove all roles from a user.
         *
         * @param WP_User $user The user object.
         *
         * @since 2.0.0 Added
         *
         * @return void
         */
        public function _remove_all_roles( &$user ) {
            $roles         = (array) $user->roles;
            $allowed_roles = array(
                WWLC_UNAPPROVED_ROLE,
                WWLC_REJECTED_ROLE,
                'customer',
                get_option( 'wwlc_general_new_lead_role', 'wholesale_customer' ),
            );
            foreach ( $roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    $user->remove_role( $role );
                }
            }
        }

        /**
         * Add customer role to user
         *
         * @param WP_User $user The user object.
         *
         * @since 2.0.0 Added
         *
         * @return void
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
                            <a href="?wwlc_ignore_unapproved_users_notice=0" style="float: right;" id="wwlc_dismiss_unapproved_user_notice"><?php esc_html_e( 'Hide Notice', 'woocommerce-wholesale-lead-capture' ); ?></a>
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

            global $current_user;
            $user_id = $current_user->ID;

            /* If user clicks to ignore the notice, add that to their user meta */
            if ( isset( $_GET['wwlc_ignore_unapproved_users_notice'] ) && '0' == $_GET['wwlc_ignore_unapproved_users_notice'] ) { //phpcs:ignore
                add_user_meta( $user_id, 'wwlc_ignore_unapproved_users_notice', 'true', true );
            }
        }

        /**
         * Hide important notice about properly managing wholesale users.
         *
         * @since 1.3.1
         */
        public function wwlc_hide_important_proper_user_management_notice() {

            global $current_user;
            $user_id = $current_user->ID;

            /* If user clicks to ignore the notice, add that to their user meta */
            if ( isset( $_GET['wwlc_dismiss_important_user_management_notice'] ) && '0' == $_GET['wwlc_dismiss_important_user_management_notice'] ) { //phpcs:ignore
                add_user_meta( $user_id, 'wwlc_dismiss_important_user_management_notice', 'true', true );
            }
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
         * @param int    $userID   The user ID.
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
         * @param int $userID  The user ID.
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
         * @param bool  $send      Whether to send the email.
         * @param array $user      The original user array.
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
         * Sanitize the set custom role to make sure it is only set to allowed roles.
         *
         * @param string $custom_role Custom role to sanitize.
         *
         * @return string Sanitized custom role.
         * @since 1.7.0
         */
        public static function sanitize_custom_role( $custom_role ) {

            if ( ! $custom_role ) {
                return '';
            }

            if ( ! function_exists( 'get_editable_roles' ) ) {
                require_once ABSPATH . '/wp-admin/includes/user.php';
            }

            $available_roles  = array_keys( get_editable_roles() );
            $restricted_roles = apply_filters(
                'wwlc_registration_allowed_roles',
                array(
                    'administrator',
                    'editor',
                    'author',
                    'contributor',
                )
            );

            // if set role is restricted or is not in the list of available roles, then return empty.
            if ( in_array( $custom_role, $restricted_roles, true ) || ! in_array( $custom_role, $available_roles, true ) ) {
                return '';
            }

            return $custom_role;
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
         * @param WP_User $new_lead  User Object Data.
         * @param array   $user_data Registration form field data.
         *
         * @since 1.8.0
         * @access public
         * @return bool
         */
        public function wwlc_auto_login_check( $autologin, $new_lead, $user_data ) {

            if ( isset( $user_data['wwlc_auto_login'] ) ) {
                return 'true' === $user_data['wwlc_auto_login'] ? true : false;
            }

            return $autologin;
        }

        /**
         * Redirects user properly. Triggers when loggin-in via wp-login.php and Wholesale Login Page.
         * Fix the issue when logging-in in a multisite, the user redirected to a blank page. WWLC-286
         *
         * @since 1.14
         * @since 1.17.4 Prevent redirect to shop page if wwlc login redirect setting is note set.
         * @access public
         *
         * @param string  $redirect Redirect URL.
         * @param WP_User $user     WP_User object of the logged-in user.
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
         * @param bool|WP_Error $retval  True if the user should be added to the site, false
         *                               or error object otherwise.
         * @param int           $user_id User ID.
         * @param string        $role    User role.
         * @param int           $blog_id Site ID.
         *
         * @since 1.14
         * @access public
         * @return bool
         */
        public function wwlc_remove_user_from_blog( $retval, $user_id, $role, $blog_id ) { // phpcs:ignore.

            if ( is_multisite() ) {

                $user_data = get_user_by( is_email( $_POST['username'] ) ? 'email' : 'login', $_POST['username'] ); //phpcs:ignore

                if ( $user_data && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
                    return false;
                }
            }

            return $retval;
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
