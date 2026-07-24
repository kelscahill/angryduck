<?php
/**
 * Role Automation Mail Catcher.
 *
 * Intercepts outgoing emails via `wp_mail`, stores the most recent one in a
 * transient, and exposes REST endpoints so Cypress tests can inspect, clear,
 * and trigger role-automation action hooks without a WP-CLI dependency.
 *
 * Only active when `WP_DEBUG` is `true` (i.e., on test/dev sites). Never
 * runs on production builds where `WP_DEBUG` is absent or false.
 *
 * REST endpoints (namespace `wwpp/v1`):
 *   GET    /wp-json/wwpp/v1/mailcatcher/latest     — latest captured mail or 404.
 *   DELETE /wp-json/wwpp/v1/mailcatcher/clear      — clears the transient.
 *   POST   /wp-json/wwpp/v1/test/fire-action       — fires a named do_action.
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
 * Class WWPP_Wholesale_Role_Automation_Mail_Catcher
 *
 * Captures outgoing `wp_mail` calls and exposes them via the REST API.
 * Also provides a `fire-action` endpoint so Cypress can trigger named
 * WordPress actions without a WP-CLI binary.
 *
 * Only registered when `WP_DEBUG` is `true`.
 *
 * @since 2.1.0
 */
class WWPP_Wholesale_Role_Automation_Mail_Catcher {

    /**
     * Singleton instance.
     *
     * @since  2.1.0
     * @access private
     * @var    WWPP_Wholesale_Role_Automation_Mail_Catcher|null
     */
    private static $_instance = null;

    /**
     * REST API namespace shared by all endpoints in this class.
     *
     * @since  2.1.0
     * @access private
     * @var    string
     */
    private $api_namespace = 'wwpp/v1';

    /**
     * Transient key used to store the latest captured mail.
     *
     * @since  2.1.0
     * @access private
     * @var    string
     */
    private $transient_key = 'wwpp_role_automation_mailcatcher_latest';

    /**
     * Explicit list of hook names the fire-action endpoint will accept.
     *
     * A narrow allowlist (rather than a prefix match) keeps the test
     * surface tight: only the hooks the email scenarios actually need
     * are exposed, so a misuse cannot trigger internal lifecycle hooks
     * like sweep-chunk-run or evaluate_user. Expand this list only when
     * a new test surface requires firing an additional hook.
     *
     * @since  2.1.0
     * @access private
     * @var    string[]
     */
    private $allowed_hooks = array(
        'wwpp_wholesale_role_automation_upgraded',
        'wwpp_wholesale_role_automation_downgraded',
    );

    /**
     * Private constructor — use ::instance().
     *
     * @since 2.1.0
     */
    private function __construct() {}

    /**
     * Return the singleton instance.
     *
     * @since 2.1.0
     *
     * @return WWPP_Wholesale_Role_Automation_Mail_Catcher
     */
    public static function instance(): self {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Register hooks.
     *
     * The outer `WP_DEBUG` gates in `woocommerce-wholesale-prices-premium.plugin.php`
     * (the conditional `require_once` at file load and the `instance()->run()`
     * call in `init_plugin()`) prevent this method from being reachable on
     * production builds, so no internal check is needed here.
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function run(): void {
        add_filter( 'wp_mail', array( $this, 'capture_mail' ) );
        add_action( 'rest_api_init', array( $this, 'register_api' ) );
    }

    /**
     * Intercept `wp_mail` and persist the latest email to a transient.
     *
     * Returns `$args` unchanged so normal mail delivery continues.
     *
     * @since 2.1.0
     *
     * @param array $args {
     *     Mail arguments passed to `wp_mail()`.
     *
     *     @type string|string[] $to          Recipient(s).
     *     @type string          $subject     Subject line.
     *     @type string          $message     Email body.
     *     @type string|string[] $headers     Additional headers.
     *     @type string[]        $attachments Attachment paths.
     * }
     *
     * @return array The unchanged $args.
     */
    public function capture_mail( array $args ): array {
        $mail = array(
            'to'      => $args['to'],
            'subject' => $args['subject'],
            'message' => $args['message'],
            'headers' => $args['headers'],
            'time'    => time(),
        );

        set_transient( $this->transient_key, $mail, HOUR_IN_SECONDS );

        return $args;
    }

    /**
     * Register all REST API routes for this class.
     *
     * Registers three routes:
     *   - GET  mailcatcher/latest  — retrieve the latest captured email.
     *   - DELETE mailcatcher/clear — clear the stored email.
     *   - POST  test/fire-action   — fire a scoped WordPress action hook.
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function register_api(): void {
        register_rest_route(
            $this->api_namespace,
            '/mailcatcher/latest',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_latest' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                ),
            )
        );

        register_rest_route(
            $this->api_namespace,
            '/mailcatcher/clear',
            array(
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'clear' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                ),
            )
        );

        register_rest_route(
            $this->api_namespace,
            '/test/fire-action',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'fire_action' ),
                    'permission_callback' => array( $this, 'permission_check' ),
                    'args'                => array(
                        'hook' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'args' => array(
                            'required' => false,
                            'type'     => 'array',
                            'default'  => array(),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Check that the requester has sufficient permissions.
     *
     * Grants access to any user with `manage_options` (administrators) or
     * `manage_woocommerce` (shop managers) so Cypress can authenticate via
     * basic auth without needing a full admin account.
     *
     * @since 2.1.0
     *
     * @return bool True when the current user is allowed.
     */
    public function permission_check(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
    }

    /**
     * Return the most recently captured email.
     *
     * Returns a 404 WP_Error when no email has been captured since the last
     * `clear` call so Cypress can distinguish "empty inbox" from a request
     * error.
     *
     * @since 2.1.0
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_latest() {
        $mail = get_transient( $this->transient_key );

        if ( false === $mail ) {
            return new WP_Error(
                'wwpp_no_mail',
                __( 'No email has been captured yet.', 'woocommerce-wholesale-prices-premium' ),
                array( 'status' => 404 )
            );
        }

        return rest_ensure_response( $mail );
    }

    /**
     * Delete the stored email so the next scenario starts with a clean inbox.
     *
     * @since 2.1.0
     *
     * @return WP_REST_Response
     */
    public function clear(): WP_REST_Response {
        delete_transient( $this->transient_key );

        return rest_ensure_response( array( 'cleared' => true ) );
    }

    /**
     * Fire a scoped WordPress action hook with the supplied arguments.
     *
     * Accepts only hooks present in `$allowed_hooks` so this endpoint
     * cannot be used to trigger arbitrary WordPress actions. The `args`
     * array is forwarded positionally to `do_action_ref_array()`.
     *
     * @since 2.1.0
     *
     * @param WP_REST_Request $request The REST request containing `hook` and `args`.
     *
     * @return WP_REST_Response|WP_Error `{ fired: true, hook: <hook> }` on success,
     *                                   or a 400 WP_Error when the hook name is rejected.
     */
    public function fire_action( WP_REST_Request $request ) {
        $hook = sanitize_text_field( $request->get_param( 'hook' ) );
        $args = (array) $request->get_param( 'args' );

        if ( ! in_array( $hook, $this->allowed_hooks, true ) ) {
            return new WP_Error(
                'wwpp_invalid_hook',
                sprintf(
                    /* translators: %s: comma-separated list of allowed hook names. */
                    __( 'Hook must be one of: %s.', 'woocommerce-wholesale-prices-premium' ),
                    implode( ', ', $this->allowed_hooks )
                ),
                array( 'status' => 400 )
            );
        }

        do_action_ref_array( $hook, $args ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

        return rest_ensure_response(
            array(
                'fired' => true,
                'hook'  => $hook,
            )
        );
    }
}
