<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<tr valign="top">
    <th colspan="2" scope="row" class="titledesc">
        <div id="wholesale-role-tax-options">

            <div id="wholesale-role-tax-options-field-controls">

                <div class="field-container wwpp-wholesale-roles-field-container">

                    <label for="wwpp-wholesale-roles"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-wholesale-roles" data-placeholder="<?php _e( 'Choose wholesale role...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <option value=""></option>
                        <?php foreach ( $all_wholesale_roles as $wholesale_role_key => $wholesale_role ) { ?>
                            <option value="<?php echo $wholesale_role_key ?>"><?php echo $wholesale_role[ 'roleName' ]; ?></option>
                        <?php } ?>
                    </select>

                </div>

                <div class="field-container wwpp-tax-exempt-wholesale-role-field-container">

                    <label for="wwpp-tax-exempt-wholesale-role"><?php _e( 'Tax Exempted?' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-tax-exempt-wholesale-role">
                        <option value="yes"><?php _e( 'Yes' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                        <option value="no"><?php _e( 'No' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                    </select>

                </div>

                <div style="clear: both; float: none; display: block;"></div>

            </div>

            <div class="wholesale-role-tax-options-button-controls add-mode">

                <input type="button" id="cancel-edit-mapping" class="button button-secondary" value="<?php _e( 'Cancel' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
                <input type="button" id="save-mapping" class="button button-primary" value="<?php _e( 'Save Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
                <input type="button" id="add-mapping" class="button button-primary" value="<?php _e( 'Add Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
                <span class="spinner"></span>

                <div style="clear: both; float: none; display: block;"></div>

            </div>

            <table id="wholesale-role-tax-options-mapping" class="wp-list-table widefat">
                <thead>
                <tr>
                    <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th><?php _e( 'Tax Exempted' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th></th>
                </tr>
                </thead>

                <tfoot>
                <tr>
                    <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th><?php _e( 'Tax Exempted' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th></th>
                </tr>
                </tfoot>

                <?php if ( $wholesale_role_tax_options ) {

                    $itemNumber =   0;

                    foreach( $wholesale_role_tax_options as $wholesale_role => $tax_options ) {
                        $itemNumber++;

                        if ( $itemNumber % 2 == 0 ) { // even  ?>
                            <tr class="even">
                        <?php } else { // odd ?>
                            <tr class="odd alternate">
                        <?php } ?>

                        <td class="meta hidden">
                            <span class="wholesale-role"><?php echo $wholesale_role; ?></span>
                            <span class="tax-exempted"><?php echo $tax_options[ 'tax_exempted' ]; ?></span>
                        </td>
                        <td class="wholesale-role-name"><?php
                            if ( isset( $all_wholesale_roles[ $wholesale_role ][ 'roleName' ] ) )
                                echo $all_wholesale_roles[ $wholesale_role ][ 'roleName' ];
                            else
                                echo sprintf( __( '%1$s role does not exist anymore' , 'woocommerce-wholesale-prices-premium' ) , $wholesale_role ); ?>
                        </td>
                        <td class="tax-exempted-text"><?php echo ( $tax_options[ 'tax_exempted' ] == 'yes' ) ? __( 'Yes' , 'woocommerce-wholesale-prices-premium' ) : __( 'No' , 'woocommerce-wholesale-prices-premium' ); ?></td>
                        <td class="controls">
                            <a class="edit dashicons dashicons-edit"></a>
                            <a class="delete dashicons dashicons-no"></a>
                        </td>

                        </tr>
                        <?php
                    }

                } else { ?>

                    <tr class="no-items">
                        <td class="colspanchange" colspan="3"><?php _e( 'No Mappings Found' , 'woocommerce-wholesale-prices-premium' ); ?></td>
                    </tr>

                <?php } ?>

            </table>

        </div><!--#wholesale-role-tax-options-->
    </th>
</tr>
