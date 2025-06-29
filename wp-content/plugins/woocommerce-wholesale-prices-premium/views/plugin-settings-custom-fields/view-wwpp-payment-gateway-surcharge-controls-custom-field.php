<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

$taxable = array(
    'yes' => __( 'Yes' , 'woocommerce-wholesale-prices-premium' ),
    'no' => __( 'No' , 'woocommerce-wholesale-prices-premium' )
);
?>

<div id="payment-gateway-surcharge-mapping">

    <div class="surcharge-controls">

        <input type="hidden" id="wwpp-index" value=""/>

        <div class="field-container wwpp-wholesale-roles-field-container">

            <label for="wwpp-wholesale-roles"><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            <select id="wwpp-wholesale-roles" data-placeholder="<?php _e( 'Choose wholesale role...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                <option value=""></option>
                <?php foreach ($all_wholesale_roles as $wholesaleRoleKey => $wholesaleRole ) { ?>
                    <option value="<?php echo $wholesaleRoleKey ?>"><?php echo $wholesaleRole[ 'roleName' ]; ?></option>
                <?php } ?>
            </select>

        </div>

        <div class="field-container wwpp-payment-gateway-field-container">

            <label for="wwpp-payment-gateway"><?php _e( 'Payment Gateway' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            <select id="wwpp-payment-gateway" data-placeholder="<?php _e( 'Choose payment gateway...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                <option value=""></option>
                <?php foreach ( $available_gateways as $gateway_key => $gateway ) { ?>
                    <option value="<?php echo $gateway_key ?>"> 
                        <?php if( !empty($gateway->title) && $gateway->title !== $gateway->method_title )
                            echo $gateway->method_title . ' | '. $gateway->title;
                        else
                            echo $gateway->method_title; ?>
                    </option>
                <?php } ?>
            </select>

        </div>

        <div class="field-container">

            <label for="wwpp-surcharge-title"><?php _e( 'Surcharge Title' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            <input type="text" id="wwpp-surcharge-title" class="regular-text" value=""/>

        </div>

        <div class="field-container wwpp-surcharge-type-field-container">

            <label for="wwpp-surcharge-type"><?php _e( 'Surcharge Type' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            <select id="wwpp-surcharge-type" data-placeholder="<?php _e( 'Choose surcharge type...' , 'woocommerce-wholesale-prices-premium' ); ?>">
                <option value=""></option>
                <?php foreach ( $surcharge_types as $surcharge_key => $surcharge_text ) { ?>
                    <option value="<?php echo $surcharge_key; ?>"><?php echo $surcharge_text; ?></option>
                <?php } ?>
            </select>

        </div>

        <div class="field-container">

            <label for="wwpp-surcharge-amount"><?php _e( 'Surcharge Amount' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            <input type="number" id="wwpp-surcharge-amount" class="regular-text" value="" step="any"/>
            <p class="desc"><?php _e( 'If surcharge type is percentage, then input amount in percent (%). Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.' , 'woocommerce-wholesale-prices-premium' ); ?></p>
            
        </div>

        <div class="field-container wwpp-surcharge-taxable-field-container">

            <label for="wwpp-surcharge-taxable"><?php _e( 'Taxable?' , 'woocommerce-wholesale-prices-premium' ); ?></label>
            <select id="wwpp-surcharge-taxable" data-placeholder="<?php _e('Select an option', 'woocommerce-wholesale-prices-premium');?>" >
                <option value=""></option>
                <option value="yes"><?php echo $taxable['yes']; ?></option>
                <option value="no"><?php echo $taxable['no']; ?></option>
            </select>

        </div>

        <div style="clear: both; float: none; display: block;"></div>

    </div>

    <div class="button-controls add-mode">

        <input type="button" id="cancel-edit-surcharge" class="button button-secondary" value="<?php _e( 'Cancel' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
        <input type="button" id="save-surcharge" class="button button-primary" value="<?php _e( 'Save Surcharge' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
        <input type="button" id="add-surcharge" class="button button-primary" value="<?php _e( 'Add Surcharge' , 'woocommerce-wholesale-prices-premium' ); ?>"/>
        <span class="spinner"></span>

        <div style="clear: both; float: none; display: block;"></div>

    </div>

    <table id="wholesale-payment-gateway-surcharge" class="wp-list-table widefat">
        <thead>
            <tr>
                <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Payment Gateway' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Surcharge Title' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Surcharge Type' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Surcharge Amount' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Taxable' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th></th>
            </tr>
        </thead>

        <tfoot>
            <tr>
                <th><?php _e( 'Wholesale Role' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Payment Gateway' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Surcharge Title' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Surcharge Type' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Surcharge Amount' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th><?php _e( 'Taxable' , 'woocommerce-wholesale-prices-premium' ); ?></th>
                <th></th>
            </tr>
        </tfoot>

        <tbody>

        <?php if ( $payment_gateway_surcharge ) {

            $itemNumber =   0;

            foreach( $payment_gateway_surcharge as $idx => $surcharge ) {
                $itemNumber++;

                $surcharge_text = isset( $all_wholesale_roles[ $surcharge[ 'wholesale_role' ] ] ) ? 
                                    $all_wholesale_roles[ $surcharge[ 'wholesale_role' ] ][ 'roleName' ] : 
                                    sprintf( __( 'Warning: The role <b>%1$s</b> does not exist anymore.' , 'woocommerce-wholesale-prices-premium' ) , $surcharge[ 'wholesale_role' ] );

                $payment_method = !empty( $available_gateways[ $surcharge[ 'payment_gateway' ] ]->title && $available_gateways[ $surcharge[ 'payment_gateway' ] ]->title !== $available_gateways[ $surcharge[ 'payment_gateway' ] ]->method_title ) ? $available_gateways[ $surcharge[ 'payment_gateway' ] ]->method_title . ' | ' . $available_gateways[ $surcharge[ 'payment_gateway' ] ]->title : $available_gateways[ $surcharge[ 'payment_gateway' ] ]->method_title;
                
                $surcharge_gateway_title = isset( $available_gateways[ $surcharge[ 'payment_gateway' ] ] ) ?
                                            $payment_method : sprintf( __( 'Warning: The payment gateway <b>%1$s</b> does not exist anymore' ) , $available_gateways[ $surcharge[ 'payment_gateway' ] ] );

                if ( $itemNumber % 2 == 0 ) { // even  ?>
                    <tr class="even">
                <?php } else { // odd ?>
                    <tr class="odd alternate">
                <?php } ?>

                    <td class="meta hidden">
                        <span class="index"><?php echo $idx; ?></span>
                        <span class="wholesale-role"><?php echo $surcharge[ 'wholesale_role' ] ?></span>
                        <span class="payment-gateway"><?php echo $surcharge[ 'payment_gateway' ] ?></span>
                        <span class="surcharge-type"><?php echo $surcharge[ 'surcharge_type' ]; ?></span>
                        <span class="taxable"><?php echo $surcharge[ 'taxable' ]; ?></span>
                    </td>
                    <td class="wholesale-role-text"><?php echo $surcharge_text; ?></td>
                    <td class="payment-gateway-text"><?php echo $surcharge_gateway_title; ?></td>
                    <td class="surcharge-title"><?php echo $surcharge[ 'surcharge_title' ]; ?></td>
                    <td class="surcharge-type-text"><?php echo $surcharge_types[ $surcharge[ 'surcharge_type' ] ]; ?></td>
                    <td class="surcharge-amount"><?php echo $surcharge[ 'surcharge_amount' ]; ?></td>
                    <td class="taxable-text"><?php echo $taxable[$surcharge[ 'taxable' ]]; ?></td>
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
        </tbody>

    </table>

</div>