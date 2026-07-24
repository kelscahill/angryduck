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
          toastr.success("", wp.i18n.__( 'Successfully cleared unused product meta.', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: success_message_duration,
          });
        } else {
          toastr.error("", wp.i18n.__( 'Failed to clear unused product meta.', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: error_message_duration,
          });

          console.log(wp.i18n.__( 'Failed to clear unused product meta.', 'woocommerce-wholesale-prices-premium' ));
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error("", wp.i18n.__( 'Failed to clear unused product meta.', 'woocommerce-wholesale-prices-premium' ), {
          closeButton: true,
          showDuration: error_message_duration,
        });

        console.log(wp.i18n.__( 'Failed to clear unused product meta.', 'woocommerce-wholesale-prices-premium' ));
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
          toastr.success("", wp.i18n.__( 'Visibility Meta Successfully Initialized', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: success_message_duration,
          });
        } else {
          toastr.error("", wp.i18n.__( 'Failed To Initialize Visibility Meta', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: error_message_duration,
          });

          console.log(wp.i18n.__( 'Failed To Initialize Visibility Meta', 'woocommerce-wholesale-prices-premium' ));
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error("", wp.i18n.__( 'Failed To Initialize Visibility Meta', 'woocommerce-wholesale-prices-premium' ), {
          closeButton: true,
          showDuration: error_message_duration,
        });

        console.log(wp.i18n.__( 'Failed To Initialize Visibility Meta', 'woocommerce-wholesale-prices-premium' ));
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
          toastr.success("", wp.i18n.__( 'Successfully re-fetched plugin update data', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: success_message_duration,
          });
        } else {
          toastr.error("", wp.i18n.__( 'Failed to re-fetch plugin update data', 'woocommerce-wholesale-prices-premium' ), {
            closeButton: true,
            showDuration: error_message_duration,
          });

          console.log(wp.i18n.__( 'Failed to re-fetch plugin update data', 'woocommerce-wholesale-prices-premium' ));
          console.log(data);
          console.log("----------");
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        toastr.error("", wp.i18n.__( 'Failed to re-fetch plugin update data', 'woocommerce-wholesale-prices-premium' ), {
          closeButton: true,
          showDuration: error_message_duration,
        });

        console.log(wp.i18n.__( 'Failed to re-fetch plugin update data', 'woocommerce-wholesale-prices-premium' ));
        console.log(jqXHR);
        console.log("----------");
      })
      .always(function () {
        $this.removeAttr("disabled").siblings(".spinner").css("display", "none").css("visibility", "hidden");
      });
  });
});
