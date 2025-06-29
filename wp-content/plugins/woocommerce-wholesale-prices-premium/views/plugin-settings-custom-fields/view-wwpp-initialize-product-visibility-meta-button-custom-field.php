<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for=""><?php _e( 'Product Visibility Meta' , 'woocommerce-wholesale-prices-premium' ); ?></label>
    </th>
    <td class="forminp forminp-initialize_product_visibility_meta_button">
        <input type="button" id="initialize-product-visibility-meta" class="button button-secondary" value="<?php _e( 'Re-Initialize Product Visibility Meta' , 'woocommerce-wholesale-prices-premium' ); ?>"><span class="spinner"></span>
        <p class="desc"><?php _e( 'Re-initialize the product visibility meta data for all simple and variable products in the system. Sometimes after product importing or manual database manipulation, the visibility meta used to determine the visibility of your products to wholesalers will be malformed. This button resets all the product visibility meta data so your product visibility options are properly respected.' , 'woocommerce-wholesale-prices-premium' ); ?></p>
    </td>
</tr>
