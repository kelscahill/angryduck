jQuery(document).ready(function ($) {
  var $initialize_product_visibility_meta_btn = $("#initialize-product-visibility-meta"),
    $clear_unused_product_meta_btn = $("#clear-unused-product-meta"),
    $force_fetch_update_data_btn = $("#force-fetch-update-data"),
    error_message_duration = "10000",
    success_message_duration = "5000";

  $clear_unused_product_meta_btn.click(function () {
    var $this = $(this);

    $this.attr("disabled", "disabled").siblings(".spinner").css("display", "inline-block").css("visibility", "visible");

    wwppBackendAjaxServices
      .clear_unused_product_meta()
      .done(function (data, textStatus, jqXHR) {
        if (data.status == "success") {
          toastr.success("", wwpp_settings_debug_var.success_clear_unused_product_meta_txt, {
            closeButton: true,
            showDuration: success_message_duration,
          });
        } else {
          toastr.error("", wwpp_settings_debug_var.failed_clear_unused_product_meta_txt, {
            closeButton: true,
            showDuration: error_message_duration,
          });

          console.log(wwpp_settings_debug_var.failed_clear_unused_product_meta_txt);
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error("", wwpp_settings_debug_var.failed_clear_unused_product_meta_txt, {
          closeButton: true,
          showDuration: error_message_duration,
        });

        console.log(wwpp_settings_debug_var.failed_clear_unused_product_meta_txt);
        console.log(jqXHR);
        console.log("----------");
      })
      .always(function () {
        $this.removeAttr("disabled").siblings(".spinner").css("display", "none").css("visibility", "hidden");
      });
  });

  $initialize_product_visibility_meta_btn.click(function () {
    var $this = $(this);

    $this.attr("disabled", "disabled").siblings(".spinner").css("display", "inline-block").css("visibility", "visible");

    wwppBackendAjaxServices
      .initialize_product_visibility_meta()
      .done(function (data, textStatus, jqXHR) {
        if (data.status == "success") {
          toastr.success("", wwpp_settings_debug_var.success_initialize_visibility_meta_txt, {
            closeButton: true,
            showDuration: success_message_duration,
          });
        } else {
          toastr.error("", wwpp_settings_debug_var.failed_initialize_visibility_meta_txt, {
            closeButton: true,
            showDuration: error_message_duration,
          });

          console.log(wwpp_settings_debug_var.failed_initialize_visibility_meta_txt);
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error("", wwpp_settings_debug_var.failed_initialize_visibility_meta_txt, {
          closeButton: true,
          showDuration: error_message_duration,
        });

        console.log(wwpp_settings_debug_var.failed_initialize_visibility_meta_txt);
        console.log(jqXHR);
        console.log("----------");
      })
      .always(function () {
        $this.removeAttr("disabled").siblings(".spinner").css("display", "none").css("visibility", "hidden");
      });
  });

  $force_fetch_update_data_btn.click(function (e) {
    var $this = $(this);

    if (!confirm($(this).data("confirm"))) {
      e.stopImmediatePropagation();
      e.preventDefault();
      return;
    }

    $this.attr("disabled", "disabled").siblings(".spinner").css("display", "inline-block").css("visibility", "visible");

    wwppBackendAjaxServices
      .force_fetch_update_data()
      .done(function (data, textStatus, jqXHR) {
        if (data.status == "success") {
          toastr.success("", wwpp_settings_debug_var.success_force_fetch_update_data_txt, {
            closeButton: true,
            showDuration: success_message_duration,
          });
        } else {
          toastr.error("", wwpp_settings_debug_var.failed_force_fetch_update_data_txt, {
            closeButton: true,
            showDuration: error_message_duration,
          });

          console.log(wwpp_settings_debug_var.failed_force_fetch_update_data_txt);
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error("", wwpp_settings_debug_var.failed_force_fetch_update_data_txt, {
          closeButton: true,
          showDuration: error_message_duration,
        });

        console.log(wwpp_settings_debug_var.failed_force_fetch_update_data_txt);
        console.log(jqXHR);
        console.log("----------");
      })
      .always(function () {
        $this.removeAttr("disabled").siblings(".spinner").css("display", "none").css("visibility", "hidden");
      });
  });
});
