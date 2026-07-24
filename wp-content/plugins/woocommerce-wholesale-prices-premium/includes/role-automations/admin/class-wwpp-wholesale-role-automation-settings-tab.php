<?php
/**
 * Wholesale Role Automation Settings Tab.
 *
 * Registers the settings tab entry and the per-section controls payload that
 * the WWP free plugin renders via its existing Vue settings app. Owns the
 * group-action and trigger-action allow-list filters.
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
 * Class WWPP_Wholesale_Role_Automation_Settings_Tab.
 *
 * @since 2.1.0
 * @since 2.1.0 Added audit_log + overrides section methods plus the private row-builder helpers.
 */
class WWPP_Wholesale_Role_Automation_Settings_Tab {

    /**
     * Default audit-log page size mirrored from the spec.
     *
     * Single-sourced so `audit_rows_for_table()` and any future caller agree
     * on the row count returned by `Audit_Log::query()`.
     *
     * @since 2.1.0
     *
     * @var int
     */
    private const AUDIT_TABLE_PER_PAGE = 50;

    /**
     * Placeholder rendered when a role-keyed audit column has no value.
     *
     * Pause and unpause rows carry `null` from_role/to_role; the audit table
     * shows an em-dash to make the empty state visually unambiguous.
     *
     * @since 2.1.0
     *
     * @var string
     */
    private const ROLE_PLACEHOLDER = '—';

    /**
     * Memoised audit-log service instance.
     *
     * Lazily constructed on first call to `audit_log()` so the same instance
     * is reused across `audit_rows_for_table()` and `locked_users_for_table()`
     * within a single request.
     *
     * @since 2.1.0
     *
     * @var WWPP_Wholesale_Role_Automation_Audit_Log|null
     */
    private ?WWPP_Wholesale_Role_Automation_Audit_Log $audit_log = null;

    /**
     * Lazily resolve the shared audit-log service instance.
     *
     * @since 2.1.0
     *
     * @return WWPP_Wholesale_Role_Automation_Audit_Log Shared audit-log service.
     */
    private function audit_log(): WWPP_Wholesale_Role_Automation_Audit_Log {
        if ( null === $this->audit_log ) {
            $this->audit_log = new WWPP_Wholesale_Role_Automation_Audit_Log();
        }

        return $this->audit_log;
    }

    /**
     * Append the Wholesale Role Automation tab to the WWP settings tab tree.
     *
     * Hooked on `wwp_admin_setting_default_tabs`. Adds a child tab under
     * `wholesale_prices` with sections `rules`, `audit_log`, `overrides`.
     * `no_save => true` suppresses the bulk Save button — every control
     * group writes through its own group/trigger action.
     *
     * Uses the array-union operator (`+=`) instead of direct assignment so
     * that values already set on the entry by an earlier hook (notably the
     * inline `sort`/`key`/`label` populated in `WWPP_Admin_Settings::
     * admin_settings_tabs()`, or a third-party plugin extending the tab) are
     * preserved while the structural keys this section owns (`sections`,
     * `no_save`) are layered on. The inline entry then becomes a meaningful
     * fallback rather than always-overwritten dead code, and changes to the
     * inline `sort`/`label` propagate without needing a mirror edit here.
     *
     * @since 2.1.0
     *
     * @param array $tabs Tabs array provided by the filter.
     *
     * @return array Tabs array with the new entry merged in.
     */
    public function register_tab( array $tabs ): array {
        if ( ! isset( $tabs['wholesale_prices']['child']['wholesale_role_automation'] ) || ! is_array( $tabs['wholesale_prices']['child']['wholesale_role_automation'] ) ) {
            $tabs['wholesale_prices']['child']['wholesale_role_automation'] = array();
        }

        $tabs['wholesale_prices']['child']['wholesale_role_automation'] += array(
            'key'      => 'wholesale_role_automation',
            'label'    => __( 'Wholesale Role Automation', 'woocommerce-wholesale-prices-premium' ),
            'sort'     => 5,
            'sections' => array(
                'rules'     => array(
                    'label' => __( 'Rules', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'Define rules that promote or demote users between wholesale roles.', 'woocommerce-wholesale-prices-premium' ),
                ),
                'audit_log' => array(
                    'label' => __( 'Audit log', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'Browse, filter, and export the history of automated role changes.', 'woocommerce-wholesale-prices-premium' ),
                ),
                'overrides' => array(
                    'label' => __( 'Overrides', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'Lock specific users so the automation skips them.', 'woocommerce-wholesale-prices-premium' ),
                ),
            ),
            'no_save'  => true,
        );

        return $tabs;
    }

    /**
     * Provide the controls payload for each Wholesale Role Automation section.
     *
     * Hooked on `wwp_admin_setting_default_controls` — the global controls
     * filter WWP free applies to the full controls payload at the end of
     * `WWP_Admin_Settings::_default_controls()`, AFTER each per-tab
     * `wwp_admin_setting_default_<tab>_controls` filter has run. Ensures
     * the three section keys under `wholesale_prices.wholesale_role_automation`
     * are populated so the Vue settings app can render the section
     * navigation and each section's controls without a null:
     *   - `rules`     — `group_conditional` control built by `rules_controls()`.
     *   - `audit_log` — filters + buttons + paginated table built by `audit_log_controls()`.
     *   - `overrides` — `group_conditional` for locked-user list built by `overrides_controls()`.
     *
     * Uses the array-union operator (`+=`) instead of direct assignment so
     * that controls already populated by an earlier callback (whether by
     * a third-party plugin extending the wholesale_role_automation tab or
     * by a sibling sub-issue hooking at a lower priority) are preserved —
     * only the missing default section keys are filled in. The parent
     * `wholesale_prices` key is also defensively initialised because
     * nothing in WWP free guarantees its shape to filter consumers.
     *
     * @since 2.1.0
     *
     * @param array $controls Controls payload provided by the filter.
     *
     * @return array Controls payload with the wholesale_role_automation
     *               sections present and populated for rules, audit_log,
     *               and overrides.
     */
    public function register_controls( array $controls ): array {
        if ( ! isset( $controls['wholesale_prices'] ) || ! is_array( $controls['wholesale_prices'] ) ) {
            $controls['wholesale_prices'] = array();
        }

        if ( ! isset( $controls['wholesale_prices']['wholesale_role_automation'] ) || ! is_array( $controls['wholesale_prices']['wholesale_role_automation'] ) ) {
            $controls['wholesale_prices']['wholesale_role_automation'] = array();
        }

        $controls['wholesale_prices']['wholesale_role_automation'] += array(
            'rules'     => $this->rules_controls( $this->build_role_options() ),
            'audit_log' => $this->audit_log_controls( array() ),
            'overrides' => $this->overrides_controls(),
        );

        return $controls;
    }

    /**
     * Build the rules section's `group_conditional` control payload.
     *
     * Single control whose drawer fields describe every editable rule
     * attribute and whose table config describes every visible column. The
     * `data` array is sourced from `Rules_Repository::all_for_table()` so
     * rows arrive pre-rendered for fast Vue rendering.
     *
     * @since 2.1.0
     *
     * @param array $role_options Map of `role_key => Role Label` for the wholesale role selects.
     *
     * @return array The rules section controls payload (single-element array containing one `group_conditional` control).
     */
    private function rules_controls( array $role_options ): array {
        $upgrade_options   = $role_options + array(
            'customer' => __( 'Customer (revoke wholesale)', 'woocommerce-wholesale-prices-premium' ),
        );
        $downgrade_options = array(
            '' => __( '(no downgrade)', 'woocommerce-wholesale-prices-premium' ),
        ) + $upgrade_options;

        return array(
            array(
                'type'         => 'group_conditional',
                'id'           => 'wwpp_wholesale_role_automation_rules',
                'classes'      => 'wwpp-wholesale-role-automation-rules',
                'group_action' => 'wwpp_wholesale_role_automation_rule_save',
                'button_label' => __( 'Add rule', 'woocommerce-wholesale-prices-premium' ),
                'fields'       => array(
                    array(
                        'type'        => 'text',
                        'id'          => 'label',
                        'label'       => __( 'Label', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'e.g. Promote bronze after $5,000 spend', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'A short, human-readable name for this rule. Shown in the rules list and audit log.', 'woocommerce-wholesale-prices-premium' ),
                        'default'     => '',
                    ),
                    array(
                        'type'        => 'radio',
                        'id'          => 'scope_type',
                        'label'       => __( 'Scope', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'Which users this rule evaluates against.', 'woocommerce-wholesale-prices-premium' ),
                        'options'     => array(
                            'any_non_wholesale' => __( 'Any non-wholesale user', 'woocommerce-wholesale-prices-premium' ),
                            'wholesale_role'    => __( 'Users currently in role', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default'     => 'any_non_wholesale',
                    ),
                    array(
                        'type'        => 'select',
                        'id'          => 'scope_role_key',
                        'label'       => __( 'Role', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'The wholesale role users must currently hold for this rule to apply.', 'woocommerce-wholesale-prices-premium' ),
                        'options'     => $role_options,
                        'default'     => '',
                        'condition'   => array(
                            array(
                                'key'   => 'scope_type',
                                'value' => 'wholesale_role',
                            ),
                        ),
                    ),
                    array(
                        'type'        => 'radio',
                        'id'          => 'metric',
                        'label'       => __( 'Metric', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'Which customer behaviour the threshold is measured against.', 'woocommerce-wholesale-prices-premium' ),
                        'options'     => array(
                            'total_spent' => __( 'Total spent', 'woocommerce-wholesale-prices-premium' ),
                            'order_count' => __( 'Order count', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default'     => 'total_spent',
                    ),
                    array(
                        'type'        => 'radio',
                        'id'          => 'window_type',
                        'label'       => __( 'Window', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'Time period the metric is summed over.', 'woocommerce-wholesale-prices-premium' ),
                        'options'     => array(
                            'lifetime'     => __( 'Lifetime', 'woocommerce-wholesale-prices-premium' ),
                            'rolling_days' => __( 'Rolling days', 'woocommerce-wholesale-prices-premium' ),
                            'fixed_month'  => __( 'Calendar month', 'woocommerce-wholesale-prices-premium' ),
                            'fixed_year'   => __( 'Calendar year', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default'     => 'lifetime',
                    ),
                    array(
                        'type'        => 'number',
                        'id'          => 'window_days',
                        'label'       => __( 'Window days', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'e.g. 90', 'woocommerce-wholesale-prices-premium' ),
                        /* translators: The literal phrase "Rolling days" inside the sentence must stay in lockstep with the matching window_type radio label (see the "Rolling days" option just above). When translating, keep both copies aligned for the locale. */
                        'description' => __( 'How many days back the rolling window looks. Required when Window is set to "Rolling days".', 'woocommerce-wholesale-prices-premium' ),
                        'default'     => '',
                        'condition'   => array(
                            array(
                                'key'   => 'window_type',
                                'value' => 'rolling_days',
                            ),
                        ),
                    ),
                    array(
                        'type'        => 'text',
                        'id'          => 'threshold',
                        'label'       => __( 'Threshold', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'e.g. 5000 or 24', 'woocommerce-wholesale-prices-premium' ),
                        /* translators: The literal phrases "Total spent" and "Order count" must stay in lockstep with the matching metric radio labels (see the "Total spent" / "Order count" options above). When translating, keep both copies aligned for the locale. */
                        'description' => __( 'The cutoff value. For "Total spent", enter an amount in the shop currency. For "Order count", enter a whole number.', 'woocommerce-wholesale-prices-premium' ),
                        'default'     => '',
                    ),
                    array(
                        'type'        => 'select',
                        'id'          => 'upgrade_to_role_key',
                        'label'       => __( 'Upgrade to', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'Role to assign when the user meets the threshold.', 'woocommerce-wholesale-prices-premium' ),
                        'options'     => $upgrade_options,
                        'default'     => '',
                    ),
                    array(
                        'type'        => 'select',
                        'id'          => 'downgrade_to_role_key',
                        'label'       => __( 'Downgrade to', 'woocommerce-wholesale-prices-premium' ),
                        /* translators: The literal phrase "(no downgrade)" must stay in lockstep with the matching sentinel option label in $downgrade_options (built in build_role_options()). When translating, keep both copies aligned for the locale. */
                        'description' => __( 'Role to assign when the user falls below the threshold. Choose "(no downgrade)" to skip revocation.', 'woocommerce-wholesale-prices-premium' ),
                        'options'     => $downgrade_options,
                        'default'     => '',
                    ),
                    array(
                        'type'        => 'switch',
                        'id'          => 'enabled',
                        'label'       => __( 'Enabled', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'When disabled, this rule is skipped by the evaluation sweep but kept on the list for later use.', 'woocommerce-wholesale-prices-premium' ),
                        'default'     => 'yes',
                        'options'     => array(
                            'yes' => __( 'Enabled', 'woocommerce-wholesale-prices-premium' ),
                            'no'  => __( 'Disabled', 'woocommerce-wholesale-prices-premium' ),
                        ),
                    ),
                ),
                'table'        => array(
                    'drag_and_drop' => true,
                    'edit_action'   => 'wwpp_wholesale_role_automation_rule_edit',
                    'delete_action' => 'wwpp_wholesale_role_automation_rule_delete',
                    'sort_action'   => 'wwpp_wholesale_role_automation_rule_sort',
                    'editable'      => true,
                    'can_delete'    => true,
                    'fields'        => array(
                        array(
                            'title' => __( 'Priority', 'woocommerce-wholesale-prices-premium' ),
                            'key'   => 'priority',
                        ),
                        array(
                            'title' => __( 'Scope', 'woocommerce-wholesale-prices-premium' ),
                            'key'   => 'scope_label',
                        ),
                        array(
                            'title' => __( 'Condition', 'woocommerce-wholesale-prices-premium' ),
                            'key'   => 'condition_label',
                        ),
                        array(
                            'title' => __( 'Window', 'woocommerce-wholesale-prices-premium' ),
                            'key'   => 'window_label',
                        ),
                        array(
                            /* translators: Column header for "upgrade target / downgrade target". */
                            'title' => __( 'Up→/Down→', 'woocommerce-wholesale-prices-premium' ),
                            'key'   => 'transition_label',
                        ),
                        array(
                            'title' => __( 'On', 'woocommerce-wholesale-prices-premium' ),
                            'key'   => 'enabled_label',
                        ),
                        array(
                            'title' => '',
                            'key'   => 'operation',
                        ),
                    ),
                    'show_in_table' => array( 'priority', 'scope_label', 'condition_label', 'window_label', 'transition_label', 'enabled_label', 'operation' ),
                    'data'          => ( new WWPP_Wholesale_Role_Automation_Rules_Repository() )->all_for_table(),
                ),
            ),
        );
    }

    /**
     * Build the `role_key => Role Label` map for the wholesale role selects.
     *
     * Reads the registered wholesale roles via the WWP free plugin and
     * unwraps the `roleName` field to the value the select control expects.
     *
     * @since 2.1.0
     *
     * @return array Map of `role_key => Role Label`.
     */
    private function build_role_options(): array {
        $roles_obj    = WWP_Wholesale_Roles::getInstance()->getAllRegisteredWholesaleRoles();
        $role_options = array();

        if ( ! is_array( $roles_obj ) ) {
            return $role_options;
        }

        foreach ( $roles_obj as $key => $role ) {
            if ( isset( $role['roleName'] ) && is_string( $role['roleName'] ) ) {
                $role_options[ (string) $key ] = $role['roleName'];
            }
        }

        return $role_options;
    }

    /**
     * Whitelist the role-automation group action slugs.
     *
     * Hooked on `wwp_allowed_group_actions`. Allows the free plugin's REST
     * handler to accept the `*_rule_save` / `_edit` / `_delete` / `_sort` /
     * `_override` slugs used by the Rules and Overrides sections, plus
     * `_audit_reload` which is dispatched through the group-settings path
     * (rather than the trigger path) so the full paginated response shape
     * survives the REST round-trip — see `Action_Handlers::handle_audit_reload`
     * PHPDoc for the rationale.
     *
     * @since 2.1.0
     *
     * @param array $actions Existing whitelist.
     *
     * @return array Whitelist with the role-automation slugs appended.
     */
    public function register_group_actions( array $actions ): array {
        return array_merge(
            $actions,
            array(
                'wwpp_wholesale_role_automation_rule_save',
                'wwpp_wholesale_role_automation_rule_edit',
                'wwpp_wholesale_role_automation_rule_delete',
                'wwpp_wholesale_role_automation_rule_sort',
                'wwpp_wholesale_role_automation_override',
                'wwpp_wholesale_role_automation_audit_reload',
            )
        );
    }

    /**
     * Whitelist the role-automation trigger action slugs.
     *
     * Hooked on `wwp_allowed_trigger_actions`. Allows the audit-log CSV
     * download trigger action. The audit-table reload action is intentionally
     * NOT in this list — it lives on `wwp_allowed_group_actions` because the
     * trigger dispatch path strips response keys other than
     * `{status, message, redirect}` and would drop the pagination payload.
     *
     * @since 2.1.0
     *
     * @param array $actions Existing whitelist.
     *
     * @return array Whitelist with the role-automation trigger slugs appended.
     */
    public function register_trigger_actions( array $actions ): array {
        return array_merge(
            $actions,
            array(
                'wwpp_wholesale_role_automation_audit_csv',
            )
        );
    }

    /**
     * Build the controls payload for the `audit_log` section.
     *
     * Returns an ordered list of WWP settings controls: text/select filter
     * inputs, the Export CSV trigger button, the Reload trigger button, and
     * a paginated table backed by `Audit_Log::query()`. The CSV-streaming
     * and reload action handlers themselves live in a sibling sub-issue;
     * this method only places the button controls in the payload.
     *
     * @since 2.1.0
     *
     * @param array $filters Filter map matching the keys accepted by
     *                       `Audit_Log::query()`: `user_id`, `rule_id`,
     *                       `direction`, `trigger_type`, `from`, `to`.
     *                       Missing keys default to empty (no filter).
     *
     * @return array Ordered list of WWP settings control arrays.
     */
    private function audit_log_controls( array $filters ): array {
        return array(
            array(
                'type'    => 'paragraph',
                'id'      => 'wwpp_wholesale_role_automation_audit_filters_heading',
                'classes' => 'wwpp-wholesale-role-automation-sub-heading',
                'content' => '<strong>' . esc_html__( 'Filters', 'woocommerce-wholesale-prices-premium' ) . '</strong>',
            ),
            array(
                'type'        => 'text',
                'id'          => 'wwpp_wholesale_role_automation_audit_filter_user',
                'label'       => __( 'User', 'woocommerce-wholesale-prices-premium' ),
                'placeholder' => __( 'User ID or email', 'woocommerce-wholesale-prices-premium' ),
                'description' => __( 'Filter the audit log by a single user. Accepts a numeric ID or the user\'s email.', 'woocommerce-wholesale-prices-premium' ),
                'default'     => '',
            ),
            array(
                'type'        => 'text',
                'id'          => 'wwpp_wholesale_role_automation_audit_filter_rule',
                'label'       => __( 'Rule', 'woocommerce-wholesale-prices-premium' ),
                'placeholder' => __( 'Rule ID', 'woocommerce-wholesale-prices-premium' ),
                'description' => __( 'Filter by the ID of the rule that produced the audit row.', 'woocommerce-wholesale-prices-premium' ),
                'default'     => '',
            ),
            array(
                'type'        => 'select',
                'id'          => 'wwpp_wholesale_role_automation_audit_filter_direction',
                'label'       => __( 'Direction', 'woocommerce-wholesale-prices-premium' ),
                'description' => __( 'Limit rows to a single transition direction (upgrade, downgrade, pause, unpause).', 'woocommerce-wholesale-prices-premium' ),
                'default'     => '',
                'options'     => array( '' => __( 'All', 'woocommerce-wholesale-prices-premium' ) ) + self::direction_labels(),
            ),
            array(
                'type'        => 'select',
                'id'          => 'wwpp_wholesale_role_automation_audit_filter_trigger_type',
                'label'       => __( 'Trigger', 'woocommerce-wholesale-prices-premium' ),
                'description' => __( 'Limit rows to changes produced by a single trigger (e.g. order completed, refund, scheduled sweep).', 'woocommerce-wholesale-prices-premium' ),
                'default'     => '',
                'options'     => array( '' => __( 'All', 'woocommerce-wholesale-prices-premium' ) ) + self::trigger_labels(),
            ),
            array(
                'type'        => 'text',
                'id'          => 'wwpp_wholesale_role_automation_audit_filter_from',
                'label'       => __( 'From date', 'woocommerce-wholesale-prices-premium' ),
                'placeholder' => __( 'YYYY-MM-DD', 'woocommerce-wholesale-prices-premium' ),
                'description' => __( 'Inclusive lower bound, in UTC. Leave blank to span all earlier rows.', 'woocommerce-wholesale-prices-premium' ),
                'default'     => '',
            ),
            array(
                'type'        => 'text',
                'id'          => 'wwpp_wholesale_role_automation_audit_filter_to',
                'label'       => __( 'To date', 'woocommerce-wholesale-prices-premium' ),
                'placeholder' => __( 'YYYY-MM-DD', 'woocommerce-wholesale-prices-premium' ),
                'description' => __( 'Inclusive upper bound, in UTC. Leave blank to span all later rows.', 'woocommerce-wholesale-prices-premium' ),
                'default'     => '',
            ),
            array(
                'type'    => 'paragraph',
                'id'      => 'wwpp_wholesale_role_automation_audit_actions_divider',
                'classes' => 'wwpp-wholesale-role-automation-divider',
                'content' => '<hr />',
            ),
            array(
                'type'    => 'paragraph',
                'id'      => 'wwpp_wholesale_role_automation_audit_actions_heading',
                'classes' => 'wwpp-wholesale-role-automation-sub-heading',
                'content' => '<strong>' . esc_html__( 'Actions', 'woocommerce-wholesale-prices-premium' ) . '</strong>',
            ),
            array(
                'type'         => 'button',
                'id'           => 'wwpp_wholesale_role_automation_audit_csv_btn',
                'button_label' => __( 'Export CSV', 'woocommerce-wholesale-prices-premium' ),
                'action'       => 'wwpp_wholesale_role_automation_audit_csv',
                'description'  => __( 'Streams a CSV of audit rows matching the current filters.', 'woocommerce-wholesale-prices-premium' ),
            ),
            array(
                'type'         => 'button',
                'id'           => 'wwpp_wholesale_role_automation_audit_reload_btn',
                'button_label' => __( 'Reload', 'woocommerce-wholesale-prices-premium' ),
                'action'       => 'wwpp_wholesale_role_automation_audit_reload',
            ),
            array(
                'type'    => 'paragraph',
                'id'      => 'wwpp_wholesale_role_automation_audit_table_divider',
                'classes' => 'wwpp-wholesale-role-automation-divider',
                'content' => '<hr />',
            ),
            array(
                'type'    => 'paragraph',
                'id'      => 'wwpp_wholesale_role_automation_audit_table_heading',
                'classes' => 'wwpp-wholesale-role-automation-sub-heading',
                'content' => '<strong>' . esc_html__( 'Audit entries', 'woocommerce-wholesale-prices-premium' ) . '</strong>',
            ),
            array(
                'type'      => 'table',
                'id'        => 'wwpp_wholesale_role_automation_audit_table',
                'paginated' => array(
                    'pageSize'        => self::AUDIT_TABLE_PER_PAGE,
                    'defaultPageSize' => self::AUDIT_TABLE_PER_PAGE,
                ),
                'fields'    => array(
                    array(
                        'title'     => __( 'When (UTC)', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'created_at',
                        'dataIndex' => 'created_at',
                    ),
                    array(
                        'title'     => __( 'User', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'user_label',
                        'dataIndex' => 'user_label',
                    ),
                    array(
                        'title'     => __( 'Rule', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'rule_label',
                        'dataIndex' => 'rule_label',
                    ),
                    array(
                        'title'     => __( 'Direction', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'direction_label',
                        'dataIndex' => 'direction_label',
                    ),
                    array(
                        'title'     => __( 'From', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'from_label',
                        'dataIndex' => 'from_label',
                    ),
                    array(
                        'title'     => __( 'To', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'to_label',
                        'dataIndex' => 'to_label',
                    ),
                    array(
                        'title'     => __( 'Trigger', 'woocommerce-wholesale-prices-premium' ),
                        'key'       => 'trigger_label',
                        'dataIndex' => 'trigger_label',
                    ),
                ),
                'data'      => $this->audit_rows_for_table( $filters )['data'] ?? array(),
            ),
        );
    }

    /**
     * Build the controls payload for the `overrides` section.
     *
     * Returns a single `group_conditional` control whose drawer carries a
     * `user_email` text field plus a `locked` switch and whose embedded
     * table lists currently-locked users. The override save/delete action
     * handler that resolves the email to a user account and toggles the
     * lock meta lives in a sibling sub-issue; this method only places the
     * control in the payload.
     *
     * @since 2.1.0
     *
     * @return array Single-element list with the `group_conditional` control.
     */
    private function overrides_controls(): array {
        return array(
            array(
                'type'    => 'paragraph',
                'id'      => 'wwpp_wholesale_role_automation_overrides_heading',
                'classes' => 'wwpp-wholesale-role-automation-sub-heading',
                'content' => '<strong>' . esc_html__( 'Locked users', 'woocommerce-wholesale-prices-premium' ) . '</strong>',
            ),
            array(
                'type'         => 'group_conditional',
                'id'           => 'wwpp_wholesale_role_automation_overrides',
                'group_action' => 'wwpp_wholesale_role_automation_override',
                'button_label' => __( 'Add override', 'woocommerce-wholesale-prices-premium' ),
                'fields'       => array(
                    array(
                        'type'        => 'text',
                        'id'          => 'user_email',
                        'label'       => __( 'User email', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'customer@example.com', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'Enter the user\'s email; we resolve it to a user account on save.', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    array(
                        'type'        => 'switch',
                        'id'          => 'locked',
                        'label'       => __( 'Locked', 'woocommerce-wholesale-prices-premium' ),
                        'description' => __( 'When locked, the automation skips this user entirely — no upgrades, downgrades, or pauses are applied.', 'woocommerce-wholesale-prices-premium' ),
                        'default'     => 'yes',
                        'options'     => array(
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                        ),
                    ),
                ),
                'table'        => array(
                    'editable'   => true,
                    'can_delete' => true,
                    'fields'     => array(
                        array(
                            'title'     => __( 'User', 'woocommerce-wholesale-prices-premium' ),
                            'key'       => 'display_name',
                            'dataIndex' => 'display_name',
                        ),
                        array(
                            'title'     => __( 'Email', 'woocommerce-wholesale-prices-premium' ),
                            'key'       => 'email',
                            'dataIndex' => 'email',
                        ),
                        array(
                            'title'     => __( 'Locked since', 'woocommerce-wholesale-prices-premium' ),
                            'key'       => 'locked_since_label',
                            'dataIndex' => 'locked_since_label',
                        ),
                    ),
                    'data'       => $this->locked_users_for_table(),
                ),
            ),
        );
    }

    /**
     * Build the audit-table data payload for the current filter set.
     *
     * Fetches the first page of `Audit_Log::query()` and decorates each row
     * in the `data` key with the pre-rendered labels the Vue table component
     * reads: `user_label`, `rule_label`, `direction_label`, `from_label`,
     * `to_label`, `trigger_label`. The pagination keys (`total`, `page`,
     * `per_page`, `pages`) returned by the service are passed through
     * unchanged so the Vue component can render its pagination footer.
     *
     * @since 2.1.0
     *
     * @param array $filters Filter map forwarded verbatim to
     *                       `Audit_Log::query()`; see that method for the
     *                       accepted keys.
     *
     * @return array{data: array, total: int, page: int, per_page: int, pages: int}
     *               Enriched rows plus pagination metadata.
     */
    private function audit_rows_for_table( array $filters ): array {
        $rules_repo  = new WWPP_Wholesale_Role_Automation_Rules_Repository();
        $role_helper = new WWPP_Wholesale_Role_Automation_Role_Helper();
        $result      = $this->audit_log()->query( $filters, 1, self::AUDIT_TABLE_PER_PAGE );
        $rows        = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
        $rule_cache  = array();
        $user_cache  = array();

        $direction_labels = self::direction_labels();
        $trigger_labels   = self::trigger_labels();
        $date_format      = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        foreach ( $rows as $index => $row ) {
            $direction      = (string) ( $row['direction'] ?? '' );
            $trigger        = (string) ( $row['trigger_type'] ?? '' );
            $created_at_raw = (string) ( $row['created_at'] ?? '' );

            $row['created_at']      = '' === $created_at_raw
                ? ''
                : date_i18n( $date_format, strtotime( $created_at_raw . ' UTC' ), true );
            $row['user_label']      = $this->resolve_user_label( (int) ( $row['user_id'] ?? 0 ), $user_cache );
            $row['rule_label']      = $this->resolve_rule_label( (string) ( $row['rule_id'] ?? '' ), $rule_cache, $rules_repo );
            $row['direction_label'] = $direction_labels[ $direction ] ?? $direction;
            $row['from_label']      = $this->resolve_role_label( $row['from_role'] ?? null, $role_helper );
            $row['to_label']        = $this->resolve_role_label( $row['to_role'] ?? null, $role_helper );
            $row['trigger_label']   = $trigger_labels[ $trigger ] ?? $trigger;

            $rows[ $index ] = $row;
        }

        $result['data'] = $rows;

        return $result;
    }

    /**
     * Build the overrides-table data rows for currently-locked users.
     *
     * Queries `WP_User_Query` for users carrying the lock meta key
     * (`WWPP_USER_META_WHOLESALE_ROLE_AUTOMATION_LOCKED = 'yes'`) and, for
     * each one, looks up the most-recent `direction='pause'` audit row to
     * resolve the "locked since" label. Locked-user counts are small in
     * practice (a handful per store) so the per-user audit lookup stays well
     * within page-load budget.
     *
     * @since 2.1.0
     *
     * @return array List of row arrays with keys `user_id`, `display_name`,
     *               `email`, `locked_since_label`.
     */
    private function locked_users_for_table(): array {
        $query = new WP_User_Query(
            array(
                'fields'     => array( 'ID', 'display_name', 'user_email' ),
                'number'     => -1,
                'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Override list is a small set; full scan is acceptable for the admin Overrides tab.
                    array(
                        'key'     => WWPP_USER_META_WHOLESALE_ROLE_AUTOMATION_LOCKED,
                        'value'   => 'yes',
                        'compare' => '=',
                    ),
                ),
            )
        );

        $users = $query->get_results();
        if ( ! is_array( $users ) || empty( $users ) ) {
            return array();
        }

        $audit_log = $this->audit_log();
        $rows      = array();

        foreach ( $users as $user ) {
            $user_id = isset( $user->ID ) ? (int) $user->ID : 0;
            if ( $user_id <= 0 ) {
                continue;
            }

            $rows[] = array(
                'user_id'            => $user_id,
                'display_name'       => isset( $user->display_name ) ? (string) $user->display_name : '',
                'email'              => isset( $user->user_email ) ? (string) $user->user_email : '',
                'locked_since_label' => $this->resolve_locked_since_label( $user_id, $audit_log ),
            );
        }

        return $rows;
    }

    /**
     * Resolve a user ID to an "email (display name)" label.
     *
     * Caches lookups by `user_id` across the row set so the same user is
     * not re-resolved for every audit row, matching the in-method cache
     * pattern used by `resolve_rule_label()`.
     *
     * @since 2.1.0
     *
     * @param int                $user_id    The audit row's `user_id` column.
     * @param array<int, string> $user_cache Reference to a per-call cache keyed by user ID.
     *
     * @return string The combined label, or a placeholder when the user
     *                cannot be resolved (deleted account or empty id).
     */
    private function resolve_user_label( int $user_id, array &$user_cache ): string {
        if ( $user_id <= 0 ) {
            return self::ROLE_PLACEHOLDER;
        }

        if ( array_key_exists( $user_id, $user_cache ) ) {
            return $user_cache[ $user_id ];
        }

        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            $user_cache[ $user_id ] = self::ROLE_PLACEHOLDER;
            return $user_cache[ $user_id ];
        }

        $email        = (string) $user->user_email;
        $display_name = (string) $user->display_name;

        if ( '' === $display_name ) {
            $user_cache[ $user_id ] = $email;
            return $user_cache[ $user_id ];
        }

        /* translators: 1: user email. 2: user display name. */
        $user_cache[ $user_id ] = sprintf( __( '%1$s (%2$s)', 'woocommerce-wholesale-prices-premium' ), $email, $display_name );

        return $user_cache[ $user_id ];
    }

    /**
     * Resolve a rule UUID to its label, falling back to "(rule deleted)".
     *
     * Caches lookups across the row set so the same rule_id is not
     * re-fetched for every audit row.
     *
     * @since 2.1.0
     *
     * @param string                                          $rule_id    Audit row's `rule_id` column.
     * @param array                                           $rule_cache In-out cache keyed by rule_id; pass an empty array on first call.
     * @param WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repo Repository used to fetch the rule.
     *
     * @return string The rule label, "(rule deleted)" if not found, or the
     *                placeholder when the row carries no rule_id.
     */
    private function resolve_rule_label( string $rule_id, array &$rule_cache, WWPP_Wholesale_Role_Automation_Rules_Repository $rules_repo ): string {
        if ( '' === $rule_id ) {
            return self::ROLE_PLACEHOLDER;
        }

        if ( ! array_key_exists( $rule_id, $rule_cache ) ) {
            $rule                   = $rules_repo->find( $rule_id );
            $rule_cache[ $rule_id ] = is_array( $rule ) && isset( $rule['label'] ) ? (string) $rule['label'] : '';
        }

        if ( '' === $rule_cache[ $rule_id ] ) {
            return __( '(rule deleted)', 'woocommerce-wholesale-prices-premium' );
        }

        return $rule_cache[ $rule_id ];
    }

    /**
     * Translated label map for the `direction` enum.
     *
     * Single source of truth shared between the filter `select` options in
     * `audit_log_controls()` and the row-level `direction_label` decoration
     * in `audit_rows_for_table()`.
     *
     * @since 2.1.0
     *
     * @return array<string, string> Map keyed by `direction` enum value.
     */
    private static function direction_labels(): array {
        return array(
            'upgrade'   => __( 'Upgrade', 'woocommerce-wholesale-prices-premium' ),
            'downgrade' => __( 'Downgrade', 'woocommerce-wholesale-prices-premium' ),
            'pause'     => __( 'Pause', 'woocommerce-wholesale-prices-premium' ),
            'unpause'   => __( 'Unpause', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Translated label map for the `trigger_type` enum.
     *
     * Single source of truth shared between the filter `select` options in
     * `audit_log_controls()` and the row-level `trigger_label` decoration
     * in `audit_rows_for_table()`.
     *
     * @since 2.1.0
     *
     * @return array<string, string> Map keyed by `trigger_type` enum value.
     */
    private static function trigger_labels(): array {
        return array(
            'order_completed' => __( 'Order completed', 'woocommerce-wholesale-prices-premium' ),
            'refund'          => __( 'Refund', 'woocommerce-wholesale-prices-premium' ),
            'cancelled'       => __( 'Cancelled', 'woocommerce-wholesale-prices-premium' ),
            'scheduled'       => __( 'Scheduled', 'woocommerce-wholesale-prices-premium' ),
            'admin'           => __( 'Admin', 'woocommerce-wholesale-prices-premium' ),
        );
    }

    /**
     * Resolve a wholesale role key to its label, with em-dash fallback.
     *
     * Pause and unpause audit rows carry `null` from_role/to_role; render
     * the placeholder in that case rather than the literal "null". The
     * `customer` key is a valid downgrade target (revoking wholesale) but
     * is not a registered wholesale role, so it is special-cased here to
     * match the label `rules_controls()` uses in the rules drawer and to
     * avoid the warning logger inside `Role_Helper::role_label()`.
     *
     * @since 2.1.0
     *
     * @param string|null                                $role_key    Audit row's `from_role` or `to_role` column.
     * @param WWPP_Wholesale_Role_Automation_Role_Helper $role_helper Helper used for label resolution.
     *
     * @return string The resolved label or the placeholder.
     */
    private function resolve_role_label( ?string $role_key, WWPP_Wholesale_Role_Automation_Role_Helper $role_helper ): string {
        if ( null === $role_key || '' === $role_key ) {
            return self::ROLE_PLACEHOLDER;
        }

        if ( 'customer' === $role_key ) {
            return __( 'Customer (revoke wholesale)', 'woocommerce-wholesale-prices-premium' );
        }

        return $role_helper->role_label( $role_key );
    }

    /**
     * Resolve the "locked since" timestamp for a user.
     *
     * Returns the `created_at` of the user's most-recent `direction='pause'`
     * audit row, or an em-dash when no such row exists (e.g. the user was
     * locked before the audit table was created, or the row was pruned).
     *
     * @since 2.1.0
     *
     * @param int                                      $user_id   The locked user's ID.
     * @param WWPP_Wholesale_Role_Automation_Audit_Log $audit_log Audit log service used to look up the row.
     *
     * @return string The UTC timestamp string or the placeholder.
     */
    private function resolve_locked_since_label( int $user_id, WWPP_Wholesale_Role_Automation_Audit_Log $audit_log ): string {
        $result = $audit_log->query(
            array(
                'user_id'   => $user_id,
                'direction' => 'pause',
            ),
            1,
            1
        );

        if ( empty( $result['data'] ) || ! is_array( $result['data'] ) ) {
            return self::ROLE_PLACEHOLDER;
        }

        $row = $result['data'][0];

        if ( ! isset( $row['created_at'] ) || '' === $row['created_at'] ) {
            return self::ROLE_PLACEHOLDER;
        }

        return date_i18n(
            get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
            strtotime( $row['created_at'] . ' UTC' ),
            true
        );
    }
}
