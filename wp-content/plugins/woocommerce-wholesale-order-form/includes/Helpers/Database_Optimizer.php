<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use WP_Error;

/**
 * Database_Optimizer class.
 *
 * Handles database optimization tasks including index creation and query optimization.
 *
 * @since 3.0
 */
class Database_Optimizer extends Abstract_Class {

    /**
     * Cache group for database optimization.
     */
    const CACHE_GROUP = 'wwof_db_optimization';

    /**
     * Plugin file.
     *
     * @var string
     */
    private $plugin_file = 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.php';

    /**
     * Initialize database optimization.
     *
     * @since 3.0
     * @return void
     */
    public function init() {
        // Optimize queries.
        add_action( 'wp_loaded', array( $this, 'maybe_optimize_queries' ) );
        add_action( 'wwof_create_database_indexes', array( $this, 'create_indexes_background' ) );
    }

    /**
     * Schedule the index creation action if the WWOF plugin is overwritten.
     *
     * @param  string $package_file The package file.
     * @return void
     */
    public function schedule_on_package_overwritten( $package_file ) {
        if ( $this->plugin_file !== $package_file ) {
            return;
        }

        $this->schedule_index_creation();
    }

    /**
     * Schedule the index creation action if the installed version is less than the current version.
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
            if ( $this->plugin_file !== $plugin ) {
                continue;
            }

            $this->schedule_index_creation();
        }
    }

    /**
     * Schedule database index creation.
     *
     * @since 3.0.8
     * @return void
     */
    public function schedule_index_creation() {
        // Check if indexes have already been created successfully.
        $indexes_completed = wp_cache_get( 'wwof_indexes_completed', self::CACHE_GROUP );

        if ( false !== $indexes_completed ) {
            return;
        }

        // Check if there's already a scheduled event.
        if ( ! wp_next_scheduled( 'wwof_create_database_indexes' ) ) {
            $next_minute = strtotime( '+1 minute' );
            wp_schedule_single_event( $next_minute, 'wwof_create_database_indexes' );

            Logger::info( 'WWOF database index creation scheduled to run in the background.' );
        }
    }

    /**
     * Manually trigger database index creation.
     *
     * @since 3.0.8
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function create_indexes_manual() {
        global $wpdb;

        try {
            // Create indexes for better query performance.
            $this->create_product_meta_indexes( $wpdb );
            $this->create_term_relationship_indexes( $wpdb );
            $this->create_post_content_indexes( $wpdb );

            // Mark indexes as completed successfully (permanent flag).
            wp_cache_set( 'wwof_indexes_completed', true, self::CACHE_GROUP, 0 ); // No expiration.

            Logger::info(
                'WWOF database indexes created successfully via manual trigger.',
                array( 'source' => 'wwof-db-optimization' )
            );

            return true;
        } catch ( \Exception $exception ) {
            $error = new WP_Error( 'index_creation_failed', $exception->getMessage() );

            Logger::error(
                'WWOF database index creation failed: ' . $exception->getMessage(),
                array( 'source' => 'wwof-db-optimization' )
            );

            return $error;
        }
    }

    /**
     * Create database indexes in the background via scheduled action.
     *
     * @since 3.0.8
     * @return void
     */
    public function create_indexes_background() {
        global $wpdb;

        Logger::info( 'Creating database indexes in the background.' );

        // Check if indexes have already been created successfully.
        $indexes_completed = wp_cache_get( 'wwof_indexes_completed', self::CACHE_GROUP );
        if ( false !== $indexes_completed ) {
            return;
        }

        // Check if we're currently running to prevent duplicate execution.
        $is_running = wp_cache_get( 'wwof_indexes_running', self::CACHE_GROUP );
        if ( false !== $is_running ) {
            return;
        }

        // Set running flag to prevent duplicate execution.
        wp_cache_set( 'wwof_indexes_running', true, self::CACHE_GROUP, 300 ); // 5 minutes timeout.

        try {
            // Create indexes for better query performance.
            $this->create_product_meta_indexes( $wpdb );
            $this->create_term_relationship_indexes( $wpdb );
            $this->create_post_content_indexes( $wpdb );

            // Mark indexes as completed successfully (permanent flag).
            wp_cache_set( 'wwof_indexes_completed', true, self::CACHE_GROUP, 0 ); // No expiration.

            // Clear the running flag.
            wp_cache_delete( 'wwof_indexes_running', self::CACHE_GROUP );

            Logger::info(
                __( 'WWOF database indexes created successfully via scheduled action.', 'woocommerce-wholesale-order-form' ),
                array( 'source' => 'wwof-db-optimization' )
            );

        } catch ( \Exception $exception ) {
            // Clear the running flag.
            wp_cache_delete( 'wwof_indexes_running', self::CACHE_GROUP );

            Logger::error(
                sprintf(
                    /* translators: %s: error message */
                    __( 'WWOF database index creation failed: %s', 'woocommerce-wholesale-order-form' ),
                    $exception->getMessage()
                ),
                array( 'source' => 'wwof-db-optimization' )
            );

            // Reschedule for next midnight if this was a failure.
            $this->reschedule_on_failure();
        }
    }

    /**
     * Reschedule index creation on failure.
     *
     * @since 3.0.8
     * @return void
     */
    private function reschedule_on_failure() {
        // Clear any existing scheduled event.
        wp_clear_scheduled_hook( 'wwof_create_database_indexes' );

        // Schedule for next midnight.
        $next_midnight = strtotime( '+1 hour' );
        wp_schedule_single_event( $next_midnight, 'wwof_create_database_indexes' );

        Logger::warning(
            __( 'WWOF database index creation rescheduled for next hour due to failure.', 'woocommerce-wholesale-order-form' ),
            array( 'source' => 'wwof-db-optimization' )
        );
    }

    /**
     * Check if an index exists on a table.
     *
     * @param \wpdb  $wpdb        WordPress database object.
     * @param string $table_name  Table name.
     * @param string $index_name  Index name.
     * @since 3.0.8
     * @return bool
     */
    private function index_exists( $wpdb, $table_name, $index_name ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table_name, $index_name ) );
        return null !== $result;
    }

    /**
     * Create indexes for product meta lookups.
     *
     * @param \wpdb $wpdb WordPress database object.
     * @since 3.0
     * @return void
     */
    private function create_product_meta_indexes( $wpdb ) {
        $table_name = $wpdb->prefix . 'wc_product_meta_lookup';

        Logger::info( 'Creating product meta indexes for table: ' . $table_name );

        // Check if table exists.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        // Create composite index for product_id and sku for better SKU searches.
        if ( ! $this->index_exists( $wpdb, $table_name, 'idx_wc_product_meta_lookup_product_sku' ) ) {
            Logger::info( 'Creating product meta index: idx_wc_product_meta_lookup_product_sku' );
            $wpdb->query( $wpdb->prepare( 'CREATE INDEX idx_wc_product_meta_lookup_product_sku ON %i (product_id, sku)', $table_name ) );
        }

        // Create index for price ordering.
        if ( ! $this->index_exists( $wpdb, $table_name, 'idx_wc_product_meta_lookup_price' ) ) {
            Logger::info( 'Creating product meta index: idx_wc_product_meta_lookup_price' );
            $wpdb->query( $wpdb->prepare( 'CREATE INDEX idx_wc_product_meta_lookup_price ON %i (min_price, max_price)', $table_name ) );
        }

        Logger::info( 'Product meta indexes created successfully.' );
    }

    /**
     * Create indexes for term relationships.
     *
     * @param \wpdb $wpdb WordPress database object.
     * @since 3.0
     * @return void
     */
    private function create_term_relationship_indexes( $wpdb ) {
        // Create composite index for term relationships.
        if ( ! $this->index_exists( $wpdb, $wpdb->term_relationships, 'idx_term_relationships_object_term' ) ) {
            Logger::info( 'Creating term relationship index: idx_term_relationships_object_term' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "CREATE INDEX idx_term_relationships_object_term ON {$wpdb->term_relationships} (object_id, term_taxonomy_id)" );
        }

        // Create index for term taxonomy lookups.
        if ( ! $this->index_exists( $wpdb, $wpdb->term_relationships, 'idx_term_relationships_term_taxonomy' ) ) {
            Logger::info( 'Creating term relationship index: idx_term_relationships_term_taxonomy' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "CREATE INDEX idx_term_relationships_term_taxonomy ON {$wpdb->term_relationships} (term_taxonomy_id)" );
        }

        Logger::info( 'Term relationship indexes created successfully.' );
    }

    /**
     * Create indexes for post content searches.
     *
     * @param \wpdb $wpdb WordPress database object.
     * @since 3.0
     * @return void
     */
    private function create_post_content_indexes( $wpdb ) {
        // Create index for post content searches (for shortcode detection).
        if ( ! $this->index_exists( $wpdb, $wpdb->posts, 'idx_posts_content_type_status' ) ) {
            Logger::info( 'Creating post content index: idx_posts_content_type_status' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "CREATE INDEX idx_posts_content_type_status ON {$wpdb->posts} (post_content(100), post_type, post_status)" );
        }

        // Create index for post type and status combinations.
        if ( ! $this->index_exists( $wpdb, $wpdb->posts, 'idx_posts_type_status' ) ) {
            Logger::info( 'Creating post content index: idx_posts_type_status' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "CREATE INDEX idx_posts_type_status ON {$wpdb->posts} (post_type, post_status)" );
        }

        // Create SARGABLE-friendly indexes for better query performance.
        // Index for shortcode pattern searches (prefix-based).
        if ( ! $this->index_exists( $wpdb, $wpdb->posts, 'idx_posts_content_shortcode' ) ) {
            Logger::info( 'Creating post content index: idx_posts_content_shortcode' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "CREATE INDEX idx_posts_content_shortcode ON {$wpdb->posts} (post_content(50), post_type, post_status)" );
        }

        // Index for exact SKU matches in product meta lookup.
        $table_name = $wpdb->prefix . 'wc_product_meta_lookup';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
            if ( ! $this->index_exists( $wpdb, $table_name, 'idx_wc_product_meta_lookup_sku_exact' ) ) {
                Logger::info( 'Creating product meta index: idx_wc_product_meta_lookup_sku_exact' );
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query( $wpdb->prepare( 'CREATE INDEX idx_wc_product_meta_lookup_sku_exact ON %i (sku)', $table_name ) );
            }
        }

        Logger::info( 'Post content indexes created successfully.' );
    }

    /**
     * Optimize queries by setting query hints.
     *
     * @since 3.0
     * @return void
     */
    public function maybe_optimize_queries() {
        // Add query optimization filters.
        add_filter( 'posts_clauses_request', array( $this, 'optimize_query_clauses' ), 5 );
        add_filter( 'wp_query_optimization', array( $this, 'optimize_wp_query' ), 10, 2 );
    }

    /**
     * Optimize query clauses for better performance.
     *
     * @param array $clauses Query clauses.
     * @since 3.0
     * @return array
     */
    public function optimize_query_clauses( $clauses ) {
        global $wpdb;

        // Add query hints for better performance.
        if ( ! empty( $clauses['join'] ) && strpos( $clauses['join'], 'wc_product_meta_lookup' ) !== false ) {
            // Force index usage for product meta lookups.
            $clauses['join'] = str_replace(
                'wc_product_meta_lookup',
                'wc_product_meta_lookup FORCE INDEX (idx_wc_product_meta_lookup_product_sku)',
                $clauses['join']
            );
        }

        // Optimize term relationship joins.
        if ( ! empty( $clauses['join'] ) && strpos( $clauses['join'], 'term_relationships' ) !== false ) {
            $clauses['join'] = str_replace(
                'term_relationships',
                'term_relationships FORCE INDEX (idx_term_relationships_object_term)',
                $clauses['join']
            );
        }

        // Add SARGABLE query optimizations.
        $clauses = $this->optimize_sargable_queries( $clauses );

        return $clauses;
    }

    /**
     * Optimize queries to be more SARGABLE.
     *
     * @param array $clauses Query clauses.
     * @since 3.0.8
     * @return array
     */
    private function optimize_sargable_queries( $clauses ) {
        global $wpdb;

        // Optimize LIKE patterns to be more SARGABLE.
        if ( ! empty( $clauses['where'] ) ) {
            // Replace leading wildcard patterns with more efficient alternatives where possible.
            $clauses['where'] = preg_replace(
                '/post_content\s+LIKE\s+\'%([^%]+)%\'/',
                'post_content LIKE \'$1%\' OR post_content LIKE \'%$1\'',
                $clauses['where']
            );
        }

        // Optimize ORDER BY clauses for better index usage.
        if ( ! empty( $clauses['orderby'] ) ) {
            // Ensure ORDER BY uses indexed columns directly.
            $clauses['orderby'] = preg_replace(
                '/CASE\s+([^E]+)END\s+(ASC|DESC)/',
                '$1 $2',
                $clauses['orderby']
            );
        }

        return $clauses;
    }

    /**
     * Optimize WP_Query for better performance.
     *
     * @param \WP_Query $query WordPress query object.
     * @param array     $args Query arguments.
     * @since 3.0
     * @return void
     */
    public function optimize_wp_query( $query, $args ) {
        Logger::info( 'Optimizing WP_Query.' );

        // Set query optimization flags.
        $query->set( 'no_found_rows', true );
        $query->set( 'update_post_meta_cache', false );
        $query->set( 'update_post_term_cache', false );

        // Only load necessary fields.
        if ( ! isset( $args['fields'] ) ) {
            $query->set( 'fields', 'ids' );
        }

        Logger::info( 'WP_Query optimized.' );
    }

    /**
     * Clear optimization cache.
     *
     * @since 3.0
     * @return void
     */
    public function clear_optimization_cache() {
        Logger::info( 'Clearing optimization cache.' );
        wp_cache_delete( 'wwof_indexes_created', self::CACHE_GROUP );
        wp_cache_delete( 'wwof_indexes_completed', self::CACHE_GROUP );
        wp_cache_delete( 'wwof_indexes_running', self::CACHE_GROUP );

        Logger::info( 'Optimization cache cleared.' );
    }

    /**
     * Clear scheduled actions and cache.
     *
     * @since 3.0.8
     * @return void
     */
    public function cleanup() {
        Logger::info( 'Cleaning up database optimization.' );

        // Clear scheduled action.
        wp_clear_scheduled_hook( 'wwof_create_database_indexes' );

        // Clear optimization cache.
        $this->clear_optimization_cache();

        // Log cleanup.
        Logger::info( 'WWOF database optimization cleanup completed.', array( 'source' => 'wwof-db-optimization' ) );
    }

    /**
     * Reset completion flag for testing/debugging.
     *
     * @since 3.0.8
     * @return void
     */
    public function reset_completion_flag() {
        wp_cache_delete( 'wwof_indexes_completed', self::CACHE_GROUP );
        wp_cache_delete( 'wwof_indexes_running', self::CACHE_GROUP );

        // Log the reset.
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->info( 'WWOF database index completion flag reset.', array( 'source' => 'wwof-db-optimization' ) );
        }
    }

    /**
     * Get database performance statistics.
     *
     * @since 3.0
     * @return array
     */
    public function get_performance_stats() {
        global $wpdb;

        $cache_hits = wp_cache_get( 'wwof_cache_hits', self::CACHE_GROUP );
        if ( false === $cache_hits ) {
            $cache_hits = 0;
        }

        $cache_misses = wp_cache_get( 'wwof_cache_misses', self::CACHE_GROUP );
        if ( false === $cache_misses ) {
            $cache_misses = 0;
        }

        $stats = array(
            'total_queries' => $wpdb->num_queries,
            'cache_hits'    => $cache_hits,
            'cache_misses'  => $cache_misses,
        );

        Logger::info( 'Database performance statistics: ' . wp_json_encode( $stats ) );

        return $stats;
    }

    /**
     * Run database optimization tasks.
     *
     * @since 3.0
     * @return void
     */
    public function run_optimization() {
        global $wpdb;

        Logger::info( 'Running database optimization.' );

        // Analyze tables for better query planning.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "ANALYZE TABLE {$wpdb->posts}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "ANALYZE TABLE {$wpdb->term_relationships}" );

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_product_meta_lookup' ) ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "ANALYZE TABLE {$wpdb->prefix}wc_product_meta_lookup" );
        }

        // Clear query cache.
        $this->clear_optimization_cache();

        Logger::info( 'Database optimization completed.' );
    }

    /**
     * Run the database optimizer.
     *
     * @since 3.0
     * @return void
     */
    public function run() {
        $this->init();
    }
}
