<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="wws-license-interstitial" class='wrap nosubsub wws-license-interstitial--<?php echo esc_attr( $interstitial_type ); ?>' data-software_key="<?php echo esc_attr( $software_key ); ?>">
    <hr class="wp-header-end">
    <div class="wws-license-interstitial-container">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wws-license-settings' ) ); ?>" class="wws-license-interstitial-close dashicons dashicons-no-alt"></a>
        <img src="<?php echo esc_url( WWPP_IMAGES_URL . '/logo.svg' ); ?>" alt="Wholesale Suite Logo" class="wws-license-interstitial-logo">
        <h1 class="wws-license-interstitial-title">
            <?php
            switch ( $interstitial_type ) {
                case 'expired':
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                            __( '%1$sUrgent!%2$s Your Wholesale Suite license has expired!', 'woocommerce-wholesale-lead-capture' ),
                            '<span class="text-color-red">',
                            '</span>'
                        )
                    );
                    break;
                case 'nolicense':
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                            __( '%1$sUrgent!%2$s Your Wholesale Suite license is missing!', 'woocommerce-wholesale-lead-capture' ),
                            '<span class="text-color-red">',
                            '</span>'
                        )
                    );
                    break;
                case 'disabled':
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                            __( '%1$sUrgent!%2$s Your Wholesale Suite license is disabled!', 'woocommerce-wholesale-lead-capture' ),
                            '<span class="text-color-red">',
                            '</span>'
                        )
                    );
                    break;
            }
            ?>
        </h1>
        <p><?php esc_html_e( 'Without an active license, your website front end will still continue to receive wholesale orders, leads and show wholesale pricing but premium functionality has been disabled until a valid license is entered.', 'woocommerce-wholesale-lead-capture' ); ?></p>
        <img src="<?php echo esc_url( WWPP_IMAGES_URL . '/wws-locked.png' ); ?>" alt="License Expired" class="wws-license-interstitial-locked-image">
        <ul class="wws-license-interstitial-plugins-status">
            <?php if ( ! empty( $wws_license_data ) ) : ?>
                <?php foreach ( $wws_license_data as $plugin_key => $license_data ) : ?>
                    <li class="plugin-key plugin-key--<?php echo esc_attr( strtolower( esc_attr( $plugin_key ) ) ); ?>">
                        <span class="plugin-name"><?php echo esc_html( WWLC_WWS_License_Manager::get_plugin_name( $plugin_key ) ); ?>:</span>
                        <?php if ( array_key_exists( 'license_status', $license_data ) ) : ?>
                        <span class="plugin-status text-color-<?php echo 'active' === $license_data['license_status'] ? 'green' : 'red'; ?>">
                            <?php echo esc_html( WWLC_WWS_License_Manager::get_license_status_i18n( $license_data['license_status'] ) ); ?>
                        </span>
                        <?php else : ?>
                        <span class="plugin-status text-color-red">
                            <?php esc_html_e( 'No license found', 'woocommerce-wholesale-lead-capture' ); ?>
                        </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <?php if ( 'expired' === $interstitial_type ) : ?>
            <a href="<?php echo esc_url( $license_management_url ); ?>" target="_blank" class="button button-primary button-hero wws-license-interstitial-action-button"><?php esc_html_e( 'Renew License', 'woocommerce-wholesale-lead-capture' ); ?></a>
        <?php elseif ( 'nolicense' === $interstitial_type ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wws-license-settings' ) ); ?>" class="button button-primary button-hero wws-license-interstitial-action-button"><?php esc_html_e( 'Enter License Now', 'woocommerce-wholesale-lead-capture' ); ?></a>
        <?php elseif ( 'disabled' === $interstitial_type ) : ?>
            <a href="<?php echo esc_url( 'https://wholesalesuiteplugin.com/bundle/?utm_source=wwlc&utm_medium=drm&utm_campaign=wwlcdrminterstitialrepurchaselink' ); ?>" target="_blank" class="button button-primary button-hero wws-license-interstitial-action-button"><?php esc_html_e( 'Repurchase New License', 'woocommerce-wholesale-lead-capture' ); ?></a>
        <?php endif; ?>
        <div class="wws-license-interstitial-action-links">
            <?php if ( 'expired' === $interstitial_type || 'disabled' === $interstitial_type ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wws-license-settings' ) ); ?>"><?php esc_html_e( 'Enter a new license', 'woocommerce-wholesale-lead-capture' ); ?></a>
                <a href="#" id="wws-refresh-license-status-link"><?php esc_html_e( 'Refresh license status', 'woocommerce-wholesale-lead-capture' ); ?><span class="license-status-timeout"></span></a>
            <?php elseif ( 'nolicense' === $interstitial_type ) : ?>
                <a href="<?php echo esc_url( 'https://wholesalesuiteplugin.com/bundle/?utm_source=wwwlcwpp&utm_medium=drm&utm_campaign=wwlcdrminterstitialpurchaselink' ); ?>" target="_blank"><?php esc_html_e( 'Don’t have a license yet? Purchase here.', 'woocommerce-wholesale-lead-capture' ); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Fix white backround in embeded page -->
<div class="wws-license-interstitial-overlay"></div>
