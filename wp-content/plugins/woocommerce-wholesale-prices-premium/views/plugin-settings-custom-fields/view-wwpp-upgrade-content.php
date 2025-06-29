<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

$wwof_active = WWPP_Helper_Functions::is_wwof_active();
$wwlc_active = WWPP_Helper_Functions::is_wwlc_active(); 

?>

<tr>
    <td>
        <div class="wholesale-suite-bundle">
        <img src="<?php echo WWP_IMAGES_URL ?>wholesale-suite-activation-notice-logo.png" alt="<?php _e( 'WooCommerce Wholesale Prices Premium' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <h1><?php _e( 'Other Plugins In The Wholesale Suite Family', 'woocommerce-wholesale-prices-premium' ); ?></h1>
            <p class="sub"><?php _e( 'Everything you need to sell to wholesale customers in WooCommerce.<br/>
            Use these other products in the Wholesale Suite family to enhance your wholesale customer\'s experience.', 'woocommerce-wholesale-prices-premium' ); ?></p>
            <br/><br/>
            <div class="products active-plugin">
                <div class="row">
                    <div class="column">
                        <h2><?php _e( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-prices-premium' ); ?></h2>
                        <p><?php _e( 'Easily add wholesale pricing to your products. Control product visibility. Satisfy your country\'s strictest tax requirements & control
                            pricing display. Force wholesalers to use certain shipping & payment gateways. Enforce order minimums and individual product minimums. And 100\'s
                            of other product and pricing related wholesale features.', 'woocommerce-wholesale-prices-premium' ); ?>
                        </p>
                        <p><b><i><span class="dashicons dashicons-yes-alt"></span><?php _e( 'Installed', 'woocommerce-wholesale-prices-premium' ); ?></i></b></p>
                    </div>
                    <div class="column">
                        <img src="<?php echo WWP_IMAGES_URL ?>upgrade-page-wwpp-box.png" alt="<?php _e( 'WooCommerce Wholesale Prices Premium' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
                    </div>
                </div>
            </div >
            <div class="<?php echo $wwof_active ? 'products active-plugin' : 'products'; ?>">
                <div class="row">
                    <div class="column">
                        <h2><?php _e( 'WooCommerce Wholesale Order Form', 'woocommerce-wholesale-prices-premium' ); ?></h2>
                        <p><?php _e( 'Decrease frustration and increase order size with the most efficient one-page WooCommerce order form.
                            Your wholesale customers will love it. No page loading means less back & forth, full ajax enabled add to cart buttons,
                            responsive layout for on-the-go ordering and your whole product catalog available at your customer\'s fingertips.', 'woocommerce-wholesale-prices-premium' ); ?>
                        </p>
                        <p>
                            <?php if( $wwof_active ) { ?>
                                <b><i><span class="dashicons dashicons-yes-alt"></span><?php _e( 'Installed', 'woocommerce-wholesale-prices-premium' ); ?></i></b>
                            <?php } else { ?>
                                <a href="https://wholesalesuiteplugin.com/woocommerce-wholesale-order-form/?utm_source=freeplugin&utm_medium=upsell&utm_campaign=upgradepagewwoflearnmore" target="_blank" class="see-product">
                                    <?php _e( 'Learn about Order Form', 'woocommerce-wholesale-prices-premium' ); ?>
                                    <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                                </a>
                            <?php }?>
                        </p>
                    </div>
                    <div class="column">
                        <img src="<?php echo WWP_IMAGES_URL ?>upgrade-page-wwof-box.png" alt="<?php _e( 'WooCommerce Wholesale Prices Premium' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
                    </div>
                </div>
            </div>
            <div class="<?php echo $wwlc_active ? 'products active-plugin' : 'products'; ?>">
                <div class="row">
                    <div class="column">
                        <h2><?php _e( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-prices-premium' ); ?></h2>
                        <p><?php _e( 'Take the pain out of manually recruiting & registering wholesale customers. Lead Capture will save you admin time and recruit wholesale customers
                            for your WooCommerce store on autopilot. Full registration form builder, automated email onboarding email sequence, full automated or manual approvals
                            system and much more.', 'woocommerce-wholesale-prices-premium' ); ?>
                        </p>
                        <p>
                            <?php if( $wwlc_active ) { ?>
                                <b><i><span class="dashicons dashicons-yes-alt"></span><?php _e( 'Installed', 'woocommerce-wholesale-prices-premium' ); ?></i></b>
                            <?php } else { ?>
                            <a href="https://wholesalesuiteplugin.com/woocommerce-wholesale-lead-capture/?utm_source=freeplugin&utm_medium=upsell&utm_campaign=upgradepagewwlclearnmore" target="_blank" class="see-product">
                                <?php _e( 'Learn about Lead Capture', 'woocommerce-wholesale-prices-premium' ); ?>
                                <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 5px"></span>
                            </a>
                            <?php } ?>
                        </p>
                    </div>
                    <div class="column">
                    <img src="<?php echo WWP_IMAGES_URL ?>upgrade-page-wwlc-box.png" alt="<?php _e( 'WooCommerce Wholesale Prices Premium' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
                    </div>
                </div>
            </div>
        </div>
    </td>
</tr>