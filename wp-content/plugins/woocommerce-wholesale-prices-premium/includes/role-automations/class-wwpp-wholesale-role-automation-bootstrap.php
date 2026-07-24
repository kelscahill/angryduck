<?php
/**
 * Wholesale Role Automation Bootstrap.
 *
 * Module entry point. Constructs and wires every helper, service, handler,
 * admin, and email class for the Wholesale Role Automation feature, owns the
 * versioned `dbDelta` migration for the audit table, and registers the
 * `plugins_loaded` hook that runs the migration on plugin updates.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Wholesale_Role_Automation_Bootstrap.
 *
 * Single instance per request, exposed via
 * `$wc_wholesale_prices_premium->wwpp_wholesale_role_automation`.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Bootstrap {

    /**
     * Audit-table schema version. Bump whenever the schema changes; the same
     * `dbDelta` call handles forward migrations.
     *
     * @since 2.1.0
     * @var   string
     */
    const DB_VERSION = '1.0';

    /**
     * Property that holds the single main instance of the bootstrap.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Bootstrap
     */
    private static $_instance;

    /**
     * Role helper.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Role_Helper
     */
    public $role_helper;

    /**
     * Window helper.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Window_Helper
     */
    public $window_helper;

    /**
     * Currency helper.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Currency_Helper
     */
    public $currency_helper;

    /**
     * Rules repository.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Rules_Repository
     */
    public $rules_repository;

    /**
     * Audit log.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Audit_Log
     */
    public $audit_log;

    /**
     * Metric provider.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Metric_Provider
     */
    public $metric_provider;

    /**
     * Pause handler.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Pause_Handler
     */
    public $pause_handler;

    /**
     * Upgrade handler.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Upgrade_Handler
     */
    public $upgrade_handler;

    /**
     * Downgrade handler.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Downgrade_Handler
     */
    public $downgrade_handler;

    /**
     * Action dispatcher.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Action_Dispatcher
     */
    public $action_dispatcher;

    /**
     * Evaluator.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Evaluator
     */
    public $evaluator;

    /**
     * Scheduler.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Scheduler
     */
    public $scheduler;

    /**
     * Settings tab.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Settings_Tab
     */
    public $settings_tab;

    /**
     * Group / trigger action handlers.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_Action_Handlers
     */
    public $action_handlers;

    /**
     * User profile lock field.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_User_Profile_Field
     */
    public $user_profile_field;

    /**
     * My Account role-change notice.
     *
     * @since  2.1.0
     * @access public
     * @var    WWPP_Wholesale_Role_Automation_My_Account_Notice
     */
    public $my_account_notice;

    /**
     * WWPP_Wholesale_Role_Automation_Bootstrap constructor.
     *
     * Constructs every helper, service, handler, and admin class in
     * dependency order. WC_Email subclasses are not constructed here because
     * WooCommerce instantiates them lazily via the `woocommerce_email_classes`
     * filter.
     *
     * @since 2.1.0
     *
     * @param array $dependencies Array of instance objects of all dependencies provided by the main plugin singleton. Reserved for future consumers; the bootstrap currently constructs its own collaborators.
     */
    public function __construct( $dependencies = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $dependencies is reserved for upcoming DI wiring (separate issues consume it); kept on the signature now so the public contract is stable.
        $this->role_helper     = new WWPP_Wholesale_Role_Automation_Role_Helper();
        $this->window_helper   = new WWPP_Wholesale_Role_Automation_Window_Helper();
        $this->currency_helper = new WWPP_Wholesale_Role_Automation_Currency_Helper();

        $this->rules_repository = new WWPP_Wholesale_Role_Automation_Rules_Repository( $this->role_helper );
        $this->audit_log        = new WWPP_Wholesale_Role_Automation_Audit_Log();

        $this->metric_provider = new WWPP_Wholesale_Role_Automation_Metric_Provider( $this->window_helper, $this->currency_helper );
        $this->pause_handler   = new WWPP_Wholesale_Role_Automation_Pause_Handler( $this->rules_repository, $this->audit_log );

        $this->upgrade_handler   = new WWPP_Wholesale_Role_Automation_Upgrade_Handler( $this->role_helper, $this->audit_log );
        $this->downgrade_handler = new WWPP_Wholesale_Role_Automation_Downgrade_Handler( $this->role_helper, $this->audit_log );

        $this->action_dispatcher = new WWPP_Wholesale_Role_Automation_Action_Dispatcher( $this->upgrade_handler, $this->downgrade_handler, $this->audit_log );
        $this->evaluator         = new WWPP_Wholesale_Role_Automation_Evaluator( $this->rules_repository, $this->metric_provider, $this->pause_handler, $this->role_helper );
        $this->scheduler         = new WWPP_Wholesale_Role_Automation_Scheduler( $this->evaluator, $this->action_dispatcher, $this->role_helper, $this->rules_repository );

        $this->settings_tab       = new WWPP_Wholesale_Role_Automation_Settings_Tab();
        $this->action_handlers    = new WWPP_Wholesale_Role_Automation_Action_Handlers( $this->rules_repository, $this->pause_handler, $this->audit_log );
        $this->user_profile_field = new WWPP_Wholesale_Role_Automation_User_Profile_Field( $this->pause_handler );
        $this->my_account_notice  = new WWPP_Wholesale_Role_Automation_My_Account_Notice( $this->role_helper );
    }

    /**
     * Ensure that only one instance of the bootstrap is loaded or can be loaded (Singleton Pattern).
     *
     * @since  2.1.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies provided by the main plugin singleton.
     *
     * @return WWPP_Wholesale_Role_Automation_Bootstrap
     */
    public static function instance( $dependencies = array() ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Register module hooks.
     *
     * Wires the `plugins_loaded` migration check (priority 9 so it runs before
     * any other module that might read the audit table), the `init` recurring
     * sweep registration, the WC order events that drive per-user
     * evaluation, the Action Scheduler callbacks for the sweep, sweep-chunk,
     * and per-user worker, the WC email classes and actions, and the four
     * settings-tab registration filters (priority 10 to match existing WWPP
     * tabs). Downstream issues append their own hook registrations to this
     * method.
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function run(): void {
        add_action( 'plugins_loaded', array( $this, 'maybe_migrate' ), 9 );

        add_action( 'init', array( $this, 'maybe_schedule_sweep' ), 10 );

        add_action( 'woocommerce_order_status_completed', array( $this->scheduler, 'on_order_completed' ), 10, 2 );
        add_action( 'woocommerce_order_refunded', array( $this->scheduler, 'on_order_refunded' ), 10, 2 );
        add_action( 'woocommerce_order_status_cancelled', array( $this->scheduler, 'on_order_cancelled' ), 10, 2 );

        add_action( WWPP_ACTION_WHOLESALE_ROLE_AUTOMATION_SWEEP, array( $this->scheduler, 'handle_sweep' ) );
        add_action( WWPP_Wholesale_Role_Automation_Scheduler::ACTION_SWEEP_CHUNK, array( $this->scheduler, 'handle_sweep_chunk' ), 10, 2 );
        add_action( WWPP_ACTION_WHOLESALE_ROLE_AUTOMATION_EVAL_USER, array( $this->scheduler, 'handle_evaluate_user' ), 10, 3 );

        add_action( 'woocommerce_before_account_navigation', array( $this->my_account_notice, 'render_notice' ), 10 );

        add_action( 'show_user_profile', array( $this->user_profile_field, 'render_lock_field' ) );
        add_action( 'edit_user_profile', array( $this->user_profile_field, 'render_lock_field' ) );
        add_action( 'personal_options_update', array( $this->user_profile_field, 'save_lock_field' ) );
        add_action( 'edit_user_profile_update', array( $this->user_profile_field, 'save_lock_field' ) );

        add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
        add_filter( 'woocommerce_email_actions', array( $this, 'register_email_actions' ) );

        add_filter( 'wwp_admin_setting_default_tabs', array( $this->settings_tab, 'register_tab' ), 10, 1 );
        add_filter( 'wwp_admin_setting_default_controls', array( $this->settings_tab, 'register_controls' ), 10, 1 );
        add_filter( 'wwp_allowed_group_actions', array( $this->settings_tab, 'register_group_actions' ), 10, 1 );
        add_filter( 'wwp_allowed_trigger_actions', array( $this->settings_tab, 'register_trigger_actions' ), 10, 1 );

        add_filter( 'wwp_group_settings_wwpp_wholesale_role_automation_rule_save', array( $this->action_handlers, 'handle_rule_save' ) );
        add_filter( 'wwp_group_settings_wwpp_wholesale_role_automation_rule_edit', array( $this->action_handlers, 'handle_rule_edit' ) );
        add_filter( 'wwp_group_settings_wwpp_wholesale_role_automation_rule_delete', array( $this->action_handlers, 'handle_rule_delete' ) );
        add_filter( 'wwp_group_settings_wwpp_wholesale_role_automation_rule_sort', array( $this->action_handlers, 'handle_rule_sort' ) );
        add_filter( 'wwp_group_settings_wwpp_wholesale_role_automation_override', array( $this->action_handlers, 'handle_override' ) );

        add_filter( 'wwp_trigger_wwpp_wholesale_role_automation_audit_csv', array( $this->action_handlers, 'handle_audit_csv' ) );

        // Audit reload is dispatched through `wwp_group_settings_*` (not `wwp_trigger_*`)
        // so the WWP free plugin's REST handler forwards the full response shape
        // (`data`, `total`, `page`, `per_page`, `pages`) instead of stripping to
        // `status`/`message`/`redirect`. See `handle_audit_reload()` PHPDoc.
        add_filter( 'wwp_group_settings_wwpp_wholesale_role_automation_audit_reload', array( $this->action_handlers, 'handle_audit_reload' ) );
    }

    /**
     * Idempotently register the daily recurring sweep with Action Scheduler.
     *
     * Uses a two-layer guard against duplicate schedules:
     * 1. Fast path: `as_has_scheduled_action` short-circuits before the
     *    schedule call when one already exists, so plugin re-activations
     *    don't trigger a redundant insert.
     * 2. Atomic path: the schedule call passes `$unique = true`, so even
     *    when two concurrent requests slip past step 1
     *    between the check and the insert (TOCTOU window), Action
     *    Scheduler's own uniqueness index rejects the duplicate at the
     *    DB level.
     *
     * Hooked on `init` because Action Scheduler initializes its data store
     * on `init` priority 1 — calling `as_has_scheduled_action()` or
     * `as_schedule_recurring_action()` earlier (e.g. on `plugins_loaded`,
     * where the functions are already defined but the store is not yet
     * initialized) triggers `_doing_it_wrong` notices.
     *
     * @since 2.1.0
     * @since 2.1.1 Moved from `plugins_loaded` to `init` so the Action
     *              Scheduler data store is initialized before use.
     *
     * @return void
     */
    public function maybe_schedule_sweep(): void {
        if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
            return;
        }

        if ( as_has_scheduled_action( WWPP_ACTION_WHOLESALE_ROLE_AUTOMATION_SWEEP, array(), WWPP_Wholesale_Role_Automation_Scheduler::AS_GROUP ) ) {
            return;
        }

        as_schedule_recurring_action(
            time(),
            DAY_IN_SECONDS,
            WWPP_ACTION_WHOLESALE_ROLE_AUTOMATION_SWEEP,
            array(),
            WWPP_Wholesale_Role_Automation_Scheduler::AS_GROUP,
            true
        );
    }

    /**
     * Register the wholesale role automation `WC_Email` subclasses.
     *
     * Hooked into `woocommerce_email_classes`. Lazily requires the email class
     * files (they extend `WC_Email`, which is not available at plugin-load
     * time) and appends them to the WC mailer's email map keyed by class name.
     *
     * @since 2.1.0
     *
     * @param array $emails The current map of registered `WC_Email` instances keyed by class name.
     *
     * @return array The map with the upgrade and downgrade emails appended.
     */
    public function register_email_classes( array $emails ): array {
        require_once WWPP_INCLUDES_PATH . 'role-automations/emails/class-wwpp-email-role-upgraded.php';
        require_once WWPP_INCLUDES_PATH . 'role-automations/emails/class-wwpp-email-role-downgraded.php';

        $emails['WWPP_Email_Role_Upgraded']   = new WWPP_Email_Role_Upgraded();
        $emails['WWPP_Email_Role_Downgraded'] = new WWPP_Email_Role_Downgraded();

        return $emails;
    }

    /**
     * Register the wholesale role automation actions that trigger emails.
     *
     * Hooked into `woocommerce_email_actions`. Adding an action here causes
     * `WC_Emails` to bind a callback that invokes `->trigger( ...$args )` on
     * every registered email when the action fires.
     *
     * @since 2.1.0
     *
     * @param array $actions The current list of email-triggering action names.
     *
     * @return array The list with the upgrade and downgrade actions appended.
     */
    public function register_email_actions( array $actions ): array {
        $actions[] = 'wwpp_wholesale_role_automation_upgraded';
        $actions[] = 'wwpp_wholesale_role_automation_downgraded';

        return $actions;
    }

    /**
     * Run the audit-table migration when the stored DB version is behind.
     *
     * Cheap on the hot path: a single `get_option` read once the version is
     * caught up. Wired to both `plugins_loaded` (update path) and
     * `WWPP_Bootstrap::_activate()` (fresh-install path, multisite-aware).
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function maybe_migrate(): void {
        $installed = get_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_DB_VERSION, '0' );

        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            $this->install_or_update_audit_table();
            update_option( WWPP_OPTION_WHOLESALE_ROLE_AUTOMATION_DB_VERSION, self::DB_VERSION, false );
        }
    }

    /**
     * Create or update the audit-log table via `dbDelta`.
     *
     * `VARCHAR(20)` is used for `direction` and `trigger_type` (not `ENUM`)
     * so new values can be added later without a schema change. `from_role`
     * and `to_role` are nullable so diagnostic rows for "rule's target role
     * no longer exists" can be inserted without a sentinel string.
     *
     * `trigger_ref` is `VARCHAR(32)` because the documented use cases are
     * compact numeric identifiers (order ID, actor user ID); it is not a
     * free-form string column. `created_at` is stored as UTC `DATETIME`
     * (matching the WP core `*_gmt` convention) — `Audit_Log::append()` MUST
     * write `gmdate( 'Y-m-d H:i:s' )` and downstream consumers MUST compare
     * against UTC bounds.
     *
     * @since 2.1.0
     *
     * @return void
     */
    private function install_or_update_audit_table(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . WWPP_TABLE_WHOLESALE_ROLE_AUTOMATION_AUDIT;

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            rule_id VARCHAR(36) NOT NULL,
            from_role VARCHAR(60) DEFAULT NULL,
            to_role VARCHAR(60) DEFAULT NULL,
            direction VARCHAR(20) NOT NULL,
            trigger_type VARCHAR(20) NOT NULL,
            trigger_ref VARCHAR(32) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_id, created_at),
            KEY idx_rule (rule_id, created_at),
            KEY idx_created (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }
}
