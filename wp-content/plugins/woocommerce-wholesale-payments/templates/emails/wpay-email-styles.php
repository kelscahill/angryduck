<?php
/**
 * Email Styles for WooCommerce Wholesale Payments
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-wholesale-payments/emails/wpay-email-styles.php.
 *
 * HOWEVER, on occasion WooCommerce Wholesale Payments will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce Wholesale Payments\Templates\Emails
 * @version 1.0.0
 * @category Templates
 * @author Wholesale Suite
 * @license GPL-2.0+
 * @link https://wholesalesuiteplugin.com
 */

use Automattic\WooCommerce\Internal\Email\EmailFont;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

$settings = $settings ?? array();

// Load colors.
$bg               = get_option( 'woocommerce_email_background_color' );
$body             = get_option( 'woocommerce_email_body_background_color' );
$base             = get_option( 'woocommerce_email_base_color' );
$text             = get_option( 'woocommerce_email_text_color' );
$footer_text      = get_option( 'woocommerce_email_footer_text_color' );
$header_alignment = get_option( 'woocommerce_email_header_alignment', $email_improvements_enabled ? 'left' : false );
$logo_image_width = get_option( 'woocommerce_email_header_image_width', '120' );
$default_font     = 'Helvetica';
$font_family      = $email_improvements_enabled ? get_option( 'woocommerce_email_font_family', $default_font ) : $default_font;

/**
 * Check if we are in preview mode (WooCommerce > Settings > Emails).
 *
 * @since 9.6.0
 * @param bool $is_email_preview Whether the email is being previewed.
 */
$is_email_preview = isset( $settings['is_email_preview'] ) ? $settings['is_email_preview'] : false;

if ( $is_email_preview ) {
    $bg_transient               = get_transient( 'woocommerce_email_background_color' );
    $body_transient             = get_transient( 'woocommerce_email_body_background_color' );
    $base_transient             = get_transient( 'woocommerce_email_base_color' );
    $text_transient             = get_transient( 'woocommerce_email_text_color' );
    $footer_text_transient      = get_transient( 'woocommerce_email_footer_text_color' );
    $header_alignment_transient = get_transient( 'woocommerce_email_header_alignment' );
    $logo_image_width_transient = get_transient( 'woocommerce_email_header_image_width' );
    $font_family_transient      = get_transient( 'woocommerce_email_font_family' );

    $bg               = $bg_transient ? $bg_transient : $bg;
    $body             = $body_transient ? $body_transient : $body;
    $base             = $base_transient ? $base_transient : $base;
    $text             = $text_transient ? $text_transient : $text;
    $footer_text      = $footer_text_transient ? $footer_text_transient : $footer_text;
    $header_alignment = $header_alignment_transient ? $header_alignment_transient : $header_alignment;
    $logo_image_width = $logo_image_width_transient ? $logo_image_width_transient : $logo_image_width;
    $font_family      = $font_family_transient ? $font_family_transient : $font_family;
}

// Only use safe fonts. They won't be escaped to preserve single quotes.
$safe_font_family = EmailFont::$font[ $font_family ] ?? EmailFont::$font[ $default_font ];

$base_text = wc_light_or_dark( $base, '#202020', '#ffffff' );

// Pick a contrasting color for links.
$link_color = wc_hex_is_light( $base ) ? $base : $base_text;

if ( wc_hex_is_light( $body ) ) {
    $link_color = wc_hex_is_light( $base ) ? $base_text : $base;
}

// If email improvements are enabled, always use the base color for links.
if ( $email_improvements_enabled ) {
    $link_color = $base;
}

$border_color    = wc_light_or_dark( $body, 'rgba(0, 0, 0, .2)', 'rgba(255, 255, 255, .2)' );
$bg_darker_10    = wc_hex_darker( $bg, 10 );
$body_darker_10  = wc_hex_darker( $body, 10 );
$base_lighter_20 = wc_hex_lighter( $base, 20 );
$base_lighter_40 = wc_hex_lighter( $base, 40 );
$text_lighter_20 = wc_hex_lighter( $text, 20 );
$text_lighter_40 = wc_hex_lighter( $text, 40 );

?>
body {
    background-color: <?php echo esc_attr( $bg ); ?>;
    padding: 0;
    text-align: center;
}

#outer_wrapper {
    background-color: <?php echo esc_attr( $bg ); ?>;
}

#inner_wrapper {
    background-color: <?php echo esc_attr( $body ); ?>;
    border-radius: 8px;
}

#wrapper {
    margin: 0 auto;
    padding: <?php echo $email_improvements_enabled ? '24px 0' : '70px 0'; ?>;
    -webkit-text-size-adjust: none !important;
    width: 100%;
    max-width: 600px;border-radius: 8px;
}

#wrapper table#inner_wrapper {
    background-color: transparent;
    border-radius: 8px;
}

#template_container {
    background-color: <?php echo esc_attr( $body ); ?>;
    border-radius: 8px !important;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1) !important;
    background-color: <?php echo esc_attr( $body ); ?>;
}

#template_header {
    background-color: <?php echo esc_attr( $base ); ?>;
    border-radius: 8px 8px 0 0 !important;
    color: <?php echo esc_attr( $email_improvements_enabled ? $text : $base_text ); ?>;;
    font-weight: bold;
    line-height: 100%;
    vertical-align: middle;
    font-family: <?php echo $safe_font_family; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
    padding: 48px 32px;
    text-align: center;
    border-bottom: 0;
    bgcolor: <?php echo esc_attr( $email_improvements_enabled ? $body : $base ); ?>;
}

#template_header h1,
#template_header h1 a {
    color: <?php echo esc_attr( $email_improvements_enabled ? $text : $base_text ); ?>;
    background-color: inherit;
}

#body_content {
    background-color: <?php echo esc_attr( $body ); ?>;
}

#body_content_inner {
    color: <?php echo esc_attr( $text ); ?>;
    font-family: <?php echo $safe_font_family; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
    font-size: 16px;
    line-height: 150%;
    text-align: <?php echo is_rtl() ? 'right' : 'left'; ?>;
    padding: 3rem 2rem;
}

.wpay-text {
    color: <?php echo esc_attr( $text ); ?>;
    font-size: 18px;
    margin-bottom: 24px;
}

.wpay-highlight {
    font-weight: 600;
}

.wpay-cta {
    padding-top: 24px;
    text-align: center;
}

.wpay-cta-button {
    background-color: <?php echo esc_attr( $base ); ?>;
    color: white;
    padding: 12px 32px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 18px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
    text-decoration: none;
}

.wpay-cta-icon {
    width: 18px;
    height: 18px;
    display: inline-block;
}

.wpay-cta-icon svg {
    width: 100%;
    height: 100%;
    stroke: currentColor;
    stroke-width: 3;
}

.wpay-cta-note {
    margin-top: 16px;
    font-size: 14px;
    color: <?php echo esc_attr( $text ); ?>;
}

#template_footer {
    padding: 24px 32px;
    text-align: center;
}

.wpay-powered-by {
    margin-top: 32px;
    text-align: center;
}

.wpay-powered-by-content {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.wpay-powered-by-text {
    font-size: 12px;
    color: <?php echo esc_attr( $footer_text ); ?>;
}

.wpay-powered-by-logo {
    height: 32px;
}

/* Responsive styles */
@media screen and (max-width: 600px) {
    #wrapper {
        width: 100% !important;
    }

    #template_header {
        padding: 32px 16px !important;
    }

    #template_header h1 {
        font-size: 24px !important;
    }

    #body_content_inner {
        padding: 32px 16px !important;
        font-size: 14px !important;
    }

    .wpay-text {
        font-size: 16px !important;
    }

    .wpay-cta-button {
        padding: 8px 24px !important;
        font-size: 16px !important;
    }

    #template_footer {
        padding: 16px !important;
    }

    .wpay-powered-by-content {
        flex-direction: column;
    }
}
