<?php
/**
 * WWPP email footer template.
 *
 * This template can be overridden by copying it to yourtheme/wwpp/emails/email-footer.php.
 *
 * @since 2.0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
                                    <tr>
                                        <td valign="top" class="bottom-content" style="text-align: center;">
                                            <p style="padding-top: 20px;"><strong><?php esc_html_e( 'Discover our Products', 'woocommerce-wholesale-prices-premium' ); ?></strong></p>
                                            <img src="<?php echo esc_url( $our_products_image ); ?>" alt="Our products" />
                                            <p><?php esc_html_e( 'For more information:', 'woocommerce-wholesale-prices-premium' ); ?></p>
                                            <p><?php echo esc_html( $wwp_store_address ); ?><br>
                                                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $store_name ); ?><br>
                                                <?php esc_html_e( 'All rights reserved.', 'woocommerce-wholesale-prices-premium' ); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr class="footer">
                            <td align="center" valign="middle" height="100px">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td width="40%" height="50" align="right" valign="middle">
                                            <span style="padding-right: 10px;"><?php esc_html_e( 'Powered by', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                        </td>
                                        <td width="60%" height="50" align="left" valign="middle">
                                            <a href="<?php echo esc_url( $logo_url ); ?>" target="_blank"><img src="<?php echo esc_url( $footer_image ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> logo" style="padding-left: 10px; width: 175px;" /></a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td><!-- Deliberately empty to support consistent sizing and layout across multiple email clients. --></td>
        </tr>
    </table>
</body>

</html>
