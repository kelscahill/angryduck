<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\CLI
 */

namespace RymeraWebCo\WWOF\CLI;

use RymeraWebCo\WWOF\Classes\Migrate_Form_Data;
use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\Factories\Order_Forms_Query;
use Symfony\Component\Yaml\Yaml;
use WP_CLI;
use WP_CLI\ExitException;

defined( 'ABSPATH' ) || exit;

/**
 * Cli class.
 *
 * @since 3.0
 */
class Cli extends \WP_CLI_Command {

    /**
     * Command name.
     *
     * @var string
     */
    const COMMAND = 'wwof';

    /**
     * Migrates WWOF forms settings to the new format.
     *
     * ## OPTIONS
     * [<id>...]
     * : One or more IDs of the forms to migrate. Omitting this will migrate all forms.
     *
     * ## EXAMPLES
     *      wp wwof migrate_forms_settings
     *      wp wwof migrate_forms_settings <id>...
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     */
    public function migrate_forms_settings( $args = array(), $assoc_args = array() ) {

        $form_ids = array();
        if ( ! empty( $args ) ) {
            $form_ids = array_map( 'absint', $args );
        }

        $migrator = new Migrate_Form_Data( $form_ids );
        $result   = $migrator->run();

        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
        }

        $form_count = count( $form_ids );
        if ( $form_count > 0 ) {
            WP_CLI::success(
                sprintf(/* translators: %d = number of order forms migrated */
                    _n(
                        'Migrated %d Order Form to v3.',
                        '%d Order Forms migrated to v3.',
                        $form_count,
                        'woocommerce-wholesale-order-form'
                    ),
                    $form_count
                )
            );
        } else {
            WP_CLI::success( __( 'Order Forms migrated to v3.', 'woocommerce-wholesale-order-form' ) );
        }
    }

    /**
     * Migrates WWOF forms settings to the new format.
     *
     * ## OPTIONS
     * <json_file_path>
     * : The path to the JSON file containing the forms to import.
     *
     * ## EXAMPLES
     *      wp wwof import_v2_forms_from_json_file path/to/orderForms.json
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     * @throws ExitException Exit exception.
     */
    public function import_v2_forms_from_json_file( $args = array(), $assoc_args = array() ) {

        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $json  = file_get_contents( $args[0] );
        $forms = json_decode( $json, true );
        if ( ! empty( $forms ) ) {
            foreach ( $forms as $index => $form ) {
                $post_id = wp_insert_post(
                    array(
                        'post_title'  => $form['title'] ?? "Order Form {$index}",
                        'post_type'   => Order_Form::POST_TYPE,
                        'post_status' => $form['status'] ?? 'publish',
                    ),
                    true
                );
                if ( is_wp_error( $post_id ) ) {
                    WP_CLI::error( $post_id->get_error_message(), false );
                    continue;
                }
                if ( ! empty( $form['form_elements'] ) ) {
                    update_post_meta( $post_id, 'form_elements', $form['form_elements'] );
                }
                if ( ! empty( $form['editor_area'] ) ) {
                    update_post_meta( $post_id, 'editor_area', $form['editor_area'] );
                }
                if ( ! empty( $form['styles'] ) ) {
                    update_post_meta( $post_id, 'styles', $form['styles'] );
                }
                if ( ! empty( $form['settings'] ) ) {
                    update_post_meta( $post_id, 'settings', $form['settings'] );
                }
            }

            WP_CLI::success( __( 'Order Forms imported.', 'woocommerce-wholesale-order-form' ) );

            return;
        }

        WP_CLI::line( __( 'No Order Forms found in the JSON file.', 'woocommerce-wholesale-order-form' ) );
    }

    /**
     * Export order forms in given format. Either JSON or YAML.
     *
     * ## OPTIONS
     * [<format>]
     * : The format to export the order forms in. Valid values are: json, yml (default).
     *
     * ## EXAMPLES
     *      wp wwof export_order_forms
     *      wp wwof export_order_forms json
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     * @throws ExitException Exit exception.
     */
    public function export_order_forms( $args = array(), $assoc_args = array() ) {

        $format = $args[0] ?? 'yml';
        $format = 'yaml' === $format ? 'yml' : $format;

        $order_forms_query = new Order_Forms_Query(
            array(
                'post_status' => array( 'draft', 'publish' ),
                'orderby'     => 'ID',
                'order'       => 'DESC',
            ),
            'edit'
        );

        if ( $order_forms_query->have_posts() ) {
            $order_forms = array();
            foreach ( $order_forms_query->posts as $order_form ) {
                $order_forms[] = array(
                    'id'          => $order_form->ID,
                    'title'       => get_the_title( $order_form->ID ),
                    'form_header' => $order_form->form_header,
                    'form_body'   => $order_form->form_body,
                    'form_footer' => $order_form->form_footer,
                    'settings'    => $order_form->settings,
                );
            }

            $uploads_dir = wp_upload_dir();

            $filename = $uploads_dir['basedir'] . '/orderForms.' . $format;
            if ( 'json' === $format ) {
                //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                file_put_contents( $filename, wp_json_encode( $order_forms ) );
            } elseif ( 'yml' === $format ) {
                if ( class_exists( 'Symfony\Component\Yaml\Yaml' ) ) {
                    //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                    file_put_contents( $filename, Yaml::dump( $order_forms ) );
                } else {
                    WP_CLI::error(
                        __( 'Class Symfony\\Component\\Yaml\\Yaml not found.', 'woocommerce-wholesale-order-form' )
                    );
                }
            } else {
                WP_CLI::error(
                    WP_CLI::colorize(
                        sprintf(/* translators: %s = given format */
                            __( 'Invalid format %%y%s%%n.', 'woocommerce-wholesale-order-form' ),
                            $format
                        )
                    )
                );
            }
            WP_CLI::success(
                sprintf(/* translators: %1$s = format; %2$s = file path in uploads dir */
                    __( 'Order Forms exported to %1$s format in: %2$s', 'woocommerce-wholesale-order-form' ),
                    $format,
                    $filename
                )
            );

            return;
        }
        WP_CLI::error(
            __( 'No Order Forms found.', 'woocommerce-wholesale-order-form' )
        );
    }

    /**
     * Just a test.
     * ## OPTIONS
     * ## EXAMPLES
     *      wp wwof test
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0
     * @throws ExitException Exit exception.
     */
    public function test( $args = array(), $assoc_args = array() ) {

        try {
            $uploads_dir = wp_upload_dir();
            $filename    = $uploads_dir['basedir'] . '/cli-test.php';
            if ( file_exists( $filename ) ) {
                include $filename;
            }
        } catch ( \Exception $exception ) {
            WP_CLI::error( $exception->getMessage() );
        }

        WP_CLI::success( 'Done!' );
    }

    /**
     * Creates database indexes for better query performance.
     *
     * ## OPTIONS
     * [--force]
     * : Force index creation even if indexes already exist.
     *
     * [--dry-run]
     * : Show what indexes would be created without actually creating them.
     *
     * ## EXAMPLES
     *      wp wwof create_database_indexes
     *      wp wwof create_database_indexes --force
     *      wp wwof create_database_indexes --dry-run
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0.8
     * @throws ExitException Exit exception.
     */
    public function create_database_indexes( $args = array(), $assoc_args = array() ) {

        global $wpdb;

        $force   = isset( $assoc_args['force'] );
        $dry_run = isset( $assoc_args['dry-run'] );

        WP_CLI::line( 'Starting database index creation for WWOF performance optimization...' );

        // Check if indexes already exist (unless forced).
        if ( ! $force && ! $dry_run ) {
            $indexes_completed = wp_cache_get( 'wwof_indexes_completed', 'wwof_db_optimization' );
            if ( false !== $indexes_completed ) {
                WP_CLI::warning( 'Indexes have already been created successfully. Use --force to recreate them.' );
                return;
            }
        }

        // Get database optimizer instance.
        $optimizer = new \RymeraWebCo\WWOF\Helpers\Database_Optimizer();

        try {
            if ( $dry_run ) {
                WP_CLI::line( 'DRY RUN - No indexes will be created.' );
                WP_CLI::line( 'The following indexes would be created:' );
                WP_CLI::line( '- idx_wc_product_meta_lookup_product_sku on wc_product_meta_lookup' );
                WP_CLI::line( '- idx_wc_product_meta_lookup_price on wc_product_meta_lookup' );
                WP_CLI::line( '- idx_wc_product_meta_lookup_sku_exact on wc_product_meta_lookup' );
                WP_CLI::line( '- idx_term_relationships_object_term on wp_term_relationships' );
                WP_CLI::line( '- idx_term_relationships_term_taxonomy on wp_term_relationships' );
                WP_CLI::line( '- idx_posts_content_type_status on wp_posts' );
                WP_CLI::line( '- idx_posts_type_status on wp_posts' );
                WP_CLI::line( '- idx_posts_content_shortcode on wp_posts' );
                WP_CLI::success( 'Dry run completed. Use without --dry-run to create indexes.' );
                return;
            }

            // Force flag handling - if forced, reset completion flag first.
            if ( $force ) {
                $optimizer->reset_completion_flag();
                WP_CLI::line( 'Reset completion flag for forced recreation.' );
            }

            // Use the manual method for CLI execution.
            $result = $optimizer->create_indexes_manual();

            if ( is_wp_error( $result ) ) {
                WP_CLI::error( 'Failed to create database indexes: ' . $result->get_error_message() );
                return;
            }

            WP_CLI::success( 'Database indexes created successfully!' );
            WP_CLI::line( 'These indexes will improve query performance for:' );
            WP_CLI::line( '- SKU searches and product lookups' );
            WP_CLI::line( '- Term relationship queries' );
            WP_CLI::line( '- Shortcode pattern searches' );
            WP_CLI::line( '- Post content filtering' );

            // Show next scheduled run info.
            $next_scheduled = wp_next_scheduled( 'wwof_create_database_indexes' );
            if ( $next_scheduled ) {
                WP_CLI::line( 'Next scheduled run: ' . gmdate( 'Y-m-d H:i:s', $next_scheduled ) );
            } else {
                WP_CLI::line( 'No future scheduled runs (indexes completed successfully).' );
            }
        } catch ( \Exception $exception ) {
            WP_CLI::error( 'Failed to create database indexes: ' . $exception->getMessage() );
        }
    }

    /**
     * Check the status of database optimization.
     *
     * ## EXAMPLES
     *      wp wwof check_database_status
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0.8
     * @throws ExitException Exit exception.
     */
    public function check_database_status( $args = array(), $assoc_args = array() ) {

        // Get database optimizer instance.
        $optimizer = new \RymeraWebCo\WWOF\Helpers\Database_Optimizer();

        WP_CLI::line( 'WWOF Database Optimization Status:' );
        WP_CLI::line( '================================' );

        // Check completion status.
        $indexes_completed = wp_cache_get( 'wwof_indexes_completed', 'wwof_db_optimization' );
        if ( false !== $indexes_completed ) {
            WP_CLI::success( '✓ Database indexes have been created successfully.' );
        } else {
            WP_CLI::warning( '✗ Database indexes have not been created yet.' );
        }

        // Check if currently running.
        $is_running = wp_cache_get( 'wwof_indexes_running', 'wwof_db_optimization' );
        if ( false !== $is_running ) {
            WP_CLI::warning( '⚠ Database index creation is currently running.' );
        }

        // Check scheduled action status.
        $next_scheduled = wp_next_scheduled( 'wwof_create_database_indexes' );
        if ( $next_scheduled ) {
            WP_CLI::line( 'Next scheduled run: ' . gmdate( 'Y-m-d H:i:s', $next_scheduled ) );
            WP_CLI::line( 'Time until next run: ' . human_time_diff( time(), $next_scheduled ) );
        } elseif ( false !== $indexes_completed ) {
                WP_CLI::line( 'No scheduled runs (indexes completed successfully).' );
            } else {
                WP_CLI::warning( 'No scheduled runs found. Indexes may not be scheduled.' );
        }

        // Show performance stats if available.
        $stats = $optimizer->get_performance_stats();
        if ( ! empty( $stats ) ) {
            WP_CLI::line( '' );
            WP_CLI::line( 'Performance Statistics:' );
            WP_CLI::line( '- Total queries: ' . $stats['total_queries'] );
            WP_CLI::line( '- Cache hits: ' . $stats['cache_hits'] );
            WP_CLI::line( '- Cache misses: ' . $stats['cache_misses'] );
        }

        // Show available commands.
        WP_CLI::line( '' );
        WP_CLI::line( 'Available Commands:' );
        WP_CLI::line( '- wp wwof create_database_indexes (create indexes now)' );
        WP_CLI::line( '- wp wwof create_database_indexes --force (force recreation)' );
        WP_CLI::line( '- wp wwof create_database_indexes --dry-run (see what would be created)' );
    }

    /**
     * Reset database optimization completion flag for testing.
     *
     * ## EXAMPLES
     *      wp wwof reset_database_flags
     *
     * @param array $args       Arguments.
     * @param array $assoc_args Associative arguments.
     *
     * @since 3.0.8
     */
    public function reset_database_flags( $args = array(), $assoc_args = array() ) {

        // Get database optimizer instance.
        $optimizer = new \RymeraWebCo\WWOF\Helpers\Database_Optimizer();

        WP_CLI::line( 'Resetting WWOF database optimization flags...' );

        // Reset completion flag.
        $optimizer->reset_completion_flag();

        // Clear any scheduled events.
        wp_clear_scheduled_hook( 'wwof_create_database_indexes' );

        WP_CLI::success( 'Database optimization flags have been reset.' );
        WP_CLI::line( 'The system will now schedule a new index creation task.' );
        WP_CLI::line( 'Use "wp wwof check_database_status" to verify the reset.' );
    }
}
