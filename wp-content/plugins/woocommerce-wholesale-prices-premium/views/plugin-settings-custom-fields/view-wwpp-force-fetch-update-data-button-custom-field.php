<?php if (!defined('ABSPATH')) {
    exit;
}
; // Exit if accessed directly ?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for=""><?php _e('Refetch Plugin Update Data', 'woocommerce-wholesale-prices-premium');?></label>
    </th>
    <td class="forminp forminp-force_fetch_update_data_button">
        <input type="button" id="force-fetch-update-data" class="button button-secondary" value="<?php _e('Refetch Plugin Update Data', 'woocommerce-wholesale-prices-premium');?>" data-confirm="<?php _e( 'Are you sure you want to refetch plugin update data?', 'woocommerce-wholesale-prices-premium' ); ?>"><span class="spinner"></span>
        <p class="desc"><?php _e('This will refetch the plugin update data. Useful for debugging failed plugin update operations.', 'woocommerce-wholesale-prices-premium');?></p>
    </td>
</tr>
