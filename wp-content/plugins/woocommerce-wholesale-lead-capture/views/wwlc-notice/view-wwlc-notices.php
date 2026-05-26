<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$wwp_active = is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' );
$wwp_notice = get_option( 'wwp_admin_notice_getting_started_show' );

$wwpp_active = is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' );
$wwpp_notice = get_option( 'wwp_admin_notice_getting_started_show' );

$wwof_active = is_plugin_active( 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php' );
$wwof_notice = get_option( 'wwof_admin_notice_getting_started_show' );

$active_counter = 1;

if ( ( $wwp_active && 'yes' === $wwp_notice ) || ( $wwpp_active && 'yes' === $wwpp_notice ) ) {
    ++$active_counter;
}
if ( $wwof_active && 'yes' === $wwof_notice ) {
    ++$active_counter;
}

$wwp_getting_started_link  = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-free-plugin-getting-started-guide/?utm_source=wwp&utm_medium=kb&utm_campaign=WWPGettingStartedGuide';
$wwpp_getting_started_link = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-premium-getting-started-guide/?utm_source=wwpp&utm_medium=kb&utm_campaign=WWPPGettingStartedGuide';
$wwof_getting_started_link = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-order-form-getting-started-guide/?utm_source=wwof&utm_medium=kb&utm_campaign=WWOFGettingStartedGuide';
$wwlc_getting_started_link = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-lead-capture-getting-started-guide/?utm_source=wwlc&utm_medium=kb&utm_campaign=WWLCGettingStartedGuide';

// Check if current user is admin or shop manager.
// Check if getting started option is 'yes'.
if ( ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) && ( 'yes' === get_option( 'wwlc_admin_notice_getting_started_show' ) || false === get_option( 'wwlc_admin_notice_getting_started_show' ) ) ) {

    $screen = get_current_screen();

    // Check if WWS license page.
    // Check if products pages.
    // Check if woocommerce pages ( wc, products, analytics ).
    // Check if plugins page.
    if ( 'settings_page_wws-license-settings' === $screen->id || 'product' === $screen->post_type || in_array( $screen->parent_base, array( 'woocommerce', 'plugins' ), true ) ) {

        if ( $active_counter > 1 ) {
            ?>
            <div class="updated notice wwlc-getting-started">
                <p>
                    <img width="300" src="<?php echo esc_url( WWLC_IMAGES_ROOT_URL ); ?>wholesale-suite-logo.svg" alt="Wholesale Suite"/>
                </p>
                <p>
                    <?php esc_html_e( 'Thank you for choosing Wholesale Suite – the most complete wholesale solution for building wholesale sales into your existing WooCommerce driven store.', 'woocommerce-wholesale-lead-capture' ); ?>
                </p>
                <p>
                    <?php esc_html_e( 'To help you get up and running as quickly and as smoothly as possible, we\'ve published a number of getting started guides for our tools. You\'ll find links to these at any time inside the Help section in the settings for each plugin, but here are the links below so you can read them now.', 'woocommerce-wholesale-lead-capture' ); ?>
                </p>
                <p>
                    <?php if ( $wwpp_active && 'yes' === $wwpp_notice ) { ?>
                        <a href="<?php echo esc_url( $wwpp_getting_started_link ); ?>" target="_blank">
                            <?php esc_html_e( 'Wholesale Prices Premium Guide', 'woocommerce-wholesale-lead-capture' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                        </a>
                    <?php } elseif ( $wwp_active && 'yes' === $wwp_notice ) { ?>
                        <a href="<?php echo esc_url( $wwp_getting_started_link ); ?>" target="_blank">
                            <?php esc_html_e( 'Wholesale Prices Guide', 'woocommerce-wholesale-lead-capture' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                        </a>
                    <?php } ?>

                    <?php if ( $wwof_active && 'yes' === $wwof_notice ) { ?>
                        <a href="<?php echo esc_url( $wwof_getting_started_link ); ?>" target="_blank">
                            <?php esc_html_e( 'Wholesale Order Form Guide', 'woocommerce-wholesale-lead-capture' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                        </a>
                    <?php } ?>

                    <a href="<?php echo esc_url( $wwlc_getting_started_link ); ?>" target="_blank">
                        <?php esc_html_e( 'Wholesale Lead Capture Guide', 'woocommerce-wholesale-lead-capture' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                    </a>
                </p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'woocommerce-wholesale-lead-capture' ); ?></span>
                </button>
            </div>
            <?php
        } else {
            ?>
            <div class="updated notice wwlc-getting-started">
                <p>
                    <img width="300" src="<?php echo esc_url( WWLC_IMAGES_ROOT_URL ); ?>wholesale-suite-logo.svg" alt="Wholesale Suite"/>
                </p>
                <p>
                    <?php esc_html_e( 'Thank you for choosing Lead Capture to managed your wholesale customers.', 'woocommerce-wholesale-lead-capture' ); ?>
                </p>
                <p>
                    <?php esc_html_e( 'The plugin has already done a lot of the heavy lifting for you and you\'ll find new pages for the wholesale registration and wholesale login forms under your Pages area. We highly recommend reading the getting started guide to help you get up to speed on everything from customizing your registration form to user approval flow.', 'woocommerce-wholesale-lead-capture' ); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( $wwlc_getting_started_link ); ?>" target="_blank">
                        <?php esc_html_e( 'Read the Getting Started guide', 'woocommerce-wholesale-lead-capture' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                    </a>
                </p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'woocommerce-wholesale-lead-capture' ); ?></span>
                </button>
            </div>
            <?php
        }
    }
}
