<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MISC Functions
 */

if ( ! function_exists( 'wwlc_check_plugin_dependencies' ) ) {

    /**
     * Check for plugin dependencies of WooCommerce Wholesale Lead Capture plugin.
     *
     * @since 1.6.2
     * @return array Array of required plugins that are not present
     */
    function wwlc_check_plugin_dependencies() {
        // Makes sure the plugin is defined before trying to use it.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $missing_required_plugins = array();
        $required_plugins         = array_unique( apply_filters( 'wwlc_required_plugins', array( 'woocommerce/woocommerce.php' ) ) );

        foreach ( $required_plugins as $required_plugin ) {
            if ( ! is_plugin_active( $required_plugin ) ) {
                $plugin_name                = explode( '/', $required_plugin );
                $missing_required_plugins[] = array(
                    'plugin-key'  => $plugin_name[0],
                    'plugin-base' => $required_plugin,
                    'plugin-name' => ucwords( str_replace( '-', ' ', $plugin_name[0] ) ),
                );
            }
        }

        return $missing_required_plugins;
    }
}

/**
 * Provide admin notice to users that a required plugin dependency of WooCommerce Wholesale Lead Capture plugin is missing.
 *
 * Change function name from wwlcAdminNotices to wwlc_admin_notices for readability and consistency of naming standards.
 *
 * @since 1.6.2
 * @since 1.16.0
 * @since 1.17.2 Display notice if WWP is lower than 2.1.3
 */
function wwlc_admin_notices() {
    $missing_required_plugins = wwlc_check_plugin_dependencies();
    $wwp_plugin_data          = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

    if ( version_compare( $wwp_plugin_data['Version'], '2.1.3', '<' ) ) {

        global $current_user;

        $user_id      = $current_user->ID;
        $wwp_basename = plugin_basename( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices.bootstrap.php' ); ?>

        <div class="error">
            <p>
                <?php
                    printf(
                        '<b>%1$s</b><br/>%2$s <a href="%3$s" target="_blank">%4$s</a> %5$s.',
                        esc_html__( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-lead-capture' ),
                        esc_html__( 'Please ensure you have the latest version of', 'woocommerce-wholesale-lead-capture' ),
                        'http://wordpress.org/plugins/woocommerce-wholesale-prices/',
                        esc_html__( 'WooCommerce Wholesale Prices', 'woocommerce-wholesale-lead-capture' ),
                        esc_html__( 'plugin installed and activated along with the Premium extensions', 'woocommerce-wholesale-lead-capture' ),
                    );
                ?>
            </p>
            <p><?php printf( '<a href="%1$s">%2$s &rarr;</a>', esc_url( wp_nonce_url( 'update.php?action=upgrade-plugin&plugin=' . $wwp_basename, 'upgrade-plugin_' . $wwp_basename ) ), esc_html__( 'Click here to update WooCommerce Wholesale Prices Plugin', 'woocommerce-wholesale-lead-capture' ) ); ?></p>
        </div>
        <?php

    } elseif ( ! empty( $missing_required_plugins ) ) {

        $admin_notice_message = '';

        foreach ( $missing_required_plugins as $plugin ) {
            $pluginFile = $plugin['plugin-base'];
            $sptFile    = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $pluginFile );

            $spt_install_text = sprintf( '<a href="%1$s">%2$s &rarr;</a>', wp_nonce_url( 'update.php?action=install-plugin&plugin=' . $plugin['plugin-key'], 'install-plugin_' . $plugin['plugin-key'] ), __( 'Click here to install from WordPress.org repo', 'woocommerce-wholesale-lead-capture' ) );

            if ( file_exists( $sptFile ) ) {
                $spt_install_text = sprintf( '<a href="%1$s">%2$s &rarr;</a>', wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $pluginFile . '&amp;plugin_status=all&amp;s', 'activate-plugin_' . $pluginFile ), __( 'Click here to activate', 'woocommerce-wholesale-lead-capture' ) );
            }

            $admin_notice_message .= sprintf(
                '<br/>%1$s <a href="%2$s" target="_blank">%3$s</a> %4$s<br/>%5$s',
                __( 'Please ensure you have the', 'woocommerce-wholesale-lead-capture' ),
                'http://wordpress.org/plugins/' . $plugin['plugin-key'] . '/',
                $plugin['plugin-name'],
                __( 'plugin installed and activated.', 'woocommerce-wholesale-lead-capture' ),
                $spt_install_text . '<br/>'
            );
        }
        ?>

        <div class="error">
            <p>
                <?php
                    printf(
                        '<b>%1$s</b> %2$s.<br/>%3$s',
                        esc_html__( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-lead-capture' ),
                        esc_html__( 'plugin missing dependency', 'woocommerce-wholesale-lead-capture' ),
                        wp_kses_post( $admin_notice_message )
                    );
                ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Show WooCommerce Wholesale Lead Capture admin notices
 *
 * @since 1.6.2
 */
function wwlc_admin_notices_action() {
    add_action( 'admin_notices', 'wwlc_admin_notices' );
}

if ( ! function_exists( 'wwlc_global_plugin_deactivate' ) ) {

    /**
     * Delete code activation flag on plugin deactivate.
     *
     * @param bool $network_wide Variable that store if site is network or single site.
     *
     * @since 1.3.0
     * @since 1.11 Includes removal of license related options
     */
    function wwlc_global_plugin_deactivate( $network_wide ) {
        global $wpdb;

        // check if it is a multisite network.
        if ( is_multisite() ) {

            // check if the plugin has been deactivated on the network or on a single site.
            if ( $network_wide ) {

                // get ids of all sites.
                $blogIDs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                foreach ( $blogIDs as $blogID ) {

                    switch_to_blog( $blogID );
                    delete_option( 'wwlc_activation_code_triggered' );
                    delete_site_option( 'wwlc_option_installed_version' );
                    delete_site_option( 'wwlc_update_data' );
                    delete_site_option( 'wwlc_license_expired' );

                }

                restore_current_blog();

            } else {

                // deactivated on a single site, in a multi-site.
                delete_option( 'wwlc_activation_code_triggered' );
                delete_site_option( 'wwlc_option_installed_version' );
                delete_site_option( 'wwlc_update_data' );
                delete_site_option( 'wwlc_license_expired' );

            }
        } else {

            // deactivated on a single site.
            delete_option( 'wwlc_activation_code_triggered' );
            delete_option( 'wwlc_option_installed_version' );
            delete_option( 'wwlc_update_data' );
            delete_option( 'wwlc_license_expired' );

        }
    }
}

/**
 * Log deprecated function error to the php_error.log file and display on screen when not on AJAX.
 *
 * @since 1.7.0
 * @access public
 *
 * @param array  $trace       debug_backtrace() output.
 * @param string $function_name    Name of deprecated function.
 * @param string $version     Version when the function is set as deprecated since.
 * @param string $replacement Name of function to be replaced.
 */
function wwlc_deprecated_function( $trace, $function_name, $version, $replacement = null ) {
    $caller = array_shift( $trace );

    $log_string  = "The <em>{$function_name}</em> function is deprecated since version <em>{$version}</em>.";
    $log_string .= $replacement ? " Replace with <em>{$replacement}</em>." : '';
    $log_string .= ' Trace: <strong>' . $caller['file'] . '</strong> on line <strong>' . $caller['line'] . '</strong>';

    error_log( strip_tags( $log_string ) ); //phpcs:ignore

    if ( ! is_ajax() && WP_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {
        echo wp_kses_post( $log_string );
    }
}

/**
 * Protect an upload directory from direct web access.
 *
 * Creates .htaccess (deny from all) and index.php (silence is golden)
 * files in the given directory if they do not already exist.
 *
 * @param string $dir_path Absolute path to the directory to protect.
 *
 * @since 2.0.5
 * @return void
 */
function wwlc_protect_upload_directory( $dir_path ) {
    if ( ! is_dir( $dir_path ) ) {
        return;
    }

    $htaccess_file = $dir_path . '/.htaccess';
    if ( ! file_exists( $htaccess_file ) ) {
        file_put_contents( $htaccess_file, "deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    $index_file = $dir_path . '/index.php';
    if ( ! file_exists( $index_file ) ) {
        file_put_contents( $index_file, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
}

/**
 * Get the page url. We need to return only the page URL.
 *
 * @param string $page_option Contains option name.
 *
 * @access public
 * @since 1.8.0
 * @return string
 */
function wwlc_get_url_of_page_option( $page_option ) {
    $page     = get_option( $page_option );
    $page_url = '';

    if ( $page ) {

        $page_id = intval( $page );

        if ( $page_id ) {
            $page_url = trim( get_permalink( $page_id ) );
        } else {

            // Check if this url string belongs to the site and has permalink for WPML compatibility/translation.
            $page_id  = get_page_by_path( $page );
            $page_url = trim( $page_id ? get_permalink( $page_id ) : $page );

        }
}

    return apply_filters( 'wwlc_redirect_page_url', $page_url, $page_option );
}

/**
 * Get the user role.
 *
 * @param int $user_id User ID.
 *
 * @since 1.8.0
 * @return string
 */
function wwlc_get_user_role( $user_id ) {
    global $wp_roles;

    $custom_role        = get_user_meta( $user_id, 'wwlc_custom_set_role', true );
    $wwlc_new_lead_role = get_option( 'wwlc_general_new_lead_role' );

    if ( $custom_role ) {
        return $wp_roles->roles[ $custom_role ]['name'];
    } else { // Custom Role set via shortcode role="your_role".
        return $wp_roles->roles[ $wwlc_new_lead_role ]['name'];
    }
    // Get via wwlc 'New Lead Role' option.
}

/**
 * Get current url.
 *
 * @since 1.8.0
 * @return string
 */
function wwlc_get_current_url() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

    return esc_url_raw( home_url( $request_uri ) );
}

/**
 * Check if WWP or both WWP and WWPP are active.
 *
 * @since 1.8.0
 *
 * @param bool $check_if_wpp_is_active Is WWP active or not.
 * @return bool
 */
function wwlc_is_wwp_and_wwpp_active( $check_if_wpp_is_active = false ) {
    if ( $check_if_wpp_is_active ) {
        return is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' ) && is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' );
    } else {
        return is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' );
    }
}

/**
 * Strip custom field slashes before adding and updating.
 *
 * @since 1.12.0
 *
 * @param array $custom_field Custom Fields Array.
 * @return array
 */
function wwlc_strip_slashes( $custom_field = array() ) {
    // Strip extra slashes.
    if ( $custom_field ) {
        foreach ( $custom_field as $key => $value ) {
            switch ( $key ) {
                case 'field_name':
                case 'field_placeholder':
                case 'default_value':
                    $custom_field[ $key ] = wp_kses_post( stripslashes( $value ) );
                    break;
                case 'options':
                    if ( ! empty( $value ) ) {
                        foreach ( $value as $index => $option_val ) {
                            $custom_field[ $key ][ $index ]['value'] = stripslashes( $option_val['value'] );
                            $custom_field[ $key ][ $index ]['text']  = stripslashes( $option_val['text'] );
                        }
                    }
                    break;
            }
        }
    }

    return $custom_field;
}

/**
 * Convert special chars to html entities.
 *
 * @since 1.12.0
 *
 * @param array $custom_field Custom Fields Array.
 * @return array
 */
function wwlc_htmlspecialchars( $custom_field = array() ) {
    // Strip extra slashes.
    if ( isset( $custom_field['field_type'] ) && in_array( $custom_field['field_type'], array( 'radio', 'select', 'checkbox' ), true ) ) {
        foreach ( $custom_field as $key => $value ) {
            switch ( $key ) {
                case 'options':
                    if ( ! empty( $value ) ) {
                        foreach ( $value as $index => $option_val ) {
                            $custom_field[ $key ][ $index ]['value'] = htmlspecialchars( $option_val['value'] );
                            $custom_field[ $key ][ $index ]['text']  = htmlspecialchars( $option_val['text'] );
                        }
                    }
                    break;
            }
        }
    }

    return $custom_field;
}

/**
 * Get the current wholesale role of user.
 *
 * @param WP_User $user WP_User object of the logged-in user.
 * @since 1.14.7
 * @return bool|array Returns array of wholesale role else returns false
 */
function wwlc_get_wholesale_role( $user = null ) {
    global $wc_wholesale_prices;

    if ( ! $wc_wholesale_prices ) {
        return false;
    }

    return $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole( $user );
}

/**
 * Normalize a checkbox option value to 'yes' or 'no'.
 *
 * Handles stale 'true'/'false' values that the WWP settings framework
 * could save due to a native DOM event race condition in CheckboxControl.
 *
 * @since 2.0.5
 *
 * @param string $value The raw option value.
 * @return string 'yes' or 'no'.
 */
function wwlc_normalize_checkbox_value( $value ) {
    return in_array( $value, array( 'yes', 'true' ), true ) ? 'yes' : 'no';
}

/**
 * Provide Administrator and Shop Managers to view Registration Page.
 *
 * @since 1.15.1
 * @since 2.0.5 Accept 'true' in addition to 'yes' as a valid enabled value.
 */
function wwlc_allow_admin_to_view_registration_page() {
    // Include Necessary Files.
    require_once 'woocommerce-wholesale-lead-capture.options.php';
    require_once 'woocommerce-wholesale-lead-capture.plugin.php';

    $wc_wholesale_lead_capture = WooCommerce_Wholesale_Lead_Capture::instance();

    $is_admin_or_manager = current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
    $admin_view_enabled  = 'yes' === wwlc_normalize_checkbox_value( get_option( 'wwlc_enable_admin_registration_page_view', 'no' ) );

    if ( is_user_logged_in() && $is_admin_or_manager && $admin_view_enabled ) {

        remove_action( 'wp', array( $wc_wholesale_lead_capture->_wwlc_user_account, 'registration_page_redirect_logged_in_user' ) );

        remove_shortcode( 'wwlc_registration_form' );
        add_shortcode(
            'wwlc_registration_form',
            function ( $atts ) {
                global $wc_wholesale_lead_capture;
                $atts = shortcode_atts(
                    array(
                        'redirect'     => '',
                        'role'         => '',
                        'auto_approve' => '',
                        'auto_login'   => '',
                    ),
                    $atts,
                    'wwlc_registration_form'
                );
                return $wc_wholesale_lead_capture->_wwlc_shortcode->wwlc_registration_form_fields( $atts );
            }
        );

    }
}

/**
 * Unified function to sanitize user data from various sources (AJAX, API, and Forms).
 * Handles both built-in and custom fields with proper validation.
 *
 * @param array  $raw_user_data Raw user data from request.
 * @param string $context Context of the sanitization (ajax, api, form).
 *
 * @return array Sanitized user data.
 * @since 2.0.2
 */
function wwlc_sanitize_user_data( $raw_user_data, $context = 'ajax' ) {

    if ( ! is_array( $raw_user_data ) ) {
        return array();
    }

    $sanitized_data = array();

    // Get built-in registration fields.
    global $WWLC_REGISTRATION_FIELDS;
    $built_in_fields = $WWLC_REGISTRATION_FIELDS ?? array();

    // Get custom fields.
    $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, array() );

    // Create a comprehensive field map for sanitization.
    $field_map = array();

    // Add built-in fields to the map.
    foreach ( $built_in_fields as $field ) {
        if ( isset( $field['name'] ) ) {
            $field_map[ $field['name'] ] = array(
                'type'         => $field['type'] ?? 'text',
                'required'     => $field['required'] ?? false,
                'custom_field' => false,
            );
        }
    }

    // Add custom fields to the map.
    if ( is_array( $custom_fields ) ) {
        foreach ( $custom_fields as $field_id => $custom_field ) {
            if ( ! is_array( $custom_field ) ) {
                continue;
            }

            $field_map[ $field_id ] = array(
                'type'         => $custom_field['field_type'] ?? 'text',
                'required'     => ( '1' === ( $custom_field['required'] ?? '0' ) ),
                'custom_field' => true,
                'options'      => $custom_field['options'] ?? array(),
            );
        }
    }

    // Sanitize each field based on its type and context.
    foreach ( $raw_user_data as $field_name => $field_value ) {

        // Skip special fields that should be handled separately.
        if ( in_array( $field_name, array( 'role', 'shipping_address', 'status' ), true ) ) {
            continue;
        }

        // Only allow fields that exist in the field map (built-in + custom registration fields).
        // This prevents injection of arbitrary user meta keys like wwlc_custom_set_role.
        if ( ! isset( $field_map[ $field_name ] ) ) {
            continue;
        }

        // Get field configuration.
        $field_config = $field_map[ $field_name ];
        $field_type   = $field_config['type'];

        // Sanitize text field value, handling both arrays and scalars.
        $sanitize_text = function ( $value ) {
            if ( is_array( $value ) ) {
                return array_map(
                    function ( $val ) {
                        return sanitize_text_field( wp_unslash( $val ) );
                    },
                    $value
                );
            }

            return sanitize_text_field( wp_unslash( $value ) );
        };

        // Sanitize based on field type.
        switch ( $field_type ) {
            case 'email':
                $sanitized_data[ $field_name ] = sanitize_email( wp_unslash( $field_value ) );
                break;

            case 'url':
                $sanitized_data[ $field_name ] = esc_url_raw( wp_unslash( $field_value ) );
                break;

            case 'textarea':
                $sanitized_data[ $field_name ] = sanitize_textarea_field( wp_unslash( $field_value ) );
                break;

            case 'number':
                $sanitized_data[ $field_name ] = is_numeric( $field_value ) ? floatval( $field_value ) : '';
                break;

            case 'checkbox':
                $sanitized_data[ $field_name ] = $sanitize_text( $field_value );
                break;

            case 'select':
            case 'radio':
                // Handle multi-select arrays or single select/radio values.
                if ( $field_config['custom_field'] && ! empty( $field_config['options'] ) ) {
                    $allowed_values = array();
                    foreach ( $field_config['options'] as $option ) {
                        if ( isset( $option['value'] ) ) {
                            $allowed_values[] = $option['value'];
                        }
                    }

                    if ( is_array( $field_value ) ) {
                        $sanitized_data[ $field_name ] = array_values(
                            array_filter(
                                $sanitize_text( $field_value ),
                                function ( $val ) use ( $allowed_values ) {
                                    return in_array( $val, $allowed_values, true );
                                }
                            )
                        );
                    } elseif ( in_array( $field_value, $allowed_values, true ) ) {
                        $sanitized_data[ $field_name ] = $sanitize_text( $field_value );
                    }
                } else {
                    $sanitized_data[ $field_name ] = $sanitize_text( $field_value );
                }
                break;

            case 'file':
                // File fields are handled separately in the upload process.
                $sanitized_data[ $field_name ] = sanitize_text_field( wp_unslash( $field_value ) );
                break;

            case 'wwlc_password':
                // Passwords must not be sanitized with sanitize_text_field() as it strips
                // special characters (e.g. + - ' ) ^ " _). Only wp_unslash() is needed to
                // reverse WordPress magic quotes; wp_hash_password() handles the raw value safely.
                $sanitized_data[ $field_name ] = wp_unslash( $field_value );
                break;

            case 'hidden':
            case 'content':
            case 'terms_conditions':
                $payload = is_array( $field_value ) ? ( $field_value[0] ?? false ) : $field_value; // convert array into scalar value. if original value is an array return that array value, else return false, if original value is not an array, return the original value(non-array value).

                // These fields may contain HTML or special content.
                $sanitized_data[ $field_name ] = wp_kses_post( wp_unslash( $payload ) );
                break;

            default:
                $sanitized_data[ $field_name ] = $sanitize_text( $field_value );
                break;
        }
    }

    // Handle meta_data separately if present.
    if ( isset( $raw_user_data['meta_data'] ) && is_array( $raw_user_data['meta_data'] ) ) {
        $sanitized_data['meta_data'] = array();

        // Meta keys that must never be set via registration to prevent privilege escalation.
        $blocked_meta_keys = apply_filters(
            'wwlc_blocked_registration_meta_keys',
            array(
                'wwlc_custom_set_role',
                'wp_capabilities',
                'wp_user_level',
                'rich_editing',
                'syntax_highlighting',
                'show_admin_bar_front',
                'locale',
                'wp_user_avatars',
                'session_tokens',
            )
        );

        // Also block any key with the DB table prefix for capabilities/user_level.
        global $wpdb;
        $capabilities_key = $wpdb->prefix . 'capabilities';
        $user_level_key   = $wpdb->prefix . 'user_level';

        foreach ( $raw_user_data['meta_data'] as $meta ) {
            if ( is_array( $meta ) && isset( $meta['key'] ) && isset( $meta['value'] ) ) {
                $meta_key = sanitize_text_field( wp_unslash( $meta['key'] ) );

                // Block dangerous meta keys.
                if ( in_array( $meta_key, $blocked_meta_keys, true )
                    || $meta_key === $capabilities_key
                    || $meta_key === $user_level_key
                ) {
                    continue;
                }

                $sanitized_data['meta_data'][] = array(
                    'key'   => $meta_key,
                    'value' => is_array( $meta['value'] )
                        ? array_map( 'sanitize_text_field', wp_unslash( $meta['value'] ) )
                        : sanitize_text_field( wp_unslash( $meta['value'] ) ),
                );
            }
        }
    }

    // Handle custom role field if present.
    if ( isset( $raw_user_data['wwlc_role'] ) ) {
        $requested_role = sanitize_text_field( wp_unslash( $raw_user_data['wwlc_role'] ) );

        // Validate wwlc_role and override with default role if invalid.
        if ( wwlc_check_role( $requested_role ) ) {
            $sanitized_data['wwlc_role'] = $requested_role;
        } else {
            $sanitized_data['wwlc_role'] = get_option( 'wwlc_general_new_lead_role', WWLC_UNAPPROVED_ROLE );
        }
    }

    // Handle special fields that were skipped during sanitization.
    if ( isset( $raw_user_data['shipping_address'] ) && is_array( $raw_user_data['shipping_address'] ) ) {
        $sanitized_data['shipping_address'] = array();
        foreach ( $raw_user_data['shipping_address'] as $key => $value ) {
            $sanitized_data['shipping_address'][ $key ] = sanitize_text_field( wp_unslash( $value ) );
        }
    }

    if ( isset( $raw_user_data['status'] ) ) {
        $sanitized_data['status'] = sanitize_text_field( wp_unslash( $raw_user_data['status'] ) );
    }

    // Preserve shortcode-generated nonce fields needed for auto-approve/auto-login verification.
    if ( isset( $raw_user_data['wwlc_auto_approve_nonce'] ) ) {
        $sanitized_data['wwlc_auto_approve_nonce'] = sanitize_text_field( wp_unslash( $raw_user_data['wwlc_auto_approve_nonce'] ) );
    }

    if ( isset( $raw_user_data['wwlc_auto_login_nonce'] ) ) {
        $sanitized_data['wwlc_auto_login_nonce'] = sanitize_text_field( wp_unslash( $raw_user_data['wwlc_auto_login_nonce'] ) );
    }

    if ( isset( $raw_user_data['wwlc_page_id'] ) ) {
        $sanitized_data['wwlc_page_id'] = absint( $raw_user_data['wwlc_page_id'] );
    }

    return apply_filters( 'wwlc_sanitized_user_data', $sanitized_data, $raw_user_data, $context );
}

/**
 * Check if a role is valid for wholesale lead assignment.
 *
 * @param string $role The role to validate.
 *
 * @return bool True if role is valid, false otherwise.
 * @since 2.0.2
 */
function wwlc_check_role( $role ) {

    if ( empty( $role ) || ! is_string( $role ) ) {
        return false;
    }

    $allowed_roles = class_exists( 'WWLC_Helper_Functions' ) ? WWLC_Helper_Functions::get_wholesale_lead_roles() : array(
        WWLC_UNAPPROVED_ROLE,
        WWLC_REJECTED_ROLE,
        get_option( 'wwlc_general_new_lead_role', WWLC_UNAPPROVED_ROLE ),
    );

    if ( array_key_exists( $role, $allowed_roles ) ) {
        return true;
    }

    return false;
}
