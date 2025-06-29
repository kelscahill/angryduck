<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wholesale_only_purchases_i18n = array(
    'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
    'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
);?>

<div id='wwpp-wholesale-roles-page' class='wwpp-page wrap nosubsub'>
    <h2><?php esc_html_e( 'Wholesale Roles', 'woocommerce-wholesale-prices-premium' ); ?></h2>

    <div id="col-container">

        <div id="col-right">

            <div class="col-wrap">

                <div>

                    <table id="wholesale-roles-table" class="wp-list-table widefat fixed tags"
                        style="margin-top: 74px;">

                        <thead>
                            <tr>
                                <th scope="col" id="role-name" class="manage-column column-role-name">
                                    <span><?php esc_html_e( 'Name', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                                <th scope="col" id="role-key" class="manage-column column-role-key">
                                    <span><?php esc_html_e( 'Key', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                                <th scope="col" id="only-allow-wholesale-purchases"
                                    class="manage-column column-only-allow-wholesale-purchases">
                                    <span><?php esc_html_e( 'Wholesale Purchases Only', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                                <th scope="col" id="role-desc" class="manage-column column-role-desc">
                                    <span><?php esc_html_e( 'Description', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                            </tr>
                        </thead>

                        <tbody id="the-list">
                            <?php
                            $count = 0;
                            foreach ( $all_registered_wholesale_roles as $ws_role_key => $ws_role ) {
                                ++$count;
                                $alternate = '';

                                if ( 0 !== $count % 2 ) {
                                    $alternate = 'alternate';
                                }
                            ?>

                            <tr id="<?php echo esc_attr( $ws_role_key ); ?>" class="<?php echo esc_attr( $alternate ); ?>">

                                <td class="role-name column-role-name">

                                    <?php if ( array_key_exists( 'main', $ws_role ) && $ws_role['main'] ) { ?>

                                    <strong><a class="main-role-name"><?php echo esc_html( $ws_role['roleName'] ); ?></a></strong>

                                    <div class="row-actions">
                                        <span class="edit"><a class="edit-role"
                                                href="#"><?php esc_attr_e( 'Edit', 'woocommerce-wholesale-prices-premium' ); ?></a>
                                    </div>

                                    <?php } else { ?>

                                    <strong><a><?php echo esc_html( $ws_role['roleName'] ); ?></a></strong><br>

                                    <div class="row-actions">
                                        <span class="edit"><a class="edit-role"
                                                href="#"><?php esc_attr_e( 'Edit', 'woocommerce-wholesale-prices-premium' ); ?></a>
                                            | </span>
                                        <span class="delete"><a class="delete-role"
                                                href="#"><?php esc_attr_e( 'Delete', 'woocommerce-wholesale-prices-premium' ); ?></a></span>
                                    </div>

                                    <?php } ?>

                                </td>

                                <td class="role-key column-role-key"><?php echo esc_attr( $ws_role_key ); ?></td>

                                <td class="only-allow-wholesale-purchases column-only-allow-wholesale-purchases"
                                    data-attr-raw-data="<?php echo isset( $ws_role['onlyAllowWholesalePurchases'] ) ? esc_attr( $ws_role['onlyAllowWholesalePurchases'] ) : 'no'; ?>">
                                    <?php echo isset( $ws_role['onlyAllowWholesalePurchases'] ) ? esc_html( $wholesale_only_purchases_i18n[ $ws_role['onlyAllowWholesalePurchases'] ] ) : esc_html( $wholesale_only_purchases_i18n['no'] ); ?>
                                </td>

                                <td class="role-desc column-role-desc"><?php echo esc_html( $ws_role['desc'] ); ?></td>

                            </tr>
                            <?php } ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th scope="col" id="role-name" class="manage-column column-role-name">
                                    <span><?php esc_html_e( 'Name', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                                <th scope="col" id="role-key" class="manage-column column-role-key">
                                    <span><?php esc_html_e( 'Key', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                                <th scope="col" id="only-allow-wholesale-purchases"
                                    class="manage-column column-only-allow-wholesale-purchases">
                                    <span><?php esc_html_e( 'Wholesale Purchases Only', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                                <th scope="col" id="role-desc" class="manage-column column-role-desc">
                                    <span><?php esc_html_e( 'Description', 'woocommerce-wholesale-prices-premium' ); ?></span>
                                </th>
                            </tr>
                        </tfoot>

                    </table>

                    <br class="clear">
                </div>

                <div class="form-wrap">
                    <p>
                        <strong><?php esc_html_e( 'Note:', 'woocommerce-wholesale-prices-premium' ); ?></strong><br />
                        <?php esc_html_e( 'When deleting a wholesale role, all users attached with that role will have the default wholesale role (Wholesale Customer) as their wholesale role.', 'woocommerce-wholesale-prices-premium' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( "Wholesale Roles are just a copy of WooCommerce's Customer Role with an additional custom capability of 'have_wholesale_price'.", 'woocommerce-wholesale-prices-premium' ); ?>
                    </p>
                </div>

            </div>
            <!--.col-wrap-->

        </div>
        <!--#col-right-->

        <div id="col-left">

            <div class="col-wrap">

                <div class="form-wrap">
                    <h3><?php esc_html_e( 'Add New Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?></h3>

                    <div id="wholesale-form">

                        <div class="form-field form-required">
                            <label
                                for="role-name"><?php esc_html_e( 'Role Name', 'woocommerce-wholesale-prices-premium' ); ?></label>
                            <input id="role-name" value="" size="40" type="text">
                            <p><?php esc_html_e( 'Required. Recommended to be unique.', 'woocommerce-wholesale-prices-premium' ); ?>
                            </p>
                        </div>

                        <div class="form-field form-required">
                            <label
                                for="role-key"><?php esc_html_e( 'Role Key', 'woocommerce-wholesale-prices-premium' ); ?></label>
                            <input id="role-key" value="" size="40" type="text">
                            <p class="required_notice">
                                <?php esc_html_e( 'Optional. Must be unique. Must only contain lowercase letters, numbers, hyphens, and underscores', 'woocommerce-wholesale-prices-premium' ); ?>
                            </p>
                        </div>

                        <div class="form-field form-required">
                            <label
                                for="role-desc"><?php esc_html_e( 'Description', 'woocommerce-wholesale-prices-premium' ); ?></label>
                            <textarea id="role-desc" rows="5" cols="40"></textarea>
                            <p><?php esc_html_e( 'Optional.', 'woocommerce-wholesale-prices-premium' ); ?></p>
                        </div>

                        <h2 style="margin-top: 20px;">
                            <?php esc_html_e( 'Role Specific Settings', 'woocommerce-wholesale-prices-premium' ); ?></h2>

                        <div class="form-field checkbox-field">
                            <input type="checkbox" id="only-allow-wholesale-purchase" autocomplete="off">
                            <label for="only-allow-wholesale-purchase">
                                <?php esc_html_e( 'Prevent purchase if wholesale condition is not met', 'woocommerce-wholesale-prices-premium' ); ?>
                                <span class="dashicons dashicons-editor-help tooltip right"
                                    data-tip="<?php esc_html_e( 'Prevents customers from checking out if they haven\'t met the minimum requirements to activate wholesale pricing (as per the Minimum Order Requirements setting)', 'woocommerce-wholesale-prices-premium' ); ?>"></span>
                            </label>
                        </div>

                        <p class="submit add-controls">
                            <input id="add-wholesale-role-submit" class="button button-primary"
                                value="<?php esc_html_e( 'Add New Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?>"
                                type="button"><span class="spinner"></span>
                        </p>

                        <p class="submit edit-controls">
                            <input id="edit-wholesale-role-submit" class="button button-primary"
                                value="<?php esc_html_e( 'Edit Wholesale Role', 'woocommerce-wholesale-prices-premium' ); ?>"
                                type="button"><span class="spinner"></span>
                            <input id="cancel-edit-wholesale-role-submit" class="button button-secondary"
                                value="<?php esc_html_e( 'Cancel Edit', 'woocommerce-wholesale-prices-premium' ); ?>"
                                type="button" />
                        </p>

                    </div>
                </div>

            </div>
            <!--.col-wrap-->

        </div>
        <!--#col-left-->

    </div>
    <!--#col-container-->

</div>
<!--#wwpp-wholesale-roles-page-->
