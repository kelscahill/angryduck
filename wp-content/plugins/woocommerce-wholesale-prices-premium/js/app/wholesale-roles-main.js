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
                wwpp_wholesale_roles_main_params.i18n_enter_role_name,
                wwpp_wholesale_roles_main_params.i18n_error_wholesale_form,
                { closeButton: true, showDuration: errorToastrShowDuration }
            );
            checkPoint = false;
            wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
        }

        if(roleKey != ""){
            // role key should only contain lowercase letters, numbers, hyphens, and underscores other than that show error
            if(!roleKey.match($regexname)){
                toastr.error(
                    wwpp_wholesale_roles_main_params.i18n_error_role_key,
                    wwpp_wholesale_roles_main_params.i18n_failed_add_new_role,
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
                            response.data.roleName +
                                " " +
                                wwpp_wholesale_roles_main_params.i18n_role_successfully_added,
                            {
                                closeButton: true,
                                showDuration: successToastrShowDuration,
                            }
                        );
                    } else {
                        toastr.error(
                            response.error_message,
                            wwpp_wholesale_roles_main_params.i18n_failed_add_new_role,
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
                        wwpp_wholesale_roles_main_params.i18n_failed_add_new_role,
                        {
                            closeButton: true,
                            showDuration: errorToastrShowDuration,
                        }
                    );

                    console.log(
                        wwpp_wholesale_roles_main_params.i18n_failed_add_new_role
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
                wwpp_wholesale_roles_main_params.i18n_enter_role_name,
                wwpp_wholesale_roles_main_params.i18n_error_wholesale_form,
                { closeButton: true, showDuration: errorToastrShowDuration }
            );
            checkPoint = false;
            wwppWholesaleRolesFormActions.setSubmitButtonToNormalState();
        }

        if(roleKey != ""){
            // role key should only contain letters, numbers, hyphens, and underscores other than that show error
            if(!roleKey.match($regexname)){
                toastr.error(
                    wwpp_wholesale_roles_main_params.i18n_error_role_key,
                    wwpp_wholesale_roles_main_params.i18n_error_wholesale_form,
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
                            response.data.roleName +
                                " " +
                                wwpp_wholesale_roles_main_params.i18n_role_successfully_edited,
                            wwpp_wholesale_roles_main_params.i18n_successfully_edited_role,
                            {
                                closeButton: true,
                                showDuration: successToastrShowDuration,
                            }
                        );
                    } else {
                        toastr.error(
                            response.error_message,
                            wwpp_wholesale_roles_main_params.i18n_failed_edit_role,
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
                        wwpp_wholesale_roles_main_params.i18n_failed_edit_role,
                        {
                            closeButton: true,
                            showDuration: errorToastrShowDuration,
                        }
                    );

                    console.log(
                        wwpp_wholesale_roles_main_params.i18n_failed_edit_role
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

        if (confirm("Delete " + roleKey + " Wholesale Role?")) {
            wwppBackendAjaxServices
                .deleteWholesaleRole(roleKey)
                .done(function (data, textStatus, jqXHR) {
                    if (data.status == "success") {
                        wwppWholesaleRolesListingActions.deleteRole(roleKey);
                        toastr.success(
                            roleKey +
                                " " +
                                wwpp_wholesale_roles_main_params.i18n_role_successfully_deleted,
                            wwpp_wholesale_roles_main_params.i18n_successfully_deleted_role,
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
                                wwpp_wholesale_roles_main_params.i18n_failed_delete_role,
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
                        wwpp_wholesale_roles_main_params.i18n_failed_delete_role,
                        {
                            closeButton: true,
                            showDuration: errorToastrShowDuration,
                        }
                    );

                    console.log(
                        wwpp_wholesale_roles_main_params.i18n_failed_delete_role
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
