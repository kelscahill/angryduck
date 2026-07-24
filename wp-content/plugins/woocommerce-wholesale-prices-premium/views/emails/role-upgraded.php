<?php
/**
 * Wholesale role upgraded email — HTML template.
 *
 * This template can be overridden by copying it to
 * `yourtheme/woocommerce/emails/role-upgraded.php`.
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
 * @subpackage WholesaleRoleAutomation\Emails\Templates
 * @since      2.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output the email header.
 *
 * @hooked WC_Emails::email_header()
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php /* translators: %s: customer display name. */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce-wholesale-prices-premium' ), esc_html( $user_display_name ) ); ?></p>

<?php /* translators: 1: site title, 2: new wholesale role label. */ ?>
<p><?php printf( esc_html__( 'Good news — your wholesale role on %1$s has been upgraded to %2$s.', 'woocommerce-wholesale-prices-premium' ), esc_html( $site_title ), '<strong>' . esc_html( $new_role_label ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

<?php if ( '' !== $old_role_label ) : ?>
    <?php /* translators: %s: previous wholesale role label. */ ?>
    <p><?php printf( esc_html__( 'You have been moved from %s.', 'woocommerce-wholesale-prices-premium' ), '<strong>' . esc_html( $old_role_label ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
<?php endif; ?>

<?php if ( '' !== $rule_label ) : ?>
    <?php /* translators: %s: name/label of the rule that triggered the upgrade. */ ?>
    <p><?php printf( esc_html__( 'Triggered by: %s', 'woocommerce-wholesale-prices-premium' ), '<em>' . esc_html( $rule_label ) . '</em>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
<?php endif; ?>

<p><?php esc_html_e( 'This affects future orders. Existing subscriptions are unchanged.', 'woocommerce-wholesale-prices-premium' ); ?></p>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * Output the email footer.
 *
 * @hooked WC_Emails::email_footer()
 */
do_action( 'woocommerce_email_footer', $email );
