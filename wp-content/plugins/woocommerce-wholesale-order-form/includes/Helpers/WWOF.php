<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\REST\Order_Form as Order_Form_REST;
use RymeraWebCo\WWOF\Helpers\WC as WC_Helper;
use RymeraWebCo\WWOF\Helpers\WWP as WWP_Helper;
use RymeraWebCo\WWOF\Helpers\WWPP as WWPP_Helper;
use WP_Post;
use WP_Query;

/**
 * WWOF class.
 *
 * @since 3.0
 */
class WWOF {

    /**
     * The form header section.
     */
    public const FORM_HEADER = 'form_header';

    /**
     * The form body section.
     */
    public const FORM_BODY = 'form_body';

    /**
     * The form footer section.
     */
    public const FORM_FOOTER = 'form_footer';

    /**
     * The form settings section.
     */
    public const FORM_SETTINGS = 'settings';

    /**
     * User favourites meta key.
     */
    public const FAVOURITES_META_KEY = 'wwof_favourites';

    /**
     * Checks if WWP is fresh install.
     *
     * @return bool
     */
    public static function is_fresh_install() {

        /*
        |--------------------------------------------------------------------------
        | Check installation
        |--------------------------------------------------------------------------
        |
        | Flag that determines if the current order form is old installation.
        |
        */
        $order_form_page = get_option( WWOF_SETTINGS_WHOLESALE_PAGE_ID );

        return ! ( $order_form_page > 0 && get_post_status( $order_form_page ) !== '' );
    }

    /**
     * Check if the site has Product Addons active.
     *
     * @since  3.0
     * @access public
     * @return boolean
     */
    public static function is_product_addons_active() {

        return is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' );
    }

    /**
     * Check if WWOF has template overrides prior to 2.0.
     *
     * @since  2.0
     * @access public
     * @return boolean
     */
    public static function has_template_overrides() {

        /*
        |--------------------------------------------------------------------------
        | Ensure list_files function is available
        |--------------------------------------------------------------------------
        |
        | Makes sure the list_files() function is defined before trying to use it.
        |
        */
        if ( ! function_exists( 'list_files' ) ) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $wc_templates = list_files( get_stylesheet_directory() . '/woocommerce' );

        /*
        |--------------------------------------------------------------------------
        | Check for template overrides
        |--------------------------------------------------------------------------
        |
        | Bail if template override is present.
        |
        */
        if ( ! empty( $wc_templates ) ) {
            foreach ( $wc_templates as $temp ) {
                if ( strpos( $temp, 'wwof-product-listing' ) !== false ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks required plugins if they are active.
     *
     * @return array List of plugins that are not active.
     */
    public static function missing_required_plugins() {

        $i       = 0;
        $plugins = array();

        $new_required_plugins = array(
            'woocommerce/woocommerce.php',
            'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php',
        );

        /**
         * Filter the required plugins.
         *
         * @param array $new_required_plugins The required plugins.
         *
         * @since 3.0
         */
        $required_plugins = apply_filters( 'wwof_required_plugins', $new_required_plugins );

        foreach ( $required_plugins as $plugin ) {
            if ( ! is_plugin_active( $plugin ) ) {
                $plugin_name                  = explode( '/', $plugin );
                $plugins[ $i ]['plugin-key']  = $plugin_name[0];
                $plugins[ $i ]['plugin-base'] = $plugin;
                $plugins[ $i ]['plugin-name'] = str_replace(
                    'Woocommerce',
                    'WooCommerce',
                    ucwords( str_replace( '-', ' ', $plugin_name[0] ) )
                );
            }

            ++$i;
        }

        return $plugins;
    }

    /**
     * Check if WC Admin is active
     *
     * @since  1.11.7
     * @access public
     * @return boolean True if active, false otherwise.
     */
    public static function is_wc_admin_active() {

        if ( class_exists( '\Automattic\WooCommerce\Admin\Composer\Package' ) &&
            defined( 'WC_ADMIN_APP' ) && WC_ADMIN_APP ) {
            return \Automattic\WooCommerce\Admin\Composer\Package::is_package_active();
        } elseif ( is_plugin_active( 'woocommerce-admin/woocommerce-admin.php' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get Admin Note instance. WC Admin v1.7 they changed the class from WC_Admin_Note to Note.
     *
     * @param string $note_id Note ID.
     *
     * @access public
     * @since  1.13
     * @return \Automattic\WooCommerce\Admin\Notes\Note|\Automattic\WooCommerce\Admin\Notes\WC_Admin_Note
     */
    public static function wc_admin_note_instance( $note_id = null ) {

        if ( class_exists( '\Automattic\WooCommerce\Admin\Notes\Note' ) ) {
            return new \Automattic\WooCommerce\Admin\Notes\Note( $note_id );
        } else {
            return new \Automattic\WooCommerce\Admin\Notes\WC_Admin_Note( $note_id );
        }
    }

    /**
     * Get data about the current woocommerce installation.
     *
     * @since  1.3.1
     * @access public
     * @return array|false Array of data about the current woocommerce installation or false if plugin is not active.
     */
    public static function get_woocommerce_data() {

        if ( class_exists( 'WooCommerce' ) && defined( 'WC_PLUGIN_FILE' ) ) {
            return self::get_plugin_data( WC_PLUGIN_FILE );
        }

        return false;
    }

    /**
     * Loads admin template part.
     *
     * @param string $name Template name relative to `templates/admin/parts` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @return string
     */
    public static function locate_admin_template_part( $name, $load = false, $once = true ) {

        return self::locate_admin_template( 'parts/' . $name, $load, $once );
    }

    /**
     * Loads admin template.
     *
     * @param string $name Template name relative to `templates/admin` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @return string
     */
    public static function locate_admin_template( $name, $load = false, $once = true ) {

        //phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        $template = WWOF_PLUGIN_DIR_PATH . 'templates/admin/' . $name;
        if ( ! file_exists( $template ) ) {
            return '';
        }

        if ( $load ) {
            if ( $once ) {
                require_once $template;
            } else {
                require $template;
            }
        }

        //phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        return $template;
    }

    /**
     * Add allowed HTML tags for order form app use.
     *
     * @param array  $tags    Allowed HTML tags and their attributes.
     * @param string $context Context name.
     *
     * @since 3.0
     * @return mixed
     */
    public static function order_form_app_kses_allowed_html( $tags, $context ) {

        if ( 'post' === $context ) {
            if ( ! empty( $tags['bdo'] ) ) {
                $tags['bdi'] = $tags['bdo'];
            } else {
                $tags['bdi'] = array();
            }
        }

        return $tags;
    }

    /**
     * Get current app user role.
     *
     * @since 3.0
     * @return mixed|string|null
     */
    public static function get_app_user() {

        return current_user_can( 'manage_woocommerce' ) ? 'admin' : ( wp_get_current_user()->roles[0] ?? null );
    }

    /**
     * Order Form App frontend and backend common JS app localization properties.
     *
     * @param array $merge Additional data to merge.
     *
     * @since 3.0
     * @return array
     */
    public static function order_form_app_common_l10n( $merge = array() ) {

        global $wc_wholesale_prices;

        $wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        $app_user = self::get_app_user();

        $constant_properties = array(
            'appContext'     => 'view',
            'isRTL'          => is_rtl(),
            'wpNonce'        => wp_create_nonce( 'wp_rest' ),
            'wcNonce'        => wp_create_nonce( 'wc_store_api' ),
            'restUrl'        => rest_url(),
            'restNamespace'  => Order_Form_REST::instance()->namespace,
            'loginUrl'       => wp_login_url(),
            'placeholderImg' => wc_placeholder_img_src(),
            'appUser'        => $app_user,
            'userFavourites' => self::get_favourite_products(),
            'wholesaleRoles' => array_keys( $wholesale_roles ),
            'i18n'           => require WWOF_PLUGIN_DIR_PATH . 'includes/I18n/order-form.php',
        );

        $constant_properties = array_merge(
            $constant_properties,
            WC_Helper::get_script_l10n(),
            WWP_Helper::get_script_l10n(),
            WWPP_Helper::get_script_l10n(),
        );

        add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'order_form_app_kses_allowed_html' ), 10, 2 );
        $allowed_tags = array_keys( wp_kses_allowed_html( 'post' ) );
        sort( $allowed_tags );

        $allowed_attrs = array_keys( array_merge( ...array_values( wp_kses_allowed_html( 'post' ) ) ) );
        sort( $allowed_attrs );

        /**
         * Filter the order form app common l10n defaults.
         *
         * @param array $defaults The default order form app common l10n properties.
         *
         * @since 3.0
         */
        $defaults = apply_filters(
            'wwof_order_form_app_common_l10n_defaults',
            array(
                'maxRequests'       => 8,
                'allowedTags'       => $allowed_tags,
                'allowedAttrs'      => $allowed_attrs,
                '_fields'           => array(
                    'attribute_summary',
                    'attributes',
                    'backorders',
                    'categories',
                    'default_attributes',
                    'id',
                    'images',
                    'image',
                    'key',
                    'low_stock_amount',
                    'manage_stock',
                    'menu_order',
                    'meta_data',
                    'name',
                    'parent_id',
                    'permalink',
                    'price',
                    'price_html',
                    'product_variations',
                    'short_description',
                    'sku',
                    'stock_quantity',
                    'stock_status',
                    'type',
                    'variations',
                    'wholesale_data',
                ),
                'thirdPartyPlugins' => array(),
            )
        );

        $defaults = wp_parse_args( $defaults, $constant_properties );

        remove_filter( 'wp_kses_allowed_html', array( __CLASS__, 'order_form_app_kses_allowed_html' ) );

        return wp_parse_args( $merge, $defaults );
    }

    /**
     * Get current WWOF plugin version.
     *
     * @since 3.0
     * @return string
     */
    public static function get_current_plugin_version() {

        return self::get_plugin_data()['Version'];
    }

    /**
     * Get current WWOF plugin data.
     *
     * @param bool $markup        Optional. If the returned data should have HTML markup applied.
     *                            Default false.
     * @param bool $translate     Optional. If the returned data should be translated. Default false.
     *
     * @since 3.0
     * @return array
     */
    public static function get_plugin_data( $markup = false, $translate = false ) {

        return get_plugin_data( WWOF_PLUGIN_FILE, $markup, $translate );
    }

    /**
     * Get order forms created for WWOF v2.0.
     *
     * @param array $query_args Optional. WP Query arguments. Default empty array.
     *
     * @since 3.0
     * @return int[]
     */
    public static function get_v2_forms( $query_args = array() ) {

        $defaults = array(
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'form_body',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'editor_area',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $args = wp_parse_args( $query_args, $defaults );
        if ( ! empty( $args['post__in'] ) ) {
            unset( $args['meta_query'] );
        }

        $args['post_type'] = Order_Form::POST_TYPE;

        return get_posts( $args );
    }

    /**
     * Get order forms created for WWOF v3.0.
     *
     * @param array $query_args WP_Query args.
     *
     * @since 3.0
     * @return int[]|WP_Post[]
     */
    public static function get_forms( $query_args = array() ) {

        $defaults = array(
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_type'      => Order_Form::POST_TYPE,
            'meta_query'     => array(
                array(
                    'key'     => 'form_body',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        return get_posts( wp_parse_args( $query_args, $defaults ) );
    }

    /**
     * Log an error message.
     *
     * @param string $message The message to log.
     *
     * @since 3.0
     * @return void
     */
    public static function log_error( $message ) {

        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->error( $message, array( 'source' => 'wwof' ) );
        }
    }

    /**
     * Generate a unique id.
     *
     * @param array $data The whole old data array.
     *
     * @since 3.0
     * @return string
     */
    public static function generate_id( $data ) {

        try {
            $id = random_bytes( 5 );
            $id = mb_substr( bin2hex( $id ), 0, 5 );
        } catch ( \Exception $e ) {
            $id = mb_substr( md5( wp_json_encode( $data ) ), 0, 5 );
        }

        return $id;
    }

    /**
     * Get WordPress page by title.
     * This is a helper method for to replace the deprecated WordPress `get_page_by_title` function in 6.2.
     *
     * @see https://make.wordpress.org/core/2023/03/06/get_page_by_title-deprecated/
     *
     * @param string $title The title of the page to get.
     *
     * @return WP_Post|null
     */
    public static function get_page_by_title( $title ) {

        $page_args  = array(
            'post_type'              => 'page',
            'title'                  => $title,
            'post_status'            => 'all',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'orderby'                => 'post_date ID',
            'order'                  => 'ASC',
        );
        $page_query = new WP_Query( $page_args );

        return ! empty( $page_query->post ) ? $page_query->post : null;
    }

    /**
     * Get favourite products.
     *
     * @param int $user_id The user ID.
     *
     * @since 3.0.5
     * @return array
     */
    public static function get_favourite_products( $user_id = null ) {

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $favourites = (array) get_user_meta( $user_id, self::FAVOURITES_META_KEY, true );

        return array_filter( $favourites );
    }

    /**
     * Save a product to favourites.
     *
     * @param int $product_id The product ID.
     * @param int $user_id    The user ID.
     *
     * @since 3.0.5
     * @return bool|int
     */
    public static function save_favourite_product( $product_id, $user_id = null ) {

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $product_ids = (array) get_user_meta( $user_id, self::FAVOURITES_META_KEY, true );
        $product_ids = array_filter( $product_ids );
        if ( empty( $product_ids ) ) {
            $product_ids = array();
        }

        $product_id = absint( $product_id );
        $product    = wc_get_product( $product_id );
        if ( ! in_array( $product_id, $product_ids, true ) ) {
            $product_ids[] = $product_id;
        }
        if ( $product && $product->is_type( 'variable' ) ) {
            $variations = $product->get_children();
            if ( ! empty( $variations ) ) {
                $product_ids = array_merge( $product_ids, array_map( 'absint', $variations ) );
                $product_ids = array_flip( array_flip( $product_ids ) );
            }
        }

        return update_user_meta( $user_id, self::FAVOURITES_META_KEY, array_values( $product_ids ) );
    }

    /**
     * Remove a product from favourites.
     *
     * @param int $product_id The product ID.
     * @param int $user_id    The user ID.
     *
     * @since 3.0.5
     * @return bool|int
     */
    public static function remove_favourite_product( $product_id, $user_id = null ) {

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $product_ids = (array) get_user_meta( $user_id, self::FAVOURITES_META_KEY, true );
        $product_ids = array_filter( $product_ids );
        if ( empty( $product_ids ) ) {
            $product_ids = array();
        }

        $product_id = (int) $product_id;
        $key        = array_search( $product_id, $product_ids, true );
        if ( false === $key ) {
            return false;
        }

        unset( $product_ids[ $key ] );
        $product_ids = array_values( $product_ids );

        return update_user_meta( $user_id, self::FAVOURITES_META_KEY, $product_ids );
    }

    /**
     * Get the getting started notice status.
     *
     * @since 3.0.5
     * @return false|mixed|null
     */
    public static function get_getting_started_notice() {

        return get_option( 'wwof_admin_notice_getting_started_show', 'yes' );
    }

    /**
     * Update the getting started notice status.
     *
     * @param string $status The status of the getting started notice.
     *
     * @since 3.0.5
     * @return bool
     */
    public static function update_getting_started_notice( $status = 'yes' ) {

        return update_option( 'wwof_admin_notice_getting_started_show', $status );
    }
}
