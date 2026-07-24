<?php
/**
 * Wholesale Role Automation Audit Log Service.
 *
 * Append-only ledger of role transitions, pauses, and unpauses backed by the
 * `{$wpdb->prefix}wwpp_wholesale_role_automation_changes` table created by the
 * scaffold migration. Provides a paginated query interface for the admin UI
 * and a streaming CSV export.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Services
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Audit_Log.
 *
 * Validates rows on append (`direction` ∈ `{upgrade, downgrade, pause, unpause}`)
 * and inserts via `$wpdb->insert`.
 *
 * Timezone contract: `created_at` is stored as UTC (matching WP core's
 * `*_gmt` convention). `append()` writes `current_time( 'mysql', true )`;
 * `query()` accepts UTC bounds; `stream_csv()` emits UTC timestamps and the
 * column header reflects this.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Audit_Log {

    /**
     * Allowed `direction` enum values.
     *
     * @since 2.1.0
     *
     * @var string[]
     */
    private const ALLOWED_DIRECTIONS = array( 'upgrade', 'downgrade', 'pause', 'unpause' );

    /**
     * Allowed `trigger_type` enum values.
     *
     * @since 2.1.0
     *
     * @var string[]
     */
    private const ALLOWED_TRIGGER_TYPES = array( 'order_completed', 'refund', 'cancelled', 'scheduled', 'admin' );

    /**
     * CSV streaming batch size.
     *
     * `stream_csv()` paginates the read in chunks of this size so peak memory
     * usage stays bounded regardless of result-set size. 1000 is a balance
     * between query overhead (smaller chunks → more queries) and memory
     * footprint (larger chunks → bigger materialized buffer per fetch).
     *
     * @since 2.1.0
     *
     * @var int
     */
    private const CSV_STREAM_CHUNK_SIZE = 1000;

    /**
     * Transient key for the cached unfiltered audit-log row count.
     *
     * The unfiltered `SELECT COUNT(*)` is a full-table scan on InnoDB and the
     * audit table grows unbounded with order volume. The unfiltered total
     * powers the admin Audit Log tab's pagination footer — a UI hint, not a
     * transactional figure — so caching it under a short TTL trades a few
     * minutes of staleness for avoiding a hot-path scan on stores with large
     * audit tables.
     *
     * @since 2.1.0
     *
     * @var string
     */
    private const UNFILTERED_TOTAL_TRANSIENT = 'wwpp_audit_log_unfiltered_total';

    /**
     * Cached column list for {@see query()} reads.
     *
     * Single-sourced so a schema change (column rename / addition) only needs
     * to land in one place rather than drifting between `query()` and
     * `stream_csv()`. Order matches the audit table's logical column order;
     * `id` is included because `query()` returns full rows for the admin UI.
     *
     * @since 2.1.0
     *
     * @var string
     */
    private const QUERY_COLUMNS = 'id, user_id, rule_id, from_role, to_role, direction, trigger_type, trigger_ref, created_at';

    /**
     * Cached column list for {@see stream_csv()} reads.
     *
     * Order intentionally differs from {@see QUERY_COLUMNS} so the SELECT
     * tuple matches the CSV header row positionally — `created_at` first
     * (the "When" column), `id` omitted (not exported), the rest aligned
     * with the column-header `__()` strings below.
     *
     * @since 2.1.0
     *
     * @var string
     */
    private const CSV_COLUMNS = 'created_at, user_id, rule_id, direction, from_role, to_role, trigger_type, trigger_ref';

    /**
     * Append a row to the audit log.
     *
     * Validates `direction` and `trigger_type` against their allowed enums,
     * then inserts via `$wpdb->insert` with explicit format strings. Sets
     * `created_at` to UTC.
     *
     * @since 2.1.0
     *
     * @param array $row Row to append, with keys: `user_id` (int, required),
     *                   `rule_id` (string, required), `direction` (string,
     *                   required, one of upgrade|downgrade|pause|unpause),
     *                   `trigger_type` (string, required, one of
     *                   order_completed|refund|cancelled|scheduled|admin),
     *                   `from_role` (string|null), `to_role` (string|null),
     *                   `trigger_ref` (string, optional, capped at 32 chars
     *                   by the schema).
     *
     * @return int The inserted row ID.
     * @throws InvalidArgumentException When `direction` or `trigger_type` is not in the allowed set.
     * @throws RuntimeException When the database insert fails.
     */
    public function append( array $row ): int {
        $direction    = isset( $row['direction'] ) ? (string) $row['direction'] : '';
        $trigger_type = isset( $row['trigger_type'] ) ? (string) $row['trigger_type'] : '';

        if ( ! in_array( $direction, self::ALLOWED_DIRECTIONS, true ) ) {
            throw new InvalidArgumentException(
                'Invalid direction: ' . esc_html( $direction )
            );
        }

        if ( ! in_array( $trigger_type, self::ALLOWED_TRIGGER_TYPES, true ) ) {
            throw new InvalidArgumentException(
                'Invalid trigger_type: ' . esc_html( $trigger_type )
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . WWPP_TABLE_WHOLESALE_ROLE_AUTOMATION_AUDIT;

        $from_role   = isset( $row['from_role'] ) && '' !== $row['from_role'] ? (string) $row['from_role'] : null;
        $to_role     = isset( $row['to_role'] ) && '' !== $row['to_role'] ? (string) $row['to_role'] : null;
        $trigger_ref = isset( $row['trigger_ref'] ) ? (string) $row['trigger_ref'] : '';

        $data = array(
            'user_id'      => (int) ( $row['user_id'] ?? 0 ),
            'rule_id'      => (string) ( $row['rule_id'] ?? '' ),
            'from_role'    => $from_role,
            'to_role'      => $to_role,
            'direction'    => $direction,
            'trigger_type' => $trigger_type,
            'trigger_ref'  => $trigger_ref,
            'created_at'   => current_time( 'mysql', true ),
        );

        $formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

        $insert = $wpdb->insert( $table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit table; no caching layer applies.

        if ( false === $insert ) {
            throw new RuntimeException(
                'Audit_Log::append failed: ' . esc_html( (string) $wpdb->last_error )
            );
        }

        // Cached unfiltered total is stale once a new row lands; let the next
        // unfiltered query() recompute it. Filtered counts are not cached.
        delete_transient( self::UNFILTERED_TOTAL_TRANSIENT );

        return (int) $wpdb->insert_id;
    }

    /**
     * Query the audit log with filters and pagination.
     *
     * Supported filter keys: `user_id` (int), `rule_id` (string),
     * `direction` (string), `trigger_type` (string), `from` (UTC `Y-m-d`),
     * `to` (UTC `Y-m-d`). Date filters are inclusive: `from` becomes
     * `>= '<from> 00:00:00'` and `to` becomes `<= '<to> 23:59:59'`. Results
     * are sorted newest first (`created_at DESC, id DESC`).
     *
     * @since 2.1.0
     *
     * @param array $filters  Filter map.
     * @param int   $page     One-based page number. Coerced to >= 1.
     * @param int   $per_page Page size. Coerced to >= 1.
     *
     * @return array{data: array, total: int, page: int, per_page: int, pages: int}
     */
    public function query( array $filters, int $page = 1, int $per_page = 50 ): array {
        global $wpdb;

        $page     = max( 1, $page );
        $per_page = max( 1, $per_page );
        $offset   = ( $page - 1 ) * $per_page;

        $table  = $wpdb->prefix . WWPP_TABLE_WHOLESALE_ROLE_AUTOMATION_AUDIT;
        $clause = $this->build_where_clause( $filters );

        // Filtered counts hit a smaller index-friendly slice and remain
        // uncached. The unfiltered count is the expensive case (full-table
        // scan on InnoDB) and is served from a short-TTL transient that
        // append() invalidates when a new row lands.
        $is_unfiltered = empty( $clause['args'] );
        $total         = false;

        if ( $is_unfiltered ) {
            $cached = get_transient( self::UNFILTERED_TOTAL_TRANSIENT );
            if ( false !== $cached ) {
                $total = (int) $cached;
            }
        }

        if ( false === $total ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $clause['sql'] is composed solely from hard-coded fragments in build_where_clause(); user input is bound via $clause['args'].
            $count_sql = "SELECT COUNT(*) FROM %i WHERE {$clause['sql']}";
            $total     = (int) $wpdb->get_var(
                $wpdb->prepare( $count_sql, array_merge( array( $table ), $clause['args'] ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $count_sql is built above from hard-coded fragments only; user input is bound through $wpdb->prepare().
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit table; no caching layer applies.

            if ( $is_unfiltered ) {
                set_transient( self::UNFILTERED_TOTAL_TRANSIENT, $total, 5 * MINUTE_IN_SECONDS );
            }
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $clause['sql'] is composed solely from hard-coded fragments in build_where_clause(); user input is bound via $rows_args.
        $rows_sql  = 'SELECT ' . self::QUERY_COLUMNS . " FROM %i WHERE {$clause['sql']} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
        $rows_args = array_merge( array( $table ), $clause['args'], array( $per_page, $offset ) );
        $rows      = $wpdb->get_results(
            $wpdb->prepare( $rows_sql, $rows_args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $rows_sql is built above from hard-coded fragments only; user input is bound through $wpdb->prepare().
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit table; no caching layer applies.

        // $per_page is coerced to >= 1 above, so the divisor is always positive.
        $pages = (int) ceil( $total / $per_page );

        return array(
            'data'     => is_array( $rows ) ? $rows : array(),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => $pages,
        );
    }

    /**
     * Stream the filtered audit log as CSV and exit.
     *
     * Sends `Content-Type: text/csv; charset=utf-8` and `Content-Disposition`
     * headers, writes the header row plus every matching audit row to
     * `php://output` row-by-row (avoiding a full in-memory buffer), and
     * `exit()`s.
     *
     * Returns early without output if `headers_sent()` is already true
     * (caller cannot recover from that). Active output buffers are drained
     * before headers are sent so prior buffered content does not corrupt the
     * CSV stream — same pattern WooCommerce uses in `WC_CSV_Exporter`.
     *
     * Filters supported are identical to {@see query()}; pagination is not
     * applied — the entire matching set is streamed.
     *
     * @since 2.1.0
     *
     * @param array $filters Filter map matching the same keys as `query()`.
     *
     * @return void Exits the request after streaming. Returns early without
     *               output if headers were already sent.
     */
    public function stream_csv( array $filters ): void {
        global $wpdb;

        // If something earlier in the request already flushed output, the
        // CSV download cannot recover — bail rather than emit a corrupted
        // mix of HTML + CSV with `Cannot modify header information` warnings.
        if ( headers_sent() ) {
            return;
        }

        // Drain any active output buffers (debug toolbar, plugin echoes,
        // WP's own admin pre-render) so they do not leak into the CSV body.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        $table  = $wpdb->prefix . WWPP_TABLE_WHOLESALE_ROLE_AUTOMATION_AUDIT;
        $clause = $this->build_where_clause( $filters );

        // sanitize_file_name() strips characters that would break the
        // Content-Disposition header (CR/LF, quotes) and any path separators —
        // defense in depth even though the upstream trigger handler is
        // expected to validate `Y-m-d` shape.
        $from_label = isset( $filters['from'] ) && '' !== $filters['from'] ? sanitize_file_name( (string) $filters['from'] ) : 'all-time';
        $to_label   = isset( $filters['to'] ) && '' !== $filters['to'] ? sanitize_file_name( (string) $filters['to'] ) : 'all-time';
        $filename   = sprintf( 'wwpp-wholesale-role-automation-audit-%s-%s.csv', $from_label, $to_label );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming response to php://output; WP_Filesystem is for filesystem operations and does not apply.

        // fopen('php://output') is essentially infallible on standard PHP
        // builds, but rare disk-full / open_basedir / extension-disabled
        // configurations can return false. A subsequent fputcsv() against a
        // non-resource throws TypeError on PHP 8+ — exit cleanly instead.
        if ( false === $output ) {
            exit;
        }

        fputcsv(
            $output,
            array(
                /* translators: Audit log CSV column header — UTC timestamp of the role-change event. */
                __( 'When (UTC)', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — WordPress user ID the change applied to. */
                __( 'User ID', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — identifier of the automation rule that fired. */
                __( 'Rule ID', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — direction of the change (upgrade, downgrade, pause, unpause). */
                __( 'Direction', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — wholesale role the user moved away from. */
                __( 'From Role', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — wholesale role the user moved to. */
                __( 'To Role', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — kind of trigger that caused the change (order_completed, refund, cancelled, scheduled, admin). */
                __( 'Trigger Type', 'woocommerce-wholesale-prices-premium' ),
                /* translators: Audit log CSV column header — opaque reference for the trigger (e.g. order ID). */
                __( 'Trigger Ref', 'woocommerce-wholesale-prices-premium' ),
            ),
            ',',
            '"',
            ''
        );

        // Stream in chunks so memory stays bounded regardless of result size.
        // $wpdb->get_results() materializes the entire set; chunked LIMIT/OFFSET
        // keeps peak memory at chunk-size rather than full-set.
        $chunk_size = self::CSV_STREAM_CHUNK_SIZE;
        $offset     = 0;
        $fetched    = 0;

        do {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $clause['sql'] is composed solely from hard-coded fragments in build_where_clause(); user input is bound via $rows_args.
            $rows_sql  = 'SELECT ' . self::CSV_COLUMNS . " FROM %i WHERE {$clause['sql']} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
            $rows_args = array_merge( array( $table ), $clause['args'], array( $chunk_size, $offset ) );
            $rows      = $wpdb->get_results(
                $wpdb->prepare( $rows_sql, $rows_args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $rows_sql is built above from hard-coded fragments only; user input is bound through $wpdb->prepare().
                ARRAY_A
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit table; no caching layer applies.

            if ( ! is_array( $rows ) || empty( $rows ) ) {
                break;
            }

            foreach ( $rows as $row ) {
                // Neutralize formula-triggering characters before the cell
                // values reach fputcsv() — see neutralize_csv_field().
                fputcsv(
                    $output,
                    array_map(
                        array( $this, 'neutralize_csv_field' ),
                        array(
                            (string) ( $row['created_at'] ?? '' ),
                            (string) ( $row['user_id'] ?? '' ),
                            (string) ( $row['rule_id'] ?? '' ),
                            (string) ( $row['direction'] ?? '' ),
                            (string) ( $row['from_role'] ?? '' ),
                            (string) ( $row['to_role'] ?? '' ),
                            (string) ( $row['trigger_type'] ?? '' ),
                            (string) ( $row['trigger_ref'] ?? '' ),
                        )
                    ),
                    ',',
                    '"',
                    ''
                );
            }

            $fetched = count( $rows );
            $offset += $fetched;

            // Free chunk before fetching the next batch.
            unset( $rows );
        } while ( $fetched === $chunk_size );

        fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming response to php://output; WP_Filesystem is for filesystem operations and does not apply.

        exit;
    }

    /**
     * Neutralize a CSV cell value against spreadsheet formula injection.
     *
     * Spreadsheet applications (Excel, Google Sheets, LibreOffice Calc) treat
     * a cell whose content starts with `=`, `+`, `-`, `@`, a tab, or a
     * carriage return as a formula to evaluate on open — a classic CSV/formula
     * injection vector when exported values can carry attacker-influenced
     * strings (e.g. `rule_id`, `trigger_ref`, role slugs). Prefixing such
     * values with a leading apostrophe forces spreadsheet apps to render them
     * as literal text instead of executing them; values that don't start with
     * one of those characters (including empty and numeric values) pass
     * through unchanged.
     *
     * @since 2.1.1
     *
     * @param string $value Cell value to neutralize.
     *
     * @return string The original value, or the value prefixed with a leading
     *                 apostrophe if it starts with a formula-triggering character.
     */
    private function neutralize_csv_field( string $value ): string {
        if ( '' === $value ) {
            return $value;
        }

        if ( in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Build the shared WHERE clause and bound arguments for filtered reads.
     *
     * Returns an array with two keys: `sql` (the WHERE clause body, sans
     * leading `WHERE`) and `args` (the ordered bound arguments to pass to
     * `$wpdb->prepare()`). Date filters are inclusive (`from` → `00:00:00`,
     * `to` → `23:59:59`). All filter values are funneled through prepare's
     * placeholders — no string concatenation of user input.
     *
     * @since 2.1.0
     *
     * @param array $filters Filter map; see {@see query()}.
     *
     * @return array{sql: string, args: array} The composed WHERE fragment and bound arguments.
     */
    private function build_where_clause( array $filters ): array {
        $parts = array();
        $args  = array();

        if ( isset( $filters['user_id'] ) && '' !== $filters['user_id'] ) {
            $parts[] = 'user_id = %d';
            $args[]  = (int) $filters['user_id'];
        }

        if ( isset( $filters['rule_id'] ) && '' !== $filters['rule_id'] ) {
            $parts[] = 'rule_id = %s';
            $args[]  = (string) $filters['rule_id'];
        }

        if ( isset( $filters['direction'] ) && '' !== $filters['direction'] ) {
            $parts[] = 'direction = %s';
            $args[]  = (string) $filters['direction'];
        }

        if ( isset( $filters['trigger_type'] ) && '' !== $filters['trigger_type'] ) {
            $parts[] = 'trigger_type = %s';
            $args[]  = (string) $filters['trigger_type'];
        }

        if ( isset( $filters['from'] ) && '' !== $filters['from'] ) {
            $parts[] = 'created_at >= %s';
            $args[]  = (string) $filters['from'] . ' 00:00:00';
        }

        if ( isset( $filters['to'] ) && '' !== $filters['to'] ) {
            $parts[] = 'created_at <= %s';
            $args[]  = (string) $filters['to'] . ' 23:59:59';
        }

        // Callers interpolate `WHERE {$clause['sql']}` — fall back to a
        // tautology when no filters are set so the SQL stays grammatical
        // without forcing every caller to branch on the no-filter path.
        return array(
            'sql'  => $parts ? implode( ' AND ', $parts ) : '1=1',
            'args' => $args,
        );
    }
}
