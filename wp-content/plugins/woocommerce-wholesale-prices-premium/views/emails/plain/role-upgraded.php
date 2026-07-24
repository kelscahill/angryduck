<?php
/**
 * Wholesale role upgraded email — plain-text template.
 *
 * This template can be overridden by copying it to
 * `yourtheme/woocommerce/emails/plain/role-upgraded.php`.
 *
 * Available variables:
 *
 * - string $email_heading      Translated email heading.
 * - string $additional_content Free-form copy added by the store owner.
 * - string $user_display_name  Display name of the recipient.
 * - string $old_role_label     Human-readable label of the previous role.
 * - string $new_role_label     Human-readable label of the new role.
 * - string $rule_label         Human-readable label of the triggering rule.
 * - string $site_title         Site title.
 * - bool   $sent_to_admin      Whether the email is sent to admin.
 * - bool   $plain_text         Whether the email is plain text.
 * - object $email              The WC_Email instance.
 *
 * @package    WooCommerceWholeSalePricesPremium
 * @subpackage WholesaleRoleAutomation\Emails\Templates\Plain
 * @since      2.1.0
 */

defined( 'ABSPATH' ) || exit;

echo "= = = = = = = = = = = = = = = = = = = = =\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n= = = = = = = = = = = = = = = = = = = = =\n\n";

/* translators: %s: customer display name. */
echo sprintf( esc_html__( 'Hi %s,', 'woocommerce-wholesale-prices-premium' ), esc_html( $user_display_name ) ) . "\n\n";

/* translators: 1: site title, 2: new wholesale role label. */
echo sprintf( esc_html__( 'Good news — your wholesale role on %1$s has been upgraded to %2$s.', 'woocommerce-wholesale-prices-premium' ), esc_html( $site_title ), esc_html( $new_role_label ) ) . "\n\n";

if ( '' !== $old_role_label ) {
    /* translators: %s: previous wholesale role label. */
    echo sprintf( esc_html__( 'You have been moved from %s.', 'woocommerce-wholesale-prices-premium' ), esc_html( $old_role_label ) ) . "\n\n";
}

if ( '' !== $rule_label ) {
    /* translators: %s: name/label of the rule that triggered the upgrade. */
    echo sprintf( esc_html__( 'Triggered by: %s', 'woocommerce-wholesale-prices-premium' ), esc_html( $rule_label ) ) . "\n\n";
}

echo esc_html__( 'This affects future orders. Existing subscriptions are unchanged.', 'woocommerce-wholesale-prices-premium' ) . "\n\n";

echo "- - - - - - - - - - - - - - - - - - - - -\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n- - - - - - - - - - - - - - - - - - - - -\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
