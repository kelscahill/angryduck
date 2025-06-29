<?php
/**
 * Leads admin page class
 *
 * @since 2.0.0 - Added
 * @version 2.0.0
 * @package WooCommerce_Wholesale_Lead_Capture
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Leads admin model.
 *
 * @version 2.0.0
 */
class WWLC_Leads_Admin_Page {
	/**
     * Property that holds the single main instance of WWPP_Dashboard.
     *
     * @since  2.0.0
     * @access private
     * @var WWLC_Leads_Admin_Page
     */
	private static $_instance;

	/**
     * Property that holds the single main instance of WWLC_User_Account.
     *
     * @since 2.0.0
     * @access private
     * @var WWLC_User_Account
     */
    private $_wwlc_user_account;

	/**
	 * Holds the leads vite app instance
	 *
	 * @var WWLC_Leads_App
	 */
	private $_leads_app;

	/**
	 * WWLC_Admin_Settings constructor.
	 *
	 * @param array $dependencies The list of dependencies required for the functionality of this model.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function __construct( $dependencies ) {
		$this->_wwlc_user_account = $dependencies['WWLC_User_Account'];
		$this->_leads_app         = WWLC_Leads_App::instance( $dependencies );
	}

	/**
	 * Ensure that only one instance of WWLC_Leads_Admin_Page is loaded or can be loaded (Singleton Pattern).
	 *
	 * @param array $dependencies Array of instance objects of all dependencies of WWLC_Leads_Admin_Page model.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return WWLC_Leads_Admin_Page
	 */
	public static function instance( $dependencies = null ) {

		if ( ! self::$_instance instanceof self ) {
			self::$_instance = new self( $dependencies );
		}

		return self::$_instance;
	}

	/**
	 * Initialize hooks and filters
	 *
	 * @access public
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function run() {
        add_action( 'admin_menu', array( $this, 'leads_page_submenu' ), 99 );
		add_action( 'admin_menu', array( $this, 'unapproved_bubble_count' ), 100 );

		$this->_leads_app->run();
	}

	/**
	 * Add the leads admin menu
	 *
	 * @access public
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function leads_page_submenu() {
		add_submenu_page(
			'wholesale-suite', // Settings.
			__( 'Leads', 'woocommerce-wholesale-lead-capture' ),
			__( 'Leads', 'woocommerce-wholesale-lead-capture' ),
			'manage_woocommerce', // phpcs:ignore.
			'wwlc-leads-admin-page',
			array( $this, 'leads_page' ),
			2
		);
	}

	/**
	 * Render the leads admin page.
	 *
	 * @access public
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function leads_page() {
		?>
		<div class="wrap">
			<div id="leads-admin-page" class="leads-admin-page"></div>
        </div>
		<?php
	}

	/**
     * Display unapproved users bubble count.
	 *
	 * @access public
	 * @since 2.0.0
     *
     * @since 2.0.0
	 * @since 2.0.0 Renamed to use unapproved role..
     */
	public function unapproved_bubble_count() {

		global $submenu;
		$total_unapproved_users = $this->_wwlc_user_account->get_total_unapproved_users();

		if ( ! $total_unapproved_users ) {
			return;
		}

        foreach ( $submenu as $parent_slug => $submenu_items ) {
            if ( 'wholesale-suite' !== $parent_slug ) {
                continue;
            }
            foreach ( $submenu_items as $key => $submenu_item ) {
                if ( 'wwlc-leads-admin-page' === $submenu_item[2] ) {
                    $bubble_count = sprintf(
                        ' <span class="awaiting-mod count-%s"><span class="chat-count">%s</span></span>',
                        $total_unapproved_users,
                        $total_unapproved_users
                    );

                    $submenu[$parent_slug][$key][0] .= $bubble_count; //phpcs:ignore
                    return;
                }
            }
        }
	}
}
