<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<tr valign="top">
    <th colspan="2" scope="row" class="titledesc">
        <div class="shipping-method-controls">

            <input type="hidden" id="table-rate-sipping-type" value="<?php echo ( $table_rate_shipping_type ) ? $table_rate_shipping_type : "" ; ?>">

            <input type="hidden" id="index" value="">

            <div class="field-container">

                <label for="wwpp-wholesale-roles"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                <select id="wwpp-wholesale-roles" data-placeholder="<?php _e( 'Choose wholesale role...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                    <option value=""></option>
                    <?php foreach ( $all_wholesale_roles as $wholesaleRoleKey => $wholesaleRole ) { ?>
                        <option value="<?php echo $wholesaleRoleKey ?>"><?php echo $wholesaleRole[ 'roleName' ]; ?></option>
                    <?php } ?>
                </select>

            </div>

            <div class="field-container">

                <label for="wwpp-shipping-methods"><?php _e( 'Shipping Method' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                <select id="wwpp-shipping-methods" data-placeholder="<?php _e( 'Choose shipping method...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                    <option value=""></option>
                    <?php foreach ( $wc_shipping_methods as $shippingMethodKey => $shippingMethod ) { ?>
                        <option value="<?php echo $shippingMethodKey; ?>"><?php echo $shippingMethod->title; ?></option>
                    <?php } ?>
                </select>

            </div>

            <?php if ( $table_rate_shipping_type == 'woo_themes' ) { ?>

                <div class="field-container shipping-zones-container wwpp-hidden">

                    <label for="wwpp-shipping-zones"><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-shipping-zones" data-placeholder="<?php _e( 'Choose shipping zone...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <option value=""></option>
                        <!--Dynamically Populated-->
                    </select>

                </div>

                <div class="field-container shipping-zone-methods-container wwpp-hidden">

                    <label for="wwpp-shipping-zone-methods"><?php _e( 'Shipping Zone Method' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-shipping-zone-methods" data-placeholder="<?php _e( 'Choose shipping zone method...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <option value=""></option>
                        <!--Dynamically Populated-->
                    </select>

                </div>

            <?php } elseif ( $table_rate_shipping_type == 'code_canyon' ) { ?>

                <div class="field-container cc-shipping-zones-container wwpp-hidden">

                    <label for="wwpp-cc-shipping-zones"><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-cc-shipping-zones" data-placeholder="<?php _e( 'Choose shipping zone...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <option value=""></option>
                        <?php foreach ( $cc_shipping_zones as $zone ) { ?>
                            <option value="<?php echo $zone[ 'zone_id' ]; ?>"><?php echo $zone[ 'zone_title' ]; ?></option>
                        <?php } ?>
                    </select>

                </div>
                
                <div class="field-container cc-shipping-zone-table-rates-container wwpp-hidden">

                    <label for="wwpp-cc-shipping-zone-table-rates"><?php _e( 'Shipping Zone Table Rate' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-cc-shipping-zone-table-rates" data-placeholder="<?php _e( 'Choose shipping zone table rate...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <option value=""></option>
                        <!--Dynamically Populated-->
                    </select>

                </div>
                
            <?php } elseif ( $table_rate_shipping_type == 'mango_hour' ) { ?>

                <div class="field-container mh-shipping-zones-container wwpp-hidden">
                    <label for="wwpp-mh-shipping-zones"><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-mh-shipping-zones" data-placeholder="<?php _e( 'Choose shipping zone...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <option value="0"><?php _e( 'Default Zone (everywhere else)' , 'woocommerce-wholesale-prices-premium' ); ?></option>
                        <?php foreach ( $mh_shipping_zones as $zone ) { ?>
                            <option value="<?php echo $zone[ 'id' ]; ?>"><?php echo $zone[ 'name' ]; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field-container mh-shipping-services-container wwpp-hidden">
                    <label for="wwpp-mh-shipping-services"><?php _e( 'Shipping Service' , 'woocommerce-wholesale-prices-premium' ); ?></label>
                    <select id="wwpp-mh-shipping-services" data-placeholder="<?php _e( 'Choose shipping service...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                        <?php foreach ( $mh_shipping_services as $services ) { ?>
                            <option value="<?php echo $services[ 'id' ]; ?>"><?php echo $services[ 'name' ]; ?></option>
                        <?php } ?>
                    </select>
                </div>

            <?php } ?>

            <!-- TODO: Get a list of all shipping classes -->

            <div style="clear: both; float: none; display: block;"></div>

        </div>

        <div class="button-controls add-mode">

            <input type="button" id="cancel-edit-mapping" class="button button-secondary" value="<?php _e( 'Cancel' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <input type="button" id="save-mapping" class="button button-primary" value="<?php _e( 'Save Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <input type="button" id="add-mapping" class="button button-primary" value="<?php _e( 'Add Mapping' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
            <span class="spinner"></span>

            <div style="clear: both; float: none; display: block;"></div>

        </div>

        <table id="wholesale-role-shipping-method-mapping" class="wp-list-table widefat">
            <thead>
                <tr>
                    <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th><?php _e( 'Shipping Method' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php if ( $table_rate_shipping_type == 'woo_themes' ) { ?>

                        <th><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                        <th><?php _e( 'Shipping Zone Method' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php } elseif ( $table_rate_shipping_type == 'code_canyon' ) { ?>

                        <th><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                        <th><?php _e( 'Shipping Table Rates' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php } elseif ( $table_rate_shipping_type == 'mango_hour' ) { ?>

                        <th><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                        <th><?php _e( 'Shipping Service' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php } ?>

                    <th><?php _e( 'Shipping Class' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th></th>
                </tr>
            </thead>

            <tfoot>
                <tr>
                    <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th><?php _e( 'Shipping Method' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php if ( $table_rate_shipping_type == 'woo_themes' ) { ?>

                        <th><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                        <th><?php _e( 'Shipping Zone Method' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php } elseif ( $table_rate_shipping_type == 'code_canyon' ) { ?>

                        <th><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                        <th><?php _e( 'Shipping Table Rates' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php } elseif ( $table_rate_shipping_type == 'mango_hour' ) { ?>

                        <th><?php _e( 'Shipping Zone' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                        <th><?php _e( 'Shipping Service' , 'woocommerce-wholesale-prices-premium' ); ?></th>

                    <?php } ?>

                    <th><?php _e( 'Shipping Class' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                    <th></th>
                </tr>
            </tfoot>

            <?php if ( $saved_mapping ) {

                $itemNumber =   0;

                foreach ( $saved_mapping as $index => $shipping ) {
                    $itemNumber++;

                    if ( $itemNumber % 2 == 0 ) { // even  ?>
                        <tr class="even">
                    <?php } else { // odd ?>
                        <tr class="odd alternate">
                    <?php } ?>

                        <td class="meta hidden">
                            <span class="index"><?php echo trim( $index ); ?></span>
                            <span class="wholesale_role"><?php echo trim( $shipping[ 'wholesale_role' ] ); ?></span>
                            <span class="shipping_method"><?php echo trim( $shipping[ 'shipping_method' ] ); ?></span>

                            <?php if ( $table_rate_shipping_type == 'woo_themes' ) { ?>

                                <span class="shipping_zone"><?php echo trim( $shipping[ 'shipping_zone' ] ); ?></span>
                                <span class="shipping_zone_method"><?php echo trim( $shipping[ 'shipping_zone_method' ] ); ?></span>

                            <?php } elseif ( $table_rate_shipping_type == 'code_canyon' ) { ?>

                                <span class="shipping_zone"><?php echo trim( $shipping[ 'shipping_zone' ] ); ?></span>
                                <span class="shipping_zone_table_rate"><?php echo trim( $shipping[ 'shipping_zone_table_rate' ] ); ?></span>

                            <?php } elseif ( $table_rate_shipping_type == 'mango_hour' ) { ?>

                                <span class="shipping_zone"><?php echo trim( $shipping[ 'shipping_zone' ] ); ?></span>
                                <span class="shipping_service"><?php echo trim( $shipping[ 'shipping_service' ] ); ?></span>

                            <?php } ?>

                        </td>
                        <td class="wholesale_role_text"><?php echo trim( $all_wholesale_roles[ $shipping[ 'wholesale_role' ] ][ 'roleName' ] ); ?></td>
                        <td class="shipping_method_text"><?php echo trim( $wc_shipping_methods[ $shipping[ 'shipping_method' ] ]->title ); ?></td>

                        <?php if ( $table_rate_shipping_type == 'woo_themes' ) { ?>

                            <td class="shipping_zone_text"><?php echo trim( $shipping[ 'shipping_zone_name' ] ); ?></td>
                            <td class="shipping_zone_method_text"><?php echo trim( $shipping[ 'shipping_zone_method_name' ] ); ?></td>

                        <?php } elseif ( $table_rate_shipping_type == 'code_canyon' ) { ?>

                            <td class="shipping_zone_text"><?php echo trim( $shipping[ 'shipping_zone_name' ] ); ?></td>
                            <td class="shipping_zone_table_rate_text"><?php echo trim( $shipping[ 'shipping_zone_table_rate_name' ] ); ?></td>

                        <?php } elseif ( $table_rate_shipping_type == 'mango_hour' ) { ?>

                            <td class="shipping_zone_text"><?php echo trim( $shipping[ 'shipping_zone_name' ] ); ?></td>
                            <td class="shipping_service_text"><?php echo trim( $shipping[ 'shipping_service_name' ] ); ?></td>

                        <?php } ?>

                        <td class="shipping_class"><?php echo trim( $shipping[ 'shipping_class' ] ); ?></td>

                        <td class="controls">
                            <a class="edit dashicons dashicons-edit"></a>
                            <a class="delete dashicons dashicons-no"></a>
                        </td>

                    </tr>

                <?php }

            } else { ?>

                <tr class="no-items">
                    <td class="colspanchange" colspan="6"><?php _e( 'No Mappings Found' , 'woocommerce-wholesale-prices-premium' ); ?></td>
                </tr>

            <?php } ?>

        </table>
    </th>
</tr>