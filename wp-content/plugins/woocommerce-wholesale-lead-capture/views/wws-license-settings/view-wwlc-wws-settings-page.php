<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wwlc_license_email           = is_multisite() ? get_site_option( WWLC_OPTION_LICENSE_EMAIL ) : get_option( WWLC_OPTION_LICENSE_EMAIL );
$wwlc_license_key             = is_multisite() ? get_site_option( WWLC_OPTION_LICENSE_KEY ) : get_option( WWLC_OPTION_LICENSE_KEY );
$wwlc_license_expiration_date = is_multisite() ? get_site_option( WWLC_LICENSE_EXPIRED ) : get_option( WWLC_LICENSE_EXPIRED );

$display = $wwlc_license_expiration_date ? 'table-row' : 'none';

// phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['license-email'] ) && ! empty( $_GET['license-email'] ) ) {
    $wwpp_license_email = sanitize_text_field( wp_unslash( $_GET['license-email'] ) );
}

if ( isset( $_GET['license-key'] ) && ! empty( $_GET['license-key'] ) ) {
    $wwpp_license_key = sanitize_text_field( wp_unslash( $_GET['license-key'] ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>

<div id="wws_settings_wwlc" class="wws_license_settings_page_container">

    <table class="form-table">
        <tbody>
            <tr valign="top" id="wws_wwlc_license_expired_notice" style="background-color: #fff; border-left: 4px solid #dc3232; display: <?php echo esc_attr( $display ); ?>">
                <th scope="row" class="titledesc">
                    <label style="display: inline-block; padding-left: 10px;"><?php esc_html_e( 'License Expired', 'woocommerce-wholesale-lead-capture' ); ?></label>
                </th>
                <td class="forminp">
                    <p>
                        <?php
                        printf(
                            /* Translators: $1 is expiration date. $2 is <br/> tag. $3 is opening <a> tag. $4 is closing </a> tag. */
                            esc_html__( 'The entered license was purchased over 12 months ago and expired on %1$s.%2$sTo continue receiving support & updates please %3$sclick here to renew your license%4$s.', 'woocommerce-wholesale-prices-premium' ),
                            '<b id="wwlc-license-expiration-date">' . esc_html( WWLC_Helper_Functions::convert_datetime_to_site_standard_format( $wwlc_license_expiration_date, wc_date_format() ) ) . '</b>',
                            '<br />',
                            '<b><a href="https://wholesalesuiteplugin.com/my-account/downloads/?utm_source=wwlc&utm_medium=license&utm_campaign=wwlclicenseexpirednotice" target="_blank">',
                            '</a></b>'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="wws_wwlc_license_email"><?php esc_html_e( 'License Email', 'woocommerce-wholesale-lead-capture' ); ?></label>
                </th>
                <td class="forminp forminp-text">
                    <input type="text" id="wws_wwlc_license_email" class="regular-text ltr" value="<?php echo esc_attr( $wwlc_license_email ); ?>"/>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="wws_wwlc_license_key"><?php esc_html_e( 'License Key', 'woocommerce-wholesale-lead-capture' ); ?></label>
                </th>
                <td class="forminp forminp-text">
                    <input type="password" id="wws_wwlc_license_key" class="regular-text ltr" value="<?php echo esc_attr( $wwlc_license_key ); ?>"/>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <input type="button" id="wws_save_btn" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'woocommerce-wholesale-lead-capture' ); ?>"/>
        <span class="spinner"></span>
    </p>

</div><!--#wws_settings_wwlc-->