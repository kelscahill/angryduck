<?php
/**
 * Wholesale Role Automation Group / Trigger Action Handlers.
 *
 * HTTP boundary class. Receives sanitized payloads from the WWP free plugin's
 * group-action / trigger-action REST handler and routes them into the
 * `Rules_Repository`, `Pause_Handler`, and `Audit_Log` services.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Admin
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Action_Handlers.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Action_Handlers {

    /**
     * Capability required to invoke every handler in this class.
     *
     * Matches WWPP convention for store-owner actions. The WWP free plugin's
     * `permission_admin_check` already requires `manage_options` upstream; we
     * re-check `manage_woocommerce` defensively so a future relaxation of the
     * upstream check does not lower our gate.
     *
     * @since 2.1.0
     * @var   string
     */
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    /**
     * Default page size for the audit-log table reload.
     *
     * @since 2.1.0
     * @var   int
     */
    private const AUDIT_RELOAD_DEFAULT_PER_PAGE = 50;

    /**
     * Rules repository collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Rules_Repository
     */
    private $rules_repository;

    /**
     * Pause handler collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Pause_Handler
     */
    private $pause_handler;

    /**
     * Audit log collaborator.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Audit_Log
     */
    private $audit_log;

    /**
     * WWPP_Wholesale_Role_Automation_Action_Handlers constructor.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repository Rules repository instance.
     * @param WWPP_Wholesale_Role_Automation_Pause_Handler    $pause_handler    Pause handler instance.
     * @param WWPP_Wholesale_Role_Automation_Audit_Log        $audit_log        Audit log instance.
     */
    public function __construct(
        WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repository,
        WWPP_Wholesale_Role_Automation_Pause_Handler $pause_handler,
        WWPP_Wholesale_Role_Automation_Audit_Log $audit_log
    ) {
        $this->rules_repository = $rules_repository;
        $this->pause_handler    = $pause_handler;
        $this->audit_log        = $audit_log;
    }

    /**
     * Handle the rule save group action.
     *
     * Hooked on `wwp_group_settings_wwpp_wholesale_role_automation_rule_save`.
     * Validates the payload, calls `Rules_Repository::create()`, and returns a
     * `{ status, message }` response.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the group-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    public function handle_rule_save( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $data = $this->extract_rule_payload( $options );

        try {
            $this->rules_repository->create( $data );
        } catch ( WWPP_Wholesale_Role_Automation_Validation_Exception $e ) {
            return $this->validation_error_response( $e );
        }

        return array(
            'status'  => 'success',
            'message' => esc_html__( 'Rule saved.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Handle the rule edit group action.
     *
     * Hooked on `wwp_group_settings_wwpp_wholesale_role_automation_rule_edit`.
     * Updates an existing rule matched by its UUID via
     * `Rules_Repository::update()`.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the group-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    public function handle_rule_edit( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $key = isset( $options['key'] ) ? sanitize_text_field( (string) $options['key'] ) : '';

        if ( '' === $key ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Missing rule key.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        $data = $this->extract_rule_payload( $options );

        try {
            $this->rules_repository->update( $key, $data );
        } catch ( WWPP_Wholesale_Role_Automation_Validation_Exception $e ) {
            return $this->validation_error_response( $e );
        }

        return array(
            'status'  => 'success',
            'message' => esc_html__( 'Rule updated.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Handle the rule delete group action.
     *
     * Hooked on `wwp_group_settings_wwpp_wholesale_role_automation_rule_delete`.
     * Removes the rule matched by its UUID via `Rules_Repository::delete()`.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the group-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    public function handle_rule_delete( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $key = isset( $options['key'] ) ? sanitize_text_field( (string) $options['key'] ) : '';

        if ( '' === $key ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Missing rule key.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        if ( ! $this->rules_repository->delete( $key ) ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Rule not found.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        return array(
            'status'  => 'success',
            'message' => esc_html__( 'Rule deleted.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Handle the rule sort group action.
     *
     * Hooked on `wwp_group_settings_wwpp_wholesale_role_automation_rule_sort`.
     * Atomically reorders rules via `Rules_Repository::reorder()`. The payload
     * carries `list` — the full reordered array of `{key, ...}` items — from
     * which the UUIDs are extracted in order.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the group-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    public function handle_rule_sort( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $list = isset( $options['list'] ) && is_array( $options['list'] ) ? $options['list'] : array();

        $ordered_ids = array();
        foreach ( $list as $item ) {
            if ( is_array( $item ) && isset( $item['key'] ) ) {
                $ordered_ids[] = sanitize_text_field( (string) $item['key'] );
            }
        }

        // An empty list reaches `Rules_Repository::reorder()` as a vacuous
        // success when the rules option itself is also empty (count 0 === 0),
        // returning "Rules reordered." for a no-op. Reject up front so the
        // response message describes reality.
        if ( empty( $ordered_ids ) ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'No rules supplied to reorder.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        if ( ! $this->rules_repository->reorder( $ordered_ids ) ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Could not reorder rules. The supplied list must reference every rule exactly once.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        return array(
            'status'  => 'success',
            'message' => esc_html__( 'Rules reordered.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Handle the per-user override group action.
     *
     * Hooked on `wwp_group_settings_wwpp_wholesale_role_automation_override`.
     * Routes `locked = 'yes'` to `Pause_Handler::lock_user()` and any other
     * value to `unlock_user()`. The pre-call `is_user_paused()` guard makes
     * repeated submits idempotent — `Pause_Handler::lock_user()` and
     * `unlock_user()` both write an audit row unconditionally, so without
     * this guard a Vue toggle clicked twice (lock → lock or unlock → unlock)
     * would pollute the audit log with redundant rows.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the group-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    public function handle_override( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $user_id = isset( $options['user_id'] ) ? (int) $options['user_id'] : 0;

        if ( $user_id <= 0 ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Invalid user.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        $locked    = isset( $options['locked'] ) ? sanitize_key( (string) $options['locked'] ) : '';
        $is_locked = $this->pause_handler->is_user_paused( $user_id );

        if ( 'yes' === $locked ) {
            if ( ! $is_locked ) {
                $this->pause_handler->lock_user( $user_id, get_current_user_id() );
            }

            return array(
                'status'  => 'success',
                'message' => esc_html__( 'User locked from automation.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        if ( $is_locked ) {
            $this->pause_handler->unlock_user( $user_id, get_current_user_id() );
        }

        return array(
            'status'  => 'success',
            'message' => esc_html__( 'User unlocked from automation.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Handle the audit-log CSV trigger action.
     *
     * Hooked on `wwp_trigger_wwpp_wholesale_role_automation_audit_csv`.
     * Validates the filter shape, then calls `Audit_Log::stream_csv()` which
     * emits the CSV bytes to `php://output` and `exit()`s — so on the success
     * path this method does not return. The `{ status, message }` return is
     * reached only on validation failure or capability denial.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the trigger-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string }` — only reached on validation failure; the success path exits inside `stream_csv()`.
     */
    public function handle_audit_csv( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $filters = $this->extract_audit_filters( $options );

        $date_error = $this->validate_audit_date_range( $filters );
        if ( null !== $date_error ) {
            return $date_error;
        }

        $this->audit_log->stream_csv( $filters );

        // Only reachable if `stream_csv()` returned without exiting (headers
        // already sent — the streamer bails to avoid corrupting output).
        return array(
            'status'  => 'error',
            'message' => esc_html__( 'Could not stream audit log: response has already started.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Handle the audit-log table reload group action.
     *
     * Hooked on `wwp_group_settings_wwpp_wholesale_role_automation_audit_reload`.
     * Returns the latest filtered page of audit rows for the Vue table to
     * re-render. Read-only — no audit state is mutated.
     *
     * The dispatch slug deviates from the issue brief, which prescribed a
     * `wwp_trigger_*` action. The WWP free plugin's `trigger_action()` REST
     * handler strips the response shape to `{ status, message, [redirect] }`
     * before returning to the client, so `data`/`total`/`page`/`per_page`/
     * `pages` would be silently dropped on the round trip. The
     * `wwp_group_settings_*` dispatch path forwards the entire filter return
     * value verbatim, so the table receives the rows it needs to render.
     * Semantically this is a read served through a write-shaped slug; the
     * tradeoff is documented here so a future maintainer reviewing the
     * trigger slugs does not "fix" it back.
     *
     * @since 2.1.0
     *
     * @param array $options Sanitized payload from the group-action handler.
     *
     * @return array Response shaped as `{ status: string, message: string, data: array, total: int, page: int, per_page: int, pages: int }`.
     */
    public function handle_audit_reload( array $options ): array {
        if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
            return $this->unauthorized_response();
        }

        $filters = $this->extract_audit_filters( $options );

        $date_error = $this->validate_audit_date_range( $filters );
        if ( null !== $date_error ) {
            return $date_error;
        }

        $page     = isset( $options['page'] ) ? max( 1, (int) $options['page'] ) : 1;
        $per_page = isset( $options['per_page'] ) ? max( 1, (int) $options['per_page'] ) : self::AUDIT_RELOAD_DEFAULT_PER_PAGE;

        $result = $this->audit_log->query( $filters, $page, $per_page );

        return array(
            'status'   => 'success',
            'message'  => esc_html__( 'Audit log reloaded.', 'woocommerce-wholesale-prices-premium' ),
            'data'     => $result['data'],
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
            'pages'    => $result['pages'],
        );
    }

    /**
     * Build the canonical unauthorized `{ status, message }` response.
     *
     * Single source so every handler emits an identical string when the
     * capability gate trips, keeping the Vue notification text consistent.
     *
     * @since 2.1.0
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    private function unauthorized_response(): array {
        return array(
            'status'  => 'error',
            'message' => esc_html__( 'You are not authorized to perform this action.', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Wrap a Validation_Exception in the `{ status, message }` envelope.
     *
     * Surfaces the first validation error message — multiple-error rendering
     * is a future polish (`get_errors()` is available on the exception for
     * callers that need the full list).
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Validation_Exception $e The thrown validation exception.
     *
     * @return array Response shaped as `{ status: string, message: string }`.
     */
    private function validation_error_response( WWPP_Wholesale_Role_Automation_Validation_Exception $e ): array {
        return array(
            'status'  => 'error',
            'message' => $e->getMessage(),
        );
    }

    /**
     * Extract and sanitize the rule payload shared by save / edit handlers.
     *
     * `sanitize_key` handles the slug-shaped fields (`scope_type`, `metric`,
     * `window_type`, role keys); `sanitize_text_field` handles the free-text
     * `label`; `wc_format_decimal` normalizes the monetary `threshold`;
     * `window_days` is cast to int. The `enabled` flag accepts the Vue UI's
     * `'yes' | 'no'` shape and falls back to enabled.
     *
     * @since 2.1.0
     *
     * @param array $options Raw payload from the group-action handler.
     *
     * @return array Sanitized rule data ready for the repository.
     */
    private function extract_rule_payload( array $options ): array {
        return array(
            'label'                 => sanitize_text_field( (string) ( $options['label'] ?? '' ) ),
            'scope_type'            => sanitize_key( (string) ( $options['scope_type'] ?? '' ) ),
            'scope_role_key'        => isset( $options['scope_role_key'] ) ? sanitize_key( (string) $options['scope_role_key'] ) : null,
            'metric'                => sanitize_key( (string) ( $options['metric'] ?? '' ) ),
            'window_type'           => sanitize_key( (string) ( $options['window_type'] ?? '' ) ),
            'window_days'           => isset( $options['window_days'] ) && '' !== $options['window_days'] ? (int) $options['window_days'] : null,
            'threshold'             => isset( $options['threshold'] ) ? wc_format_decimal( $options['threshold'] ) : '0',
            'upgrade_to_role_key'   => isset( $options['upgrade_to_role_key'] ) ? sanitize_key( (string) $options['upgrade_to_role_key'] ) : null,
            'downgrade_to_role_key' => isset( $options['downgrade_to_role_key'] ) ? sanitize_key( (string) $options['downgrade_to_role_key'] ) : null,
            'enabled'               => 'yes' === sanitize_key( (string) ( $options['enabled'] ?? 'yes' ) ),
        );
    }

    /**
     * Extract and sanitize the audit-log filter set shared by csv / reload.
     *
     * `user_id` is cast to int; the remaining keys are slug-shaped or `Y-m-d`
     * date strings and are passed through `sanitize_text_field` (date shape
     * validation happens in `validate_audit_date_range()` so this method
     * stays purely sanitization).
     *
     * @since 2.1.0
     *
     * @param array $options Raw payload from the trigger-action handler.
     *
     * @return array Sanitized filter map suitable for `Audit_Log::query()` / `stream_csv()`.
     */
    private function extract_audit_filters( array $options ): array {
        $filters = array();

        if ( isset( $options['user_id'] ) && '' !== $options['user_id'] ) {
            $filters['user_id'] = (int) $options['user_id'];
        }

        if ( isset( $options['rule_id'] ) && '' !== $options['rule_id'] ) {
            $filters['rule_id'] = sanitize_text_field( (string) $options['rule_id'] );
        }

        if ( isset( $options['direction'] ) && '' !== $options['direction'] ) {
            $filters['direction'] = sanitize_key( (string) $options['direction'] );
        }

        if ( isset( $options['trigger_type'] ) && '' !== $options['trigger_type'] ) {
            $filters['trigger_type'] = sanitize_key( (string) $options['trigger_type'] );
        }

        if ( isset( $options['from'] ) && '' !== $options['from'] ) {
            $filters['from'] = sanitize_text_field( (string) $options['from'] );
        }

        if ( isset( $options['to'] ) && '' !== $options['to'] ) {
            $filters['to'] = sanitize_text_field( (string) $options['to'] );
        }

        return $filters;
    }

    /**
     * Validate the `from` / `to` filter dates at the HTTP boundary.
     *
     * Each date must parse exactly as `Y-m-d` (any other shape is rejected).
     * `DateTime::createFromFormat()` is strict only when the round-tripped
     * value equals the input — that comparison is what catches malformed
     * inputs like `2026-13-40` that the parser otherwise normalizes.
     *
     * Returning `null` means the dates are valid (or absent); a non-null
     * return is a ready-to-emit error response.
     *
     * @since 2.1.0
     *
     * @param array $filters Filter map produced by `extract_audit_filters()`.
     *
     * @return array|null Error response shaped as `{ status, message }`, or null when valid.
     */
    private function validate_audit_date_range( array $filters ): ?array {
        if ( isset( $filters['from'] ) && ! $this->is_valid_ymd_date( $filters['from'] ) ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Invalid from date. Expected format Y-m-d.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        if ( isset( $filters['to'] ) && ! $this->is_valid_ymd_date( $filters['to'] ) ) {
            return array(
                'status'  => 'error',
                'message' => esc_html__( 'Invalid to date. Expected format Y-m-d.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        return null;
    }

    /**
     * Determine whether the supplied string parses exactly as `Y-m-d`.
     *
     * The round-trip equality check is required because
     * `DateTime::createFromFormat()` accepts values like `2026-13-40` and
     * silently normalizes them (the brief calls this out explicitly).
     *
     * @since 2.1.0
     *
     * @param string $value The date string to validate.
     *
     * @return bool True when the value parses round-trip-cleanly as `Y-m-d`.
     */
    private function is_valid_ymd_date( string $value ): bool {
        $date = DateTime::createFromFormat( 'Y-m-d', $value );

        return $date instanceof DateTime && $date->format( 'Y-m-d' ) === $value;
    }
}
