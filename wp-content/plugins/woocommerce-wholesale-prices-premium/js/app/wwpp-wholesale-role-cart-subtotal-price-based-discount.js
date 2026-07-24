jQuery(document).ready(function ($) {
  /*
     |------------------------------------------------------------------------------------------------------------------
     | Wholesale Role Cart Total Price Based Discount
     |------------------------------------------------------------------------------------------------------------------
     */

  var discountTypeLabels = {
    "percent-discount": wp.i18n.__( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ),
    'fixed-discount': wp.i18n.__( 'Fixed Discount', 'woocommerce-wholesale-prices-premium' ),
  };

  var $wrapper = $("#wholesale-role-cart-total-based-discount-container"),
    $field_controls = $wrapper.find(".field-controls"),
    $mapping_index = $wrapper.find("#mapping-index"),
    $wholesale_roles = $wrapper.find("#wholesale-roles"),
    $subtotal_price = $wrapper.find("#subtotal-price"),
    $discount_type = $wrapper.find("#discount-type"),
    $discount_amount = $wrapper.find("#discount-amount"),
    $discount_title = $wrapper.find("#discount-title"),
    $button_controls = $wrapper.find(".button-controls"),
    $add_mapping = $button_controls.find("#add-mapping"),
    $save_mapping = $button_controls.find("#save-mapping"),
    $cancel_save_mapping = $button_controls.find("#cancel-edit-mapping"),
    $mapping_table = $wrapper.find("#wholesale-role-cart-total-discount-mapping"),
    error_message_duration = "10000",
    successMessageDuration = "5000";

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
    |------------------------------------------------------------------------------------------------------------------
    | Helper Functions
    |------------------------------------------------------------------------------------------------------------------
    */

  function construct_mapping_data(
    wholesale_role,
    subtotal_price,
    discount_type,
    discount_amount,
    discount_title,
    index
  ) {
    var err = [];

    wholesale_role = $.trim(wholesale_role);
    subtotal_price = parseFloat($.trim(subtotal_price)).toFixed(2);
    discount_type = $.trim(discount_type);
    discount_amount = parseFloat($.trim(discount_amount)).toFixed(2);
    discount_title = $.trim(discount_title);

    if (index) index = $.trim(index);

    if (wholesale_role == "") err.push( wp.i18n.__( 'Please specify a wholesale role', 'woocommerce-wholesale-prices-premium' ) );

    if (subtotal_price <= 0 || subtotal_price == "" || isNaN(subtotal_price))
      err.push( wp.i18n.__( 'Invalid subtotal price', 'woocommerce-wholesale-prices-premium' ) );

    if (discount_type == "") err.push( wp.i18n.__( 'Invalid discount type', 'woocommerce-wholesale-prices-premium' ) );

    if (discount_amount <= 0 || discount_amount == "" || isNaN(discount_amount))
      err.push( wp.i18n.__( 'Invalid discount amount', 'woocommerce-wholesale-prices-premium' ) );

    if (discount_title == "") err.push( wp.i18n.__( 'Invalid discount title', 'woocommerce-wholesale-prices-premium' ) );

    if (err.length <= 0) {
      var data = {
        wholesale_role: wholesale_role,
        subtotal_price: subtotal_price,
        discount_type: discount_type,
        discount_amount: discount_amount,
        discount_title: discount_title,
      };

      if (index) data.index = index;

      return {
        status: "success",
        data: data,
      };
    } else return { status: "fail", error: err };
  }

  function prepopulate_form_with_entry_data($tr, $field_controls, $button_controls) {
    var type = $.trim($tr.find(".discount_type").text()) == "Percent Discount" ? "percent-discount" : "fixed-discount";

    $field_controls.find("#mapping-index").val($.trim($tr.attr("data-index")));
    $field_controls
      .find("#wholesale-roles")
      .val($.trim($tr.find(".meta .wholesale-role").text()))
      .trigger("chosen:updated");
    $field_controls.find("#subtotal-price").val($.trim($tr.find(".subtotal_price").text()));
    $field_controls.find("#discount-type").val(type).trigger("chosen:updated");
    $field_controls.find("#discount-amount").val($.trim($tr.find(".discount_amount").text().replace("%", "")));
    $field_controls.find("#discount-title").val($.trim($tr.find(".discount_title").text()));

    $button_controls.removeClass("add-mode").addClass("edit-mode");
  }

  function form_in_processing_mode($field_controls, $button_controls) {
    $field_controls.find(".field-control").attr("disabled", "disabled");
    $button_controls.find(".button").attr("disabled", "disabled").siblings(".spinner").css("visibility", "visible");
  }

  function form_in_normal_mode($field_controls, $button_controls) {
    $field_controls.find(".field-control").removeAttr("disabled");
    $button_controls.find(".button").removeAttr("disabled").siblings(".spinner").css("visibility", "hidden");
  }

  function add_new_mapping_table_entry(index, data, $mapping_table) {
    let discount_amount = data.discount_type == "percent-discount" ? data.discount_amount + "%" : data.discount_amount;
    var entry_str =
      '<tr data-index="' +
      index +
      '">' +
      '<td class="meta hidden">' +
      '<span class="index">' +
      index +
      "</span>" +
      '<span class="wholesale-role">' +
      data.wholesale_role +
      "</span>" +
      '<span class="wholesale-discount">' +
      data.percent_discount +
      "</span>" +
      "</td>" +
      '<td class="wholesale_role">' +
      cart_subtotal_price_based_discount_params.wholesale_roles[data.wholesale_role].roleName +
      "</td>" +
      '<td class="subtotal_price">' +
      data.subtotal_price +
      "</td>" +
      '<td class="discount_type">' +
      discountTypeLabels[data.discount_type] +
      "</td>" +
      '<td class="discount_amount">' +
      discount_amount +
      "</td>" +
      '<td class="discount_title">' +
      data.discount_title +
      "</td>" +
      '<td class="controls">' +
      '<a class="edit dashicons dashicons-edit"></a>' +
      '<a class="delete dashicons dashicons-no"></a>' +
      "</td>" +
      "</tr>";

    if ($mapping_table.find("tbody tr.no-items").length > 0) $mapping_table.find("tbody").html(entry_str);
    else $mapping_table.find("tbody").append(entry_str);
  }

  function edit_mapping_table_entry(index, data, $mapping_table) {
    let discount_amount = data.discount_type == "percent-discount" ? data.discount_amount + "%" : data.discount_amount;
    $mapping_table
      .find("tbody tr[data-index='" + index + "']")
      .find("td.meta .wholesale-role")
      .text(data.wholesale_role)
      .end()
      .find("td.meta .wholesale-discount")
      .text(data.percent_discount)
      .end()
      .find("td.wholesale_role")
      .text(cart_subtotal_price_based_discount_params.wholesale_roles[data.wholesale_role].roleName)
      .end()
      .find("td.subtotal_price")
      .text(data.subtotal_price)
      .end()
      .find("td.discount_type")
      .text(discountTypeLabels[data.discount_type])
      .end()
      .find("td.discount_amount")
      .text(discount_amount)
      .end()
      .find("td.discount_title")
      .text(data.discount_title);
  }

  function remove_mapping_table_entry(index, $mapping_table) {
    $mapping_table.find("tbody tr[data-index='" + index + "']").remove();

    if ($mapping_table.find("tbody tr").length <= 0)
      $mapping_table
        .find("tbody")
        .append(
          '<tr class="no-items"><td class="colspanchange" colspan="5">' +
            wp.i18n.__( 'No Mappings Found', 'woocommerce-wholesale-prices-premium' ) +
            "</td></tr>"
        );
  }

  function reset_form($field_controls, $button_controls, $mapping_table) {
    form_in_normal_mode($field_controls, $button_controls);

    $button_controls.removeClass("edit-mode").addClass("add-mode");
    $field_controls.find(".field-control").val("");

    $field_controls.find("#wholesale-roles").trigger("chosen:updated");
    $field_controls.find("#discount-type").trigger("chosen:updated");

    $mapping_table.find("tbody tr").removeClass("editing").find("td.controls .dashicons").css("visibility", "visible");
  }

  /*
    |------------------------------------------------------------------------------------------------------------------
    | Events
    |------------------------------------------------------------------------------------------------------------------
    */

  // Add Mapping
  $add_mapping.on("click", function (e) {
    e.preventDefault();

    var result = construct_mapping_data(
      $wholesale_roles.val(),
      $subtotal_price.val(),
      $discount_type.val(),
      $discount_amount.val(),
      $discount_title.val()
    );

    if (result.status !== "success") {
      var err_msg = "";

      for (var i = 0; i < result.error.length; i++) err_msg += result.error[i] + "<br/>";

      toastr.error(err_msg, wp.i18n.__( 'Form Error', 'woocommerce-wholesale-prices-premium' ), {
        closeButton: true,
        showDuration: error_message_duration,
      });
    } else {
      form_in_processing_mode($field_controls, $button_controls);

      $.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "wwpp_add_wholesale_role_cart_subtotal_discount_mapping",
          cart_total_based_discount_mapping: result.data,
          nonce: cart_subtotal_price_based_discount_params.add_wholesale_role_cart_subtotal_discount_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status == "success") {
            toastr.success("", wp.i18n.__( 'Mapping Successfully Added', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: successMessageDuration,
            });
            add_new_mapping_table_entry(data.last_inserted_item_index, result.data, $mapping_table);
            reset_form($field_controls, $button_controls, $mapping_table);
          } else {
            toastr.error(data.error_msg, wp.i18n.__( 'Add Mapping Error', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: error_message_duration,
            });
            console.log(data);
          }
        })
        .fail(function (jqxhr) {
          toastr.error(
            wp.i18n.__( 'Failed to record new mapping entry', 'woocommerce-wholesale-prices-premium' ),
            wp.i18n.__( 'Add Mapping Error', 'woocommerce-wholesale-prices-premium' ),
            { closeButton: true, showDuration: error_message_duration }
          );
          console.log(jqxhr);
        })
        .always(function () {
          form_in_normal_mode($field_controls, $button_controls);
        });
    }
  });

  // Delete Mapping
  $mapping_table.on("click", "tbody td.controls .delete", function (e) {
    e.preventDefault();

    var $tr = $(this).closest("tr");

    $tr.addClass("editing");

    if (confirm( wp.i18n.__( 'Are you sure you want to remove this mapping?', 'woocommerce-wholesale-prices-premium' ) )) {
      $cancel_save_mapping.find("tbody tr td.controls .dashicons").css("visibility", "hidden");

      var index = $.trim($tr.attr("data-index"));

      $.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "wwpp_delete_wholesale_role_cart_subtotal_discount_mapping",
          index: index,
          nonce: cart_subtotal_price_based_discount_params.delete_wholesale_role_cart_subtotal_discount_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status === "success") {
            toastr.success("", wp.i18n.__( 'Mapping Successfully Deleted', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: successMessageDuration,
            });
            remove_mapping_table_entry(index, $mapping_table);
          } else {
            toastr.error(data.error_msg, wp.i18n.__( 'Delete Mapping Error', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: error_message_duration,
            });
            console.log(data);
          }
        })
        .fail(function (jqxhr) {
          toastr.error(
            wp.i18n.__( 'Failed to delete mapping', 'woocommerce-wholesale-prices-premium' ),
            wp.i18n.__( 'Delete Mapping Error', 'woocommerce-wholesale-prices-premium' ),
            { closeButton: true, showDuration: error_message_duration }
          );
          console.log(jqxhr);
        })
        .always(function () {
          $cancel_save_mapping.find("tbody tr td.controls .dashicons").css("visibility", "visible");

          if ($tr) $tr.removeClass("editing");
        });
    } else $tr.removeClass("editing");
  });

  // Edit Mapping
  $mapping_table.on("click", "tbody td.controls .edit", function (e) {
    e.preventDefault();

    var $tr = $(this).closest("tr");

    $tr.addClass("editing");

    prepopulate_form_with_entry_data($tr, $field_controls, $button_controls);

    $mapping_table.find("tbody tr td.controls .dashicons").css("visibility", "hidden");
  });

  // Cancel Mapping
  $cancel_save_mapping.click(function (e) {
    e.preventDefault();

    reset_form($field_controls, $button_controls, $mapping_table);

    $mapping_table
      .find("tbody tr")
      .removeClass("editing")
      .find("td.controls .dashicons")
      .css({ visibility: "visible" });
  });

  // Save Mapping
  $save_mapping.click(function (e) {
    e.preventDefault();

    var result = construct_mapping_data(
      $wholesale_roles.val(),
      $subtotal_price.val(),
      $discount_type.val(),
      $discount_amount.val(),
      $discount_title.val(),
      $mapping_index.val()
    );

    if (result.status !== "success") {
      var err_msg = "";

      for (var i = 0; i < result.error.length; i++) err_msg += result.error[i] + "<br/>";

      toastr.error(err_msg, wp.i18n.__( 'Form Error', 'woocommerce-wholesale-prices-premium' ), {
        closeButton: true,
        showDuration: error_message_duration,
      });
    } else {
      form_in_processing_mode($field_controls, $button_controls);

      $.ajax({
        url: ajaxurl,
        type: "post",
        data: {
          action: "wwpp_edit_wholesale_role_cart_subtotal_discount_mapping",
          cart_total_based_discount_mapping: result.data,
          nonce: cart_subtotal_price_based_discount_params.edit_wholesale_role_cart_subtotal_discount_mapping_nonce,
        },
        dataType: "json",
      })
        .done(function (data) {
          if (data.status == "success") {
            toastr.success("", wp.i18n.__( 'Mapping Successfully Updated', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: successMessageDuration,
            });

            edit_mapping_table_entry(result.data.index, result.data, $mapping_table);
            reset_form($field_controls, $button_controls, $mapping_table);
          } else {
            toastr.error(data.error_msg, wp.i18n.__( 'Edit Mapping Error', 'woocommerce-wholesale-prices-premium' ), {
              closeButton: true,
              showDuration: error_message_duration,
            });
            console.log(data);
          }
        })
        .fail(function (jqxhr) {
          toastr.error(
            wp.i18n.__( 'Failed to edit mapping', 'woocommerce-wholesale-prices-premium' ),
            wp.i18n.__( 'Edit Mapping Error', 'woocommerce-wholesale-prices-premium' ),
            { closeButton: true, showDuration: error_message_duration }
          );
          console.log(jqxhr);
        })
        .always(function () {
          form_in_normal_mode($field_controls, $button_controls);
        });
    }
  });

  /*
    |------------------------------------------------------------------------------------------------------------------
    | On Page Load
    |------------------------------------------------------------------------------------------------------------------
    */

  $wholesale_roles.chosen({ allow_single_deselect: true });
  $discount_type.chosen({ allow_single_deselect: true });
});
