<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class that defines the Leads Admin Page feature.
 */
class WWLC_Leads_App {
    /**
     * Property that holds the single main instance of WWPP_Dashboard.
     *
     * @since  2.0.0
     * @access private
     * @var WWLC_Leads_App
     */
	private static $_instance;

	/**
     * Is hmr enabled
     *
     * @var bool $is_hmr_enabled Is hmr enabled
     */
    public $is_hmr_enabled = false;

    /**
     * Env
     *
     * @var array $env Env
     */
    protected $env = array();

    /**
     * Host
     *
     * @var string $host Host
     */
    protected $host = '';

    /**
     * Port
     *
     * @var string $port Port
     */
    protected $port = '';

    /**
     * Dev base url
     *
     * @var string $dev_base_url Dev base url.
     */
    protected $dev_base_url = '';

	/**
	 * The WWLC Version
	 *
	 * @var string $wwlc_version The WWLC current version.
	 */
	protected $wwlc_version;

    /**
     * Holds the WWLC_User_Custom_Fields instance
     *
     * @var WWLC_User_Custom_Fields
     */
    public $wwlc_user_custom_fields;

    /**
     * Holds the WWP_Wholesale_Roles instance
     *
     * @var WWP_Wholesale_Roles
     */
    public $wwp_wholesale_roles = null;

    /**
	 * Ensure that only one instance of WWLC_Leads_App is loaded or can be loaded (Singleton Pattern).
	 *
	 * @param array $dependencies Array of instance objects of all dependencies of WWLC_Leads_App model.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return WWLC_Leads_App
	 */
	public static function instance( $dependencies = null ) {

		if ( ! self::$_instance instanceof self ) {
			self::$_instance = new self( $dependencies );
		}

		return self::$_instance;
	}

    /**
     * Create an instance of the class.
	 *
	 * @param array $dependencies The dependencies of this file.
     */
    public function __construct( $dependencies ) {
		$this->wwlc_version            = $dependencies['WWLC_Version'];
        $this->wwlc_user_custom_fields = $dependencies['WWLC_User_Custom_Fields'];

        if ( class_exists( 'WWP_Wholesale_Roles' ) ) {
            $this->wwp_wholesale_roles = WWP_Wholesale_Roles::getInstance();
        }

        // Fix for HMR_DEV undefined constant warning.
        $hmr_dev = defined( 'HMR_DEV' ) ? HMR_DEV : false;
        if ( 'wwlc' === $hmr_dev ) {
            $this->is_hmr_enabled = true;
            $this->parse_env();
            $this->host         = isset( $this->env['VITE_DEV_SERVER_HOST'] ) ? $this->env['VITE_DEV_SERVER_HOST'] : 'localhost';
            $this->port         = isset( $this->env['VITE_DEV_SERVER_PORT'] ) ? $this->env['VITE_DEV_SERVER_PORT'] : '3000';
            $this->dev_base_url = isset( $this->env['VITE_DEV_SERVER_ORIGIN'] )
                ? $this->env['VITE_DEV_SERVER_ORIGIN']
                : 'https://' . $this->host . ':' . $this->port;
        }
    }

    /**
     * Initialize the hooks.
     *
     * @return void
     */
    public function run() {
        if ( ! isset( $_GET['page'] ) || 'wwlc-leads-admin-page' !== $_GET['page'] ) { // phpcs:ignore
            return;
        }

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_module_to_scripts_tag' ), 10, 2 );
    }

	/**
	 * Register and enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
        $roles = $this->get_user_roles();
        $i18n  = array(
			'app_name'            => __( 'Wholesale Leads', 'woocommerce-wholesale-lead-capture' ),
            'logo_alt'            => __( 'Wholesale Suite Logo', 'woocommerce-wholesale-lead-capture' ),
			'select_all'          => __( 'Select All', 'woocommerce-wholesale-lead-capture' ),
			'name'                => __( 'Name', 'woocommerce-wholesale-lead-capture' ),
			'email_address'       => __( 'Email Address', 'woocommerce-wholesale-lead-capture' ),
			'role'                => __( 'Role', 'woocommerce-wholesale-lead-capture' ),
			'registration_date'   => __( 'Registration Date', 'woocommerce-wholesale-lead-capture' ),
			'status'              => __( 'Status', 'woocommerce-wholesale-lead-capture' ),
			'action'              => __( 'Action', 'woocommerce-wholesale-lead-capture' ),
			'search'              => __( 'Search', 'woocommerce-wholesale-lead-capture' ),
			'search_placeholder'  => __( 'Search (name, or email)', 'woocommerce-wholesale-lead-capture' ),
			'quick_filters'       => __( 'Quick Filters:', 'woocommerce-wholesale-lead-capture' ),
			'all'                 => __( 'All', 'woocommerce-wholesale-lead-capture' ),
			'approved'            => __( 'Approved', 'woocommerce-wholesale-lead-capture' ),
			'pending'             => __( 'Pending', 'woocommerce-wholesale-lead-capture' ),
			'rejected'            => __( 'Rejected', 'woocommerce-wholesale-lead-capture' ),
			'unapproved'          => __( 'Unapproved', 'woocommerce-wholesale-lead-capture' ),
			'inactive'            => __( 'Inactive', 'woocommerce-wholesale-lead-capture' ),
			'bulk_actions'        => __( 'Bulk Actions', 'woocommerce-wholesale-lead-capture' ),
			'approve'             => __( 'Approve', 'woocommerce-wholesale-lead-capture' ),
			'reject'              => __( 'Reject', 'woocommerce-wholesale-lead-capture' ),
			'deactivate'          => __( 'Deactivate', 'woocommerce-wholesale-lead-capture' ),
			'filter'              => __( 'Filter', 'woocommerce-wholesale-lead-capture' ),
            'processing'          => __( 'Processing', 'woocommerce-wholesale-lead-capture' ),
            'apply_action'        => __( 'Apply', 'woocommerce-wholesale-lead-capture' ),
            'all_statuses'        => __( 'All Statuses', 'woocommerce-wholesale-lead-capture' ),
            'all_roles'           => __( 'All Roles', 'woocommerce-wholesale-lead-capture' ),

			// For the form.
			'edit_user'           => __( 'Edit User', 'woocommerce-wholesale-lead-capture' ),
			'billing_address'     => __( 'Billing Address', 'woocommerce-wholesale-lead-capture' ),
			'shipping_address'    => __( 'Shipping Address', 'woocommerce-wholesale-lead-capture' ),
			'same_as_billing'     => __( 'Same as Billing Details?', 'woocommerce-wholesale-lead-capture' ),
			'first_name'          => __( 'First name', 'woocommerce-wholesale-lead-capture' ),
			'last_name'           => __( 'Last name', 'woocommerce-wholesale-lead-capture' ),
			'website_url'         => __( 'Website URL', 'woocommerce-wholesale-lead-capture' ),
            'company_name'        => __( 'Company Name', 'woocommerce-wholesale-lead-capture' ),
			'save_user'           => __( 'Save User', 'woocommerce-wholesale-lead-capture' ),
			'cancel'              => __( 'Cancel', 'woocommerce-wholesale-lead-capture' ),

			// Address form.
			'address_1'           => __( 'Address Line 1', 'woocommerce-wholesale-lead-capture' ),
			'address_2'           => __( 'Address Line 2', 'woocommerce-wholesale-lead-capture' ),
			'country'             => __( 'Country', 'woocommerce-wholesale-lead-capture' ),
			'state'               => __( 'State', 'woocommerce-wholesale-lead-capture' ),
			'city'                => __( 'City', 'woocommerce-wholesale-lead-capture' ),
			'zip_code'            => __( 'ZIP/Postal Code', 'woocommerce-wholesale-lead-capture' ),
			'phone_number'        => __( 'Phone Number', 'woocommerce-wholesale-lead-capture' ),
			'phone_placeholder'   => __( '+99 9999999999', 'woocommerce-wholesale-lead-capture' ),

			// Custom fields.
			'custom_fields'       => __( 'Custom Fields', 'woocommerce-wholesale-lead-capture' ),

            // Error messages.
            'select_action_text'  => __( 'Please select one of the bulk actions to proceed', 'woocommerce-wholesale-lead-capture' ),
            'select_rows_text'    => __( 'Please select one or more rows to process.', 'woocommerce-wholesale-lead-capture' ),

            // Confirmation messages.
            'ok'                  => __( 'Ok', 'woocommerce-wholesale-lead-capture' ),
            'approve_title'       => __( 'Approve User?', 'woocommerce-wholesale-lead-capture' ),
			'approve_text'        => __( 'Are you sure you want to approve this user?', 'woocommerce-wholesale-lead-capture' ),
            'deactivate_title'    => __( 'Deactivate User?', 'woocommerce-wholesale-lead-capture' ),
			'deactivate_text'     => __( 'Are you sure you want to deactivate this user?', 'woocommerce-wholesale-lead-capture' ),
            'reject_title'        => __( 'Reject User?', 'woocommerce-wholesale-lead-capture' ),
			'reject_text'         => __( 'Are you sure you want to reject this user?', 'woocommerce-wholesale-lead-capture' ),

            // Popup messages.
            'approve_popup_title' => __( 'Approve User', 'woocommerce-wholesale-lead-capture' ),
            'approve_popup_text'  => __( 'Approve this lead and grant them access to your site.', 'woocommerce-wholesale-lead-capture' ),
            'edit_popup_title'    => __( 'Edit User', 'woocommerce-wholesale-lead-capture' ),
            'edit_popup_text'     => __( "Update this lead's details or modify their role assignment", 'woocommerce-wholesale-lead-capture' ),
            'reject_popup_title'  => __( 'Reject User', 'woocommerce-wholesale-lead-capture' ),
            'reject_popup_text'   => __( 'Reject this lead from the pending list.', 'woocommerce-wholesale-lead-capture' ),
        );

        foreach ( $roles as $role_key => $role_name ) {
            $i18n[ $role_key ] = $role_name;
        }

        $wwlc_lap_object = array(
            'rest_url'             => rest_url( 'wwlc/v1' ),
            'leads_page_url'       => admin_url( 'admin.php?page=wwlc-leads-admin-page' ),
            'nonce'                => wp_create_nonce( 'wp_rest' ),
			'wholesale_suite_logo' => WWLC_IMAGES_ROOT_URL . 'wholesale-suite-logo.svg',
            'admin_url'            => admin_url(),
			'roles'                => $roles,
            'address_enabled'      => ( 'yes' === get_option( 'wwlc_fields_activate_address_field' ) ) ? 'yes' : 'no',
            'countries'            => $this->get_countries(),
            'registration_fields'  => $this->wwlc_user_custom_fields->get_registration_form_fields(),
            'custom_fields'        => $this->wwlc_user_custom_fields->get_custom_fields(),
            'i18n'                 => $i18n,
            'leads_per_page'       => apply_filters( 'wwlc_leads_per_page', 15 ),
        );

		if ( $this->is_hmr_enabled ) {
            wp_register_script(
                'wwlc-leads-admin-page-vite-client',
                "$this->dev_base_url/@vite/client",
                array(),
                time(),
                true
            );
            wp_register_script(
                'wwlc-leads-admin-page-index',
                "$this->dev_base_url/apps/leads-page/index.ts",
                array( 'jquery', 'editor', 'wwlc-leads-admin-page-vite-client' ),
                time(),
                true
            );

            wp_enqueue_script( 'wwlc-leads-admin-page-vite-client' );
            wp_enqueue_script( 'wwlc-leads-admin-page-index' );

        } else {
            $manifest_json = $this->get_manifest_json();

            $styles = $manifest_json['apps/leads-page/index.ts']['css'] ?? array();

            if ( ! empty( $styles ) ) {
                foreach ( $styles as $style ) {
                    wp_enqueue_style(
						'wwlc-leads-admin-page-' . $style,
						esc_url( WWLC_DIST_URL . $style ),
						array(),
						$this->wwlc_version
					);
                }
            }
            foreach ( $manifest_json as $entry => $info ) {
                $file_ext = $this->get_file_extension( $info['file'] );

                if ( 'js' === $file_ext ) {
                    wp_enqueue_script(
						'wwlc-leads-admin-page-' . $this->get_entry_file_name( $entry ),
						esc_url( WWLC_DIST_URL . $info['file'] ),
						array(),
						$this->wwlc_version,
						true
					);
                }
            }
        }

        wp_localize_script(
            'wwlc-leads-admin-page-index',
            'wwlc_lap',
            $wwlc_lap_object
        );
	}

	/**
     * Read and parse the .env file to get env variables.
     *
     * @return void
     */
    protected function parse_env() {
        $env_file_path = WWLC_PLUGIN_DIR . '/.env';
        if ( ! file_exists( $env_file_path ) ) {
            return;
        }

        $env_file = file_get_contents( $env_file_path ); // phpcs:ignore
        $env_file = explode( "\n", $env_file );
        foreach ( $env_file as $env ) {
            $env = explode( '=', $env );
            if ( isset( $env[0] ) && isset( $env[1] ) ) {
                $key               = trim( $env[0] );
                $val               = trim( $env[1] );
                $this->env[ $key ] = $val;
            }
        }
    }

	/**
     * Get the contents of the manifest json.
	 *
	 * @return mixed
     */
    public function get_manifest_json() {
        $manifest_json_path = apply_filters(
			'wwlc_leads_admin_page_manifest_json_path',
			WWLC_PLUGIN_DIR . 'dist/.vite/manifest.json'
		);
        $response           = file_get_contents( $manifest_json_path ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return json_decode( $response, true );
    }

    /**
     * Get file extension
     *
     * @param string $file File path.
	 * @return string
     */
    public function get_file_extension( $file ) {
        $file_info      = explode( '.', $file );
        $file_extension = end( $file_info );
        return $file_extension;
    }

    /**
     * Get entry file name from file path
     *
     * @param string $entry The entry file path.
	 * @return string
     */
    public function get_entry_file_name( $entry ) {
        $file_info           = explode( '/', $entry );
        $file_with_extension = end( $file_info );
        $file_info           = explode( '.', $file_with_extension );
        return $file_info[0];
    }

    /**
     * Add module to scripts tag
     *
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     *
     * @return string
     */
    public function add_module_to_scripts_tag( $tag, $handle ) {
        $module_handles = array(
            'wwlc-leads-admin-page-vite-client',
            'wwlc-leads-admin-page-index',
        );

        $nomodule_handles = array(
            'wwlc-leads-admin-page-index-legacy',
            'wwlc-leads-admin-page-legacy-polyfills-legacy',
        );

        if ( in_array( $handle, $module_handles, true ) ) {
            $tag = str_replace( ' src', ' type="module" src', $tag );
        }

        if ( in_array( $handle, $nomodule_handles, true ) ) {
            $tag = str_replace( ' src', ' nomodule src', $tag );
        }

        return $tag;
    }

    /**
     * Get the registered user roles.
     *
     * Gets all registered WordPress user roles as key-value pair.
     *
     * @return array
     */
    public function get_user_roles() {
        return WWLC_Helper_Functions::get_wholesale_lead_roles();
    }

    /**
     * Get all registered wholesale roles.
     *
     * @return array $registered_wholesale_roles All the registered wholesale roles.
     * @version 2.0.0
     * @since   2.0.0
     */
    public function get_registered_wholesale_roles() {
        if ( ! $this->wwp_wholesale_roles ) {
            return maybe_unserialize( get_option( 'wwp_options_registered_custom_roles' ) );
        }

        $registered_wholesale_roles = $this->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        $wholesale_roles = array();
        foreach ( $registered_wholesale_roles as $role_key => $role_data ) {
            $wholesale_roles[ $role_key ] = $role_data['roleName'];
        }

        /**
         * Allow to modify the registered wholesale roles.
         *
         * @param array $registered_wholesale_roles All the registered wholesale roles.
         */
        return apply_filters(
            'wwlc_registered_wholesale_roles',
            $wholesale_roles
        );
    }

    /**
     * Get a list of countries and their states.
     *
     * @return array{
     *      code: mixed,
     *      name: mixed,
     *      states: array|bool[]
     * }
     */
    public function get_countries() {
        $countries_list = array();

        if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            return $countries_list;
        }

        $wc_countries = new WC_Countries();
        $countries    = $wc_countries->get_countries();

        foreach ( $countries as $cc => $name ) {
            $countries_list[ $cc ] = array(
                'code'   => $cc,
                'name'   => $name,
                'states' => $wc_countries->get_states( $cc ),
            );
        }

        return $countries_list;
    }
}
