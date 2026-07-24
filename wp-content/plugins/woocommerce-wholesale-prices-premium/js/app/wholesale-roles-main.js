jQuery(document).ready(function ($) {
    // Variable Declarations
    var $wholesaleRolesPage = $("#wwpp-wholesale-roles-page"),
        $wholesaleTable = $wholesaleRolesPage.find(".wp-list-table"),
        $wholesaleForm = $wholesaleRolesPage.find("#wholesale-form"),
        errorToastrShowDuration = "12000",
        successToastrShowDuration = "5000",
        $regexname = /^(?![0-9\-_]+$)[0-9a-z\-_]{0,150}$/;

    /*
     |--------------------------------------------------------------------------
     | Initialize Tooltips
     |--------------------------------------------------------------------------
     */

    $(".tooltip").tipTip({
        attribute: "data-tip",
        fadeIn: 50,
        fadeOut: 50,
        delay: 200,
    });

    /*
     |--------------------------------------------------------------------------
     | Events
     |--------------------------------------------------------------------------
     */

    $wholesaleForm.find("#add-wholesale-role-submit").click(function () {
        wwppWholesaleRolesFormActions.setSubmitButtonToProcessingState();

        var roleName = $.trim($wholesaleForm.find("#role-name").val()),
            roleKey = $.trim($wholesaleForm.find("#role-key").val()),
            roleDesc = $.trim($wholesaleForm.find("#role-desc").val()),
            onlyAllowWholesalePurchases = $wholesaleForm
                .find("#only-allow-wholesale-purchase")
                .is(":checked")
                ? "yes"
                : "no",
            checkPoint = true;

        if (roleName == "") {
            toastr.error(
                wp.i18n.__( 'Please Enter Role Name', 'woocommerce-wholesale-prices-premium' ),
                wp.i18n.__( 'Error in Wholesale Form', 'woocommerce-wholesale-prices-premium' ),
                { closeButton: true, showDuration: errorToastrShowDuration }
            );
            checkPoint = false;
            wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
        }

        if(roleKey != ""){
            // role key should only contain lowercase letters, numbers, hyphens, and underscores other than that show error
            if(!roleKey.match($regexname)){
                toastr.error(
                    wp.i18n.__( 'Role Key must contain only lowercase letters, numbers, hyphens, and underscores', 'woocommerce-wholesale-prices-premium' ),
                    wp.i18n.__( 'Failed to Add New Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                    { closeButton: true, showDuration: errorToastrShowDuration }
                );
                checkPoint = false;
                wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
            }
        }


        if (checkPoint) {
            var newRole = {
                roleKey: roleKey,
                roleName: roleName,
                roleDesc: roleDesc,
                onlyAllowWholesalePurchases: onlyAllowWholesalePurchases,
            };

            wwppBackendAjaxServices
                .addNewWholesaleRole(newRole)
                .done(function (response, textStatus, jqXHR) {
                    if (response.status == "success") {
                        wwppWholesaleRolesListingActions.addRole(response.data);
                        toastr.success(
                            wp.i18n.sprintf(
                                /* translators: %s: wholesale role name */
                                wp.i18n.__( '%s Wholesale Role Successfully Added', 'woocommerce-wholesale-prices-premium' ),
                                response.data.roleName
                            ),
                            wp.i18n.__( 'Successfully Added New Role', 'woocommerce-wholesale-prices-premium' ),
                            {
                                closeButton: true,
                                showDuration: successToastrShowDuration,
                            }
                        );
                    } else {
                        toastr.error(
                            response.error_message,
                            wp.i18n.__( 'Failed to Add New Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            {
                                closeButton: true,
                                showDuration: errorToastrShowDuration,
                            }
                        );
                        console.log(response);
                    }

                    wwppWholesaleRolesFormActions.initialForm();
                    wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    toastr.error(
                        jqXHR.responseText,
                        wp.i18n.__( 'Failed to Add New Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                        {
                            closeButton: true,
                            showDuration: errorToastrShowDuration,
                        }
                    );

                    console.log(
                        wp.i18n.__( 'Failed to Add New Wholesale Role', 'woocommerce-wholesale-prices-premium' )
                    );
                    console.log(jqXHR);
                    console.log("----------");

                    wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
                });
        }

        return false;
    });

    $wholesaleForm.find("#edit-wholesale-role-submit").click(function () {
        wwppWholesaleRolesFormActions.setSubmitButtonToProcessingState();

        var roleName = $.trim($wholesaleForm.find("#role-name").val()),
            roleKey = $.trim($wholesaleForm.find("#role-key").val()),
            roleDesc = $.trim($wholesaleForm.find("#role-desc").val()),
            onlyAllowWholesalePurchases = $wholesaleForm
                .find("#only-allow-wholesale-purchase")
                .is(":checked")
                ? "yes"
                : "no",
            checkPoint = true;

        if (roleName == "") {
            toastr.error(
                wp.i18n.__( 'Please Enter Role Name', 'woocommerce-wholesale-prices-premium' ),
                wp.i18n.__( 'Error in Wholesale Form', 'woocommerce-wholesale-prices-premium' ),
                { closeButton: true, showDuration: errorToastrShowDuration }
            );
            checkPoint = false;
            wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
        }

        if(roleKey != ""){
            // role key should only contain letters, numbers, hyphens, and underscores other than that show error
            if(!roleKey.match($regexname)){
                toastr.error(
                    wp.i18n.__( 'Role Key must contain only lowercase letters, numbers, hyphens, and underscores', 'woocommerce-wholesale-prices-premium' ),
                    wp.i18n.__( 'Error in Wholesale Form', 'woocommerce-wholesale-prices-premium' ),
                    { closeButton: true, showDuration: errorToastrShowDuration }
                );
                checkPoint = false;
                wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
            }
        }

        if (checkPoint) {
            var role = {
                roleKey: roleKey,
                roleName: roleName,
                roleDesc: roleDesc,
                onlyAllowWholesalePurchases: onlyAllowWholesalePurchases,
            };

            wwppBackendAjaxServices
                .editWholesaleRole(role)
                .done(function (response, textStatus, jqXHR) {
                    if (response.status == "success") {
                        wwppWholesaleRolesListingActions.editRole(response.data);
                        toastr.success(
                            wp.i18n.sprintf(
                                /* translators: %s: wholesale role name */
                                wp.i18n.__( '%s Wholesale Role Successfully Edited', 'woocommerce-wholesale-prices-premium' ),
                                response.data.roleName
                            ),
                            wp.i18n.__( 'Successfully Edited Role', 'woocommerce-wholesale-prices-premium' ),
                            {
                                closeButton: true,
                                showDuration: successToastrShowDuration,
                            }
                        );
                    } else {
                        toastr.error(
                            response.error_message,
                            wp.i18n.__( 'Failed to Edit Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            {
                                closeButton: true,
                                showDuration: errorToastrShowDuration,
                            }
                        );
                        console.log(response);
                    }

                    wwppWholesaleRolesListingActions.setRowsToNormalMode();
                    wwppWholesaleRolesFormActions.initialForm();
                    wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    toastr.error(
                        jqXHR.responseText,
                        wp.i18n.__( 'Failed to Edit Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                        {
                            closeButton: true,
                            showDuration: errorToastrShowDuration,
                        }
                    );

                    console.log(
                        wp.i18n.__( 'Failed to Edit Wholesale Role', 'woocommerce-wholesale-prices-premium' )
                    );
                    console.log(jqXHR);
                    console.log("----------");

                    wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
                });
        }

        return false;
    });

    $wholesaleForm
        .find("#cancel-edit-wholesale-role-submit")
        .click(function () {
            wwppWholesaleRolesListingActions.setRowsToNormalMode();
            wwppWholesaleRolesFormActions.initialForm();
        });

    $wholesaleTable.delegate(".edit-role", "click", function () {
        wwppWholesaleRolesListingActions.setRowsToNormalMode();

        var $currentRow = $(this).closest("tr"),
            role = {
                roleName: $.trim(
                    $currentRow.find(".column-role-name > strong > a").text()
                ),
                roleKey: $.trim($currentRow.find(".column-role-key").text()),
                roleDesc: $.trim($currentRow.find(".column-role-desc").text()),
                onlyAllowWholesalePurchases: $.trim(
                    $currentRow
                        .find(".column-only-allow-wholesale-purchases")
                        .attr("data-attr-raw-data")
                ),
            };

        wwppWholesaleRolesFormActions.setFormToEditMode(role);
        wwppWholesaleRolesListingActions.setRowToEditMode($currentRow);

        return false;
    });

    $wholesaleTable.delegate(".delete-role", "click", function () {
        var $currentRow = $(this).closest("tr"),
            roleKey = $.trim($currentRow.find(".column-role-key").text());

        if (confirm( wp.i18n.sprintf(
            /* translators: %s: wholesale role key */
            wp.i18n.__( 'Delete %s Wholesale Role?', 'woocommerce-wholesale-prices-premium' ),
            roleKey
        ) )) {
            wwppBackendAjaxServices
                .deleteWholesaleRole(roleKey)
                .done(function (data, textStatus, jqXHR) {
                    if (data.status == "success") {
                        wwppWholesaleRolesListingActions.deleteRole(roleKey);
                        toastr.success(
                            wp.i18n.sprintf(
                                /* translators: %s: wholesale role name */
                                wp.i18n.__( '%s Wholesale Role Successfully Deleted', 'woocommerce-wholesale-prices-premium' ),
                                roleKey
                            ),
                            wp.i18n.__( 'Successfully Deleted Role', 'woocommerce-wholesale-prices-premium' ),
                            {
                                closeButton: true,
                                showDuration: successToastrShowDuration,
                            }
                        );
                    } else {
                        if (data.status == "error") {
                            toastr.warning(
                                data.error_message,
                                data.error_title,
                                {
                                    closeButton: true,
                                    showDuration: errorToastrShowDuration,
                                    hideDuration: 5000,
                                }
                            );
                        } else {
                            toastr.error(
                                data.error_message,
                                wp.i18n.__( 'Failed to Delete Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                {
                                    closeButton: true,
                                    showDuration: errorToastrShowDuration,
                                }
                            );
                            console.log(data);
                        }
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    toastr.error(
                        jqXHR.responseText,
                        wp.i18n.__( 'Failed to Delete Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                        {
                            closeButton: true,
                            showDuration: errorToastrShowDuration,
                        }
                    );

                    console.log(
                        wp.i18n.__( 'Failed to Delete Wholesale Role', 'woocommerce-wholesale-prices-premium' )
                    );
                    console.log(jqXHR);
                    console.log("----------");
                });
        }

        return false;
    });

    // Init on load
    wwppWholesaleRolesFormActions.initialForm();
    //$wholesaleForm.find("#role-shipping-class").chosen({allow_single_deselect: true});
});
