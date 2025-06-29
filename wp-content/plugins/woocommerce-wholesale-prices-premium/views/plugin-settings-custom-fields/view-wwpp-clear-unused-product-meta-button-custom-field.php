<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for=""><?php _e( 'Clear Unused Product Meta' , 'woocommerce-wholesale-prices-premium' ); ?></label>
    </th>
    <td class="forminp forminp-clear_unused_product_meta_button">
        <input type="button" id="clear-unused-product-meta" class="button button-secondary" value="<?php _e( 'Clear Unused Product Meta' , 'woocommerce-wholesale-prices-premium' ); ?>"><span class="spinner"></span>
        <p class="desc"><?php _e( 'Option to clear product meta that isn\'t used. This is a result from deleting wholesale role. This is useful for import/export to have a clean product meta data.' , 'woocommerce-wholesale-prices-premium' ); ?></p>
    </td>
</tr>
