<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Only load if WP_CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {

    // Make sure the WP_CLI namespace is available.
    if ( ! class_exists( 'WP_CLI' ) ) {
        return;
    }

    /**
     * WWLC Roles Migrator CLI.
     *
     * @since 2.0.0
     */
    class WWLC_Roles_Migrator_CLI {

        /**
         * Roles migrator instance.
         *
         * @var WWLC_Roles_Migrator
         */
        public $roles_migrator;

        /**
         * WWLC version.
         *
         * @var string
         */
        public $wwlc_version;

        /**
         * Construct instance of the class.
         *
         * @since 2.0.0
         */
        public function __construct() {
            $this->roles_migrator = WWLC_Roles_Migrator::instance(
                array(
                    'batch_size'   => 1000,
                    'WWLC_Version' => WooCommerce_Wholesale_Lead_Capture::VERSION,
                )
            );
        }

        /**
         * Schedule the roles migration.
         *
         * ## OPTIONS
         *
         * [--batch-size=<batch_size>]
         * : Number of users to process in each batch. Default: 1000
         *
         * [--force]
         * : Force rescheduling even if migration was previously completed.
         *
         * ## Examples
         *
         *     wp wwlc migrator schedule
         *     wp wwlc migrator schedule --batch-size=2000
         *     wp wwlc migrator schedule --force
         *     wp wwlc migrator schedule --force --batch-size=2000
         *
         * @param array $args The arguments to be passed to the command.
         * @param array $assoc_args The named arguments to be passed to the command.
         *
         * @return void
         */
        public function schedule( $args, $assoc_args ) {
            $force      = WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
            $batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 1000 );

            $this->roles_migrator->batch_size = $batch_size;

            WP_CLI::line( sprintf( 'Scheduling migrate_roles with batch size: %d', $this->roles_migrator->batch_size ) );

            if ( $force ) {
                delete_transient( 'wwlc_migrate_roles_complete' );
                WP_CLI::line( 'Clearing previous schedule...' );
                $this->roles_migrator->clear_schedule();
            }

            $this->roles_migrator->maybe_schedule();

            WP_CLI::success( 'Migration task scheduled successfully. A new task will be scheduled if necessary.' );
        }

        /**
         * Clear the migration schedule.
         *
         * ## Examples
         *
         *     wp wwlc migrator unschedule
         *
         * @param array $args       Positional arguments (unused, required by WP-CLI).
         * @param array $assoc_args Named arguments (unused, required by WP-CLI).
         *
         * @return void
         */
        public function unschedule( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            $this->roles_migrator->clear_schedule();
        }

        /**
         * Run the migrate roles task directly.
         *
         * ## OPTIONS
         *
         * [--batch-size=<batch_size>]
         * : Number of users to process in each batch. Default: 1000
         *
         * ## Examples
         *
         *     wp wwlc migrator migrate
         *     wp wwlc migrator migrate --batch-size=500
         *
         * @param array $args The arguments to be passed to the command.
         * @param array $assoc_args The named arguments to be passed to the command.
         *
         * @return void
         */
        public function migrate( $args, $assoc_args ) {
            $batch_size = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 1000 );

            $this->roles_migrator->batch_size = $batch_size;

            WP_CLI::line( sprintf( 'Running migrate_roles with batch size: %d', $this->roles_migrator->batch_size ) );

            $this->roles_migrator->migrate_roles();

            $this->roles_migrator->maybe_schedule_next_batch(
                'wwlc_migrate_roles_complete',
                $this->roles_migrator::WWLC_SCHEDULE_ACTION_NAME
            );

            WP_CLI::success( 'Migration task completed successfully.' );
        }
    }

    /**
     * Add the command to WP CLI.
     */
    WP_CLI::add_command(
        'wwlc migrator',
        'WWLC_Roles_Migrator_CLI',
        array(
            'shortdesc' => __( 'Manage the roles migration for WWLC.', 'woocommerce-wholesale-lead-capture' ),
            'longdesc'  => __( 'This command allows you to schedule, run, and manage the roles migration process for WWLC.', 'woocommerce-wholesale-lead-capture' ),
        )
    );
}
