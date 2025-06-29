<?php if ( ! defined( 'ABSPATH' ) ) {
exit;} // Exit if accessed directly ?>

<div class="field-controls">

    <input type="hidden" id="mapping-index" class="field-control" value="">

    <div class="field-container wrcqbwd-wholesale-roles-field-container">

        <label for="wrcqbwd-wholesale-roles"><?php esc_html_e( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?></label>
        <select id="wrcqbwd-wholesale-roles" class="field-control" data-placeholder="<?php esc_attr_e( 'Choose wholesale role...', 'woocommerce-wholesale-prices-premium' ); ?>" style="width: 400px;">
            <option value=""></option>
            <?php foreach ( $all_wholesale_roles as $wholesaleRoleKey => $wholesaleRole ) { ?>
                <option value="<?php echo esc_attr( $wholesaleRoleKey ); ?>"><?php echo esc_html( $wholesaleRole['roleName'] ); ?></option>
            <?php } ?>
        </select>
        <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php esc_attr_e( 'Select wholesale role to which rule applies.', 'woocommerce-wholesale-prices-premium' ); ?>"></span>

    </div>
    
    <div class="field-container">
                
        <label for="wrcqbwd-starting-qty"><?php esc_html_e( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ); ?></label>
        <input type="number" id="wrcqbwd-starting-qty" class="field-control">
        <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php esc_attr_e( 'Minimum order quantity required for this rule. Must be a number.', 'woocommerce-wholesale-prices-premium' ); ?>"></span>                                

    </div>

    <div class="field-container">
                
        <label for="wrcqbwd-ending-qty"><?php esc_html_e( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ); ?></label>
        <input type="number" id="wrcqbwd-ending-qty" class="field-control">
        <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php esc_attr_e( 'Maximum order quantity required for this rule. Must be a number. Leave this blank for no maximum quantity.', 'woocommerce-wholesale-prices-premium' ); ?>"></span>
        
    </div>

    <div class="field-container">

        <label for="wrcqbwd-percent-discount"><?php esc_html_e( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ); ?></label>
        <input type="number" min="0" step="1" id="wrcqbwd-percent-discount" class="field-control"/>
        <span class="dashicons dashicons-editor-help tooltip right" data-tip="<?php esc_attr_e( 'The new % value off the regular price. This will be the discount value used for quantities within the given range.', 'woocommerce-wholesale-prices-premium' ); ?>"></span>        
        <p class="desc"> <?php esc_html_e( 'New percentage amount off the retail price. Provide value in percent (%), Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.', 'woocommerce-wholesale-prices-premium' ); ?></p>

    </div>

    <div style="clear: both; float: none; display: block;"></div>

</div>

<div class="button-controls add-mode">

    <input type="button" id="wrcqbwd-save-mapping" class="button button-primary" value="<?php esc_attr_e( 'Save Mapping', 'woocommerce-wholesale-prices-premium' ); ?>"/>
    <input type="button" id="wrcqbwd-cancel-edit-mapping" class="button button-secondary" value="<?php esc_attr_e( 'Cancel', 'woocommerce-wholesale-prices-premium' ); ?>"/>
    <input type="button" id="wrcqbwd-add-mapping" class="button button-primary" value="<?php esc_attr_e( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ); ?>"/>
    <span class="spinner"></span>

    <div style="clear: both; float: none; display: block;"></div>

</div>

<table id="wholesale-role-cart-qty-based-wholesale-discount-mapping" class="wp-list-table widefat fixed striped posts">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php esc_html_e( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php esc_html_e( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php esc_html_e( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th></th>
        </tr>
    </thead>

    <tfoot>
        <tr>
            <th><?php esc_html_e( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php esc_html_e( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php esc_html_e( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php esc_html_e( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th></th>
        </tr>
    </tfoot>

    <tbody>
    
        <?php
        if ( $cart_qty_discount_mapping ) {

            foreach ( $cart_qty_discount_mapping as $index => $mapping ) {
                if ( 'rawTable' !== $index ) {
                ?>

                    <tr data-index="<?php echo esc_attr( $index ); ?>">
                        <td class="meta hidden">
                            <span class="index"><?php echo esc_html( $index ); ?></span>
                            <span class="wholesale-role"><?php echo esc_html( $mapping['wholesale_role'] ); ?></span>
                            <span class="wholesale-discount"><?php echo esc_html( $mapping['percent_discount'] ); ?></span>
                        </td>
                        <td class="wholesale_role">
                        <?php
                            if ( isset( $all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'] ) ) {
                                echo esc_html( $all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'] )
                        ?>
                                <span class="role_key" style="display:none;"><?php echo esc_html( $mapping['wholesale_role'] ); ?></span>
                        <?php
                            } else {
                            ?>
                                <?php echo esc_html( $mapping['wholesale_role'] ); ?> role does not exist anymore
                            <?php
                            }
                            ?>
                        </td>
                        <td class="start_qty"><?php echo esc_html( $mapping['start_qty'] ); ?></td>
                        <td class="end_qty"><?php echo esc_html( $mapping['end_qty'] ); ?></td>
                        <td class="percent_discount"><?php echo esc_html( $mapping['percent_discount'] . '%' ); ?></td>
                        <td class="controls">
                            <a class="edit dashicons dashicons-edit"></a>
                            <a class="delete dashicons dashicons-no"></a>
                        </td>

                    </tr>

                <?php
                }
            }
        } else {
        ?>
            <tr class="no-items">
                <td class="colspanchange" colspan="5"><?php esc_html_e( 'No Mappings Found', 'woocommerce-wholesale-prices-premium' ); ?></td>
            </tr>
        <?php } ?>
    
    </tbody>

</table>
