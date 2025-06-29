<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Helpers\WPML as WPML_Helper;

/**
 * WPML class.
 *
 * @since 3.0
 */
class WPML extends Abstract_Class {

    /**
     * Run each translatable fields through `wpml_translate_string` filter hook to enable translation.
     *
     * @param array  $fields   The fields to translate.
     * @param int    $form_id  Order Form ID.
     * @param string $meta_key One of 'form_header', 'form_body', 'form_footer', or 'settings'.
     *
     * @since 3.0
     * @return array
     */
    public function filter_fields_to_translate( $fields, $form_id, $meta_key ) {

        [
            'fields'         => $translatable_fields,
            'setting_fields' => $settings_translatable_fields,
        ] = WPML_Helper::get_translatable_fields();

        switch ( $meta_key ) {
            case 'form_header':
            case 'form_footer':
                if ( ! empty( $fields['rows'] ) ) {
                    foreach ( $fields['rows'] as &$row ) {
                        if ( ! empty( $row['columns'] ) ) {
                            foreach ( $row['columns'] as &$column ) {
                                if ( ! empty( $column['fields'] ) ) {
                                    foreach ( $column['fields'] as &$field ) {
                                        if ( ! empty( $field['settings']['options'] ) ) {
                                            foreach ( $field['settings']['options'] as $option_key => &$option_value ) {
                                                if ( isset( $translatable_fields[ $option_key ] ) ) {
                                                    $option_value = $this->wpml_translate_meta_string(
                                                        WPML_Helper::translatable_field_name(
                                                            $form_id,
                                                            $meta_key,
                                                            $field['elementClass'],
                                                            $option_key,
                                                            $option_value
                                                        ),
                                                        $option_value
                                                    );
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                break;
            case 'form_body':
                if ( ! empty( $fields['columns'] ) ) {
                    foreach ( $fields['columns'] as &$column ) {
                        if ( ! empty( $column['settings']['options'] ) ) {
                            foreach ( $column['settings']['options'] as $option_key => &$option_value ) {
                                if ( isset( $translatable_fields[ $option_key ] ) ) {
                                    if ( ! empty( $option_value ) && is_array( $option_value ) ) {
                                        /***************************************************************************
                                         * Maybe translate fields with array fields like Product Meta
                                         ***************************************************************************
                                         *
                                         * Fields with array values as of WWOF 3.0 currently includes:
                                         * - Product Meta
                                         */
                                        foreach ( $option_value as $option_item_key => &$option_item_value ) {
                                            if ( ! empty( $option_item_value ) && is_array( $option_item_value ) ) {
                                                foreach ( $option_item_value as $option_item_item_key => &$option_item_item_value ) {
                                                    if ( isset( $translatable_fields[ $option_item_item_key ] ) ) {
                                                        $option_item_item_value = $this->wpml_translate_meta_string(
                                                            WPML_Helper::translatable_field_name(
                                                                $form_id,
                                                                $meta_key,
                                                                $column['elementClass'],
                                                                $option_item_item_key,
                                                                $option_item_item_value
                                                            ),
                                                            $option_item_item_value
                                                        );
                                                    }
                                                }
                                            } elseif ( isset( $translatable_fields[ $option_item_key ] ) ) {
                                                /***************************************************************************
                                                 * May not be necessary, but just in case
                                                 ***************************************************************************
                                                 *
                                                 * Currently, the only fields with array values as of 3.0 are Product Meta.
                                                 * So, this should only be executed for just in case.
                                                 */
                                                $option_item_value = $this->wpml_translate_meta_string(
                                                    WPML_Helper::translatable_field_name(
                                                        $form_id,
                                                        $meta_key,
                                                        $column['elementClass'],
                                                        $option_item_key,
                                                        $option_item_value
                                                    ),
                                                    $option_item_value
                                                );
                                            }
                                        }
                                    } else {
                                        $option_value = $this->wpml_translate_meta_string(
                                            WPML_Helper::translatable_field_name(
                                                $form_id,
                                                $meta_key,
                                                $column['elementClass'],
                                                $option_key,
                                                $option_value
                                            ),
                                            $option_value
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
                break;
            case 'settings':
                foreach ( $settings_translatable_fields as $settings_key => &$settings_value ) {
                    if ( ! empty( $fields[ $settings_key ] ) ) {
                        if ( is_array( $settings_value ) ) {
                            foreach ( $settings_value as $setting_key => $_ ) {
                                if ( ! empty( $fields[ $settings_key ][ $setting_key ] ) ) {
                                    $fields[ $settings_key ][ $setting_key ] = $this->wpml_translate_meta_string(
                                        WPML_Helper::translatable_field_name(
                                            $form_id,
                                            $meta_key,
                                            $settings_key,
                                            '',
                                            $setting_key
                                        ),
                                        $fields[ $settings_key ][ $setting_key ]
                                    );
                                }
                            }
                        } else {
                            $fields[ $settings_key ] = $this->wpml_translate_meta_string(
                                WPML_Helper::translatable_field_name(
                                    $form_id,
                                    $meta_key,
                                    $settings_key,
                                    '',
                                    $settings_key
                                ),
                                $fields[ $settings_key ]
                            );
                        }
                    }
                }
                break;
        }

        return $fields;
    }

    /**
     * Applies WPML's `wpml_translate_string` filter hook to enable translation.
     *
     * @param string $key   The meta key to translate.
     * @param string $value The meta value to translate.
     *
     * @since 3.0
     * @return mixed|null
     */
    private function wpml_translate_meta_string( $key, $value ) {

        /***************************************************************************
         * WPML Package
         ***************************************************************************
         *
         * We try to use the same package data when registering and displaying.
         *
         * See \RymeraWebCo\WWOF\Helpers\WPML::maybe_register_translatable_field()
         * method.
         *
         * See related WPML docs at:
         * https://wpml.org/documentation/support/string-package-translation/#applying-translations-before-output
         */
        $package = array(
            'kind'      => 'WWOF Field',
            'kind_slug' => 'wwof',
            'name'      => $key,
        );

        /**
         * Apply WPML's `wpml_translate_string` filter hook to enable translation.
         *
         * @param string $value   The meta value to translate.
         * @param string $key     The meta key to translate.
         * @param array  $package The package data.
         *
         * @since 3.0.1
         */
        return apply_filters( 'wpml_translate_string', $value, $key, $package );
    }

    /**
     * Get current language.
     *
     * @since 3.0
     * @return mixed|null
     */
    public function get_current_language() {

        global $sitepress;

        return ! empty( $sitepress ) && method_exists( $sitepress, 'get_current_language' )
            ? $sitepress->get_current_language() : null;
    }

    /**
     * Add additional localization properties for WPML.
     *
     * @param array $defaults The default localization properties.
     *
     * @since 3.0
     * @return array
     */
    public function wwof_app_common_l10n( $defaults ) {

        return array_merge_recursive(
            $defaults,
            array(
                'currentLang'     => $this->get_current_language(),
                'currentCurrency' => get_woocommerce_currency() ? get_woocommerce_currency() : null,
                '_fields'         => array( 'translations' ),
            )
        );
    }

    /**
     * Customizes the search query for WPML.
     *
     * @param array $clauses        The query clauses.
     * @param WC    $wc_integration The WooCommerce integration class instance.
     *
     * @since 3.0.1
     * @return array
     */
    public function filter_search_post_clauses_request( $clauses, $wc_integration ) {

        global $wpdb;

        $language_code = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : 'en';

        if ( $wc_integration->product_variations_query ) {
            $replacement = "OR ($wpdb->posts.post_parent != 0 AND wc_product_meta_lookup.product_id IN (SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = %s) AND wc_product_meta_lookup.sku LIKE";
        } else {
            $replacement = "OR ($wpdb->posts.post_type = 'product' AND wc_product_meta_lookup.product_id IN (SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = %s) AND wc_product_meta_lookup.sku LIKE";
        }
        $clauses['where'] = str_replace(
            'OR (wc_product_meta_lookup.sku LIKE',
            $wpdb->prepare( $replacement, $language_code ), //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $clauses['where']
        );

        return $clauses;
    }

    /**
     * Add hooks related to WPML.
     *
     * @since 3.0
     */
    public function run() {

        if ( ! WPML_Helper::is_active() ) {
            return;
        }

        add_filter( 'wwof_order_form_app_common_l10n_defaults', array( $this, 'wwof_app_common_l10n' ), 100 );
        add_filter( 'wwof_order_form_header_meta', array( $this, 'filter_fields_to_translate' ), 10, 3 );
        add_filter( 'wwof_order_form_body_meta', array( $this, 'filter_fields_to_translate' ), 10, 3 );
        add_filter( 'wwof_order_form_footer_meta', array( $this, 'filter_fields_to_translate' ), 10, 3 );
        add_filter( 'wwof_order_form_settings_meta', array( $this, 'filter_fields_to_translate' ), 10, 3 );
        add_filter( 'wwof_search_post_clauses_request', array( $this, 'filter_search_post_clauses_request' ), 10, 2 );
    }
}
