<?php if (!defined('ABSPATH')) {
    exit;
}

$columns = array(
    __('Wholesale Role', 'woocommerce-wholesale-prices-premium'),
    __('Subtotal Price', 'woocommerce-wholesale-prices-premium'),
    __('Discount Type', 'woocommerce-wholesale-prices-premium'),
    __('Discount Amount', 'woocommerce-wholesale-prices-premium'),
    __('Discount Title', 'woocommerce-wholesale-prices-premium'),
);

$type = array(
    'percent-discount' => __('Percent Discount', 'woocommerce-wholesale-prices-premium'),
    'fixed-discount' => __('Fixed Discount', 'woocommerce-wholesale-prices-premium'),
);

// Exit if accessed directly ?>
<tr valign="top" id="wholesale-role-cart-total-based-discount-container">
    <th colspan="2" scope="row" class="titledesc">
        <div class="field-controls">
            <input type="hidden" id="mapping-index" class="field-control" value="">

            <div class="field-container wholesale-roles-field-container">
                <label for="wholesale-roles"><?php echo $columns[0]; ?></label>
                <select id="wholesale-roles" class="field-control" data-placeholder="<?php _e('Choose wholesale role...', 'woocommerce-wholesale-prices-premium');?>" style="width: 400px;">
                    <option value=""></option>
                    <?php foreach ($all_wholesale_roles as $wholesaleRoleKey => $wholesaleRole) {?>
                        <option value="<?php echo $wholesaleRoleKey ?>"><?php echo $wholesaleRole['roleName']; ?></option>
                    <?php }?>
                </select>
                <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php _e('Select wholesale role to which rule applies.', 'woocommerce-wholesale-prices-premium');?>"></span>
            </div>

            <div class="field-container">
                <label for="subtotal-price"><?php _e('Subtotal Price', 'woocommerce-wholesale-prices-premium')?></label>
                <input type="number" id="subtotal-price" class="field-control">
                <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php _e('The cart subtotal price that the discount will start applying at (excluding taxes and shipping). Must be a number.', 'woocommerce-wholesale-prices-premium');?>"></span>
            </div>

            <div class="field-container">
                <label for="discount-type"><?php echo $columns[2]; ?></label>
                <select id="discount-type" class="field-control" data-placeholder="<?php _e('Choose discount type...', 'woocommerce-wholesale-prices-premium');?>" style="width: 400px;">
                    <option value=""></option>
                    <option value="percent-discount"><?php echo $type['percent-discount']; ?></option>
                    <option value="fixed-discount"><?php echo $type['fixed-discount']; ?></option>
                </select>
                <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php _e('The type of discount which the price is calculated.', 'woocommerce-wholesale-prices-premium');?>"></span>
            </div>

            <div class="field-container">
                <label for="discount-amount"><?php echo $columns[3]; ?></label>
                <input type="number" min="0" step="1" id="discount-amount" class="field-control"/>
                <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php _e('Discount based off the cart subtotal price.', 'woocommerce-wholesale-prices-premium');?>"></span>
                <p class="desc"> <?php _e('Discount amount off the cart subtotal price. If discount type is percentage (%), Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.', 'woocommerce-wholesale-prices-premium');?></p>
            </div>

            <div class="field-container">
                <label for="discount-title"><?php echo $columns[4]; ?></label>
                <input type="text" id="discount-title" class="field-control"/>
                <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php _e('A short title to show the user for this discount. Shown on the totals table.', 'woocommerce-wholesale-prices-premium');?>"></span>
            </div>
            <div style="clear: both; float: none; display: block;"></div>
        </div>

        <div class="button-controls add-mode">
            <input type="button" id="save-mapping" class="button button-primary" value="<?php _e('Save Mapping', 'woocommerce-wholesale-prices-premium');?>"/>
            <input type="button" id="cancel-edit-mapping" class="button button-secondary" value="<?php _e('Cancel', 'woocommerce-wholesale-prices-premium');?>"/>
            <input type="button" id="add-mapping" class="button button-primary" value="<?php _e('Add Mapping', 'woocommerce-wholesale-prices-premium');?>"/>
            <span class="spinner"></span>
            <div style="clear: both; float: none; display: block;"></div>
        </div>

        <table id="wholesale-role-cart-total-discount-mapping" class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <th><?php echo $columns[0]; ?></th>
                    <th><?php echo $columns[1]; ?></th>
                    <th><?php echo $columns[2]; ?></th>
                    <th><?php echo $columns[3]; ?></th>
                    <th><?php echo $columns[4]; ?></th>
                    <th></th>
                </tr>
            </thead>

            <tfoot>
                <tr>
                    <th><?php echo $columns[0]; ?></th>
                    <th><?php echo $columns[1]; ?></th>
                    <th><?php echo $columns[2]; ?></th>
                    <th><?php echo $columns[3]; ?></th>
                    <th><?php echo $columns[4]; ?></th>
                    <th></th>
                </tr>
            </tfoot>

            <tbody><?php

                if ($cart_subtotal_price_based_discount_mapping) {
                    
                    foreach ($cart_subtotal_price_based_discount_mapping as $index => $mapping) {

                        $mapping['wholesale_role'] = isset($mapping['wholesale_role']) ? $mapping['wholesale_role'] : '';
                        $mapping['subtotal_price'] = isset($mapping['subtotal_price']) ? $mapping['subtotal_price'] : '';
                        $mapping['discount_type'] = isset($mapping['discount_type']) ? $mapping['discount_type'] : '';
                        $mapping['discount_amount'] = isset($mapping['discount_amount']) ? $mapping['discount_amount'] : '';
                        $mapping['discount_title'] = isset($mapping['discount_title']) ? $mapping['discount_title'] : '';?>

                        <tr data-index="<?php echo $index; ?>">
                            <td class="meta hidden">
                                <span class="index"><?php echo $index; ?></span>
                                <span class="wholesale-role"><?php echo $mapping['wholesale_role']; ?></span>
                                <span class="wholesale-discount"><?php //echo $mapping['percent_discount']; ?></span>
                            </td>
                            <td class="wholesale_role"><?php
                                if (isset($all_wholesale_roles[$mapping['wholesale_role']]['roleName'])) {
                                    echo $all_wholesale_roles[$mapping['wholesale_role']]['roleName'];
                                    echo '<span class="role_key" style="display:none;">' . $mapping['wholesale_role'] . '</span>';
                                } else {
                                    echo sprintf(__('%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium'), $mapping['wholesale_role']);
                            }?>
                                </td>
                                <td class="subtotal_price"><?php echo $mapping['subtotal_price']; ?></td>
                                <td class="discount_type"><?php echo $type[$mapping['discount_type']]; ?></td>
                                <td class="discount_amount"><?php echo $mapping['discount_type'] == 'percent-discount' ? $mapping['discount_amount'] . '%' : $mapping['discount_amount']; ?></td>
                                <td class="discount_title"><?php echo $mapping['discount_title']; ?></td>
                                <td class="controls">
                                    <a class="edit dashicons dashicons-edit"></a>
                                    <a class="delete dashicons dashicons-no"></a>
                            </td>

                        </tr><?php

                    }

                } else { ?>

                    <tr class="no-items">
                        <td class="colspanchange" colspan="6"><?php _e('No Mappings Found', 'woocommerce-wholesale-prices-premium');?></td>
                    </tr><?php

                } ?>

            </tbody>
        </table>
    </th>
</tr>