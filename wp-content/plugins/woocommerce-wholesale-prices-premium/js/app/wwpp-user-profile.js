jQuery(document).ready(function ($) {
  // =======================================================================
  // Init
  // =======================================================================

  var $form = $("#wwpp-per-wholesale-user-settings");
  ($override_min_order_qty = $form.find("#wwpp_override_min_order_qty")),
    ($override_min_order_price = $form.find("#wwpp_override_min_order_price")),
    ($override_wholesale_discount = $form.find("#wwpp_override_wholesale_discount")),
    ($override_qty_discount_mapping = $form.find("#wwpp_override_wholesale_discount_qty_discount_mapping")),
    ($override_shipping_options = $form.find("#wwpp_override_shipping_options")),
    ($override_payment_gateway_options = $form.find("#wwpp_override_payment_gateway_options")),
    ($override_payment_gateway_surcharge = $form.find("#wwpp_override_payment_gateway_surcharge")),
    ($min_order_qty = $form.find("#wwpp_min_order_qty")),
    ($min_order_price = $form.find("#wwpp_min_order_price")),
    ($min_order_logic = $form.find("#wwpp_min_order_logic")),
    ($wholesale_discount = $form.find("#wwpp_wholesale_discount")),
    ($qty_discount_mapping_mode2 = $form.find("#wwpp_wholesale_discount_qty_discount_mapping_mode_2")),
    ($qty_discount_mapping = $form.find("#wwpp_wholesale_discount_qty_discount_mapping")),
    ($shipping_method_type = $form.find("#wwpp_shipping_methods_type")),
    ($hide_selected_methods = $form.find("#wwpp_hide_selected_methods_from_others")),
    ($shipping_zone = $form.find("#wwpp_shipping_zone")),
    ($shipping_methods = $form.find("#wwpp_shipping_methods")),
    ($specify_non_zoned_method = $form.find("#wwpp_specify_non_zoned_shipping_methods")),
    ($non_zoned_methods = $form.find("#wwpp_non_zoned_shipping_methods")),
    ($payment_gateway_options = $form.find("#wwpp_payment_gateway_options")),
    ($payment_gateway_surcharge_mapping = $form.find("#wwpp_payment_gateway_surcharge_mapping"));

  // =======================================================================
  // Helper Functions
  // =======================================================================

  /**
   * Filter field to only allow numbers to input.
   *
   * @since 1.16.0
   *
   * @param object e Event object.
   */
  function filter_number_input(e) {
    var filter_arr = [46, 8, 9, 27, 13, 110],
      element_id = $(e.target).attr("id");

    if (element_id === "wwpp_min_order_price" || element_id === "wwpp_wholesale_discount") filter_arr.push(190); // Allow .

    // Allow: backspace, delete, tab, escape, enter and .
    if (
      $.inArray(e.keyCode, filter_arr) !== -1 ||
      // Allow: Ctrl+A, Command+A
      (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
      // Allow: home, end, left, right, down, up
      (e.keyCode >= 35 && e.keyCode <= 40)
    ) {
      // let it happen, don't do anything
      return;
    }

    // Ensure that it is a number and stop the keypress
    if ((e.shiftKey || e.keyCode < 48 || e.keyCode > 57) && (e.keyCode < 96 || e.keyCode > 105)) e.preventDefault();
  }

  // =======================================================================
  // Min Order Qty
  // =======================================================================

  $override_min_order_qty.change(function () {
    if ($(this).val() === "yes") {
      $form.find("#wwpp_min_order_qty-tr").slideDown("fast");

      if ($override_min_order_price.val() === "yes") $form.find("#wwpp_min_order_logic-tr").slideDown("fast");
      else $form.find("#wwpp_min_order_logic-tr").slideUp("fast");
    } else {
      $form.find("#wwpp_min_order_qty-tr").slideUp("fast");
      $form.find("#wwpp_min_order_logic-tr").slideUp("fast");
    }
  });

  $override_min_order_qty.trigger("change");

  $min_order_qty.keydown(filter_number_input);

  // =======================================================================
  // Min Order Subtotal
  // =======================================================================

  $override_min_order_price.change(function () {
    if ($(this).val() === "yes") {
      $form.find("#wwpp_min_order_price-tr").slideDown("fast");

      if ($override_min_order_qty.val() === "yes") $form.find("#wwpp_min_order_logic-tr").slideDown("fast");
      else $form.find("#wwpp_min_order_logic-tr").slideUp("fast");
    } else {
      $form.find("#wwpp_min_order_price-tr").slideUp("fast");
      $form.find("#wwpp_min_order_logic-tr").slideUp("fast");
    }
  });

  $override_min_order_price.trigger("change");

  $min_order_qty.keydown(filter_number_input);

  // =======================================================================
  // Wholesale Discount
  // =======================================================================

  $override_qty_discount_mapping.change(function () {
    if ($(this).val() === "specify_general_per_wholesale_role_qty_mapping") {
      $form.find("#wwpp_wholesale_discount_qty_discount_mapping_mode_2-tr").slideDown("fast");
      $form.find("#wwpp_wholesale_discount_qty_discount_mapping-tr").slideDown("fast");
    } else {
      $form.find("#wwpp_wholesale_discount_qty_discount_mapping_mode_2-tr").slideUp("fast");
      $form.find("#wwpp_wholesale_discount_qty_discount_mapping-tr").slideUp("fast");
    }
  });

  $override_wholesale_discount.change(function () {
    if ($(this).val() === "yes") {
      $form.find("#wwpp_wholesale_discount-tr").slideDown("fast");
      $form.find("#wwpp_override_wholesale_discount_qty_discount_mapping-tr").slideDown("fast");

      $override_qty_discount_mapping.trigger("change");
    } else {
      $form.find("#wwpp_wholesale_discount-tr").slideUp("fast");
      $form.find("#wwpp_override_wholesale_discount_qty_discount_mapping-tr").slideUp("fast");
      $form.find("#wwpp_wholesale_discount_qty_discount_mapping_mode_2-tr").slideUp("fast");
      $form.find("#wwpp_wholesale_discount_qty_discount_mapping-tr").slideUp("fast");
    }
  });

  $wholesale_discount.keydown(filter_number_input);

  $override_wholesale_discount.trigger("change");

  // =======================================================================
  // Shipping Options
  // =======================================================================

  $shipping_methods.chosen();
  $non_zoned_methods.chosen();

  $shipping_zone.on("change", function (event) {
    var $this = $(this),
      zone_id = $this.val();

    $this.attr("disabled", "disabled");
    $shipping_methods.attr("disabled", "disabled").trigger("chosen:updated");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "wwpp_get_zone_shipping_methods",
        zone_id: zone_id,
        nonce: wwpp_user_profile_args.wwpp_get_zone_shipping_methods_nonce,
      },
      dataType: "json",
    })
      .done(function (data, text_status, jqxhr) {
        if (data.status == "success") {
          var shipping_method_options = "";

          for (var method_instance_id in data.shipping_methods) {
            if (data.shipping_methods.hasOwnProperty(method_instance_id)) {
              var selected = "";
              if (
                zone_id == wwpp_user_profile_args.wwpp_shipping_zone &&
                wwpp_user_profile_args.wwpp_zone_shipping_methods
              )
                selected =
                  wwpp_user_profile_args.wwpp_zone_shipping_methods.indexOf(parseInt(method_instance_id, 10)) > -1
                    ? "selected"
                    : "";

              shipping_method_options +=
                '<option value="' +
                method_instance_id +
                '" ' +
                selected +
                ">" +
                data.shipping_methods[method_instance_id] +
                "</option>";
            }
          }

          $shipping_methods.html(shipping_method_options);
        } else {
          console.log(data);
          alert(data.error_message);
        }
      })
      .fail(function (jqxhr, text_status, error_thrown) {
        console.log(jqxhr);
        alert(wwpp_user_profile_args.i18n_failed_to_get_zoned_methods);
      })
      .always(function () {
        $this.removeAttr("disabled");
        $shipping_methods.removeAttr("disabled").trigger("chosen:updated");
      });
  });

  $specify_non_zoned_method.change(function () {
    if ($(this).val() === "yes") $form.find("#wwpp_non_zoned_shipping_methods-tr").slideDown("fast");
    else $form.find("#wwpp_non_zoned_shipping_methods-tr").slideUp("fast");
  });

  $shipping_method_type.change(function () {
    if ($(this).val() === "specify_shipping_methods") {
      $form.find("#wwpp_hide_selected_methods_from_others-tr").slideDown("fast");
      $form.find("#wwpp_shipping_zone-tr").slideDown("fast");
      $form.find("#wwpp_shipping_methods-tr").slideDown("fast");
      $form.find("#wwpp_specify_non_zoned_shipping_methods-tr").slideDown("fast");

      $shipping_zone.trigger("change");
      $specify_non_zoned_method.trigger("change");
    } else {
      $form.find("#wwpp_hide_selected_methods_from_others-tr").slideUp("fast");
      $form.find("#wwpp_shipping_zone-tr").slideUp("fast");
      $form.find("#wwpp_shipping_methods-tr").slideUp("fast");
      $form.find("#wwpp_specify_non_zoned_shipping_methods-tr").slideUp("fast");
      $form.find("#wwpp_non_zoned_shipping_methods-tr").slideUp("fast");
    }
  });

  $override_shipping_options.change(function () {
    if ($(this).val() === "yes") {
      $form.find("#wwpp_shipping_methods_type-tr").slideDown("fast");
      $shipping_method_type.trigger("change");
    } else {
      $form.find("#wwpp_shipping_methods_type-tr").slideUp("fast");
      $form.find("#wwpp_hide_selected_methods_from_others-tr").slideUp("fast");
      $form.find("#wwpp_shipping_zone-tr").slideUp("fast");
      $form.find("#wwpp_shipping_methods-tr").slideUp("fast");
      $form.find("#wwpp_specify_non_zoned_shipping_methods-tr").slideUp("fast");
      $form.find("#wwpp_non_zoned_shipping_methods-tr").slideUp("fast");
    }
  });

  $override_shipping_options.trigger("change");

  // =======================================================================
  // Payment Gateway Options
  // =======================================================================

  $payment_gateway_options.chosen();

  $override_payment_gateway_options.change(function () {
    if ($(this).val() === "yes") $form.find("#wwpp_payment_gateway_options-tr").slideDown("fast");
    else $form.find("#wwpp_payment_gateway_options-tr").slideUp("fast");
  });

  $override_payment_gateway_surcharge.change(function () {
    if ($(this).val() === "specify_surcharge_mapping")
      $form.find("#wwpp_payment_gateway_surcharge_mapping-tr").slideDown("fast");
    else $form.find("#wwpp_payment_gateway_surcharge_mapping-tr").slideUp("fast");
  });

  $override_payment_gateway_options.trigger("change");
  $override_payment_gateway_surcharge.trigger("change");

  // =======================================================================
  // Before Submitting Data
  // =======================================================================

  $("#submit").click(function (e) {
    // Shipping Methods
    var shipping_methods = $shipping_methods.val();

    if (shipping_methods && shipping_methods.length > 0) {
      for (var i = 0; i < shipping_methods.length; i++)
        $form
          .find("#wwpp_shipping_methods-tr")
          .append('<input type="hidden" name="wwpp_shipping_methods[]" value="' + shipping_methods[i] + '">');
    } else $form.find("#wwpp_shipping_methods-tr").append('<input type="hidden" name="wwpp_shipping_methods" value="">');

    // Non zoned shipping methods
    var non_shipping_methods = $non_zoned_methods.val();

    if (non_shipping_methods && non_shipping_methods.length > 0) {
      for (var i = 0; i < non_shipping_methods.length; i++)
        $form
          .find("#wwpp_non_zoned_shipping_methods-tr")
          .append(
            '<input type="hidden" name="wwpp_non_zoned_shipping_methods[]" value="' + non_shipping_methods[i] + '">'
          );
    } else $form.find("#wwpp_non_zoned_shipping_methods-tr").append('<input type="hidden" name="wwpp_non_zoned_shipping_methods" value="">');

    // Payment gateway Options
    var payment_gateways = $payment_gateway_options.val();

    if (payment_gateways && payment_gateways.length > 0) {
      for (var i = 0; i < payment_gateways.length; i++)
        $form
          .find("#wwpp_payment_gateway_options-tr")
          .append('<input type="hidden" name="wwpp_payment_gateway_options[]" value="' + payment_gateways[i] + '">');
    } else $form.find("#wwpp_payment_gateway_options-tr").append('<input type="hidden" name="wwpp_payment_gateway_options" value="">');
  });
});
