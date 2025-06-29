<?php if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly ?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for=""><?php _e('Clear variable product price range and wholesale price cache', 'woocommerce-wholesale-prices-premium');?></label>
    </th>
    <td class="forminp">
        <input type="button" id="clear-var-prod-price-range-cache" class="button button-secondary" value="<?php _e('Clear Cache', 'woocommerce-wholesale-prices-premium');?>">
        <span class="spinner" style="float: none; display: inline-block; visibility: hidden;"></span>
        <p class="desc"><?php _e('Clear all cached wholesale variable product price ranges and the wholesale prices. Note: the cache system keeps itself up to date, so only do this if you are experiencing price range problems.', 'woocommerce-wholesale-prices-premium');?></p>
    </td>
</tr>
