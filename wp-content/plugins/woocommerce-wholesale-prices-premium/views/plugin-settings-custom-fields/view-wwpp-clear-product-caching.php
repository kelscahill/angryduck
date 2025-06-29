<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for=""><?php _e( 'Clear product ID cache' , 'woocommerce-wholesale-prices-premium' ); ?></label>
    </th>
    <td class="forminp">
        <input type="button" name="wwpp_clear_product_caching" id="wwpp_clear_product_caching" class="button button-secondary" value="<?php _e( 'Clear Cache' , 'woocommerce-wholesale-prices-premium' ); ?>">
        <span class="spinner" style="float: none; display: inline-block; visibility: hidden;"></span>
        <p class="desc"><?php _e( 'Clear all product ID caches for each role. Caches are automatically rebuilt when visiting the shop page or other product listings.' , 'woocommerce-wholesale-prices-premium' ); ?></p>
    </td>
</tr>
