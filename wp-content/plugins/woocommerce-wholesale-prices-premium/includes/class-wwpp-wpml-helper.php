<?php
/**
 * WPML helper functions.
 *
 * @package RymeraWebCo\WWPP
 */

namespace RymeraWebCo\WWPP;

defined( 'ABSPATH' ) || exit;

/**
 * WPML_Helper class.
 *
 * Provides helpers to resolve the correct WP locale when WPML's admin-bar
 * language switcher is active. WordPress's determine_locale() returns the
 * site default (e.g. en_US) in that scenario, causing script translations
 * to load for the wrong locale.
 *
 * @since 2.1.0
 */
class WWPP_WPML_Helper {

    /**
     * Check if WPML is active.
     *
     * @since  2.1.0
     * @return bool
     */
    public static function is_active() {

        return class_exists( 'SitePress' );
    }

    /**
     * Get the WordPress locale for the current WPML language.
     *
     * When WPML's admin-bar language switcher is used, determine_locale()
     * still returns the site default (e.g. en_US). This method resolves the
     * correct WP locale from WPML's current language so that script
     * translations load for the right locale.
     *
     * @since  2.1.0
     * @return string|null WP locale (e.g. fr_FR) or null if WPML is inactive
     *                     or serving the default language.
     */
    public static function get_current_locale() {

        if ( ! self::is_active() ) {
            return null;
        }

        /**
         * Filter: current WPML language code.
         *
         * @param string|null $current_language Current language code.
         */
        $current_language = apply_filters( 'wpml_current_language', null );

        if ( empty( $current_language ) || 'all' === $current_language ) {
            return null;
        }

        /**
         * Filter: WPML active languages list.
         *
         * @param array|null $languages Empty value filled by WPML.
         */
        $active_languages = apply_filters( 'wpml_active_languages', null );

        if ( ! empty( $active_languages[ $current_language ]['default_locale'] ) ) {
            return $active_languages[ $current_language ]['default_locale'];
        }

        return null;
    }
}
