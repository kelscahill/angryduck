<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_WC_Multilingual' ) ) {

    /**
     * Model that houses the logic of integrating with 'WooCommerce Multilingual' plugin.
     *
     * @since 1.27.7
     */
    class WWPP_WC_Multilingual {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
        */

        /**
         * Property that holds the single main instance of WWPP_WC_Composite_Product.
         *
         * @since 1.27.7
         * @access private
         * @var WWPP_WC_Multilingual
         */
        private static $_instance;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWPP_WC_Multilingual constructor.
         *
         * @since 1.27.7
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Multilingual model.
         */
        public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWPP_WC_Multilingual is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.27.7
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_WC_Multilingual model.
         * @return WWPP_WC_Multilingual
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * When variable product is being translated, WPML only copies one role for the 'wwpp_product_wholesale_visibility_filter' post meta.
         * This filter is fired after the product data sync, we need to ensure that the meta values are being added properly if it has multiple meta value.
         *
         * @since 1.27.7
         * @access public
         *
         * @param int    $original_product_id    Orignal product ID.
         * @param int    $tr_product_id          Translated product ID.
         * @param string $lang                   Language.
         */
        public function sync_product_wholesale_visibility_filter( $original_product_id, $tr_product_id, $lang ) {

            $original_product = wc_get_product( $original_product_id );

            if ( WWP_Helper_Functions::wwp_get_product_type( $original_product ) === 'variable' ) {

                global $iclTranslationManagement, $woocommerce_wpml;

                // WPML custom fields translation sync settings.
                $settings = $iclTranslationManagement->settings['custom_fields_translation'];

                // Check if 'wwpp_product_wholesale_visibility_filter' custom fields setting is set to 'Copy'.
                if ( isset( $settings[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ] ) && WPML_COPY_CUSTOM_FIELD === $settings[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ] ) {
                    $original_variations = $original_product->get_children();

                    foreach ( $original_variations as $original_variation_id ) {
                        $this->sync_variations_wholesale_visibility_filter( $original_variation_id, $lang );
                    }
                }
            }
        }

        /**
         * The Wholesale Exclusive Variation isn't being copied to the translated product, when the users saving from 'Save Changes' button on the Variations tab.
         * This will synchronize 'wwpp_product_wholesale_visibility_filter' post meta for variations.
         *
         * @since 1.27.7
         * @access public
         *
         * @param int $product_id product ID.
         */
        public function sync_product_variations_wholesale_visibility_filter( $product_id ) {
            global $wpdb, $woocommerce_wpml, $sitepress, $iclTranslationManagement;

            // WPML custom fields translation sync settings.
            $settings = $iclTranslationManagement->settings['custom_fields_translation'];

            // Check if 'wwpp_product_wholesale_visibility_filter' custom fields setting is set to 'Copy'.
            if ( isset( $settings[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ] ) && WPML_COPY_CUSTOM_FIELD === $settings[ WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ] ) {

                if ( $woocommerce_wpml->products->is_original_product( $product_id ) ) {

                    $trid = $sitepress->get_element_trid( $product_id, 'post_product' );

                    if ( empty( $trid ) ) {
                        $trid = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT trid FROM {$wpdb->prefix}icl_translations
                                            WHERE element_id = %d AND element_type = 'post_product'",
                                $product_id
                            )
                        );
                    }

                    $translations        = $sitepress->get_element_translations( $trid, 'post_product' );
                    $original_product    = wc_get_product( $product_id );
                    $original_variations = $original_product->get_children();

                    foreach ( $translations as $translation ) {
                        if ( ! $translation->original ) {

                            foreach ( $original_variations as $original_variation_id ) {
                                $this->sync_variations_wholesale_visibility_filter( $original_variation_id, $translation->language_code );
                            }
                        }
                    }
                }
            }
        }

        /**
         * Function to re-sync the 'wwpp_product_wholesale_visibility_filter' post meta for variations.
         * This function is used both on when the user update the product using the Publish tab and on the Variations tab.
         *
         * @since 1.27.7
         * @access public
         *
         * @param int    $variation_id Variation ID.
         * @param string $lang         Language.
         */
        public function sync_variations_wholesale_visibility_filter( $variation_id, $lang ) {
            global $woocommerce_wpml;

            // Get variation product object.
            $variation = wc_get_product( $variation_id );

            $wholesale_visibility_filters = WWP_Helper_Functions::get_formatted_meta_data( $variation, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

            if ( empty( $wholesale_visibility_filters ) ) {
                return;
            }

            // Get translated variation ID by language from original variation.
            $tr_variation_id = $woocommerce_wpml->sync_variations_data->get_variation_id_by_lang( $lang, $variation_id );

            // Get translated product object.
            $tr_variation = wc_get_product( $tr_variation_id );

            // Because we are adding post meta via add_meta_data
            // We make sure to delete old post meta so the meta won't contains duplicate values.
            $tr_variation->delete_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

            foreach ( $wholesale_visibility_filters as $wholesale_visibility_filter ) {
                $tr_variation->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, $wholesale_visibility_filter );
            }

            // Save translated variation.
            $tr_variation->save();
        }

        /**
         * Execute model.
         *
         * @since 1.27.7
         * @access public
         */
        public function run() {

            if ( WWP_Helper_Functions::is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {

                add_action( 'wcml_after_sync_product_data', array( $this, 'sync_product_wholesale_visibility_filter' ), 10, 3 );

                add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'sync_product_variations_wholesale_visibility_filter' ), 20, 1 );

            }
        }
    }

}
