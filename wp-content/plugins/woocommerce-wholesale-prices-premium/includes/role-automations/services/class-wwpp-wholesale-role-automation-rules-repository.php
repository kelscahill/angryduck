<?php
/**
 * Wholesale Role Automation Rules Repository.
 *
 * Owns CRUD, validation, sort contract, and the 500-rule hard cap for the
 * automation rule blob stored in `wp_options` under
 * `WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES`.
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
 * Class WWPP_Wholesale_Role_Automation_Rules_Repository.
 *
 * Sort order is `priority DESC, created_at ASC, id ASC`.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Rules_Repository {

    /**
     * Role helper collaborator.
     *
     * Used by `applicable_for()` to resolve the user's current wholesale role.
     * Optional in the constructor so the repository can be instantiated from
     * contexts that only need CRUD or sort behavior (e.g. tests, admin
     * handlers); a default helper is created lazily when not supplied.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Role_Helper|null
     */
    private $role_helper;

    /**
     * Allowed values for the `scope_type` field.
     *
     * @since 2.1.0
     * @var   array
     */
    private const ALLOWED_SCOPE_TYPES = array( 'any_non_wholesale', 'wholesale_role' );

    /**
     * Allowed values for the `metric` field.
     *
     * @since 2.1.0
     * @var   array
     */
    private const ALLOWED_METRICS = array( 'total_spent', 'order_count' );

    /**
     * Allowed values for the `window_type` field.
     *
     * @since 2.1.0
     * @var   array
     */
    private const ALLOWED_WINDOW_TYPES = array( 'lifetime', 'rolling_days', 'fixed_month', 'fixed_year' );

    /**
     * Retail role key accepted as a downgrade target (revoke wholesale).
     *
     * @since 2.1.0
     * @var   string
     */
    private const RETAIL_ROLE_KEY = 'customer';

    /**
     * Flag passed to `validate()` to enforce the 500-rule hard cap.
     *
     * Named constant so the `create()` call site reads as
     * `validate( ..., self::ENFORCE_HARD_CAP )` instead of a bare `true`.
     *
     * @since 2.1.0
     * @var   bool
     */
    private const ENFORCE_HARD_CAP = true;

    /**
     * WWPP_Wholesale_Role_Automation_Rules_Repository constructor.
     *
     * Accepts the role helper collaborator used by `applicable_for()`. The
     * argument is optional so the bootstrap (and tests) can construct the
     * repository before the helper exists or without wiring one when the
     * caller only needs CRUD/sort behavior.
     *
     * @since 2.1.0
     *
     * @param WWPP_Wholesale_Role_Automation_Role_Helper|null $role_helper Role helper instance.
     */
    public function __construct( ?WWPP_Wholesale_Role_Automation_Role_Helper $role_helper = null ) {
        $this->role_helper = $role_helper;
    }

    /**
     * Return all rules sorted by the canonical sort contract.
     *
     * Sort order is `priority DESC`, ties broken by `created_at ASC`, then by
     * `id ASC` (UUID lex order) for full determinism.
     *
     * @since 2.1.0
     *
     * @return array List of rule arrays.
     */
    public function all(): array {
        $rules = get_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, array() );

        if ( ! is_array( $rules ) ) {
            $rules = array();
        }

        $rules = array_values( $rules );

        usort(
            $rules,
            static function ( array $a, array $b ): int {
                // Three-key contract from the spec: priority desc → created_at asc → id asc.
                // `??` guards defend against malformed legacy entries.
                $priority_cmp = ( $b['priority'] ?? 0 ) <=> ( $a['priority'] ?? 0 );
                if ( 0 !== $priority_cmp ) {
                    return $priority_cmp;
                }

                $created_cmp = strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
                if ( 0 !== $created_cmp ) {
                    return $created_cmp;
                }

                return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
            }
        );

        return $rules;
    }

    /**
     * Find a single rule by its UUID.
     *
     * @since 2.1.0
     *
     * @param string $id The rule UUID.
     *
     * @return array|null The rule array, or null when not found.
     */
    public function find( string $id ): ?array {
        $rules = $this->read_raw();

        return $rules[ $id ] ?? null;
    }

    /**
     * Create a new rule.
     *
     * Validates payload against the registered wholesale roles list and the
     * 500-rule hard cap. Persists to the option store and returns the created
     * rule with its assigned UUID and `created_at` timestamp.
     *
     * @since 2.1.0
     *
     * @param array $data The rule payload.
     *
     * @return array The persisted rule.
     * @throws WWPP_Wholesale_Role_Automation_Validation_Exception When the payload fails validation.
     */
    public function create( array $data ): array {
        $rules = $this->read_raw();

        $now = gmdate( 'Y-m-d H:i:s' );
        $id  = wp_generate_uuid4();

        $rule = array(
            'id'                    => $id,
            'label'                 => isset( $data['label'] ) ? (string) $data['label'] : '',
            'scope_type'            => isset( $data['scope_type'] ) ? (string) $data['scope_type'] : '',
            'scope_role_key'        => isset( $data['scope_role_key'] ) && '' !== $data['scope_role_key'] ? (string) $data['scope_role_key'] : null,
            'metric'                => isset( $data['metric'] ) ? (string) $data['metric'] : '',
            'window_type'           => isset( $data['window_type'] ) ? (string) $data['window_type'] : '',
            'window_days'           => isset( $data['window_days'] ) && '' !== $data['window_days'] ? (int) $data['window_days'] : null,
            'threshold'             => $data['threshold'] ?? '',
            'upgrade_to_role_key'   => isset( $data['upgrade_to_role_key'] ) && '' !== $data['upgrade_to_role_key'] ? (string) $data['upgrade_to_role_key'] : null,
            'downgrade_to_role_key' => isset( $data['downgrade_to_role_key'] ) && '' !== $data['downgrade_to_role_key'] ? (string) $data['downgrade_to_role_key'] : null,
            'enabled'               => isset( $data['enabled'] ) ? (bool) $data['enabled'] : true,
            'priority'              => $this->next_priority( $rules ),
            'created_at'            => $now,
            'updated_at'            => $now,
        );

        $this->validate( $rule, count( $rules ) + 1, self::ENFORCE_HARD_CAP );

        $rules[ $id ] = $rule;

        update_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, $rules, false );

        return $rule;
    }

    /**
     * Update an existing rule.
     *
     * Validates the merged payload before persisting. Throws with code
     * `rule_not_found` when no rule matches the supplied UUID; otherwise emits
     * the same validation codes as `create()` (except `too_many_rules`, which
     * is enforced on creation only).
     *
     * @since 2.1.0
     *
     * @param string $id   The rule UUID.
     * @param array  $data The rule payload (partial or full).
     *
     * @return array The persisted rule.
     * @throws WWPP_Wholesale_Role_Automation_Validation_Exception When no rule matches `$id` (`rule_not_found`) or the merged payload fails validation.
     */
    public function update( string $id, array $data ): array {
        $rules = $this->read_raw();

        if ( ! isset( $rules[ $id ] ) ) {
            throw new WWPP_Wholesale_Role_Automation_Validation_Exception(
                array(
                    array(
                        'code'    => 'rule_not_found',
                        /* translators: %s: rule UUID. */
                        'message' => sprintf( esc_html__( 'Rule %s not found.', 'woocommerce-wholesale-prices-premium' ), esc_html( $id ) ),
                    ),
                )
            );
        }

        $existing = $rules[ $id ];

        $merged = array_merge( $existing, $data );

        // Preserve immutable fields and refresh the updated timestamp.
        $merged['id']         = $existing['id'];
        $merged['created_at'] = $existing['created_at'];
        $merged['updated_at'] = gmdate( 'Y-m-d H:i:s' );

        // Normalize nullable keys: empty string means cleared.
        $merged['scope_role_key']        = ( isset( $merged['scope_role_key'] ) && '' !== $merged['scope_role_key'] ) ? (string) $merged['scope_role_key'] : null;
        $merged['window_days']           = ( isset( $merged['window_days'] ) && '' !== $merged['window_days'] && null !== $merged['window_days'] ) ? (int) $merged['window_days'] : null;
        $merged['upgrade_to_role_key']   = ( isset( $merged['upgrade_to_role_key'] ) && '' !== $merged['upgrade_to_role_key'] ) ? (string) $merged['upgrade_to_role_key'] : null;
        $merged['downgrade_to_role_key'] = ( isset( $merged['downgrade_to_role_key'] ) && '' !== $merged['downgrade_to_role_key'] ) ? (string) $merged['downgrade_to_role_key'] : null;
        $merged['enabled']               = isset( $merged['enabled'] ) ? (bool) $merged['enabled'] : true;

        $this->validate( $merged, count( $rules ) );

        $rules[ $id ] = $merged;

        update_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, $rules, false );

        return $merged;
    }

    /**
     * Delete a rule by its UUID.
     *
     * @since 2.1.0
     *
     * @param string $id The rule UUID.
     *
     * @return bool True when the rule was deleted, false when no matching rule exists.
     */
    public function delete( string $id ): bool {
        $rules = $this->read_raw();

        if ( ! isset( $rules[ $id ] ) ) {
            return false;
        }

        unset( $rules[ $id ] );

        update_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, $rules, false );

        return true;
    }

    /**
     * Atomically reorder rules by their UUIDs.
     *
     * Reassigns `priority` per the supplied order. Atomic: either every rule
     * is updated or nothing is. The highest array index gets the highest
     * priority so dragging a rule to the top of the visual list yields the
     * highest priority value.
     *
     * @since 2.1.0
     *
     * @param array $ordered_ids List of rule UUIDs in the desired display order.
     *
     * @return bool True on success.
     */
    public function reorder( array $ordered_ids ): bool {
        $rules = $this->read_raw();

        // Atomicity precondition: the supplied list must reference every rule exactly once.
        if ( count( $ordered_ids ) !== count( $rules ) ) {
            return false;
        }

        $unique_ids = array_unique( $ordered_ids );
        if ( count( $unique_ids ) !== count( $ordered_ids ) ) {
            return false;
        }

        foreach ( $ordered_ids as $rule_id ) {
            if ( ! is_string( $rule_id ) || ! isset( $rules[ $rule_id ] ) ) {
                return false;
            }
        }

        // Build the next payload entirely in memory before persisting.
        // Highest array index gets the highest priority — drag-to-top yields the largest value.
        $reordered = array();
        foreach ( $ordered_ids as $index => $rule_id ) {
            $rule                  = $rules[ $rule_id ];
            $rule['priority']      = $index + 1;
            $reordered[ $rule_id ] = $rule;
        }

        update_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, $reordered, false );

        return true;
    }

    /**
     * Toggle the enabled flag for a rule.
     *
     * Used by the Pause Handler for per-rule pause/unpause. Does not touch
     * `updated_at` because pausing is operational state, not a definition
     * change.
     *
     * @since 2.1.0
     *
     * @param string $id      The rule UUID.
     * @param bool   $enabled New enabled state.
     *
     * @return bool True on success.
     */
    public function set_enabled( string $id, bool $enabled ): bool {
        $rules = $this->read_raw();

        if ( ! isset( $rules[ $id ] ) ) {
            return false;
        }

        $rules[ $id ]['enabled'] = $enabled;

        update_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, $rules, false );

        return true;
    }

    /**
     * Return rules applicable to the supplied user.
     *
     * Filters by `scope_type` (`any_non_wholesale` vs `wholesale_role`),
     * current role match, and `enabled` flag. Does NOT filter by metric or
     * threshold — that is the evaluator's responsibility.
     *
     * @since 2.1.0
     *
     * @param WP_User $user The user to filter against.
     *
     * @return array List of applicable rules in canonical sort order.
     */
    public function applicable_for( WP_User $user ): array {
        $current_role = $this->get_role_helper()->current_wholesale_role( (int) $user->ID );
        $has_role     = ! empty( $current_role );

        $applicable = array();

        foreach ( $this->all() as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }

            if ( 'any_non_wholesale' === $rule['scope_type'] && ! $has_role ) {
                $applicable[] = $rule;
                continue;
            }

            if ( 'wholesale_role' === $rule['scope_type']
                && $has_role
                && $rule['scope_role_key'] === $current_role
            ) {
                $applicable[] = $rule;
            }
        }

        return $applicable;
    }

    /**
     * Return all rules with pre-rendered `*_label` fields for table display.
     *
     * Used by the Rules section's `group_conditional` table control so the
     * Vue side does not need to resolve role labels client-side. Each row
     * is the raw rule struct merged with `scope_label`, `condition_label`,
     * `window_label`, `transition_label`, and `enabled_label` strings —
     * all already passed through `__()` for i18n. Not cached: at the 500
     * rule hard cap the formatting work is well under 100ms.
     *
     * @since 2.1.0
     *
     * @return array List of rule arrays augmented with display label fields.
     */
    public function all_for_table(): array {
        $rows = array();

        foreach ( $this->all() as $rule ) {
            $rows[] = array_merge(
                $rule,
                array(
                    'scope_label'      => $this->compose_scope_label( $rule ),
                    'condition_label'  => $this->compose_condition_label( $rule ),
                    'window_label'     => $this->compose_window_label( $rule ),
                    'transition_label' => $this->compose_transition_label( $rule ),
                    'enabled_label'    => ! empty( $rule['enabled'] )
                        ? __( 'On', 'woocommerce-wholesale-prices-premium' )
                        : __( 'Off', 'woocommerce-wholesale-prices-premium' ),
                )
            );
        }

        return $rows;
    }

    /**
     * Compose the human-readable scope label for a rule.
     *
     * Renders `Any non-wholesale` for the catch-all scope and the resolved
     * role label (via `Role_Helper::role_label`) for role-scoped rules.
     *
     * @since 2.1.0
     *
     * @param array $rule The rule struct.
     *
     * @return string The composed scope label.
     */
    private function compose_scope_label( array $rule ): string {
        if ( 'wholesale_role' === ( $rule['scope_type'] ?? '' ) ) {
            $role_key = isset( $rule['scope_role_key'] ) ? (string) $rule['scope_role_key'] : '';

            if ( '' === $role_key ) {
                return '';
            }

            return $this->get_role_helper()->role_label( $role_key );
        }

        return __( 'Any non-wholesale', 'woocommerce-wholesale-prices-premium' );
    }

    /**
     * Compose the human-readable condition label for a rule.
     *
     * Renders strings like `Spend ≥ <formatted-amount>` for `total_spent`
     * and `≥ N order(s)` for `order_count`. Currency formatting uses
     * `wc_price()` for `total_spent`, then strips the `<span>` wrapper and
     * decodes HTML entities (most currency symbols — `&#36;` for USD,
     * `&euro;` for EUR, `&pound;` for GBP, etc. — survive tag-stripping as
     * literal entities and would render verbatim under Vue's textContent
     * mustache binding). `order_count` is pluralized via `_n()` and
     * formatted with `number_format_i18n()`.
     *
     * @since 2.1.0
     *
     * @param array $rule The rule struct.
     *
     * @return string The composed condition label.
     */
    private function compose_condition_label( array $rule ): string {
        $metric    = isset( $rule['metric'] ) ? (string) $rule['metric'] : '';
        $threshold = $rule['threshold'] ?? '';

        if ( 'order_count' === $metric ) {
            $count = (int) $threshold;
            return sprintf(
                /* translators: %s: order count threshold (already number_format_i18n-formatted). */
                _n( '≥ %s order', '≥ %s orders', $count, 'woocommerce-wholesale-prices-premium' ),
                number_format_i18n( $count )
            );
        }

        if ( 'total_spent' === $metric ) {
            return sprintf(
                /* translators: %s: spend threshold formatted as currency. */
                __( 'Spend ≥ %s', 'woocommerce-wholesale-prices-premium' ),
                html_entity_decode(
                    wp_strip_all_tags( wc_price( (float) $threshold ) ),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )
            );
        }

        return '';
    }

    /**
     * Compose the human-readable window label for a rule.
     *
     * Renders `Lifetime`, `Last N days`, `Calendar month`, or `Calendar year`
     * depending on the rule's `window_type` / `window_days`.
     *
     * @since 2.1.0
     *
     * @param array $rule The rule struct.
     *
     * @return string The composed window label.
     */
    private function compose_window_label( array $rule ): string {
        $window_type = isset( $rule['window_type'] ) ? (string) $rule['window_type'] : '';

        switch ( $window_type ) {
            case 'lifetime':
                return __( 'Lifetime', 'woocommerce-wholesale-prices-premium' );

            case 'rolling_days':
                $days = isset( $rule['window_days'] ) ? (int) $rule['window_days'] : 0;
                return sprintf(
                    /* translators: %d: number of days in the rolling window. */
                    __( 'Last %d days', 'woocommerce-wholesale-prices-premium' ),
                    $days
                );

            case 'fixed_month':
                return __( 'Calendar month', 'woocommerce-wholesale-prices-premium' );

            case 'fixed_year':
                return __( 'Calendar year', 'woocommerce-wholesale-prices-premium' );

            default:
                return '';
        }
    }

    /**
     * Compose the human-readable transition label for a rule.
     *
     * Renders the `upgrade_target / downgrade_target` pair. Each target is
     * resolved by `resolve_transition_target()`: em dash for null/empty (so
     * the column always reads as a pair), the localized "Customer" label
     * for the retail role key, or the role's registered label (via
     * `Role_Helper::role_label`) otherwise.
     *
     * @since 2.1.0
     *
     * @param array $rule The rule struct.
     *
     * @return string The composed transition label.
     */
    private function compose_transition_label( array $rule ): string {
        return sprintf(
            /* translators: 1: upgrade target role label. 2: downgrade target role label. */
            __( '%1$s / %2$s', 'woocommerce-wholesale-prices-premium' ),
            $this->resolve_transition_target( $rule['upgrade_to_role_key'] ?? null ),
            $this->resolve_transition_target( $rule['downgrade_to_role_key'] ?? null )
        );
    }

    /**
     * Resolve a single transition target role key to its display label.
     *
     * Returns an em dash (`—`) for null/empty keys so the transition column
     * always reads as a pair, the localized "Customer" label for the retail
     * role key, or the role's registered label via `Role_Helper::role_label`
     * for any other wholesale role key.
     *
     * @since 2.1.0
     *
     * @param string|null $role_key The transition target role key.
     *
     * @return string The display label for the target.
     */
    private function resolve_transition_target( ?string $role_key ): string {
        if ( null === $role_key || '' === $role_key ) {
            return '—';
        }

        if ( self::RETAIL_ROLE_KEY === $role_key ) {
            return __( 'Customer', 'woocommerce-wholesale-prices-premium' );
        }

        return $this->get_role_helper()->role_label( $role_key );
    }

    /**
     * Return the role helper, lazy-instantiating if not injected.
     *
     * The repository accepts an optional `Role_Helper` via its constructor
     * for evaluator use; this accessor lazy-instantiates a default helper
     * when none was injected so `applicable_for()` and the label composers
     * can reach the helper from any call site without duplicating the
     * null-check.
     *
     * @since 2.1.0
     *
     * @return WWPP_Wholesale_Role_Automation_Role_Helper The role helper.
     */
    private function get_role_helper(): WWPP_Wholesale_Role_Automation_Role_Helper {
        if ( null === $this->role_helper ) {
            $this->role_helper = new WWPP_Wholesale_Role_Automation_Role_Helper();
        }

        return $this->role_helper;
    }

    /**
     * Read the raw, unsorted rules array keyed by UUID.
     *
     * @since 2.1.0
     *
     * @return array Map of rule UUID => rule array.
     */
    private function read_raw(): array {
        $rules = get_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_RULES, array() );

        return is_array( $rules ) ? $rules : array();
    }

    /**
     * Compute the next `priority` value for a freshly created rule.
     *
     * New rules append to the bottom of the priority stack — current max + 1.
     * For an empty store the seed value is 1.
     *
     * @since 2.1.0
     *
     * @param array $rules The current rules map.
     *
     * @return int The next priority value.
     */
    private function next_priority( array $rules ): int {
        $max = 0;

        foreach ( $rules as $rule ) {
            if ( isset( $rule['priority'] ) && (int) $rule['priority'] > $max ) {
                $max = (int) $rule['priority'];
            }
        }

        return $max + 1;
    }

    /**
     * Validate a rule payload against the six business rules and the hard cap.
     *
     * Each failure adds a distinct error code to the aggregated list so the
     * UI can surface every problem in a single round trip.
     *
     * @since 2.1.0
     *
     * @param array $rule       The fully-shaped rule array.
     * @param int   $rule_count Total rule count after the create/update would persist.
     * @param bool  $check_cap  Whether to enforce the 500-rule hard cap. The cap
     *                          bounds option growth, so it is only checked on
     *                          create — updates and deletes can never exceed
     *                          a count the store already holds. Defaults to
     *                          false.
     *
     * @return void
     * @throws WWPP_Wholesale_Role_Automation_Validation_Exception When any validation rule fails.
     */
    private function validate( array $rule, int $rule_count, bool $check_cap = false ): void {
        $errors = array();

        $registered_roles = WWP_Wholesale_Roles::getInstance()->getAllRegisteredWholesaleRoles();
        $registered_keys  = is_array( $registered_roles ) ? array_keys( $registered_roles ) : array();

        // 1. scope_type.
        if ( ! in_array( $rule['scope_type'], self::ALLOWED_SCOPE_TYPES, true ) ) {
            $errors[] = array(
                'code'    => 'invalid_scope',
                'message' => esc_html__( 'scope_type must be one of: any_non_wholesale, wholesale_role.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        // 2. scope_role_key (only when scope_type === 'wholesale_role').
        if ( 'wholesale_role' === $rule['scope_type'] ) {
            if ( null === $rule['scope_role_key'] || ! in_array( $rule['scope_role_key'], $registered_keys, true ) ) {
                $errors[] = array(
                    'code'    => 'invalid_role_key',
                    /* translators: %s: rejected scope_role_key value. */
                    'message' => sprintf( esc_html__( 'scope_role_key %s is not a registered wholesale role.', 'woocommerce-wholesale-prices-premium' ), esc_html( (string) $rule['scope_role_key'] ) ),
                );
            }
        }

        // 3. metric.
        if ( ! in_array( $rule['metric'], self::ALLOWED_METRICS, true ) ) {
            $errors[] = array(
                'code'    => 'invalid_metric',
                'message' => esc_html__( 'metric must be one of: total_spent, order_count.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        // 4. window_type / window_days.
        if ( ! in_array( $rule['window_type'], self::ALLOWED_WINDOW_TYPES, true ) ) {
            $errors[] = array(
                'code'    => 'invalid_window',
                'message' => esc_html__( 'window_type must be one of: lifetime, rolling_days, fixed_month, fixed_year.', 'woocommerce-wholesale-prices-premium' ),
            );
        } elseif ( 'rolling_days' === $rule['window_type'] && ( null === $rule['window_days'] || (int) $rule['window_days'] <= 0 ) ) {
            $errors[] = array(
                'code'    => 'invalid_window',
                'message' => esc_html__( 'window_days must be a positive integer when window_type is rolling_days.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        // 5. threshold.
        if ( ! is_numeric( $rule['threshold'] ) || (float) $rule['threshold'] < 0 ) {
            $errors[] = array(
                'code'    => 'invalid_threshold',
                'message' => esc_html__( 'threshold must be a numeric value greater than or equal to zero.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        // 6. target presence.
        $valid_target_keys = array_merge( $registered_keys, array( self::RETAIL_ROLE_KEY ) );

        $upgrade_target   = $rule['upgrade_to_role_key'];
        $downgrade_target = $rule['downgrade_to_role_key'];

        $upgrade_valid   = null !== $upgrade_target && in_array( $upgrade_target, $valid_target_keys, true );
        $downgrade_valid = null !== $downgrade_target && in_array( $downgrade_target, $valid_target_keys, true );

        $upgrade_invalid_value   = null !== $upgrade_target && ! $upgrade_valid;
        $downgrade_invalid_value = null !== $downgrade_target && ! $downgrade_valid;

        if ( ( ! $upgrade_valid && ! $downgrade_valid ) || $upgrade_invalid_value || $downgrade_invalid_value ) {
            $errors[] = array(
                'code'    => 'missing_target',
                'message' => esc_html__( 'At least one of upgrade_to_role_key or downgrade_to_role_key must resolve to a registered wholesale role or the retail role customer; any non-null target must resolve to one.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        // 7. hard cap (create only — bounds option growth, not edits).
        if ( $check_cap && $rule_count > WWPP_WHOLESALE_ROLE_AUTOMATION_RULES_HARD_CAP ) {
            $errors[] = array(
                'code'    => 'too_many_rules',
                /* translators: %d: hard-cap rule count. */
                'message' => sprintf( esc_html__( 'Cannot store more than %d rules.', 'woocommerce-wholesale-prices-premium' ), (int) WWPP_WHOLESALE_ROLE_AUTOMATION_RULES_HARD_CAP ),
            );
        }

        if ( ! empty( $errors ) ) {
            // Every entry in $errors carries either a static esc_html__() string or a sprintf()
            // whose dynamic substitutions were each individually escaped via esc_html() above —
            // the array as a whole is therefore output-safe by construction.
            throw new WWPP_Wholesale_Role_Automation_Validation_Exception( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
