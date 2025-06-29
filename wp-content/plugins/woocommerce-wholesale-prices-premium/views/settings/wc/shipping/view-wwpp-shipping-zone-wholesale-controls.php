<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<div id="wholesale-shipping-controls">

    <div class="meta" style="display: none !important;">
        <span class="zone-id"><?php echo $zone_id; ?></span>
    </div>

    <h3><?php _e( 'Wholesale Shipping Configuration' , 'woocommerce-wholesale-prices-premium' ); ?></h3>

    <table class="form-table">

        <tr valign="top">

            <?php

            $checked = '';
            if ( !is_null( $zone_wholesale_config ) )
                $checked = ( $zone_wholesale_config[ 'wholesale_only' ] == 'yes' ) ? 'checked="checked"' : '';

            ?>

            <th scope="row" class="titledesc">
                <label for="wwpp-shipping-zone-wholesale-only"><?php _e( 'Wholesale Only' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            </th>

            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php _e( 'Wholesale Only' , 'woocommerce-wholesale-prices-premium' ); ?></span></legend>
                    <label for="wwpp-shipping-zone-wholesale-only">
                        <input class="" name="wwpp-shipping-zone-wholesale-only" id="wwpp-shipping-zone-wholesale-only" style="" value="1" <?php echo $checked; ?> type="checkbox" autocomplete="off"> <?php _e( 'Make this shipping zone only available to wholesale customers' , 'woocommerce-wholesale-prices-premium' ); ?>
                    </label><br>
                </fieldset>
            </td>

        </tr>

        <tr valign="top">

            <?php

            $selected_wholesale_roles = array();
            if ( !is_null( $zone_wholesale_config ) )
                $selected_wholesale_roles = ( isset( $zone_wholesale_config[ 'wholesale_roles' ] ) && is_array( $zone_wholesale_config[ 'wholesale_roles' ] ) ) ? $zone_wholesale_config[ 'wholesale_roles' ] : array();

            ?>

            <th scope="row" class="titledesc">
                <label for="wwpp-shipping-zone-wholesale-roles"><?php _e( 'Wholesale Roles' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            </th>

            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php _e( 'Wholesale Roles' , 'woocommerce-wholesale-prices-premium' ); ?></span></legend>

                    <select name="wwpp-shipping-zone-wholesale-roles" id="wwpp-shipping-zone-wholesale-roles" data-placeholder="<?php _e( 'Choose wholesale role/s...' , 'woocommerce-wholesale-prices-premium' ); ?>" style="min-width: 440px;" multiple autocomplete="off">
                        <option value=""></option>
                        <?php foreach ( $all_wholesale_roles as $role_key => $role_data ) {

                            $selected = '';
                            if ( in_array( $role_key , $selected_wholesale_roles ) )
                                $selected = 'selected="selected"'; ?>

                            <option value="<?php echo $role_key; ?>" <?php echo $selected; ?>><?php echo $role_data[ 'roleName' ]; ?></option>

                        <?php } ?>
                    </select>

                    <p class="description"><?php _e( 'The wholesale roles that this shipping zone is applicable to' , 'woocommerce-wholesale-prices-premium' ); ?></p>
                </fieldset>
            </td>

        </tr>

    </table>

    <p class="submit">
        <input type="hidden" id="ajax-nonce" value="<?php echo wp_create_nonce( 'wwpp-save-shipping-zone-wholesale-config' ); ?>">
        <input type="button" id="save-shipping-zone-wholesale-config" class="button-primary" value="<?php _e( 'Save changes' , 'woocommerce-wholesale-prices-premium' ); ?>">
        <span class="spinner"></span>
    </p>

</div>
