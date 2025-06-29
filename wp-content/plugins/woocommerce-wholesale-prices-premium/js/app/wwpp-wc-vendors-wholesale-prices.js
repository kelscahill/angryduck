jQuery(function ($) {
    const wcvIntegration = {
      init: function () {
        this.initPricingFields();
        $("body").on(
          "change",
          ".wholesale_discount_type",
          this.handleDiscountTypeChange
        );

        $("body").on(
          "keyup",
          ".wholesale_discount",
          this.handleWholesaleDiscountChanged
        );

        // Update price if regular price changes.
        $("body").on(
          "keyup",
          "#_regular_price",
          this.handleSimpleRegularPriceChanged
        );

        $("body").on(
          "keyup",
          ".variable_regular_price",
          this.handleVariableRegularPriceChange
        );

        $("body").on(
          'change',
          '#wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
          this.toggleVendorsSettings
        )

        // Initialize the vendor settings.
        this.toggleVendorsSettings();

        // Initialize wholesale sale price schedule fields.
        this.wholesaleSalePriceSchedules();

        // Detect wcv_variation is added then initialize select2 on the variation.
        this.observe();
      },

      /**
       * Initialize the pricing fields when page is loaded.
       */
      initPricingFields: function () {
        var $discount_types = $(".wholesale_discount_type");

        $.each(
          $discount_types,
          function (index, $element) {
            wcvIntegration.togglePriceFields($element);
          }
        );
      },

      /**
       * Get the regular price field
       *
       * Checks the product type and returns the appropriate regular price field for simple/variable products.
       */
      getRegularPriceField: function( $element ) {
        const productType = $('#product-type').val();

        var $regularPriceField = 'simple' == productType
          ? $element.closest('#general').find('#_regular_price')
          : $element.closest('.wcv_variation').find('.variable_regular_price');

          return $regularPriceField;
      },

      /**
       * Handle discount type changed
       *
       * Fire wholesale price calculation when discount type has changed.
       */
      handleDiscountTypeChange: function () {
        var $element = $(this);

        wcvIntegration.togglePriceFields($element);

        if ($element.val() === "percentage") {
          const $regularPriceField = wcvIntegration.getRegularPriceField($element);
          wcvIntegration.calculateWholesalePrice($element, $regularPriceField)
        }
      },

      /**
       * Handle wholesale discount field changed.
       *
       * Fires the function to calculate the wholesale price when the discount has changed.
       */
      handleWholesaleDiscountChanged: function (e) {
        var $element = $(this);

        const $regularPriceField = wcvIntegration.getRegularPriceField($element);

        wcvIntegration.calculateWholesalePrice( $element, $regularPriceField );
      },

      /**
       * Handle simple product regular price changed.
       *
       * Fires a function to calculate the wholesale price when a simple product's regular price has changed.
       */
      handleSimpleRegularPriceChanged: function( ) {
        const $general = $(this).closest('#general');

        const $wholesalePrices = $($general).find(".wholesale-prices-fields--simple");

        $.each( $wholesalePrices, function( index, $el) {
          var $element = $($el).find( ".wholesale_discount_type" );
          var $regularPriceField = $element.closest('#general').find('#_regular_price');
          wcvIntegration.calculateWholesalePrice( $element, $regularPriceField );
        } );
      },

      /**
       * Handle variable regular price changed
       *
       * Fires a function to calculate the wholesale price when a variation's regular price has changed.
       */
      handleVariableRegularPriceChange: function () {
        const $variation = $(this).closest('.wcv_variation');

        const $variationPrices = $variation.find(".wholesale-prices-fields--variation");

        $.each($variationPrices, function (index, $el) {
          console.log("Updating variation ", index);
          var $element = $($el).find(".wholesale_discount_type");
          var $regularPriceField = $element.closest('.wcv_variation').find('.variable_regular_price');
          wcvIntegration.calculateWholesalePrice($element, $regularPriceField);
        });
      },

      /**
       * Calculate the wholesale price for a wholesale role.
       *
       * @param {string|DOMElement} element The element related the set of fields to update. Usually the element firing the event or manually specified.
       * @param {DOMElement} $regularPriceField The regular price field to calculate the price from.
       */
      calculateWholesalePrice: function (element, $regularPriceField) {
        // Get the elements.
        var $element = element ?? $(this);
        var $pricingContainer = $element.closest(".wcv-wholesale-prices-fields");

        var discountType = $pricingContainer.find(".wholesale_discount_type").val();
        var wholesalePrice = parseFloat($pricingContainer.find(".wholesale_price").val());
        var wholesaleDiscount = parseFloat($pricingContainer.find(".wholesale_discount").val());

        // Get the Regular Price.
        regularPrice = parseFloat($regularPriceField.val()) ?? 0;

        // Calculate the wholesale price with discount.
        if ( ! isNaN( regularPrice ) && regularPrice > 0 && discountType === "percentage" ) {
          wholesalePrice = regularPrice - parseFloat( regularPrice * (wholesaleDiscount / 100) ) ;
        }

        if ( isNaN(wholesalePrice) || 0 === wholesalePrice ) {
          wholesalePrice = '';
        }

        const formattedPrice = wcvIntegration.formaPrice(wholesalePrice);

        $pricingContainer.find(".wholesale_price").val(formattedPrice);
      },

      /**
       * Get the pricing fields and toggle which ones are visible based on discount type.
       *
       * @param {string} element The element related to the pricing fields.
       */
      togglePriceFields: function (element) {
        var $pricingContainer = $(element).closest(
          ".wcv-wholesale-prices-fields"
        );
        var $wholesalePriceField = $pricingContainer.find(".wholesale_price");
        var $wholesaleDiscountField = $pricingContainer.find(
          ".wholesale_discount"
        );

        var value = $(element).val();

        switch (value) {
          case "percentage":
            $wholesalePriceField.attr("readonly", true);
            $wholesaleDiscountField.attr("readonly", false);
            $wholesalePriceField.parent().parent().show();
            $wholesaleDiscountField.parent().parent().show();
            break;
          case "fixed":
            $wholesalePriceField.attr("readonly", false);
            $wholesaleDiscountField.attr("readonly", true);
            $wholesalePriceField.parent().parent().show();
            $wholesaleDiscountField.parent().parent().hide();
            break;
        }
      },

      /**
       * Initialize event handles for wholesale sale price date fields.
       */
      wholesaleSalePriceSchedules: function() {
        // Sale price schedule
        $('.wholesale_sale_price_dates_fields').each(function () {
          var sale_schedule_set = false;

          const $parentContainer = $(this).closest('.wwpp-wcv-wholesale-sale-dates-fields');

          $('.wholesale_sale_price_dates_fields')
            .find('input')
            .each(function () {
              if ($(this).val() != '') {
                sale_schedule_set = true;
              }
            });

            if (sale_schedule_set) {
              $parentContainer.find('.wholesale_sale_schedule').hide();
              $parentContainer.find('.wholesale_sale_price_dates_fields').show();
            } else {
              $parentContainer.find('.wholesale_sale_schedule').show();
              $parentContainer.find('.wholesale_sale_price_dates_fields').hide();
            }
        });

        $('.wholesale_sale_schedule').on('click', function () {
          const $parentContainer = $(this).closest('.wwpp-wcv-wholesale-sale-dates-fields');
          $parentContainer.find('.wholesale_sale_price_dates_fields').show();
          $(this).hide();
          $parentContainer.find('.cancel_wholesale_sale_schedule').show();
          return false;
        });

        $('.cancel_wholesale_sale_schedule').on('click', function () {
          const $parentContainer = $(this).closest('.wwpp-wcv-wholesale-sale-dates-fields');
          $parentContainer.find('.wholesale_sale_price_dates_fields').hide();
          $(this).hide();
          $parentContainer.find('.wholesale_sale_schedule').show();
          return false;
        });
      },

      /**
       * Toggle the locations & roles fields visibility
       */
      toggleVendorsSettings: function() {
        const $showPricesSwitch = $('#wwp_prices_settings_show_wholesale_prices_to_non_wholesale');

        if ( $showPricesSwitch.is(':checked') ) {
          $('#wwpp_show_if_vendors_show_prices').show();
        } else {
          $('#wwpp_show_if_vendors_show_prices').hide();
        }
      },

      /**
       * Setup a mutation observer to observe when a variation is added to the dom
       */
      observe: function() {
        // Set up MutationObserver to detect added elements with class 'wcv_variation'
        const observer = new MutationObserver(function (mutations) {
          mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
              if (
                node.nodeType === 1 &&
                node.classList.contains("wcv_variation")
              ) {
                // Initialize select2 if the variation is added.
                $(node).find(".select2").select2();
              }
            });
          });
        });

        // Start observing the document body for added nodes
        observer.observe(document.body, {
          childList: true,
          subtree: true,
        });
      },

      /**
       * Format the price using WooCommerce decimal/thousand separator and number of decimals.
       *
       * @param {mixed} price The price to format.
       * @returns string price The formatted price.
       */
      formaPrice: function (price, ) {
        const { decimal_sep, decimal_num, thousand_sep } = window.wwpp_wcvendors_params;

        if (price === '' || price === null) {
          return '';
        }

        // Convert to a number to ensure proper formatting.
        const formattedPrice = parseFloat(price).toFixed(decimal_num);

        // Format the price with the provided separators.
        return formattedPrice.toString().replace('.', decimal_sep).replace(/\B(?=(\d{3})+(?!\d))/g, thousand_sep);
      }
    };
    wcvIntegration.init();
});
