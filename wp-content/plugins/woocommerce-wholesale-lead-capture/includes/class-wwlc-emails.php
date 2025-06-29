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
         * @since 1.0.0
         * @since 1.7.2 Refactored code so instead of manually creating key, we use get_password_reset_key WP function.
         *
         * @param string $user_login The username of the user.
         * @return bool
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
         * Get admin email recipients.
         *
         * @since 1.3.0
         * @since 1.17.4 add display parameter
         *
         * @param array|string $display Value to display.
         * @return array|string
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
         * @since 1.3.0
         * @since 1.17.4 add display parameter
         *
         * @param array|string $display Value to display.
         * @return array|string
         */
        public static function get_admin_email_cc( $display = 'string' ) {
            $cc = trim( get_option( 'wwlc_emails_cc' ) );

            if ( '' !== $cc ) {
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
         * @since 1.3.0
         * @since 1.17.4 add display parameter
         *
         * @param array|string $display Value to display.
         * @return array|string
         */
        public static function get_admin_email_bcc( $display = 'string' ) {
            $bcc = trim( get_option( 'wwlc_emails_bcc' ) );

            if ( '' !== $bcc ) {
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
         * @since 1.6.0
         *
         * @param int $userID The user ID.
         * @return array
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
         * @since 1.8.0
         *
         * @param string  $message  Manage user link to be sent to email.
         * @param WP_User $user     WP_User object.
         * @return string
         */
        public function wwlc_allow_managing_of_users_via_email( $message, $user ) {
            $allow = get_option( 'wwlc_email_allow_managing_of_users', false );

            if ( 'yes' === $allow ) {

                $mgt_links_html  = '<p>';
                $mgt_links_html .= '<a target="_blank" href="' . admin_url( 'user-edit.php?user_id=' . $user->ID ) . '&action=approve_user">Approve</a>';
                $mgt_links_html .= '&nbsp;|&nbsp;';
                $mgt_links_html .= '<a target="_blank" href="' . admin_url( 'user-edit.php?user_id=' . $user->ID ) . '&action=reject_user">Reject</a>';
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
         */
        public function wwlc_process_approve_reject() {
            // phpcs:disable WordPress.Security.NonceVerification
            $allow              = get_option( 'wwlc_email_allow_managing_of_users', false );
            $screen             = get_current_screen();
            $current_user       = wp_get_current_user();
            $current_user_roles = $current_user->roles;

            if ( 'yes' === $allow && 'user-edit' === $screen->id && in_array( 'administrator', $current_user_roles, true ) && isset( $_GET['action'] ) && isset( $_GET['user_id'] ) ) {

                $action  = $_GET['action'];
                $user_id = $_GET['user_id'];
                $user    = get_userdata( $_GET['user_id'] );
                $roles   = $user->roles;

                if ( $user && array_intersect( $roles, array( WWLC_UNAPPROVED_ROLE, WWLC_REJECTED_ROLE ) ) ) {

                    if ( 'approve_user' === $action ) {

                        if ( $this->wwlc_user_account->wwlc_approve_user( array( 'userID' => $user_id ) ) ) {
                            wp_safe_redirect( wwlc_get_current_url() . '&status=success' );
                            exit;
                        }
                    } elseif ( 'reject_user' === $action ) {

                        if ( $this->wwlc_user_account->wwlc_reject_user( array( 'userID' => $user_id ) ) ) {
                            wp_safe_redirect( wwlc_get_current_url() . '&status=success' );
                            exit;
                        }
                    }
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification
        }

        /**
         * Shows user approval notice in user edit screen.
         *
         * @since 1.8.0
         */
        public function wwlc_user_management_notice() {
            // phpcs:disable WordPress.Security.NonceVerification
            if ( isset( $_GET['action'] ) && isset( $_GET['status'] ) ) {

                // Approved Notice.
                if ( 'approve_user' === $_GET['action'] && 'success' === $_GET['status'] ) {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Successfully Approved!', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    </div>
                    <?php

                }

                // Rejected Notice.
                if ( 'reject_user' === $_GET['action'] && 'success' === $_GET['status'] ) {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Successfully Rejected!', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    </div>
                    <?php

                }
            }
            // phpcs:enable WordPress.Security.NonceVerification
        }

        /**
         * Generate user reset password link.
         *
         * @since 1.13
         * @access private
         *
         * @param int $user_id User ID.
         * @return string User reset password link.
         */
        private function _generate_user_reset_password_link( $user_id ) {
            $user = get_user_by( 'ID', absint( $user_id ) );

            if ( ! is_a( $user, 'WP_User' ) ) {
                return wc_lostpassword_url();
            }

            $reset_key = get_password_reset_key( $user );
            $username  = rawurlencode( $user->user_login );

            return wc_lostpassword_url() . "?key=$reset_key&login=$username";
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
         * @since 1.17
         *
         * @param int   $user_id User ID.
         * @param array $args    Array of actions.
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
         * @since 1.17
         *
         * @param int   $user_id User ID.
         * @param array $args    Array of actions.
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
         * @since 1.17
         *
         * @param int   $user_id User ID.
         * @param array $args    Array of actions.
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
         * @since 1.17.4
         * @access public
         *
         * @param array $email_classes Array of email classes.
         *
         * @return array
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
         * @since 1.17.4
         * @access public
         *
         * @param string      $field_html  The markup of the field being generated (initiated as an empty string).
         * @param string      $key         The key of the field.
         * @param array       $data        The attributes of the field as an associative array.
         * @param WC_Settings $wc_settings The current WC_Settings_API object.
         *
         * @return string
         */
        public function render_email_wysiwyg_html( $field_html, $key, $data, $wc_settings ) {
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $wc_settings->get_tooltip_html( $data ) ; // phpcs:ignore. ?></label>
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
         * @since 1.17.4
         * @access public
         *
         * @return array
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
         * @since 1.17.4
         * @access public
         *
         * @param string $core_file     Core template file path.
         * @param string $template      Template file name.
         * @param string $template_base Template base path.
         * @param string $email_id      Email ID.
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
         * @since 1.17.4
         * @access public
         *
         * @param string   $find_replace String of email content.
         * @param WC_Email $wc_email     WC_Email object.
         *
         * @return array
         */
        public function wwlc_woocommerce_email_format_string( $find_replace, $wc_email ) {

            $wwlc_email_ids = array(
                'wwlc_email_new_wholesale_lead',
                'wwlc_email_new_wholesale_lead_auto_approved',
                'wwlc_email_wholesale_application_received',
                'wwlc_email_wholesale_account_approved',
                'wwlc_email_wholesale_account_rejected',
            );

            if ( in_array( $wc_email->id, $wwlc_email_ids, true ) ) {
                $find    = array_keys( $wc_email->wwlc_placeholders );
                $replace = array();

                if ( in_array( '{site_name}', $find, true ) ) {
                    $replace[ array_search( '{site_name}', $find, true ) ] = get_bloginfo( 'name' );
                }

                if ( in_array( '{user_management_url}', $find, true ) ) {
                    $replace[ array_search( '{user_management_url}', $find, true ) ] = get_admin_url( null, 'users.php' );
                }

                if ( in_array( '{user_edit_profile_url}', $find, true ) ) {
                    $replace[ array_search( '{user_edit_profile_url}', $find, true ) ] = admin_url( 'user-edit.php?user_id=' . $wc_email->object->data->ID );
                }

                if ( in_array( '{user_role}', $find, true ) ) {
                    $replace[ array_search( '{user_role}', $find, true ) ] = wwlc_get_user_role( $wc_email->object->data->ID );
                }

                // Generate {user_wholesale_role} template tag.
                if ( in_array( '{user_wholesale_role}', $find, true ) ) {
                    if ( class_exists( 'WWP_Wholesale_Roles' ) ) {

                        $user_wholesale_role = '';
                        $wwp_wholesale_role  = WWP_Wholesale_Roles::getInstance();
                        $wholesale_roles     = $wwp_wholesale_role->getAllRegisteredWholesaleRoles();

                        // Check wholesale role name.
                        foreach ( $wc_email->object->roles as $role ) {
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
                    $replace[ array_search( '{reset_password_url}', $find, true ) ] = $this->_get_reset_password_url( $wc_email->object->data->user_login );
                }

                if ( in_array( '{full_name}', $find, true ) ) {
                    $replace[ array_search( '{full_name}', $find, true ) ] = $wc_email->object->first_name . ' ' . $wc_email->object->last_name;
                }

                if ( in_array( '{first_name}', $find, true ) ) {
                    $replace[ array_search( '{first_name}', $find, true ) ] = $wc_email->object->first_name;
                }

                if ( in_array( '{last_name}', $find, true ) ) {
                    $replace[ array_search( '{last_name}', $find, true ) ] = $wc_email->object->last_name;
                }

                if ( in_array( '{username}', $find, true ) ) {
                    $replace[ array_search( '{username}', $find, true ) ] = $wc_email->object->data->user_login;
                }

                /**
                 * Generate {password} placeholder depending on the email sent.
                 * Only send plain password to user only on wholesale lead registration.
                 */
                if ( in_array( '{password}', $find, true ) ) {

                    global $wpdb;
                    $user_capability         = maybe_unserialize( get_user_meta( $wc_email->object->data->ID, $wpdb->get_blog_prefix() . 'capabilities', true ) );
                    $auto_generated_password = get_user_meta( $wc_email->object->data->ID, 'wwlc_auto_generated_password', true );

                    // On upgrade Account.
                    if ( get_user_meta( $wc_email->object->data->ID, 'wwlc_request_upgrade', true ) ) {
                        $replace[ array_search( '{password}', $find, true ) ] = __( '[your current password]', 'woocommerce-wholesale-lead-capture' );
                    } else { // phpcs:ignore
                        // On wholesale lead registration, send plain password to email content.
                        if ( isset( $user_capability ) && ( isset( $user_capability['wwlc_unapproved'] ) && true === $user_capability['wwlc_unapproved'] ) ) {
                            $replace[ array_search( '{password}', $find, true ) ] = $wc_email->user_password ?? '';
                        } elseif ( ! empty( $auto_generated_password ) && get_option( 'wwlc_general_auto_approve_new_leads' ) === 'yes' ) { // On wholesale lead registration, if password is auto generated.
                            $reset_link = $this->_generate_user_reset_password_link( $wc_email->object->data->ID );
                            $replace[ array_search( '{password}', $find, true ) ] = sprintf( '<a href="%1$s">%2$s</a>', $reset_link, __( 'Click here to set your password', 'woocommerce-wholesale-lead-capture' ) );
                        } elseif ( ! empty( $auto_generated_password ) ) {
                            $replace[ array_search( '{password}', $find, true ) ] = __( '[the password was supplied in your registration email]', 'woocommerce-wholesale-lead-capture' );
                        } else {
                            $replace[ array_search( '{password}', $find, true ) ] = __( '[the password supplied upon registration]', 'woocommerce-wholesale-lead-capture' );
                        }
                    }
                }

                if ( in_array( '{email}', $find, true ) ) {
                    $replace[ array_search( '{email}', $find, true ) ] = $wc_email->object->data->user_email;
                }

                if ( in_array( '{phone}', $find, true ) ) {
                    $replace[ array_search( '{phone}', $find, true ) ] = $wc_email->object->wwlc_phone;
                }

                if ( in_array( '{company_name}', $find, true ) ) {
                    $replace[ array_search( '{company_name}', $find, true ) ] = $wc_email->object->wwlc_company_name;
                }

                if ( in_array( '{address}', $find, true ) ) {
                    $replace[ array_search( '{address}', $find, true ) ] = sprintf(
                        '%1$s%2$s%3$s%4$s%5$s%6$s',
                        $wc_email->object->wwlc_address,
                        $wc_email->object->wwlc_address_2 ? '<br/>' . $wc_email->object->wwlc_address_2 : '',
                        $wc_email->object->wwlc_city ? '<br/>' . $wc_email->object->wwlc_city : '',
                        $wc_email->object->wwlc_state ? '<br/>' . $wc_email->object->wwlc_state : '',
                        $wc_email->object->wwlc_postcode ? '<br/>' . $wc_email->object->wwlc_postcode : '',
                        $wc_email->object->wwlc_country ? '<br/>' . $wc_email->object->wwlc_country : '',
                    );
                }

                if ( in_array( '{address_1}', $find, true ) ) {
                    $replace[ array_search( '{address_1}', $find, true ) ] = $wc_email->object->wwlc_address;
                }

                if ( in_array( '{address_2}', $find, true ) ) {
                    $replace[ array_search( '{address_2}', $find, true ) ] = $wc_email->object->wwlc_address_2;
                }

                if ( in_array( '{city}', $find, true ) ) {
                    $replace[ array_search( '{city}', $find, true ) ] = $wc_email->object->wwlc_city;
                }

                if ( in_array( '{state}', $find, true ) ) {
                    $replace[ array_search( '{state}', $find, true ) ] = $wc_email->object->wwlc_state;
                }

                if ( in_array( '{postcode}', $find, true ) ) {
                    $replace[ array_search( '{postcode}', $find, true ) ] = $wc_email->object->wwlc_postcode;
                }

                if ( in_array( '{country}', $find, true ) ) {
                    $replace[ array_search( '{country}', $find, true ) ] = $wc_email->object->wwlc_country;
                }

                // Custom fields.
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
                    foreach ( $custom_fields as $cf_key => $cf_data ) {
                        if ( in_array( '{custom_field:' . $cf_key . '}', $find, true ) ) {

                            $cf_value = $wc_email->object->{$cf_key};

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
         * @since 1.17.5
         * @access public
         *
         * @param string $footer_text Footer text.
         *
         * @return string
         */
        public function display_correct_wc_email_footer_text( $footer_text ) {

            $footer_text = apply_filters( 'woocommerce_email_footer_text', $footer_text );

            return $footer_text;
        }

        /**
         * Execute model.
         *
         * @since 1.8.0
         * @access public
         */
        public function run() {
            add_filter( 'woocommerce_email_classes', array( $this, 'register_wwlc_emails_classes' ), 10, 1 );
            add_filter( 'wwlc_email_content_html_wwlc_email_new_wholesale_lead', array( $this, 'wwlc_allow_managing_of_users_via_email' ), 10, 2 );
            add_action( 'admin_head', array( $this, 'wwlc_process_approve_reject' ) );
            add_action( 'admin_notices', array( $this, 'wwlc_user_management_notice' ) );
            add_filter( 'woocommerce_generate_wwlc_email_wysiwyg_html', array( $this, 'render_email_wysiwyg_html' ), 10, 4 );
            add_action( 'wwlc_email_footer', array( $this, 'display_wwlc_email_footer' ), 10, 1 );
            add_filter( 'woocommerce_locate_core_template', array( $this, 'override_wwlc_template_file_path_check' ), 10, 4 );
            add_filter( 'woocommerce_email_format_string', array( $this, 'wwlc_woocommerce_email_format_string' ), 10, 2 );
            add_filter( 'wwlc_email_footer_text', array( $this, 'display_correct_wc_email_footer_text' ) );

            // Ultimate Member Plugin integration - registration email conflicts with WWLC wholesale registration.
            if ( is_plugin_active( 'ultimate-member/ultimate-member.php' ) ) {
                add_action( 'init', array( $this, 'wwlc_um_email_conflicts_fix' ) );
                add_action( 'um_registration_complete', array( $this, 'wwlc_um_send_registration_notification_fix' ), 10, 2 );
                add_action( 'um_post_registration_checkmail_hook', array( $this, 'wwlc_um_post_registration_checkmail_hook_fix' ), 10, 2 );
                add_action( 'um_post_registration_approved_hook', array( $this, 'wwlc_um_post_registration_approved_hook_fix' ), 10, 2 );
            }
        }
    }
}
