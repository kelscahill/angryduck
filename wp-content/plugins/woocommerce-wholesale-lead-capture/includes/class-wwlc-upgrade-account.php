<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Upgrade_Account' ) ) {

    /**
     * Class WWLC_Upgrade_Account
     *
     * @since 1.15
     */
    class WWLC_Upgrade_Account {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_Upgrade_Account.
         *
         * @since 1.15
         * @access private
         * @var WWLC_Upgrade_Account
         */
        private static $_instance;

        /**
         * Get instance of WWLC_User_Account class
         *
         * @since 1.15
         * @access private
         * @var WWLC_User_Account
         */
        private $_wwlc_user_account;

        /**
         * Class Methods
         */

        /**
         * WWLC_Upgrade_Account constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Upgrade_Account model.
         *
         * @access public
         * @since 1.15
         */
        public function __construct( $dependencies ) {

            $this->_wwlc_user_account = $dependencies['WWLC_User_Account'];
        }

        /**
         * Ensure that only one instance of WWLC_Upgrade_Account is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Upgrade_Account model.
         *
         * @return WWLC_Upgrade_Account
         * @since 1.15
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Display account upgrade notice.
         *
         * @since 1.15
         * @access public
         */
        public function display_account_upgrade_notice() {

            global $wp_roles, $sitepress;

            $all_user_roles      = $wp_roles->get_names();
            $user                = wp_get_current_user();
            $user_roles          = is_user_logged_in() ? $user->roles : array();
            $show_upgrade_notice = apply_filters( 'wwlc_upgrade_account_notice', get_option( 'wwlc_show_account_upgrade', 'yes' ) === 'yes' ? true : false );

            if ( $show_upgrade_notice && get_option( 'wwlc_enable_account_upgrade' ) === 'yes' ) {

                if ( isset( $_GET['remove'] ) && filter_var( $_GET['remove'], FILTER_VALIDATE_BOOLEAN ) === true ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    update_user_meta( $user->ID, 'wwlc_request_upgrade_approved', false );
                }

                if ( in_array( 'customer', $user_roles, true ) ) {

                    $message = get_option( 'wwlc_account_upgrade_message' );
                    $message = ! empty( $message ) ? html_entity_decode( $message ) : __( 'You are registered as a standard customer. Click here to {link}request an account upgrade{/link}.', 'woocommerce-wholesale-lead-capture' );
                    echo '<div class="wwlc_upgrade_account_request_message">';
                    echo apply_filters( 'wwlc_upgrade_account_request_message', $this->format_string( $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '</div>';
                    echo '<br/><br/>';

                } elseif ( in_array( 'wwlc_unapproved', $user_roles, true ) && get_user_meta( $user->ID, 'wwlc_request_upgrade', true ) ) {

                    echo apply_filters( 'wwlc_upgrade_account_pending_approval', __( 'Your account is pending for approval.', 'woocommerce-wholesale-lead-capture' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<br/><br/>';

                } elseif ( get_user_meta( $user->ID, 'wwlc_request_upgrade_approved', true ) ) {
                    global $wp;
                    $current_url = home_url( add_query_arg( array(), $wp->request ) ) . '?remove=true';?>

                    <div class="woocommerce-message woocommerce-message--success woocommerce-Message woocommerce-Message--success woocommerce-success">
                        <a class="woocommerce-Button button" href="<?php echo esc_url( $current_url ); ?>"><?php esc_html_e( 'Remove', 'woocommerce-wholesale-lead-capture' ); ?></a>
                        <?php apply_filters( 'wwlc_upgrade_account_approved', esc_html_e( 'Your upgrade account request has been approved.', 'woocommerce-wholesale-lead-capture' ) ); ?>
                    </div>

                <?php
                }
}
        }

        /**
         * Display account upgrade notice.
         *
         * @param bool $val    Default is true.
         *
         * @since 1.15
         * @access public
         * @return bool
         */
        public function allow_upgrade_account( $val ) {

            $user       = wp_get_current_user();
            $user_roles = is_user_logged_in() ? $user->roles : array();

            return in_array( 'customer', $user_roles, true ) && get_option( 'wwlc_enable_account_upgrade' ) === 'yes' ? false : $val;
        }

        /**
         * Handles upgrading customer account.
         *
         * @param array  $user_data               Registration form data.
         * @param bool   $auto_generated_password Auto generate password.
         * @param string $password                Password text.
         *
         * @since 1.15
         * @since 1.17.4 remove $email_processor parameter
         * @access public
         * @return array
         */
        public function upgrade_account( $user_data, $auto_generated_password, $password ) {

            $account_upgrade = get_option( 'wwlc_enable_account_upgrade' );
            $user            = wp_get_current_user();
            $user_roles      = is_user_logged_in() ? $user->roles : array();

            if ( in_array( 'customer', $user_roles, true ) && 'yes' === $account_upgrade ) {

                // Request Upgrade flag.
                update_user_meta( $user->ID, 'wwlc_request_upgrade', true );

                // Get new user.
                $new_lead = new WP_User( $user->ID );

                // Remove all associated roles.
                $currentRoles = $new_lead->roles;

                foreach ( $currentRoles as $role ) {
                    $new_lead->remove_role( $role );
                }

                // Save registration form fields.
                $this->_wwlc_user_account->save_registration_form_fields( $user->ID, $user_data, $auto_generated_password );

                // Save customer billing address.
                $this->_wwlc_user_account->wwlc_save_customer_billing_address( $user->ID );

                // Transfer uploaded files from temporary folder to users wholesale folder.
                $this->_wwlc_user_account->_move_user_files_to_permanent( $user->ID );

                // Process auto approve new leads.
                $this->_wwlc_user_account->process_new_leads( $user_data, $new_lead, $password );

                wc_do_deprecated_action( 'wwlc_action_after_create_user', array( $new_lead ), '1.17.4', 'wwlc_action_after_create_wholesale_lead' );
                do_action( 'wwlc_action_after_create_wholesale_lead', $new_lead );

                $response = array(
                    'status'          => 'success',
                    'form_data'       => $user_data,
                    'success_message' => apply_filters( 'wwlc_inline_account_upgrade_notice', __( 'Thank you for your registration. We will be in touch shortly to discuss your account upgrade request.', 'woocommerce-wholesale-lead-capture' ), 'success' ),
                    'user_id'         => $user->ID,
                );

                $response = apply_filters( 'wwlc_upgrade_user_response_data', $response );

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode( $response );
                    die();

                } else {
                    return $response;
                }
}
        }

        /**
         * Set user approve flag. Approve via request upgrade account.
         *
         * @param object $user WP User object.
         *
         * @since 1.15
         * @access public
         */
        public function user_approve_flag( $user ) {

            if ( get_user_meta( $user->ID, 'wwlc_request_upgrade', true ) ) {

                // Request Upgrade Approved Flag
                // To be used as notice.
                update_user_meta( $user->ID, 'wwlc_request_upgrade_approved', true );

            }
        }

        /**
         * Display upgrade notice in registration form.
         *
         * @since 1.15
         * @access public
         */
        public function display_upgrade_notice_registration_form() {

            $user        = wp_get_current_user();
            $user_roles  = is_user_logged_in() ? $user->roles : array();
            $show_notice = apply_filters( 'display_upgrade_notice_registration_form', true );

            if ( $show_notice && in_array( 'customer', $user_roles, true ) && get_option( 'wwlc_enable_account_upgrade' ) === 'yes' ) {
            ?>

                <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
                    <?php apply_filters( 'wwlc_registration_form_upgrade_account_notice', esc_html_e( 'Upgrade Account Request.', 'woocommerce-wholesale-lead-capture' ) ); ?>
                </div>

            <?php
            }
        }

        /**
         * Display upgrade notice in registration form.
         *
         * @since 1.15
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function display_field_as_read_only( $field, $key, $args, $value ) {

            $required        = $args['required'] ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-wholesale-lead-capture' ) . '">*</abbr>' : '';
            $sort            = $args['priority'] ? $args['priority'] : '';
            $field_container = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr( $sort ) . '">%3$s</p>';
            $container_class = esc_attr( implode( ' ', $args['class'] ) );
            $field_html      = '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';

            if ( 'wwlc_password' === $key ) {
                $field_html .= '<em>' . apply_filters( 'wwlc_read_only_password', __( 'Your current password.', 'woocommerce-wholesale-lead-capture' ) ) . '</em>';
            } else {

                $user       = wp_get_current_user();
                $user_roles = is_user_logged_in() ? $user->roles : array();

                if ( 'user_email' === $key ) {
                    $value = $user->user_email;
                } elseif ( 'wwlc_username' === $key ) {
                    $value = $user->user_login;
                }
                $field_html .= '<input disabled type="text" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '"  id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" />';
            }

            return sprintf( $field_container, $container_class, $key, $field_html );
        }

        /**
         * Update type of Username, Email, Password into read-only text/field.
         *
         * @since 1.15
         * @access public
         *
         * @param array $fields List of registration fields.
         * @return string
         */
        public function update_type_as_read_only( $fields ) {

            $user       = wp_get_current_user();
            $user_roles = is_user_logged_in() ? $user->roles : array();

            if ( in_array( 'customer', $user_roles, true ) && get_option( 'wwlc_enable_account_upgrade' ) === 'yes' ) {

                foreach ( $fields as $key => $field ) {
                    if ( isset( $field['id'] ) && in_array( $field['id'], array( 'user_email', 'wwlc_username', 'wwlc_password' ), true ) ) {
                        $fields[ $key ]['type'] = 'read_only';
                    }
                }
            }

            return $fields;
        }

        /**
         * Format upgrade account string.
         *
         * @param string $upgrade_account_message Text to replace placeholders in.
         *
         * @since 1,17.5
         * @access public
         */
        public function format_string( $upgrade_account_message ) {

            $registration_page     = apply_filters( 'wwlc_account_upgrade_registration_page', wwlc_get_url_of_page_option( 'wwlc_general_registration_page' ) );
            $registration_page_url = '<a href="' . esc_url( $registration_page ) . '">';

            $find = array(
                '{link}',
                '{/link}',
            );

		    $replace = array(
                $registration_page_url,
                '</a>',
            );

            /**
             * Filter for main find/replace.
             *
             * @since 1.17.5
             */
            return apply_filters( 'wwlc_upgrade_account_format_string', str_replace( $find, $replace, $upgrade_account_message ) );
        }

        /**
         * Execute model.
         *
         * @since 1.15
         * @access public
         */
        public function run() {

            // Display notice in WC account page.
            add_action( 'woocommerce_account_dashboard', array( $this, 'display_account_upgrade_notice' ), 10, 4 );

            // Display notice in registration form page.
            add_action( 'before_registration_form', array( $this, 'display_upgrade_notice_registration_form' ) );

            // Update type as read-only.
            add_filter( 'wwlc_registration_form_fields', array( $this, 'update_type_as_read_only' ) );

            // Display Username, Email, Password as read-only.
            add_filter( 'woocommerce_form_field_read_only', array( $this, 'display_field_as_read_only' ), 10, 4 );

            add_filter( 'wwlc_upgrade_account', array( $this, 'allow_upgrade_account' ) );
            add_action( 'wwlc_action_before_create_wholesale_lead', array( $this, 'upgrade_account' ), 10, 3 );
            add_action( 'wwlc_action_after_approve_user', array( $this, 'user_approve_flag' ) );
            add_action( 'wwlc_action_auto_approve_user', array( $this, 'user_approve_flag' ) );
        }
    }
}
