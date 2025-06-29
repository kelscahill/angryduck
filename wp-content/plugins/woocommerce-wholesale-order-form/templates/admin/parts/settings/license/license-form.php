<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\Helpers\Datetime;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

$wwof_license_email           = is_multisite() ? get_site_option( WWOF_OPTION_LICENSE_EMAIL ) : get_option( WWOF_OPTION_LICENSE_EMAIL );
$wwof_license_key             = is_multisite() ? get_site_option( WWOF_OPTION_LICENSE_KEY ) : get_option( WWOF_OPTION_LICENSE_KEY );
$wwof_license_expiration_date = is_multisite() ? get_site_option( WWOF_LICENSE_EXPIRED ) : get_option( WWOF_LICENSE_EXPIRED );

// phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['license-email'] ) && ! empty( $_GET['license-email'] ) ) {
    $wwof_license_email = sanitize_text_field( wp_unslash( $_GET['license-email'] ) );
}

if ( isset( $_GET['license-key'] ) && ! empty( $_GET['license-key'] ) ) {
    $wwof_license_key = sanitize_text_field( wp_unslash( $_GET['license-key'] ) );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$display = $wwof_license_expiration_date ? 'table-row' : 'none';
?>
<div id="wws_settings_wwof" class="wws_license_settings_page_container">

    <table class="form-table">
        <tbody>
            <tr
                id="wws_wwof_license_expired_notice"
                style="background-color: #fff; border-left: 4px solid #dc3232; display: <?php echo esc_html( $display ); ?>"
            >
                <th scope="row" class="titledesc">
                    <label
                        style="display: inline-block; padding-left: 10px;"
                    ><?php esc_html_e( 'License Expired', 'woocommerce-wholesale-order-form' ); ?></label>
                </th>
                <td class="forminp">
                    <p>
                        <?php
                        printf(/* translators: %1$s = license expiration date; %2$s = <br> tag; %3$s = opening <strong> tag; %4$s = </strong> closing tag; %5$s = opening <a> tag; %6$s = closing </a> tag */
                            esc_html__( 'The entered license was purchased over 12 months ago and expired on %1$s.%2$sTo continue receiving support & updates please %3$s%5$sclick here to renew your license%6$s.%4$s', 'woocommerce-wholesale-order-form' ),
                            '<strong id="wwof-license-expiration-date">' . esc_html(
                                Datetime::convert_datetime_to_site_standard_format(
                                    $wwof_license_expiration_date,
                                    wc_date_format()
                                )
                            ) . '</strong>',
                            '<br>',
                            '<strong>',
                            '</strong>',
                            '<a href="https://wholesalesuiteplugin.com/my-account/?utm_source=wwof&utm_medium=license&utm_campaign=wwoflicenseexpirednotice" target="_blank">',
                            '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row" class="titledesc">
                    <label
                        for="wws_wwof_license_email"
                    ><?php esc_html_e( 'License Email', 'woocommerce-wholesale-order-form' ); ?></label>
                </th>
                <td class="forminp forminp-text">
                    <input
                        type="text" id="wws_wwof_license_email" class="regular-text ltr"
                        value="<?php echo esc_attr( $wwof_license_email ); ?>"
                    />
                </td>
            </tr>

            <tr>
                <th scope="row" class="titledesc">
                    <label
                        for="wws_wwof_license_key"
                    ><?php esc_html_e( 'License Key', 'woocommerce-wholesale-order-form' ); ?></label>
                </th>
                <td class="forminp forminp-text">
                    <input
                        type="password" id="wws_wwof_license_key" class="regular-text ltr"
                        value="<?php echo esc_attr( $wwof_license_key ); ?>"
                    />
                </td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <input
            type="button" id="wws_save_btn" class="button button-primary"
            value="<?php esc_html_e( 'Save Changes', 'woocommerce-wholesale-order-form' ); ?>"
        />
        <span class="spinner" style="float: none; vertical-align: middle; margin-top: 0;"></span>
    </p>

</div><!--#wws_settings_wwof-->
