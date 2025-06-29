<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of bootstrapping the plugin.
 *
 * @since 1.13.0
 */
class WWPP_Bootstrap {


    /**
     * Class properties
     */

    /**
     * Property that holds the single main instance of WWPP_Bootstrap.
     *
     * @since 1.13.0
     * @access private
     * @var WWPP_Bootstrap
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information of the license.
     *
     * @since 1.30.4
     * @access private
     * @var WWPP_WWS_License_Manager
     */
    private $_wwpp_wws_license_manager;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since 1.13.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private $_wwpp_wholesale_roles;

    /**
     * Model that houses the logic relating to payment gateways.
     *
     * @since 1.13.0
     * @access private
     * @var WWPP_Wholesale_Role_Payment_Gateway
     */
    private $_wwpp_wholesale_role_payment_gateway;

    /**
     * Array of registered wholesale roles.
     *
     * @since 1.13.0
     * @access private
     * @var array
     */
    private $_registered_wholesale_roles;

    /**
     * Current WWP version.
     *
     * @since 1.13.3
     * @access private
     * @var int
     */
    private $_wwpp_current_version;

    /**
     * Class Methods
     */

    /**
     * WWPP_Bootstrap constructor.
     *
     * @since 1.13.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Bootstrap model.
     */
    public function __construct( $dependencies ) {
        $this->_wwpp_wws_license_manager            = $dependencies['WWPP_WWS_License_Manager'];
        $this->_wwpp_wholesale_roles                = $dependencies['WWPP_Wholesale_Roles'];
        $this->_wwpp_wholesale_role_payment_gateway = $dependencies['WWPP_Wholesale_Role_Payment_Gateway'];
        $this->_wwpp_current_version                = $dependencies['WWPP_CURRENT_VERSION'];

        $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
    }

    /**
     * Ensure that only one instance of WWPP_Bootstrap is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.13.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Bootstrap model.
     * @return WWPP_Bootstrap
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Internationalization and Localization
     */

    /**
     * Load plugin text domain.
     *
     * @since 1.2.0
     * @since 1.13.0 Refactor codebase and move to its dedicated model.
     * @access public
     */
    public function load_plugin_text_domain() {
        load_plugin_textdomain( 'woocommerce-wholesale-prices-premium', false, WWPP_PLUGIN_BASE_PATH . 'languages/' );
    }

    /**
     * Bootstrap/Shutdown Functions
     */

    /**
     * Plugin activation hook callback.
     *
     * @since 1.0.0
     * @since 1.12.5 Add flush rewrite rules
     * @since 1.13.0 Add multisite support
     * @param bool $network_wide Activate network wide on multisite.
     * @access public
     */
    public function activate( $network_wide ) {

        global $wpdb;

        if ( is_multisite() ) {

            if ( $network_wide ) {

                // Get ids of all sites.
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                // Iterate through blogs on this multisite instance and activate.
                foreach ( $blog_ids as $blog_id ) {
                    switch_to_blog( $blog_id );
                    $this->_activate( $blog_id );
                }

                restore_current_blog();
            } else {
                // Activate on specific blog.
                $this->_activate( $wpdb->blogid );
            }
        } else {
            // Activate on specific blog.
            $this->_activate( $wpdb->blogid );
        }
    }

    /**
     * Plugin activation codebase.
     *
     * @since 1.13.0
     * @since 1.17 Refactor support for multisite setup.
     * @access private
     *
     * @param int $blog_id Site id.
     */
    private function _activate( $blog_id ) { // phpcs:ignore

        /**
         * Previously multisite installs site store license options using normal get/add/update_option functions.
         * These stores the option on a per sub-site basis. We need move these options network wide in multisite setup
         * via get/add/update_site_option functions.
         */
        if ( is_multisite() ) {

            $license_email     = get_option( WWPP_OPTION_LICENSE_EMAIL );
            $license_key       = get_option( WWPP_OPTION_LICENSE_KEY );
            $installed_version = get_option( WWPP_OPTION_INSTALLED_VERSION );

            if ( $license_email ) {
                update_site_option( WWPP_OPTION_LICENSE_EMAIL, $license_email );
                delete_option( WWPP_OPTION_LICENSE_EMAIL );
            }

            if ( $license_key ) {
                update_site_option( WWPP_OPTION_LICENSE_KEY, $license_key );
                delete_option( WWPP_OPTION_LICENSE_KEY );
            }

            if ( $installed_version ) {
                update_site_option( WWPP_OPTION_INSTALLED_VERSION, $installed_version );
                delete_option( WWPP_OPTION_INSTALLED_VERSION );
            }
        }

        // Set some default settings.
        if ( ! get_option( 'wwpp_admin_notice_getting_started_show', false ) ) {
            update_option( 'wwpp_admin_notice_getting_started_show', 'yes', 'no' );
        }

        if ( ! get_option( 'wwpp_settings_wholesale_price_title_text', false ) ) {
            update_option( 'wwpp_settings_wholesale_price_title_text', 'Wholesale Price:', 'no' );
        }

        if ( ! get_option( 'wwpp_settings_variable_product_price_display', false ) ) {
            update_option( 'wwpp_settings_variable_product_price_display', 'price-range', 'no' );
        }

        if ( ! get_option( 'wwpp_settings_show_saving_amount_text', false ) ) {
            update_option( 'wwpp_settings_show_saving_amount_text', 'You are saving {saved_amount} ({saved_percentage}) off RRP on this order', 'no' );
        }

        // Initialize product visibility related meta.
        wp_schedule_single_event( time(), WWPP_CRON_INITIALIZE_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

        // Set all existing payment tokens as not default.
        $this->_wwpp_wholesale_role_payment_gateway->undefault_existing_payment_tokens();

        // Flush rewrite rules after activation.
        flush_rewrite_rules();

        // Register action scheduler to handle license check every 24 hours,
        // Check if license action scheduler is already registered.
        if ( ! as_next_scheduled_action( 'wws_license_check' ) ) {
            // Enqueue license check action.
            as_enqueue_async_action( 'wws_license_check', array(), 'wws_license_check', true );

            // Schedule license check, randomize the time in a day to avoid multiple sites checking at the same time.
            as_schedule_recurring_action( time() + wp_rand( 0, 86400 ), 86400, 'wws_license_check', array(), 'wws_license_check', true );
        }

        // Record the installed version & date in the DB.
        if ( is_multisite() ) {
            update_site_option( 'wwpp_option_installed_version', $this->_wwpp_current_version );

            // Set the last license check.
            if ( ! get_site_option( WWPP_LAST_LICENSE_CHECK, false ) ) {
                update_site_option( WWPP_LAST_LICENSE_CHECK, time() );
            }
        } else {
            update_option( 'wwpp_option_installed_version', $this->_wwpp_current_version, 'no' );

            // Set the last license check.
            if ( ! get_option( WWPP_LAST_LICENSE_CHECK, false ) ) {
                update_option( WWPP_LAST_LICENSE_CHECK, time(), 'no' );
            }
        }

        /**
         * Clear WC Transients on activation
         * This is required by 'filter_available_variable_product_variations'. If we don't clear the product transients
         * at this point woocommerce_get_children function won't be triggered which means our function
         * 'filter_available_variable_product_variations' will not be executed as WC will just continue to use the
         * transient data. We only need to do this on plugin activation since every subsequent product update, it
         * will clear the transient for that specific product.
         */
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients();
        }

        // Record that we ran the activation code so we don't run it again.
        update_option( 'wwpp_option_activation_code_triggered', 'yes', 'no' );
    }

    /**
     * Ajax wrapper function for re-initializing product visibility filter meta data on products. Called from the Help
     * settings page.
     *
     * @since 1.30.1 Created AJAX wrapper function for initialize_product_visibility_filter_meta
     * @access public
     * @return bool Operation status.
     */
    public function ajax_initialize_product_visibility_filter_meta() {
        // Only do this if we're calling via ajax.
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

            // Security check when calling via ajax endpoint.
            if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wwpp_initialize_product_visibility_filter_meta_nonce' ) ||
                ! current_user_can( 'manage_woocommerce' )
                ) {
                @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
                echo wp_json_encode(
                    array(
                        'status'  => 'error',
                        'message' => 'Security check failure.',
                    )
                );

                wp_die();
            } else {

                // Ready to go, call internal function to initialize product visibility meta.
                $this->initialize_product_visibility_filter_meta();

                // Return AJAX response.
                @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
                echo wp_json_encode( array( 'status' => 'success' ) );
                wp_die();
            }
        } else {
            // Just return if not ajax.
            return false;
        }
    }

    /**
     * Get all products and check if the product has no 'wwpp_product_wholesale_visibility_filter' meta key yet. If not,
     * then set a meta for the current product with a key of 'wwpp_product_wholesale_visibility_filter' and value of
     * 'all'. This indicates the product is available for viewing for all users of the site.
     *
     * @since 1.4.2
     * @since 1.13.0 Refactor codebase and move to its own model.
     * @since 1.14.0 Make it handle ajax callback 'wp_ajax_wwpp_initialize_product_visibility_meta'.
     * @since 1.23.9 Set <wholesale_role>_have_wholesale_price meta into the parent group product.
     * @since 1.30.1 Separated the AJAX call so this function can be called from anywhere.
     * @access public
     * @return bool Operation status.
     */
    public function initialize_product_visibility_filter_meta() {
        global $wpdb, $wc_wholesale_prices_premium, $wc_wholesale_prices;

        do_action( 'wwpp_before_initialize_product_visibility_filter_meta' );

        /**
         * In version 1.13.0 we refactored the Wholesale Exclusive Variation feature.
         * Now it is an enhanced select box instead of the old check box.
         * This gives us more flexibility including the 'all' value if no wholesale role is selected.
         * In light to this, we must migrate the old <wholesale_role>_exclusive_variation data to the new 'wwpp_product_visibility_filter'.
         */
        foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                     SELECT 
                        $wpdb->posts.ID, 
                        'wwpp_product_wholesale_visibility_filter', 
                        %s
                     FROM 
                        $wpdb->posts 
                     WHERE 
                        $wpdb->posts.post_type IN ('product_variation') 
                        AND $wpdb->posts.ID IN (
                          SELECT 
                            $wpdb->posts.ID 
                          FROM 
                            $wpdb->posts 
                            INNER JOIN $wpdb->postmeta ON (
                              $wpdb->posts.ID = $wpdb->postmeta.post_id
                            ) 
                          WHERE 
                            meta_key = %s
                            AND meta_value = 'yes'
                        )
                    ",
                    $role_key,
                    $role_key . '_exclusive_variation'
                )
            );
        }

        /**
         * Initialize wwpp_product_wholesale_visibility_filter meta
         * This meta is in charge of product visibility. We need to set this to 'all' as mostly
         * all imported products will not have this meta. Meaning, all imported products
         * with no 'wwpp_product_wholesale_visibility_filter' meta set is visible to all users by default.
         */
        $wpdb->query(
            "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
             SELECT 
                $wpdb->posts.ID, 
                'wwpp_product_wholesale_visibility_filter', 
                'all' 
             FROM 
                $wpdb->posts 
             WHERE 
                $wpdb->posts.post_type IN ('product', 'product_variation') 
                AND $wpdb->posts.ID NOT IN (
                    SELECT 
                        $wpdb->posts.ID 
                    FROM 
                        $wpdb->posts 
                        INNER JOIN $wpdb->postmeta ON (
                            $wpdb->posts.ID = $wpdb->postmeta.post_id
                        ) 
                    WHERE 
                    meta_key = 'wwpp_product_wholesale_visibility_filter'
                )"
        );

        /**
         * Address instances where the wwpp_product_wholesale_visibility_filter meta is present but have empty value.
         * This can possibly occur when importing products using external tool that tries to import meta data but fails to properly save the data.
         */
        $wpdb->query(
            "UPDATE 
                $wpdb->postmeta 
             SET 
                meta_value = 'all' 
             WHERE 
                meta_key = 'wwpp_product_wholesale_visibility_filter' 
                AND meta_value = ''"
        );

        /**
         * Properly set {wholesale_role}_have_wholesale_price meta
         * There will be cases where users import products from external sources and they
         * "set up" wholesale prices via external tools prior to importing
         * We need to handle those cases.
         */
        foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {

            // We need to delete prior to inserting, else we will have a stacked meta, same multiple meta for a single post.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM 
                        $wpdb->postmeta 
                    WHERE 
                        meta_key = %s
                    ",
                    $role_key . '_have_wholesale_price'
                )
            );

            // Delete wholesale price set by product cat.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM
                        $wpdb->postmeta
                     WHERE 
                        meta_key = %s
                    ",
                    $role_key . '_have_wholesale_price_set_by_product_cat'
                )
            );

            // Delete Variations with wholesale price meta. To avoid duplicates or non-existing variation id post.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM 
                        $wpdb->postmeta 
                     WHERE 
                        meta_key = %s
                    ",
                    $role_key . '_variations_with_wholesale_price'
                )
            );

            /**
             * Remove <wholesale_role>_wholesale_price in the variable product meta. This will cause visibility issue.
             * This scenario happens when a product was still a simple product type (added a wholesale price) and converted to variable.
             * The wholesale price is not gonna be use anymore so we need to delete it.
             */
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM 
                        $wpdb->postmeta 
                     WHERE 
                        meta_key = %s
                     AND post_id IN (
                        SELECT 
                            DISTINCT object_id 
                        FROM 
                            $wpdb->term_relationships tr 
                            LEFT JOIN $wpdb->terms terms ON terms.term_id = tr.term_taxonomy_id 
                        WHERE 
                            terms.name = 'variable'
                    )",
                    $role_key . '_wholesale_price'
                )
            );

            /**
             * Get all variations that has wholesale_price and assign the id of post meta to the parent variable post meta.
             * Set <wholesale_role>_variations_with_wholesale_price into post_parent / variable product that has wholesale variations.
             */
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                     SELECT 
                        p.post_parent, 
                        %s, 
                        p.ID 
                     FROM 
                        $wpdb->posts p 
                        LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID 
                     WHERE 
                        p.post_type = 'product_variation' 
                        AND pm.meta_key = %s 
                        AND pm.meta_value > 0
                    ",
                    $role_key . '_variations_with_wholesale_price',
                    $role_key . '_wholesale_price'
                )
            );

            // Insert have wholesale price.
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) 
                    SELECT 
                        $wpdb->posts.ID, 
                        %s, 
                        'yes' 
                    FROM 
                        $wpdb->posts 
                    WHERE 
                        $wpdb->posts.post_type = 'product' 
                        AND $wpdb->posts.ID IN (
                            SELECT 
                                DISTINCT $wpdb->postmeta.post_id 
                            FROM 
                                $wpdb->postmeta 
                            WHERE 
                                (
                                    (
                                        meta_key = %s
                                        AND meta_value > 0
                                    ) 
                                    OR (
                                        meta_key = %s
                                        AND meta_value != ''
                                    ) 
                                    OR (
                                        meta_key = %s
                                        AND meta_value = 'yes'
                                    )
                                )
                        )",
                    $role_key . '_have_wholesale_price',
                    $role_key . '_wholesale_price',
                    $role_key . '_variations_with_wholesale_price',
                    $role_key . '_have_wholesale_price_set_by_product_cat'
                )
            );

        }

        // Extra visibility fixes for other product types not covered above (Grouped products, Bundled products).

        // Get grouped products.
        $args = array(
            'type'   => 'grouped',
            'return' => 'ids',
            'limit'  => -1,
        );

        $grouped_products = wc_get_products( $args );

        if ( ! empty( $grouped_products ) ) {

            /**
             * Set parent group product <wholesale_role>_have_wholesale_price so that it will be visible when
             * "Only Show Wholesale Products To Wholesale Users" is enabled.
             */
            foreach ( $grouped_products as $product_id ) {
                $wc_wholesale_prices->wwp_wholesale_price_grouped_product->insert_have_wholesale_price_meta( $product_id );
            }
        }

        // Get bundled products.
        $bundle_args = array(
            'type'   => 'bundle',
            'return' => 'ids',
            'limit'  => -1,
        );

        $bundled_products = wc_get_products( $bundle_args );

        if ( ! empty( $bundled_products ) ) {

            /**
             * Set parent group product <wholesale_role>_have_wholesale_price so that it will be visible when
             * "Only Show Wholesale Products To Wholesale Users" is enabled
             */
            foreach ( $bundled_products as $bundle_product_id ) {
                $wc_wholesale_prices_premium->wwpp_wc_bundle_product->set_bundle_product_visibility_meta( $bundle_product_id );
            }
        }

        /**
         * Get all terms and set <wholesale_role>_have_wholesale_price and
         * <wholesale_role>_have_wholesale_price_set_by_product_cat on category level discounts
         */
        $product_terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            )
        );

        foreach ( $product_terms as $term ) {
            $category_discount = get_option( 'taxonomy_' . $term->term_id );

            if ( ! empty( $category_discount ) ) {
                $wholesale_role_with_discounts = array();

                foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
                    if ( isset( $category_discount[ $role_key . '_wholesale_discount' ] ) &&
                        ! empty( $category_discount[ $role_key . '_wholesale_discount' ] ) ) {
                        $wholesale_role_with_discounts[] = $role_key;
                    }
                }

                $category_discount_products = wc_get_products(
                    array(
                        'category' => array( $term->slug ),
                        'return'   => 'ids',
                    )
                );

                $category_discount_products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT 
                            DISTINCT p.ID 
                         FROM 
                            $wpdb->posts p 
                            LEFT JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id) 
                            LEFT JOIN $wpdb->term_relationships tr ON (p.ID = tr.object_id) 
                         WHERE 
                            p.post_status = 'publish' 
                            AND p.post_type = 'product' 
                            AND tr.term_taxonomy_id = %d",
                        $term->term_id
                    ),
                    ARRAY_A
                );

                if ( ! empty( $category_discount_products ) ) {
                    foreach ( $category_discount_products as $product ) {
                        foreach ( $wholesale_role_with_discounts as $role_key ) {
                            $wpdb->insert(
                                $wpdb->postmeta,
                                array(
                                    'post_id'    => $product['ID'],
                                    'meta_key'   => $role_key . '_have_wholesale_price',
                                    'meta_value' => 'yes',
                                )
                            );

                            $wpdb->insert(
                                $wpdb->postmeta,
                                array(
                                    'post_id'    => $product['ID'],
                                    'meta_key'   => $role_key . '_have_wholesale_price_set_by_product_cat',
                                    'meta_value' => 'yes',
                                )
                            );
                        }
                    }
                }
            }
        }

        // Clear product id cache.
        $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();

        // Clear WC Product Transients Cache.
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients();
        }

        $this->_initialize_wholesale_sale_prices_meta();

        do_action( 'wwpp_after_initialize_product_visibility_filter_meta' );

        return true;
    }

    /**
     * Add wholesale sale prices meta for simple and variable products that has been created on previous version.
     * '{$role_key}_have_wholesale_sale_price': determine if product has wholesale sale price,
     * '{$role_key}_variations_have_wholesale_sale_price': determine if the variations of the variable product has wholesale sale price,
     *
     * @since 1.30.1.1
     * @access public
     */
    private function _initialize_wholesale_sale_prices_meta() {
        global $wpdb, $wc_wholesale_prices_premium;

        foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            $product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT posts.ID FROM $wpdb->posts as posts
                            LEFT JOIN $wpdb->postmeta as postmeta ON posts.ID = postmeta.post_id
                        WHERE posts.post_type IN ('product', 'product_variation')
                            AND postmeta.meta_key = %s AND postmeta.meta_value > 0
                    ",
                    $role_key . '_wholesale_sale_price',
                )
            );

            if ( ! empty( $product_ids ) ) {
                foreach ( $product_ids as $product_id ) {
                    $product = wc_get_product( $product_id );

                    if ( 'variation' === $product->get_type() ) {
                        $variable_product_id = $product->get_parent_id();
                        $variable_product    = wc_get_product( $variable_product_id );

                        // Update product meta data to indicate that the product variable has wholesale sale price.
                        $variable_product->update_meta_data( "{$role_key}_variations_have_wholesale_sale_price", 'yes' );
                        $variable_product->save();
                    }

                    // Update product meta data to indicate that the product has wholesale sale price.
                    $product->update_meta_data( "{$role_key}_have_wholesale_sale_price", 'yes' );
                    $product->save();
                }
            }
        }

        $wc_wholesale_prices_premium->wwpp_wholesale_prices->scheduled_wholesale_sales();

        return true;
    }

    /**
     * New option to remove all unused product meta data when a role is removed.
     *
     * @since 1.23.9
     * @access public
     */
    public function wwpp_clear_unused_product_meta() {
        // Security check when calling via ajax endpoint.
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX &&
            (
                ! wp_verify_nonce( $_REQUEST['nonce'], 'wwpp_clear_unused_product_meta_nonce' ) ||
                ! current_user_can( 'manage_woocommerce' )
            ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'  => 'error',
                    'message' => 'Security check failure.',
                )
            );
            wp_die();
        }

        // Check current user can manage WC settings.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        global $wpdb;

        $existing_roles          = array();
        $wwpp_existing_meta_keys = array();

        foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            $existing_roles[]          = $role_key;
            $wwpp_existing_meta_keys[] = $role_key . '_wholesale_price';
            $wwpp_existing_meta_keys[] = $role_key . '_have_wholesale_price';
            $wwpp_existing_meta_keys[] = $role_key . '_wholesale_minimum_order_quantity';
            $wwpp_existing_meta_keys[] = $role_key . '_wholesale_order_quantity_step';
        }

        $wwpp_fields = $wpdb->get_results(
            "SELECT 
                $wpdb->postmeta.* 
             FROM 
                $wpdb->postmeta 
             WHERE 
                $wpdb->postmeta.meta_key LIKE '%_wholesale_price' 
                OR $wpdb->postmeta.meta_key LIKE '%_have_wholesale_price' 
                OR $wpdb->postmeta.meta_key LIKE '%_wholesale_minimum_order_quantity' 
                OR $wpdb->postmeta.meta_key LIKE '%_wholesale_order_quantity_step' 
                OR $wpdb->postmeta.meta_key = 'wwpp_product_wholesale_visibility_filter' 
                OR $wpdb->postmeta.meta_key = 'wwpp_post_meta_quantity_discount_rule_mapping'"
        );

        if ( ! empty( $wwpp_fields ) ) {

            foreach ( $wwpp_fields as $index => $obj ) {

                // Get product object.
                $product = wc_get_product( $obj->post_id );

                // Delete unused meta keys.
                switch ( $obj->meta_key ) {
                    case 'wwpp_product_wholesale_visibility_filter':
                        if ( 'all' !== $obj->meta_value && ! in_array( $obj->meta_value, $existing_roles, true ) ) {
                            $product->delete_meta_data_value( $obj->meta_key, $obj->meta_value );
                        }

                        break;
                    case 'wwpp_post_meta_quantity_discount_rule_mapping':
                        $mapping = maybe_unserialize( $obj->meta_value );
                        if ( $mapping ) {
                            foreach ( $mapping as $key => $map ) {
                                if ( ! in_array( $map['wholesale_role'], $existing_roles, true ) ) {
                                    unset( $mapping[ $key ] );
                                }
                            }
                        }

                        $product->update_meta_data( $obj->meta_key, $mapping );
                        break;

                    default:
                        if ( ! in_array( $obj->meta_key, $wwpp_existing_meta_keys, true ) ) {
                            $product->delete_meta_data( $obj->meta_key );
                        }

                        break;
                }

                // Save product.
                $product->save();
            }
        }

        $this->initialize_product_visibility_filter_meta();

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode( array( 'status' => 'success' ) );
            wp_die();
        } else {
            return true;
        }
    }

    /**
     * Plugin deactivation hook callback.
     *
     * @since 1.0.0
     * @since 1.12.5 Add flush rewrite rules.
     * @since 1.13.0 Add multisite support.
     *
     * @param bool $network_wide Deactivate network wide or not.
     * @access public
     */
    public function deactivate( $network_wide ) {
        global $wpdb;

        // check if it is a multisite network.
        if ( is_multisite() ) {

            // check if the plugin has been activated on the network or on a single site.
            if ( $network_wide ) {

                // get ids of all sites.
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                foreach ( $blog_ids as $blog_id ) {
                    switch_to_blog( $blog_id );
                    $this->_deactivate( $wpdb->blogid );

                }

                restore_current_blog();
            } else {
                // activated on a single site, in a multi-site.
                $this->_deactivate( $wpdb->blogid );
            }
        } else {
            // activated on a single site.
            $this->_deactivate( $wpdb->blogid );
        }
    }

    /**
     * Remove <wholesale_role>_have_wholesale_price on plugin deactivation only if <wholesale_role>_wholesale_price has empty value.
     * This is a fix in the api update where the fetched non wholesale products will still return coz of that meta.
     *
     * @since 1.24.8
     * @access public
     */
    public function remove_have_wholesale_price_meta_on_deactivation() {
        global $wpdb;

        foreach ( $this->_registered_wholesale_roles as $role_key => $role ) {
            $args = array(
                'post_type'      => array( 'product', 'product_variation' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => $role_key . '_have_wholesale_price',
                        'value'   => 'yes',
                        'compare' => '=',
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key'     => $role_key . '_wholesale_price',
                            'value'   => '',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => $role_key . '_wholesale_price',
                            'value'   => 'gebbirish',
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                ),
            );

            $query = new WP_Query( $args );

            if ( ! empty( $query->posts ) ) {
                $ids = "'" . implode( "','", $query->posts ) . "'";
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Ignored for allowing interpolation in IN query.
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM 
                            $wpdb->postmeta 
                         WHERE 
                            post_id IN ( $ids )
                            AND meta_key = %s",
                        $role_key . '_have_wholesale_price'
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared.
            }
        }
    }

    /**
     * Plugin deactivation codebase.
     *
     * @since 1.13.0
     * @access public
     *
     * @param int $blog_id Site id.
     */
    private function _deactivate( $blog_id ) { // phpcs:ignore
        flush_rewrite_rules();
        wc_delete_product_transients();
        $this->remove_action_scheduler();
    }

    /**
     * Remove all scheduled actions for the plugin.
     *
     * @since 1.13.0
     * @access public
     */
    public function remove_action_scheduler() {
        as_unschedule_all_actions( 'wws_license_check' );

        if ( class_exists( 'ActionScheduler' ) ) {
            $hook_names = array(
                'wws_license_check',
            );

            foreach ( $hook_names as $hook_name ) {
                $args = array(
                    'hook'     => $hook_name,
                    'status'   => ActionScheduler_Store::STATUS_PENDING,
                    'orderby'  => 'scheduled_date',
                    'order'    => 'ASC',
                    'per_page' => 100,
                );

                $actions = ActionScheduler::store()->query_actions( $args );
                if ( ! empty( $actions ) ) {
                    foreach ( $actions as $action_id ) {
                        ActionScheduler::store()->cancel_action( $action_id );
                        ActionScheduler::store()->delete_action( $action_id );
                    }
                }
            }
        }
    }

    /**
     * Method to initialize a newly created site in a multi site set up.
     *
     * @since 1.13.0
     * @access public
     *
     * @param int    $blog_id Blog ID.
     * @param int    $user_id User ID.
     * @param string $domain  Site domain.
     * @param string $path    Site path.
     * @param int    $site_id Site ID. Only relevant on multi-network installs.
     * @param array  $meta    Meta data. Used to set initial site options.
     */
    public function new_mu_site_init( $blog_id, $user_id, $domain, $path, $site_id, $meta ) { // phpcs:ignore
        if ( is_plugin_active_for_network( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' ) ) {
            switch_to_blog( $blog_id );
            $this->_activate( $blog_id );
            restore_current_blog();
        }
    }

    /**
     * Plugin initializaton.
     *
     * @since 1.2.9
     * @since 1.13.0 Add multi-site support.
     */
    public function initialize() {
        /**
         * Check if activation has been triggered, if not trigger it. Activation codes are not triggered if plugin
         * dependencies are not present and this plugin is activated.
         */
        $installed_version = is_multisite() ?
            get_site_option( 'wwpp_option_installed_version', false ) :
            get_option( 'wwpp_option_installed_version', false );

        if ( version_compare( $installed_version, $this->_wwpp_current_version, '!=' ) ||
            get_option( 'wwpp_option_activation_code_triggered', false ) !== 'yes' ) {

            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            $network_wide = is_plugin_active_for_network( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' );
            $this->activate( $network_wide );

            $this->clear_unused_role_properties();

            // Initialize visibility meta.
            $this->initialize_product_visibility_filter_meta();

            // Turn off autoload for all options.
            $this->_turn_off_autoload_options();
        }
    }

    /**
     * Remove 'shippingClassName' and 'shippingClassTermId' from role properties on plugin update.
     *
     * @since 1.23.9
     */
    public function clear_unused_role_properties() {
        if ( '1.23.9' === $this->_wwpp_current_version ) {

            $all_registered_wholesale_roles  = maybe_unserialize( get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES ) );
            $all_registered_wholesale_roles2 = $all_registered_wholesale_roles;

            foreach ( $all_registered_wholesale_roles2 as $role_key => $data ) {
                unset( $data['shippingClassName'] );
                unset( $data['shippingClassTermId'] );
                $all_registered_wholesale_roles[ $role_key ] = $data;
            }

            update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, maybe_serialize( $all_registered_wholesale_roles ), 'no' );
        }
    }

    /**
     * Getting Started notice on plugin activation.
     *
     * @since 1.24
     * @since 1.27.3 Display notices in the new top level menu.
     * @access public
     */
    public function wwpp_getting_started_notice() {
        /**
         * Check if current user is admin or shop manager
         * Check if getting started option is 'yes'
         */
        if ( ( current_user_can( 'manage_woocommerce' ) ) &&
            ( get_option( 'wwpp_admin_notice_getting_started_show' ) === 'yes' ||
            get_option( 'wwpp_admin_notice_getting_started_show' ) === false ) ) {

            $screen = get_current_screen();

            /**
             * Check if WWS license page
             * Check if products pages
             * Check if woocommerce pages ( wc, products, analytics )
             * Check if plugins page
             */
            if (
                in_array(
                    $screen->id,
                    array(
                        'wholesale_page_order-forms',
                        'wholesale_page_wholesale-settings',
                        'settings_page_wws-license-settings',
                        'wholesale_page_wwpp-wholesale-roles-page',
                        'wholesale_page_wws-license-settings',
                    ),
                    true
                ) ||
                'product' === $screen->post_type ||
                in_array( $screen->parent_base, array( 'woocommerce', 'plugins' ), true )
            ) {
                ?>

                <div class="updated notice wwpp-getting-started">
                    <p><img src="<?php echo esc_url( WWP_IMAGES_URL ); ?>wholesale-suite-activation-notice-logo.png" alt=""/></p>
                    <p><?php esc_html_e( 'Thank you for purchasing WooCommerce Wholesale Prices Premium – you now have a whole range of extra wholesale pricing, product and ordering features available.', 'woocommerce-wholesale-prices-premium' ); ?>
                    <p><?php esc_html_e( 'A great place to get started is with our official guide to the Premium add-on. Click through below and it will take you through all you need to know and where to get extra assistance if you need it.', 'woocommerce-wholesale-prices-premium' ); ?>
                    <p><a href="https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-premium-getting-started-guide/?utm_source=wwpp&utm_medium=kb&utm_campaign=wwppgettingstarted" target="_blank">
                        <?php esc_html_e( 'Read the Getting Started guide', 'woocommerce-wholesale-prices-premium' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                    </a></p>
                    <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'woocommerce-wholesale-prices-premium' ); ?></span></button>
                </div>
                <?php
            }
        }
    }

    /**
     * Remove WWP Getting Started notice.
     *
     * @since 1.24
     * @access public
     */
    public function remove_wwp_getting_started_notice() {
        global $wc_wholesale_prices;

        if ( $wc_wholesale_prices ) {
            remove_action( 'admin_notices', array( $wc_wholesale_prices->wwp_bootstrap, 'getting_started_notice' ), 10 );
        }
    }

    /**
     * Hide WWPP getting started notice on close.
     *
     * @since 1.24
     * @access public
     */
    public function wwpp_getting_started_notice_hide() {
        // Make sure we are doing an AJAX call.
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            // Make sure user has the appropriate capability.
            if ( current_user_can( 'manage_woocommerce' ) ) {
                // Hide WWP and WWPP notices.
                update_option( 'wwp_admin_notice_getting_started_show', 'no', 'no' );
                update_option( 'wwpp_admin_notice_getting_started_show', 'no', 'no' );
                wp_send_json( array( 'status' => 'success' ) );
            } else {
                wp_send_json( array( 'status' => 'error' ) );
            }

            wp_die();
        }
    }

    /**
     * Plugin Custom Action Links
     */

    /**
     * Add plugin listing custom action link (settings).
     *
     * @since 1.0.2
     * @since 1.12.8 Rename 'Plugin Settings' and 'License Settings' to just 'Settings' and 'Licence' respectively.
     * @since 1.14.0 Move to its proper model.
     * @access public
     *
     * @param array  $links Array of links.
     * @param string $file  Plugin basename.
     * @return array Filtered array of links.
     */
    public function add_plugin_listing_custom_action_links( $links, $file ) {

        // If WWP min requirement is not met don't display this extra links when WWPP is activated.
        if ( get_option( 'wwp_running' ) !== 'yes' ) {
            return $links;
        }

        if ( plugin_basename( WWPP_PLUGIN_PATH . 'woocommerce-wholesale-prices-premium.bootstrap.php' ) === $file ) {
            if ( ! is_multisite() ) {
                if ( $this->_wwpp_wws_license_manager->is_license_status( 'expired' ) ) {
                    $license_data      = $this->_wwpp_wws_license_manager->get_wws_license_data( 'WWPP' );
                    $renew_license_url = add_query_arg(
                        array(
                            'utm_source'   => 'wwpp',
                            'utm_medium'   => 'drm',
                            'utm_campaign' => 'wwppdrmpluginlinkrenew',
                        ),
                        $license_data['management_url']
                    );

                    $renew_license_link = '<a href="' . esc_url( $renew_license_url ) . '">' . __( 'Renew License', 'woocommerce-wholesale-prices-premium' ) . '</a>';
                    array_unshift( $links, $renew_license_link );
                }

                $license_link = '<a href="options-general.php?page=wws-license-settings&tab=wwpp">' . __( 'License', 'woocommerce-wholesale-prices-premium' ) . '</a>';
                if ( method_exists( 'WWP_Helper_Functions', 'is_wwp_v2' ) && WWP_Helper_Functions::is_wwp_v2() ) {
                    $license_link = '<a href="admin.php?page=wws-license-settings&tab=wwpp">' . __( 'License', 'woocommerce-wholesale-prices-premium' ) . '</a>';
                }
                array_unshift( $links, $license_link );
            }

            $settings_link = '<a href="admin.php?page=wc-settings&tab=wwp_settings">' . __( 'Settings', 'woocommerce-wholesale-prices-premium' ) . '</a>';
            array_unshift( $links, $settings_link );

            $getting_started          = '<a href="https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-premium-getting-started-guide/?utm_source=wwpp&utm_medium=kb&utm_campaign=wwppgettingstarted" target="_blank">' .
                __( 'Getting Started', 'woocommerce-wholesale-prices-premium' ) .
                '</a>';
            $links['getting_started'] = $getting_started;
        }

        return $links;
    }

    /**
     * Execute Model
     */

    /**
     * Register model ajax handlers.
     *
     * @since 1.14.0
     * @access public
     */
    public function register_ajax_handler() {
        add_action( 'wp_ajax_wwpp_initialize_product_visibility_meta', array( $this, 'ajax_initialize_product_visibility_filter_meta' ) );
        add_action( 'wp_ajax_wwpp_clear_unused_product_meta', array( $this, 'wwpp_clear_unused_product_meta' ) );
    }

    /**
     * Turn off autoload options.
     *
     * @since 2.0.0.3
     * @access private
     */
    private function _turn_off_autoload_options() {
        global $wpdb;

        $like  = $wpdb->esc_like( 'wwpp_' ) . '%';
        $value = 'off';
        $query = $wpdb->prepare(
            "UPDATE {$wpdb->options} SET autoload = %s WHERE option_name LIKE %s",
            $value,
            $like
        );

        // Execute the query.
        $wpdb->query( $query ); // phpcs:ignore.
    }

    /**
     * Execute model.
     *
     * @since 1.13.0
     * @access public
     */
    public function run() {
        // Load Plugin Text Domain.
        add_action( 'init', array( $this, 'load_plugin_text_domain' ) );

        register_activation_hook( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium.bootstrap.php', array( $this, 'activate' ) );
        register_deactivation_hook( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices-premium.bootstrap.php', array( $this, 'deactivate' ) );

        // Execute plugin initialization ( plugin activation ) on every newly created site in a multi site set up.
        add_action( 'wpmu_new_blog', array( $this, 'new_mu_site_init' ), 10, 6 );

        // Initialize Plugin.
        add_action( 'init', array( $this, 'initialize' ) );
        add_action( WWPP_CRON_INITIALIZE_PRODUCT_WHOLESALE_VISIBILITY_FILTER, array( $this, 'initialize_product_visibility_filter_meta' ) );
        add_action( 'init', array( $this, 'register_ajax_handler' ) );
        add_filter( 'plugin_action_links', array( $this, 'add_plugin_listing_custom_action_links' ), 10, 2 );

        // Getting Started notice.
        add_action( 'init', array( $this, 'remove_wwp_getting_started_notice' ) );
        add_action( 'admin_notices', array( $this, 'wwpp_getting_started_notice' ), 10 );
        add_action( 'wp_ajax_wwpp_getting_started_notice_hide', array( $this, 'wwpp_getting_started_notice_hide' ) );
    }
}
