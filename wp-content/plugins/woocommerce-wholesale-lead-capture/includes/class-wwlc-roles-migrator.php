<?php

/**
 * Class to define and manage scheduled actions.
 *
 * @since   2.0.0
 * @version 2.0.0
 * @package WWLC
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WWLC_Roles_Migrator class.
 *
 * @since 2.0.0
 */
class WWLC_Roles_Migrator {

    /**
     * Holds the instance of the class.
     *
     * @var WWLC_Roles_Migrator
     */
    private static $_instance = null;

    /**
     * WWLC plugin file.
     *
     * @var string
     */
    const WWLC_PLUGIN_FILE = 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.php';

    /**
     * WWLC schedule action name.
     *
     * @var string
     */
    const WWLC_SCHEDULE_ACTION_NAME = 'wwlc_migrate_roles';

    /**
     * WWLC version.
     *
     * @var string
     */
    public $wwlc_version;

    /**
     * Batch size
     *
     * @var int
     */
    public $batch_size = 1000;

    /**
     * Construct an instance of the class.
     *
     * @param array $dependencies The list of dependencies.
     */
    public function __construct( $dependencies = array() ) {
        $this->wwlc_version = $dependencies['WWLC_Version'];
        $this->batch_size   = isset( $dependencies['batch_size'] ) ? $dependencies['batch_size'] : 1000;
    }

    /**
     * Get a single instance of this class.
     *
     * @param  array $dependencies The list of dependencies.
     * @return WWLC_Roles_Migrator|null
     */
    public static function instance( $dependencies = array() ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Add action hooks.
     *
     * @return void
     */
    public function run() {
        add_action( 'wwlc_migrate_roles', array( $this, 'migrate_roles' ) );
        add_action( 'upgrader_process_complete', array( $this, 'schedule_on_upgrade_complete' ), 11, 2 );
        add_action( 'upgrader_overwrote_package', array( $this, 'schedule_on_package_overwritten' ), 11 );
    }

    /**
     * Schedule the migrate roles action if the WWLC plugin is overwritten.
     *
     * @param  string $package_file The package file.
     * @return void
     */
    public function schedule_on_package_overwritten( $package_file ) {
        $this->maybe_schedule( $package_file );
    }

    /**
     * Schedule the migrate roles action if the installed version is less than the current version.
     *
     * @param WP_Upgrader $upgrader_object The upgrader object.
     * @param array       $options         The options array.
     *
     * @return void
     */
    public function schedule_on_upgrade_complete( $upgrader_object, $options ) {

        if ( 'update' !== $options['action'] || empty( $options['plugins'] ) ) {
            return;
        }

        foreach ( (array) $options['plugins'] as $plugin ) {
            $this->maybe_schedule( $plugin );
        }
    }

    /**
     * Migrate all users who has the removed roles to the new role.
     *
     * This must be processed in batches of 100 users.
     *
     * @return void
     */
    public function migrate_roles() {

        /**
         * Allow filtering the batch size.
         *
         * @since 2.0.0
         *
         * @param  int $batch_size The batch size.
         * @return int
         */
        $batch_size = apply_filters(
            'wwlc_migrate_roles_batch_size',
            $this->batch_size
        );

        $users = get_users(
            array(
                'role__in' => array( 'wwlc_unmoderated', 'wwlc_inactive' ),
                'limit'    => $batch_size,
            )
        );

        if ( empty( $users ) ) {
            // If no more users, unschedule the action.
            set_transient( 'wwlc_migrate_roles_complete', 'yes' );
            $this->clear_schedule();
            $this->unregister_roles();
            return;
        }

        // Remove un-moderated and inactive roles and capabilities.
        foreach ( $users as $user ) {

            if ( in_array( 'wwlc_unmoderated', $user->roles, true ) ) {
                $user->remove_role( 'wwlc_unmoderated' );
                $user->add_role( WWLC_UNAPPROVED_ROLE );
            }

            // If user has inactive role change role to 'customer'.
            if ( in_array( 'wwlc_inactive', $user->roles, true ) ) {
                $user->remove_role( 'wwlc_inactive' );
                $user->add_role( 'customer' );
            }

            // Remove all wwlc caps if they exist for the user.
            $caps = $user->allcaps;
            foreach ( $caps as $cap ) {
                if ( strpos( $cap, 'wwlc_' ) === 0 ) {
                    $user->remove_cap( $cap );
                }
            }
        }

        // Schedule the next batch.
        $this->maybe_schedule_next_batch( 'wwlc_migrate_roles_complete', self::WWLC_SCHEDULE_ACTION_NAME );
    }

    /**
     * Maybe schedule the migrate roles action.
     *
     * @param  mixed $plugin The plugin file.
     * @return void
     */
    public function maybe_schedule( $plugin = '' ) {
        if ( '' === $plugin ) {
            $plugin = self::WWLC_PLUGIN_FILE;
        }

        $installed_version = get_option( WWLC_OPTION_INSTALLED_VERSION );

        if ( self::WWLC_PLUGIN_FILE === $plugin && version_compare( $installed_version, '2.0.0', '<' ) ) {
            $this->maybe_schedule_next_batch( 'wwlc_migrate_roles_complete', self::WWLC_SCHEDULE_ACTION_NAME );
            WC()->queue()->schedule_single(
                time() + 10,
                self::WWLC_SCHEDULE_ACTION_NAME
            );
        }
    }

    /**
     * Maybe schedule the next batch.
     *
     * @param   string $transient_name The name of the transient to check.
     * @param   string $hook_name      The name of the hook to schedule.
     * @return  void
     * @version 2.0.0
     * @since   2.0.0
     */
    public function maybe_schedule_next_batch( $transient_name, $hook_name = '' ) {
        $complete = get_transient( $transient_name );

        if ( wc_string_to_bool( $complete ) ) {
            return;
        }

        // If already scheduled, unschedule it.
        if ( as_next_scheduled_action( '' !== $hook_name ? $hook_name : self::WWLC_SCHEDULE_ACTION_NAME ) ) {
            return;
        }

        WC()->queue()->schedule_single(
            time() + 10,
            '' !== $hook_name ? $hook_name : self::WWLC_SCHEDULE_ACTION_NAME
        );
    }

    /**
     * Unregister the removed roles.
     *
     * @return void
     */
    public function unregister_roles() {
        remove_role( 'wwlc_unmoderated' );
        remove_role( 'wwlc_inactive' );
        flush_rewrite_rules();
    }

    /**
     * Clear the schedule.
     *
     * @param  string $hook_name The name of the hook to clear.
     * @return void
     */
    public function clear_schedule( $hook_name = '' ) {
        as_unschedule_all_actions( '' !== $hook_name ? $hook_name : self::WWLC_SCHEDULE_ACTION_NAME );
        delete_transient( 'wwlc_migrate_roles_complete' );
    }
}
