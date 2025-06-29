jQuery(document).ready(function ($) {
  /*
     |------------------------------------------------------------------------------------------------------------------
     | Variable Declarations
     |------------------------------------------------------------------------------------------------------------------
     */

  var $surcharge_controls = $("#payment-gateway-surcharge-mapping .surcharge-controls"),
    $wwpp_index = $surcharge_controls.find("#wwpp-index"),
    $wwpp_wholesale_roles = $surcharge_controls.find("#wwpp-wholesale-roles"),
    $wwpp_payment_gateway = $surcharge_controls.find("#wwpp-payment-gateway"),
    $wwpp_surcharge_title = $surcharge_controls.find("#wwpp-surcharge-title"),
    $wwpp_surcharge_type = $surcharge_controls.find("#wwpp-surcharge-type"),
    $wwpp_surcharge_amount = $surcharge_controls.find("#wwpp-surcharge-amount"),
    $wwpp_surcharge_taxable = $surcharge_controls.find("#wwpp-surcharge-taxable"),
    $button_controls = $("#payment-gateway-surcharge-mapping .button-controls"),
    $wholesale_payment_gateway_surcharge = $("#wholesale-payment-gateway-surcharge"),
    errorMessageDuration = "10000",
    successMessageDuration = "5000";

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Helper Functions
     |------------------------------------------------------------------------------------------------------------------
     */

  function removeTableNoItemsPlaceholder($table) {
    $table.find("tbody").find(".no-items").remove();
  }

  function resetTableRowStyling() {
    $wholesale_payment_gateway_surcharge
      .find("tbody")
      .find("tr")
      .each(function (index) {
        index++; // we do this coz index is zero base

        if (index % 2 == 0) {
          // even
          $(this).removeClass("odd").removeClass("alternate").addClass("even");
        } else {
          // odd
          $(this).removeClass("even").addClass("odd").addClass("alternate");
        }
      });
  }

  function resetFields() {
    $wwpp_index.val("");
    $wwpp_wholesale_roles.val("").trigger("change").trigger("chosen:updated");
    $wwpp_payment_gateway.val("").trigger("change").trigger("chosen:updated");
    $wwpp_surcharge_title.val("");
    $wwpp_surcharge_type.val("").trigger("change").trigger("chosen:updated");
    $wwpp_surcharge_amount.val("");
    $wwpp_surcharge_taxable.val("").trigger("chosen:updated");
  }

  function validateFields() {
    error_fields = [];

    if ($.trim($wwpp_wholesale_roles.val()) == "") error_fields.push("Wholesale Role");

    if ($.trim($wwpp_payment_gateway.val()) == "") error_fields.push("Payment Gateway");

    if ($.trim($wwpp_surcharge_title.val()) == "") error_fields.push("Surcharge Title");

    if ($.trim($wwpp_surcharge_type.val()) == "") error_fields.push("Surcharge Type");

    if ($.trim($wwpp_surcharge_amount.val()) == "") error_fields.push("Surcharge Value");

    if ($.trim($wwpp_surcharge_taxable.val()) == "") error_fields.push("Surcharge Taxable");

    return error_fields;
  }

  /*
     |------------------------------------------------------------------------------------------------------------------
     | Events
     |------------------------------------------------------------------------------------------------------------------
     */

  $button_controls.find("#add-surcharge").click(function () {
    var $this = $(this);

    $this.attr("disabled", "disabled");
    $button_controls.addClass("processing");

    var error_fields = validateFields();

    if (error_fields.length > 0) {
      var msg = wwpp_payment_gateway_controls_custom_field_params.i18n_specify_field_values_with_colon + "<br/><ul>";

      for (var i = 0; i < error_fields.length; i++) msg += "<li>" + error_fields[i] + "</li>";

      msg += "</ul>";

      toastr.error(msg, wwpp_payment_gateway_controls_custom_field_params.i18n_form_error, {
        closeButton: true,
        showDuration: errorMessageDuration,
      });

      $this.removeAttr("disabled");
      $button_controls.removeClass("processing");

      return false;
    }

    var surchargeData = {
      wholesale_role: $.trim($wwpp_wholesale_roles.val()),
      payment_gateway: $.trim($wwpp_payment_gateway.val()),
      surcharge_title: $.trim($wwpp_surcharge_title.val()),
      surcharge_type: $.trim($wwpp_surcharge_type.val()),
      surcharge_type_text: $.trim($wwpp_surcharge_type.find(":selected").text()),
      surcharge_amount: $.trim($wwpp_surcharge_amount.val()),
      taxable: $.trim($wwpp_surcharge_taxable.val()),
      taxable_text: $.trim($wwpp_surcharge_taxable.find(":selected").text()),
    };

    wwppBackendAjaxServices
      .addPaymentGatewaySurcharge(surchargeData, wwpp_payment_gateway_controls_custom_field_params.user_id)
      .done(function (data, textStatus, jqXHR) {
        if (data.status == "success") {
          toastr.success("", wwpp_payment_gateway_controls_custom_field_params.i18n_payment_gateway_added, {
            closeButton: true,
            showDuration: successMessageDuration,
          });

          removeTableNoItemsPlaceholder($wholesale_payment_gateway_surcharge);

          var tr_class = "";

          if ($wholesale_payment_gateway_surcharge.find("tr").length % 2 == 0)
            // currently even, next add (our add) would make it odd
            tr_class = "odd alternate";
          // currently odd, next add (our add) would make it even
          else tr_class = "even";

          $wholesale_payment_gateway_surcharge
            .find("tbody")
            .append(
              '<tr class="' +
                tr_class +
                ' edited">' +
                '<td class="meta hidden">' +
                '<span class="index">' +
                data.latest_index +
                "</span>" +
                '<span class="wholesale-role">' +
                surchargeData.wholesale_role +
                "</span>" +
                '<span class="payment-gateway">' +
                surchargeData.payment_gateway +
                "</span>" +
                '<span class="surcharge-type">' +
                surchargeData.surcharge_type +
                "</span>" +
                '<span class="taxable">' +
                surchargeData.taxable +
                "</span>" +
                "</td>" +
                '<td class="wholesale-role-text">' +
                $wwpp_wholesale_roles.find("option[value='" + surchargeData.wholesale_role + "']").text() +
                "</td>" +
                '<td class="payment-gateway-text">' +
                $wwpp_payment_gateway.find("option[value='" + surchargeData.payment_gateway + "']").text() +
                "</td>" +
                '<td class="surcharge-title">' +
                surchargeData.surcharge_title +
                "</td>" +
                '<td class="surcharge-type-text">' +
                $wwpp_surcharge_type.find("option[value='" + surchargeData.surcharge_type + "']").text() +
                "</td>" +
                '<td class="surcharge-amount">' +
                surchargeData.surcharge_amount +
                "</td>" +
                '<td class="taxable-text">' +
                surchargeData.taxable_text +
                "</td>" +
                '<td class="controls">' +
                '<a class="edit dashicons dashicons-edit"></a>' +
                '<a class="delete dashicons dashicons-no"></a>' +
                "</td>" +
                "</tr>"
            );

          resetFields();

          // Remove edited class to the recently added user field
          setTimeout(function () {
            $wholesale_payment_gateway_surcharge.find("tr.edited").removeClass("edited");
          }, 500);
        } else {
          toastr.error(
            data.error_message,
            wwpp_payment_gateway_controls_custom_field_params.i18n_failed_add_payment_gateway,
            { closeButton: true, showDuration: errorMessageDuration }
          );

          console.log(wwpp_payment_gateway_controls_custom_field_params.i18n_failed_add_payment_gateway);
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error(
          jqXHR.responseText,
          wwpp_payment_gateway_controls_custom_field_params.i18n_failed_add_payment_gateway,
          { closeButton: true, showDuration: errorMessageDuration }
        );

        console.log(wwpp_payment_gateway_controls_custom_field_params.i18n_failed_add_payment_gateway);
        console.log(jqXHR);
        console.log("----------");
      })
      .always(function () {
        $this.removeAttr("disabled");
        $button_controls.removeClass("processing");
      });
  });

  $button_controls.find("#save-surcharge").click(function () {
    var $this = $(this);

    $this.attr("disabled", "disabled");
    $button_controls.addClass("processing");

    var error_fields = validateFields();

    if (error_fields.length > 0) {
      var msg = wwpp_payment_gateway_controls_custom_field_params.i18n_specify_field_values_with_colon + "<br/><ul>";

      for (var i = 0; i < error_fields.length; i++) msg += "<li>" + error_fields[i] + "</li>";

      msg += "</ul>";

      toastr.error(msg, wwpp_payment_gateway_controls_custom_field_params.i18n_form_error, {
        closeButton: true,
        showDuration: errorMessageDuration,
      });

      $this.removeAttr("disabled");
      $button_controls.removeClass("processing");

      return false;
    }

    var idx = $.trim($wwpp_index.val()),
      surchargeData = {
        wholesale_role: $.trim($wwpp_wholesale_roles.val()),
        payment_gateway: $.trim($wwpp_payment_gateway.val()),
        surcharge_title: $.trim($wwpp_surcharge_title.val()),
        surcharge_type: $.trim($wwpp_surcharge_type.val()),
        surcharge_type_text: $.trim($wwpp_surcharge_type.find(":selected").text()),
        surcharge_amount: $.trim($wwpp_surcharge_amount.val()),
        taxable: $.trim($wwpp_surcharge_taxable.val()),
        taxable_text: $.trim($wwpp_surcharge_taxable.find(":selected").text()),
      };

    wwppBackendAjaxServices
      .updatePaymentGatewaySurcharge(idx, surchargeData, wwpp_payment_gateway_controls_custom_field_params.user_id)
      .done(function (data, textStatus, jqXHR) {
        if (data.status == "success") {
          $wholesale_payment_gateway_surcharge
            .find("tr.edited")
            .find(".meta")
            .find(".index")
            .text(idx)
            .end()
            .find(".wholesale-role")
            .text(surchargeData.wholesale_role)
            .end()
            .find(".payment-gateway")
            .text(surchargeData.payment_gateway)
            .end()
            .find(".surcharge-type")
            .text(surchargeData.surcharge_type)
            .end()
            .find(".taxable")
            .text(surchargeData.taxable)
            .end()
            .end()
            .find(".wholesale-role-text")
            .text($wwpp_wholesale_roles.find("option[value='" + surchargeData.wholesale_role + "']").text())
            .end()
            .find(".payment-gateway-text")
            .text($wwpp_payment_gateway.find("option[value='" + surchargeData.payment_gateway + "']").text())
            .end()
            .find(".surcharge-title")
            .text(surchargeData.surcharge_title)
            .end()
            .find(".surcharge-type-text")
            .text(surchargeData.surcharge_type_text)
            .end()
            .find(".surcharge-amount")
            .text(surchargeData.surcharge_amount)
            .end()
            .find(".taxable-text")
            .text(surchargeData.taxable_text);

          $wholesale_payment_gateway_surcharge.find("tr .controls .dashicons").css("display", "inline-block");

          resetFields();

          // Remove edited class to the recently added user field
          setTimeout(function () {
            $wholesale_payment_gateway_surcharge.find("tr.edited").removeClass("edited");
          }, 500);

          $button_controls.removeClass("edit-mode").addClass("add-mode");

          toastr.success("", wwpp_payment_gateway_controls_custom_field_params.i18n_payment_gateway_updated, {
            closeButton: true,
            showDuration: successMessageDuration,
          });
        } else {
          toastr.error(
            data.error_message,
            wwpp_payment_gateway_controls_custom_field_params.i18n_failed_update_payment_gateway,
            { closeButton: true, showDuration: errorMessageDuration }
          );

          console.log(wwpp_payment_gateway_controls_custom_field_params.i18n_failed_update_payment_gateway);
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error(
          jqXHR.responseText,
          wwpp_payment_gateway_controls_custom_field_params.i18n_failed_update_payment_gateway,
          { closeButton: true, showDuration: errorMessageDuration }
        );

        console.log(
          jqXHR.responseText,
          wwpp_payment_gateway_controls_custom_field_params.i18n_failed_update_payment_gateway
        );
        console.log(data);
        console.log("----------");
      })
      .always(function () {
        $this.removeAttr("disabled");
        $button_controls.removeClass("processing");
      });
  });

  $button_controls.find("#cancel-edit-surcharge").click(function () {
    resetFields();

    $button_controls.removeClass("edit-mode").addClass("add-mode");

    $wholesale_payment_gateway_surcharge
      .find("tbody tr")
      .removeClass("edited")
      .find(".controls .dashicons")
      .css("display", "inline-block");
  });

  $wholesale_payment_gateway_surcharge.delegate(".edit", "click", function () {
    var $this = $(this),
      $currentTr = $this.closest("tr");

    $currentTr.addClass("edited");
    $wholesale_payment_gateway_surcharge.find(".controls .dashicons").css("display", "none");

    var currMapping = {
      index: $.trim($currentTr.find(".meta").find(".index").text()),
      wholesale_role: $.trim($currentTr.find(".meta").find(".wholesale-role").text()),
      payment_gateway: $.trim($currentTr.find(".meta").find(".payment-gateway").text()),
      surcharge_title: $.trim($currentTr.find(".surcharge-title").text()),
      surcharge_type: $.trim($currentTr.find(".meta").find(".surcharge-type").text()),
      surcharge_amount: $.trim($currentTr.find(".surcharge-amount").text()),
      taxable: $.trim($currentTr.find(".meta").find(".taxable").text()),
    };

    $wwpp_index.val(currMapping.index);
    $wwpp_wholesale_roles.val(currMapping.wholesale_role).trigger("change").trigger("chosen:updated");
    $wwpp_payment_gateway.val(currMapping.payment_gateway).trigger("change").trigger("chosen:updated");
    $wwpp_surcharge_title.val(currMapping.surcharge_title);
    $wwpp_surcharge_type.val(currMapping.surcharge_type).trigger("change").trigger("chosen:updated");
    $wwpp_surcharge_amount.val(currMapping.surcharge_amount);
    $wwpp_surcharge_taxable.val(currMapping.taxable).trigger("change").trigger("chosen:updated");

    $button_controls.removeClass("add-mode").addClass("edit-mode");
  });

  $wholesale_payment_gateway_surcharge.delegate(".delete", "click", function () {
    var $this = $(this),
      $currentTr = $this.closest("tr");

    $currentTr.addClass("edited");

    if (confirm(wwpp_payment_gateway_controls_custom_field_params.i18n_click_ok_remove_payment_gateway)) {
      var idx = $.trim($currentTr.find(".meta").find(".index").text());

      $wholesale_payment_gateway_surcharge.find(".controls .dashicons").css("display", "none");

      wwppBackendAjaxServices
        .deletePaymentGatewaySurcharge(idx, wwpp_payment_gateway_controls_custom_field_params.user_id)
        .done(function (data, textStatus, jqXHR) {
          if (data.status == "success") {
            $currentTr.fadeOut("fast", function () {
              $currentTr.remove();

              resetTableRowStyling();

              // If no more item then append the empty table placeholder
              if ($wholesale_payment_gateway_surcharge.find("tbody").find("tr").length <= 0) {
                $wholesale_payment_gateway_surcharge
                  .find("tbody")
                  .html(
                    '<tr class="no-items">' +
                      '<td class="colspanchange" colspan="6">' +
                      wwpp_payment_gateway_controls_custom_field_params.i18n_no_mapping_found +
                      "</td>" +
                      "</tr>"
                  );
              }
            });

            toastr.success("", wwpp_payment_gateway_controls_custom_field_params.i18n_payment_gateway_deleted, {
              closeButton: true,
              showDuration: successMessageDuration,
            });
          } else {
            toastr.error(
              data.error_message,
              wwpp_payment_gateway_controls_custom_field_params.i18n_failed_delete_payment_gateway,
              { closeButton: true, showDuration: errorMessageDuration }
            );

            console.log(wwpp_payment_gateway_controls_custom_field_params.i18n_failed_delete_payment_gateway);
            console.log(data);
            console.log("----------");
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          toastr.error(
            jqXHR.responseText,
            wwpp_payment_gateway_controls_custom_field_params.i18n_failed_delete_payment_gateway,
            { closeButton: true, showDuration: errorMessageDuration }
          );

          console.log(wwpp_payment_gateway_controls_custom_field_params.i18n_failed_delete_payment_gateway);
          console.log(jqXHR);
          console.log("----------");
        })
        .always(function () {
          $wholesale_payment_gateway_surcharge.find(".controls .dashicons").css("display", "inline-block");
        });
    } else {
      $currentTr.removeClass("edited");
    }
  });

  /*
     |------------------------------------------------------------------------------------------------------------------
     | On Page Load
     |------------------------------------------------------------------------------------------------------------------
     */

  $surcharge_controls.find("#wwpp-wholesale-roles").chosen({ allow_single_deselect: true });
  $surcharge_controls.find("#wwpp-payment-gateway").chosen({ allow_single_deselect: true });
  $surcharge_controls.find("#wwpp-surcharge-type").chosen({ allow_single_deselect: true });
  $surcharge_controls.find("#wwpp-surcharge-taxable").chosen({ allow_single_deselect: true });
});
