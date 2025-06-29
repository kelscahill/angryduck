<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Registration_Form_Custom_Fields' ) ) {

    /**
     * Model that houses the logic of bootstrapping the plugin.
     *
     * @since 1.6.3
     */
    class WWLC_Registration_Form_Custom_Fields {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_Registration_Form_Custom_Fields.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Registration_Form_Custom_Fields
         */
        private static $_instance;

        /**
         * Class Methods
         */

        /**
         * WWLC_Registration_Form_Custom_Fields constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Registration_Form_Custom_Fields model.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWLC_Registration_Form_Custom_Fields is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Registration_Form_Custom_Fields model.
         *
         * @return WWLC_Registration_Form_Custom_Fields
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Save registration form custom field.
         *
         * @since 1.1.0
         * @since 1.12.0 Strip extra slashes before saving.
         *
         * @param null|array $custom_field Value of custom fields.
         * @return bool
         */
        public function wwlc_add_registration_form_custom_field( $custom_field = null ) {

            if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'wwlc_custom_fields_nonce' ) ) {

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    $custom_field = $_POST['customField'];
                }

                $field_id = $custom_field['field_id'];
                unset( $custom_field['field_id'] );

                // Strip extra slashes before saving.
                $custom_field = wwlc_strip_slashes( $custom_field );

                $field_id = str_replace( 'wwlc_cf_', '', $field_id );
                $field_id = 'wwlc_cf_' . $field_id;

                if ( ! ctype_alnum( str_replace( '_', '', $field_id ) ) ) {

                    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                        header( 'Content-Type: application/json' ); // specify we return json.
                        echo wp_json_encode(
                            array(
                                'status'        => 'fail',
                                /* translators: %1$s field ID */
                                'error_message' => sprintf( __( 'Field id %1$s contains none alpha numeric character/s', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                            )
                        );
                        die();

                    } else {
                        return false;
                    }
                }

                $custom_field['field_order'] = str_replace( array( '.', ',' ), '', $custom_field['field_order'] );
                if ( ! is_numeric( $custom_field['field_order'] ) ) {
                    $custom_field['field_order'] = 0;
                }

                $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( ! array_key_exists( $field_id, $registration_form_custom_fields ) ) {
                    $registration_form_custom_fields[ $field_id ] = $custom_field;
                } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                        header( 'Content-Type: application/json' ); // specify we return json.
                        echo wp_json_encode(
                            array(
                                'status'        => 'fail',
                                /* translators: %1$s field ID */
                                'error_message' => sprintf( __( 'Duplicate field, %1$s already exists.', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                            )
                        );
                        die();

                    } else {
                        return false;
                }

                update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
                            'status' => 'success',
                        )
                    );
                    die();

                } else {
                    return true;
                }
            }
        }

        /**
         * Edit registration form custom field. Same as above.
         *
         * @since 1.1.0
         * @since 1.12.0 Strip extra slashes before saving.
         *
         * @param null|array $custom_field Value of custom fields.
         * @return bool
         */
        public function wwlc_edit_registration_form_custom_field( $custom_field = null ) {

            if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'wwlc_custom_fields_nonce' ) ) {
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    $custom_field = $_POST['customField'];
                }

                $field_id = $custom_field['field_id'];
                unset( $custom_field['field_id'] );

                // Strip extra slashes before updating.
                $custom_field = wwlc_strip_slashes( $custom_field );

                $field_id = str_replace( 'wwlc_cf_', '', $field_id );
                $field_id = 'wwlc_cf_' . $field_id;

                $custom_field['field_order'] = str_replace( array( '.', ',' ), '', $custom_field['field_order'] );
                if ( ! is_numeric( $custom_field['field_order'] ) ) {
                    $custom_field['field_order'] = 0;
                }

                $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( array_key_exists( $field_id, $registration_form_custom_fields ) ) {
                    $registration_form_custom_fields[ $field_id ] = $custom_field;
                } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                        header( 'Content-Type: application/json' ); // specify we return json.
                        echo wp_json_encode(
                            array(
                                'status'        => 'fail',
                                /* translators: %1$s field ID */
                                'error_message' => sprintf( __( '%1$s custom field that you wish to edit does not exist', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                            )
                        );
                        die();

                    } else {
                        return false;
                }

                update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
                            'status' => 'success',
                        )
                    );
                    die();

                } else {
                    return true;
                }
            }
        }

        /**
         * Delete registration form custom field.
         *
         * @param null|string $field_id Field ID.
         * @return bool
         *
         * @since 1.1.0
         */
        public function wwlc_delete_registration_form_custom_field( $field_id = null ) {

            if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'wwlc_custom_fields_nonce' ) ) {
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    $field_id = $_POST['field_id'];
                }

                $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( array_key_exists( $field_id, $registration_form_custom_fields ) ) {
                    unset( $registration_form_custom_fields[ $field_id ] );
                } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                        header( 'Content-Type: application/json' ); // specify we return json.
                        echo wp_json_encode(
                            array(
                                'status'        => 'fail',
                                /* translators: %1$s field ID */
                                'error_message' => sprintf( __( '%1$s custom field that you wish to delete does not exist', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                            )
                        );
                        die();

                    } else {
                        return false;
                }

                update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, $registration_form_custom_fields );

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
                            'status' => 'success',
                        )
                    );
                    die();

                } else {
                    return true;
                }
            }
        }

        /**
         * Get custom field by id.
         *
         * @param @param null|string $field_id Field ID.
         * @param bool               $ajax_call Flag that we use to disable/enable ajax when this function called by other function.
         * @return array/bool
         *
         * @since 1.1.0
         * @since 1.6.3 WWLC-151
         */
        public function wwlc_get_custom_field_by_id( $field_id = null, $ajax_call = true ) {
            if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'wwlc_custom_fields_nonce' ) || wp_verify_nonce( $_REQUEST['_wpnonce'], 'wwlc_register_user' ) || wp_verify_nonce( $_REQUEST['_wpnonce'], 'wwlc_update_user' ) ) {
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX && true === $ajax_call ) {
                    $field_id = $_POST['field_id'];
                }

                $registration_form_custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

                if ( array_key_exists( $field_id, $registration_form_custom_fields ) ) {

                    $custom_field             = $registration_form_custom_fields[ $field_id ];
                    $custom_field['field_id'] = $field_id;

                    // Convert select and radio type special chars into html entities.
                    $custom_field = wwlc_htmlspecialchars( $custom_field );

                } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX && true === $ajax_call ) {

                        header( 'Content-Type: application/json' ); // specify we return json.
                        echo wp_json_encode(
                            array(
                                'status'        => 'fail',
                                /* translators: %1$s field ID */
                                'error_message' => sprintf( __( 'Cannot retrieve custom field, %1$s does not exist', 'woocommerce-wholesale-lead-capture' ), $field_id ),
                            )
                        );
                        die();

                    } else {
                        return false;
                }

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX && true === $ajax_call ) {

                    header( 'Content-Type: application/json' ); // specify we return json.
                    echo wp_json_encode(
                        array(
                            'status'       => 'success',
                            'custom_field' => $custom_field,
                        )
                    );
                    die();

                } else {
                    return $custom_field;
                }
            }
        }
    }
}
