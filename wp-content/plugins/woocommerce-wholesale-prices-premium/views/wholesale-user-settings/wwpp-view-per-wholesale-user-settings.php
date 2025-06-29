<?php if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<h3><?php _e( 'Per User Wholesale Prices Settings' , 'woocommerce-wholesale-prices-premium' ); ?></h3>

<table id="wwpp-per-wholesale-user-settings" class="form-table">

    <?php foreach ( $per_wholesale_user_settings as $key => $data ) { ?>

        <tr id="<?php echo $key . '-tr'; ?>">
			
            <?php switch( $data[ 'type' ] ) {

                case 'select':

                    ?>
                    
                    <th><label for="<?php echo $key; ?>"><?php echo $data[ 'label' ]; ?></label></th>

                    <td>
                        <select id="<?php echo $key; ?>" name="<?php echo $key; ?>" autocomplete="off" class="<?php echo $data[ 'class' ]; ?>">
                            <?php foreach( $data[ 'options' ] as $k => $v ) { ?>
                                <option value="<?php echo $k; ?>" <?php echo $k == $data[ 'default' ] ? "selected" : ""; ?>><?php echo $v; ?></option>
                            <?php } ?>
                        </select>
                        
                        <?php if ( isset( $data[ 'desc' ] ) ) { ?>
                            <p class="desc"><?php echo $data[ 'desc' ]; ?></p>
                        <?php } ?>
                    </td>

                    <?php

                    break;

                case 'text':

                    ?>
                    
                    <th><label for="<?php echo $key; ?>"><?php echo $data[ 'label' ]; ?></label></th>

                    <td>
                        <input type="text" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $data[ 'default' ]; ?>" autocomplete="off" class="<?php echo $data[ 'class' ]; ?>">

                        <?php if ( isset( $data[ 'desc' ] ) ) { ?>
                            <p class="desc"><?php echo $data[ 'desc' ]; ?></p>
                        <?php } ?>
                    </td>

                    <?php

                    break;

                case 'multiselect':
                    
                    ?>
                    
                    <th><label for="<?php echo $key; ?>"><?php echo $data[ 'label' ]; ?></label></th>

                    <td>
                        <select id="<?php echo $key; ?>" autocomplete="off" class="<?php echo $data[ 'class' ]; ?>" multiple autocomplete="off" data-placeholder="<?php echo $data[ 'placeholder' ]; ?>" style="width: 25em;">
                            <?php foreach( $data[ 'options' ] as $k => $v ) { ?>
                                <option value="<?php echo $k; ?>" <?php echo in_array( $k , $data[ 'default' ] ) ? "selected" : ""; ?>><?php echo $v; ?></option>
                            <?php } ?>
                        </select>
                        
                        <?php if ( isset( $data[ 'desc' ] ) ) { ?>
                            <p class="desc"><?php echo $data[ 'desc' ]; ?></p>
                        <?php } ?>
                    </td>

                    <?php
                    break;

                case 'cart_qty_based_wholesale_discount_mapping_table':
                    
                    ?>
                    
                    <th colspan="2" style="padding-top: 0;">
                        
                        <p class="desc" style="margin-top: 0;"><?php _e( 'You must supply wholesale discount above for the mapping below to take effect.' , 'woocommerce-wholesale-prices-premium' ); ?></p>

                        <div id="wholesale-role-cart-qty-based-wholesale-discount-container">

                            <?php require_once ( WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-general-cart-qty-based-discount-controls-custom-field.php' ); ?>
                            
                        </div>
                        
                    </th>

                    <?php
                    break;

                case 'payment_gateway_surcharge_mapping_table':
                    
                    ?>
                    
                    <th colspan="2" style="padding-top: 0;">
                        
                        <?php require_once ( WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-payment-gateway-surcharge-controls-custom-field.php' ); ?>

                    </th>
                    
                    <?php
                    break;
                    
                case 'divider':
                    
                    ?>
                    
                    <th colspan="2" style="padding: 0;">
                        <hr style="margin: 0;">
                    </th>

                    <?php
                    break;
                
                case 'subheading':
                    
                    ?>
                    
                    <th colspan="2" style="padding: 0;">
                        <h4 style="font-size: 1.1em; margin: 1.75em 0 0.5em;"><?php echo $data[ 'label' ]; ?></h4>
                    </th>

                    <?php
                    break;
                    
            }  ?>

        </tr>    

    <?php } ?>

</table>