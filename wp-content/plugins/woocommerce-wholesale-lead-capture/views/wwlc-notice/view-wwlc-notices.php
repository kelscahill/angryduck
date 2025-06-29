<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

    if( !function_exists( 'is_plugin_active' ) )
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    $wwp_active     = is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' );
    $wwp_notice     = get_option( 'wwp_admin_notice_getting_started_show' );

    $wwpp_active    = is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' );
    $wwpp_notice    = get_option( 'wwp_admin_notice_getting_started_show' );

    $wwof_active    = is_plugin_active( 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php' );
    $wwof_notice    = get_option( 'wwof_admin_notice_getting_started_show' );

    $active_counter = 1;
    
    if( ( $wwp_active && $wwp_notice === 'yes' ) || ( $wwpp_active && $wwpp_notice === 'yes' ) ) $active_counter++;
    if( $wwof_active && $wwof_notice === 'yes' ) $active_counter++;

    $wwp_getting_started_link   = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-free-plugin-getting-started-guide/?utm_source=wwp&utm_medium=kb&utm_campaign=WWPGettingStartedGuide';
    $wwpp_getting_started_link  = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-prices-premium-getting-started-guide/?utm_source=wwpp&utm_medium=kb&utm_campaign=WWPPGettingStartedGuide';
    $wwof_getting_started_link  = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-order-form-getting-started-guide/?utm_source=wwof&utm_medium=kb&utm_campaign=WWOFGettingStartedGuide';
    $wwlc_getting_started_link  = 'https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-lead-capture-getting-started-guide/?utm_source=wwlc&utm_medium=kb&utm_campaign=WWLCGettingStartedGuide';

    // Check if current user is admin or shop manager
    // Check if getting started option is 'yes'
    if( ( current_user_can( 'administrator' ) || current_user_can( 'shop_manager' ) ) && ( get_option( 'wwlc_admin_notice_getting_started_show' ) === 'yes' || get_option( 'wwlc_admin_notice_getting_started_show' ) === false ) ) { 

        $screen = get_current_screen(); 

        // Check if WWS license page
        // Check if products pages
        // Check if woocommerce pages ( wc, products, analytics )
        // Check if plugins page
        if( $screen->id === 'settings_page_wws-license-settings' || $screen->post_type === 'product' || in_array( $screen->parent_base , array( 'woocommerce' , 'plugins' ) ) ) { 
            
            if( $active_counter > 1 ) { ?>

                <div class="updated notice wwlc-getting-started">
                    <p><img src="<?php echo WWLC_IMAGES_ROOT_URL; ?>wholesale-suite-logo.png" alt=""/></p>
                    <p><?php _e( 'Thank you for choosing Wholesale Suite – the most complete wholesale solution for building wholesale sales into your existing WooCommerce driven store.' , 'woocommerce-wholesale-lead-capture' ); ?>
                    <p><?php _e( 'To help you get up and running as quickly and as smoothly as possible, we\'ve published a number of getting started guides for our tools. You\'ll find links to these at any time inside the Help section in the settings for each plugin, but here are the links below so you can read them now.' , 'woocommerce-wholesale-lead-capture' ); ?>
                    <p><?php

                        if( $wwpp_active && $wwpp_notice === 'yes' ) { ?>
                            <a href="<?php echo $wwpp_getting_started_link; ?>" target="_blank">
                                <?php _e( 'Wholesale Prices Premium Guide' , 'woocommerce-wholesale-lead-capture' ); ?>
                                <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                            </a><?php 
                        } else if( $wwp_active && $wwp_notice === 'yes' ) { ?>
                            <a href="<?php echo $wwp_getting_started_link; ?>" target="_blank">
                                <?php _e( 'Wholesale Prices Guide' , 'woocommerce-wholesale-lead-capture' ); ?>
                                <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                            </a><?php 
                        }

                        if( $wwof_active && $wwof_notice === 'yes' ) { ?>
                            <a href="<?php echo $wwof_getting_started_link; ?>" target="_blank">
                                <?php _e( 'Wholesale Order Form Guide' , 'woocommerce-wholesale-lead-capture' ); ?>
                                <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                            </a><?php 
                        } ?>
                        
                        <a href="<?php echo $wwlc_getting_started_link; ?>" target="_blank">
                            <?php _e( 'Wholesale Lead Capture Guide' , 'woocommerce-wholesale-lead-capture' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                        </a>
                        
                    </p>
                    <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php _e( 'Dismiss this notice.' , 'woocommerce-wholesale-lead-capture' ); ?></span></button>
                </div><?php

            } else { ?>

                <div class="updated notice wwlc-getting-started">
                    <p><img src="<?php echo WWLC_IMAGES_ROOT_URL; ?>wholesale-suite-logo.png" alt=""/></p>
                    <p><?php _e( 'Thank you for choosing Lead Capture to managed your wholesale customers.' , 'woocommerce-wholesale-lead-capture' ); ?>
                    <p><?php _e( 'The plugin has already done a lot of the heavy lifting for you and you\'ll find new pages for the wholesale registration and wholesale login forms under your Pages area. We highly recommend reading the getting started guide to help you get up to speed on everything from customizing your registration form to user approval flow.' , 'woocommerce-wholesale-lead-capture' ); ?>
                    <p><a href="<?php echo $wwlc_getting_started_link; ?>" target="_blank">
                        <?php _e( 'Read the Getting Started guide' , 'woocommerce-wholesale-lead-capture' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                    </a></p>
                    <button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php _e( 'Dismiss this notice.' , 'woocommerce-wholesale-lead-capture' ); ?></span></button>
                </div><?php

            }

        }

    }

?>