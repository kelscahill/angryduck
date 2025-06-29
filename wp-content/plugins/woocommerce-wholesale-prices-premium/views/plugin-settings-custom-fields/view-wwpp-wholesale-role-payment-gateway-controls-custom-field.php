<?php if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

//wp-pg = Wholesale Role Payment Gateway ?>
<tr valign="top">
    <th colspan="2" scope="row" class="titledesc">
        <div class="wr-pg-mapping-controls">

            <div class="field-container wwpp-wr-pg-wholesale-roles-field-container">

                <label for="wwpp-wr-pg-wholesale-roles"><?php _e('Wholesale Role', 'woocommerce-wholesale-prices-premium');?></label>
                <select id="wwpp-wr-pg-wholesale-roles" data-placeholder="<?php _e('Choose wholesale role...', 'woocommerce-wholesale-prices-premium');?>">
                    <option value=""></option>
                    <?php foreach ($all_wholesale_roles as $wholesaleRoleKey => $wholesaleRole) {?>
                        <option value="<?php echo $wholesaleRoleKey ?>"><?php echo $wholesaleRole['roleName']; ?></option>
                    <?php }?>
                </select>

            </div>

            <div class="field-container">

                <label for="wwpp-wr-pg-payment-gateway"><?php _e('Payment Gateways. <br/><small><i>You can add multiple payment gateways to a single role mapping</i></small>', 'woocommerce-wholesale-prices-premium');?></label>
                <select id="wwpp-wr-pg-payment-gateway" multiple data-placeholder="<?php _e('Choose payment gateway...', 'woocommerce-wholesale-prices-premium');?>">
                    <?php foreach ($available_gateways as $gateway_key => $gateway) { ?>
                        <option value="<?php echo $gateway_key ?>"> 
                            <?php if( !empty($gateway->title) && $gateway->title !== $gateway->method_title )
                                echo $gateway->method_title . ' | '. $gateway->title;
                            else
                                echo $gateway->method_title; ?>
                        </option>
                    <?php } ?>
                </select>

            </div>

            <div style="clear: both; float: none; display: block;"></div>

        </div>

        <div class="wr-pg-button-controls add-mode">

            <input type="button" id="wr-pg-cancel-edit-mapping" class="button button-secondary" value="<?php _e('Cancel', 'woocommerce-wholesale-prices-premium');?>"/>
            <input type="button" id="wr-pg-save-mapping" class="button button-primary" value="<?php _e('Save Mapping', 'woocommerce-wholesale-prices-premium');?>"/>
            <input type="button" id="wr-pg-add-mapping" class="button button-primary" value="<?php _e('Add Mapping', 'woocommerce-wholesale-prices-premium');?>"/>
            <span class="spinner"></span>

            <div style="clear: both; float: none; display: block;"></div>

        </div>

        <table id="wr-pg-mapping" class="wp-list-table widefat">
            <thead>
                <tr>
                    <th><?php _e('Wholesale Role', 'woocommerce-wholesale-prices-premium');?></th>
                    <th><?php _e('Payment Gateways', 'woocommerce-wholesale-prices-premium');?></th>
                    <th></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th><?php _e('Wholesale Role', 'woocommerce-wholesale-prices-premium');?></th>
                    <th><?php _e('Payment Gateways', 'woocommerce-wholesale-prices-premium');?></th>
                    <th></th>
                </tr>
            </tfoot>
            <tbody>
            <?php if ($wholesale_role_payment_gateway_papping) {

    $itemNumber = 0;

    foreach ($wholesale_role_payment_gateway_papping as $wholesaleRole => $paymentGateways) {
        $itemNumber++;

        if ($itemNumber % 2 == 0) { // even  ?>
                        <tr class="even">
                    <?php } else { // odd ?>
                        <tr class="odd alternate">
                    <?php }?>

                        <td class="meta hidden">
                            <span class="wholesale-role"><?php echo $wholesaleRole; ?></span>
                            <span class="payment-gateways">
                                <ul>
                                <?php foreach ($paymentGateways as $gateway) {?>
                                    <li><?php echo $gateway['id']; ?></li>
                                <?php }?>
                                </ul>
                            </span>
                        </td>
                        <td class="wholesale-role-text"><?php
if (isset($all_wholesale_roles[$wholesaleRole]['roleName'])) {
            echo $all_wholesale_roles[$wholesaleRole]['roleName'];
        } else {
            echo sprintf(__('%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium'), $wholesaleRole);
        }
        ?>
                        </td>
                        <td class="payment-gateways-text">
                            <ul>
                            <?php foreach ($paymentGateways as $gateway) {?>
                                <li><?php echo $gateway['title']; ?></li>
                            <?php }?>
                            </ul>
                        </td>
                        <td class="controls">
                            <a class="edit dashicons dashicons-edit"></a>
                            <a class="delete dashicons dashicons-no"></a>
                        </td>

                    </tr>

                <?php }

} else {?>

                <tr class="no-items">
                    <td class="colspanchange" colspan="3"><?php _e('No Mappings Found', 'woocommerce-wholesale-prices-premium');?></td>
                </tr>

            <?php }?>

            </tbody>

        </table>
    </th>
</tr>

<style>
    p.submit {
        display: none !important;
    }
</style>