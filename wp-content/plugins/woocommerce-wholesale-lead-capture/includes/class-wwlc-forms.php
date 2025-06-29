<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Forms' ) ) {
    /**
     * Model that houses the logic of handling form field type filters.
     *
     * @since 1.17.5
     */
    class WWLC_Forms {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWLC_Forms.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Forms
         */
        private static $_instance;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWLC_Forms constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Forms model.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWLC_Forms is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Forms model.
         *
         * @return WWLC_Forms
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Return formatted custom fields. ( Abide to the formatting of existing fields ).
         *
         * @return array
         *
         * @since 1.1.0
         * @since 1.6.3 Insert default value in the array.
         *                 Made the function access method from private to public since we need this function called in WWLC_Shortcode class
         */
        public function _get_formatted_custom_fields() {

            $registration_form_custom_fields           = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );
            $formatted_registration_form_custom_fields = array();

            foreach ( $registration_form_custom_fields as $field_id => $custom_field ) {

                $formatted_registration_form_custom_fields[] = array(
                    'label'         => $custom_field['field_name'],
                    'name'          => $field_id,
                    'id'            => $field_id,
                    'class'         => 'wwlc_registration_field form_field wwlc_custom_field ' . $custom_field['field_type'] . '_wwlc_custom_field',
                    'type'          => $custom_field['field_type'],
                    'required'      => ( '1' === $custom_field['required'] ) ? true : false,
                    'custom_field'  => true,
                    'active'        => ( '1' === $custom_field['enabled'] ) ? true : false,
                    'validation'    => array(),
                    'field_order'   => $custom_field['field_order'],
                    'attributes'    => isset( $custom_field['attributes'] ) ? $custom_field['attributes'] : array(),
                    'options'       => isset( $custom_field['options'] ) ? $custom_field['options'] : array(),
                    'placeholder'   => isset( $custom_field['field_placeholder'] ) ? $custom_field['field_placeholder'] : '',
                    'default_value' => isset( $custom_field['default_value'] ) ? $custom_field['default_value'] : '',
                );

            }

            return $formatted_registration_form_custom_fields;
        }

        /**
         * Retrives all file custom fields registed on the settings.
         * Note: this can be converted to retrieve any type of custom fields.
         *
         * @return array
         *
         * @since 1.6.0
         */
        public function wwlc_get_file_custom_fields() {

            $custom_fields = $this->_get_formatted_custom_fields();

            if ( ! is_array( $custom_fields ) ) {
                return;
            }

            $file_fields = array();

            foreach ( $custom_fields as $key => $field ) {

                if ( 'file' === $field['type'] ) {
                    $file_fields[] = $field;
                }
            }

            return $file_fields;
        }

        /**
         * Returns the recaptcha field.
         *
         * @since 1.6.0
         * @since 1.6.3 Made the function access method from private to public since we need this function called in WWLC_Shortcode class
         */
        public function _get_recaptcha_field() {

            if ( 'yes' !== get_option( 'wwlc_security_enable_recaptcha' ) ) {
                return array();
            }

            $recaptcha_field = array(
                'type'        => 'recaptcha',
                'id'          => 'recaptcha_field',
                'active'      => true,
                'required'    => true,
                'field_order' => 99999999,
            );

            return array( $recaptcha_field );
        }

        /**
         * Usort callback for sorting associative arrays.
         * Used for sorting field ordering on the form. (Registration form).
         *
         * @param array $arr1 First array to compare.
         * @param array $arr2 Second array to compare.
         * @return int
         *
         * @since 1.1.0
         */
        public function _usort_callback( $arr1, $arr2 ) {

            if ( $arr1['field_order'] === $arr2['field_order'] ) {
                return 0;
            }

            return ( $arr1['field_order'] < $arr2['field_order'] ) ? -1 : 1;
        }

        /**
         * Get field label markup.
         *
         * @deprecated 1.7.0
         *
         * @param array $field Field data.
         *
         * @return void
         * @since 1.0.0
         */
        public function wwlc_get_label( $field ) { // phpcs:ignore.

            wwlc_deprecated_function( debug_backtrace(), 'WWLC_Forms::wwlc_get_label', '1.7.0', 'woocommerce_form_field' ); // phpcs:ignore.
        }

        /**
         * Get field markup.
         *
         * @deprecated 1.7.0
         *
         * @param array $field Field data.
         *
         * @return string
         * @since 1.0.0
         */
        public function wwlc_get_field( $field ) {

            wwlc_deprecated_function( debug_backtrace(), 'WWLC_Forms::wwlc_get_field', '1.7.0', 'woocommerce_form_field' ); // phpcs:ignore.

            ob_start();
            $this->wwlc_form_field( $field );
            return ob_get_clean();
        }

        /**
         * Display WWLC form field.
         *
         * @deprecated 1.7.0
         *
         * @param array $field Field data.
         *
         * @return void
         * @since 1.0.0
         * @since 2.0.1 Added support for content and terms_conditions fields.
         */
        public function wwlc_form_field( $field ) {

            if ( ! $this->wwlc_is_field_active( $field ) ) {
                return;
            }

            switch ( $field['type'] ) {

                case 'content':
                    echo '<div id="' . esc_attr( $field['id'] ) . '" class="content-field-wrap">';
                    echo wp_kses_post( $field['default_value'] );
                    echo '</div>';
                    break;

                case 'terms_conditions':
                    $show_text            = __( 'show', 'woocommerce-wholesale-lead-capture' );
                    $hide_text            = __( 'hide', 'woocommerce-wholesale-lead-capture' );
                    $field['label']      .= ' (<a class="show-hide" href="#" data-hide=' . $hide_text . '>' . $show_text . '</a>)';
                    $field['required']    = true;
                    $field['label_class'] = array( 'checkbox_options_holder', 'terms_conditions_checkbox' );

                    woocommerce_form_field( $field['id'], $this->wwlc_reformat_field_args( $field ) );

                    echo '<div id="' . esc_attr( $field['id'] ) . '-content" class="terms-conditions-wrap" style="display:none;">';
                    echo wp_kses_post( $field['default_value'] );
                    echo '</div>';
                    break;

                default:
                    woocommerce_form_field( $field['id'], $this->wwlc_reformat_field_args( $field ) );
                    break;
            }
        }

        /**
         * Get registration terms and privacy texts.
         *
         * @return string
         */
        public function wwlc_get_form_terms_privacy() {

            $field_html = '';
            if ( 'yes' === get_option( 'wwlc_general_show_terms_and_conditions' ) ) {

                $terms_privacy_text  = get_option( 'wwlc_terms_privacy_text' );
                $terms_and_condition = '';
                $privacy_policy      = '';

                if ( empty( $terms_privacy_text ) ) {
                    $terms_and_condition = apply_filters( 'wwlc_filter_terms_and_condition_text', $terms_and_condition );
                    $privacy_policy      = apply_filters( 'wwlc_filter_privacy_policy_text', $privacy_policy );

                    $concatenator       = ( ! empty( $terms_and_condition ) && ! empty( $privacy_policy ) ) ? ' and ' : '';
                    $terms_privacy_text = '<p class="terms-and-conditions">By clicking register, you agree to the ' . $terms_and_condition . $concatenator . $privacy_policy . '</p>';
                }

                $field_html .= '<div class="form-row field-set wwlc_registration_field form_field validate-required terms_privacy-field">';
                $field_html .= '<label class="checkbox checkbox_options_holder terms_conditions_checkbox default-terms-privacy-field" data-required="yes">';
                $field_html .= '<input type="checkbox" class="input-checkbox wwlc_form_field" name="terms_privacy_text" value="1" />';
                $field_html .= $terms_privacy_text . '</label>';
                $field_html .= '</div>';
            }

            return $field_html;
        }

        /**
         * Get registration form controls.
         *
         * @return string
         * @since 1.0.0
         * @since 1.8.0 Get page option url via wwlc_get_url_of_page_option function
         */
        public function wwlc_get_form_controls() {
            $register = '<p class="register-button-container"><input type="submit" name="submit" class="form-control button button-primary" id="wwlc-register" value="' . __( 'Register', 'woocommerce-wholesale-lead-capture' ) . '" ><span class="wwlc-loader"></span></p>';
            $register = apply_filters( 'wwlc_filter_registration_form_register_control', $register );

            $log_in = '<a class="form-control" id="log-in" href="' . wwlc_get_url_of_page_option( 'wwlc_general_login_page' ) . '" >' . __( 'Log In', 'woocommerce-wholesale-lead-capture' ) . '</a>';
            $log_in = apply_filters( 'wwlc_filter_registration_form_login_control', $log_in );

            $lost_password = '<a class="form-control" id="lost-password" href="' . wp_lostpassword_url() . '" >' . __( 'Lost Password', 'woocommerce-wholesale-lead-capture' ) . '</a>';
            $lost_password = apply_filters( 'wwlc_filter_registration_form_lost_password_control', $lost_password );

            return $register . $log_in . ' ' . $lost_password;
        }

        /**
         * Check if field is active.
         *
         * @param array $field Field data.
         *
         * @return mixed
         * @since 1.0.0
         */
        public function wwlc_is_field_active( $field ) {

            return $field['active'];
        }

        /**
         * Do registration form initialization. Adding nonces and honey pot.
         *
         * @since 1.0.0
         */
        public function wwlc_initialize_registration_form() {

            echo '<div class="woocommerce wwlc-form-error"></div>';

            echo '<form method="post" class="wwlc-register woocommerce-form woocommerce-form-register" id="registration_form">';

            // echo nonce fields.
            wp_nonce_field( 'wwlc_register_user', 'wwlc_register_user_nonce_field' );

            // echo honeypot fields.
            $honey_pot_fields = '<div style="display: none !important;" class="honeypot">' .
            '<label for="honeypot-field">' . __( 'Please Leave This Empty:', 'woocommerce-wholesale-lead-capture' ) . '</label>' .
                '<input type="text" id="honeypot-field" name="honeypot-field" val="">' .
                '</div>';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $honey_pot_fields;

            do_action( 'wwlc_initialize_registration_form' );
        }

        /**
         * End the registration form. Adding necesarry html endings and special fields.
         *
         * @param array $options Shortcode options.
         *
         * @since 1.7.0
         * @since 1.8.0 Pass array of shortcode options
         */
        public function wwlc_end_registration_form( $options ) {

            do_action( 'wwlc_end_registration_form', $options );

            if ( isset( $options['role'] ) ) {
                echo '<input class="input-hidden wwlc_form_field" type="hidden" name="wwlc_role" id="wwlc_role" value="' . esc_attr( $options['role'] ) . '">';
            }

            if ( ! empty( $options['autoApprove'] ) ) {
                echo '<input class="input-hidden wwlc_form_field" type="hidden" name="wwlc_auto_approve" id="wwlc_auto_approve" value="' . esc_attr( $options['autoApprove'] ) . '">';
            }

            if ( ! empty( $options['autoLogin'] ) ) {
                echo '<input class="input-hidden wwlc_form_field" type="hidden" name="wwlc_auto_login" id="wwlc_auto_login" value="' . esc_attr( $options['autoLogin'] ) . '">';
            }

            echo '</form>';
        }

        /**
         * Create lead pages. Necessary pages for the plugin to work correctly.
         *
         * @param null $dummy_arg Dummy argument.
         *
         * @return bool
         * @since 1.0.0
         * @since 1.10 Show an error that there pages in the trash that contains the login and registration shortcode
         */
        public function wwlc_create_lead_pages( $dummy_arg = null ) { // phpcs:ignore.

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

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                    wp_send_json(
                        array(
							'status'          => 'success',
							'wwlc_lead_pages' => $wwlc_lead_pages,
                        )
                    );

                } else {
                    return true;
                }
            } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                wp_send_json(
                    array(
                        'status'        => 'failed',
                        'error_message' => __( 'Error: There are pages in the Trash that contain the login/registration shortcodes. Please permanently delete those pages or restore them first.', 'woocommerce-wholesale-lead-capture' ),
                    )
                );

            } else {
                return false;
            }
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

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Fields display related functions.
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Reformat WWLC field args to be used for the woocommerce_form_field function.
         *
         * @since 1.7.0
         * @access public
         *
         * @param array $field WWLC field data.
         * @return array Filtered WWLC args to woocommerce_form_field args.
         */
        public function wwlc_reformat_field_args( $field ) {

            $field_id    = isset( $field['id'] ) ? $field['id'] : '';
            $type        = isset( $field['type'] ) ? $field['type'] : 'text';
            $class       = array( 'field-set', $type . '-field-set', $field_id );
            $options     = array();
            $label_class = isset( $field['label_class'] ) && is_array( $field['label_class'] ) ? $field['label_class'] : array();
            $input_class = isset( $field['input_class'] ) && is_array( $field['input_class'] ) ? $field['input_class'] : array();
            $attributes  = isset( $field['custom_attributes'] ) && is_array( 'custom_attributes' ) ? $field['custom_attributes'] : array();
            $validate    = isset( $field['validation'] ) && is_array( $field['validation'] ) ? $field['validation'] : array();

            // add form_field to input class variable.
            $input_class[] = 'wwlc_form_field';

            // change class string to array.
            if ( isset( $field['class'] ) ) {
                $class = array_merge( $class, is_array( $field['class'] ) ? $field['class'] : explode( ' ', $field['class'] ) );
            }

            // remap options array list.
            if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {

                foreach ( $field['options'] as $option ) {
                    $options[ $option['value'] ] = $option['text'];
                }
            }

            // change 'phone' type to 'tel'.
            if ( 'phone' === $type ) {

                $type                         = 'tel';
                $input_class[]                = 'phone';
                $attributes['data-phonemask'] = get_option( 'wwlc_fields_phone_mask_pattern', 'No format' );

            } elseif ( 'checkbox' === $type ) {
                $type = 'wwlc_checkbox';
            } elseif ( 'terms_conditions' === $type ) {
                $type = 'checkbox';
            } elseif ( 'number' === $type ) {

                if ( isset( $field['attributes']['min'] ) && $field['attributes']['min'] ) {
                    $attributes['min'] = intval( $field['attributes']['min'] );
                }

                if ( isset( $field['attributes']['max'] ) && $field['attributes']['max'] ) {
                    $attributes['max'] = intval( $field['attributes']['max'] );
                }

                if ( isset( $field['attributes']['step'] ) && $field['attributes']['step'] ) {
                    $attributes['step'] = intval( $field['attributes']['step'] );
                }
            }

            // add 'data-required' custom attribute.
            if ( isset( $field['required'] ) && $field['required'] ) {
                $attributes['data-required'] = 'yes';
            }

            return array(
                'type'              => $type,
                'label'             => isset( $field['label'] ) ? $field['label'] : '',
                'description'       => isset( $field['description'] ) ? $field['description'] : '',
                'placeholder'       => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
                'maxlength'         => isset( $field['maxlength'] ) ? $field['maxlength'] : false,
                'required'          => isset( $field['required'] ) ? $field['required'] : false,
                'autocomplete'      => isset( $field['autocomplete'] ) ? $field['autocomplete'] : false,
                'id'                => $field_id,
                'class'             => $class,
                'label_class'       => $label_class,
                'input_class'       => $input_class,
                'return'            => isset( $field['return'] ) ? $field['return'] : false,
                'options'           => $options,
                'custom_attributes' => $attributes,
                'validate'          => $validate,
                'default'           => isset( $field['default_value'] ) ? $field['default_value'] : '',
                'autofocus'         => isset( $field['autofocus'] ) ? $field['autofocus'] : '',
                'priority'          => isset( $field['priority'] ) ? $field['priority'] : '',
                'is_wwlc_field'     => true,
            );
        }

        /**
         * Add ability to toggle label for second address line field.
         *
         * @since 1.7.0
         * @since 1.7.4 Fix for WWLC-224. Only show "(optional)" text when "Enable address line 2 label." option is enabled
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_address_field_2( $field, $key, $args, $value ) { // phpcs:ignore.

            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                // Check if the field is address line 2.
                if ( 'wwlc_address_2' === $key && get_option( 'wwlc_fields_enable_address2_label' ) !== 'yes' ) {
                    $field = str_replace( '&nbsp;<span class="optional">(optional)</span>', '', $field );
                    $field = strip_tags( $field, '<p><input>' );
                    $field = str_replace( $args['label'], '', $field );
                }
            }

            return $field;
        }

        /**
         * Add support for WWLC url field by displaying it via woocommerce_form_field.
         *
         * @since 1.7.0
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_url_field_type( $field, $key, $args, $value ) {

            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                $required        = $args['required'] ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-wholesale-lead-capture' ) . '">*</abbr>' : '';
                $attributes      = $this->get_field_custom_attributes_markup( $args );
                $sort            = $args['priority'] ? $args['priority'] : '';
                $field_container = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr( $sort ) . '">%3$s</p>';
                $container_class = esc_attr( implode( ' ', $args['class'] ) );
                $field_html      = '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';
                $field_html     .= '<input type="url" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $attributes ) . ' />';

                $field = sprintf( $field_container, $container_class, $key, $field_html );
            }

            return $field;
        }

        /**
         * Add support for hidden field by displaying it via woocommerce_form_field.
         *
         * @since 1.7.0
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_hidden_field_type( $field, $key, $args, $value ) { // phpcs:ignore.

            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                $attributes = $this->get_field_custom_attributes_markup( $args );
                $field_html = '<input type="hidden" class="testtesttest input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $args['placeholder'] ) . '" ' . implode( ' ', $attributes ) . ' />';

                $field = $field_html;
            }

            return $field;
        }

        /**
         * Add support for recaptcha field by displaying it via woocommerce_form_field.
         *
         * @since 1.7.0
         * @since 1.7.3 Update the html tag used of class g-recaptcha from span to div
         * @since 1.7.4 Fix for WWLC-226. Modified how the recaptcha field html elements are displayed.
         *                 Note: since recaptcha is being added via js we can't seem to wrap it with any tags.
         *
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_recaptcha_field_type( $field, $key, $args, $value ) { // phpcs:ignore.

            $recaptcha_sitekey = get_option( 'wwlc_security_recaptcha_site_key' );

            if ( get_option( 'wwlc_security_recaptcha_type', 'v2_im_not_a_robot' ) === 'v2_im_not_a_robot' ) {

                $container_class = esc_attr( implode( ' ', $args['class'] ) );
                $field_html      = '<p><div id="%1$s" class="g-recaptcha form-row %2$s" data-sitekey="%3$s" data-callback="recaptchaCallback"></div></p>';

                return sprintf( $field_html, $key, $container_class, $recaptcha_sitekey );

            } elseif ( get_option( 'wwlc_security_recaptcha_type' ) === 'v2_invisible' ) {
                // invisible recaptcha.

                global $post;

                if ( has_shortcode( $post->post_content, 'wwlc_registration_form' ) ) {
                    $bind = 'wwlc-register';
                } elseif ( has_shortcode( $post->post_content, 'wwlc_login_form' ) ) {
                    $bind = 'wp-submit';
                }

                $field_html = '<p><div id="%1$s" class="g-recaptcha" data-sitekey="%2$s" data-bind="%3$s" data-callback="submitForm"></div></p>';

                return sprintf( $field_html, $key, $recaptcha_sitekey, $bind );

            }
        }

        /**
         * Add support for file upload field by displaying it via woocommerce_form_field.
         *
         * @since 1.7.0
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_file_field_type( $field, $key, $args, $value ) {

            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                $required        = $args['required'] ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-wholesale-lead-capture' ) . '">*</abbr>' : '';
                $attributes      = $this->get_field_custom_attributes_markup( $args );
                $sort            = $args['priority'] ? $args['priority'] : '';
                $field_container = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr( $sort ) . '">%3$s</p>';
                $container_class = esc_attr( implode( ' ', $args['class'] ) );
                $field_html      = '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';
                $field_html     .= '<span class="wwlc-file-upload-form">';
                $field_html     .= '<input name="file" type="' . $args['type'] . '">';
                $field_html     .= '<span class="wwlc-loader"></span>';
                $field_html     .= '<span class="placeholder" style="display:none;"></span>';
                $field_html     .= '<input type="hidden" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $attributes ) . ' />';
                $field_html     .= '</span>';

                $field = sprintf( $field_container, $container_class, $key, $field_html );
            }

            return $field;
        }

        /**
         * Add support for wwlc_password field.
         *
         * @since 1.7.0
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_password_field_type( $field, $key, $args, $value ) {

            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                $required        = $args['required'] ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-wholesale-lead-capture' ) . '">*</abbr>' : '';
                $attributes      = $this->get_field_custom_attributes_markup( $args );
                $sort            = $args['priority'] ? $args['priority'] : '';
                $field_container = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr( $sort ) . '">%3$s</p>';
                $container_class = esc_attr( implode( ' ', $args['class'] ) );
                $field_html      = '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';
                $field_html     .= '<input type="password" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $attributes ) . ' />';
                $field_html     .= '<span id="wwlc-password-strength" class="wwlc-password-strength"></span>';

                if ( get_option( 'wwlc_fields_enable_confirm_password_field' ) === 'yes' ) {

                    $confirm_label       = __( 'Confirm password', 'woocommerce-wholesale-lead-capture' );
                    $confirm_placeholder = get_option( 'wwlc_fields_confirm_password_field_placeholder' );
                    $field_html         .= '</p><p class="form-row ' . esc_attr( $args['id'] ) . '_confirm-field-set ' . esc_attr( $args['id'] ) . '_confirm form_field">';
                    $field_html         .= '<label for="' . esc_attr( $args['id'] ) . '_confirm" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $confirm_label . '</label>';
                    $field_html         .= '<input type="password" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" id="' . esc_attr( $args['id'] ) . '_confirm" placeholder="' . $confirm_placeholder . '">';

                }

                $field = sprintf( $field_container, $container_class, $key, $field_html );
            }

            return $field;
        }

        /**
         * Add support for wwlc_checkbox field.
         *
         * @since 1.7.0
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_checkbox_field_type( $field, $key, $args, $value ) {

            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                $required        = $args['required'] ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-wholesale-lead-capture' ) . '">*</abbr>' : '';
                $attributes      = $this->get_field_custom_attributes_markup( $args );
                $sort            = $args['priority'] ? $args['priority'] : '';
                $field_container = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr( $sort ) . '">%3$s</p>';
                $container_class = esc_attr( implode( ' ', $args['class'] ) );
                $field_html      = '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';
                $field_html     .= '<span class="wwlc_checkboxes_container checkbox_options_holder" ' . implode( ' ', $attributes ) . '>';

                foreach ( $args['options'] as $option_value => $checkbox_label ) {
                    $field_html .= '<label class="checkbox_options">';
                    $field_html .= '<input type="checkbox" class="input-checkbox ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_value ) . '" ' . checked( $value, $option_value, false ) . ' />';
                    $field_html .= esc_html( $checkbox_label ) . '</label>';
                }

                $field_html .= '</span>';

                $field = sprintf( $field_container, $container_class, $key, $field_html );
            }

            return $field;
        }

        /**
         * Modify radio field type.
         *
         * @since 1.7.0
         * @access public
         *
         * @param string $field Field html (blank).
         * @param string $key   Field ID.
         * @param array  $args  Field arguments.
         * @param string $value Field value.
         * @return string
         */
        public function wwlc_radio_field_type( $field, $key, $args, $value ) { // phpcs:ignore.
            // Check if the field is a WWLC field.
            if ( isset( $args['is_wwlc_field'] ) && $args['is_wwlc_field'] ) {
                $attributes = $this->get_field_custom_attributes_markup( $args );
                $field      = preg_replace( '/<\/label>/', '</label><span class="radio_options_holder" ' . implode( ' ', $attributes ) . '>', $field, 1 );
                $field      = str_replace( '</p>', '</span></p>', $field );
            }

            return $field;
        }

        /**
         * Add Recaptcha in Login Page.
         *
         * @since 1.14.4
         * @access public
         *
         * @param array $args Field arguments.
         */
        public function login_page_recaptcha( $args ) {

            if ( apply_filters( 'enable_login_page_recaptcha', true ) === true ) {

                wp_enqueue_script( 'wwlc_recaptcha_api_js' );
                wp_enqueue_script( 'wwlc_login_form_js' );

                if ( empty( $args['recaptcha'] ) ) {
                    return;
                }

                $field = array_shift( $args['recaptcha'] );

                woocommerce_form_field( $field['id'], $this->wwlc_reformat_field_args( $field ) );

            }
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Utility Functions
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Load template.
         *
         * @param string $template              String template path.
         * @param array  $options               Array array of options.
         * @param string $default_template_path String default template path.
         *
         * @since 1.0.0
         * @since 1.6.3 Made the function access method from private to public since we need this function called in WWLC_Shortcode class
         */
        public function _load_template( $template, $options, $default_template_path ) {

            wc_get_template( $template, $options, '', $default_template_path );
        }

        /**
         * Generate .
         *
         * @since 1.7.0
         * @access public
         *
         * @param array $args Field arguments.
         */
        private function get_field_custom_attributes_markup( $args ) {

            $custom_attributes = array();

            if ( ! isset( $args['custom_attributes'] ) || empty( $args['custom_attributes'] ) || ! is_array( $args['custom_attributes'] ) ) {
                return $custom_attributes;
            }

            foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
                $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
            }

            return $custom_attributes;
        }

        /**
         * Get the actual permalink of a WWLC page set on the settings.
         * WWLC-201 : This is needed to properly support WPML
         *
         * @since 1.7.0
         * @since 1.8.0 Made deprecated in favor of global function wwlc_get_url_of_page_option
         * @access public
         *
         * @param string $option_name Option id of the WWLC page.
         */
        public function get_wwlc_page_url( $option_name ) {

            wwlc_deprecated_function( debug_backtrace(), 'WWLC_Forms::get_wwlc_page_url', '1.8.0', 'global function wwlc_get_url_of_page_option' ); // phpcs:ignore.

            return wwlc_get_url_of_page_option( $option_name );
        }

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Execute model.
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.7.0
         * @access public
         */
        public function run() {

            add_filter( 'woocommerce_form_field_text', array( $this, 'wwlc_address_field_2' ), 10, 4 );
            add_filter( 'woocommerce_form_field_recaptcha', array( $this, 'wwlc_recaptcha_field_type' ), 10, 4 );
            add_filter( 'woocommerce_form_field_file', array( $this, 'wwlc_file_field_type' ), 10, 4 );
            add_filter( 'woocommerce_form_field_url', array( $this, 'wwlc_url_field_type' ), 10, 4 );
            add_filter( 'woocommerce_form_field_hidden', array( $this, 'wwlc_hidden_field_type' ), 10, 4 );
            add_filter( 'woocommerce_form_field_wwlc_password', array( $this, 'wwlc_password_field_type' ), 10, 4 );
            add_filter( 'woocommerce_form_field_wwlc_checkbox', array( $this, 'wwlc_checkbox_field_type' ), 10, 4 );
            add_filter( 'woocommerce_form_field_radio', array( $this, 'wwlc_radio_field_type' ), 10, 4 );

            // Login Page Recaptcha.
            add_action( 'wwlc_login_forms', array( $this, 'login_page_recaptcha' ) );
        }
    }

}
