<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model that houses the logic of updating the plugin.
 *
 * @since 1.11
 */
class WWLC_WWS_Update_Manager {
	/*
	|--------------------------------------------------------------------------
	| Class Properties
	|--------------------------------------------------------------------------
	 */

	/**
	 * Property that holds the single main instance of WWLC_WWS_Update_Manager.
	 *
	 * @since  1.11
	 * @access private
	 * @var WWLC_WWS_Update_Manager
	 */
	private static $_instance;

	/*
	|--------------------------------------------------------------------------
	| Class Methods
	|--------------------------------------------------------------------------
	 */

	/**
	 * Property that holds the update data endpoint.
	 *
	 * @since 2.0.0
	 * @access private
	 * @var string
	 */
	private $update_data_endpoint;

	/**
	 * Class constructor.
	 *
	 * @since  1.11
	 * @access public
	 */
	public function __construct() {
		$this->update_data_endpoint = untrailingslashit( WWS_SLMW_SERVER_URL ) . '/wp-json/slmw/v1/license/update';
	}

	/**
	 * Ensure that only one instance of this class is loaded or can be loaded ( Singleton Pattern ).
	 *
	 * @since  1.11
	 * @access public
	 *
	 * @return WWLC_WWS_Update_Manager
	 */
	public static function instance() {

		if ( ! self::$_instance instanceof self ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Hijack the WordPress 'set_site_transient' function for 'update_plugins' transient.
	 * So now we don't have our own cron to check for updates, we just rely on when WordPress check updates for plugins
	 * and themes, and if WordPress does then sets the 'update_plugins' transient, then we hijack it and check for our
	 * own plugin update.
	 *
	 * @param object $update_plugins Update plugins data.
	 *
	 * @since  1.11
	 * @access public
	 */
	public function update_check( $update_plugins ) {

		/**
		 * Function wp_update_plugins calls set_site_transient( 'update_plugins' , ... ) twice, yes twice
		 * so we need to make sure we are on the last call before checking plugin updates
		 * the last call will have the checked object parameter
		 */
		if ( isset( $update_plugins->checked ) ) {
			$this->ping_for_new_version( (bool) filter_input( INPUT_GET, 'force-check', FILTER_VALIDATE_INT ) );
		}

		/**
		 * We try to inject plugin update data if it has any
		 * This is to fix the issue about plugin info appearing/disappearing
		 * when update page in WordPress is refreshed
		 */
		$result            = $this->inject_plugin_update(); // Inject new update data if there are any.
		$installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION ) : get_option( WWLC_OPTION_INSTALLED_VERSION );

		// If the plugin is up to date then put the plugin in no update.
		if ( $result && isset( $result['value'] ) && version_compare( $result['value']->new_version, $installed_version, '==' ) ) {
			unset( $update_plugins->response[ $result['key'] ] );
			$update_plugins->no_update[ $result['key'] ] = $result['value'];

			return $update_plugins;
		}

		// If plugin is outdated then put it under to pending update list.
		if ( $result && isset( $update_plugins->response ) && is_array( $update_plugins->response ) && ! array_key_exists( $result['key'], $update_plugins->response ) ) {
			$update_plugins->response[ $result['key'] ] = $result['value'];
		}

		return $update_plugins;
	}

	/**
	 * Ping plugin for new version. Ping static file first, if indeed new version is available, trigger update data
	 * request.
	 *
	 * @param bool $force Flag to determine whether to "forcefully" fetch the latest update data from the server.
	 *
	 * @access public
	 * @since  1.16.2 We added new parameter $force. This will serve as a flag if we are going to "forcefully" fetch
	 *         the latest update data from the server.
	 *
	 * @since  1.11
	 */
	public function ping_for_new_version( $force = false ) {

		$license_activated = is_multisite() ? get_site_option( WWLC_LICENSE_ACTIVATED ) : get_option( WWLC_LICENSE_ACTIVATED );

		if ( 'yes' !== $license_activated ) {

			if ( is_multisite() ) {
				delete_site_option( WWLC_UPDATE_DATA );
			} else {
				delete_option( WWLC_UPDATE_DATA );
			}

			return;

		}

		$retrieving_update_data = is_multisite() ? get_site_option( WWLC_RETRIEVING_UPDATE_DATA ) : get_option( WWLC_RETRIEVING_UPDATE_DATA );
		if ( 'yes' === $retrieving_update_data ) {
			return;
		}

		/**
		 * Only attempt to get the existing saved update data when the operation is not forced.
		 * Else, if it is forced, we ignore the existing update data if any
		 * and forcefully fetch the latest update data from our server.
		 *
		 * @since 1.16.2
		 */
		if ( ! $force ) {
			$update_data = is_multisite() ? get_site_option( WWLC_UPDATE_DATA ) : get_option( WWLC_UPDATE_DATA );
		} else {
			$update_data = null;
		}

		/**
		 * Even if the update data is still valid, we still go ahead and do static json file ping.
		 * The reason is on WooCommerce 3.3.x , it seems WooCommerce do not regenerate the download url every time you change the downloadable zip file on WooCommerce store.
		 * The side effect is, the download url is still valid, points to the latest zip file, but the update info could be outdated coz we check that if the download url
		 * is still valid, we don't check for update info, and since the download url will always be valid even after subsequent release of the plugin coz WooCommerce is reusing the url now
		 * then there will be a case our update info is outdated. So that is why we still need to check the static json file, even if update info is still valid.
		 */

		$option = apply_filters(
			'wwlc_plugin_new_version_ping_options',
			array(
				'timeout' => 10, // seconds coz only static json file ping.
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		$response = wp_remote_retrieve_body( wp_remote_get( apply_filters( 'wwlc_plugin_new_version_ping_url', WWLC_STATIC_PING_FILE ), $option ) );

		if ( ! empty( $response ) ) {

			$response = json_decode( $response );

			if ( ! empty( $response ) && property_exists( $response, 'version' ) ) {

				$installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION ) : get_option( WWLC_OPTION_INSTALLED_VERSION );

				if ( ( ! $update_data && version_compare( $response->version, $installed_version, '>' ) ) ||
					( $update_data && version_compare( $response->version, $update_data->latest_version, '>' ) ) ) {

					if ( is_multisite() ) {
						update_site_option( WWLC_RETRIEVING_UPDATE_DATA, 'yes' );
					} else {
						update_option( WWLC_RETRIEVING_UPDATE_DATA, 'yes' );
					}

					// Fetch software product update data.
					if ( is_multisite() ) {
						$this->_fetch_software_product_update_data( get_site_option( WWLC_OPTION_LICENSE_EMAIL ), get_site_option( WWLC_OPTION_LICENSE_KEY ), home_url() );
					} else {
						$this->_fetch_software_product_update_data( get_option( WWLC_OPTION_LICENSE_EMAIL ), get_option( WWLC_OPTION_LICENSE_KEY ), home_url() );
					}

					if ( is_multisite() ) {
						delete_site_option( WWLC_RETRIEVING_UPDATE_DATA );
					} else {
						delete_option( WWLC_RETRIEVING_UPDATE_DATA );
					}
				}
			}
		}
	}

	/**
	 * Fetch software product update data.
	 *
	 * @param string $activation_email Activation email.
	 * @param string $license_key      License key.
	 * @param string $site_url         Site url.
	 *
	 * @since  1.11
	 * @access public
	 */
	private function _fetch_software_product_update_data( $activation_email, $license_key, $site_url ) {

		$update_check_url = add_query_arg(
			array(
				'activation_email' => rawurlencode( $activation_email ),
				'license_key'      => $license_key,
				'site_url'         => $site_url,
				'software_key'     => 'WWLC',
				'multisite'        => is_multisite() ? 1 : 0,
			),
			apply_filters( 'wwlc_software_product_update_data_url', $this->update_data_endpoint )
		);

		$option = apply_filters(
			'wwlc_software_product_update_data_options',
			array(
				'timeout' => 30,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		$response = json_decode( wp_remote_retrieve_body( wp_remote_get( $update_check_url, $option ) ) );

		if ( ! empty( $response ) ) {

			WWLC_WWS_License_Manager::instance()->process_license_response( $response, 'update_data' );

			do_action( 'wwlc_software_product_update_data', $response, $activation_email, $license_key );
		}
	}

	/**
	 * Inject plugin update info to plugin update details page.
	 * Note, this is only triggered when there is a new update and the "View version <new version here> details" link
	 * is clicked. In short, the pure purpose for this is to provide details and info the update info popup.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args   Plugin API arguments.
	 *
	 * @since  1.11
	 * @access public
	 *
	 * @return array Plugin update info.
	 */
	public function inject_plugin_update_info( $result, $action, $args ) {

		$license_activated = is_multisite() ? get_site_option( WWLC_LICENSE_ACTIVATED ) : get_option( WWLC_LICENSE_ACTIVATED );

		if ( 'yes' === $license_activated && 'plugin_information' === $action && isset( $args->slug ) && 'woocommerce-wholesale-lead-capture' === $args->slug ) {

			$software_update_data = is_multisite() ? get_site_option( WWLC_UPDATE_DATA ) : get_option( WWLC_UPDATE_DATA );

			if ( $software_update_data ) {

				$update_info = new \StdClass();

				$update_info->name                 = 'WooCommerce Wholesale Lead Capture';
				$update_info->slug                 = 'woocommerce-wholesale-lead-capture';
				$update_info->version              = $software_update_data->latest_version;
				$update_info->tested               = $software_update_data->tested_up_to;
				$update_info->last_updated         = $software_update_data->last_updated;
				$update_info->homepage             = $software_update_data->home_page;
				$update_info->author               = sprintf( '<a href="%s" target="_blank">%s</a>', $software_update_data->author_url, $software_update_data->author );
				$update_info->download_link        = $software_update_data->download_url;
				$update_info->{'update-supported'} = true;
				$update_info->sections             = array(
					'description'  => $software_update_data->description,
					'installation' => $software_update_data->installation,
					'changelog'    => $software_update_data->changelog,
					'support'      => $software_update_data->support,
				);

				$update_info->icons = array(
					'1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-128x128.jpg',
					'2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-256x256.jpg',
					'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-256x256.jpg',
				);

				$update_info->banners = array(
					'low'  => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-772x250.jpg',
					'high' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-1544x500.jpg',
				);

				return $update_info;

			}
		}

		return $result;
	}

	/**
	 * When WordPress fetch 'update_plugins' transient ( Which holds various data regarding plugins, including which
	 * have updates ), we inject our plugin update data in, if any. It is saved on WWLC_UPDATE_DATA option. It is
	 * important we dont delete this option until the plugin have successfully updated. The reason is we are hooking (
	 * and we should do it this way ), on transient read. So if we delete this option on first transient read, then
	 * subsequent read will not include our plugin update data.
	 *
	 * It also checks the validity of the update url. There could be edge case where we stored the update data locally
	 * as an option, then later on the store, the product was deleted or any action occurred that would deem the update
	 * data invalid. So we check if update url is still valid, if not, we remove the locally stored update data.
	 *
	 * @since  1.11
	 * Refactor codebase to adapt being called on set_site_transient function.
	 * We don't need to check for software update data validity as its already been checked on ping_for_new_version
	 * and this function is immediately called right after that.
	 * @access public
	 *
	 * @return array Filtered plugin updates data.
	 */
	public function inject_plugin_update() {

		$license_activated = is_multisite() ? get_site_option( WWLC_LICENSE_ACTIVATED ) : get_option( WWLC_LICENSE_ACTIVATED );
		if ( 'yes' !== $license_activated ) {
			return false;
		}

		$software_update_data = is_multisite() ? get_site_option( WWLC_UPDATE_DATA ) : get_option( WWLC_UPDATE_DATA );

		if ( $software_update_data ) {

			$update = new \stdClass();

			$update->name                 = 'WooCommerce Wholesale Lead Capture';
			$update->id                   = $software_update_data->download_id;
			$update->slug                 = 'woocommerce-wholesale-lead-capture';
			$update->plugin               = WWLC_PLUGIN_BASE_NAME;
			$update->new_version          = $software_update_data->latest_version;
			$update->version              = $software_update_data->latest_version;
			$update->url                  = WWS_SLMW_SERVER_URL;
			$update->package              = $software_update_data->download_url;
			$update->tested               = $software_update_data->tested_up_to;
			$update->{'update-supported'} = true;
			$update->update               = false;
			$update->icons                = array(
				'1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-128x128.jpg',
				'2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-256x256.jpg',
				'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-icon-256x256.jpg',
			);

			$update->banners = array(
				'1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-772x250.jpg',
				'2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-1544x500.jpg',
				'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/wwlc-banner-1544x500.jpg',
			);

			return $update;
		}

		return false;
	}

	/**
	 * Maybe update plugin.
	 *
	 * @param object $update The update object.
	 * @param array  $plugin_data The plugin data.
	 * @param string $plugin_file The plugin file.
	 * @return object
	 */
	public function maybe_update_plugin( $update, $plugin_data, $plugin_file ) {
		if ( WWLC_PLUGIN_BASE_NAME === $plugin_file ) {
			$this->ping_for_new_version( (bool) filter_input( INPUT_GET, 'force-check', FILTER_VALIDATE_INT ) );
			$update = $this->inject_plugin_update();
		}
		return $update;
	}

	/**
	 * Execute Model.
	 *
	 * @since  1.11
	 * @access public
	 */
	public function run() {

		add_filter( 'update_plugins_wholesalesuiteplugin.com', array( $this, 'maybe_update_plugin' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'inject_plugin_update_info' ), 10, 3 );
	}
}
