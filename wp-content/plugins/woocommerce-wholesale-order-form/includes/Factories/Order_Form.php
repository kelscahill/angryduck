<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Factories
 */

namespace RymeraWebCo\WWOF\Factories;

use Exception;
use RymeraWebCo\WWOF\Helpers\WWOF;
use WP_Post;
use WP_REST_Request;

/**
 * Order_Form class which is basically similar to WP_Post with some additional methods and properties for order forms.
 *
 * @since 3.0
 */
class Order_Form {

    /**
     * Custom post type.
     */
    const POST_TYPE = 'order_form';

    /**
     * Holds context for the order form.
     *
     * @since 3.0
     * @var string
     */
    public $context;

    /**
     * Holds the WP_Post ID. We need this for the React app.
     *
     * @since 3.0
     * @var int
     */
    public $key;

    /**
     * Holds form header settings.
     *
     * @since 3.0
     * @var array
     */
    public $form_header;

    /**
     * Holds form body settings.
     *
     * @since 3.0
     * @var array
     */
    public $form_body;

    /**
     * Holds form footer settings.
     *
     * @since 3.0
     * @var array
     */
    public $form_footer;

    /**
     * Holds form settings option values.
     *
     * @since 3.0
     * @var array
     */
    public $settings;

    /**
     * Holds a list of all the pages where the form is displayed.
     *
     * @since 3.0
     * @var array
     */
    public $locations;

    /**
     * Holds internal REST data for initial app loading.
     *
     * @since 3.0
     * @var array
     */
    public $internal;

    /**
     * Holds a string of either `is` or `has` a v2 form data.
     *
     * @since 3.0
     * @var string
     */
    public $v2;

    /**
     * Holds the order form post ID.
     *
     * @since 3.0
     * @var int
     */
    public $ID;

    /**
     * Holds the order form post status.
     *
     * @since 3.0
     * @var string
     */
    public $post_status;

    /**
     * Holds the order form post title.
     *
     * @since 3.0
     * @var string
     */
    public $post_title;

    /**
     * Holds the order form post content.
     *
     * @since 3.0
     * @var string
     */
    public $post_content;

    /**
     * Holds the order form post name.
     *
     * @since 3.0
     * @var string
     */
    public $post_name;

    /**
     * Holds the order form post type.
     *
     * @since 3.0
     * @var string
     */
    public $post_type;

    /**
     * Constructor.
     *
     * @param string $context Either 'view' or 'edit'.
     */
    public function __construct( $context = 'view' ) {

        $this->context = $context;
    }

    /**
     * Get the order form instance.
     *
     * @param WP_Post|object|int $post    Post object or ID.
     * @param string             $context Either 'view' or 'edit'.
     *
     * @return self
     */
    public static function get_instance( $post = null, $context = 'view' ) {

        $instance = new self( $context );

        $instance->set_properties( $post );

        return $instance;
    }

    /**
     * Set class properties.
     *
     * @param WP_Post|object|int|null $post WP_Post object.
     *
     * @since 3.0
     * @return void
     */
    public function set_properties( $post = null ) {

        $post = get_post( $post );

        if ( empty( $post ) || self::POST_TYPE !== $post->post_type ) {
            WWOF::log_error( __( 'Invalid order form.', 'woocommerce-wholesale-order-form' ) );

            return;
        }

        $fields = array(
            'ID',
            'post_status',
            'post_title',
            'post_content',
            'post_name',
            'post_type',
        );

        foreach ( get_object_vars( $post ) as $property => $value ) {
            if ( in_array( $property, $fields, true ) ) {
                $this->{$property} = $value;
            }
        }
        $this->key = $post->ID;

        /**
         * Filter form header meta.
         *
         * @param array  $form_header The form header meta.
         * @param int    $post_id     The post ID.
         * @param string $meta_key    The meta key.
         *
         * @since 3.0
         */
        $this->form_header = apply_filters(
            'wwof_order_form_header_meta',
            wp_parse_args(
                array_filter( (array) get_post_meta( $post->ID, 'form_header', true ) ),
                array(
                    'rows'     => array(),
                    'settings' => array(
                        'styles'  => null,
                        'options' => null,
                    ),
                )
            ),
            $post->ID,
            'form_header'
        );

        /**
         * Filter form body meta.
         *
         * @param array  $form_body The form body meta.
         * @param int    $post_id   The post ID.
         * @param string $meta_key  The meta key.
         *
         * @since 3.0
         */
        $this->form_body = apply_filters(
            'wwof_order_form_body_meta',
            wp_parse_args(
            /**
             * Filter form body meta.
             *
             * @param array $form_body The form body meta.
             *
             * @since 3.0
             */
                array_filter( (array) get_post_meta( $post->ID, 'form_body', true ) ),
                array(
                    'columns'  => array(),
                    'settings' => array(
                        'styles'  => null,
                        'options' => null,
                    ),
                )
            ),
            $post->ID,
            'form_body'
        );

        /**
         * Filter form footer meta.
         *
         * @param array  $form_footer The form footer meta.
         * @param int    $post_id     The post ID.
         * @param string $meta_key    The meta key.
         *
         * @since 3.0
         */
        $this->form_footer = apply_filters(
            'wwof_order_form_footer_meta',
            wp_parse_args(
                array_filter( (array) get_post_meta( $post->ID, 'form_footer', true ) ),
                array(
                    'rows'     => array(),
                    'settings' => array(
                        'styles'  => null,
                        'options' => null,
                    ),
                )
            ),
            $post->ID,
            'form_footer'
        );

        $i18n = require WWOF_PLUGIN_DIR_PATH . 'includes/I18n/order-form.php';

        /***************************************************************************
         * General Form Settings
         ***************************************************************************
         *
         * We define the default properties here and merge them with the existing
         * settings. We also re-declare them in the SettingsTab component for the
         * app to avoid script errors for new order forms.
         *
         * @see woocommerce-wholesale-order-form/src/apps/wwof/admin/components/FormEditor/PageSidebar/Tabs/SettingsTab.vue:42
         */
        $general_settings_defaults = array(
            'permissions'                => array(
                'allowedUserRoles' => array(),
                'allowPublicRead'  => 'yes',
                'noAccessTitle'    => $i18n['accessDenied'],
                'noAccessMessage'  => $i18n['noAccessMessage'],
                'noAccessLoginUrl' => wp_login_url(),
            ),
            'hideFormTitle'              => 'no',
            'virtualTable'               => 'no',
            'virtualTableHeight'         => 600,
            'allowFavourites'            => 'no',
            'favouriteIcon'              => 'heart',
            'onlyShowFavourites'         => 'no',
            'productSort'                => 'asc',
            'productSortBy'              => '',
            'popularityPeriod'           => '',
            'productsPerPage'            => 10,
            'lazyLoad'                   => 'no',
            'showVariationsIndividually' => 'no',
            'showZeroInventory'          => 'no',
            'simplifyAddToCartNotices'   => 'yes',
            'includeProducts'            => array(),
            'excludeProducts'            => array(),
            'defaultCategory'            => null,
            'includedCategories'         => array(),
            'excludedCategories'         => array(),
        );

        $this->settings = array_filter( (array) get_post_meta( $post->ID, 'settings', true ) );

        $this->settings['permissions'] = wp_parse_args( $this->settings['permissions'], $general_settings_defaults['permissions'] );
        /**
         * Filter form settings meta.
         *
         * @param array  $settings The form settings meta.
         * @param int    $post_id  The post ID.
         * @param string $meta_key The meta key.
         *
         * @since 3.0
         */
        $this->settings = apply_filters(
            'wwof_order_form_settings_meta',
            wp_parse_args( $this->settings, $general_settings_defaults ),
            $post->ID,
            'settings'
        );

        $this->set_locations();
        $this->set_internal_properties();

        /***************************************************************************
         * Marks the order form as having v2 form data.
         ***************************************************************************
         *
         * We mark the order form as having v2 form data if the form header, body,
         * or footer has any data. This is used to determine whether to show the
         * migration button.
         */
        if ( 'edit' === $this->context ) {
            $v2_form = array_filter(
                wp_parse_args(
                    (array) get_post_meta( $this->key, 'editor_area', true ),
                    array(
                        'formHeader' => array(),
                        'formTable'  => array(),
                        'formFooter' => array(),
                    )
                )
            );

            if ( ! empty( $v2_form ) ) {
                $this->v2 = 'is';
                if ( ! empty( $this->form_body['columns'] ) ) {
                    $this->v2 = 'has';
                }
            }
        }
    }

    /**
     * Make an internal REST request to get the data.
     *
     * @param string $endpoint The REST endpoint.
     * @param array  $params   The REST endpoint params.
     *
     * @since 3.0
     * @return mixed
     */
    private function get_internal_rest_data( $endpoint, $params = array() ) {

        $request = new WP_REST_Request( 'GET', $endpoint );
        $request->set_query_params( $params );
        $response = rest_do_request( $request );

        return $response->get_data();
    }

    /**
     * Set misc properties used in the app.
     *
     * @since 3.0
     */
    private function set_internal_properties() {

        $this->internal = array(
            'products'   => array(
                'include' => array(),
                'exclude' => array(),
            ),
            'categories' => array(
                'default' => null,
                'include' => array(),
                'exclude' => array(),
            ),
        );

        if ( ! empty( $this->settings['defaultCategory'] ) ) {
            $this->internal['categories']['default'] = $this->get_internal_rest_data(
                '/wc/v3/products/categories/' . $this->settings['defaultCategory']
            );
        }

        if ( 'edit' !== $this->context ) {
            return;
        }

        if ( ! empty( $this->settings['includedCategories'] ) ) {
            $this->internal['categories']['include'] = $this->get_internal_rest_data(
                '/wc/v3/products/categories',
                array(
                    'include' => $this->settings['includedCategories'],
                )
            );
        }
        if ( ! empty( $this->settings['excludedCategories'] ) ) {
            $this->internal['categories']['exclude'] = $this->get_internal_rest_data(
                '/wc/v3/products/categories',
                array(
                    'exclude' => $this->settings['excludedCategories'],
                )
            );
        }
    }

    /**
     * Set locations of the order form. Check if its added either in page or post type.
     *
     * @since  3.0
     */
    private function set_locations() {

        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title FROM $wpdb->posts p
                          WHERE p.post_content LIKE %s AND p.post_type IN ( 'page' , 'post' ) AND p.post_status = 'publish'",
                '%' . $wpdb->esc_like(
                    sprintf( '[wwof_product_listing id="%d"]', $this->ID )
                ) . '%'
            )
        );

        $this->locations = array();

        if ( ! empty( $results ) ) {
            foreach ( $results as $result ) {
                $this->locations[] = array(
                    'ID'         => $result->ID,
                    'post_title' => $result->post_title,
                    'permalink'  => get_permalink( $result->ID ),
                );
            }

            $this->locations = array_unique( $this->locations, SORT_REGULAR );
        }
    }

    /**
     * Save order form.
     *
     * @since 3.0
     * @throws Exception If form title is empty.
     * @return void
     */
    public function save() {

        if ( empty( $this->post_title ) ) {
            throw new Exception(
                esc_html__( 'Form title is required.', 'woocommerce-wholesale-order-form' ),
                E_USER_ERROR // phpcs:ignore
            );
        }
        if ( empty( $this->{WWOF::FORM_BODY} ) ) {
            throw new Exception(
                esc_html__( 'Form body is required.', 'woocommerce-wholesale-order-form' ),
                E_USER_ERROR // phpcs:ignore
            );
        }

        $post_args = array(
            'post_title'  => $this->post_title,
            'post_status' => $this->post_status,
            'post_type'   => self::POST_TYPE,
        );
        if ( ! $this->ID ) {
            $this->ID = wp_insert_post( $post_args );
        }

        update_post_meta( $this->ID, WWOF::FORM_BODY, $this->{WWOF::FORM_BODY} );

        if ( ! empty( $this->{WWOF::FORM_HEADER} ) ) {
            update_post_meta( $this->ID, WWOF::FORM_HEADER, $this->{WWOF::FORM_HEADER} );
        }
        if ( ! empty( $this->{WWOF::FORM_FOOTER} ) ) {
            update_post_meta( $this->ID, WWOF::FORM_FOOTER, $this->{WWOF::FORM_FOOTER} );
        }
        if ( ! empty( $this->settings ) ) {
            update_post_meta( $this->ID, WWOF::FORM_SETTINGS, $this->{WWOF::FORM_SETTINGS} );
        }
    }

    /**
     * Set property value.
     *
     * @param string $key   Property name.
     * @param mixed  $value Property value.
     *
     * @since 3.0
     * @return void
     */
    public function set_form_property( $key, $value ) {

        $keys = array_flip(
            array(
                'post_title',
                'form_header',
                'form_body',
                'form_footer',
                'settings',
                'post_status',
            )
        );

        if ( ! isset( $keys[ $key ] ) ) {
            return;
        }

        $this->$key = $value;
    }

    /**
     * Get class property.
     *
     * @param string $key Property name.
     *
     * @since 3.0
     * @return null|mixed
     */
    public function __get( $key ) {

        return $this->$key ?? null;
    }

    /**
     * Get settings property.
     *
     * @param string $setting_key Settings property name.
     *
     * @since 3.0.5
     * @return mixed|null
     */
    public function get_settings( $setting_key ) {

        return $this->settings[ $setting_key ] ?? null;
    }

    /**
     * Check if the form is set to allow favourites.
     *
     * @since 3.0.5
     * @return bool
     */
    public function allow_favourites() {

        return 'yes' === $this->get_settings( 'allowFavourites' );
    }

    /**
     * Check if the form is set to only show favourites.
     *
     * @since 3.0.5
     * @return bool
     */
    public function only_show_favourites() {

        $only_show_favourites = filter_input( INPUT_GET, 'only_show_favourites', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $only_show_favourites = wc_string_to_bool( $only_show_favourites );
        $only_show_favourites = ! empty( $only_show_favourites ) ? 'yes' : $this->get_settings( 'onlyShowFavourites' );

        return $this->allow_favourites() && 'yes' === $only_show_favourites;
    }
}
