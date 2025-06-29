<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that house various generic plugin helper functions.
 *
 * @since 1.17.1
 */
final class WWLC_Helper_Functions {

    /**
     * Check if WWP is v2.0.
     *
     * @since 1.17.1
     * @access public
     *
     * @return boolean
     */
    public static function is_wwp_v2() {
        if ( self::is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' ) ) {

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            $wwp_data = get_plugin_data( WWP_PLUGIN_PATH . 'woocommerce-wholesale-prices.bootstrap.php', false, false );

            if ( version_compare( $wwp_data['Version'], '2', '>=' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Utility function that determines if a plugin is active or not.
     *
     * @since 1.17.1
     * @access public
     *
     * @param string $plugin_basename Plugin base name. Ex. woocommerce/woocommerce.php.
     * @return boolean True if active, false otherwise.
     */
    public static function is_plugin_active( $plugin_basename ) {
        // Makes sure the plugin is defined before trying to use it.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_basename );
    }

    /**
     * Get custom field option text.
     *
     * @since 1.17.5
     * @access public
     *
     * @param string $value      The value of the custom field.
     * @param array  $field_data The custom field data.
     * @return string
     */
    public static function get_custom_field_option_text( $value, $field_data ) {

        $option_text = '';

        if ( ! empty( $field_data['options'] ) ) {

            foreach ( $field_data['options'] as $option ) {

                if ( $option['value'] === $value ) {
                    $option_text = $option['text'];
                    break;
                }
            }
        }

        return $option_text;
    }

    /**
     * Get default datetime format for display.
     *
     * @since 1.30.4
     * @access public
     *
     * @return string Datetime format.
     */
    public static function get_default_datetime_format() {
        return sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'g:i a' ) );
    }

    /**
     * Get datetime with site timezone.
     *
     * @since 1.30.4
     * @access public
     *
     * @param string $datetime Datetime string.
     * @param string $interval Datetime interval.
     *
     * @return \WC_DateTime Datetime object.
     */
    public static function get_datetime_with_site_timezone( $datetime, $interval = '' ) {
        $datetime = new \WC_DateTime( $datetime, new DateTimeZone( 'UTC' ) );
        $datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
        if ( ! empty( $interval ) ) {
            $interval = DateInterval::createFromDateString( $interval );
            $datetime->add( $interval ); // Add interval if needed.
        }

        return $datetime;
    }

    /**
     * Convert datetime to site standard format.
     * 1. Datetime must use site timezone.
     * 2. Datetime must use site default datetime format.
     * 3. Datetime must be localize using i18n.
     *
     * @since 1.30.4
     *
     * @param string $datetime Datetime string.
     * @param string $format   Datetime format.
     * @param string $interval Datetime interval.
     *
     * @return string
     */
    public static function convert_datetime_to_site_standard_format( $datetime, $format = '', $interval = '' ) {
        $standard = self::get_datetime_with_site_timezone( $datetime, $interval ); // Convert to site timezone.
        $format   = ! empty( $format ) ? $format : self::get_default_datetime_format();
        return $standard->date_i18n( $format ); // Convert to site default datetime format and localize.
    }

    /**
     * Get first user with wholesale role.
     *
     * @since 1.17.4
     * @access public
     *
     * @return WP_User
     */
    public static function get_wwlc_object() {
        $default_role = 'wholesale_customer';
        $wwlc_role    = apply_filters( 'wwlc_default_email_lead_role', $default_role );

        // Get WWLC role option.
        $wwlc_general_new_lead_role = get_option( 'wwlc_general_new_lead_role', $default_role );
        if ( ! empty( $wwlc_general_new_lead_role ) ) {
            $wwlc_role = $wwlc_general_new_lead_role;
        }

        // Get first user with wholesale role.
        $users = get_users( array( 'role' => $wwlc_role ) );
        return ! empty( $users ) ? $users[0] : null;
    }

    /**
     * Get roles that can be applied to leads
     *
     * @since 2.0.0
     *
     * @return array
     */
    public static function get_lead_roles() {

        $lead_roles = array(
            WWLC_UNAPPROVED_ROLE,
            WWLC_REJECTED_ROLE,
            get_option( 'wwlc_general_new_lead_role', 'wholesale_customer' ),
        );

        $registered_custom_roles = maybe_unserialize( get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES ) );
        if ( is_array( $registered_custom_roles ) ) {
            foreach ( $registered_custom_roles as $role_key => $role_data ) {
                if ( ! in_array( $role_key, $lead_roles, true ) ) {
                    $lead_roles[] = $role_key;
                }
            }
        }

        return apply_filters(
            'wwlc_leads_applicable_roles',
            $lead_roles
        );
    }

    /**
     * Get all wholesale lead roles.
     *
     * @since 2.0.0
     *
     * @return array
     */
    public static function get_wholesale_lead_roles() {
        global $wp_roles;

        $admin_roles = apply_filters(
            'wwlc_leads_page_admin_roles',
            array( 'administrator', 'shop_manager' )
        );

        $lead_roles = self::get_lead_roles();

        $user_roles = array();
        foreach ( $wp_roles->roles as $role_key => $role ) {
            if ( in_array( $role_key, $admin_roles, true ) || ! in_array( $role_key, $lead_roles, true ) ) {
                continue;
            }
            $user_roles[ $role_key ] = $role['name'];
        }

        return apply_filters(
            'wwlc_registered_user_roles',
            $user_roles
        );
    }
}
