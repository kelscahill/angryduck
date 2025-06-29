<?php
/**
 *  WooCommerce Wholesale Lead Capture email footer.
 *
 * @version 1.17.4
 */
defined( 'ABSPATH' ) || exit;
?>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                            <!-- End Content -->
                                                        </td>
                                                    </tr>
                                                </table>
                                                <!-- End Body -->
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" valign="top">
                                    <!-- Footer -->
                                    <table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer">
                                        <tr>
                                            <td valign="top">
                                                <table border="0" cellpadding="10" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td colspan="2" valign="middle" id="credit">
                                                            <?php if ( ! apply_filters( 'wwlc_use_woocommerce_email_footer', false ) ) : ?>
                                                                <a style="text-decoration: none; color: #808080" href="https://wholesalesuiteplugin.com/powered-by/?utm_source=wwlc&utm_medium=email&utm_campaign=WWLCPoweredByEmailLink" target="_blank" rel="nofollow">
                                                                    <span style="font-size: 0.7em"><?php esc_html_e( 'Powered by', 'woocommerce-wholesale-lead-capture' ); ?></span>
                                                                    <img style="width: 90px; margin-left: 2px;" src="<?php echo esc_url( WWLC_IMAGES_ROOT_URL . 'wholesale-suite-logo.png' ); ?>" alt="Wholesale Suite" />
                                                                </a>
                                                            <?php else : ?>
                                                                <?php echo wp_kses_post( wpautop( wptexturize( apply_filters( 'wwlc_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) ); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <!-- End Footer -->
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
