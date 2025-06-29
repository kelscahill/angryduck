<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<div id="wholesale-role-tax-class-options-field-controls">

    <div class="field-container wwpp-wholesale-roles-field-container">

        <label for="wwpp-wholesale-roles"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></label>
        <select id="wwpp-wholesale-roles" data-placeholder="<?php _e( 'Choose wholesale role...' , 'woocommerce-wholesale-prices-premium' ); ?>" autocomplete="off">

            <option value=""></option>
            <?php foreach ( $all_wholesale_roles as $wholesale_role_key => $wholesale_role ) { ?>
                <option value="<?php echo $wholesale_role_key ?>"><?php echo $wholesale_role[ 'roleName' ]; ?></option>
            <?php } ?>
            
        </select>

    </div>
    
    <div class="field-container wwpp-tax-class-field-container">
        
        <label for="wwpp-tax-classes"><?php _e( 'Wholesale Tax Class' , 'woocommerce-wholesale-prices-premium' ); ?></label>

        <select id="wwpp-tax-classes" data-placeholder="<?php _e( 'Choose tax class...' , 'woocommerce-wholesale-prices-premium' ); ?>" autocomplete="off">
            
            <option value=""></option>
            <?php foreach ( $processed_tax_classes as $key => $tax_class ) { ?>
                <option value="<?php echo $key; ?>"><?php echo $tax_class; ?></option>
            <?php } ?>

        </select>

    </div>

    <div style="clear: both; float: none; display: block;"></div>

</div>

<div id="wholesale-role-tax-class-options-button-controls" class="add-mode">

    <input type="button" id="cancel-edit-mapping" class="button button-secondary" value="<?php _e( 'Cancel' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
    <input type="button" id="save-mapping" class="button button-primary" value="<?php _e( 'Save Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
    <input type="button" id="add-mapping" class="button button-primary" value="<?php _e( 'Add Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
    <span class="spinner"></span>

    <div style="clear: both; float: none; display: block;"></div>

</div>

<table id="wholesale-role-tax-class-options-mapping" class="wp-list-table widefat fixed striped posts">
    
    <thead>
        <tr>
            <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php _e( 'Tax Class' , 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th></th>
        </tr>
    </thead>

    <tfoot>
        <tr>
            <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th><?php _e( 'Tax Class' , 'woocommerce-wholesale-prices-premium' ); ?></th>
            <th></th>
        </tr>
    </tfoot>

    <tbody>
    
        <?php if ( !empty( $wholesale_role_tax_class_options ) ) {

            foreach( $wholesale_role_tax_class_options as $wholesale_role_key => $mapping ) { ?>

                <tr>

                    <td class="meta hidden">
                        <span class="wholesale-role"><?php echo $wholesale_role_key; ?></span>
                        <ul class="tax-class"><?php echo $mapping[ 'tax-class' ]; ?></ul>
                    </td>
                    <td class="wholesale-role-name"><?php
                        if ( isset( $all_wholesale_roles[ $wholesale_role_key ][ 'roleName' ] ) )
                            echo $all_wholesale_roles[ $wholesale_role_key ][ 'roleName' ];
                        else
                            echo sprintf( __( '%1$s role does not exist anymore' , 'woocommerce-wholesale-prices-premium' ) , $wholesale_role_key ); ?>
                    </td>
                    <td class="tax-class-name"><ul><?php echo $mapping[ 'tax-class-name' ]; ?></ul></td>
                    <td class="controls">
                        <a class="edit dashicons dashicons-edit"></a>
                        <a class="delete dashicons dashicons-no"></a>
                    </td>

                </tr>

            <?php }

        } else { ?>

            <tr class="no-items">
                <td class="colspanchange" colspan="3"><?php _e( 'No Mappings Found' , 'woocommerce-wholesale-prices-premium' ); ?></td>
            </tr>

        <?php } ?>
        
    </tbody>

</table>