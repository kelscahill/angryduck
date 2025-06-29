<script>
jQuery(document).ready(function($) {

    $("#wholesale-visibility-select").chosen({
        width: '100%'
    });

});
</script>
<div id="wholesale-visiblity" class="misc-pub-section">

    <strong><?php _e('Restrict To Wholesale Roles:', 'woocommerce-wholesale-prices-premium');?></strong>
    <p><i><?php _e('Set this product to be visible only to specified wholesale user role/s only', 'woocommerce-wholesale-prices-premium');?></i>
    </p>

    <div id="wholesale-visibility-select-container" style="display: flex;">

        <select style="width: 100%;"
            data-placeholder="<?php _e('Choose wholesale users...', 'woocommerce-wholesale-prices-premium');?>"
            name="wholesale-visibility-select[]" id="wholesale-visibility-select" multiple>

            <?php foreach ($all_registered_wholesale_roles as $role_key => $role) {?>
            <option value="<?php echo $role_key ?>" <?php if (in_array($role_key, $product_wholesale_role_filter)) {echo "selected";

}
    ?>>
                <?php echo $role['roleName']; ?></option>
            <?php }?>

        </select>

        <?php wp_nonce_field('wwpp_action_save_product_wholesale_role_visibility_filter', 'wwpp_nonce_save_product_wholesale_role_visibility_filter');?>

    </div>

</div>

<div id="wholesale-pricing-options" class="misc-pub-section">

    <strong><?php _e('Wholesale Pricing Options', 'woocommerce-wholesale-prices-premium');?></strong>

    <label for="void-cat-level-wholesale-discount">
        <input type="checkbox" name="void-cat-level-wholesale-discount" id="void-cat-level-wholesale-discount"
            <?php echo $ignore_cat_level_wp === "yes" ? "checked" : ""; ?> value="yes">
        <?php _e('Disregard Product Category Level Wholesale Discount', 'woocommerce-wholesale-prices-premium');?>
        <span class="dashicons dashicons-editor-help tooltip top"
            data-tip="<?php _e("When checked, it will ignore wholesale pricing set on this product's categories.", 'woocommerce-wholesale-prices-premium');?>"></span>
    </label>

    <label for="void-wholesale-role-level-wholesale-discount">
        <input type="checkbox" name="void-wholesale-role-level-wholesale-discount"
            id="void-wholesale-role-level-wholesale-discount"
            <?php echo $ignore_role_level_wp === "yes" ? "checked" : ""; ?> value="yes">
        <?php _e('Disregard Wholesale Role Level Wholesale Discount', 'woocommerce-wholesale-prices-premium');?>
        <span class="dashicons dashicons-editor-help tooltip top"
            data-tip="<?php _e("When checked, it will ignore wholesale pricing set on the per wholesale role/user level.", 'woocommerce-wholesale-prices-premium');?>"></span>
    </label>

    <?php wp_nonce_field('wwpp_action_save_product_wholesale_price_options', 'wwpp_nonce_save_product_wholesale_price_options');?>

</div>