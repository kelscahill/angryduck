<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<tr valign="top">
    <th colspan="2" scope="row" class="titledesc">
        <div id="shipping-method-controls" class="shipping-method-controls">

            <input type="hidden" id="index" value="">

            <div class="field-container">

                <label for="wholesale-roles"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                <select id="wholesale-roles" class="chosen_select" autocomplete="off">
                    <option value=""><?php _e( '--Select Wholesale Role--' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                    <?php foreach ( $all_wholesale_roles as $role_key => $role_data ) { ?>
                        <option value="<?php echo $role_key ?>"><?php echo $role_data[ 'roleName' ]; ?></option>
                    <?php } ?>
                </select>

            </div>

            <div class="field-container">

                <label for="use-non-zoned-shipping-methods">
                    <input type="checkbox" id="use-non-zoned-shipping-methods" autocomplete="off">
                    <?php _e( 'Use Non-Zoned Shipping Methods' , 'woocommerce-wholesale-prices-premium' ); ?>
                </label>

            </div>

            <div class="non-zoned-method-controls">

                <div class="field-container">

                    <label for="non-zoned-shipping-methods"><?php _e( 'Non-Zoned Shipping Zone Methods' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="non-zoned-shipping-methods" class="chosen_select" autocomplete="off">
                        <option value=""><?php _e( '--Select Shipping Method--' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                        <?php foreach ( $non_zoned_shipping_methods as $sm_key => $sm ) { ?>
                            <option value="<?php echo $sm_key; ?>"><?php echo $sm->method_title; ?></option>
                        <?php } ?>
                    </select>

                </div>

            </div>

            <div class="zone-method-controls">

                <div class="field-container">

                    <label for="shipping-zones"><?php _e( 'Shipping Zones' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="shipping-zones" class="chosen_select" autocomplete="off">
                        <option value=""><?php _e( '--Select Shipping Zone--' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                        <option value="<?php echo $wc_default_zone->get_id(); ?>"><?php echo $wc_default_zone->get_zone_name(); ?></option>
                        <?php foreach ( $wc_shipping_zones as $zone ) { ?>
                            <option value="<?php echo $zone[ 'zone_id' ]; ?>"><?php echo $zone[ 'zone_name' ]; ?></option>
                        <?php } ?>
                    </select>

                </div>

                <div class="field-container">

                    <label for="shipping-zone-methods"><?php _e( 'Shipping Zone Methods' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="shipping-zone-methods" class="chosen_select" autocomplete="off">
                        <option value=""><?php _e( '--Select Shipping Zone Method--' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                    </select>

                </div>

            </div>

            <div style="clear: both; float: none; display: block;"></div>

        </div>

        <div class="button-controls add-mode">

            <input type="button" id="cancel-edit-mapping" class="button button-secondary" value="<?php _e( 'Cancel' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <input type="button" id="edit-mapping" class="button button-primary" value="<?php _e( 'Save Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <input type="button" id="add-mapping" class="button button-primary" value="<?php _e( 'Add Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <span class="spinner"></span>

            <div style="clear: both; float: none; display: block;"></div>

        </div>

        <table id="wholesale-role-shipping-method-mapping" class="wp-list-table widefat fixed striped">

            <thead>

                <tr>
                    <th class="wholesale-role-heading"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="shipping-zone-heading"><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="shipping-method-heading"><?php _e( 'Shipping Method' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="non-zoned-method-heading"><?php _e( 'Non-Zoned' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="controls-heading"></th>
                </tr>

            </thead>

            <tfoot>

                <tr>
                    <th class="wholesale-role-heading"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="shipping-zone-heading"><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="shipping-method-heading"><?php _e( 'Shipping Method' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="non-zoned-method-heading"><?php _e( 'Non-Zoned' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th class="controls-heading"></th>
                </tr>

            </tfoot>

            <tbody>

                <?php if ( !empty( $wholesale_zone_mapping ) ) {
                    $non_zoned = array(
                        'yes' => __( 'Yes' , 'woocommerce-wholesale-prices-premium' ),
                        'no' => __( 'No' , 'woocommerce-wholesale-prices-premium' ),
                    );
                    foreach ( $wholesale_zone_mapping as $idx => $mapping ) {

                        // Wholesale role text
                        if ( array_key_exists( $mapping[ 'wholesale_role' ] , $all_wholesale_roles ) )
                            $wholesale_role_text = $all_wholesale_roles[ $mapping[ 'wholesale_role' ] ][ 'roleName' ];
                        else
                            $wholesale_role_text = sprintf( __( '%1$s role does not exist anymore' , 'woocommerce-wholesale-prices-premium' ) , $mapping[ 'wholesale_role' ] );

                        if ( $mapping[ 'use_non_zoned_shipping_method' ] == 'yes' ) {

                            // Non-Zoned Shipping Methods

                            if ( array_key_exists( $mapping[ 'non_zoned_shipping_method' ] , $non_zoned_shipping_methods ) )
                                $curr_map_shipping_method_text = $non_zoned_shipping_methods[ $mapping[ 'non_zoned_shipping_method' ] ]->method_title;
                            else
                                $curr_map_shipping_method_text = sprintf( __( 'Non-zoned shipping method with id of %1$s does not exist anymore' , 'woocommerce-wholesale-prices-premium' ) , $mapping[ 'non_zoned_shipping_method' ] );

                        } else {

                            // Zoned Shipping Methods

                            // Shipping zone text
                            $curr_map_shipping_zone    = WC_Shipping_Zones::get_zone( ( int ) $mapping[ 'shipping_zone' ] );
                            $curr_map_shipping_methods = array();

                            if ( $curr_map_shipping_zone && $curr_map_shipping_zone instanceof WC_Shipping_Zone ) {

                                $curr_map_shipping_zone_text = $curr_map_shipping_zone->get_zone_name();
                                $curr_map_shipping_methods   = $curr_map_shipping_zone->get_shipping_methods();

                            } else
                                $curr_map_shipping_zone_text = sprintf( __( 'Shipping zone with id of %1$s does not exist anymore' , 'woocommerce-wholesale-prices-premium' ) , $mapping[ 'shipping_zone' ] );

                            // Shipping method text
                            if ( array_key_exists( $mapping[ 'shipping_method' ] , $curr_map_shipping_methods ) ) {

                                $curr_map_shipping_method      = $curr_map_shipping_methods[ $mapping[ 'shipping_method' ] ];
                                $curr_map_shipping_method_text = $curr_map_shipping_method->title;

                            } else
                                $curr_map_shipping_method_text = sprintf( __( 'Shipping method with instance id of %1$s does not exist anymore' , 'woocommerce-wholesale-prices-premium' ) , $mapping[ 'shipping_method' ] );

                        } ?>

                        <tr>
                            <td class="meta hidden">
                                <span class="index"><?php echo $idx; ?></span>
                                <span class="wholesale-role"><?php echo $mapping[ 'wholesale_role' ]; ?></span>
                                <span class="use-non-zoned-shipping-method"><?php echo $mapping[ 'use_non_zoned_shipping_method' ]; ?></span>

                                <?php if ( $mapping[ 'use_non_zoned_shipping_method' ] == 'yes' ) { ?>

                                    <span class="non-zoned-shipping-method"><?php echo $mapping[ 'non_zoned_shipping_method' ]; ?></span>

                                <?php } else { ?>

                                    <span class="shipping-zone"><?php echo $mapping[ 'shipping_zone' ]; ?></span>
                                    <span class="shipping-method"><?php echo $mapping[ 'shipping_method' ]; ?></span>

                                <?php } ?>

                            </td>
                            <td class="wholesale-role-text"><?php echo $wholesale_role_text; ?></td>

                            <?php if ( $mapping[ 'use_non_zoned_shipping_method' ] == 'yes' ) { ?>

                                <td class="shipping-zone-text"></td>
                                <td class="shipping-method-text"><?php echo $curr_map_shipping_method_text; ?></td>

                            <?php } else { ?>

                                <td class="shipping-zone-text"><?php echo $curr_map_shipping_zone_text; ?></td>
                                <td class="shipping-method-text"><?php echo $curr_map_shipping_method_text; ?></td>

                            <?php } ?>

                            <td class="non-zoned-method-text">
                                <?php echo $non_zoned[$mapping[ 'use_non_zoned_shipping_method' ]]; ?>
                            </td>

                            <td class="controls">
                                <span class="dashicons dashicons-edit edit-mapping"></span>
                                <span class="dashicons dashicons-no delete-mapping"></span>
                            </td>
                        </tr>

                    <?php }

                    } else { ?>

                        <tr class="no-items">
                            <td class="colspanchange" colspan="5"><?php _e( 'No Mappings Found' , 'woocommerce-wholesale-prices-premium' ); ?></td>
                        </tr>

                <?php } ?>

            </tbody>

        </table>

    </th>
</tr>
