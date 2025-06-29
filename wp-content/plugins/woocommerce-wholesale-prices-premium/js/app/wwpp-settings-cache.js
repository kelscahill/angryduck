jQuery(document).ready(function($) {
  $("#clear-var-prod-price-range-cache").click(function() {
    
    if(confirm(wwpp_settings_cache_args.i18n_confirm_clear_cache)){

      var $this = $(this);

      $this
      .attr("disabled", "disabled")
      .siblings(".spinner")
      .css("visibility", "visible");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_regenerate_new_cache",
          "ajax-nonce": wwpp_settings_cache_args.nonce_regenerate_new_cache_hash
        },
        dataType: "json"
      })
        .done(function(data) {
          if (data.status === "success") alert(data.success_msg);
          else {
            alert(data.error_msg);
            console.log(data);
          }
        })
        .fail(function(jqxhr) {
          alert(
            wwpp_settings_cache_args.i18n_fail_var_prod_price_range_clear_cache
          );
          console.log(jqxhr);
        })
        .always(function() {
          $this
            .removeAttr("disabled")
            .siblings(".spinner")
            .css("visibility", "hidden");
        });

    }
    
  });

  $("#wwpp_clear_product_caching").click(function() {

    if(confirm(wwpp_settings_cache_args.i18n_confirm_clear_cache)){

      var $this = $(this);

      $this
        .attr("disabled", "disabled")
        .siblings(".spinner")
        .css("visibility", "visible");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wwpp_clear_product_transients_cache",
          "ajax-nonce":
            wwpp_settings_cache_args.nonce_clear_product_transients_cache
        },
        dataType: "json"
      })
        .done(function(data) {
          if (data.status === "success") alert(data.success_msg);
          else {
            alert(data.error_msg);
            console.log(data);
          }
        })
        .fail(function(jqxhr) {
          alert(
            wwpp_settings_cache_args.i18n_fail_clear_product_transients_cache
          );
          console.log(jqxhr);
        })
        .always(function() {
          $this
            .removeAttr("disabled")
            .siblings(".spinner")
            .css("visibility", "hidden");
        });

    }
    
  });
});
