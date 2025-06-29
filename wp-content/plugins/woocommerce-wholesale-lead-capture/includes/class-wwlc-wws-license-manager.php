<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wholesale Prices Premium License Manager
 */
class WWLC_WWS_License_Manager {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWLC_WWS_License_Manager.
     *
     * @since 1.17
     * @access private
     * @var WWLC_WWS_License_Manager
     */
    private static $_instance;

    /**
     * Current WWLC version.
     *
     * @since 1.17.7
     * @access private
     * @var int
     */
    private $_wwlc_current_version;

    /**
     * Property that holds the software priority of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_priority
     */
    private $_priority;

    /**
     * Property that holds the software key of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_software_key
     */
    private $_software_key;

    /**
     * Property that holds the slmw server url of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_activation_endpoint
     */
    private $_slmw_server_url;

    /**
     * Property that holds the activation endpoint of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_activation_endpoint
     */
    private $_activation_endpoint;

    /**
     * Property that holds the endpoint check of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_check_endpoint
     */
    private $_check_endpoint;

    /**
     * Property that holds the slug of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_plugin_slug
     */
    private $_plugin_slug;

    /**
     * Property that holds the name of this plugin.
     *
     * @since 1.17.7
     * @access private
     * @var string $_plugin_name
     */
    private $_plugin_name;

    /**
     * Property that holds the license settings url.
     *
     * @since 1.17.7
     * @access private
     * @var string $_license_settings_url
     */
    private $_license_settings_url;

    /**
     * Property that holds the plugin license settings url.
     *
     * @since 1.17.7
     * @access private
     * @var string $_plugin_license_settings_url
     */
    private $_plugin_license_settings_url;

    /**
     * Property that holds wholesale suite plugin list.
     *
     * @since 1.17.7
     * @access private
     * @var string $_plugin_list
     */
    private $_plugin_list;

    /**
     * Property that holds plugin submenus.
     *
     * @since 1.17.7
     * @access private
     * @var string $_plugin_submenus
     */
    private $_plugin_submenus;

    /**
     * Property that holds whether to show interstitial or not.
     *
     * @since 1.17.7
     * @access private
     * @var string $_show_interstitial
     */
    private $_show_interstitial;

    /**
     * Class Methods
     */

    /**
     * WWLC_WWS_License_Manager constructor.
     *
     * @access public
     * @since 1.17
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWLC_WWS_License_Manager model.
     */
    public function __construct( $dependencies = array() ) {
        $this->_wwlc_current_version        = ! empty( $dependencies['WWLC_Version'] ) ? $dependencies['WWLC_Version'] : '';
        $this->_software_key                = 'WWLC';
        $this->_priority                    = 30;
        $this->_plugin_slug                 = 'woocommerce-wholesale-lead-capture';
        $this->_plugin_name                 = __( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-lead-capture' );
        $this->_slmw_server_url             = constant( 'WWS_SLMW_SERVER_URL' );
        $this->_license_settings_url        = is_multisite() ? network_admin_url( 'admin.php?page=wws-ms-license-settings' ) : admin_url( 'admin.php?page=wws-license-settings' );
        $this->_plugin_license_settings_url = $this->_license_settings_url . '&tab=' . strtolower( $this->_software_key );
        $this->_activation_endpoint         = $this->_slmw_server_url . '/wp-json/slmw/v1/license/activate';
        $this->_check_endpoint              = $this->_slmw_server_url . '/wp-json/slmw/v1/license/check';
        $this->_plugin_list                 = array(
            'WWPP' => array(
                'name' => __( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-lead-capture' ),
                'slug' => 'woocommerce-wholesale-prices-premium',
            ),
            'WWOF' => array(
                'name' => __( 'WooCommerce Wholesale Order Form', 'woocommerce-wholesale-lead-capture' ),
                'slug' => 'woocommerce-wholesale-order-form',
            ),
            'WWLC' => array(
                'name' => __( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-lead-capture' ),
                'slug' => 'woocommerce-wholesale-lead-capture',
            ),
        );
        $this->_plugin_submenus             = array(
            'wwp-lead-capture-page' => array(
                'title'    => __( 'Lead Capture', 'woocommerce-wholesale-lead-capture' ),
                'callback' => 'lead_capture_settings',
                'position' => 3,
                'toplevel' => false,
            ),
        );
    }

    /**
     * Ensure that only one instance of WWLC_WWS_License_Manager is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.17
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWLC_WWS_License_Manager model.
     * @return WWLC_WWS_License_Manager
     */
    public static function instance( $dependencies = array() ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Wholesale Suite License Settings
     */

    /**
     * Add WWLC specific WWS license settings markup.
     *
     * @since 1.0.1
     * @access public
     */
    public function wws_license_settings_page() {
        ob_start();
        require_once constant( $this->_software_key . '_VIEWS_ROOT_DIR' ) . '/wws-license-settings/view-' . strtolower( $this->_software_key ) . '-wws-settings-page.php';
        wp_ob_end_flush_all();
    }

    /**
     * Check Wholesale Prices Premium license.
     *
     * @since 1.17.7
     * @access public
     *
     * @return array $license_data.
     */
    public function check_license() {
        // Get license activation email and key.
        $activation_email = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) );
        $license_key      = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) );

        $check_url = add_query_arg(
            array(
                'activation_email' => rawurlencode( $activation_email ),
                'license_key'      => $license_key,
                'site_url'         => home_url(),
                'software_key'     => $this->_software_key,
                'multisite'        => is_multisite() ? 1 : 0,
            ),
            apply_filters( strtolower( $this->_software_key ) . '_license_check_url', $this->_check_endpoint )
        );

        $args = apply_filters(
            strtolower( $this->_software_key ) . '_license_check_option',
            array(
                'timeout' => 10, // Seconds.
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $response = json_decode( wp_remote_retrieve_body( wp_remote_get( $check_url, $args ) ) );

        // Update last license check timestamp if response is success.
        if ( property_exists( $response, 'license_status' ) && 'success' === $response->status ) {
            if ( is_multisite() ) {
                update_site_option( constant( $this->_software_key . '_LAST_LICENSE_CHECK' ), time() );
            } else {
                update_option( constant( $this->_software_key . '_LAST_LICENSE_CHECK' ), time() );
            }
        }

        // Process license response on check.
        $license_data[ $this->_software_key ] = $this->process_license_response( $response, 'check' );

        // Fire post licence check hook.
        do_action( strtolower( $this->_software_key ) . '_after_check_license', $license_data, $activation_email, $license_key );

        return $license_data;
    }

    /**
     * AJAX FUNCTIONS
     */

    /**
     * Save and activate Wholesale Prices Premium license details.
     *
     * @since 1.0.1
     * @since 1.11 Updated to use new license manager
     * @access public
     *
     * @return array $response.
     */
    public function ajax_activate_license() {
        $wwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

        // Make sure we're doing ajax.
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

            // Check nonce and fail gracefully if invalid.
            if ( ! check_ajax_referer( strtolower( $this->_software_key ) . '_activate_license', 'ajax_nonce', false ) ) {
                wp_send_json(
                    array(
                        'status'    => 'fail',
                        'error_msg' => __(
                            'Security check failed',
                            'woocommerce-wholesale-lead-capture'
                        ),
                    )
                );
            }

            // Check passed in values and fail gracefully if invalid.
            if ( ! isset( $_REQUEST['license_email'] ) || ! isset( $_REQUEST['license_key'] ) ) {
                wp_send_json(
                    array(
                        'status'    => 'fail',
                        'error_msg' => __(
                            'Required parameters not supplied',
                            'woocommerce-wholesale-lead-capture'
                        ),
                    )
                );
            }
        }

        if ( isset( $_REQUEST['license_email'] ) && isset( $_REQUEST['license_key'] ) ) {
            $activation_email = sanitize_email( trim( wp_unslash( $_REQUEST['license_email'] ) ) );
            $license_key      = sanitize_text_field( trim( wp_unslash( $_REQUEST['license_key'] ) ) );
        } else {
            $activation_email = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) );
            $license_key      = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) );
        }

        // Remove license email option if empty.
        if ( '' === $activation_email ) {
            is_multisite() ? delete_site_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) ) : delete_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) );
        }

        // Remove license email option if empty.
        if ( '' === $license_key ) {
            is_multisite() ? delete_site_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) ) : delete_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) );
        }

        $activation_url = add_query_arg(
            array(
                'activation_email' => rawurlencode( $activation_email ),
                'license_key'      => $license_key,
                'site_url'         => home_url(),
                'software_key'     => $this->_software_key,
                'multisite'        => is_multisite() ? 1 : 0,
            ),
            apply_filters( strtolower( $this->_software_key ) . '_license_activation_url', $this->_activation_endpoint )
        );

        $args = apply_filters(
            strtolower( $this->_software_key ) . '_license_activation_options',
            array(
                'timeout' => 10, // Seconds.
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $response = json_decode( wp_remote_retrieve_body( wp_remote_get( $activation_url, $args ) ) );

        // Store data if license email and key are valid.
        if ( 'success' === $response->status ||
            ( 'fail' === $response->status && ( property_exists( $response, 'license_status' ) && 'invalid' !== $response->license_status ) )
        ) {
            if ( is_multisite() ) {
                update_site_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ), $activation_email );
                update_site_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ), $license_key );
            } else {
                update_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ), $activation_email );
                update_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ), $license_key );
            }
        }

        // Update last license check timestamp if response is success.
        if ( property_exists( $response, 'license_status' ) && 'success' === $response->status ) {
            if ( is_multisite() ) {
                update_site_option( constant( $this->_software_key . '_LAST_LICENSE_CHECK' ), time() );
            } else {
                update_option( constant( $this->_software_key . '_LAST_LICENSE_CHECK' ), time() );
            }
        }

        // Process license response on activation.
        $response = $this->process_license_response( $response, 'activation' );

        // Add expiration timestamp in site standard format.
        if ( is_array( $response ) && ! empty( $response ) && array_key_exists( 'expiration_timestamp', $response ) ) {
            $response['expiration_timestamp_i18n'] = WWLC_Helper_Functions::convert_datetime_to_site_standard_format( $response['expiration_timestamp'], wc_date_format() );
        }

        // Fire post activation attempt hook.
        do_action( strtolower( $this->_software_key ) . '_ajax_activate_license', $response, $activation_email, $license_key );

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            // Return AJAX response.
            wp_send_json( $response );
        } elseif ( version_compare( $wwp_plugin_data['Version'], '2.1.11', '<' ) ) {
            $response              = (array) $response;
            $response['error_msg'] = array_key_exists( 'license_status', $response ) ? $response['license_status'] : 'invalid';
            return $response;
        } else {
            return $response;
        }
    }

    /**
     * AJAX dismiss license notice.
     *
     * @since 1.17.7
     * @access public
     */
    public function ajax_dismiss_license_notice() {
        // Check this is an AJAX operation and that user is able to manage WC settings.
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore.
            wp_send_json(
                array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Invalid AJAX Operation', 'woocommerce-wholesale-lead-capture' ),
                )
            );
        } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            // Check nonce and fail gracefully if invalid.
            if ( ! check_ajax_referer( 'wws_slmw_dismiss_license_manager_nonce', 'nonce', false ) ) {
                wp_send_json(
                    array(
                        'status'    => 'fail',
                        'error_msg' => __(
                            'Security check failed',
                            'woocommerce-wholesale-lead-capture'
                        ),
                    )
                );
            }
        } elseif ( ! isset( $_REQUEST['type'] ) ) {
            wp_send_json(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Required parameters not supplied',
                        'woocommerce-wholesale-lead-capture'
                    ),
                )
            );
        }

        switch ( $_REQUEST['type'] ) {
            case 'nolicense':
                $option_name = constant( $this->_software_key . '_SHOW_NO_LICENSE_NOTICE' );
                break;
            case 'pre_expiry':
                $option_name = constant( $this->_software_key . '_SHOW_PRE_EXPIRY_LICENSE_NOTICE' );
                break;
            case 'expired':
                $option_name = constant( $this->_software_key . '_SHOW_EXPIRED_LICENSE_NOTICE' );
                break;
            case 'disabled':
                $option_name = constant( $this->_software_key . '_SHOW_DISABLED_LICENSE_NOTICE' );
                break;
        }

        // Deactivate the license notice.
        if ( is_multisite() ) {
            $update_option = update_site_option( $option_name, 'no' );
        } else {
            $update_option = update_option( $option_name, 'no' );
        }

        if ( ! $update_option ) {
            // Return AJAX response.
            wp_send_json(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Failed to dismiss license notice',
                        'woocommerce-wholesale-lead-capture'
                    ),
                )
            );
        } else {

            // Run action scheduler to show the notice again in 24 hours.
            as_schedule_single_action(
                WWLC_Helper_Functions::get_datetime_with_site_timezone( gmdate( 'Y-m-d H:i:s' ), '+24 hours' ),
                'wws_as_show_license_notice',
                array( $option_name ),
                'wws_as_show_license_notice'
            );

            wp_send_json( array( 'status' => 'success' ) );
        }
    }

    /**
     * AJAX refresh license status.
     *
     * @since 1.17.7
     * @access public
     */
    public function ajax_refresh_license_status() {
        // Check this is an AJAX operation and that user is able to manage WC settings.
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore.
            wp_send_json(
                array(
                    'status'    => 'fail',
                    'error_msg' => __( 'Invalid AJAX Operation', 'woocommerce-wholesale-lead-capture' ),
                )
            );
        } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $timeout = get_site_transient( 'wws_slmw_refresh_license_status_timeout' );

            // Check nonce and fail gracefully if invalid.
            if ( ! check_ajax_referer( 'wws_slmw_refresh_license_status_nonce', 'nonce', false ) ) {
                wp_send_json(
                    array(
                        'status'    => 'fail',
                        'error_msg' => __(
                            'Security check failed',
                            'woocommerce-wholesale-lead-capture'
                        ),
                    )
                );
            } elseif ( ! empty( $timeout ) ) {
                // Calculate the difference between current time and the time when the transient was set.
                $license_status_timer_diff = 60 - ( time() - $timeout );

                // If the the timer is still running, return AJAX response.
                if ( $license_status_timer_diff > 0 ) {
                    wp_send_json(
                        array(
                            'status'    => 'fail',
                            'timeout'   => $license_status_timer_diff,
                            'error_msg' => __(
                                'Please wait for 60 seconds before refreshing license status again.',
                                'woocommerce-wholesale-lead-capture'
                            ),
                        )
                    );
                }
            }
        }

        // Set site transient with current time & 60 seconds expiry to prevent multiple ajax calls.
        set_site_transient( 'wws_slmw_refresh_license_status_timeout', time(), 60 );

        // Check license & append response.
        $response = $this->check_license();

        // Action to allow other wholesale suite plugins to perform additional actions after refreshing license status.
        do_action( 'wws_after_refresh_license_status' );

        if ( ! $response || empty( $response ) ) {
            // Return AJAX response.
            wp_send_json(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Failed to refresh license status',
                        'woocommerce-wholesale-lead-capture'
                    ),
                )
            );
        } else {
            wp_send_json( $response );
        }
    }

    /**
     * AJAX dismiss license reminder pointer.
     *
     * @since 1.17.7
     * @access public
     */
    public function ajax_dismiss_license_reminder_pointer() {
        if ( strtolower( $this->_software_key ) === $_REQUEST['software_key'] ) {

            // Check this is an AJAX operation and that user is able to manage WC settings.
            if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore.
                wp_send_json(
                    array(
                        'status'    => 'fail',
                        'error_msg' => __( 'Invalid AJAX Operation', 'woocommerce-wholesale-lead-capture' ),
                    )
                );
            } elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                // Check nonce and fail gracefully if invalid.
                if ( ! check_ajax_referer( 'wws_slmw_dismiss_license_reminder_nonce', 'nonce', false ) ) {
                    wp_send_json(
                        array(
                            'status'    => 'fail',
                            'error_msg' => __(
                                'Security check failed',
                                'woocommerce-wholesale-lead-capture'
                            ),
                        )
                    );
                }
            }

            // Deactivate the license notice.
            if ( is_multisite() ) {
                $update_option = update_site_option( constant( $this->_software_key . '_SHOW_LICENSE_REMINDER_POINTER' ), 'no' );
            } else {
                $update_option = update_option( constant( $this->_software_key . '_SHOW_LICENSE_REMINDER_POINTER' ), 'no' );
            }

            if ( ! $update_option ) {
                // Return AJAX response.
                wp_send_json(
                    array(
                        'status'    => 'fail',
                        'error_msg' => __(
                            'Failed to dismiss license reminder pointer',
                            'woocommerce-wholesale-lead-capture'
                        ),
                    )
                );
            } else {
                wp_send_json( array( 'status' => 'success' ) );
            }
        }
    }

    /**
     * Remove WWP license upsell content.
     *
     * @since 1.27.11
     * @access public
     *
     * @param array $wwp_license The WWP_WWS_License_Manager instance.
     */
    public function remove_license_upsell_content( $wwp_license ) {
        remove_action( 'wws_action_license_settings_' . strtolower( $this->_software_key ), array( $wwp_license, strtolower( $this->_software_key ) . '_license_content' ) );
    }

    /**
     * Process license options after activation, check license or update data.
     *
     * @since 1.17.7
     * @access public
     *
     * @param object $response The API response from the activation/check endpoint.
     * @param string $context  The context of the process. Available values are 'activation', 'check' and 'update_data'.
     * @return array $response.
     */
    public function process_license_response( $response, $context = 'check' ) {
        // Activation point failed to send a response.
        if ( empty( $response ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Failed to activate license. Failed to connect to license server. Please contact plugin support.', 'woocommerce-wholesale-lead-capture' ),
            );
        } else {
            // Get WWS license data.
            $wws_license_data = is_multisite() ? (array) get_site_option( WWS_LICENSE_DATA, array() ) : (array) get_option( WWS_LICENSE_DATA, array() );

            if ( 'success' === $response->status && property_exists( $response, 'license_status' ) && 'active' === $response->license_status ) {
                if ( is_multisite() ) {
                    if ( 'update_data' === $context && ! empty( $response->software_update_data ) ) {
                        update_site_option( constant( $this->_software_key . '_UPDATE_DATA' ), $response->software_update_data );
                    }

                    delete_site_option( constant( $this->_software_key . '_LICENSE_EXPIRED' ) );
                    update_site_option( constant( $this->_software_key . '_LICENSE_ACTIVATED' ), 'yes' );
                } else {
                    if ( 'update_data' === $context && ! empty( $response->software_update_data ) ) {
                        update_option( constant( $this->_software_key . '_UPDATE_DATA' ), $response->software_update_data );
                    }

                    delete_option( constant( $this->_software_key . '_LICENSE_EXPIRED' ) );
                    update_option( constant( $this->_software_key . '_LICENSE_ACTIVATED' ), 'yes' );
                }
            } else {
                if ( is_multisite() ) {
                    update_site_option( constant( $this->_software_key . '_LICENSE_ACTIVATED' ), 'no' );

                    if ( 'update_data' === $context && ! empty( $response->software_update_data ) ) {
                        delete_site_option( constant( $this->_software_key . '_UPDATE_DATA' ) );
                    }

                    // Check if this license is expired.
                    if ( property_exists( $response, 'license_status' ) && 'expired' === $response->license_status && property_exists( $response, 'expiration_timestamp' ) ) {
                        update_site_option( constant( $this->_software_key . '_LICENSE_EXPIRED' ), $response->expiration_timestamp );
                    } else {
                        delete_site_option( constant( $this->_software_key . '_LICENSE_EXPIRED' ) );
                    }
                } else {
                    update_option( constant( $this->_software_key . '_LICENSE_ACTIVATED' ), 'no' );

                    if ( 'update_data' === $context && ! empty( $response->software_update_data ) ) {
                        delete_option( constant( $this->_software_key . '_UPDATE_DATA' ) );
                    }

                    // Check if this license is expired.
                    if ( property_exists( $response, 'license_status' ) && 'expired' === $response->license_status && property_exists( $response, 'expiration_timestamp' ) ) {
                        update_option( constant( $this->_software_key . '_LICENSE_EXPIRED' ), $response->expiration_timestamp );
                    } else {
                        delete_option( constant( $this->_software_key . '_LICENSE_EXPIRED' ) );
                    }
                }

                // Remove any locally stored update data if there are any.
                if ( 'update_data' !== $context ) {
                    $this->_maybe_remove_stored_update_data();
                }
            }

            /**
             * Store response data to WWS license data if the license status is active, disabled or expired.
             * Otherwise, remove plugin key of WWS license data.
             */
            if ( property_exists( $response, 'license_status' ) && in_array( $response->license_status, array( 'active', 'disabled', 'expired' ), true ) ) {
                /**
                 * Sanitize result before storing the data to the database.
                 * The value of the software_update_data property is an object, therefore skip sanitizing it.
                 */
                $response = array_map(
                    function ( $value ) {
                        if ( is_object( $value ) ) {
                            return $value;
                        }
                        return sanitize_text_field( $value );
                    },
                    (array) $response
                );

                // Update WWS license data.
                $wws_license_data = array_merge(
                    $wws_license_data,
                    array( $this->_software_key => $response )
                );
            } else {
                // Remove plugin key of WWS license data.
                $wws_license_data = array_diff_key( $wws_license_data, array( $this->_software_key => '' ) );
            }

            if ( is_multisite() ) {
                update_site_option( WWS_LICENSE_DATA, $wws_license_data );
            } else {
                update_option( WWS_LICENSE_DATA, $wws_license_data );
            }
        }

        return $response;
    }

    /**
     * Remove any locally stored update data if there are any.
     *
     * @since 1.17.7
     * @access private
     */
    private function _maybe_remove_stored_update_data() {
        $wp_site_transient = get_site_transient( 'update_plugins' );

        if ( $wp_site_transient ) {
            $plugin_basename = constant( $this->_software_key . '_PLUGIN_BASE_NAME' );

            if ( isset( $wp_site_transient->checked ) && is_array( $wp_site_transient->checked ) && array_key_exists( $plugin_basename, $wp_site_transient->checked ) ) {
                unset( $wp_site_transient->checked[ $plugin_basename ] );
            }

            if ( isset( $wp_site_transient->response ) && is_array( $wp_site_transient->response ) && array_key_exists( $plugin_basename, $wp_site_transient->response ) ) {
                unset( $wp_site_transient->response[ $plugin_basename ] );
            }

            set_site_transient( 'update_plugins', $wp_site_transient );
            wp_update_plugins();
        }
    }

    /**
     * Get license status i18n.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $status The license status to get i18n.
     * @return string
     */
    public function get_license_status_i18n( $status ) {
        switch ( $status ) {
            case 'active':
                return __( 'Active', 'woocommerce-wholesale-lead-capture' );
            case 'disabled':
                return __( 'Disabled', 'woocommerce-wholesale-lead-capture' );
            case 'invalid':
                return __( 'Invalid', 'woocommerce-wholesale-lead-capture' );
            case 'expired':
                return __( 'Expired', 'woocommerce-wholesale-lead-capture' );
        }
    }

    /**
     * Get plugin name from plugin key.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $software_key The software key to get plugin name.
     * @return string
     */
    public function get_plugin_name( $software_key ) {
        return $this->_plugin_list[ $software_key ]['name'];
    }

    /**
     * Get WWS license data.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $software_key The software key to get license data.
     * @return array
     */
    public function get_wws_license_data( $software_key = '' ) {
        $license_data = is_multisite() ? (array) get_site_option( WWS_LICENSE_DATA, array() ) : (array) get_option( WWS_LICENSE_DATA, array() );

        if ( ! empty( $software_key && array_key_exists( $software_key, $license_data ) ) ) {
            $license_data = ! empty( $license_data[ $software_key ] ) ? $license_data[ $software_key ] : array();
        }

        return $license_data;
    }

    /**
     * Check if license is activated.
     *
     * @since 1.17.7
     * @access public
     *
     * @return bool
     */
    public function is_license_activated() {
        return is_multisite() ? get_site_option( constant( $this->_software_key . '_LICENSE_ACTIVATED' ) ) : get_option( constant( $this->_software_key . '_LICENSE_ACTIVATED' ) );
    }

    /**
     * Get last license check.
     *
     * @since 1.17.7
     * @access public
     *
     * @return string
     */
    public function get_last_license_check() {
        return is_multisite() ? get_site_option( constant( $this->_software_key . '_LAST_LICENSE_CHECK' ) ) : get_option( constant( $this->_software_key . '_LAST_LICENSE_CHECK' ) );
    }

    /**
     * Check license status.
     * If $interval is set, then check if license is expired and within 2 days or more than 2 days.
     *
     * @since 1.17.7
     * @since 2.0.1 Adjusted license DRM from 14 days to 48 hours.
     * @access public
     *
     * @param string $status              The status to check if license is expired. Available values are 'expired', 'nolicense', 'disabled'. Default is empty string.
     * @param string $interval            The interval to check if license is expired and within 2 days or more than 2 days.
     *                                    Available values are 'within 2 days' and 'more than 2 days'. Default is empty string.
     * @param string $subscription_status The returns to return. Available values are 'boolean' and 'array'. Default is 'boolean'.
     * @return bool
     */
    public function is_license_status( $status = '', $interval = '', $subscription_status = '' ) {
        $license_status = false;
        $license_data   = $this->get_wws_license_data( $this->_software_key );

        switch ( $status ) {
            case 'pre_expiry':
                if ( ! empty( $license_data ) && array_key_exists( 'subscription_status', $license_data ) && 'pending-cancel' === $license_data['subscription_status'] ) {
                    // Get expiration timestamp from wws license data.
                    $expiration_timestamp = $license_data['expiration_timestamp'];
                    $expiration_datetime  = new \WC_DateTime( $expiration_timestamp, new \DateTimeZone( 'UTC' ) );
                    $now                  = new \WC_DateTime( 'now', new \DateTimeZone( 'UTC' ) );
                    $days_before_expiry   = $expiration_datetime->diff( $now )->days;
                    $license_status       = $days_before_expiry < 2;
                }
                break;
            case 'expired':
                if ( ! empty( $license_data ) && array_key_exists( 'license_status', $license_data ) && 'expired' === $license_data['license_status'] ) {
                    // Get expiration timestamp from wws license data.
                    $expiration_timestamp = $license_data['expiration_timestamp'];
                    $expiration_datetime  = new \WC_DateTime( $expiration_timestamp, new \DateTimeZone( 'UTC' ) );
                    $now                  = new \WC_DateTime( 'now', new \DateTimeZone( 'UTC' ) );

                    // If expiration timestamp is past the current time, then return true.
                    $license_status = $expiration_datetime < $now;

                    if ( ! empty( $interval ) && $license_status ) {
                        $days_after_expiry = $expiration_datetime->diff( $now )->days;

                        if ( 'within 2 days' === $interval ) {
                            // If license is expired and days after expiry is less than or equal to 2 days, then return true.
                            $license_status = $days_after_expiry < 2;
                        } elseif ( 'more than 2 days' === $interval ) {
                            // If license is expired and days after expiry is more than 2 days, then return true.
                            $license_status = $days_after_expiry >= 2;
                        }
                    }
                }
                break;
            case 'nolicense':
                $license_email  = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) );
                $license_key    = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) );
                $license_status = empty( $license_email ) || empty( $license_key ) ?? true;

                if ( ! empty( $interval ) && $license_status ) {
                    // Get plugin installed date.
                    $last_check_datestring = gmdate( 'Y-m-d H:i:s', $this->get_last_license_check() );

                    if ( ! empty( $last_check_datestring ) ) {
                        $last_check_datetime = new \WC_DateTime( $last_check_datestring, new \DateTimeZone( 'UTC' ) );
                        $now                 = new \WC_DateTime( 'now', new \DateTimeZone( 'UTC' ) );
                        $days_after_check    = $last_check_datetime->diff( $now )->days;

                        if ( 'more than 2 days' === $interval ) {
                            // If license is no license and days after expiry is more than 2 days, then return true.
                            $license_status = $days_after_check >= 2;
                        } elseif ( 'within 2 days' === $interval ) {
                            // If license is no license and days after expiry is less than or equal to 2 days, then return true.
                            $license_status = $days_after_check < 2;
                        } elseif ( 'within 2-7 days' === $interval ) {
                            // If license is no license and days after expiry is more than 2 days and less than or equal to 7 days, then return true.
                            $license_status = $days_after_check >= 2 && $days_after_check < 7;
                        } elseif ( 'more than 7 days' === $interval ) {
                            // If license is no license and days after expiry is more than 7 days, then return true.
                            $license_status = $days_after_check >= 7;
                        }
                    }
                }
                break;
            case 'disabled':
                if ( ! empty( $license_data ) && array_key_exists( 'license_status', $license_data ) && 'disabled' === $license_data['license_status'] ) {
                    $license_status = true;

                    if ( '' !== $subscription_status && $license_data['subscription_status'] !== $subscription_status ) {
                        $license_status = false;
                    }

                    if ( ! empty( $interval ) && $license_status ) {
                        // Get plugin installed date.
                        $last_check_datestring = gmdate( 'Y-m-d H:i:s', $this->get_last_license_check() );

                        if ( ! empty( $last_check_datestring ) ) {
                            $last_check_datetime = new \WC_DateTime( $last_check_datestring, new \DateTimeZone( 'UTC' ) );
                            $now                 = new \WC_DateTime( 'now', new \DateTimeZone( 'UTC' ) );
                            $days_after_check    = $last_check_datetime->diff( $now )->days;

                            if ( 'within 7 days' === $interval ) {
                                // If license is disabled and days after expiry is less than or equal to 7 days, then return true.
                                $license_status = $days_after_check < 7;
                            } elseif ( 'more than 7 days' === $interval ) {
                                // If license is disabled and days after expiry is more than 7 days, then return true.
                                $license_status = $days_after_check >= 7;
                            }
                        }
                    }
                }
                break;
        }

        return $license_status;
    }

    /**
     * Show license notice action scheduler.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $option_name The option name to update.
     */
    public function as_show_license_notice( $option_name ) {
        if ( is_multisite() ) {
            update_site_option( $option_name, 'yes' );
        } else {
            update_option( $option_name, 'yes' );
        }
    }

    /**
     * Lock access to premium submenu pages.
     * This is to prevent users from accessing the premium submenu pages when the license is not activated, disabled, cancelled or expired.
     *
     * @since 1.17.7
     * @access public
     */
    public function maybe_disable_plugin_settings_menu() {
        $this->_show_interstitial = $this->_maybe_show_interstitial();

        if ( ! empty( $this->_show_interstitial ) ) {
            foreach ( $this->_plugin_submenus as $slug => $submenu ) {
                $hookname = $submenu['toplevel'] ? 'toplevel_page_' . $slug : 'wholesale_page_' . $slug;

                // Remove submenu page.
                remove_submenu_page( 'wholesale-suite', $slug );

                // Add disabled page.
                add_submenu_page(
                    'wholesale-suite',
                    $submenu['title'],
                    $submenu['title'],
                    'manage_woocommerce', // phpcs:ignore.
                    $slug,
                    array( $this, 'license_interstitial_page' ),
                    $submenu['position']
                );
            }
        }
    }

    /**
     * Load license interstitial page.
     *
     * @since 1.17.7
     * @access public
     */
    public function license_interstitial_page() {
        $software_key      = $this->_software_key;
        $interstitial_type = $this->_show_interstitial;
        $wws_license_data  = wp_parse_args( $this->get_wws_license_data(), $this->_get_active_plugins( false ) );

        $license_management_url = self::get_license_management_url();

        require_once constant( $this->_software_key . '_PLUGIN_DIR' ) . 'views/wws-license-manager/' . strtolower( $this->_software_key ) . '-view-wws-license-interstitial.php';
    }

    /**
     * Get license management URL.
     *
     * @since 1.17.7
     * @access public
     *
     * @return string
     */
    public static function get_license_management_url() {
        $instance               = new self();
        $license_management_url = add_query_arg(
            array(
                'utm_source'   => strtolower( $instance->_software_key ),
                'utm_medium'   => 'drm',
                'utm_campaign' => strtolower( $instance->_software_key ) . 'drm' . $instance->_show_interstitial . 'interstitial',
            ),
            $instance->_slmw_server_url . '/my-account/downloads/'
        );

        return $license_management_url;
    }

    /**
     * Check if license intersitial should be shown.
     *
     * @since 1.17.7
     * @access public
     *
     * @return bool|string Return false if license intersitial should not be shown. Otherwise, return the intersitial type.
     */
    private function _maybe_show_interstitial() {
        $show_interstitial = false;

        if ( $this->is_license_status( 'nolicense', 'more than 2 days' ) ) {
            $show_interstitial = 'nolicense';
        } elseif ( $this->is_license_status( 'expired', 'more than 2 days' ) ) {
            $show_interstitial = 'expired';
        } elseif (
            $this->is_license_status( 'disabled', 'more than 2 days', 'active' ) ||
            $this->is_license_status( 'disabled', 'more than 2 days', 'pending-cancel' ) ||
            $this->is_license_status( 'disabled', '', 'cancelled' )
        ) {
            $show_interstitial = 'disabled';
        }

        return $show_interstitial;
    }

    /**
     * Check if license notice should be shown.
     *
     * @since 1.17.7
     * @access public
     *
     * @return bool|string
     */
    public static function show_license_interstitial() {
        $instance = new self();
        return $instance->_maybe_show_interstitial();
    }

    /**
     * Get active plugin.
     *
     * @since 1.17.7
     * @access private
     *
     * @param bool $plugin_data Whether to return plugin data. Default is true.
     * @return array
     */
    private function _get_active_plugins( $plugin_data = true ) {
        $active_plugins = array();

        foreach ( $this->_plugin_list as $key => $plugin ) {
            if ( is_plugin_active( $plugin['slug'] . '/' . $plugin['slug'] . '.bootstrap.php' ) ) {
                $active_plugins[ $key ] = $plugin_data ? $plugin : array();
            }
        }

        return $active_plugins;
    }

    /**
     * Enqueue license manager styles and scripts.
     *
     * @since 1.17.7
     * @access public
     */
    public function enqueue_wws_license_manager_scripts() {
        $show_enter_license_reminder_pointer = $this->_maybe_show_enter_license_reminder_pointer();

        if (
            ( $this->is_license_status( 'nolicense' ) &&
                (
                    $this->_maybe_show_license_notice( 'nolicense' ) ||
                    $show_enter_license_reminder_pointer
                )
            ) ||
            ( $this->is_license_status( 'pre_expiry' ) && $this->_maybe_show_license_notice( 'pre_expiry' ) ) ||
            ( $this->is_license_status( 'expired' ) && $this->_maybe_show_license_notice( 'expired' ) ) ||
            ( $this->is_license_status( 'disabled' ) && $this->_maybe_show_license_notice( 'disabled' ) )
        ) {
            $license_reminder_upsell_link = add_query_arg(
                array(
                    'utm_source'   => strtolower( $this->_software_key ),
                    'utm_medium'   => 'drm',
                    'utm_campaign' => strtolower( $this->_software_key ) . 'licensereminderpopup',
                ),
                'https://wholesalesuiteplugin.com/support/'
            );

            // Add pointers style to queue.
            wp_enqueue_style( 'wp-pointer' );

            if ( ! wp_style_is( 'wws_license_manager_css', 'enqueued' ) ) {
                wp_enqueue_style( 'wws_license_manager_css', WWLC_CSS_ROOT_URL . 'WWSLicenseManager.css', array(), $this->_wwlc_current_version, 'all' );
            }
            wp_enqueue_script( strtolower( $this->_software_key ) . '_license_manager_js', WWLC_JS_ROOT_URL . 'app/WWSLicenseManager.js', array( 'jquery', 'wp-pointer' ), $this->_wwlc_current_version, true );
            wp_localize_script(
                strtolower( $this->_software_key ) . '_license_manager_js',
                strtolower( $this->_software_key ) . '_license_manager_params',
                array(
                    'wws_slmw_software_key'        => strtolower( $this->_software_key ),
                    'wws_slmw_dismiss_license_manager_nonce' => wp_create_nonce( 'wws_slmw_dismiss_license_manager_nonce' ),
                    'wws_slmw_refresh_license_status_nonce' => wp_create_nonce( 'wws_slmw_refresh_license_status_nonce' ),
                    'wws_slmw_refresh_license_status_timeout' => get_site_transient( 'wws_slmw_refresh_license_status_timeout' ) ? 60 - ( time() - get_site_transient( 'wws_slmw_refresh_license_status_timeout' ) ) : 0,
                    'wws_slmw_license_reminder'    => array(
                        'show'    => $show_enter_license_reminder_pointer,
                        'html'    => array(
                            'title'   => __( 'Oops! Forgot to activate your Wholesale Suite license?', 'woocommerce-wholesale-lead-capture' ),
                            'content' => wp_kses_post(
                                sprintf(
                                    // Translators: %1$s - line break, %2$s - opening <a> tag, %3$s - closing </a> tag.
                                    __( 'A valid license key is required for Wholesale Suite\'s premium plugins. But don\'t worry! It\'s super easy to get fully activated.%1$sSimply click here to enter your license and, once done, you\'ll be finished your setup.%1$sDon\'t have a license? %2$sClick here%3$s.', 'woocommerce-wholesale-lead-capture' ),
                                    '<br/><br/>',
                                    '<a href="' . $license_reminder_upsell_link . '" target="_blank">',
                                    '</a>',
                                ),
                            ),
                        ),
                        'buttons' => array(
                            'close_text'             => __( 'Close', 'woocommerce-wholesale-lead-capture' ),
                            'enter_license_key_text' => __( 'Enter License Key', 'woocommerce-wholesale-lead-capture' ),
                            'close_nonce'            => wp_create_nonce( 'wws_slmw_dismiss_license_reminder_nonce' ),
                            'enter_license_key_url'  => is_multisite() ? network_admin_url( 'admin.php?page=wws-ms-license-settings&tab=' . strtolower( $this->_software_key ) ) : admin_url( 'admin.php?page=wws-license-settings&tab=' . strtolower( $this->_software_key ) ),
                        ),
                    ),
                    'wws_slmw_license_status_i18n' => array(
                        'active'   => __( 'Active', 'woocommerce-wholesale-lead-capture' ),
                        'expired'  => __( 'Expired', 'woocommerce-wholesale-lead-capture' ),
                        'invalid'  => __( 'Invalid', 'woocommerce-wholesale-lead-capture' ),
                        'disabled' => __( 'Disabled', 'woocommerce-wholesale-lead-capture' ),
                    ),
                )
            );
        }
    }

    /**
     * Handle License notices.
     *
     * @since 1.17.7
     * @access public
     *
     * @return void
     */
    public function handle_license_notices() {
        $license_data = $this->get_wws_license_data( $this->_software_key );

        if ( $this->is_license_status( 'nolicense' ) && $this->_maybe_show_license_notice( 'nolicense' ) ) {
            if ( ! did_action( 'wws_no_license_notice' ) ) {
                do_action( 'wws_no_license_notice', $this->_software_key );
            }
        } elseif ( $this->is_license_status( 'pre_expiry' ) && $this->_maybe_show_license_notice( 'pre_expiry' ) ) {
            if ( ! did_action( 'wws_pre_expiry_license_notice' ) ) {
                do_action( 'wws_pre_expiry_license_notice', $this->_software_key, $license_data );
            }
        } elseif ( $this->is_license_status( 'expired', 'within 2 days' ) && $this->_maybe_show_license_notice( 'expired' ) ) {
            if ( ! did_action( 'wws_expired_license_notice' ) ) {
                do_action( 'wws_expired_license_notice', $this->_software_key, $license_data );
            }
        } elseif ( $this->is_license_status( 'disabled' ) && $this->_maybe_show_license_notice( 'disabled' ) ) {
            if ( ! did_action( 'wws_disabled_license_notice' ) ) {
                do_action( 'wws_disabled_license_notice', $this->_software_key, $license_data );
            }
        }
    }

    /**
     * No license notice.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $software_key The software key to show license notice.
     * @return void
     */
    public function no_license_notice( $software_key ) {
        if ( $software_key === $this->_software_key ) {
            $screen         = get_current_screen();
            $is_dismissable = 'wholesale-suite' === get_current_screen()->parent_base ? '' : 'is-dismissible';

            $license_email = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_EMAIL' ) );
            $license_key   = is_multisite() ? get_site_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) ) : get_option( constant( $this->_software_key . '_OPTION_LICENSE_KEY' ) );

            $purchase_link = add_query_arg(
                array(
                    'utm_source'   => strtolower( $this->_software_key ),
                    'utm_medium'   => 'drm',
                    'utm_campaign' => strtolower( $this->_software_key ) . 'drmnoticepurchaselink',
                ),
                $this->_slmw_server_url . '/bundle'
            );

            $login_link = add_query_arg(
                array(
                    'utm_source'   => strtolower( $this->_software_key ),
                    'utm_medium'   => 'drm',
                    'utm_campaign' => strtolower( $this->_software_key ) . 'drmnoticeloginlink',
                ),
                $this->_slmw_server_url . '/my-account/downloads'
            );

            $nolicense_status = '';
            if ( $this->is_license_status( 'nolicense', 'within 2 days' ) ) {
                $nolicense_status = 'within 2 days';
            } elseif ( $this->is_license_status( 'nolicense', 'within 2-7 days' ) ) {
                $nolicense_status = 'within 2-7 days';
            }

            $notice_class = array(
                'notice',
                'within 2 days' === $nolicense_status ? 'notice-info-2' : '',
                'within 2-7 days' === $nolicense_status ? 'notice-error' : '',
                $is_dismissable,
                'wws-license-notice',
                strtolower( $this->_software_key ) . '-license-notice',
            );

            if ( 'within 2 days' === $nolicense_status || 'within 2-7 days' === $nolicense_status ) {
            ?>
            <div class="<?php echo esc_attr( implode( ' ', $notice_class ) ); ?>" data-software_key="<?php echo esc_attr( strtolower( $this->_software_key ) ); ?>" data-type="nolicense" >
                <p>
                    <strong>
                    <?php
                        if ( 'within 2 days' === $nolicense_status ) {
                            printf(
                                // Translators: %s - the plugin name.
                                esc_html__( 'Oops! Did you forget to enter your license for %s?', 'woocommerce-wholesale-lead-capture' ),
                                esc_html( $this->_plugin_name )
                            );
                        } elseif ( 'within 2-7 days' === $nolicense_status ) {
                            printf(
                                // Translators: %s - the plugin name.
                                esc_html__( '%1$sAction required!%2$s Enter your license for %3$s to continue.', 'woocommerce-wholesale-lead-capture' ),
                                '<span class="text-color-red">',
                                '</span>',
                                esc_html( $this->_plugin_name )
                            );
                        }
                    ?>
                    </strong>
                </p>
                <p>
                    <?php
                        if ( 'within 2 days' === $nolicense_status ) {
                            esc_html_e( 'Enter your license to full activate Wholesale Suite and gain access to automatic updates, technical support, and premium features.', 'woocommerce-wholesale-lead-capture' );
                        } elseif ( 'within 2-7 days' === $nolicense_status ) {
                            printf(
                                // Translators: %1$s - the purchase link opening tag, %2$s - the purchase link closing tag.
                                esc_html__( 'Don’t worry, your wholesale customers & orders completely safe and your premium wholesale features are still working. But you will need to enter a license key to continue using Wholesale Suite. If you don’t have a license, please %1$spurchase one to proceed%2$s.', 'woocommerce-wholesale-lead-capture' ),
                                '<a href="' . esc_url( $purchase_link ) . '" target="_blank">',
                                '</a>'
                            );
                        }
                    ?>
                </p>
                <form class="wws-license-notice-form <?php echo esc_attr( strtolower( $this->_software_key ) ); ?>-license-notice-form">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->_plugin_license_settings_url ); ?>" disabled="disabled">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="license-key"><?php esc_html_e( 'License Key:', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="password" class="form-control" id="license-key" name="license-key" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr( $license_key ); ?>">
                        </div>
                        <div class="form-group">
                            <label for="license-email"><?php esc_html_e( 'License Email:', 'woocommerce-wholesale-lead-capture' ); ?></label>
                            <input type="text" class="form-control" id="license-email" name="license-email" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr( $license_email ); ?>">
                        </div>
                        <!-- button -->
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Activate Key', 'woocommerce-wholesale-lead-capture' ); ?></button>
                    </div>
                </form>
                <p class="action-links mt-0">
                    <em>
                    <?php
                        printf(
                            // Translators: %1$s - the login link opening tag, %2$s - the login link closing tag.
                            esc_html__( 'Can’t find your key? %1$sLogin to your account%2$s.', 'woocommerce-wholesale-lead-capture' ),
                            '<a href="' . esc_url( $login_link ) . '" target="_blank">',
                            '</a>'
                        );
                    ?>
                    </em>
                </p>
            </div>
            <?php
            }
        }
    }

    /**
     * License pre expiry notice.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $software_key The software key to show license notice.
     * @param array  $license_data The license data.
     * @return void
     */
    public function pre_expiry_license_notice( $software_key, $license_data ) {
        if ( $software_key === $this->_software_key ) {
            $screen         = get_current_screen();
            $is_dismissable = 'wholesale-suite' === get_current_screen()->parent_base ? '' : 'is-dismissible';

            // Get days before expiry.
            $expiration_timestamp = $license_data['expiration_timestamp'];
            $expiration_datetime  = new \WC_DateTime( $expiration_timestamp, new \DateTimeZone( 'UTC' ) );
            $now                  = new \WC_DateTime( 'now', new \DateTimeZone( 'UTC' ) );
            $days_before_expiry   = $expiration_datetime->diff( $now )->days + 1;

            $pre_expiry_notice_utm_args = array(
                'utm_source'   => strtolower( $this->_software_key ),
                'utm_medium'   => 'drm',
                'utm_campaign' => strtolower( $this->_software_key ) . 'expiringpcdrmnotice',
            );

            $login_link      = add_query_arg( $pre_expiry_notice_utm_args, $license_data['upgrade_url'] );
            $learn_more_link = add_query_arg( $pre_expiry_notice_utm_args, 'https://wholesalesuiteplugin.com/kb/what-happens-if-my-license-expires/' );
            ?>
            <div class="notice notice-error wws-license-notice <?php echo esc_attr( $is_dismissable ) . ' ' . esc_attr( strtolower( $this->_software_key ) ); ?>-license-notice" data-software_key="<?php echo esc_attr( strtolower( $this->_software_key ) ); ?>" data-type="pre_expiry">
                <p>
                    <strong>
                    <?php
                        printf(
                            // Translators: %s - the plugin name.
                            esc_html__( '%1$sAction required!%2$s Your Wholesale Suite license is about to expire in %3$s days.', 'woocommerce-wholesale-lead-capture' ),
                            '<span class="text-color-red">',
                            '</span>',
                            '<span class="text-color-red">' . esc_html( $days_before_expiry ) . '</span>',
                        );
                    ?>
                    </strong>
                </p>
                <p>
                    <?php
                    printf(
                        /* Translators: %s is the license expiration date */
                        esc_html__(
                            'Your Wholesale Suite license is about to expire in %1$s days and automatic renewals are turned off. The current license will expire on %2$s. Once expired, you won\'t have access to premium features, plugin updates, or support. To avoid interruptions simply reactivate your subscription.',
                            'woocommerce-wholesale-lead-capture'
                        ),
                        esc_html( $days_before_expiry ),
                        '<strong>' . esc_html( WWLC_Helper_Functions::convert_datetime_to_site_standard_format( $license_data['expiration_timestamp'], wc_date_format() ) ) . '</strong>'
                    );
                    ?>
                </p>
                <p class="action-buttons">
                    <a href="<?php echo esc_url( $login_link ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Login & Reactivate', 'woocommerce-wholesale-lead-capture' ); ?></a>
                    <a href="<?php echo esc_url( $login_link ); ?>" target="_blank" class="learn-more-link"><?php esc_html_e( 'Learn More', 'woocommerce-wholesale-lead-capture' ); ?></a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * License expired notice.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $software_key The software key to show license notice.
     * @param array  $license_data The license data.
     * @return void
     */
    public function expired_license_notice( $software_key, $license_data ) {
        if ( $software_key === $this->_software_key ) {
            $screen         = get_current_screen();
            $is_dismissable = 'wholesale-suite' === get_current_screen()->parent_base ? '' : 'is-dismissible';

            $expired_notice_utm_args = array(
                'utm_source'   => strtolower( $this->_software_key ),
                'utm_medium'   => 'drm',
                'utm_campaign' => strtolower( $this->_software_key ) . 'drmexpirednotice',
            );

            $renew_license_link = add_query_arg( $expired_notice_utm_args, $license_data['management_url'] );
            $learn_more_link    = add_query_arg( $expired_notice_utm_args, 'https://wholesalesuiteplugin.com/kb/what-happens-if-my-license-expires/' );
            ?>
            <div class="notice notice-error wws-license-notice <?php echo esc_attr( $is_dismissable ) . ' ' . esc_attr( strtolower( $this->_software_key ) ); ?>-license-notice" data-software_key="<?php echo esc_attr( strtolower( $this->_software_key ) ); ?>" data-type="expired">
                <p><strong><?php esc_html_e( 'Oh no! Your Wholesale Suite license has expired!', 'woocommerce-wholesale-lead-capture' ); ?></strong></p>
                <p>
                    <?php
                    printf(
                        /* Translators: %s is the license expiration date */
                        esc_html__(
                            'Don’t worry, your wholesale customers & orders are completely safe and your premium wholesale features are still working. We’ve also extended premium feature functionality until %s, at which point functionality will become limited.',
                            'woocommerce-wholesale-lead-capture'
                        ),
                        esc_html( WWLC_Helper_Functions::convert_datetime_to_site_standard_format( $license_data['expiration_timestamp'], wc_date_format(), '14 days' ) ) // Outputs plus 14 days from the license expiration date.
                    );
                    ?>
                </p>
                <p><?php esc_html_e( 'Renew your Wholesale Suite license now to continue receiving automatic updates, technical support, and access to Wholesale Suite premium features.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                <p class="action-buttons">
                    <a href="<?php echo esc_url( $renew_license_link ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Renew License', 'woocommerce-wholesale-lead-capture' ); ?></a>
                    <a href="<?php echo esc_url( $learn_more_link ); ?>" target="_blank" class="learn-more-link"><?php esc_html_e( 'Learn More', 'woocommerce-wholesale-lead-capture' ); ?></a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * License disabled notice.
     *
     * @since 1.17.7
     * @access public
     *
     * @param string $software_key The software key to show license notice.
     * @param array  $license_data The license data.
     * @return void
     */
    public function disabled_license_notice( $software_key, $license_data ) {
        if ( $software_key === $this->_software_key ) {
            $screen         = get_current_screen();
            $is_dismissable = 'wholesale-suite' === get_current_screen()->parent_base ? '' : 'is-dismissible';

            $disabled_notice_utm_args = array(
                'utm_source'   => strtolower( $this->_software_key ),
                'utm_medium'   => 'drm',
                'utm_campaign' => strtolower( $this->_software_key ) . 'drmdisablednotice',
            );

            $contact_link    = add_query_arg( $disabled_notice_utm_args, 'https://wholesalesuiteplugin.com/support/' );
            $renew_link      = add_query_arg( $disabled_notice_utm_args, $license_data['management_url'] );
            $learn_more_link = add_query_arg( $disabled_notice_utm_args, 'https://wholesalesuiteplugin.com/kb/what-happens-if-my-license-expires/' );

            if ( $this->is_license_status( 'disabled', 'within 7 days', 'active' ) || $this->is_license_status( 'disabled', 'within 7 days', 'pending-cancel' ) ) {
                ?>
                <div class="notice notice-error wws-license-notice <?php echo esc_attr( $is_dismissable ) . ' ' . esc_attr( strtolower( $this->_software_key ) ); ?>-license-notice" data-software_key="<?php echo esc_attr( strtolower( $this->_software_key ) ); ?>" data-type="expired">
                    <p>
                        <strong>
                        <?php
                            printf(
                                // Translators: %1$s - span opening tag, %2$s - span closing tag.
                                esc_html__( '%1$sUrgent!%2$s This Wholesale Suite license key has been disabled. This could be due to a number of reasons:', 'woocommerce-wholesale-lead-capture' ),
                                '<span class="text-color-red">',
                                '</span>',
                            );
                        ?>
                        </strong>
                    </p>
                    <ul>
                        <li><?php esc_html_e( 'A refund or chargeback was initiated against this license key', 'woocommerce-wholesale-lead-capture' ); ?></li>
                        <li><?php esc_html_e( 'The license key may have violated our Terms of Service', 'woocommerce-wholesale-lead-capture' ); ?></li>
                        <li><?php esc_html_e( 'There may have been a malfunction with the license key and this is a false positive', 'woocommerce-wholesale-lead-capture' ); ?></li>
                    </ul>
                    <p><?php esc_html_e( 'Don’t worry, your wholesale customers & orders are completely safe and your premium wholesale features are still working for now, but a valid license is required to continue using Wholesale Suite.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    <p><?php esc_html_e( 'If you feel this is a mistake, please reach out to our support team immediately and we\'ll be happy to help.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    <p class="action-buttons">
                        <a href="<?php echo esc_url( $contact_link ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Contact Support', 'woocommerce-wholesale-lead-capture' ); ?></a>
                        <a href="<?php echo esc_url( $learn_more_link ); ?>" target="_blank" class="learn-more-link"><?php esc_html_e( 'Learn More', 'woocommerce-wholesale-lead-capture' ); ?></a>
                    </p>
                </div>
                <?php
            } elseif ( $this->is_license_status( 'disabled', 'within 7 days', 'on-hold' ) ) {
                ?>
                <div class="notice notice-error wws-license-notice <?php echo esc_attr( $is_dismissable ) . ' ' . esc_attr( strtolower( $this->_software_key ) ); ?>-license-notice" data-software_key="<?php echo esc_attr( strtolower( $this->_software_key ) ); ?>" data-type="expired">
                    <p><strong><?php esc_html_e( 'Oh no! Your Wholesale Suite license has failed to renew!', 'woocommerce-wholesale-lead-capture' ); ?></strong></p>
                    <p>
                        <?php
                        printf(
                            /* Translators: %s is the license expiration date */
                            esc_html__(
                                'Don’t worry, your wholesale customers & orders are completely safe and your premium wholesale features are still working. We’ve also extended premium feature functionality until %s, at which point functionality will become limited.',
                                'woocommerce-wholesale-lead-capture'
                            ),
                            esc_html( WWLC_Helper_Functions::convert_datetime_to_site_standard_format( gmdate( 'Y-m-d H:i:s', $this->get_last_license_check() ), wc_date_format(), '30 days' ) ) // Outputs plus 14 days from the license expiration date.
                        );
                        ?>
                    </p>
                    <p><?php esc_html_e( 'Login to your Wholesale Suite account to correct this issue to continue receiving automatic updates, technical support, and access to Wholesale Suite premium features.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    <p class="action-buttons">
                        <a href="<?php echo esc_url( $renew_link ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Login & Renew License', 'woocommerce-wholesale-lead-capture' ); ?></a>
                        <a href="<?php echo esc_url( $learn_more_link ); ?>" target="_blank" class="learn-more-link"><?php esc_html_e( 'Learn More', 'woocommerce-wholesale-lead-capture' ); ?></a>
                    </p>
                </div>
                <?php
            } elseif ( $this->is_license_status( 'disabled', 'more than 7 days', 'on-hold' ) ) {
                ?>
                <div class="notice notice-error wws-license-notice <?php echo esc_attr( $is_dismissable ) . ' ' . esc_attr( strtolower( $this->_software_key ) ); ?>-license-notice" data-software_key="<?php echo esc_attr( strtolower( $this->_software_key ) ); ?>" data-type="expired">
                    <p>
                        <strong>
                        <?php
                            printf(
                                // Translators: %1$s - span opening tag, %2$s - span closing tag.
                                esc_html__( '%1$sAction required!%2$s Your license has failed to renew and is now disabled.', 'woocommerce-wholesale-lead-capture' ),
                                '<span class="text-color-red">',
                                '</span>',
                            );
                        ?>
                        </strong>
                    </p>
                    <p><?php esc_html_e( 'An active Wholesale Suite license is required to continue receiving automatic updates, technical support, and access to Wholesale Suite premium features.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    <p><?php esc_html_e( 'Login to your Wholesale Suite account to correct this issue to continue receiving automatic updates, technical support, and access to Wholesale Suite premium features.', 'woocommerce-wholesale-lead-capture' ); ?></p>
                    <p class="action-buttons">
                        <a href="<?php echo esc_url( $renew_link ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Login & Renew License', 'woocommerce-wholesale-lead-capture' ); ?></a>
                        <a href="<?php echo esc_url( $learn_more_link ); ?>" target="_blank" class="learn-more-link"><?php esc_html_e( 'Learn More', 'woocommerce-wholesale-lead-capture' ); ?></a>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Maybe show license notice.
     *
     * @since 1.17.7
     * @access private
     *
     * @param string $notice The notice to check if it should be shown. Available values are 'nolicense', 'expired', 'disabled'.
     * @return bool
     */
    private function _maybe_show_license_notice( $notice ) {
        $screen = get_current_screen();
        $page   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        switch ( $notice ) {
            case 'nolicense':
                $option_name = constant( $this->_software_key . '_SHOW_NO_LICENSE_NOTICE' );
                break;
            case 'pre_expiry':
                $option_name = constant( $this->_software_key . '_SHOW_PRE_EXPIRY_LICENSE_NOTICE' );
                break;
            case 'expired':
                $option_name = constant( $this->_software_key . '_SHOW_EXPIRED_LICENSE_NOTICE' );
                break;
            case 'disabled':
                $option_name = constant( $this->_software_key . '_SHOW_DISABLED_LICENSE_NOTICE' );
                break;
        }

        $option_value = is_multisite() ? get_site_option( $option_name ) : get_option( $option_name );

        return (
            current_user_can( 'manage_woocommerce' ) && // phpcs:ignore.
            'wws-license-settings' !== $page &&
            (
                // Load style on all wholesale suite pages.
                strpos( $screen->id, 'wholesale_page_' ) === 0 ||
                'toplevel_page_wholesale-suite' === $screen->id ||
                // Load style on other pages if the notice is not dismissed.
                (
                    strpos( $screen->id, 'wholesale_page_' ) !== 0 &&
                    'no' !== $option_value
                )
            )
        );
    }

    /**
     * Check if enter license reminder pointer should be shown.
     *
     * @since 1.17.7
     * @access public
     *
     * @return bool|string Return false if enter license reminder pointer should not be shown.
     */
    private function _maybe_show_enter_license_reminder_pointer() {
        $screen       = get_current_screen();
        $option_value = is_multisite() ? get_site_option( constant( $this->_software_key . '_SHOW_LICENSE_REMINDER_POINTER' ) ) : get_option( constant( $this->_software_key . '_SHOW_LICENSE_REMINDER_POINTER' ) );

        return (
            current_user_can( 'manage_woocommerce' ) && // phpcs:ignore.
            (
                'wholesale_page_wws-license-settings' !== $screen->id &&
                'no' !== $option_value &&
                $this->is_license_status( 'nolicense', 'more than 3 days' )
            )
        );
    }

    /**
     * Register WWS License Settings Menu.
     *
     * @since 1.17.7
     * @access public
     */
    public function register_wws_license_settings_submenu() {
        if ( defined( 'WWS_LICENSE_SETTINGS_PAGE' ) && WWS_LICENSE_SETTINGS_PAGE === $this->_plugin_slug ) {
            // Set default tab.
            if ( ! defined( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN' ) ) {
                define( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN', strtolower( $this->_software_key ) );
            }

            // Register new WWS Settings Menu.
            add_submenu_page(
                'wholesale-suite', // Settings.
                __( 'License', 'woocommerce-wholesale-lead-capture' ),
                __( 'License', 'woocommerce-wholesale-lead-capture' ),
                'manage_woocommerce', // phpcs:ignore.
                'wws-license-settings',
                array( WWP_WWS_License_Manager::instance(), 'generate_wws_licenses_settings_page' ),
                7
            );

            // Unregister old WWP license settings page, and register new WWS license settings page with new menu slug.
            foreach ( $this->_plugin_list as $key => $plugin ) {
                remove_action( 'wws_action_license_settings_tab', array( WWP_WWS_License_Manager::instance(), strtolower( $key ) . '_license_tab' ) );
                add_action( 'wws_action_license_settings_tab', array( $this, strtolower( $key ) . '_license_settings_tab' ) );
            }
        }
    }

    /**
     * Register general wws license settings page in a multi-site environment.
     *
     * @since 1.17.7
     * @access public
     */
    public function register_ms_wws_license_settings_menu() {
        if ( defined( 'WWS_LICENSE_SETTINGS_PAGE' ) && WWS_LICENSE_SETTINGS_PAGE === $this->_plugin_slug ) {
            // Set default tab.
            if ( ! defined( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN' ) ) {
                define( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN', strtolower( $this->_software_key ) );
            }

            add_menu_page(
                __( 'WWS License', 'woocommerce-wholesale-prices' ),
                __( 'WWS License', 'woocommerce-wholesale-prices' ),
                'manage_sites',
                'wws-ms-license-settings',
                array( WWP_WWS_License_Manager::instance(), 'generate_wws_licenses_settings_page' )
            );

            // Unregister old WWP license settings page, and register new WWS license settings page with new menu slug.
            foreach ( $this->_plugin_list as $key => $plugin ) {
                remove_action( 'wws_action_license_settings_tab', array( WWP_WWS_License_Manager::instance(), strtolower( $key ) . '_license_tab' ) );
                add_action( 'wws_action_license_settings_tab', array( $this, strtolower( $key ) . '_license_settings_tab' ) );
            }
        }
    }

    /**
     * Register WWS License Settings Menu.
     *
     * @since 1.17.7
     * @access public
     */
    public function wwpp_license_settings_tab() {
        ob_start();

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        printf(
            '<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
            esc_url( $this->_license_settings_url . '&tab=wwpp' ),
            'wwpp' === $tab ? 'nav-tab-active' : '',
            esc_html( __( 'Wholesale Prices', 'woocommerce-wholesale-lead-capture' ) )
        );
        echo wp_kses_post( ob_get_clean() );
    }

    /**
     * WWLC license settings upsell tab.
     *
     * @since 1.17.7
     * @access public
     */
    public function wwlc_license_settings_tab() {
        ob_start();

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        printf(
            '<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
            esc_url( $this->_license_settings_url . '&tab=wwlc' ),
            'wwlc' === $tab ? 'nav-tab-active' : '',
            esc_html( __( 'Wholesale Lead', 'woocommerce-wholesale-lead-capture' ) )
        );
        echo wp_kses_post( ob_get_clean() );
    }

    /**
     * WWOF license settings upsell tab.
     *
     * @since 1.17.7
     * @access public
     */
    public function wwof_license_settings_tab() {
        ob_start();

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        printf(
            '<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
            esc_url( $this->_license_settings_url . '&tab=wwof' ),
            'wwof' === $tab ? 'nav-tab-active' : '',
            esc_html( __( 'Wholesale Order Form', 'woocommerce-wholesale-lead-capture' ) )
        );
        echo wp_kses_post( ob_get_clean() );
    }

    /**
     * Execute model.
     *
     * @since 1.11
     * @access public
     */
    public function run() {
        $wwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

        // Register AJAX endpoints.
        add_action( 'wp_ajax_' . strtolower( $this->_software_key ) . '_activate_license', array( $this, 'ajax_activate_license' ) );
        add_action( 'wp_ajax_' . strtolower( $this->_software_key ) . '_slmw_dismiss_license_notice', array( $this, 'ajax_dismiss_license_notice' ) );
        add_action( 'wp_ajax_wws_slmw_dismiss_license_reminder_pointer', array( $this, 'ajax_dismiss_license_reminder_pointer' ), $this->_priority );

        // Ajax refresh wholesale suite license status.
        add_action( 'wp_ajax_wws_slmw_refresh_license_status', array( $this, 'ajax_refresh_license_status' ), $this->_priority );

        // Remove WWP license upsell when this plugin is active.
        add_action( 'wwp_license_tab_and_contents', array( $this, 'remove_license_upsell_content' ) );

        // Reccuring event to check license status.
        add_action( 'wws_license_check', array( $this, 'check_license' ), $this->_priority );

        // Check license status on refreshing license status.
        add_action( 'wws_after_refresh_license_status', array( $this, 'check_license' ), $this->_priority );

        // Show license notice action scheduler.
        add_action( 'wws_as_show_license_notice', array( $this, 'as_show_license_notice' ) );

        // Lock access to premium submenu page.
        add_action( 'admin_menu', array( $this, 'maybe_disable_plugin_settings_menu' ), 100 );

        // Load license manager styles and scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wws_license_manager_scripts' ), $this->_priority, 1 );

        // Show license notices.
        add_action( 'wws_no_license_notice', array( $this, 'no_license_notice' ), $this->_priority, 1 );
        add_action( 'wws_pre_expiry_license_notice', array( $this, 'pre_expiry_license_notice' ), $this->_priority, 2 );
        add_action( 'wws_expired_license_notice', array( $this, 'expired_license_notice' ), $this->_priority, 2 );
        add_action( 'wws_disabled_license_notice', array( $this, 'disabled_license_notice' ), $this->_priority, 2 );

        if ( is_multisite() ) {
            // Network admin notice.
            add_action( 'network_admin_notices', array( $this, 'handle_license_notices' ), $this->_priority );

            // Access license page if wwp and wwlc are network active and accesing via the main blog url. Subsites will be blocked.
            if ( get_current_blog_id() === 1 ) {
                /**
                 * Replace WWP License Settings Menu.
                 * This is to fix the typo in the menu slug introduced in WWP, where the menu slug is 'wws_license_settings'.
                 */
                if ( version_compare( $wwp_plugin_data['Version'], '2.1.11', '<' ) ) {
                    // Add WooCommerce Wholesale Suite License Settings In Multi-Site Environment.
                    add_action( 'network_admin_menu', array( $this, 'register_ms_wws_license_settings_menu' ), 999 - $this->_priority );
                }

                // Add WWS License Settings Page.
                add_action( 'wws_action_license_settings_' . strtolower( $this->_software_key ), array( $this, 'wws_license_settings_page' ) );
            }
        } else {
            /**
             * Replace WWP License Settings Menu.
             * This is to fix the typo in the menu slug introduced in WWP, where the menu slug is 'wws_license_settings'.
             */
            if ( version_compare( $wwp_plugin_data['Version'], '2.1.11', '<' ) ) {
                // Add WooCommerce Wholesale Suite License Menu.
                add_action( 'admin_menu', array( $this, 'register_wws_license_settings_submenu' ), 999 - $this->_priority );
            }

            // Add WWS License Settings Page.
            add_action( 'wws_action_license_settings_' . strtolower( $this->_software_key ), array( $this, 'wws_license_settings_page' ) );

            // Admin Notice.
            add_action( 'admin_notices', array( $this, 'handle_license_notices' ), $this->_priority );
        }
    }
}
