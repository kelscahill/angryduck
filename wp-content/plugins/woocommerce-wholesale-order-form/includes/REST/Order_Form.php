<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\REST
 */

namespace RymeraWebCo\WWOF\REST;

use RymeraWebCo\WWOF\Abstracts\Abstract_REST;
use RymeraWebCo\WWOF\Classes\Migrate_Form_Data;
use RymeraWebCo\WWOF\Factories\Order_Forms_Query;
use RymeraWebCo\WWOF\Helpers\WPML;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use RymeraWebCo\WWOF\Factories\Order_Form as Order_Form_Object;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Order_Form class.
 *
 * @since 3.0
 */
class Order_Form extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @var Order_Form $instance object
     * @since 3.0.6
     */
    protected static $instance;

    /**
     * Set `rest_base` to `order-form`.
     *
     * @since 3.0
     */
    public function register_plugin_routes() {

        $this->namespace = 'wwof/v3';
        $this->rest_base = 'order-form';
        parent::register_plugin_routes();
    }

    /**
     * Register custom REST routes.
     *
     * @since 3.0
     * @return void
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/save",
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'save_form' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'form' => array(
                        'required' => true,
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base",
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_form' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'                => array(
                    'formIds' => array(
                        'required'          => true,
                        'sanitize_callback' => array( $this, 'sanitize_ids_list' ),
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_order_forms' ),
                'permission_callback' => array( $this, 'can_manage_woocommerce' ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/migrate",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'migrate_v2_forms' ),
                'permission_callback' => array( $this, 'can_manage_woocommerce' ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/v2-data",
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_v2_forms' ),
                'permission_callback' => array( $this, 'can_manage_woocommerce' ),
            )
        );
    }

    /**
     * Handle order forms query.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response | WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_order_forms( $request ) {

        $query = $request->get_params();

        $order_forms = new Order_Forms_Query(
            wp_parse_args(
                $query,
                array(
                    'post_status'    => array( 'draft', 'publish' ),
                    'orderby'        => 'ID',
                    'order'          => 'DESC',
                    'posts_per_page' => 10,
                )
            ),
            'edit'
        );

        return $this->rest_response( $order_forms->get_id_indexed_posts(), $request );
    }

    /**
     * Handle saving or updating the order form.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response | WP_Error Response object on success, or WP_Error object on failure.
     */
    public function save_form( $request ) {

        $form = $request->get_param( 'form' );
        if ( empty( $form['post_title'] ) ) {
            return new WP_Error(
                'rest_invalid_form',
                __( 'Form title is required.', 'woocommerce-wholesale-order-form' ),
                array( 'status' => 400 )
            );
        }
        if ( empty( $form['form_body']['columns'] ) ) {
            return new WP_Error(
                'rest_invalid_form',
                __( 'Order Form Table must have columns for display.', 'woocommerce-wholesale-order-form' ),
                array( 'status' => 400 )
            );
        }

        [
            'ID'          => $form_id,
            'post_status' => $post_status,
            'form_header' => $form_header,
            'form_body'   => $form_body,
            'form_footer' => $form_footer,
            'settings'    => $settings,
        ] = wp_parse_args(
            $form,
            array(
                'ID'          => null,
                'post_status' => 'draft',
                'form_header' => null,
                'form_body'   => null,
                'form_footer' => null,
                'settings'    => null,
            )
        );

        $post_args = array(
            'post_title'  => sanitize_text_field( $form['post_title'] ),
            'post_status' => sanitize_text_field( $post_status ),
            'post_type'   => Order_Form_Object::POST_TYPE,
        );

        $last_insert_id = null;
        $updated_id     = null;
        if ( empty( $form_id ) || ( ! is_numeric( $form_id ) && 'new' === strtolower( $form_id ) ) ) {
            $last_insert_id = wp_insert_post( $post_args, true );
            $form_id        = $last_insert_id;
        } else {
            $updated_id = wp_update_post(
                array(
                    'ID'          => $form_id,
                    'post_title'  => $form['post_title'],
                    'post_status' => $post_status,
                )
            );
            if ( is_wp_error( $updated_id ) ) {
                return $updated_id;
            } elseif ( 0 === $updated_id ) {
                return new WP_Error(
                    'rest_invalid_form',
                    __( 'Form could not be updated.', 'woocommerce-wholesale-order-form' ),
                    array( 'status' => 400 )
                );
            }
            $form_id = $updated_id;
        }

        if ( ! empty( $form_header ) ) {
            $form_header = $this->sanitize_form_header_footer( $form_id, $form_header, WWOF::FORM_HEADER );
        }
        update_post_meta( $form_id, 'form_header', $form_header );

        if ( ! empty( $form_footer ) ) {
            $form_footer = $this->sanitize_form_header_footer( $form_id, $form_footer, WWOF::FORM_FOOTER );
        }
        update_post_meta( $form_id, 'form_footer', $form_footer );

        $form_body = $this->sanitize_form_body( $form_id, $form_body );
        update_post_meta( $form_id, 'form_body', $form_body );

        $settings = $this->sanitize_settings_data( $form_id, $settings, WWOF::FORM_SETTINGS );
        update_post_meta( $form_id, 'settings', $settings );

        /***************************************************************************
         * Re-query order forms
         ***************************************************************************
         *
         * We re-query order forms here to ensure that the order form we just saved
         * is included in the response which is going to be used to update the
         * order forms list in the app.
         */
        $response = array(
            'formPosts' => ( new Order_Forms_Query(
                array(
                    'post_status' => array(
                        'draft',
                        'publish',
                    ),
                    'orderby'     => 'ID',
                    'order'       => 'DESC',
                ),
                'edit'
            ) )->get_id_indexed_posts(),
        );

        if ( $last_insert_id ) {
            $response['lastInsertId'] = $last_insert_id;
        }
        if ( $updated_id ) {
            $response['updatedId'] = $updated_id;
        }

        return $this->rest_response( $response, $request );
    }

    /**
     * Sanitize form header and footer data.
     *
     * @param int         $form_id The form ID.
     * @param array|mixed $data    The data to sanitize.
     * @param string      $section The section of the form.
     *
     * @since 3.0
     * @return array The sanitized data.
     */
    protected function sanitize_form_header_footer( $form_id, &$data, $section ) {

        $data = wp_parse_args(
            $data,
            array(
                'rows'     => array(),
                'settings' => array(),
            )
        );
        foreach ( $data['rows'] as &$row ) {
            foreach ( $row as $row_key => &$row_value ) {
                if ( 'id' === $row_key ) {
                    $row_value = sanitize_text_field( $row_value );
                } elseif ( 'columns' === $row_key ) {
                    foreach ( $row_value as &$column ) {
                        foreach ( $column as $col_key => &$col_value ) {
                            if ( 'id' === $col_key ) {
                                $col_value = sanitize_text_field( $col_value );
                            } elseif ( 'fields' === $col_key ) {
                                foreach ( $col_value as &$field_value ) {
                                    $field_value['settings'] = $this->sanitize_settings_data(
                                        $form_id,
                                        $field_value['settings'],
                                        $section,
                                        $field_value
                                    );
                                }
                            } elseif ( 'settings' === $col_key ) {
                                $col_value = $this->sanitize_settings_data( $form_id, $col_value, $section );
                            }
                        }
                    }
                } elseif ( 'settings' === $row_key ) {
                    $row_value = $this->sanitize_settings_data( $form_id, $row_value, $section );
                }
            }
        }

        $data['settings'] = $this->sanitize_settings_data( $form_id, $data['settings'], $section );

        return $data;
    }

    /**
     * Sanitize settings data.
     *
     * @param int    $form_id      The form ID.
     * @param array  $settings     Settings data.
     * @param string $form_section The form section.
     * @param array  $field        The field data.
     *
     * @since 3.0
     * @return array
     */
    protected function sanitize_settings_data( $form_id, &$settings, $form_section, $field = array() ) {

        if ( empty( $settings ) || ! is_array( $settings ) ) {
            return $settings;
        }

        $field_defaults = array(
            'name'         => 'Settings',
            'elementClass' => '',
        );

        $field = wp_parse_args( $field, $field_defaults );

        foreach ( $settings as $setting_key => &$setting_value ) {
            if ( empty( $setting_value ) ) {
                continue;
            }
            if ( 'styles' === $setting_key ) {
                foreach ( $setting_value as $setting_section_key => &$setting_section_value ) {
                    switch ( $setting_section_key ) {
                        case 'margin':
                        case 'padding':
                            /***************************************************************************
                             * Margin and Padding sanitization
                             ***************************************************************************
                             *
                             * Margin and padding settings are arrays of 4 values. So, we are handling their sanitization
                             * similarly here.
                             */
                            foreach ( $setting_section_value as $side_key => &$side_value ) {
                                if ( is_numeric( $side_value ) ) {
                                    $side_value = intval( $side_value );
                                } else {
                                    $side_value = sanitize_text_field( $side_value );
                                }
                            }
                            break;
                        case 'boxWidth':
                        case 'elementWidth':
                            $setting_section_value['width'] = $setting_section_value['width']
                                ? intval( $setting_section_value['width'] ) : '';
                            $setting_section_value['unit']  = sanitize_text_field( $setting_section_value['unit'] );
                            break;
                        case 'fontSize':
                            $setting_section_value['size'] = $setting_section_value['size']
                                ? intval( $setting_section_value['size'] ) : '';
                            $setting_section_value['unit'] = sanitize_text_field( $setting_section_value['unit'] );
                            break;
                        default:
                            $setting_section_value = sanitize_text_field( $setting_section_value );
                            break;
                    }
                }
            } elseif ( 'options' === $setting_key ) {
                foreach ( $setting_value as $option_key => &$option_value ) {
                    if ( is_numeric( $option_value ) ) {
                        $option_value = intval( $option_value );
                    } elseif ( is_array( $option_value ) ) {
                        foreach ( $option_value as $option_key1 => &$option_value1 ) {
                            if ( is_numeric( $option_value1 ) ) {
                                $option_value1 = intval( $option_value1 );
                            } elseif ( is_array( $option_value1 ) ) {
                                // Sanitize Product Meta fields.
                                foreach ( $option_value1 as $option_key2 => &$option_value2 ) {
                                    if ( is_numeric( $option_value2 ) ) {
                                        $option_value2 = intval( $option_value2 );
                                    } else {
                                        $option_value2 = sanitize_text_field( $option_value2 );
                                        WPML::maybe_register_translatable_field(
                                            $form_id,
                                            $form_section,
                                            $field,
                                            $option_key2,
                                            $option_value2,
                                        );
                                    }
                                }
                            } else {
                                $option_value1 = sanitize_text_field( $option_value1 );
                                WPML::maybe_register_translatable_field(
                                    $form_id,
                                    $form_section,
                                    $field,
                                    $option_key1,
                                    $option_value1,
                                );
                            }
                        }
                    } else {
                        $option_value = sanitize_text_field( $option_value );
                        WPML::maybe_register_translatable_field(
                            $form_id,
                            $form_section,
                            $field,
                            $option_key,
                            $option_value,
                        );
                    }
                }
            } elseif ( is_numeric( $setting_value ) ) {
                $setting_value = intval( $setting_value );
            } elseif ( is_array( $setting_value ) ) {
                foreach ( $setting_value as $setting_value_key => &$setting_value_value ) {
                    if ( is_array( $setting_value_value ) ) {
                        foreach ( $setting_value_value as &$item ) {
                            if ( is_numeric( $item ) ) {
                                $item = intval( $item );
                            } else {
                                $item = sanitize_text_field( $item );
                            }
                            WPML::maybe_register_translatable_field(
                                $form_id,
                                $form_section,
                                $field,
                                $setting_value_key,
                                $item,
                            );
                        }
                    } elseif ( is_numeric( $setting_value_value ) ) {
                        $setting_value_value = intval( $setting_value_value );
                    } elseif ( 'noAccessMessage' === $setting_value_key ) {
                        $setting_value_value = wp_kses_post( $setting_value_value );
                    } else {
                        $setting_value_value = sanitize_text_field( $setting_value_value );
                    }
                    WPML::maybe_register_translatable_field(
                        $form_id,
                        $form_section,
                        $field,
                        $setting_value_key,
                        $setting_value_value,
                    );
                }
            } else {
                $setting_value = sanitize_text_field( $setting_value );
            }
        }

        return $settings;
    }

    /**
     * Sanitize form body data.
     *
     * @param int         $form_id The form id.
     * @param array|mixed $data    The data to sanitize.
     *
     * @since 3.0
     * @return mixed
     */
    protected function sanitize_form_body( $form_id, $data ) {

        foreach ( $data['columns'] as &$column ) {
            foreach ( $column as $col_key => &$col_value ) {
                switch ( $col_key ) {
                    case 'settings':
                        $col_value = $this->sanitize_settings_data( $form_id, $col_value, WWOF::FORM_BODY, $column );
                        break;
                    default:
                        $col_value = sanitize_text_field( $col_value );
                }
            }
        }

        $data['settings'] = $this->sanitize_settings_data( $form_id, $data['settings'], WWOF::FORM_BODY );

        return $data;
    }

    /**
     * Ensure the id(s) are numeric.
     *
     * @param string|number|int[]|string[] $ids The id(s) to check.
     *
     * @since 3.0
     * @return int[]
     */
    public function sanitize_ids_list( $ids ) {

        return array_map( 'absint', $ids );
    }

    /**
     * Delete order forms.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response
     */
    public function delete_form( $request ) {

        $form_ids = $request->get_param( 'formIds' );

        foreach ( $form_ids as $form_id ) {
            wp_delete_post( $form_id, true );
        }

        $response = array(
            'deleted'   => true,
            'formPosts' => ( new Order_Forms_Query(
                array(
                    'post_status' => array(
                        'draft',
                        'publish',
                    ),
                )
            ) )->posts,
        );

        return $this->rest_response( $response, $request );
    }

    /**
     * Migrate v2 order forms.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response|WP_Error
     */
    public function migrate_v2_forms( $request ) {

        $migrator = new Migrate_Form_Data();
        $result   = $migrator->run();
        if ( is_wp_error( $result ) ) {
            WWOF::log_error( $result->get_error_message() );

            return $result;
        }

        return $this->rest_response( array( 'message' => __( 'V2 Order Forms migrated to V3!', 'woocommerce-wholesale-order-form' ) ), $request );
    }

    /**
     * Delete v2 order forms data.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response|WP_Error
     */
    public function delete_v2_forms( $request ) {

        $query = new Order_Forms_Query(
            array(
                'post_status'            => array( 'draft', 'publish' ),
                'orderby'                => 'ID',
                'order'                  => 'DESC',
                'posts_per_page'         => -1,
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'fields'                 => 'ids',
            ),
            'edit'
        );
        if ( $query->have_posts() ) {
            $v2_metas = array(
                'editor_area',
                'styles',
                '_settings',
            );
            foreach ( $query->posts as $form_id ) {
                foreach ( $v2_metas as $meta_key ) {
                    delete_post_meta( $form_id, $meta_key );
                }
            }

            return $this->rest_response( array( 'message' => __( 'V2 Order Forms data deleted!', 'woocommerce-wholesale-order-form' ) ), $request );
        }

        return new WP_Error( 'no_v2_forms', __( 'No V2 Order Forms found!', 'woocommerce-wholesale-order-form' ) );
    }
}
