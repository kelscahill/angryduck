<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WWLC_AJAX' ) ) {
	/**
	 * Model that houses the logic of lead capture ajax.
	 *
	 * @since 1.0.0
	 */
	class WWLC_AJAX {
		/**
         * Class Properties
         */

		/**
		 * Property that holds the single main instance of WWLC_AJAX.
		 *
		 * @since 1.6.3
		 * @access private
		 * @var WWLC_AJAX
		 */
		private static $_instance;

		/**
		 * Model that houses the logic of retrieving information relating to User Accounts.
		 *
		 * @since 1.6.5
		 * @access private
		 * @var WWLC_Bootstrap
		 */
		private $wwlc_bootstrap;

		/**
		 * Model that houses the logic of retrieving information relating to User Accounts.
		 *
		 * @since 1.6.3
		 * @access private
		 * @var WWLC_User_Account
		 */
		private $wwlc_user_account;

		/**
		 * Model that houses the logic of retrieving information relating to Emails.
		 *
		 * @since 1.6.3
		 * @access private
		 * @var WWLC_Emails
		 */
		private $wwlc_emails;

		/**
		 * Model that houses the logic of retrieving information relating to Forms.
		 *
		 * @since 1.6.3
		 * @access private
		 * @var WWLC_Forms
		 */
		private $wwlc_forms;

		/**
		 * Model that houses the logic of retrieving information relating to Registration Custom Fields.
		 *
		 * @since 1.6.3
		 * @access private
		 * @var WWLC_Registration_Form_Custom_Fields
		 */
		private $wwlc_registration_form_custom_fields;

		/**
		 * Constant Data for BYTES
		 */
		const BYTES_PER_MB = 1000000;

		/**
		 * Constant Data for Max File Size
		 */
		const DEFAULT_MAX_FILE_SIZE = 10000000;


		/**
		 * Class Models
		 */

		/**
		 * WWLC_AJAX constructor.
		 *
		 * @param array $dependencies Array of instance objects of all dependencies of WWLC_AJAX model.
		 *
		 * @since 1.6.3
		 */
		public function __construct( $dependencies ) {

			$this->wwlc_bootstrap                       = $dependencies['WWLC_Bootstrap'];
			$this->wwlc_user_account                    = $dependencies['WWLC_User_Account'];
			$this->wwlc_emails                          = $dependencies['WWLC_Emails'];
			$this->wwlc_forms                           = $dependencies['WWLC_Forms'];
			$this->wwlc_registration_form_custom_fields = $dependencies['WWLC_Registration_Form_Custom_Fields'];
		}

		/**
		 * Singleton Pattern.
		 *
		 * @param array $dependencies Array of instance objects of all dependencies of WWLC_AJAX model.
		 *
		 * @return WWLC_AJAX
		 * @since 1.0.0
		 * @since 1.6.3 Code refactor
		 */
		public static function instance( $dependencies = null ) {

			if ( ! self::$_instance instanceof self ) {
				self::$_instance = new self( $dependencies );
			}

			return self::$_instance;
		}

		/**
		 * Create user ajax interface.
		 *
		 * @param array $user_data Array of user data.
		 *
		 * @return array
		 * @since 1.0.0
		 * @since 1.6.2 Part of WWLC-117. Refactor code: modify parameter.
		 * @since 2.0.2 Address security issues by sanitizing and validating user data.
		 */
		public function wwlc_create_user( $user_data = array() ) {
			if ( empty( $user_data ) && wp_doing_ajax() && isset( $_POST['wwlc_register_user_nonce_field'] ) && check_ajax_referer( 'wwlc_register_user', 'wwlc_register_user_nonce_field' ) ) {
				// Sanitize and validate user data from $_POST.
                $user_data = wwlc_sanitize_user_data( $_POST['user_data'], 'ajax' );
			}

			return $this->wwlc_user_account->wwlc_create_user( $user_data, $this->wwlc_emails );
		}

		/**
		 * Approve user ajax interface.
		 *
		 * @param int|null $userID User ID.
		 * @param string   $page   Post page.
		 *
		 * @return bool
		 * @since 1.0.0
		 * @since 2.0.2 - Validate and sanitize userID and page.
		 */
		public function wwlc_approve_user( $userID = null, $page = null ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'wwlc_manage_leads', '_wpnonce' );

				if ( ! current_user_can( 'edit_users' ) ) {
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'woocommerce-wholesale-lead-capture' ) ) );
				}

				$userID = isset( $_POST['userID'] ) ? intval( $_POST['userID'] ) : null; // phpcs:ignore.
				$page   = isset( $_POST['page'] ) ? sanitize_text_field( $_POST['page'] ) : null; // phpcs:ignore.
			}

			if ( 'listings' === $page ) {
				$redirect_url = admin_url( 'users.php?users_approved=1' );
			} else {
				$redirect_url = admin_url( 'user-edit.php?user_id=' . $userID );
            }

			$this->wwlc_user_account->wwlc_approve_user( array( 'userID' => $userID ) );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				header( 'Content-Type: application/json' ); // specify we return json.
				echo wp_json_encode(
                    array(
						'status'       => 'success',
						'redirect_url' => $redirect_url,
                    )
                );
				die();

			} else {
				return true;
            }
		}

		/**
		 * Reject user ajax interface.
		 *
		 * @param int|null $userID User ID.
		 * @param string   $page   Post page.
		 *
		 * @return bool
		 * @since 1.0.0
		 * @since 2.0.2 - Validate and sanitize userID and page.
		 */
		public function wwlc_reject_user( $userID = null, $page = null ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'wwlc_manage_leads', '_wpnonce' );

				if ( ! current_user_can( 'edit_users' ) ) {
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'woocommerce-wholesale-lead-capture' ) ) );
				}

				$userID = isset( $_POST['userID'] ) ? intval( $_POST['userID'] ) : null; // phpcs:ignore.
				$page   = isset( $_POST['page'] ) ? sanitize_text_field( $_POST['page'] ) : null; // phpcs:ignore.
			}

			if ( 'listings' === $page ) {
				$redirect_url = admin_url( 'users.php?users_rejected=1' );
			} else {
				$redirect_url = admin_url( 'users.php' );
            }

			$this->wwlc_user_account->wwlc_reject_user( array( 'userID' => $userID ) );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				header( 'Content-Type: application/json' ); // specify we return json.
				echo wp_json_encode(
                    array(
						'status'       => 'success',
						'redirect_url' => $redirect_url,
                    )
                );
				die();

			} else {
				return true;
            }
		}

		/**
		 * Activate user ajax interface.
		 *
		 * @param int|null $userID User ID.
		 * @param string   $page   Post page.
		 *
		 * @return bool
		 * @since 1.0.0
		 */
		public function wwlc_activate_user( $userID = null, $page = null ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'wwlc_manage_leads', '_wpnonce' );

				if ( ! current_user_can( 'edit_users' ) ) {
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'woocommerce-wholesale-lead-capture' ) ) );
				}

				$userID = isset( $_POST['userID'] ) ? intval( $_POST['userID'] ) : null; // phpcs:ignore.
				$page   = isset( $_POST['page'] ) ? sanitize_text_field( $_POST['page'] ) : null; // phpcs:ignore.
			}

			if ( 'listings' === $page ) {
				$redirect_url = admin_url( 'users.php?users_activated=1' );
			} else {
				$redirect_url = admin_url( 'user-edit.php?user_id=' . $userID );
            }

			$this->wwlc_user_account->wwlc_activate_user( array( 'userID' => $userID ) );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				header( 'Content-Type: application/json' ); // specify we return json.
				echo wp_json_encode(
                    array(
						'status'       => 'success',
						'redirect_url' => $redirect_url,
                    )
                );
				die();

			} else {
				return true;
            }
		}

		/**
		 * Deactivate user ajax interface.
		 *
		 * @param int|null $userID User ID.
		 * @param string   $page   Post page.
		 *
		 * @return bool
		 * @since 1.0.0
		 */
		public function wwlc_deactivate_user( $userID = null, $page = null ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				check_ajax_referer( 'wwlc_manage_leads', '_wpnonce' );

				if ( ! current_user_can( 'edit_users' ) ) {
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'woocommerce-wholesale-lead-capture' ) ) );
				}

				$userID = isset( $_POST['userID'] ) ? intval( $_POST['userID'] ) : null; // phpcs:ignore.
				$page   = isset( $_POST['page'] ) ? sanitize_text_field( $_POST['page'] ) : null; // phpcs:ignore.
			}

			if ( 'listings' === $page ) {
				$redirect_url = admin_url( 'users.php?users_deactivated=1' );
			} else {
				$redirect_url = admin_url( 'user-edit.php?user_id=' . $userID );
            }

			$this->wwlc_user_account->wwlc_deactivate_user( array( 'userID' => $userID ) );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				header( 'Content-Type: application/json' ); // specify we return json.
				echo wp_json_encode(
                    array(
						'status'       => 'success',
						'redirect_url' => $redirect_url,
                    )
                );
				die();

			} else {
				return true;
            }
		}

		/**
		 * Get states by country code.
		 *
		 * @param null $cc Country code.
		 *
		 * @return mixed
		 * @since 1.4.0
		 */
		public function wwlc_get_states( $cc = null ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				// This endpoint is shared by frontend forms (wwlc_form_nonce) and
				// backend custom fields page (wwlc_custom_fields_nonce). Verify whichever is present.
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( ! wp_verify_nonce( $nonce, 'wwlc_form_nonce' ) && ! wp_verify_nonce( $nonce, 'wwlc_custom_fields_nonce' ) ) {
					wp_send_json_error( array( 'message' => __( 'Security check failed.', 'woocommerce-wholesale-lead-capture' ) ) );
				}
			}

			return $this->wwlc_user_account->get_states( $cc );
		}

		/**
		 * Registration form file upload handler
		 *
		 * @return mixed
		 * @since 1.6.0
		 * @since 1.6.3 WWLC-152 : Bypass mime type check so we can avoid doing upload_mimes filter else it will show this notice
		 * "Sorry, this file type is not permitted for security reasons."
		 */
		public function wwlc_file_upload_handler() {

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
            }

			if ( ! check_ajax_referer( 'wwlc_file_upload_nonce', 'nonce', false ) ) {
				header( 'Content-Type: application/json' );
				echo wp_json_encode(
					array(
						'status'  => 'fail',
						'message' => __( 'Security check failed. Please refresh the page and try again.', 'woocommerce-wholesale-lead-capture' ),
					)
				);
				die();
			}

			if ( ! isset( $_FILES['uploaded_file'] ) || UPLOAD_ERR_OK !== $_FILES['uploaded_file']['error'] ) {
				wp_send_json_error( array( 'message' => __( 'File upload failed', 'woocommerce-wholesale-lead-capture' ) ) );
			}

			if ( ! $this->wwlc_registration_form_custom_fields ) { // if the form configurations does not exist, throw error.
				wp_send_json_error( array( 'message' => __( 'Form field configuration error', 'woocommerce-wholesale-lead-capture' ) ) );
			}

			$field_id                    = isset( $_REQUEST['field_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['field_id'] ) ) : '';
			$registration_field_settings = $this->wwlc_registration_form_custom_fields->wwlc_get_custom_field_by_id( $field_id, false ) ?? array(); // this is called here to get the DB stored form settings.
			$db_allowed_file_types       = isset( $registration_field_settings['field_allowed_filetypes'] ) && ! empty( $registration_field_settings['field_allowed_filetypes'] ) ? array_map( 'trim', explode( ',', $registration_field_settings['field_allowed_filetypes'] ) ) : array(); // db stored filetypes.
			$db_max_allowed_file_size    = ! empty( $registration_field_settings['max_allowed_file_size'] ) ? ( (int) $registration_field_settings['max_allowed_file_size'] * self::BYTES_PER_MB ) : self::DEFAULT_MAX_FILE_SIZE;
			$uploaded_file               = isset( $_FILES['uploaded_file'] ) ? $_FILES['uploaded_file'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Use only DB-stored file settings; client-side values are not trusted.
			$file_settings = array(
				'allowed_file_types'    => $db_allowed_file_types,
				'max_allowed_file_size' => $db_max_allowed_file_size,
			);

			$temp      = explode( '.', $uploaded_file['name'] );
			$ext       = end( $temp );
			$error_msg = '';

			// Server-side MIME type verification using WordPress file type check.
			$file_check    = wp_check_filetype_and_ext( $uploaded_file['tmp_name'], $uploaded_file['name'] );
			$verified_ext  = $file_check['ext'];
			$verified_type = $file_check['type'];

			if ( ! $verified_ext || ! $verified_type ) {
				$error_msg = __( 'The format of the file you selected is not supported', 'woocommerce-wholesale-lead-capture' );
			} elseif ( ! in_array( $verified_ext, $file_settings['allowed_file_types'], true ) ) {
				// Enforce restriction of allowed filetypes using verified extension.
				$error_msg = __( 'The format of the file you selected is not supported', 'woocommerce-wholesale-lead-capture' );

			} elseif ( $uploaded_file['size'] > (int) $file_settings['max_allowed_file_size'] ) {

				$error_msg = __( 'The file you selected exceeds the maximum allowed file size', 'woocommerce-wholesale-lead-capture' );

			}

			if ( $error_msg ) {

				$response = array(
					'status'  => 'fail',
					'message' => $error_msg,
				);

				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

					header( 'Content-Type: application/json' );
					echo wp_json_encode( $response );
					die();

				} else {
					return $response;
                }
			}

			// Generate unique number and add to filename using the verified extension.
			$uploaded_file['name'] = str_replace( '.' . $ext, '', sanitize_file_name( $uploaded_file['name'] ) ) . '-' . time() . '.' . $verified_ext;

			$upload_overrides = array(
				'test_form' => false,   // Turn off to avoid 'Invalid form submission.
			);

			// Set temp upload directory for wwlc file upload.
			add_filter( 'upload_dir', array( $this->wwlc_bootstrap, 'wwlc_set_temp_directory' ) );

			// Perform file upload.
			$file     = wp_handle_upload( $uploaded_file, $upload_overrides );
			$file_url = isset( $file['url'] ) ? $file['url'] : '';
			$file_url = explode( '/', $file_url );

			if ( $file && ! isset( $file['error'] ) ) {

				$response = array(
					'status'    => 'success',
					'file_name' => end( $file_url ),
				);

			} else {

				$response = array(
					'status'  => 'fail',
					'message' => $file['error'],
				);

			}

			// Remove filter that sets temp upload directory.
			remove_filter( 'upload_dir', array( $this->wwlc_bootstrap, 'wwlc_set_temp_directory' ) );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

					header( 'Content-Type: application/json' );
					echo wp_json_encode( $response );
					die();

			} else {
				return $response;
            }
		}

		/**
		 * Get allowed file types for upload
		 *
		 * @param string $field_id Field ID.
		 *
		 * @return array
		 * @since 1.6.0
		 */
		public function wwlc_get_allowed_file_settings( $field_id ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( ! wp_verify_nonce( $nonce, 'wwlc_form_nonce' )
					&& ! wp_verify_nonce( $nonce, 'wwlc_update_user' )
					&& ! wp_verify_nonce( $nonce, 'wwlc_manage_leads' ) ) {
					wp_send_json_error( array( 'message' => __( 'Security check failed.', 'woocommerce-wholesale-lead-capture' ) ), 403 );
				}

				$field_id = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';
            }

			$file_field = $this->wwlc_registration_form_custom_fields->wwlc_get_custom_field_by_id( $field_id, false );

			if ( empty( $file_field ) || ! isset( $file_field['field_allowed_filetypes'] ) ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					wp_send_json_error( array( 'message' => __( 'Invalid file field configuration.', 'woocommerce-wholesale-lead-capture' ) ), 403 );
				}
				return array();
			}

			// remove spaces if there are any.
			$allowed_file_types = str_replace( ' ', '', $file_field['field_allowed_filetypes'] );

			// convert string to array.
			$allowed_file_types = explode( ',', $allowed_file_types );

			$response = array(
				'allowed_file_types'    => $allowed_file_types,
				'max_allowed_file_size' => floatval( $file_field['max_allowed_file_size'] ) * 1000000,
			);

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

				header( 'Content-Type: application/json' );
				echo wp_json_encode( $response );
				die();

			} else {
				return $response;
            }
		}

		/**
		 * AJAX Custom content wp_editor field.
		 *
		 * @since 1.7.0
		 * @access public
		 */
		public function wwlc_content_custom_wp_editor_field() {
			check_ajax_referer( 'wwlc_custom_fields_nonce', '_wpnonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( -1, 403 );
			}

			// force wp_editor to always show first the "visual" tab.
			add_filter(
                'wp_default_editor',
                function( $editor ) { // phpcs:ignore.
                return 'tinymce';
                }
            );

			$content  = isset( $_REQUEST['content'] ) ? wp_kses_post( wp_unslash( $_REQUEST['content'] ) ) : ''; // phpcs:ignore.
			$settings = array(
				'wpautop'       => true,
				'media_buttons' => false,
				'textarea_name' => 'ecwp_email_content_template',
				'textarea_rows' => get_option( 'default_post_edit_rows', 10 ),
				'tabindex'      => '',
				'editor_css'    => '',
				'editor_class'  => '',
				'teeny'         => true,
				'dfw'           => false,
				'quicktags'     => true,
				'tinymce'       => array( 'theme_advanced_disable' => 'bold,italic,underline' ),
			);

			// cleanup backlashes from escape (needed to support attributes).
			$content = stripslashes( $content );

			wp_editor( $content, 'wwlc_cf_field_default_value', $settings );
			\_WP_Editors::enqueue_scripts();
			print_footer_scripts();
			\_WP_Editors::editor_js();

			wp_die();
		}

		/**
		 * AJAX handler for removing a user's uploaded file.
		 *
		 * @since 2.0.3.2
		 * @access public
		 */
		public function wwlc_file_remove_handler() {
			check_ajax_referer( 'wwlc_file_remove_nonce', '_wpnonce' );

			if ( ! current_user_can( 'edit_users' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'woocommerce-wholesale-lead-capture' ) ) );
			}

			$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( wp_unslash( $_POST['field_name'] ) ) : '';
			$user_id    = isset( $_POST['userid'] ) ? intval( $_POST['userid'] ) : 0;

			if ( empty( $field_name ) || empty( $user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid request parameters.', 'woocommerce-wholesale-lead-capture' ) ) );
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				wp_send_json_error( array( 'message' => __( 'User not found.', 'woocommerce-wholesale-lead-capture' ) ) );
			}

			$filename = $this->wwlc_user_account->_get_user_file_name( $user_id, $field_name );

			if ( ! empty( $filename ) ) {
				$this->wwlc_user_account->_delete_user_file( $user_id, $filename );
			}

			delete_user_meta( $user_id, $field_name );

			wp_send_json_success( array( 'message' => __( 'File removed successfully.', 'woocommerce-wholesale-lead-capture' ) ) );
		}

		/**
		 * Execute model.
		 *
		 * @since 1.6.3
		 * @access public
		 */
		public function run() {

			// Note: You have to register your ajax interface to both wp_ajax_ and wp_ajax_nopriv_ if you want it to be
			// accessible to both logged in and unauthenticated users.

			// Authenticated user "ONLY" AJAX interfaces.
			add_action( 'wp_ajax_wwlc_create_user', array( $this, 'wwlc_create_user' ), 10, 3 );
			add_action( 'wp_ajax_wwlc_approve_user', array( $this, 'wwlc_approve_user' ) );
			add_action( 'wp_ajax_wwlc_reject_user', array( $this, 'wwlc_reject_user' ) );
			add_action( 'wp_ajax_wwlc_activate_user', array( $this, 'wwlc_activate_user' ) );
			add_action( 'wp_ajax_wwlc_deactivate_user', array( $this, 'wwlc_deactivate_user' ) );

			add_action( 'wp_ajax_wwlc_create_lead_pages', array( $this->wwlc_forms, 'wwlc_create_lead_pages' ) ); // <-- target properly

			add_action( 'wp_ajax_wwlc_add_registration_form_custom_field', array( $this->wwlc_registration_form_custom_fields, 'wwlc_add_registration_form_custom_field' ) );
			add_action( 'wp_ajax_wwlc_edit_registration_form_custom_field', array( $this->wwlc_registration_form_custom_fields, 'wwlc_edit_registration_form_custom_field' ) );
			add_action( 'wp_ajax_wwlc_delete_registration_form_custom_field', array( $this->wwlc_registration_form_custom_fields, 'wwlc_delete_registration_form_custom_field' ) );
			add_action( 'wp_ajax_wwlc_get_custom_field_by_id', array( $this->wwlc_registration_form_custom_fields, 'wwlc_get_custom_field_by_id' ) );

			add_action( 'wp_ajax_wwlc_get_states', array( $this, 'wwlc_get_states' ) );

			add_action( 'wp_ajax_wwlc_file_upload_handler', array( $this, 'wwlc_file_upload_handler' ) );
			add_action( 'wp_ajax_wwlc_get_allowed_file_settings', array( $this, 'wwlc_get_allowed_file_settings' ) );
			add_action( 'wp_ajax_wwlc_file_remove_handler', array( $this, 'wwlc_file_remove_handler' ) );

			add_action( 'wp_ajax_wwlc_content_wp_editor', array( $this, 'wwlc_content_custom_wp_editor_field' ) );

			// Unauthenticated user 'ONLY' AJAX interfaces.
			add_action( 'wp_ajax_nopriv_wwlc_create_user', array( $this, 'wwlc_create_user' ), 10, 3 );

			add_action( 'wp_ajax_nopriv_wwlc_get_states', array( $this, 'wwlc_get_states' ) );

			add_action( 'wp_ajax_nopriv_wwlc_file_upload_handler', array( $this, 'wwlc_file_upload_handler' ) );
			add_action( 'wp_ajax_nopriv_wwlc_get_allowed_file_settings', array( $this, 'wwlc_get_allowed_file_settings' ) );
		}
	}

}
