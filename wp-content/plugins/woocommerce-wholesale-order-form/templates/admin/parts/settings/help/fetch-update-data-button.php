<?php
/**
 * Author: Rymera Web Co
 *
 * @since 1.19.4
 */

defined( 'ABSPATH' ) || exit;
?>

<tr style="vertical-align: top;">
	<th scope="row" class="titledesc">
		<label for=""><?php esc_html_e( 'Refetch Plugin Update Data', 'woocommerce-wholesale-order-form' ); ?></label>
	</th>
	<td class="forminp forminp-force_fetch_update_data_button">
		<input
			type="button" id="wwof-force-fetch-update-data" class="button button-secondary"
			value="<?php esc_attr_e( 'Refetch Plugin Update Data', 'woocommerce-wholesale-order-form' ); ?>"
			data-confirm="
			<?php
            esc_attr_e(
                'Are you sure you want to refetch plugin update data?',
                'woocommerce-wholesale-order-form'
            );
            ?>
"
		><span class="spinner" style="float: none;"></span>
		<p class="desc">
            <?php
            esc_html_e(
                'This will refetch the plugin update data. Useful for debugging failed plugin update operations.',
                'woocommerce-wholesale-order-form'
            );
            ?>
		</p>
	</td>
</tr>
