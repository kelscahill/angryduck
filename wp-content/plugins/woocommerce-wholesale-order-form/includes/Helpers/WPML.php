<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * WPML class.
 *
 * @since 3.0
 */
class WPML {

    /**
     * Check if the site has WPML active.
     *
     * @since  3.0
     * @access public
     * @return boolean
     */
    public static function is_active() {

        return class_exists( 'SitePress' );
    }

    /**
     * Get field keys that can be translated.
     *
     * @param string $section Either 'form_fields' or 'form_settings'.
     *
     * @since 3.0
     * @return mixed|string[]
     */
    public static function get_translatable_field_keys( $section = '' ) {

        $form_fields = array(
            'placeholder',
            'buttonText',
            'clearButtonText',
            'preText',
            'emptyCartText',
            'subtotalSuffix',
            'outOfStockText',
            'headingText',
        );

        $form_settings = array(
            'noAccessTitle',
            'noAccessMessage',
        );

        if ( ! empty( $$section ) ) {
            return $$section;
        }

        return array_merge( $form_fields, $form_settings );
    }

    /**
     * Check if a field is translatable and register it with WPML.
     *
     * @param int    $form_id     Form ID.
     * @param string $meta_key    Either 'form_header', 'form_footer', 'form_body', or 'settings'.
     * @param array  $field       The field array. Requires at least 'name' and 'elementClass' keys.
     * @param string $setting_key The setting key.
     * @param mixed  $value       The value of the field or string to translate.
     *
     * @since  3.0
     * @return void
     */
    public static function maybe_register_translatable_field( $form_id, $meta_key, $field, $setting_key, $value ) {

        if ( ! self::is_active() ) {
            return;
        }

        [
            'fields'         => $translatable_fields,
            'setting_fields' => $settings_translatable_fields,
        ] = self::get_translatable_fields();

        $fields = array_merge( $translatable_fields, call_user_func_array( 'array_merge', array_values( $settings_translatable_fields ) ) );

        if ( isset( $fields[ $setting_key ] ) ) {
            $name = self::translatable_field_name( $form_id, $meta_key, $field['elementClass'], $setting_key, $value );
            /***************************************************************************
             * WPML Package
             ***************************************************************************
             *
             * We try to use the same package data when registering and displaying.
             *
             * See \RymeraWebCo\WWOF\Integrations\WPML::wpml_translate_meta_string()
             * method.
             *
             * See related WPML docs at:
             * https://wpml.org/documentation/support/string-package-translation/#recommended-workflow-for-registering-your-strings
             */
            $package = array(
                'kind'      => 'WWOF Field',
                'kind_slug' => 'wwof',
                'name'      => $name,
                'title'     => $field['name'],
                'edit_link' => admin_url( "admin.php?page=order-forms&post=$form_id" ),
            );

            /**
             * Action hook to register a string package.
             *
             * @param array $package The package data.
             *
             * @since 3.0
             */
            do_action( 'wpml_start_string_package_registration', $package );

            /**
             * Action hook to register a string in WPML.
             *
             * @param string $value     The value of the field or string to translate.
             * @param string $name      The name of the string.
             * @param array  $package   The package data.
             * @param string $title     The title of the string.
             * @param string $kind_slug The kind slug of the string.
             *
             * @since 3.0
             */
            do_action( 'wpml_register_string', $value, $name, $package, $field['name'], $package['kind'] );
        }
    }

    /**
     * Forms a snake_case string from the given arguments.
     *
     * @param int    $form_id       Form ID.
     * @param string $meta_key      Either 'form_header', 'form_footer', 'form_body', or 'settings'.
     * @param string $element_class Element class.
     * @param string $setting_key   Setting key name.
     * @param string $value         The value of the field or string to translate.
     *
     * @since 3.0
     * @return string
     */
    public static function translatable_field_name( $form_id, $meta_key, $element_class, $setting_key, $value ) {

        $hash_value = mb_substr( md5( $value ), 0, 8 );

        return str_replace( '-', '_', _wp_to_kebab_case( "form $form_id $meta_key $element_class $setting_key" ) ) . "_$hash_value";
    }

    /**
     * Get translatable fields.
     *
     * @since 3.0
     * @return array
     */
    public static function get_translatable_fields() {

        $fields = array_flip(
            array(
                'placeholder',
                'buttonText',
                'clearButtonText',
                'preText',
                'emptyCartText',
                'subtotalSuffix',
                'outOfStockText',
                'headingText',
                'name',
                'productMeta',
            )
        );

        $setting_fields = array(
            'permissions' => array_flip(
                array(
                    'noAccessTitle',
                    'noAccessMessage',
                )
            ),
        );

        return compact( 'fields', 'setting_fields' );
    }
}
