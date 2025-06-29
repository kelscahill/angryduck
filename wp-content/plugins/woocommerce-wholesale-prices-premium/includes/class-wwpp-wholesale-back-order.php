<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WWPP_Wholesale_Back_Order' ) ) {

	/**
	 * Model that houses the logic wholesale back orders.
	 *
	 * @since 1.14.0
	 */
	class WWPP_Wholesale_Back_Order {

		/*
		|--------------------------------------------------------------------------
		| Class Properties
		|--------------------------------------------------------------------------
		*/

		/**
		 * Property that holds the single main instance of WWPP_Wholesale_Back_Order.
		 *
		 * @since  1.14.0
		 * @access private
		 * @var WWPP_Wholesale_Back_Order
		 */
		private static $_instance;

		/**
		 * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
		 *
		 * @since  1.14.0
		 * @access private
		 * @var WWPP_Wholesale_Roles
		 */
		private $_wwpp_wholesale_roles;

		/*
		|--------------------------------------------------------------------------
		| Class Methods
		|--------------------------------------------------------------------------
		*/

		/**
		 * WWPP_Wholesale_Back_Order constructor.
		 *
		 * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Back_Order model.
		 *
		 * @since  1.14.0
		 * @access public
		 *
		 */
		public function __construct( $dependencies ) {

			$this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];

		}

		/**
		 * Ensure that only one instance of WWPP_Wholesale_Back_Order is loaded or can be loaded (Singleton Pattern).
		 *
		 * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Back_Order model.
		 *
		 * @return WWPP_Wholesale_Back_Order
		 * @since  1.14.0
		 * @access public
		 *
		 */
		public static function instance( $dependencies ) {

			if ( ! self::$_instance instanceof self ) {
				self::$_instance = new self( $dependencies );
			}

			return self::$_instance;

		}

		/**
		 * Filter to determine if backorders are allowed for wholesale customers or not. If the global setting for
		 * backorders is on, this should always return true. Otherwise, it goes with whatever the WC system thinks is
		 * the case for that particular product.
		 *
		 * @param boolean $backorders_allowed Boolean from WC filter that says if back orders are allowed or not.
		 * @param int     $product_id         Product id.
		 *
		 * @return boolean Filtered boolean that overrides if back orders are allowed or not for wholesale customers.
		 * @since  1.14.0 Refactor codebase and move to its proper model.
		 * @access public
		 *
		 * @since  1.6.0
		 */
		public function always_allow_back_orders_to_wholesale_users( $backorders_allowed, $product_id ) {

			$user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

			// Check if user is not an admin, else we don't want to restrict admins in any way.
			if ( ! current_user_can( 'manage_options' ) ) {
				if ( 'yes' === get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) &&
					! empty( $user_wholesale_role ) ) {
					$backorders_allowed = true;
				}
			}

			return apply_filters(
				'wwpp_filter_product_backorders_allowed',
				$backorders_allowed,
				$product_id,
				$user_wholesale_role
			);

		}

		/**
		 * The reason being is that on WooCommerce 2.6.0 there has been a major revision on the backorders logic.
		 * https://github.com/woothemes/woocommerce/issues/11187.
		 *
		 * @param boolean $backorders_allowed Flag that determines if back orders are allowed or not.
		 *
		 * @return boolean Filtered flag that determines if back orders are allowed or not.
		 * @since  1.9.2
		 * @since  1.14.0 Refactor codebase and move to its proper model.
		 * @access public
		 *
		 */
		public function always_allow_back_orders_to_wholesale_users_set_product_in_stock( $backorders_allowed ) {

			$user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

			if ( version_compare( WC()->version, '2.6.0', '>=' ) ) {

				// We only do this on WooCommerce 2.6.x series and up due to changes behavior in backorders logic

				// Check if user is not an admin, else we don't want to restrict admins in any way.
				if ( ! current_user_can( 'manage_options' ) ) {
					if ( 'yes' === get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) &&
						! empty( $user_wholesale_role ) ) {
						$backorders_allowed = true;
					}
				}
			}

			return apply_filters( 'wwpp_filter_product_is_in_stock', $backorders_allowed, $user_wholesale_role );

		}

		/**
		 * Show back order notice to wholesale users if wholesale user orders via back order
		 * and always allow back order for wholesale users option is enabled.
		 *
		 * @param boolean    $show_back_order_notice Boolean value determining whether to show or not show back order notice.
		 * @param WC_Product $product                Product object.
		 *
		 * @return boolean Boolean value determining whether to show or not show back order notice.
		 * @since  1.16.4
		 * @access public
		 *
		 */
		public function show_back_order_notice_to_wholesale_users( $show_back_order_notice, $product ) {

			$user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

			if ( ! current_user_can( 'manage_options' ) ) {
				if ( 'yes' === get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) &&
					! empty( $user_wholesale_role ) ) {
					if ( 'yes' === get_option( 'wwpp_settings_show_back_order_notice_wholesale_users', false ) ) {
						$show_back_order_notice = true;
					} else {
						$show_back_order_notice = false;
					}
				}
			}

			return apply_filters(
				'wwpp_filter_show_back_order_notice',
				$show_back_order_notice,
				$product,
				$user_wholesale_role
			);

		}

		/**
		 * Sets product stock status to `onbackorder` for wholesale users with the global settings to always notify is
		 * enabled.
		 *
		 * @param string     $value
		 * @param WC_Product $product
		 *
		 * @return string
		 */
		public function maybe_set_product_on_backorder( $value, $product ) {

			global $wc_wholesale_prices;

			$wholesale_roles   = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();
			$wholesale_roles   = ! empty( $wholesale_roles ) ? array_keys( $wholesale_roles ) : array();
			$current_user_role = wp_get_current_user()->roles[0] ?? '';

			if ( null === $product->get_stock_quantity( 'edit' ) &&
				'instock' !== $product->get_stock_status( 'edit' ) &&
				$current_user_role && ! empty( $wholesale_roles ) &&
				in_array( $current_user_role, $wholesale_roles, true ) &&
				'yes' === get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) &&
				'yes' === get_option( 'wwpp_settings_show_back_order_notice_wholesale_users', false ) ) {
				$value = 'onbackorder';
			}

			return $value;
		}

		/*
		|--------------------------------------------------------------------------
		| Execute Model
		|--------------------------------------------------------------------------
		*/

		/**
		 * Execute model.
		 *
		 * @since  1.14.0
		 * @access public
		 */
		public function run() {

			add_filter(
				'woocommerce_product_backorders_allowed',
				array( $this, 'always_allow_back_orders_to_wholesale_users' ),
				10,
				2
			);
			add_filter(
				'woocommerce_product_is_in_stock',
				array( $this, 'always_allow_back_orders_to_wholesale_users_set_product_in_stock' ),
				10,
				1
			); // ( WooCommerce 2.6.0 )

			add_filter(
				'woocommerce_product_backorders_require_notification',
				array( $this, 'show_back_order_notice_to_wholesale_users' ),
				10,
				2
			);

			add_filter(
				'woocommerce_product_get_stock_status',
				array( $this, 'maybe_set_product_on_backorder' ),
				10,
				2
			);
			add_filter(
				'woocommerce_product_variation_get_stock_status',
				array( $this, 'maybe_set_product_on_backorder' ),
				10,
				2
			);
		}

	}

}
