jQuery(document).ready(function($) {
  $("#clear-var-prod-price-range-cache").click(function() {
    
    if(confirm(wp.i18n.__( 'Are you sure want to clear the cache?', 'woocommerce-wholesale-prices-premium' ))){

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
            wp.i18n.__( 'Failed to clear variable product price range cache', 'woocommerce-wholesale-prices-premium' )
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

    if(confirm(wp.i18n.__( 'Are you sure want to clear the cache?', 'woocommerce-wholesale-prices-premium' ))){

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
            wp.i18n.__( 'Failed to clear products transient cache', 'woocommerce-wholesale-prices-premium' )
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
