<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Emails' ) ) {

    /**
     * Model that houses the logic of lead capture emails.
     *
     * @since 1.6.3
     */
    class WWLC_Emails {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_Emails.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Emails
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to WWLC_User_Account.
         *
         * @since 1.8.0
         * @access private
         * @var WWLC_User_Account
         */
        private $wwlc_user_account;

        /**
         * Class Methods
         */

        /**
         * WWLC_Emails constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Emails model.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct( $dependencies ) {
            $this->wwlc_user_account = $dependencies['WWLC_User_Account'];
        }

        /**
         * Ensure that only one instance of WWLC_Emails is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Emails model.
         *
         * @return WWLC_Emails
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get password reset url.
         *
         * @param string $user_login The username of the user.
         *
         * @return bool
         * @since 1.0.0
         * @since 1.7.2 Refactored code so instead of manually creating key, we use get_password_reset_key WP function.
         */
        private function _get_reset_password_url( $user_login ) {
            global $wpdb;

            $user_login = sanitize_text_field( $user_login );

            if ( empty( $user_login ) ) {

                return false;

            } elseif ( strpos( $user_login, '@' ) ) {

                $user_data = get_user_by( 'email', trim( $user_login ) );
                if ( empty( $user_data ) ) {
                    return false;
                }
            } else {

                $login     = trim( $user_login );
                $user_data = get_user_by( 'login', $login );

            }

            $errors = new WP_Error();
            do_action( 'lostpassword_post', $errors, $user_data );

            if ( ! $user_data || ! is_a( $user_data, 'WP_User' ) ) {
                return false;
            }

            // redefining user_login ensures we return the right case in the email.
            $user_login = $user_data->user_login;

            do_action( 'retrieve_password', $user_login );

            $allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

            if ( ! $allow ) {
                return false;
            } elseif ( is_wp_error( $allow ) ) {
                return false;
            }

            // this WP function handles creation and storage of password reset key for the user.
            $key = get_password_reset_key( $user_data );

            return network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' );
        }

        /**
         * Get the WWLC Leads admin page URL used by the {user_management_url}
         * placeholder in admin notification emails.
         *
         * Extracted as a static accessor so callers (and tests) can resolve the
         * URL without going through the full WC_Email rendering pipeline.
         *
         * @return string
         * @since 2.0.5
         */
        public static function get_user_management_url() {
            return get_admin_url( null, 'admin.php?page=wwlc-leads-admin-page#/' );
        }

        /**
         * Get admin email recipients.
         *
         * @param array|string $display Value to display.
         *
         * @return array|string
         * @since 1.3.0
         * @since 1.17.4 add display parameter
         */
        public static function get_admin_email_recipients( $display = 'string' ) {
            $to = trim( get_option( 'wwlc_emails_main_recipient' ) );

            if ( '' !== $to ) {
                $to = array_map( 'trim', explode( ',', $to ) );
                $to = array_filter( $to, 'is_email' );

                if ( 'string' === $display ) {
                    $to = implode( ', ', $to );
                }
            } else {
                $to = get_option( 'admin_email' );

                if ( 'array' === $display ) {
                    $to = array( $to );
                }
            }

            return $to;
        }

        /**
         * Get admin email cc.
         *
         * @param array|string $display Value to display.
         *
         * @return array|string
         * @since 1.3.0
         * @since 1.17.4 add display parameter
         */
        public static function get_admin_email_cc( $display = 'string' ) {
            $cc = trim( get_option( 'wwlc_emails_cc' ) );

            if ( '' !== $cc ) {
                // Strip CRLF characters to prevent email header injection.
                $cc = str_replace( array( "\r", "\n" ), '', $cc );
                $cc = array_map( 'trim', explode( ',', $cc ) );
                $cc = array_filter( $cc, 'is_email' );

                if ( 'string' === $display ) {
                    $cc = implode( ', ', $cc );
                }
            }

            return $cc;
        }

        /**
         * Get admin email bcc.
         *
         * @param array|string $display Value to display.
         *
         * @return array|string
         * @since 1.3.0
         * @since 1.17.4 add display parameter
         */
        public static function get_admin_email_bcc( $display = 'string' ) {
            $bcc = trim( get_option( 'wwlc_emails_bcc' ) );

            if ( '' !== $bcc ) {
                // Strip CRLF characters to prevent email header injection.
                $bcc = str_replace( array( "\r", "\n" ), '', $bcc );
                $bcc = array_map( 'trim', explode( ',', $bcc ) );
                $bcc = array_filter( $bcc, 'is_email' );

                if ( 'string' === $display ) {
                    $bcc = implode( ', ', $bcc );
                }
            }

            return $bcc;
        }

        /**
         * ! Not Implemented
         * Retrieves the attachment.
         *
         * @param int $userID The user ID.
         *
         * @return array
         * @since 1.6.0
         */
        public static function get_custom_field_email_attachments( $userID ) {
            $wwlc_forms         = WWLC_Forms::instance();
            $file_fields        = $wwlc_forms->wwlc_get_file_custom_fields();
            $upload_dir         = wp_upload_dir();
            $user_wholesale_dir = $upload_dir['basedir'] . '/wholesale-customers/' . $userID;
            $attachments        = array();

            if ( ! is_array( $file_fields ) ) {
                return;
            }

            // process attachments.
            foreach ( $file_fields as $field ) {

                $attachments = $user_wholesale_dir . '/' . get_user_meta( $userID, $field['name'], true );
            }

            return $attachments;
        }

        /**
         * Show Approve and Reject action in admin email when 'Allow managing of users via email' option is enabled.
         *
         * @param string  $message Manage user link to be sent to email.
         * @param WP_User $user WP_User object.
         *
         * @return string
         * @since 1.8.0
         * @since 2.0.5 Replaced wp_nonce_url() with a user-context-independent token to fix false
         *              expiry caused by WP nonces being bound to the user session at generation time.
         */
        public function wwlc_allow_managing_of_users_via_email( $message, $user ) {
            $allow = get_option( 'wwlc_email_allow_managing_of_users', false );

            if ( 'yes' === $allow ) {

                $approve_url = add_query_arg(
                    array(
                        'page'        => 'wwlc-leads-admin-page',
                        'user_id'     => $user->ID,
                        'action'      => 'approve_user',
                        '_wwlc_token' => $this->generate_email_action_token( $user->ID, 'approve_user' ),
                    ),
                    admin_url( 'admin.php' )
                );

                $reject_url = add_query_arg(
                    array(
                        'page'        => 'wwlc-leads-admin-page',
                        'user_id'     => $user->ID,
                        'action'      => 'reject_user',
                        '_wwlc_token' => $this->generate_email_action_token( $user->ID, 'reject_user' ),
                    ),
                    admin_url( 'admin.php' )
                );

                $mgt_links_html  = '<p>';
                $mgt_links_html .= '<a target="_blank" href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                $mgt_links_html .= '&nbsp;|&nbsp;';
                $mgt_links_html .= '<a target="_blank" href="' . esc_url( $reject_url ) . '">' . esc_html__( 'Reject', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                $mgt_links_html .= '</p></div>';

                // Find <div id="body_content_inner"></div> and replace the closing </div> with $mgt_links_html.
                $message = preg_replace( '/(<div id="body_content_inner">.*?)<\/div>/s', '$1' . $mgt_links_html, $message );

            }

            return $message;
        }

        /**
         * Handles approval and rejection of users via email.
         *
         * @since 1.8.0
         * @since 2.0.4 Redirect to Leads page instead of Edit User page.
         * @since 2.0.5 Replaced wp_verify_nonce() with verify_email_action_token() to fix links
         *              that were always treated as expired due to WP nonces being user-session-bound.
         *              Added backward compatibility for legacy _wwlc_nonce links.
         */
        public function wwlc_process_approve_reject() {
            $allow = get_option( 'wwlc_email_allow_managing_of_users', false );

            if ( 'yes' !== $allow ) {
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Token/nonce verified below after extracting user_id and action.
            $has_token        = isset( $_GET['action'], $_GET['user_id'], $_GET['_wwlc_token'] );
            $has_legacy_nonce = isset( $_GET['action'], $_GET['user_id'], $_GET['_wwlc_nonce'] );

            if ( ! $has_token && ! $has_legacy_nonce ) {
                return;
            }

            $action  = sanitize_text_field( wp_unslash( $_GET['action'] ) );
            $user_id = absint( $_GET['user_id'] );
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            if ( ! in_array( $action, array( 'approve_user', 'reject_user' ), true ) ) {
                return;
            }

            // Redirects token_expired, already_processed, and success notices to the Leads page with appropriate query parameters for notice display; see wwlc_user_management_notice().
            $token_expired_redirect_url     = $this->get_email_action_redirect_url( $action, 'token_expired' );
            $already_processed_redirect_url = $this->get_email_action_redirect_url( $action, 'already_processed', $user_id );
            $success_redirect_url           = $this->get_email_action_redirect_url( $action, 'success' );

            // Backward compatibility: links generated before 2.0.5 carry a WP nonce (_wwlc_nonce)
            // which is inherently unverifiable — WP nonces are bound to the user session at generation
            // time, and notification emails are sent outside any admin session. Rather than silently
            // dropping the request, redirect to the expired-link notice so the admin receives clear
            // feedback and can act manually from the Leads page.
            if ( ! $has_token ) {
                wp_safe_redirect( $token_expired_redirect_url );
                exit;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Custom token verification used; see verify_email_action_token().
            $token = sanitize_text_field( wp_unslash( $_GET['_wwlc_token'] ) );

            if ( ! $this->verify_email_action_token( $user_id, $action, $token ) ) {
                // A failed token can mean two things: genuine expiry, or a single-use token that
                // was consumed when another admin already processed this lead (either from the
                // Leads page or via a concurrent email link click). Check the user's current role
                // to surface the most accurate and actionable notice to the admin.
                $user = get_userdata( $user_id );

                if ( $user && ! array_intersect( $user->roles, array( WWLC_UNAPPROVED_ROLE, WWLC_REJECTED_ROLE ) ) ) {
                    wp_safe_redirect( $already_processed_redirect_url );
                } else {
                    wp_safe_redirect( $token_expired_redirect_url );
                }

                exit;
            }

            $user = get_userdata( $user_id );

            if ( ! $user ) {
                return;
            }

            // Token is valid but the user no longer holds an actionable role — another admin
            // processed this lead via the Leads page before this email link was clicked.
            if ( ! array_intersect( $user->roles, array( WWLC_UNAPPROVED_ROLE, WWLC_REJECTED_ROLE ) ) ) {
                wp_safe_redirect( $already_processed_redirect_url );
                exit;
            }

            if ( 'approve_user' === $action && $this->wwlc_user_account->wwlc_approve_user( array( 'userID' => $user_id ) ) ) {
                wp_safe_redirect( $success_redirect_url );
                exit;
            }

            if ( 'reject_user' === $action && $this->wwlc_user_account->wwlc_reject_user( array( 'userID' => $user_id ) ) ) {
                wp_safe_redirect( $success_redirect_url );
                exit;
            }
        }

        /**
         * Shows user approval notice on the Leads page after email-based approve/reject.
         *
         * @since 1.8.0
         * @since 2.0.4 Updated to display on Leads page instead of user edit screen.
         * @since 2.0.5 Added error notice for expired nonce and already-processed notice.
         */
        public function wwlc_user_management_notice() {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only; token verified in wwlc_process_approve_reject().
            if ( ! isset( $_GET['action'], $_GET['status'] ) ) {
                return;
            }

            $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
            $status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            // Expired token notice — shown when an email approve/reject link is clicked after the token has expired.
            if ( 'token_expired' === $status ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e( 'The approval link has expired. Please approve or reject the user manually from the Leads page.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                </div>
                <?php
                return;
            }

            // Already-processed notice — shown when the lead was already approved or rejected by another
            // admin before this email link was clicked (e.g., via the Leads page or a concurrent click).
            if ( 'already_processed' === $status ) {
                $user_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $user      = $user_id ? get_userdata( $user_id ) : false;
                $user_name = $user ? esc_html( $user->display_name ) : esc_html__( 'This lead', 'woocommerce-wholesale-lead-capture' );
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php
                        /* translators: %s is the name of the user whose lead was already processed. */
                        printf(
                            '<strong>%s</strong> %s',
                            esc_html( $user_name ),
                            esc_html__( 'has already been processed. Their account status has been updated by another admin.', 'woocommerce-wholesale-lead-capture' )
                        );
                        ?>
                    </p>
                </div>
                <?php
                return;
            }

            if ( 'success' !== $status ) {
                return;
            }

            // Approved Notice.
            if ( 'approve_user' === $action ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Successfully Approved!', 'woocommerce-wholesale-lead-capture' ); ?></p>
                </div>
                <?php
            }

            // Rejected Notice.
            if ( 'reject_user' === $action ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Successfully Rejected!', 'woocommerce-wholesale-lead-capture' ); ?></p>
                </div>
                <?php
            }
        }

        /**
         * Generate the "set your password" link rendered inside the Wholesale
         * Application Received email.
         *
         * Extracted as a static accessor (mirroring {@see self::get_user_management_url()})
         * so tests and other callers can resolve the URL without going through
         * the full WC_Email rendering pipeline.
         *
         * @param int $user_id User ID.
         *
         * @return string Set-password URL, or the bare lost-password URL when the
         *                user can't be resolved.
         * @since 1.13
         * @since 2.0.5 Made public + static; URL now uses WooCommerce's
         *              `action=newaccount&key=&login=` format and bypasses the
         *              WWLC `allow_password_reset` filter for internal key
         *              generation (the filter is intended to block user-initiated
         *              resets only — see #570).
         */
        public static function get_password_reset_link( $user_id ) {
            $user = get_user_by( 'ID', absint( $user_id ) );

            if ( ! is_a( $user, 'WP_User' ) ) {
                return wc_lostpassword_url();
            }

            // Detach WWLC's `allow_password_reset` gate around the internal,
            // server-side key generation. The gate (see WWLC_User_Account::wwlc_block_unapproved_password_reset,
            // introduced for #487) is intended to block user-initiated front-end
            // resets for unapproved/rejected wholesale users — not to block the
            // email helper that ships the brand-new lead a link to set their
            // first password. Leaving it attached here would cause
            // get_password_reset_key() to return WP_Error and the email to fall
            // back to a bare lost-password URL (#570).
            $user_account    = WWLC_User_Account::instance();
            $filter_detached = remove_filter( 'allow_password_reset', array( $user_account, 'wwlc_block_unapproved_password_reset' ), 10 );

            // `try/finally` so the gate is restored even if a third-party callback
            // attached to `retrieve_password_key` (fired inside get_password_reset_key())
            // throws — leaving #487's protection globally detached for the rest of
            // the request would silently re-open the bypass for any later code path.
            try {
                $reset_key = get_password_reset_key( $user );
            } finally {
                if ( $filter_detached ) {
                    add_filter( 'allow_password_reset', array( $user_account, 'wwlc_block_unapproved_password_reset' ), 10, 2 );
                }
            }

            if ( is_wp_error( $reset_key ) ) {
                return wc_lostpassword_url();
            }

            // Match WooCommerce's customer-new-account email URL format
            // (`?action=newaccount&key=&login=`). The `action=newaccount` param
            // is what WC_Query reads to render the "Set password" form title
            // instead of the "Lost password" form.
            return add_query_arg(
                array(
                    'action' => 'newaccount',
                    'key'    => $reset_key,
                    'login'  => rawurlencode( $user->user_login ),
                ),
                wc_lostpassword_url()
            );
        }

        /**
         * Ultimate Member Plugin integration - remove UM hooks that send email to admin and user on registration
         *
         * @since 1.17
         */
        public function wwlc_um_email_conflicts_fix() {
            remove_action( 'um_registration_complete', 'um_send_registration_notification', 10, 2 );
            remove_action( 'um_post_registration_checkmail_hook', 'um_post_registration_checkmail_hook', 10, 2 );
            remove_action( 'um_post_registration_approved_hook', 'um_post_registration_approved_hook', 10, 2 );
        }

        /**
         * Ultimate Member Plugin integration - attached new hook callback function to not send email to admin if new user register using WWLC Registration Form
         *
         * @param int   $user_id User ID.
         * @param array $args Array of actions.
         *
         * @since 1.17
         */
        public function wwlc_um_send_registration_notification_fix( $user_id, $args ) {
            // Do nothing - Don't send email if registration is coming from wwlc registration page
            // If wwlc_create_user in array is not found, UM New Registration Notification will be sent, else do nothing.
            if ( isset( $args['action'] ) && 'wwlc_create_user' === $args['action'] ) {
                return;
            }

            um_fetch_user( $user_id );

            $emails = um_multi_admin_email();
            if ( ! empty( $emails ) ) {
                foreach ( $emails as $email ) {
                    if ( um_user( 'account_status' ) !== 'pending' ) {
                        UM()->mail()->send( $email, 'notification_new_user', array( 'admin' => true ) );
                    } else {
                        UM()->mail()->send( $email, 'notification_review', array( 'admin' => true ) );
                    }
                }
            }
        }

        /**
         * Ultimate Member Plugin integration - ttached new hook callback function to not send activation email link to customer who register for wholesale customer via wwlc.
         * Attached new hook callback function to not send activation email link to customer who register for wholesale customer via wwlc.
         *
         * @param int   $user_id User ID.
         * @param array $args Array of actions.
         *
         * @since 1.17
         */
        public function wwlc_um_post_registration_checkmail_hook_fix( $user_id, $args ) {
            // Do nothing - Dont send email for activation link on registration from wwlc, admin will be the one to approve for wholesale customer.
            // If wwlc_create_user in array is not found, UM will send its activation link to regular customer or non-wholesale customers.
            if ( isset( $args['action'] ) && 'wwlc_create_user' === $args['action'] ) {
                return;
            }

            um_fetch_user( $user_id );
            UM()->user()->email_pending();
        }

        /**
         * Ultimate Member Plugin integration - attached new hook callback function to not send welcome  email link to customer who register for wholesale customer via wwlc.
         *
         * @param int   $user_id User ID.
         * @param array $args Array of actions.
         *
         * @since 1.17
         */
        public function wwlc_um_post_registration_approved_hook_fix( $user_id, $args ) {
            // Do nothing - Don't send email if registration is coming from wwlc registration page
            // If wwlc_create_user in array is not found, UM will send its welcome email to regular customer or non-wholesale customers.
            if ( isset( $args['action'] ) && 'wwlc_create_user' === $args['action'] ) {
                return;
            }

            um_fetch_user( $user_id );
            UM()->user()->approve();
        }

        /**
         * Register WWLC email classes.
         *
         * @param array $email_classes Array of email classes.
         *
         * @return array
         * @since 1.17.4
         * @access public
         */
        public function register_wwlc_emails_classes( $email_classes ) {
            require_once WWLC_INCLUDES_ROOT_DIR . 'emails/class-wwlc-email-new-wholesale-lead.php';
            require_once WWLC_INCLUDES_ROOT_DIR . 'emails/class-wwlc-email-new-wholesale-lead-auto-approved.php';
            require_once WWLC_INCLUDES_ROOT_DIR . 'emails/class-wwlc-email-wholesale-application-received.php';
            require_once WWLC_INCLUDES_ROOT_DIR . 'emails/class-wwlc-email-wholesale-account-approved.php';
            require_once WWLC_INCLUDES_ROOT_DIR . 'emails/class-wwlc-email-wholesale-account-rejected.php';

            $email_classes['WWLC_Email_New_Wholesale_Lead']               = new WWLC_Email_New_Wholesale_Lead();
            $email_classes['WWLC_Email_New_Wholesale_Lead_Auto_Approved'] = new WWLC_Email_New_Wholesale_Lead_Auto_Approved();
            $email_classes['WWLC_Email_Wholesale_Application_Received']   = new WWLC_Email_Wholesale_Application_Received();
            $email_classes['WWLC_Email_Wholesale_Account_Approved']       = new WWLC_Email_Wholesale_Account_Approved();
            $email_classes['WWLC_Email_Wholesale_Account_Rejected']       = new WWLC_Email_Wholesale_Account_Rejected();

            return $email_classes;
        }

        /**
         * Render whysiwyg editor for WWLC email content.
         *
         * @param string      $field_html The markup of the field being generated (initiated as an empty string).
         * @param string      $key The key of the field.
         * @param array       $data The attributes of the field as an associative array.
         * @param WC_Settings $wc_settings The current WC_Settings_API object.
         *
         * @return string
         * @since 1.17.4
         * @access public
         */
        public function render_email_wysiwyg_html( $field_html, $key, $data, $wc_settings ) {
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $wc_settings->get_tooltip_html( $data ); // phpcs:ignore. ?></label>
                </th>
                <td class="forminp forminp-<?php echo sanitize_title( $data['type'] ); //phpcs:ignore ?>">
                    <fieldset>
                        <?php
                        wp_editor(
                            html_entity_decode( $wc_settings->get_option( $key, $data['default'] ) ),
                            $wc_settings->id,
                            array(
								'wpautop'       => false,
								'textarea_name' => 'woocommerce_' . $wc_settings->id . '_' . $key,
								'textarea_rows' => 12,
								'editor_css'    => '<style type="text/css">div#wp-' . $wc_settings->id . '-wrap{width: 600px;}</style>',
							)
                        );
                        ?>
                    </fieldset>
                    <div class="wwlc-email-wysiwyg-desc">
                        <?php echo wp_kses_post( $wc_settings->get_description_html( $data ) ); // WPCS: XSS ok. ?>
                    </div>
                </td>
            </tr>
            <?php

            return ob_get_clean();
        }

        /**
         * Load WWLC emaiil footer.
         *
         * @since 1.17.4
         * @access public
         */
        public function display_wwlc_email_footer() {
            echo wc_get_template_html( //phpcs:ignore
                'emails/woocommerce-wholesale-lead-capture-email-footer.php',
                array(),
                '',
                WWLC_TEMPLATES_ROOT_DIR
            );
        }

        /**
         * Get all custom fields available then convert to WC_Email placeholder.
         *
         * @return array
         * @since 1.17.4
         * @access public
         */
        public static function get_custom_fields_placeholders() {
            $custom_fields              = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            $custom_fields_placeholders = array();

            if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
                foreach ( $custom_fields as $field_id => $field ) {
                    $custom_fields_placeholders[ '{custom_field:' . $field_id . '}' ] = '';
                }
            }

            return $custom_fields_placeholders;
        }

        /**
         * Override wwlc template file check to make sure our custom email templates are found by WC.
         *
         * @param string $core_file Core template file path.
         * @param string $template Template file name.
         * @param string $template_base Template base path.
         * @param string $email_id Email ID.
         *
         * @since 1.17.4
         * @access public
         */
        public function override_wwlc_template_file_path_check( $core_file, $template, $template_base, $email_id ) {
            $wwlc_email_ids = array(
				'wwlc_email_new_wholesale_lead',
				'wwlc_email_new_wholesale_lead_auto_approved',
				'wwlc_email_wholesale_application_received',
				'wwlc_email_wholesale_account_approved',
				'wwlc_email_wholesale_account_rejected',
            );

            if ( in_array( $email_id, $wwlc_email_ids, true ) ) {
                $core_file = WWLC_TEMPLATES_ROOT_DIR . $template;
            }

            return $core_file;
        }

        /**
         * Format email string, replace email content placeholders with appropriate values.
         *
         * @param string   $find_replace String of email content.
         * @param WC_Email $wc_email WC_Email object.
         *
         * @return array
         * @since 1.17.4
         * @since 2.0.5 {user_management_url} now resolves to the WWLC Leads admin page.
         * @access public
         */
        public function wwlc_woocommerce_email_format_string( $find_replace, $wc_email ) {

            $wwlc_email_ids = array(
				'wwlc_email_new_wholesale_lead',
				'wwlc_email_new_wholesale_lead_auto_approved',
				'wwlc_email_wholesale_application_received',
				'wwlc_email_wholesale_account_approved',
				'wwlc_email_wholesale_account_rejected',
            );

            $is_preview = apply_filters( 'woocommerce_is_email_preview', false );

            if ( in_array( $wc_email->id, $wwlc_email_ids, true ) && ! $is_preview ) { // do not render this on preview mode, because preview data objects are not shown properly.
                $find    = array_keys( $wc_email->wwlc_placeholders );
                $replace = array();

                // Extract user data safely from $wc_email->object.
                $user_data = $this->_extract_user_data_from_email_object( $wc_email->object );
                $user_id   = $user_data['ID'];
                $user      = $user_id ? get_user_by( 'ID', $user_id ) : null;

                if ( in_array( '{site_name}', $find, true ) ) {
                    $replace[ array_search( '{site_name}', $find, true ) ] = get_bloginfo( 'name' );
                }

                if ( in_array( '{user_management_url}', $find, true ) ) {
                    $replace[ array_search( '{user_management_url}', $find, true ) ] = self::get_user_management_url();
                }

                if ( in_array( '{user_edit_profile_url}', $find, true ) ) {
                    $replace[ array_search( '{user_edit_profile_url}', $find, true ) ] = $user ? admin_url( 'user-edit.php?user_id=' . $user->ID ) : '';
                }

                if ( in_array( '{user_role}', $find, true ) ) {
                    $replace[ array_search( '{user_role}', $find, true ) ] = $user ? wwlc_get_user_role( $user->ID ) : '';
                }

                // Generate {user_wholesale_role} template tag.
                if ( in_array( '{user_wholesale_role}', $find, true ) ) {
                    if ( class_exists( 'WWP_Wholesale_Roles' ) && $user ) {

                        $user_wholesale_role = '';
                        $wwp_wholesale_role  = WWP_Wholesale_Roles::getInstance();
                        $wholesale_roles     = $wwp_wholesale_role->getAllRegisteredWholesaleRoles();

                        // Check wholesale role name.
                        foreach ( $user->roles as $role ) {
                            if ( isset( $wholesale_roles[ $role ] ) ) {
                                $user_wholesale_role = $wholesale_roles[ $role ]['roleName'];
                            }
                        }

                        $replace[ array_search( '{user_wholesale_role}', $find, true ) ] = '' !== $user_wholesale_role ? $user_wholesale_role : '';
                    }
                }

                if ( in_array( '{wholesale_login_url}', $find, true ) ) {
                    $replace[ array_search( '{wholesale_login_url}', $find, true ) ] = wwlc_get_url_of_page_option( 'wwlc_general_login_page' );
                }

                if ( in_array( '{reset_password_url}', $find, true ) ) {
                    $replace[ array_search( '{reset_password_url}', $find, true ) ] = $user ? $this->_get_reset_password_url( $user->user_login ) : '';
                }

                if ( in_array( '{full_name}', $find, true ) ) {
                    $replace[ array_search( '{full_name}', $find, true ) ] = $user ? esc_html( $user->first_name . ' ' . $user->last_name ) : '';
                }

                if ( in_array( '{first_name}', $find, true ) ) {
                    $replace[ array_search( '{first_name}', $find, true ) ] = $user ? esc_html( $user->first_name ) : '';
                }

                if ( in_array( '{last_name}', $find, true ) ) {
                    $replace[ array_search( '{last_name}', $find, true ) ] = $user ? esc_html( $user->last_name ) : '';
                }

                if ( in_array( '{username}', $find, true ) ) {
                    $replace[ array_search( '{username}', $find, true ) ] = $user ? esc_html( $user->user_login ) : '';
                }

                /**
                 * Generate {password} placeholder depending on the email sent.
                 * Only send plain password to user only on wholesale lead registration.
                 */
                if ( in_array( '{password}', $find, true ) ) {
                    $replace[ array_search( '{password}', $find, true ) ] = $user ? $this->_get_password_placeholder_value( $user ) : '';
                }

                if ( in_array( '{email}', $find, true ) ) {
                    $replace[ array_search( '{email}', $find, true ) ] = $user ? esc_html( $user->user_email ) : '';
                }

                if ( in_array( '{phone}', $find, true ) ) {
                    $replace[ array_search( '{phone}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_phone', true ) ) : '';
                }

                if ( in_array( '{company_name}', $find, true ) ) {
                    $replace[ array_search( '{company_name}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_company_name', true ) ) : '';
                }

                if ( in_array( '{address}', $find, true ) ) {
                    $address_1 = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_address', true ) ) : '';
                    $address_2 = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_address_2', true ) ) : '';
                    $city      = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_city', true ) ) : '';
                    $state     = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_state', true ) ) : '';
                    $postcode  = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_postcode', true ) ) : '';
                    $country   = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_country', true ) ) : '';

                    $replace[ array_search( '{address}', $find, true ) ] = sprintf(
                        '%1$s%2$s%3$s%4$s%5$s%6$s',
                        $address_1,
                        $address_2 ? '<br/>' . $address_2 : '',
                        $city ? '<br/>' . $city : '',
                        $state ? '<br/>' . $state : '',
                        $postcode ? '<br/>' . $postcode : '',
                        $country ? '<br/>' . $country : '',
                    );
                }

                if ( in_array( '{address_1}', $find, true ) ) {
                    $replace[ array_search( '{address_1}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_address', true ) ) : '';
                }

                if ( in_array( '{address_2}', $find, true ) ) {
                    $replace[ array_search( '{address_2}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_address_2', true ) ) : '';
                }

                if ( in_array( '{city}', $find, true ) ) {
                    $replace[ array_search( '{city}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_city', true ) ) : '';
                }

                if ( in_array( '{state}', $find, true ) ) {
                    $replace[ array_search( '{state}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_state', true ) ) : '';
                }

                if ( in_array( '{postcode}', $find, true ) ) {
                    $replace[ array_search( '{postcode}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_postcode', true ) ) : '';
                }

                if ( in_array( '{country}', $find, true ) ) {
                    $replace[ array_search( '{country}', $find, true ) ] = $user ? esc_html( get_user_meta( $user->ID, 'wwlc_country', true ) ) : '';
                }

                // Custom fields.
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( is_array( $custom_fields ) && ! empty( $custom_fields ) && $user ) {
                    foreach ( $custom_fields as $cf_key => $cf_data ) {
                        if ( in_array( '{custom_field:' . $cf_key . '}', $find, true ) ) {

                            $cf_value = get_user_meta( $user->ID, $cf_key, true );

                            if ( 'checkbox' === $cf_data['field_type'] && is_array( $cf_value ) ) {

                                // get text value of checkbox options.
                                $cf_value = array_map(
                                    function ( $option ) use ( $cf_data ) {
                                        return WWLC_Helper_Functions::get_custom_field_option_text( $option, $cf_data );
                                    },
                                    $cf_value
                                );

                                $cf_value = implode( ', ', $cf_value );
                            } elseif ( in_array( $cf_data['field_type'], array( 'radio', 'select' ), true ) ) {
                                $cf_value = WWLC_Helper_Functions::get_custom_field_option_text( $cf_value, $cf_data );
                            } elseif ( 'terms_conditions' === $cf_data['field_type'] && is_array( $cf_value ) ) {
                                $cf_value = $cf_value[0] ?? true;
                            }

                            $replace[ array_search( '{custom_field:' . $cf_key . '}', $find, true ) ] = apply_filters( 'wwlc_email_format_string_custom_field_value', $cf_value, $cf_key, $cf_data, $wc_email );
                        }
                    }
                }

                return apply_filters( 'wwlc_email_format_string', str_replace( $find, $replace, $find_replace ), $wc_email );
            }

            return $find_replace;
        }

        /**
         * Display correct text in woocommerce email footer.
         *
         * @param string $footer_text Footer text.
         *
         * @return string
         * @since 1.17.5
         * @access public
         */
        public function display_correct_wc_email_footer_text( $footer_text ) {

            $footer_text = apply_filters( 'woocommerce_email_footer_text', $footer_text );

            return $footer_text;
        }

        /**
         * Extract user data safely from email object.
         *
         * @param mixed $email_object Email object that may be WP_User, stdClass, or array.
         *
         * @return array Array with 'ID' key containing user ID or null.
         * @since 1.17.4
         * @access private
         */
        private function _extract_user_data_from_email_object( $email_object ) {
            $user_data = array( 'ID' => null );

            if ( ! $email_object ) {
                return $user_data;
            }

            // Handle WP_User object.
            if ( $email_object instanceof WP_User ) {
                $user_data['ID'] = $email_object->ID;

                return $user_data;
            }

            // Handle objects with data property.
            if ( is_object( $email_object ) && isset( $email_object->data ) ) {
                if ( is_object( $email_object->data ) && isset( $email_object->data->ID ) ) {
                    $user_data['ID'] = $email_object->data->ID;

                    return $user_data;
                } elseif ( is_array( $email_object->data ) && isset( $email_object->data['ID'] ) ) {
                    $user_data['ID'] = $email_object->data['ID'];

                    return $user_data;
                }
                // Don't return here - fall through to check other formats.
            }

            // Handle direct object properties.
            if ( is_object( $email_object ) && isset( $email_object->ID ) ) {
                $user_data['ID'] = $email_object->ID;

                return $user_data;
            }

            // Handle array format.
            if ( is_array( $email_object ) ) {
                if ( isset( $email_object['ID'] ) ) {
                    $user_data['ID'] = $email_object['ID'];
                } elseif ( isset( $email_object['data'] ) && is_array( $email_object['data'] ) && isset( $email_object['data']['ID'] ) ) {
                    $user_data['ID'] = $email_object['data']['ID'];
                }
            }

            return $user_data;
        }

        /**
         * Get password placeholder value based on user context.
         *
         * @param WP_User $user User object.
         *
         * @return string Password placeholder value.
         * @since 1.17.4
         * @access private
         */
        private function _get_password_placeholder_value( $user ) {
            global $wpdb;

            $user_capability         = maybe_unserialize( get_user_meta( $user->ID, $wpdb->get_blog_prefix() . 'capabilities', true ) );
            $user_capability         = is_array( $user_capability ) ? $user_capability : array();
            $auto_generated_password = get_user_meta( $user->ID, 'wwlc_auto_generated_password', true );

            // On upgrade Account.
            if ( get_user_meta( $user->ID, 'wwlc_request_upgrade', true ) ) {
                return __( '[your current password]', 'woocommerce-wholesale-lead-capture' );
            }

            // On wholesale lead registration, send a password reset link instead of the plain password.
            if ( isset( $user_capability['wwlc_unapproved'] ) && true === $user_capability['wwlc_unapproved'] ) {
                $reset_link = self::get_password_reset_link( $user->ID );

                return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $reset_link ), esc_html__( 'Click here to set your password', 'woocommerce-wholesale-lead-capture' ) );
            }

            // On wholesale lead registration, if password is auto generated.
            if ( ! empty( $auto_generated_password ) && get_option( 'wwlc_general_auto_approve_new_leads' ) === 'yes' ) {
                $reset_link = self::get_password_reset_link( $user->ID );

                return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $reset_link ), esc_html__( 'Click here to set your password', 'woocommerce-wholesale-lead-capture' ) );
            }

            if ( ! empty( $auto_generated_password ) ) {
                return __( '[the password was supplied in your registration email]', 'woocommerce-wholesale-lead-capture' );
            }

            return __( '[the password supplied upon registration]', 'woocommerce-wholesale-lead-capture' );
        }

        /**
         * Generate a secure, time-limited action token for email-based user management.
         *
         * WordPress nonces are bound to the logged-in user session at generation time.
         * Admin notification emails are generated server-side — typically during WooCommerce
         * action hooks fired by the registering customer — meaning no admin session is active.
         * This causes wp_verify_nonce() to always fail when any logged-in admin later clicks
         * the link in the email.
         *
         * This method issues a user-context-independent, cryptographically secure token stored
         * as a transient. Any authenticated admin with manage_woocommerce capability can redeem
         * the token within the configured validity window, regardless of which PHP session was
         * active when the email was sent.
         *
         * @param int    $user_id The ID of the lead user being managed.
         * @param string $action  The action being authorized ('approve_user' or 'reject_user').
         *
         * @return string Hex-encoded 32-byte cryptographically secure random token.
         * @since 2.0.5
         * @access private
         */
        private function generate_email_action_token( int $user_id, string $action ): string {
            $token     = bin2hex( random_bytes( 32 ) );
            $transient = 'wwlc_email_token_' . $user_id . '_' . $action;

            /**
             * Filters the validity period (in seconds) for email-based approve/reject action tokens.
             *
             * Defaults to 7 days. Increase this value if admins are unlikely to process new leads
             * within a week; decrease it if your security policy requires a shorter window.
             *
             * @since 2.0.5
             *
             * @param int    $expiry  Token lifetime in seconds. Default: 7 * DAY_IN_SECONDS.
             * @param int    $user_id The lead user ID.
             * @param string $action  The action ('approve_user' or 'reject_user').
             */
            $expiry = (int) apply_filters( 'wwlc_email_action_token_expiry', 7 * DAY_IN_SECONDS, $user_id, $action );

            set_transient( $transient, $token, $expiry );

            return $token;
        }

        /**
         * Verify and consume a secure action token for email-based user management.
         *
         * Tokens are single-use: on successful verification the transient is immediately
         * deleted to prevent replay attacks. Returns false for expired, missing, or
         * mismatched tokens. Uses hash_equals() for constant-time comparison to prevent
         * timing side-channel attacks.
         *
         * @param int    $user_id The ID of the lead user being managed.
         * @param string $action  The action being authorized ('approve_user' or 'reject_user').
         * @param string $token   The token value submitted via the request URL.
         *
         * @return bool True if the token is valid and unused, false otherwise.
         * @since 2.0.5
         * @access private
         */
        private function verify_email_action_token( int $user_id, string $action, string $token ): bool {
            $transient    = 'wwlc_email_token_' . $user_id . '_' . $action;
            $stored_token = get_transient( $transient );

            if ( false === $stored_token || ! hash_equals( (string) $stored_token, $token ) ) {
                return false;
            }

            // Single-use enforcement: delete immediately after successful verification
            // to prevent the same link from being used more than once.
            delete_transient( $transient );

            return true;
        }

        /**
         * Generate the redirect URL for email action links with appropriate query parameters.
         *
         * @param string $action  The action being performed ('approve_user' or 'reject_user').
         * @param string $status  The status of the action result ('success', 'nonce_expired', 'already_processed').
         * @param int    $user_id Optional user ID to include in the URL for more specific notices.
         *
         * @return string The generated URL with query parameters.
         * @since 2.0.4.1
         * @access private
         */
        private function get_email_action_redirect_url( string $action, string $status, int $user_id = 0 ): string {
            $args = array(
                'page'   => 'wwlc-leads-admin-page',
                'action' => $action,
                'status' => $status,
            );
            if ( $user_id ) {
                $args['user_id'] = $user_id;
            }
            return add_query_arg( $args, admin_url( 'admin.php' ) );
        }

        /**
         * Execute model.
         *
         * @since 1.8.0
         * @access public
         */
        public function run() {
            add_filter( 'woocommerce_email_classes', array( $this, 'register_wwlc_emails_classes' ), 10, 1 );
            add_filter(
                'wwlc_email_content_html_wwlc_email_new_wholesale_lead',
                array(
					$this,
					'wwlc_allow_managing_of_users_via_email',
                ),
                10,
                2
            );
            add_action( 'admin_init', array( $this, 'wwlc_process_approve_reject' ) );
            add_action( 'admin_notices', array( $this, 'wwlc_user_management_notice' ) );
            add_filter(
                'woocommerce_generate_wwlc_email_wysiwyg_html',
                array(
					$this,
					'render_email_wysiwyg_html',
                ),
                10,
                4
            );
            add_action( 'wwlc_email_footer', array( $this, 'display_wwlc_email_footer' ), 10, 1 );
            add_filter(
                'woocommerce_locate_core_template',
                array(
					$this,
					'override_wwlc_template_file_path_check',
                ),
                10,
                4
            );
            add_filter(
                'woocommerce_email_format_string',
                array(
					$this,
					'wwlc_woocommerce_email_format_string',
                ),
                10,
                2
            );
            add_filter( 'wwlc_email_footer_text', array( $this, 'display_correct_wc_email_footer_text' ) );

            // Ultimate Member Plugin integration - registration email conflicts with WWLC wholesale registration.
            if ( is_plugin_active( 'ultimate-member/ultimate-member.php' ) ) {
                add_action( 'init', array( $this, 'wwlc_um_email_conflicts_fix' ) );
                add_action(
                    'um_registration_complete',
                    array(
						$this,
						'wwlc_um_send_registration_notification_fix',
                    ),
                    10,
                    2
                );
                add_action(
                    'um_post_registration_checkmail_hook',
                    array(
						$this,
						'wwlc_um_post_registration_checkmail_hook_fix',
                    ),
                    10,
                    2
                );
                add_action(
                    'um_post_registration_approved_hook',
                    array(
						$this,
						'wwlc_um_post_registration_approved_hook_fix',
                    ),
                    10,
                    2
                );
            }
        }
    }
}
