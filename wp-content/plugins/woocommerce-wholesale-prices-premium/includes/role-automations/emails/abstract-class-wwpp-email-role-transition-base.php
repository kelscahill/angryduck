<?php
/**
 * Wholesale Role Automation — shared base for role-transition customer emails.
 *
 * Abstract `WC_Email` subclass that owns the behaviour shared by the role
 * upgrade and role downgrade notifications:
 *
 *  - Stateful trigger data (from/to role keys, resolved labels, recipient).
 *  - The `trigger()` lifecycle (validate, hydrate, locale setup, send, restore).
 *  - HTML / plain-text content assembly via `wc_get_template_html()`.
 *  - Role label and rule label resolution against the role automation
 *    container on `$wc_wholesale_prices_premium`.
 *
 * Concrete subclasses (`WWPP_Email_Role_Upgraded`, `WWPP_Email_Role_Downgraded`)
 * only declare their direction-specific identity in their constructors —
 * `id`, `title`, `description`, default subject / heading / additional
 * content, template paths and the notification hook to bind.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Emails
 * @since      2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WWPP_Email_Role_Transition_Base.
 *
 * @since 2.1.0
 */
abstract class WWPP_Email_Role_Transition_Base extends WC_Email {

    /**
     * The wholesale role key the user is being moved away from.
     *
     * @since 2.1.0
     * @var   string
     */
    public $from_role = '';

    /**
     * The wholesale role key the user is being moved to.
     *
     * @since 2.1.0
     * @var   string
     */
    public $to_role = '';

    /**
     * Human-readable label for the previous wholesale role.
     *
     * @since 2.1.0
     * @var   string
     */
    public $old_role_label = '';

    /**
     * Human-readable label for the new wholesale role.
     *
     * @since 2.1.0
     * @var   string
     */
    public $new_role_label = '';

    /**
     * Display label for the rule that triggered the transition.
     *
     * @since 2.1.0
     * @var   string
     */
    public $rule_label = '';

    /**
     * The user's display name at the time of triggering.
     *
     * @since 2.1.0
     * @var   string
     */
    public $user_display_name = '';

    /**
     * Direction key identifying this transition email.
     *
     * Must return `'upgrade'` for `WWPP_Email_Role_Upgraded` and `'downgrade'`
     * for `WWPP_Email_Role_Downgraded`. Used by callers (and tests) that need
     * to distinguish the two subclasses without a class-name check.
     *
     * @since 2.1.0
     *
     * @return string
     */
    abstract public function direction(): string;

    /**
     * Trigger the transition email.
     *
     * Loads the user, populates placeholders, and calls `$this->send()`. No-ops
     * when the email is disabled, the user is missing, or the role keys are
     * empty.
     *
     * @since 2.1.0
     *
     * @param int    $user_id   The user being notified.
     * @param string $rule_id   The UUID of the rule that triggered the transition.
     * @param string $from_role The previous role key.
     * @param string $to_role   The new role key.
     *
     * @return void
     */
    public function trigger( int $user_id, string $rule_id, string $from_role, string $to_role ): void {
        // Early-return on invalid input so subsequent invocations cannot inherit
        // stale `$this->recipient` / `$this->object` / placeholder state from an
        // earlier valid trigger call on the same WC()->mailer() singleton.
        if ( $user_id <= 0 || '' === $to_role ) {
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            return;
        }

        $this->setup_locale();

        $this->object            = $user;
        $this->recipient         = $user->user_email;
        $this->from_role         = $from_role;
        $this->to_role           = $to_role;
        $this->user_display_name = $user->display_name;
        $this->old_role_label    = $this->resolve_role_label( $from_role );
        $this->new_role_label    = $this->resolve_role_label( $to_role );
        $this->rule_label        = $this->resolve_rule_label( $rule_id );

        $this->placeholders['{user_display_name}'] = $this->user_display_name;
        $this->placeholders['{old_role_label}']    = $this->old_role_label;
        $this->placeholders['{new_role_label}']    = $this->new_role_label;
        $this->placeholders['{rule_label}']        = $this->rule_label;

        if ( $this->is_enabled() && $this->get_recipient() ) {
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        $this->restore_locale();
    }

    /**
     * Render the HTML body of the email.
     *
     * @since 2.1.0
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            $this->get_template_args( false ),
            '',
            $this->template_base
        );
    }

    /**
     * Render the plain-text body of the email.
     *
     * @since 2.1.0
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            $this->get_template_args( true ),
            '',
            $this->template_base
        );
    }

    /**
     * Build the template variable map for HTML and plain-text renders.
     *
     * Extracted so the only difference between `get_content_html()` and
     * `get_content_plain()` is the `plain_text` flag.
     *
     * @since 2.1.0
     *
     * @param bool $plain_text Whether the template should render plain text.
     *
     * @return array
     */
    protected function get_template_args( bool $plain_text ): array {
        return array(
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'user_display_name'  => $this->user_display_name,
            'old_role_label'     => $this->old_role_label,
            'new_role_label'     => $this->new_role_label,
            'rule_label'         => $this->rule_label,
            'site_title'         => $this->get_blogname(),
            'sent_to_admin'      => false,
            'plain_text'         => $plain_text,
            'email'              => $this,
        );
    }

    /**
     * Resolve a wholesale role key to its human-readable label.
     *
     * Falls back to the role key when the role helper is unavailable or returns
     * an empty value.
     *
     * @since 2.1.0
     *
     * @param string $role_key The wholesale role key to resolve.
     *
     * @return string
     */
    protected function resolve_role_label( string $role_key ): string {
        if ( '' === $role_key ) {
            return '';
        }

        global $wc_wholesale_prices_premium;

        if ( isset( $wc_wholesale_prices_premium->wwpp_wholesale_role_automation->role_helper ) ) {
            return $wc_wholesale_prices_premium->wwpp_wholesale_role_automation->role_helper->role_label( $role_key );
        }

        return $role_key;
    }

    /**
     * Resolve a rule UUID to its display label.
     *
     * Falls back to the rule UUID when the rules repository is unavailable or
     * does not return a label.
     *
     * @since 2.1.0
     *
     * @param string $rule_id The rule UUID to resolve.
     *
     * @return string
     */
    protected function resolve_rule_label( string $rule_id ): string {
        if ( '' === $rule_id ) {
            return '';
        }

        global $wc_wholesale_prices_premium;

        if ( isset( $wc_wholesale_prices_premium->wwpp_wholesale_role_automation->rules_repository ) ) {
            $rule = $wc_wholesale_prices_premium->wwpp_wholesale_role_automation->rules_repository->find( $rule_id );

            if ( is_array( $rule ) && ! empty( $rule['label'] ) ) {
                return (string) $rule['label'];
            }
        }

        return $rule_id;
    }
}
