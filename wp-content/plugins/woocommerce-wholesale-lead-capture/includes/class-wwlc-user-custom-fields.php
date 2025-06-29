<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_User_Custom_Fields' ) ) {

    /**
     * Model that houses the logic of WWLC Custom Fields.
     *
     * @since 1.0.0
     */
    class WWLC_User_Custom_Fields {

        /**
         *  Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_User_Custom_Fields.
         *
         * @since 1.0.0
         * @access private
         * @var WWLC_User_Custom_Fields
         */
        private static $_instance;

        /**
         * Property that holds the single main instance of WWLC_User_Custom_Fields.
         *
         * @since 1.0.0
         * @access private
         * @var WWLC_User_Account
         */
        private $_wwlc_user_account;

        /**
         * Property that holds the single main instance of WWLC_User_Custom_Fields.
         *
         * @since 1.0.0
         * @access private
         * @var WWLC_Emails
         */
        private $_wwlc_emails;

        /**
         * Class Methods
         */

        /**
         * WWLC_User_Custom_Fields constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_User_Custom_Fields model.
         *
         * @access public
         * @since 1.0.0
         * @since 1.6.3 Code Refactor
         */
        public function __construct( $dependencies ) {
            $this->_wwlc_user_account = $dependencies['WWLC_User_Account'];
            $this->_wwlc_emails       = $dependencies['WWLC_Emails'];
        }

        /**
         * Ensure that only one instance of WWLC_User_Custom_Fields is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_User_Custom_Fields model.
         *
         * @return WWLC_User_Custom_Fields
         * @since 1.0.0
         * @since 1.6.3 Code Refactor : add dependency on new instance
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Add custom row action to user listing page.
         *
         * @since 1.0.0
         * @since 1.7.3 Removed condition current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ), replaced with $this->wwlc_is_user_allowed_to_approve()
         * @access public
         *
         * @param string[] $actions     An array of action links to be displayed.
         *                              Default 'Edit', 'Delete' for single site, and
         *                              'Edit', 'Remove' for Multisite.
         * @param WP_User  $user_object WP_User object for the currently listed user.
         * @return mixed
         */
        public function wwlc_add_user_list_custom_row_action_ui( $actions, $user_object ) {

            // Admins and Shop managers can manage wholesale users.
            if ( $this->wwlc_is_user_allowed_to_approve() && get_current_user_id() !== $user_object->ID ) {

                $user           = get_userdata( $user_object->ID );
                $is_user_active = get_user_meta( $user->ID, 'wwlc_is_user_active', true );

                if ( in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {

                    $actions['wwlc_user_row_action_approve']  = '<a class="wwlc_approve wwlc_user_row_action" data-userID="' . $user_object->ID . '" href="#">' . __( 'Approve', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                    $actions['wwlc_user_row_action_reject']   = '<a class="wwlc_reject wwlc_user_row_action" data-userID="' . $user_object->ID . '" href="#">' . __( 'Reject', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                    $actions['wwlc_user_row_action_activate'] = '<a class="wwlc_activate wwlc_user_row_action" data-userID="' . $user_object->ID . '" href="#">' . __( 'Activate', 'woocommerce-wholesale-lead-capture' ) . '</a>';

                } elseif ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {

                    $actions['wwlc_user_row_action_approve'] = '<a class="wwlc_approve wwlc_user_row_action" data-userID="' . $user_object->ID . '" href="#">' . __( 'Approve', 'woocommerce-wholesale-lead-capture' ) . '</a>';

                } elseif ( 'no' === $is_user_active && ! in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {

                    $actions['wwlc_user_row_action_activate'] = '<a class="wwlc_activate wwlc_user_row_action" data-userID="' . $user_object->ID . '" href="#">' . __( 'Activate', 'woocommerce-wholesale-lead-capture' ) . '</a>';

                } else {

                    $actions['wwlc_user_row_action_deactivate'] = '<a class="wwlc_deactivate wwlc_user_row_action" data-userID="' . $user_object->ID . '" href="#">' . __( 'Deactivate', 'woocommerce-wholesale-lead-capture' ) . '</a>';

                }
            }

            return $actions;
        }

        /**
         * Add custom column to user listing page.
         *
         * @since 1.0.0
         * @access public
         *
	     * @param string[] $columns An array of columns with column IDs as the keys
	     *                          and translated column names as the values.
         * @return mixed
         */
        public function wwlc_add_user_listing_custom_column( $columns ) {

            $array_keys = array_keys( $columns );
            $last_index = $array_keys[ count( $array_keys ) - 1 ];
            $last_value = $columns[ $last_index ];
            array_pop( $columns );

            $columns['wwlc_user_status']       = __( 'Status', 'woocommerce-wholesale-lead-capture' );
            $columns['wwlc_registration_date'] = __( 'Registration Date', 'woocommerce-wholesale-lead-capture' );
            $columns['wwlc_approval_date']     = __( 'Approval Date', 'woocommerce-wholesale-lead-capture' );
            $columns['wwlc_rejection_date']    = __( 'Rejection Date', 'woocommerce-wholesale-lead-capture' );

            $columns[ $last_index ] = $last_value;

            return $columns;
        }

        /**
         * Add content to custom column to user listing page.
         *
         * @since 1.0.0
         * @access public
         *
         * @param string $val         Custom column output. Default empty.
         * @param string $column_name Column name.
         * @param int    $user_id     ID of the currently-listed user.
         * @return string
         */
        public function wwlc_add_user_listing_custom_column_content( $val, $column_name, $user_id ) {

            $user           = get_userdata( $user_id );
            $is_user_active = get_user_meta( $user_id, 'wwlc_is_user_active', true );

            if ( 'wwlc_user_status' === $column_name ) {

                if ( in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {
                    return "<span style='width: 80px; text-align: center; color: #fff; background-color: black; display: inline-block; padding: 0 6px;'>" . __( 'Unapproved', 'woocommerce-wholesale-lead-capture' ) . '</span>';
                } elseif ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {
                    return "<span style='width: 80px; text-align: center; color: #fff; background-color: orange; display: inline-block; padding: 0 6px;'>" . __( 'Rejected', 'woocommerce-wholesale-lead-capture' ) . '</span>';
                } elseif ( 'no' === $is_user_active && ! in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {
                    return "<span style='width: 80px; text-align: center; color: #fff; background-color: grey; display: inline-block; padding: 0 6px;'>" . __( 'Inactive', 'woocommerce-wholesale-lead-capture' ) . '</span>';
                } else {
                    return "<span style='width: 80px; text-align: center; color: #fff; background-color: green; display: inline-block; padding: 0 6px;'>" . __( 'Active', 'woocommerce-wholesale-lead-capture' ) . '</span>';
                }
            } elseif ( 'wwlc_registration_date' === $column_name ) {

                return "<span class='wwlc_registration_date' >" . get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' ) . '</span>';

            } elseif ( 'wwlc_approval_date' === $column_name ) {

                if ( ! in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) && ! in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {

                    $approval_date = get_user_meta( $user->ID, 'wwlc_approval_date', true );

                    // For older versions of this plugin (prior to 1.3.1) we don't save approval dates.
                    // If approval date is not present, we will use the registration date by default.
                    if ( ! $approval_date ) {
                        $approval_date = $user->user_registered;
                    }

                    return "<span class='wwlc_approval_date'>" . $approval_date . '</span>';

                }
            } elseif ( 'wwlc_rejection_date' === $column_name ) {

                if ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {

                    $rejection_date = get_user_meta( $user->ID, 'wwlc_rejection_date', true );

                    return "<span class='wwlc_rejection_date'>" . $rejection_date . '</span>';

                }
            }

            // Return current column content if its not our column.
            return $val;
        }

        /**
         * Add custom admin notices on user listing page. WWLC related.
         *
         * @since 1.0.0
         */
        public function wwlc_custom_submissions_bulk_action_notices() {
            global $post_type, $pagenow;

            // phpcs:disable WordPress.Security.NonceVerification
            if ( 'users.php' === $pagenow ) {
                if ( ( isset( $_REQUEST['users_approved'] ) && (int) $_REQUEST['users_approved'] ) ||
                    ( isset( $_REQUEST['users_rejected'] ) && (int) $_REQUEST['users_rejected'] ) ||
                    ( isset( $_REQUEST['users_activated'] ) && (int) $_REQUEST['users_activated'] ) ||
                    ( isset( $_REQUEST['users_deactivated'] ) && (int) $_REQUEST['users_deactivated'] ) ) {

                    if ( ! empty( $_REQUEST['users_approved'] ) ) {
                        $action   = 'approved';
                        $affected = $_REQUEST['users_approved'];
                    } elseif ( ! empty( $_REQUEST['users_rejected'] ) ) {
                        $action   = 'rejected';
                        $affected = $_REQUEST['users_rejected'];
                    } elseif ( ! empty( $_REQUEST['users_activated'] ) ) {
                        $action   = 'activated';
                        $affected = $_REQUEST['users_activated'];
                    } elseif ( ! empty( $_REQUEST['users_deactivated'] ) ) {
                        $action   = 'deactivated';
                        $affected = $_REQUEST['users_deactivated'];
                    }

                    $message = sprintf(
                        // translators: %1$d is the number of affected users, %2$s is the action performed.
                        _n(
                            '%1$d user has been %2$s.',
                            '%1$d users have been %2$s.',
                            $affected,
                            'woocommerce-wholesale-lead-capture'
                        ),
                        $affected,
                        $action
                    );

                    echo wp_kses_post( "<div class=\"updated\"><p>{$message}</p></div>" );
                } elseif ( ( isset( $_REQUEST['action'] ) && 'wwlc_approve' === $_REQUEST['action'] ) ||
                    ( isset( $_REQUEST['action'] ) && 'wwlc_reject' === $_REQUEST['action'] ) ||
                    ( isset( $_REQUEST['action'] ) && 'wwlc_activate' === $_REQUEST['action'] ) ||
                    ( isset( $_REQUEST['action'] ) && 'wwlc_deactivate' === $_REQUEST['action'] ) ) {

                    if ( isset( $_REQUEST['users'] ) ) {
                        if ( count( $_REQUEST['users'] ) > 0 ) {
                            if ( 'wwlc_approve' === $_REQUEST['action'] ) {
                                $action = 'approved';
                            }
                            if ( 'wwlc_reject' === $_REQUEST['action'] ) {
                                $action = 'rejected';
                            }
                            if ( 'wwlc_activate' === $_REQUEST['action'] ) {
                                $action = 'activated';
                            }
                            if ( 'wwlc_deactivate' === $_REQUEST['action'] ) {
                                $action = 'deactivated';
                            }

                            $message = sprintf(
                                // translators: %1$d is the number of users, %2$s is the action performed (approved/rejected/etc).
                                _n(
                                    '%1$d user %2$s',
                                    '%1$d users %2$s.',
                                    count( $_REQUEST['users'] ),
                                    'woocommerce-wholesale-lead-capture'
                                ),
                                number_format_i18n( count( $_REQUEST['users'] ) ),
                                $action
                            );
                            echo wp_kses_post( "<div class=\"updated\"><p>{$message}</p></div>" );
                        }
                    }
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification
        }

        /**
         * Add custom user listing bulk action items on the action select boxes. Done via JS.
         *
         * @since 1.0.0
         * @since 1.7.3 Removed condition current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ), replaced with $this->wwlc_is_user_allowed_to_approve()
         */
        public function wwlc_custom_user_listing_bulk_action_footer_js() {

            global $pagenow;

            if ( $this->wwlc_is_user_allowed_to_approve() && 'users.php' === $pagenow ) { ?>

                <script type="text/javascript">

                    jQuery( document ).ready( function() {

                        jQuery( '<option>' ).val( 'wwlc_approve' ).text( '<?php esc_html_e( 'Approve', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action']" );
                        jQuery( '<option>' ).val( 'wwlc_approve' ).text( '<?php esc_html_e( 'Approve', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action2']" );

                        jQuery( '<option>' ).val( 'wwlc_reject' ).text( '<?php esc_html_e( 'Reject', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action']" );
                        jQuery( '<option>' ).val( 'wwlc_reject' ).text( '<?php esc_html_e( 'Reject', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action2']" );

                        jQuery( '<option>' ).val( 'wwlc_activate' ).text( '<?php esc_html_e( 'Activate', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action']" );
                        jQuery( '<option>' ).val( 'wwlc_activate' ).text( '<?php esc_html_e( 'Activate', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action2']" );

                        jQuery( '<option>' ).val( 'wwlc_deactivate' ).text( '<?php esc_html_e( 'Deactivate', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action']" );
                        jQuery( '<option>' ).val( 'wwlc_deactivate' ).text( '<?php esc_html_e( 'Deactivate', 'woocommerce-wholesale-lead-capture' ); ?>' ).appendTo( "select[name='action2'] ");

                    });

                </script>

            <?php
            }
        }

        /**
         * Add custom user listing bulk action.
         *
         * @since 1.3.3
         * @since 1.7.3 Removed condition current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ), replaced with $this->wwlc_is_user_allowed_to_approve()
         */
        public function wwlc_custom_user_listing_bulk_action() {

            global $pagenow;

            if ( $this->wwlc_is_user_allowed_to_approve() && 'users.php' === $pagenow ) {

                // get the current action.
                $wp_list_table = _get_list_table( 'WP_Users_List_Table' );  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc.
                $action        = $wp_list_table->current_action();

                // set allowed actions, and check if current action is in allowed actions.
                $allowed_actions = array( 'wwlc_approve', 'wwlc_reject', 'wwlc_activate', 'wwlc_deactivate' );
                if ( ! in_array( $action, $allowed_actions, true ) ) {
                    return;
                }

                // security check.
                check_admin_referer( 'bulk-users' );

                // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids' or 'users'.
                if ( isset( $_REQUEST['users'] ) ) {
                    $user_ids = $_REQUEST['users'];
                }

                if ( empty( $user_ids ) ) {
                    return;
                }

                // this is based on wp-admin/edit.php.
                $sendback = remove_query_arg( array( 'wwlc_approve', 'wwlc_reject', 'wwlc_activate', 'wwlc_deactivate', 'untrashed', 'deleted', 'ids' ), wp_get_referer() );
                if ( ! $sendback ) {
                    $sendback = admin_url( 'users.php' );
                }

                $pagenum  = $wp_list_table->get_pagenum();
                $sendback = add_query_arg( 'paged', $pagenum, $sendback );

                switch ( $action ) {

                    case 'wwlc_approve':
                        $users_activated = 0;
                        foreach ( $user_ids as $user_id ) {

                            if ( get_current_user_id() !== $user_id ) {
                                if ( $this->_wwlc_user_account->wwlc_approve_user( array( 'userID' => $user_id ) ) ) {
                                    ++$users_activated;
                                }
                            }
                        }

                        $sendback = add_query_arg(
                            array(
                                'users_approved' => $users_activated,
                                'ids'            => join(
                                    ',',
                                    $user_ids
                                ),
                            ),
                            $sendback
                        );
                        break;

                    case 'wwlc_reject':
                        $users_rejected = 0;
                        foreach ( $user_ids as $user_id ) {

                            if ( get_current_user_id() !== $user_id ) {
                                if ( $this->_wwlc_user_account->wwlc_reject_user( array( 'userID' => $user_id ) ) ) {
                                    ++$users_rejected;
                                }
                            }
                        }

                        $sendback = add_query_arg(
                            array(
                                'users_rejected' => $users_rejected,
                                'ids'            => join(
                                    ',',
                                    $user_ids
                                ),
                            ),
                            $sendback
                        );
                        break;

                    case 'wwlc_activate':
                        // if we set up user permissions/capabilities, the code might look like:
                        // if ( !current_user_can($post_type_object->cap->export_post, $post_id) )
                        // wp_die( __('You are not allowed to export this post.') );.

                        $users_activated = 0;
                        foreach ( $user_ids as $user_id ) {

                            if ( get_current_user_id() !== $user_id ) {
                                if ( $this->_wwlc_user_account->wwlc_activate_user( array( 'userID' => $user_id ) ) ) {
                                    ++$users_activated;
                                }
                            }
                        }

                        $sendback = add_query_arg(
                            array(
                                'users_activated' => $users_activated,
                                'ids'             => join(
                                    ',',
                                    $user_ids
                                ),
                            ),
                            $sendback
                        );
                        break;

                    case 'wwlc_deactivate':
                        $users_deactivated = 0;
                        foreach ( $user_ids as $user_id ) {

                            if ( get_current_user_id() !== $user_id ) {
                                if ( $this->_wwlc_user_account->wwlc_deactivate_user( array( 'userID' => $user_id ) ) ) {
                                    ++$users_deactivated;
                                }
                            }
                        }

                        $sendback = add_query_arg(
                            array(
                                'users_deactivated' => $users_deactivated,
                                'ids'               => join(
                                    ',',
                                    $user_ids
                                ),
                            ),
                            $sendback
                        );
                        break;

                    default:
                        return;

                }

                $sendback = remove_query_arg( array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' ), $sendback );

                wp_safe_redirect( $sendback );
                exit();

            }
        }

        /**
         * Get the user registration form fields
         *
         * @since 2.0.0 Added
         *
         * @return array
         */
        public function get_registration_form_fields() {
            global $WWLC_REGISTRATION_FIELDS;

            $registration_form_fields = $WWLC_REGISTRATION_FIELDS ?? array();

            usort( $registration_form_fields, array( $this, 'usort_callback' ) );

            $form_fields = array();
            foreach ( $registration_form_fields as $field ) {
                $form_fields[ $field['name'] ] = $field;
            }

            return apply_filters(
                'wwlc_user_registration_form_fields',
                $form_fields
            );
        }

        /**
         * Get all custom fields.
         *
         * @since 2.0.0 Added
         *
         * @return array
         */
        public function get_custom_fields() {
            $formatted_fields = $this->_get_formatted_custom_fields();

            usort( $formatted_fields, array( $this, 'usort_callback' ) );

            $form_fields = array();
            foreach ( $formatted_fields as $field ) {
                $form_fields[ $field['name'] ] = $field;
            }

            return apply_filters(
                'wwlc_user_registration_custom_fields',
                $form_fields
            );
        }

        /**
         * Display custom fields on user admin.
         *
         * @since 1.0.0
         * @access public
         *
         * @param WP_User $user The current WP_User object.
         */
        public function wwlc_display_custom_fields_on_user_admin_page( $user ) { // phpcs:ignore.

            global $WWLC_REGISTRATION_FIELDS;

            $custom_fields = $this->_get_formatted_custom_fields();

            $registration_form_fields = array_merge( $WWLC_REGISTRATION_FIELDS, $custom_fields );

            usort( $registration_form_fields, array( $this, 'usort_callback' ) );

            require_once WWLC_PLUGIN_DIR . 'views/custom-fields/view-wwlc-custom-fields-on-user-admin.php';
        }

        /**
         * Return formatted custom fields. ( Abide to the formatting of existing fields ).
         *
         * @return array
         *
         * @since 1.1.0
         */
        private function _get_formatted_custom_fields() {

            $registration_form_custom_fields           = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            $formatted_registration_form_custom_fields = array();

            foreach ( $registration_form_custom_fields as $field_id => $custom_field ) {

                $formatted_registration_form_custom_fields[] = array(
                    'label'        => $custom_field['field_name'],
                    'name'         => $field_id,
                    'id'           => $field_id,
                    'class'        => 'wwlc_registration_field form_field wwlc_custom_field',
                    'type'         => $custom_field['field_type'],
                    'required'     => ( '1' === $custom_field['required'] ) ? true : false,
                    'custom_field' => true,
                    'active'       => ( '1' === $custom_field['enabled'] ) ? true : false,
                    'validation'   => array(),
                    'field_order'  => $custom_field['field_order'],
                    'attributes'   => isset( $custom_field['attributes'] ) ? $custom_field['attributes'] : '',
                    'options'      => isset( $custom_field['options'] ) ? $custom_field['options'] : '',
                );

            }

            return $formatted_registration_form_custom_fields;
        }

        /**
         * Usort callback for sorting associative arrays.
         * Used for sorting field ordering on the form. (Registration form).
         *
         * @since 1.1.0
         *
         * @param array $arr1 Array 1.
         * @param array $arr2 Array 2.
         * @return int
         */
        public function usort_callback( $arr1, $arr2 ) {

            if ( $arr1['field_order'] === $arr2['field_order'] ) {
                return 0;
            }

            return ( $arr1['field_order'] < $arr2['field_order'] ) ? -1 : 1;
        }

        /**
         * Save custom fields on user admin.
         *
         * @since 1.0.0
         * @since 1.17.5 Added filter to allow saving file on user admin.
         *
         * @param int $user_id The user ID.
         * @return bool
         */
        public function wwlc_save_custom_fields_on_user_admin_page( $user_id ) {
            if ( ! current_user_can( 'edit_user', $user_id ) && false === wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
                return false;
            }

            global $WWLC_REGISTRATION_FIELDS;

            $custom_fields = $this->_get_formatted_custom_fields();

            $registration_form_fields = array_merge( $WWLC_REGISTRATION_FIELDS, $custom_fields );

            usort( $registration_form_fields, array( $this, 'usort_callback' ) );

            foreach ( $registration_form_fields as $field ) {

                if ( ! $field['custom_field'] ) {
                    continue;
                }

                if ( array_key_exists( $field['id'], $_POST ) ) {
                    if ( 'file' === $field['type'] ) {
                        if ( ! empty( $_POST[ $field['id'] ] ) ) {
                            // Set temporary data.
                            update_user_meta( $user_id, $field['id'], $_POST[ $field['id'] ] );

                            // Transfer files from temp folder to user folder.
                            $this->_wwlc_user_account->_move_user_files_to_permanent( $user_id );
                        } else {
                            $file_name = $this->_wwlc_user_account->_get_user_file_name( $user_id, $field['id'] );
                            if ( ! empty( $file_name ) ) {
                                // Delete files from user folder.
                                $this->_wwlc_user_account->_delete_user_file( $user_id, $file_name );
                            }

                            // Delete temporary data.
                            update_user_meta( $user_id, $field['id'], $_POST[ $field['id'] ] );
                        }
                    } else {
                        update_user_meta( $user_id, $field['id'], $_POST[ $field['id'] ] );
                    }
                } elseif ( 'checkbox' === $field['type'] && $field['custom_field'] ) {
                    update_user_meta( $user_id, $field['id'], array() );
                }
            }
        }

        /**
         * Show Approve, Reject, Activate and Deactivate buttons on user edit screen.
         *
         * @since 1.5.0
         * @since 1.7.3 Moved from Class WWLC_User_Account to Class WWLC_User_Custom_Fields
         *              Removed condition current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ), replaced with $this->wwlc_is_user_allowed_to_approve()
         */
        public function wwlc_show_user_management_buttons_in_user_edit_screen() {

            $screen = get_current_screen();

            // phpcs:disable WordPress.Security.NonceVerification
            if ( $this->wwlc_is_user_allowed_to_approve() && 'user-edit' === $screen->id && isset( $_REQUEST['user_id'] ) ) {
                ?>
                <div class="notice manage-user-controls" data-screen-view="edit-screen">
                    <h4><?php esc_html_e( 'Manage User:', 'woocommerce-wholesale-lead-capture' ); ?></h4>
                    <?php
                    $user_id = sanitize_text_field( $_REQUEST['user_id'] );

                    $approveText    = __( 'Approve', 'woocommerce-wholesale-lead-capture' );
                    $rejectText     = __( 'Reject', 'woocommerce-wholesale-lead-capture' );
                    $activateText   = __( 'Activate', 'woocommerce-wholesale-lead-capture' );
                    $deactivateText = __( 'Deactivate', 'woocommerce-wholesale-lead-capture' );
                    $user           = get_userdata( $user_id );
                    $actions        = '';

                    $is_user_active = get_user_meta( $user_id, 'wwlc_is_user_active', true );

                    if ( in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {

                        $actions .= '<a class="wwlc_approve wwlc_user_row_action" data-userID="' . $user_id . '" href="#" title="' . $approveText . '">' . $approveText . '</a> | ';
                        $actions .= '<a class="wwlc_reject wwlc_user_row_action" data-userID="' . $user_id . '" href="#" title="' . $rejectText . '">' . $rejectText . '</a>';

                    } elseif ( in_array( WWLC_REJECTED_ROLE, $user->roles, true ) ) {

                        $actions .= '<a class="wwlc_approve wwlc_user_row_action" data-userID="' . $user_id . '" href="#" title="' . $approveText . '">' . $approveText . '</a>';

                    } elseif ( 'no' === $is_user_active && in_array( WWLC_UNAPPROVED_ROLE, $user->roles, true ) ) {

                        $actions .= '<a class="wwlc_activate wwlc_user_row_action" data-userID="' . $user_id . '" href="#" title="' . $activateText . '">' . $activateText . '</a>';

                    } else {

                        $actions .= '<a class="wwlc_deactivate wwlc_user_row_action" data-userID="' . $user_id . '" href="#" title="' . $deactivateText . '">' . $deactivateText . '</a>';

                    }

                    echo wp_kses_post( apply_filters( 'wwlc_manage_user_controls', $actions ) );
                    ?>

                </div>
                <?php
            }
            // phpcs:enable WordPress.Security.NonceVerification
        }

        /**
         * Display Loader when processing AJAX request
         *
         * @since 1.5.0
         * @since 1.7.3 Moved from Class WWLC_User_Account to Class WWLC_User_Custom_Fields
         *              Removed condition current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ), replaced with $this->wwlc_is_user_allowed_to_approve()
         */
        public function wwlc_loading_screen_for_request_request() {

            $page = get_current_screen();

            if ( $this->wwlc_is_user_allowed_to_approve() && ( 'user-edit' === $page->id || 'users' === $page->id ) ) {
                echo '<div class="loading-screen"></div>';
            }
        }

        /**
         * Check if current user is allowed to approve / reject users. Allowed default user roles are administrator and shop_manager.
         *
         * @since 1.7.3
         * @since 1.14  Multisite support. Show user listings custom actions if admin is logged-in in a multisite. WWLC-286
         * @return bool
         */
        public function wwlc_is_user_allowed_to_approve() {

            $current_user = wp_get_current_user();

            if ( is_multisite() && empty( $current_user->roles ) ) {

                $user_blog   = get_blogs_of_user( $current_user->ID );
                $user_blog   = array_values( $user_blog );
                $blog_id     = $user_blog[0]->userblog_id; // User Blog ID where they registered.
                $wp_user_obj = '';

                // Find the roles from the original site the user was registered.
                if ( isset( $blog_id ) ) {
                    $wp_user_obj = new WP_User( $current_user->ID, '', $blog_id );
                }

                if ( is_a( $wp_user_obj, 'WP_User' ) && ! empty( $wp_user_obj->roles ) ) {
                    $current_user = &$wp_user_obj;
                }
            }

            $allowed_roles_default = apply_filters( 'wwlc_allowed_user_roles', array( 'administrator', 'shop_manager' ) );
            $current_user_roles    = $current_user->roles;
            $roles_allowed         = array_intersect( $current_user_roles, $allowed_roles_default );

            return ! empty( $roles_allowed ) ? true : false;
        }

        /**
         * Blacklist wwlc custom field from wpml string translation. WWLC-281
         *
         * @since 1.14
         *
         * @param array $alloptions Array of wp options.
         * @return array
         */
        public function wwlc_blacklist_custom_field_from_wpml_string_translation( $alloptions ) {

            if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'string-translation.php' ) !== false ) { // phpcs:ignore WordPress.Security.NonceVerification

                if ( isset( $alloptions['wwlc_option_registration_form_custom_fields'] ) ) {

                    $custom_fields   = maybe_unserialize( $alloptions['wwlc_option_registration_form_custom_fields'] );
                    $custom_fields2  = array();
                    $wpml_black_list = apply_filters( 'wwlc_blacklist_field_attribute_wpml_translate', array( 'field_type', 'field_order', 'required', 'enabled' ) );

                    foreach ( $custom_fields as $key => $custom_field ) {

                        $custom_field2 = array();

                        foreach ( $custom_field as $field_key => $val ) {

                            if ( ! in_array( $field_key, $wpml_black_list, true ) ) {
                                $custom_field2[ $field_key ] = $val;
                            }
                        }

                        $custom_fields2[ $key ] = $custom_field2;

                    }

                    $alloptions['wwlc_option_registration_form_custom_fields'] = maybe_serialize( $custom_fields2 );

                }
            }

            return $alloptions;
        }

        /**
		 * Sets the loead capture custom fields used during checkout.
		 *
		 * @since 1.17.4
         * @access public
		 *
		 * @param array[] $fields Checkout fields.
         * @return $fields
		 */
        public function wwlc_show_custom_fields_on_checkout( $fields ) {
            $user_wholesale_role = wwlc_get_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
                $custom_fields = (array) $custom_fields;
                $custom_fields = array_filter( $custom_fields );

                if ( ! empty( $custom_fields ) ) {
                    foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                        if ( $custom_field['enabled'] > 0 && $custom_field['checkout_display'] > 0 ) {
                            // Disallow showing custom field for content and terms conditions field type.
                            if ( in_array( $custom_field['field_type'], array( 'file', 'content', 'terms_conditions' ), true ) ) {
                                continue;
                            }

                            $fields['billing'][ $custom_field_id ] = array(
                                'type'         => $custom_field['field_type'],
                                'label'        => $custom_field['field_name'],
                                'class'        => array( 'form-row-wide' ),
                                'required'     => $custom_field['required'],
                                'placeholder'  => $custom_field['field_placeholder'],
                                'autocomplete' => $custom_field_id,
                                'priority'     => 200 + $custom_field['field_order'],
                            );

                            if ( in_array( $custom_field['field_type'], array( 'select', 'radio', 'checkbox' ), true ) ) {
                                $options = array();
                                foreach ( $custom_field['options'] as $wwlc_cf_options ) {
                                    $options[ $wwlc_cf_options['value'] ] = $wwlc_cf_options['text'];
                                }
                                $fields['billing'][ $custom_field_id ]['options'] = $options;
                            }
                        }
                    }
                }
            }

            return $fields;
        }

        /**
         * Add wwlc custom fields shown on checkout to checkout posted data.
         *
         * @since 1.17.6
         * @access public
         *
         * @param array $data Posted data.
         */
        public function add_custom_fields_to_checkout_posted_data( $data ) {
            // phpcs:disable WordPress.Security.NonceVerification
            $user_wholesale_role = wwlc_get_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
                $custom_fields = (array) $custom_fields;
                $custom_fields = array_filter( $custom_fields );

                if ( ! empty( $custom_fields ) ) {
                    foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                        if ( $custom_field['enabled'] > 0 && $custom_field['checkout_display'] > 0 ) {
                            // Disallow custom field for file, content and terms conditions field type.
                            if ( in_array( $custom_field['field_type'], array( 'file', 'content', 'terms_conditions' ), true ) ) {
                                continue;
                            }

                            if ( isset( $_REQUEST[ $custom_field_id ] ) ) {

                                // Sanitize before saving to db.
                                if ( is_array( $_REQUEST[ $custom_field_id ] ) ) {
                                    $data[ $custom_field_id ] = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST[ $custom_field_id ] ) );
                                } else {
                                    $data[ $custom_field_id ] = sanitize_text_field( $_REQUEST[ $custom_field_id ] );
                                }
                            }
                        }
                    }
                }
            }

            // phpcs:enable WordPress.Security.NonceVerification
            return $data;
        }


        /**
         * Save user custom fields data to the DB & add order meta after processing checkout.
         *
         * @since 1.17.4
         * @since 1.17.6 Add compatibility with WooCommerce HPOS.
         * @access public
         *
         * @param int   $order_id The order id being saved.
         * @param array $data  Posted data.
         */
        public function wwlc_save_custom_fields_and_add_order_meta_after_create_order( $order_id, $data ) {

            $user_wholesale_role = wwlc_get_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
                $custom_fields = (array) $custom_fields;
                $custom_fields = array_filter( $custom_fields );

                if ( ! empty( $custom_fields ) ) {
                    $order   = wc_get_order( $order_id );
                    $user_id = $order->get_customer_id();

                    foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                        if ( $custom_field['enabled'] > 0 && $custom_field['checkout_display'] > 0 ) {
                            // Disallow custom field for file, content and terms conditions field type.
                            if ( in_array( $custom_field['field_type'], array( 'file', 'content', 'terms_conditions' ), true ) ) {
                                continue;
                            }

                            if ( isset( $data[ $custom_field_id ] ) ) {
                                update_user_meta( $user_id, $custom_field_id, $data[ $custom_field_id ] );

                                if ( 'checkbox' === $custom_field['field_type'] ) {
                                    $order->update_meta_data( $custom_field_id, implode( ', ', $data[ $custom_field_id ] ) );
                                } else {
                                    $order->update_meta_data( $custom_field_id, $data[ $custom_field_id ] );
                                }
                                $order->save();
                            }
                        }
                    }
                }
            }
        }

        /**
         * Show custom fields data to the order details.
         *
         * @since 1.17.4
         * @access public
         *
         * @param object $order The order object.
         */
        public function wwlc_show_custom_fields_on_order_details( $order ) {
            $user_wholesale_role = wwlc_get_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
                $custom_fields = (array) $custom_fields;
                $custom_fields = array_filter( $custom_fields );
                $user_id       = $order->get_customer_id();

                if ( ! empty( $custom_fields ) ) {

                    $custom_fields_checkout = array();
                    foreach ( $custom_fields as $custom_field_id => $custom_field ) {
                        // Disallow custom field for file, content and terms conditions field type.
                        if ( in_array( $custom_field['field_type'], array( 'file', 'content', 'terms_conditions' ), true ) ) {
                            continue;
                        }

                        if ( $custom_field['enabled'] > 0 && $custom_field['checkout_display'] > 0 ) {
                            $custom_fields_checkout[ $custom_field_id ] = $custom_field;
                        }
                    }

                    if ( ! empty( $custom_fields_checkout ) ) {
                        ?>
                        <section class="woocommerce-columns woocommerce-columns--2 woocommerce-columns--wholesale-details col2-set wholesale-details">
                            <h2 class="woocommerce-column__title"><?php esc_html_e( 'Wholesale details', 'woocommerce' ); ?></h2>
                            <address>
                                <?php
                                $wholesale_details = '';
                                foreach ( $custom_fields_checkout as $custom_field_id => $custom_field ) {

                                    $user_meta_value = get_user_meta( $user_id, $custom_field_id, true );

                                    if ( empty( $user_meta_value ) ) {
                                        continue;
                                    }

                                    $wholesale_details_items = '<div class="%1$s">
                                        <span class="wholesale-details--title">%2$s</span>:
                                        <span class="wholesale-details--value">%3$s</span>
                                    </div>';

                                    if ( 'checkbox' === $custom_field['field_type'] && is_array( $user_meta_value ) ) {
                                        // get text value of checkbox options.
                                        $user_meta_value = array_map(
                                            function ( $option ) use ( $custom_field ) {
                                                return WWLC_Helper_Functions::get_custom_field_option_text( $option, $custom_field );
                                            },
                                            $user_meta_value
                                        );

                                        $user_meta_value = implode( ',', $user_meta_value );
                                    } elseif ( in_array( $custom_field['field_type'], array( 'radio', 'select' ), true ) ) {
                                        $user_meta_value = WWLC_Helper_Functions::get_custom_field_option_text( $user_meta_value, $custom_field );
                                    }

                                    $wholesale_details .= sprintf(
                                        $wholesale_details_items,
                                        $custom_field_id,
                                        esc_html( $custom_field['field_name'] ),
                                        esc_html( $user_meta_value )
                                    );
                                }
                                echo apply_filters( 'wwlc_show_custom_fields_on_order_details', $wholesale_details, $custom_fields_checkout, $order ); //phpcs:ignore
                                ?>
                            </address>
                        </section>
                        <?php
                    }
                }
            }
        }

        /**
		 * Remodel woocommerce form field checkbox output for custom fields.
         * WooCommerce checkbox form field template only shows single checkbox,
         * while lead capture checkbox custom field allows to create multiple.
         *
         * @since 1.17.4
         * @access public
         *
         * @param string  $field Checkbox html markup.
         * @param string  $key   Key.
         * @param array[] $args  Arguments.
         * @param string  $value (default: null).
		 */
        public function wwlc_custom_field_checkbox_on_checkout_page( $field, $key, $args, $value ) {
            $user_wholesale_role = wwlc_get_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
                $custom_fields = (array) $custom_fields;
                $custom_fields = array_filter( $custom_fields );

                if ( ! empty( $custom_fields ) ) {
                    if ( array_key_exists( $key, $custom_fields ) ) {

                        if ( is_string( $args['class'] ) ) {
                            $args['class'] = array( $args['class'] );
                        }

                        if ( $args['required'] ) {
                            $args['class'][] = 'validate-required';
                            $required        = '&nbsp;<abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>';
                        } else {
                            $required = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
                        }

                        if ( is_string( $args['label_class'] ) ) {
                            $args['label_class'] = array( $args['label_class'] );
                        }

                        if ( is_null( $value ) ) {
                            $value = $args['default'];
                        } else {
                            $value = explode( ',', $value );
                        }

                        // Custom attribute handling.
                        $custom_attributes         = array();
                        $args['custom_attributes'] = array_filter( (array) $args['custom_attributes'], 'strlen' );

                        if ( $args['maxlength'] ) {
                            $args['custom_attributes']['maxlength'] = absint( $args['maxlength'] );
                        }

                        if ( ! empty( $args['autocomplete'] ) ) {
                            $args['custom_attributes']['autocomplete'] = $args['autocomplete'];
                        }

                        if ( true === $args['autofocus'] ) {
                            $args['custom_attributes']['autofocus'] = 'autofocus';
                        }

                        if ( $args['description'] ) {
                            $args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
                        }

                        if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
                            foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
                                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
                            }
                        }

                        if ( ! empty( $args['validate'] ) ) {
                            foreach ( $args['validate'] as $validate ) {
                                $args['class'][] = 'validate-' . $validate;
                            }
                        }

                        $field           = '';
                        $label_id        = $args['id'] . '_' . current( array_keys( $args['options'] ) );
                        $sort            = $args['priority'] ? $args['priority'] : '';
                        $field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';

                        if ( ! empty( $args['options'] ) ) {
                            foreach ( $args['options'] as $option_key => $option_text ) {
                                $checked_value = in_array( $option_key, $value ) ? $option_key : ''; //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
                                $field        .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="checkbox ' . implode( ' ', $args['label_class'] ) . '" ' . implode( ' ', $custom_attributes ) . '>
                                                    <input type="checkbox" class="input-checkbox ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" value="' . esc_attr( $option_key ) . '" ' . checked( $checked_value, $option_key, false ) . ' /> ' . $option_text . '</label>';
                            }
                        }

                        if ( ! empty( $field ) ) {
                            $field_html  = '<label for="' . esc_attr( $label_id ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . wp_kses_post( $args['label'] ) . $required . '</label>';
                            $field_html .= '<span class="woocommerce-input-wrapper">' . $field;

                            if ( $args['description'] ) {
                                $field_html .= '<span class="description" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
                            }

                            $field_html .= '</span>';

                            $container_class = esc_attr( implode( ' ', $args['class'] ) );
                            $container_id    = esc_attr( $args['id'] ) . '_field';
                            $field           = sprintf( $field_container, $container_class, $container_id, $field_html );
                        }
                    }
                }
            }

            return $field;
        }

        /**
         * Set default value for custom fields.
         * WooCommerce checkbox form fields only support string values, so we need to set the default value to a string.
         *
         * @since 1.17.4
         * @access public
         *
         * @param string $value Value of the input we want to modify.
         * @param string $key   Name of the input we want to grab data for. e.g. wwlc_cf_vat.
         * @return string
         */
        public function wwlc_set_default_value_for_checkbox_custom_fields( $value, $key ) {
            $user_wholesale_role = wwlc_get_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {
                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
                $custom_fields = (array) $custom_fields;
                $custom_fields = array_filter( $custom_fields );

                if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
                    if ( array_key_exists( $key, $custom_fields ) ) {
                        if ( 'checkbox' === $custom_fields[ $key ]['field_type'] ) {
                            $customer_object = false;

                            if ( is_user_logged_in() ) {
                                $customer_object = new WC_Customer( get_current_user_id(), true );
                            }

                            if ( ! $customer_object ) {
                                $customer_object = WC()->customer;
                            }

                            if ( is_callable( array( $customer_object, "get_$key" ) ) ) {
                                $meta_value = $customer_object->{"get_$key"}();
                            } elseif ( $customer_object->meta_exists( $key ) ) {
                                $meta_value = $customer_object->get_meta( $key, true );
                            }

                            $value = ! empty( $meta_value ) ? implode( ',', $meta_value ) : '';
                        }
                    }
                }
            }

            return $value;
        }

        /**
         * Add custom fields to the my account page.
         *
         * @since 1.6.3
         * @access public
         *
         * @return void
         */
        public function wwlc_account_details_fields() {
            $user_id                 = get_current_user_id();
            $wwlc_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

            if ( ! empty( $wwlc_form_custom_fields ) ) {
                wp_nonce_field( 'wwlc_update_user', 'wwlc_update_user_nonce_field' );

                foreach ( $wwlc_form_custom_fields as $wwlc_form_custom_field_key => $wwlc_form_custom_field ) {
                    if ( '1' === $wwlc_form_custom_field['enabled'] && 'file' === $wwlc_form_custom_field['field_type'] ) {

                        $file_url  = esc_url( get_user_meta( $user_id, $wwlc_form_custom_field_key, true ) );
                        $file_tmp  = explode( '/', $file_url );
                        $file_name = end( $file_tmp );
                    ?>
                    <div class="wwlc-account-details-field">
                        <label for="<?php echo esc_attr( $wwlc_form_custom_field_key ); ?>"><?php echo esc_html( $wwlc_form_custom_field['field_name'] ); ?></label>
                        <div class="wwlc-account-details-field-file">
                            <input type="hidden" name="<?php echo esc_attr( $wwlc_form_custom_field_key ); ?>" id="<?php echo esc_attr( $wwlc_form_custom_field_key ); ?>" value="<?php echo esc_html( $file_name ); ?>" />
                            <input name="file" type="file" data-field="<?php echo esc_attr( $wwlc_form_custom_field_key ); ?>">
                            <span class="wwlc-loader"></span>
                        </div>
                        <a href="<?php echo esc_url( $file_url ); ?>" target="_blank"><?php echo esc_html( $file_name ); ?></a>
                    </div>
                    <?php
                    }
                }
            }
        }

        /**
         * Save custom fields to the my account page.
         *
         * @since 1.6.3
         * @access public
         *
         * @param int $user_id User ID.
         *
         * @return void
         */
        public function wwlc_save_account_details_fields( $user_id ) {
            $wwlc_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            if ( ! empty( $wwlc_form_custom_fields ) ) {
                foreach ( $wwlc_form_custom_fields as $wwlc_form_custom_field_key => $wwlc_form_custom_field ) {

                    // Check if the field is enabled and is a file type.
                    if ( '1' === $wwlc_form_custom_field['enabled'] && 'file' === $wwlc_form_custom_field['field_type'] ) {
                        update_user_meta( $user_id, $wwlc_form_custom_field_key, $_POST[ $wwlc_form_custom_field_key ] ); // phpcs:ignore.
                    }
                }

                // Transfer files from temp folder to user folder.
                $this->_wwlc_user_account->_move_user_files_to_permanent( $user_id );
            }
        }

        /**
         * Execute model.
         *
         * @since 1.6.3
         * @access public
         */
        public function run() {

            // Custom Row Action UI.
            add_filter( 'user_row_actions', array( $this, 'wwlc_add_user_list_custom_row_action_ui' ), 10, 2 );

            // Custom Admin Notices Related To WWLC Actions.
            add_action( 'admin_notices', array( $this, 'wwlc_custom_submissions_bulk_action_notices' ) );

            // Add Custom Bulk Action Options On Actions Select Box. Done Via JS.
            add_action( 'admin_footer-users.php', array( $this, 'wwlc_custom_user_listing_bulk_action_footer_js' ) );

            // Add Custom Bulk Action.
            add_action( 'load-users.php', array( $this, 'wwlc_custom_user_listing_bulk_action' ) );

            // Show Approve, Reject, Activate and Deactivate buttons on user edit screen.
            add_action( 'admin_notices', array( $this, 'wwlc_show_user_management_buttons_in_user_edit_screen' ), 100 );
            add_action( 'admin_footer', array( $this, 'wwlc_loading_screen_for_request_request' ), 100 );

            // Add Custom Column To User Listing Page.
            add_filter( 'manage_users_columns', array( $this, 'wwlc_add_user_listing_custom_column' ) );

            // Add Content To Custom Column On User Listing Page.
            add_filter( 'manage_users_custom_column', array( $this, 'wwlc_add_user_listing_custom_column_content' ), 10, 3 );

            // Add Custom Fields To Admin User Edit Page.
            add_action( 'show_user_profile', array( $this, 'wwlc_display_custom_fields_on_user_admin_page' ) );
            add_action( 'edit_user_profile', array( $this, 'wwlc_display_custom_fields_on_user_admin_page' ) );

            // Save Custom Fields On Admin User Edit Page.
            add_action( 'personal_options_update', array( $this, 'wwlc_save_custom_fields_on_user_admin_page' ) );
            add_action( 'edit_user_profile_update', array( $this, 'wwlc_save_custom_fields_on_user_admin_page' ) );

            // Blacklist custom field from wpml string translation.
            add_filter( 'alloptions', array( $this, 'wwlc_blacklist_custom_field_from_wpml_string_translation' ) );

            // Show custom fields on checkout page.
            add_filter( 'woocommerce_checkout_fields', array( $this, 'wwlc_show_custom_fields_on_checkout' ) );

            // Save custom fields on checkout order processed.
            add_filter( 'woocommerce_checkout_posted_data', array( $this, 'add_custom_fields_to_checkout_posted_data' ), 10, 1 );
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'wwlc_save_custom_fields_and_add_order_meta_after_create_order' ), 10, 2 );

            // Show custom fields data to the order details.
            add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'wwlc_show_custom_fields_on_order_details' ) );

            // Modify woocommerce form field checkbox output for custom fields.
            add_action( 'woocommerce_form_field_checkbox', array( $this, 'wwlc_custom_field_checkbox_on_checkout_page' ), 10, 4 );

            // Set default value for checkbox custom fields.
            add_filter( 'woocommerce_checkout_get_value', array( $this, 'wwlc_set_default_value_for_checkbox_custom_fields' ), 10, 2 );
        }
    }
}
