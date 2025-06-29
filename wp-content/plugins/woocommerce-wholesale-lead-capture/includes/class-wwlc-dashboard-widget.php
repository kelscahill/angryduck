<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WWLC_Dashboard_Widget' ) ) {

	/**
	 * WWLC Dashboard Widget class
	 *
	 * @since 2.0.0 Removed inactive and unmoderated roles.
	 */
	class WWLC_Dashboard_Widget {

        /**
         * --------------------------------------------------------------------------
         * Class Properties
         * --------------------------------------------------------------------------
         */

		/**
         * Property that holds the single main instance of WWLC_Dashboard_Widget.
         *
         * @since 1.8.0
         * @access private
         * @var WWLC_Dashboard_Widget
         */
		private static $_instance;

        /**
         * --------------------------------------------------------------------------
         *  Class Methods
         * --------------------------------------------------------------------------
         */

        /**
         * WWLC_Dashboard_Widget constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Dashboard_Widget model.
         *
         * @since 1.8.0
         */
        public function __construct( $dependencies ) {}

		/**
		 * Singleton Pattern.
		 *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Dashboard_Widget model.
         *
         * @return WWLC_Dashboard_Widget
		 * @since 1.8.0
         */
		public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
		}

		/**
		 * Creates new dashbpard widget.
		 *
		 * @since 1.8.0
         */
		public function wwlc_insert_dashboard_widget() {

			wp_add_dashboard_widget(
             	'wwlc_lead_capture_stats_widget',             // Widget slug.
           		'Lead Capture Statistics',                  // Title.
             	array( $this, 'wwlc_lead_capture_statistics' ) // Display function.
	        );
		}

		/**
		 * WWLC statistics.
		 *
		 * @return array
		 * @since 1.8.0
         */
		public function calculate_lead_capture_statics() {

			$total_pending       = $this->calculate_total( 'pending' );
			$total_approved      = $this->calculate_total( 'approved' );
			$total_rejected      = $this->calculate_total( 'rejected' );
			$total_active        = $this->calculate_total( 'active' );
			$total_inactive      = $this->calculate_total( 'inactive' );
			$total_registrations = $total_pending + $total_approved + $total_rejected;

			return array(
				'total_registrations' => $total_registrations,
				'total_pending'       => $total_pending,
				'total_approved'      => $total_approved,
				'total_rejected'      => $total_rejected,
				'total_active'        => $total_active,
				'total_inactive'      => $total_inactive,
			);
		}

		/**
		 * SQL query for computing statistics
		 *
		 * @param string $user_status The user status.
		 * @return int
		 * @since 1.12
         */
		public function calculate_total( $user_status = null ) {
			global $wpdb;

			$result = 0;
			switch ( $user_status ) {
				case 'pending':
					$result = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON (u.ID = um.user_id)
							WHERE um.meta_key = %s AND um.meta_value LIKE %s",
							'wp_capabilities',
							'%wwlc_unapproved%'
						)
					);
					break;
				case 'approved':
					$result = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON (u.ID = um.user_id)
							WHERE um.meta_key = %s",
							'wwlc_approval_date'
						)
					);
					break;
				case 'rejected':
					$result = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um ON (u.ID = um.user_id)
							WHERE um.meta_key = %s",
							'wwlc_rejection_date'
						)
					);
					break;
				case 'active':
					$result = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um1 ON (u.ID = um1.user_id)
							WHERE um1.meta_key = %s AND um1.meta_value LIKE %s",
							'wp_capabilities',
							'%wholesale_%'
						)
					);
					break;
				case 'inactive':
					$result = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->users} u
							INNER JOIN {$wpdb->usermeta} um1 ON (u.ID = um1.user_id)
							WHERE um1.meta_key = %s AND um1.meta_value = %s",
							'wwlc_is_user_active',
							'no'
						)
					);
					break;
				default:
					$result = 0;
					break;
			}

			return $result ? (int) $result : 0;
		}

		/**
		 * Handles displaying data inside the dashboard widget.
		 *
		 * @since 1.8.0
         */
		public function wwlc_lead_capture_statistics() {

			$lead_capture_stats = $this->calculate_lead_capture_statics(); ?>

			<table class="wwlc_stats">
				<tr>
					<td>
                    <?php
						esc_html_e( 'Total Registrations', 'woocommerce-wholesale-lead-capture' );
						echo wp_kses_post(
							wc_help_tip(
								__(
                                    'Total customers that have registered via the wholesale registration form, including any accounts that are now inactive or have had their user role changed.',
                                    'woocommerce-wholesale-lead-capture'
                                )
							)
						);
                        ?>
                        :
					</td>
					<td><b><?php echo esc_html( $lead_capture_stats['total_registrations'] ); ?></b></td>
				</tr>
				<tr>
					<td>
                    <?php
						esc_html_e( 'Total Pending', 'woocommerce-wholesale-lead-capture' );
						echo wp_kses_post(
							wc_help_tip(
                                __(
                                    'Total customers that registered via the wholesale registration form where their status is still pending.',
                                    'woocommerce-wholesale-lead-capture'
                                )
                            )
						);
                        ?>
                        :
					</td>
					<td><b><?php echo esc_html( $lead_capture_stats['total_pending'] ); ?></b></td>
				</tr>
				<tr>
					<td>
                    <?php
						esc_html_e( 'Total Approved', 'woocommerce-wholesale-lead-capture' );
						echo wp_kses_post(
							wc_help_tip(
                                __(
                                    'Total customers that registered via the wholesale registration form that have been approved, regardless of subsequent user role changes.',
                                    'woocommerce-wholesale-lead-capture'
                                )
                            )
						);
                        ?>
                        :
					</td>
					<td><b><?php echo esc_html( $lead_capture_stats['total_approved'] ); ?></b></td>
				</tr>
				<tr>
					<td>
                    <?php
						esc_html_e( 'Total Rejected', 'woocommerce-wholesale-lead-capture' );
						echo wp_kses_post(
							wc_help_tip(
                                __(
                                    'Total customers that registered via the wholesale registration form that have been rejected.',
                                    'woocommerce-wholesale-lead-capture'
                                )
                            )
						);
                        ?>
                        :
					</td>
					<td><b><?php echo esc_html( $lead_capture_stats['total_rejected'] ); ?></b></td>
				</tr>

				<?php if ( wwlc_is_wwp_and_wwpp_active() ) { ?>

					<tr>
						<td>
                        <?php
							esc_html_e( 'Total Active Wholesale Customers', 'woocommerce-wholesale-lead-capture' );
							echo wp_kses_post(
								wc_help_tip(
                                    __(
                                        'Total customers that registered via the wholesale registration form that are still active and have a wholesale user role.',
                                        'woocommerce-wholesale-lead-capture'
                                    )
                                )
							);
                            ?>
                            :
						</td>
						<td><b><?php echo esc_html( $lead_capture_stats['total_active'] ); ?></b></td>
					</tr>
					<tr>
						<td>
                        <?php
							esc_html_e( 'Total Inactive Wholesale Customers', 'woocommerce-wholesale-lead-capture' );
							echo wp_kses_post(
								wc_help_tip(
                                    __(
                                        'Total customers that registered via the wholesale registration form but are now inactive or have had their user role changed.',
                                        'woocommerce-wholesale-lead-capture'
                                    )
                                )
							);
                            ?>
                            :
						</td>
						<td><b><?php echo esc_html( $lead_capture_stats['total_inactive'] ); ?></b></td>
					</tr>

				<?php } ?>

			</table>
            <?php
		}

	    /**
	     * Stats styles and Lead Capture Statistics Tooltip.
	     *
	     * @since 1.8.0
	     * @access public
	     */
		public function dashboard_widget_scripts_styles() {

			$screen = get_current_screen();

			if ( 'dashboard' === $screen->id && 'dashboard' === $screen->base ) {
            ?>

				<style type="text/css">
					#wwlc_lead_capture_stats_widget table{ width:100%; }
					#wwlc_lead_capture_stats_widget table tr td{ padding: 5px; }
					#wwlc_lead_capture_stats_widget table tr td:first-child{ width: 260px; text-align: right; }
					#wwlc_lead_capture_stats_widget table tr td:last-child{ text-align: left; }
					.wwlc_stats .woocommerce-help-tip{ font-size: 18px; margin: 0px 4px; }
				</style>

				<script type="text/javascript">
		            // qtip tooltip
		            jQuery( document ).ready( function( $ ) {
			            $( '.woocommerce-help-tip' ).tipTip( {
							'attribute': 'data-tip',
							'fadeIn': 50,
							'fadeOut': 50,
							'delay': 200
						} );
					} );
				</script>
                <?php
			}
		}

	    /**
	     * Execute model.
	     *
	     * @since 1.8.0
	     * @access public
	     */
	    public function run() {
	    	add_action( 'wp_dashboard_setup', array( $this, 'wwlc_insert_dashboard_widget' ) );
	    	add_action( 'admin_footer', array( $this, 'dashboard_widget_scripts_styles' ) );
	    }
	}
}
