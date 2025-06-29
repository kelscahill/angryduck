<?php
/**
 * Help resources template part in the Help sub-tab section.
 *
 * @package RymeraWebCo\WWOF
 */

defined( 'ABSPATH' ) || exit;
?>
<tr style="vertical-align: top;">
	<th scope="row" class="titledesc">
		<label for="">
            <?php
            esc_html_e( 'Knowledge Base', 'woocommerce-wholesale-order-form' );
            ?>
		</label>
	</th>
	<td class="forminp forminp-wwof_help_resources">
        <?php
        esc_html_e( 'Looking for documentation? Please see our growing', 'woocommerce-wholesale-order-form' );
        ?>
		&nbsp;
		<a
			href="https://wholesalesuiteplugin.com/knowledge-base/?utm_source=Order%20Form%20Plugin&amp;utm_medium=Settings&amp;utm_campaign=Knowledge%20Base%20"
			target="_blank"
		><?php echo esc_html__( 'Knowledge Base', 'woocommerce-wholesale-order-form' ); ?></a>.
	</td>
</tr>
