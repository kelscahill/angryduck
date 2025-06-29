<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WWLC_Cron' ) ) {

    /**
     * WWLC_Cron class.
     *
     * @since 1.6.3
     */
	class WWLC_Cron {
        /*
        |--------------------------------------------------------------------------
        | Class Properties.
        |--------------------------------------------------------------------------
        */

		/**
         * Property that holds the single main instance of WWLC_Cron.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Cron
         */
		private static $_instance;

		/*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWLC_Cron constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Cron model.
         *
         * @access public
         * @since 1.6.3
         */
		public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWLC_Cron is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Cron model.
         *
         * @return WWLC_Cron
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

		/**
		 * Delete's all files uploaded temporarily that hasn't been assigned to a user.
		 * Cron function scheduled daily
		 *
         * @since 2.0.0 - Replaced unlink() with wp_delete_file() which is the WordPress recommended way to delete files.
		 * @since 1.6.0
		 */
		public function wwlc_delete_temp_files_daily() {

			$temp_upload = get_option( 'wwlc_temp_upload_directory' );

            if ( false !== $temp_upload ) {
                // Cycle through all files in the directory.
                foreach ( glob( $temp_upload['dir'] . '/*' ) as $file ) {

                    // If file is 24 hours (86400 seconds) old then delete it.
                    if ( filemtime( $file ) < time() - 86400 ) {
                        wp_delete_file( $file );
                    }
                }
			}
		}

	    /**
	     * Execute model.
	     *
	     * @since 1.6.3
	     * @access public
	     */
	    public function run() {

			// Register cron event actions.
			add_action( 'wwlc_delete_temp_files_daily', array( $this, 'wwlc_delete_temp_files_daily' ) );
	    }
	}
}
