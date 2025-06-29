<?php if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

if (!class_exists('WWPP_WC_Products_Atributes_Variations')) {

    /**
     * Model that houses the logic of integrating with 'WooCommerce Product Bundles' plugin.
     *
     * Bundle products just inherits from simple product so that's why they are very similar.
     * So most of the codebase here are just reusing the codes from simple product.
     *
     * @since 1.25
     */
    class WWPP_WC_Products_Atributes_Variations {

        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_WC_Composite_Product.
         *
         * @since 1.25
         * @access private
         * @var WWPP_WC_Composite_Product
         */
        private static $_instance;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_WC_Composite_Product constructor.
         *
         * @since 1.25
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Composite_Product model.
         */
        public function __construct($dependencies) {}

        /**
         * Ensure that only one instance of WWPP_WC_Composite_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.25
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Composite_Product model.
         * @return WWPP_WC_Composite_Product
         */
        public static function instance($dependencies = array()) {

            if (!self::$_instance instanceof self) {
                self::$_instance = new self($dependencies);
            }

            return self::$_instance;

        }

        /**
         * Hide main variable products.
         *
         * @since 1.25
         * @access public
         *
         * @param array $post__in
         * @param array $args
         * @return array
         */
        public function pre_get_post__in($post__in, $args) {

            if (
                get_option('afwssv_enable_single_variation') == 'yes' &&
                get_option('afwssv_hide_main_product') == 'yes'
            ) {

                $afwssv_applied_on_products = unserialize(get_option('afwssv_applied_on_products'));
                $afwssv_applied_on_categories = unserialize(get_option('afwssv_applied_on_categories'));

                if (!empty($afwssv_applied_on_categories)) {

                    $cat_slugs = array();
                    foreach ($afwssv_applied_on_categories as $cat_id) {
                        $cat = get_term($cat_id);
                        if ($cat) {
                            $cat_slugs[] = $cat->slug;
                        }
                    }

                    $args = array(
                        'type' => 'variable',
                        'status' => 'publish',
                        'limit' => -1,
                        'return' => 'ids',
                        'category' => $cat_slugs,
                    );

                    $products = wc_get_products($args);

                    if (!empty($afwssv_applied_on_products)) {
                        $products = array_unique(array_merge($products, $afwssv_applied_on_products));
                    }

                    $post__in = array_unique(array_diff($post__in, $products));

                } else {

                    $variable_products = array();

                    if (!empty($afwssv_applied_on_products)) {
                        $variable_products = $afwssv_applied_on_products;
                    } else {
                        // Get variable products.
                        $args = array(
                            'type' => 'variable',
                            'return' => 'ids',
                        );

                        $variable_products = wc_get_products($args);
                    }

                    $post__in = array_diff($post__in, $variable_products);
                }
            }

            return $post__in;

        }

        /**
         * Show variations on specfic product or via category.
         *
         * @since 1.25
         * @access public
         *
         * @param array $product_ids
         * @return array
         */
        public function filter_product_ids_to_get_variations($product_ids) {

            if (get_option('afwssv_enable_single_variation') == 'yes') {

                $afwssv_applied_on_products = unserialize(get_option('afwssv_applied_on_products'));
                $filtered_ids = array();

                $afwssv_applied_on_categories = unserialize(get_option('afwssv_applied_on_categories'));

                if (!empty($afwssv_applied_on_categories)) {

                    $cat_slugs = array();
                    foreach ($afwssv_applied_on_categories as $cat_id) {
                        $cat = get_term($cat_id);
                        if ($cat) {
                            $cat_slugs[] = $cat->slug;
                        }
                    }

                    $args = array(
                        'type' => 'variable',
                        'status' => 'publish',
                        'limit' => -1,
                        'return' => 'ids',
                        'category' => $cat_slugs,
                    );

                    $filtered_ids = wc_get_products($args);

                    if (!empty($afwssv_applied_on_products)) {
                        $filtered_ids = array_unique(array_merge($filtered_ids, $afwssv_applied_on_products));
                    }

                } else {
                    if (!empty($afwssv_applied_on_products)) {
                        $filtered_ids = $afwssv_applied_on_products;
                    }
                }

                return !empty($filtered_ids) ? $filtered_ids : $product_ids;

            }

            return $product_ids;

        }

        /*
        |--------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.25
         * @access public
         */
        public function run() {

            if (is_plugin_active('show-products-by-attributes-variations/addify_show_variation_single_product.php')) {

                // Filter post__in property
                add_filter('wwpp_pre_get_post__in', array($this, 'pre_get_post__in'), 10, 2);

                // Products By Attributes & Variations for WooCommerce - Applied On Products option
                add_filter('wwpp_get_variation_ids_via_product_ids', array($this, 'filter_product_ids_to_get_variations'));

            }

        }

    }

}
