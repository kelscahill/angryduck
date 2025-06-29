/**
 * A function implementing the revealing module pattern to house all ajax request. It implements the ajax promise methodology
 * @return {Ajax Promise} promise it returns a promise, I promise that #lamejoke
 *
 * Info:
 * ajaxurl points to admin ajax url for ajax call purposes. Added by wp when script is wp enqueued
 */
var wwppBackendAjaxServices = (function () {
  var getAllRegisteredWholesaleRoles = function () {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: { action: "wwppGetAllRegisteredWholesaleRoles" },
        dataType: "json",
      });
    },
    addNewWholesaleRole = function (newRole) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppAddNewWholesaleRole",
          newRole: newRole,
          nonce: wwpp_wholesaleRolesListingActions_params.add_new_wholesale_role_nonce,
        },
        dataType: "json",
      });
    },
    editWholesaleRole = function (role) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppEditWholesaleRole",
          role: role,
          nonce: wwpp_wholesaleRolesListingActions_params.edit_wholesale_role_nonce,
        },
        dataType: "json",
      });
    },
    deleteWholesaleRole = function (roleKey) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpDeleteWholesaleRole",
          roleKey: roleKey,
          nonce: wwpp_wholesaleRolesListingActions_params.delete_wholesale_role_nonce,
        },
        dataType: "json",
      });
    },
    saveWWPPLicenseDetails = function (licenseDetails) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_activate_license",
          license_email: licenseDetails.license_email,
          license_key: licenseDetails.license_key,
          ajax_nonce: licenseDetails.nonce,
        },
        dataType: "json",
      });
    },
    addWholesaleRoleGeneralDiscount = function (discountMapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppAddWholesaleRoleGeneralDiscountMapping",
          discountMapping: discountMapping,
          nonce: wwpp_discount_controls_custom_field_params.add_wholesale_role_general_discount_mapping_nonce,
        },
        dataType: "json",
      });
    },
    editWholesaleRoleGeneralDiscount = function (discountMapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppEditWholesaleRoleGeneralDiscountMapping",
          discountMapping: discountMapping,
          nonce: wwpp_discount_controls_custom_field_params.edit_wholesale_role_general_discount_mapping_nonce,
        },
        dataType: "json",
      });
    },
    deleteWholesaleRoleGeneralDiscount = function (wholesaleRole) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppDeleteWholesaleRoleGeneralDiscountMapping",
          wholesaleRole: wholesaleRole,
          nonce: wwpp_discount_controls_custom_field_params.delete_wholesale_role_general_discount_mapping_nonce,
        },
        dataType: "json",
      });
    },
    addPaymentGatewaySurcharge = function (surchargeData, user_id) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppAddPaymentGatewaySurcharge",
          surchargeData: surchargeData,
          user_id: user_id,
          nonce: wwpp_payment_gateway_controls_custom_field_params.add_payment_gateway_surcharge_nonce,
        },
        dataType: "json",
      });
    },
    updatePaymentGatewaySurcharge = function (idx, surchargeData, user_id) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppUpdatePaymentGatewaySurcharge",
          idx: idx,
          surchargeData: surchargeData,
          user_id: user_id,
          nonce: wwpp_payment_gateway_controls_custom_field_params.update_payment_gateway_surcharge_nonce,
        },
        dataType: "json",
      });
    },
    deletePaymentGatewaySurcharge = function (idx, user_id) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppDeletePaymentGatewaySurcharge",
          idx: idx,
          user_id: user_id,
          nonce: wwpp_payment_gateway_controls_custom_field_params.delete_payment_gateway_surcharge_nonce,
        },
        dataType: "json",
      });
    },
    addWholesaleRolePaymentGatewayMapping = function (mapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppAddWholesaleRolePaymentGatewayMapping",
          mapping: mapping,
          nonce: wwpp_payment_gateway_controls_custom_field_params.add_wholesale_role_payment_gateway_mapping_nonce,
        },
        dataType: "json",
      });
    },
    updateWholesaleRolePaymentGatewayMapping = function (mapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppUpdateWholesaleRolePaymentGatewayMapping",
          mapping: mapping,
          nonce: wwpp_payment_gateway_controls_custom_field_params.update_wholesale_role_payment_gateway_mapping_nonce,
        },
        dataType: "json",
      });
    },
    deleteWholesaleRolePaymentGatewayMapping = function (wholesaleRoleKey) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppDeleteWholesaleRolePaymentGatewayMapping",
          wholesaleRoleKey: wholesaleRoleKey,
          nonce: wwpp_payment_gateway_controls_custom_field_params.delete_wholesale_role_payment_gateway_mapping_nonce,
        },
        dataType: "json",
      });
    },
    add_wholesale_role_order_requirement = function (mapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_add_wholesale_role_order_requirement",
          mapping: mapping,
          nonce: wwpp_order_requirement_per_wholesale_role_var.add_wholesale_role_order_requirement_nonce,
        },
        dataType: "json",
      });
    },
    edit_wholesale_role_order_requirement = function (mapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_edit_wholesale_role_order_requirement",
          mapping: mapping,
          nonce: wwpp_order_requirement_per_wholesale_role_var.edit_wholesale_role_order_requirement_nonce,
        },
        dataType: "json",
      });
    },
    delete_wholesale_role_order_requirement = function (wholesale_role) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_delete_wholesale_role_order_requirement",
          wholesale_role: wholesale_role,
          nonce: wwpp_order_requirement_per_wholesale_role_var.delete_wholesale_role_order_requirement_nonce,
        },
        dataType: "json",
      });
    },
    add_wholesale_role_tax_option = function (mapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_add_wholesale_role_tax_option",
          mapping: mapping,
          nonce: wwpp_settings_tax_var.wwpp_add_wholesale_role_tax_option_nonce,
        },
        dataType: "json",
      });
    },
    edit_wholesale_role_tax_option = function (mapping) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_edit_wholesale_role_tax_option",
          mapping: mapping,
          nonce: wwpp_settings_tax_var.wwpp_edit_wholesale_role_tax_option_nonce,
        },
        dataType: "json",
      });
    },
    delete_wholesale_role_tax_option = function (wholesale_role) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_delete_wholesale_role_tax_option",
          wholesale_role: wholesale_role,
          nonce: wwpp_settings_tax_var.wwpp_delete_wholesale_role_tax_option_nonce,
        },
        dataType: "json",
      });
    },
    initialize_product_visibility_meta = function () {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_initialize_product_visibility_meta",
          nonce: wwpp_settings_debug_var.wwpp_initialize_product_visibility_filter_meta_nonce,
        },
        dataType: "json",
      });
    },
    clear_unused_product_meta = function () {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_clear_unused_product_meta",
          nonce: wwpp_settings_debug_var.wwpp_clear_unused_product_meta_nonce,
        },
        dataType: "json",
      });
    },
    force_fetch_update_data = function () {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_force_fetch_update_data",
          nonce: wwpp_settings_debug_var.wwpp_force_fetch_update_data_nonce,
        },
        dataType: "json",
      });
    },
    toggle_product_quantity_based_wholesale_pricing = function (post_id, enable) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppToggleProductQuantityBasedWholesalePricing",
          post_id: post_id,
          enable: enable,
          nonce: wwpp_single_product_admin_vars.wwpp_toggle_product_quantity_based_wholesale_pricing_nonce,
        },
        dataType: "json",
      });
    },
    addQuantityDiscountRule = function (post_id, rule) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppAddQuantityDiscountRule",
          post_id: post_id,
          rule: rule,
          nonce: wwpp_single_product_admin_vars.wwpp_add_quantity_discount_rule_nonce,
        },
        dataType: "json",
      });
    },
    saveQuantityDiscountRule = function (post_id, index, rule) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppSaveQuantityDiscountRule",
          post_id: post_id,
          index: index,
          rule: rule,
          nonce: wwpp_single_product_admin_vars.wwpp_save_quantity_discount_rule_nonce,
        },
        dataType: "json",
      });
    },
    deleteQuantityDiscountRule = function (post_id, index) {
      return jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwppDeleteQuantityDiscountRule",
          post_id: post_id,
          index: index,
          nonce: wwpp_single_product_admin_vars.wwpp_delete_quantity_discount_rule_nonce,
        },
        dataType: "json",
      });
    };

  return {
    getAllRegisteredWholesaleRoles: getAllRegisteredWholesaleRoles,
    addNewWholesaleRole: addNewWholesaleRole,
    editWholesaleRole: editWholesaleRole,
    deleteWholesaleRole: deleteWholesaleRole,
    saveWWPPLicenseDetails: saveWWPPLicenseDetails,
    addWholesaleRoleGeneralDiscount: addWholesaleRoleGeneralDiscount,
    editWholesaleRoleGeneralDiscount: editWholesaleRoleGeneralDiscount,
    deleteWholesaleRoleGeneralDiscount: deleteWholesaleRoleGeneralDiscount,
    addPaymentGatewaySurcharge: addPaymentGatewaySurcharge,
    updatePaymentGatewaySurcharge: updatePaymentGatewaySurcharge,
    deletePaymentGatewaySurcharge: deletePaymentGatewaySurcharge,
    addWholesaleRolePaymentGatewayMapping: addWholesaleRolePaymentGatewayMapping,
    updateWholesaleRolePaymentGatewayMapping: updateWholesaleRolePaymentGatewayMapping,
    deleteWholesaleRolePaymentGatewayMapping: deleteWholesaleRolePaymentGatewayMapping,
    add_wholesale_role_order_requirement: add_wholesale_role_order_requirement,
    edit_wholesale_role_order_requirement: edit_wholesale_role_order_requirement,
    delete_wholesale_role_order_requirement: delete_wholesale_role_order_requirement,
    add_wholesale_role_tax_option: add_wholesale_role_tax_option,
    edit_wholesale_role_tax_option: edit_wholesale_role_tax_option,
    delete_wholesale_role_tax_option: delete_wholesale_role_tax_option,
    clear_unused_product_meta: clear_unused_product_meta,
    initialize_product_visibility_meta: initialize_product_visibility_meta,
    force_fetch_update_data: force_fetch_update_data,
    toggle_product_quantity_based_wholesale_pricing: toggle_product_quantity_based_wholesale_pricing,
    addQuantityDiscountRule: addQuantityDiscountRule,
    saveQuantityDiscountRule: saveQuantityDiscountRule,
    deleteQuantityDiscountRule: deleteQuantityDiscountRule,
  };
})();
