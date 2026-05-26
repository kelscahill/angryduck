/**
 * @typedef {Object} WPayCheckout
 * @property {string} nonce REST API nonce for authentication.
 * @property {string} restUrl Base REST API URL for Stripe operations.
 * @property {string} checkoutRestUrl REST API URL for checkout operations.
 * @property {string|null} customerId Stripe customer ID.
 * @property {boolean} overallAutoCharge Whether global auto-charge is enabled.
 * @property {boolean} wpayDebug Whether debug mode is enabled.
 * @property {Object} i18n Internationalization strings.
 * @property {string} i18n.savePaymentMethod
 * @property {string} i18n.savingPaymentMethod
 * @property {string} i18n.saved
 * @property {string} i18n.error
 * @property {string} i18n.paymentMethodNotInitialized
 * @property {string} i18n.completePaymentInformation
 * @property {string} i18n.customerInfoNotAvailable
 * @property {string} i18n.processingPaymentMethod
 * @property {string} i18n.failedToCreatePaymentMethod
 * @property {string} i18n.failedToSavePaymentMethod
 * @property {string} i18n.placeOrder
 * @property {string} i18n.unableToCreateStripeCustomer
 * @property {string} i18n.paymentInformationReady
 */

/**
 * @type {WPayCheckout|undefined}
 */
window.wpayCheckout;

/**
 * Configuration constants for Wholesale Payments.
 *
 * @constant {Object} WPAY_CONFIG
 * @property {number} MAX_RETRIES Maximum number of retry attempts for payment method attachment.
 * @property {number} RETRY_DELAY_BASE_MS Base delay in milliseconds for retry attempts (multiplied by attempt number).
 * @property {number} RESUBMIT_DELAY_MS Delay in milliseconds before resubmitting checkout form.
 * @property {number} SCROLL_OFFSET_PX Offset in pixels when scrolling to error notices.
 * @property {number} SCROLL_DURATION_MS Duration in milliseconds for scroll animation.
 */
// Define WPAY_CONFIG only if it doesn't already exist (may be declared in other scripts).
if (typeof window.WPAY_CONFIG === 'undefined') {
  window.WPAY_CONFIG = {
    MAX_RETRIES: 3,
    RETRY_DELAY_BASE_MS: 1000,
    RESUBMIT_DELAY_MS: 100,
    SCROLL_OFFSET_PX: 100,
    SCROLL_DURATION_MS: 300,
  };
}
// Use window.WPAY_CONFIG directly to avoid const redeclaration errors

// Global variables to track payment method state for classic checkout
let classicStripeInstance = null;
let classicCardElement = null;
let classicIsPaymentElementValid = false;
let classicCurrentCustomerId = null;

// Track resubmission timeout to prevent race conditions
let resubmitTimeout = null;

/**
 * Handle Wholesale Payments errors consistently.
 *
 * Extracts error messages from multiple sources and provides user-friendly feedback.
 * Maps common error codes to localized messages and uses WooCommerce notice system.
 *
 * @param {Object|Error} error Error object from AJAX request or exception.
 * @param {string} context Context where error occurred (e.g., 'attachPaymentMethod', 'savePaymentMethod').
 * @param {string} fallbackMessage Fallback message if error extraction fails.
 * @return {string} User-friendly error message.
 */
function handleWPayError(error, context, fallbackMessage) {
  let errorMsg =
    error?.responseJSON?.message ||
    error?.message ||
    (window.wpayCheckout?.i18n?.[`error${context}`]) ||
    fallbackMessage ||
    "An error occurred. Please try again.";

  // Map common error codes to user-friendly messages
  if (error?.status === 401 || error?.status === 403) {
    errorMsg =
      window.wpayCheckout?.i18n?.authError ||
      "Authentication error. Please refresh the page and try again.";
  } else if (error?.status === 0 || error?.statusText === "timeout") {
    errorMsg =
      window.wpayCheckout?.i18n?.networkError ||
      "Network error. Please check your connection and try again.";
  }

  // Debug logging
  if (window.wpayCheckout?.wpayDebug) {
    console.error(`[WPay] Error in ${context}:`, error);
  }

  return errorMsg;
}

/**
 * Show WooCommerce-style error notice on checkout page.
 *
 * @param {string} message Error message to display.
 */
function showCheckoutError(message) {
  // Remove any existing error notices from this plugin
  jQuery('.woocommerce-checkout .wpay-error-notice').remove();

  // Create error notice element
  const $errorNotice = jQuery(
    '<div class="woocommerce-error wpay-error-notice" role="alert"><ul><li>' +
      jQuery('<div>').text(message).html() +
      '</li></ul></div>'
  );

  // Insert at the top of checkout form or before payment methods section
  const $checkoutForm = jQuery('.woocommerce-checkout form.checkout');
  if ($checkoutForm.length) {
    const $paymentMethods = $checkoutForm.find(
      '.woocommerce-checkout-payment'
    );
    if ($paymentMethods.length) {
      $paymentMethods.before($errorNotice);
    } else {
      $checkoutForm.prepend($errorNotice);
    }
  } else {
    // Fallback: prepend to checkout container
    jQuery('.woocommerce-checkout').prepend($errorNotice);
  }

  // Scroll to error notice
  const offset = $errorNotice.offset();
  if (offset) {
    jQuery('html, body').animate(
      {
        scrollTop: offset.top - window.WPAY_CONFIG.SCROLL_OFFSET_PX,
      },
      window.WPAY_CONFIG.SCROLL_DURATION_MS
    );
  }

  // Trigger WooCommerce checkout error event for compatibility
  jQuery('body').trigger('checkout_error', [message]);
}

jQuery(document).ready(function ($) {
  if (jQuery(".woocommerce-checkout").length > 0) {
    jQuery(".woocommerce-checkout").on(
      "change",
      "input[type=radio][name=payment_method]",
      function () {
        if (jQuery(this).val() === "wc_wholesale_payments") {
          jQuery(
            ".payment_method_wc_wholesale_payments input[type=radio][name=wpay_plan]:first"
          ).prop("checked", true);
        }
      }
    );

    // Classic checkout Payment Element handling (progressive enhancement).
    // Toggle the single classic help paragraph (#wpay-auto-charge-help-classic)
    const toggleSingleClassicHelp = () => {
      const $selected = jQuery(
        '.payment_method_wc_wholesale_payments input[name="wpay_plan"]:checked'
      );
      const overall = jQuery("#wpay-auto-charge-help-classic").data(
        "overall-auto-charge"
      );
      const isSelectedAuto =
        $selected.length && $selected.hasClass("wpay-auto-charge-plan");

      const shouldShow = overall === true || isSelectedAuto;
      jQuery("#wpay-auto-charge-help-classic").toggle(shouldShow);
    };

    const maybeInitPaymentElement = () => {
      const $selectedPlan = jQuery(
        '.payment_method_wc_wholesale_payments input[name="wpay_plan"]:checked'
      );
      const overallAttr = jQuery("#wpay-auto-charge-help-classic").data(
        "overall-auto-charge"
      );
      const overallFlag =
        overallAttr === true ||
        (window.wpayCheckout && window.wpayCheckout.overallAutoCharge === true);
      const isSelectedAuto =
        $selectedPlan.length && $selectedPlan.hasClass("wpay-auto-charge-plan");
      const isAutoCharge = overallFlag || isSelectedAuto;

      const $container = jQuery("#wpay-payment-element");
      const $result = jQuery("#wpay-save-result");

      // Toggle single classic help (global or selected auto-charge).
      toggleSingleClassicHelp();

      if (!window.wpayCheckout || !window.Stripe || !isAutoCharge) {
        $container.hide();
        $result.hide();
        // Clear global state
        classicStripeInstance = null;
        classicCardElement = null;
        classicIsPaymentElementValid = false;
        classicCurrentCustomerId = null;
        return;
      }

      const api = (path, opts = {}) => {
        return jQuery.ajax({
          url: window.wpayCheckout.restUrl + path,
          method: opts.method || "GET",
          headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
          contentType: "application/json",
          data: opts.data ? JSON.stringify(opts.data) : undefined,
        });
      };

      api("/customer/payment-methods")
        .done((res) => {
          if (res.hasDefault) {
            $container.hide();
            $result.hide();
            // Hide auto-charge help text since customer already has saved payment method
            jQuery("#wpay-auto-charge-help-classic").hide();
            jQuery(".wpay-auto-charge-requirements").hide();
            // Clear global state since no payment element is needed
            classicStripeInstance = null;
            classicCardElement = null;
            classicIsPaymentElementValid = false;
            classicCurrentCustomerId = null;
            return;
          }

          // Store customer ID for later use in payment processing
          classicCurrentCustomerId = res.customer_id;

          // Mount Payment Element for capturing PM.
          const stripe = window.Stripe(window.wpayCheckout.publishableKey);
          if (!stripe) {
            return;
          }
          classicStripeInstance = stripe;

          if (!window._wpayElements) {
            window._wpayElements = stripe.elements();
          }
          let cardElement = window._wpayCardElement;
          if (!cardElement) {
            cardElement = window._wpayElements.create("card", {
              hidePostalCode: true,
              style: {
                base: {
                  fontSize: "16px",
                  fontWeight: "500",
                  color: "#424770",
                  letterSpacing: "0.025em",
                  fontFamily: "Source Code Pro, monospace",
                  "::placeholder": {
                    color: "#aab7c4",
                  },
                },
                invalid: {
                  color: "#9e2146",
                },
                complete: {
                  color: "#28a745",
                },
              },
            });
            window._wpayCardElement = cardElement;
          }

          // Store card element globally for payment processing
          classicCardElement = cardElement;

          // Listen for card element changes to update validation state
          cardElement.off("change").on("change", (event) => {
            classicIsPaymentElementValid = event.complete && !event.error;

            // Update result display based on validation
            if (event.error) {
              $result
                .text(event.error.message)
                .css("color", "#721c24")
                .css("background-color", "#f8d7da")
                .css("border-color", "#f5c6cb")
                .show();
            } else if (event.complete) {
              $result
                .text(
                  (window.wpayCheckout &&
                    window.wpayCheckout.i18n &&
                    window.wpayCheckout.i18n.paymentInformationReady) ||
                    "Payment information is ready. You can now place your order."
                )
                .css("color", "#155724")
                .css("background-color", "#d4edda")
                .css("border-color", "#c3e6cb")
                .show();
            } else {
              $result.hide();
            }
          });

          cardElement.mount("#wpay-payment-element");
          $container.show();
          $result.hide();

          // Initialize validation state
          classicIsPaymentElementValid = false;
        })
        .fail(() => {
          $container.hide();
          $result.hide();

          // Clear global state on error
          classicStripeInstance = null;
          classicCardElement = null;
          classicIsPaymentElementValid = false;
          classicCurrentCustomerId = null;
        });
    };

    // React to plan selection changes to conditionally show Payment Element.
    jQuery(document).on(
      "change",
      '.payment_method_wc_wholesale_payments input[name="wpay_plan"]',
      function () {
        toggleSingleClassicHelp();
        maybeInitPaymentElement();
      }
    );
    // And initial load if payment method already selected.
    toggleSingleClassicHelp();
    maybeInitPaymentElement();

    // Hook into WooCommerce classic checkout form submission
    jQuery("form.checkout").on(
      "checkout_place_order_wc_wholesale_payments",
      async function (e) {
        const $form = jQuery(this);

        // If this is a resubmission after payment method attachment, allow it to proceed
        // Don't prevent default - let WooCommerce handle it normally
        if ($form.data("wpay-resubmitting")) {
          $form.removeData("wpay-resubmitting");
          // Clear any previous button state
          jQuery("#place_order").prop("disabled", false);
          // Don't prevent default - allow WooCommerce to process the submission normally
          // Since we're not preventing default, WooCommerce will handle the submission
          return; // Exit early without preventing default
        }

        // Prevent default submission - we'll manually trigger it when needed
        // This is necessary because async functions return Promises (always truthy),
        // so return false won't actually block submission
        e.preventDefault();
        e.stopImmediatePropagation();

        // Helper function to allow form submission
        const allowSubmission = () => {
          // Temporarily remove our handler to prevent infinite loop
          $form.off("checkout_place_order_wc_wholesale_payments");
          // Manually trigger WooCommerce's checkout submission by clicking the button
          // This will trigger WooCommerce's AJAX handler without our custom handler
          jQuery("#place_order").trigger("click");
        };

        // Check if payment method was already attached (hidden field present from previous attempt)
        if ($form.find('input[name="wpay_pm_attached"]').val() === "1") {
          allowSubmission(); // Payment method already attached, allow submission to proceed
          return;
        }

        // Check if we need to capture a payment method
        const $selectedPlan = jQuery(
          '.payment_method_wc_wholesale_payments input[name="wpay_plan"]:checked'
        );
        const overallAttr = jQuery("#wpay-auto-charge-help-classic").data(
          "overall-auto-charge"
        );
        const overallFlag =
          overallAttr === true ||
          (window.wpayCheckout &&
            window.wpayCheckout.overallAutoCharge === true);
        const isSelectedAuto =
          $selectedPlan.length &&
          $selectedPlan.hasClass("wpay-auto-charge-plan");
        const needsPaymentMethod = overallFlag || isSelectedAuto;

        // Validate nonce before proceeding
        if (!window.wpayCheckout || !window.wpayCheckout.nonce) {
          showCheckoutError(
            "Security token expired. Please refresh the page and try again."
          );
          return; // Block submission (default already prevented)
        }

        if (!needsPaymentMethod) {
          allowSubmission(); // Allow order to proceed
          return;
        }

        // Check if customer already has a saved payment method
        // If they do, we don't need Stripe element initialization
        if (!classicStripeInstance || !classicCardElement) {
          // First check if user has a saved payment method via REST API
          try {
            const hasPaymentMethod = await new Promise((resolve) => {
              jQuery.ajax({
                url:
                  (window.wpayCheckout && window.wpayCheckout.restUrl) +
                  "/customer/payment-methods",
                method: "GET",
                headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
                success: (res) => {
                  resolve(res && res.hasDefault === true);
                },
                error: () => {
                  resolve(false); // On error, assume no payment method
                },
              });
            });

            if (hasPaymentMethod) {
              // User has saved payment method, allow order to proceed
              allowSubmission();
              return;
            }
          } catch (error) {
            // If check fails, proceed to Stripe element validation
            if (window.wpayCheckout?.wpayDebug) {
              console.error("Error checking payment method:", error);
            }
          }

          // No saved payment method and Stripe element not initialized
          // This means Stripe payment element must be initialized for auto-charge plans
          showCheckoutError(
            (window.wpayCheckout &&
              window.wpayCheckout.i18n &&
              window.wpayCheckout.i18n.paymentMethodNotInitialized) ||
              "Payment method not initialized. Please refresh the page and try again."
          );
          return; // Block order (default already prevented)
        }

        if (!classicIsPaymentElementValid) {
          showCheckoutError(
            (window.wpayCheckout &&
              window.wpayCheckout.i18n &&
              window.wpayCheckout.i18n.completePaymentInformation) ||
              "Please complete your payment information before placing the order."
          );
          return; // Block order (default already prevented)
        }

        if (!classicCurrentCustomerId) {
          showCheckoutError(
            (window.wpayCheckout &&
              window.wpayCheckout.i18n &&
              window.wpayCheckout.i18n.customerInfoNotAvailable) ||
              "Customer information not available. Please refresh and try again."
          );
          return; // Block order (default already prevented)
        }

        try {
          // Show loading state
          const $placeOrderBtn = jQuery("#place_order");
          const originalText = $placeOrderBtn.text();
          $placeOrderBtn
            .prop("disabled", true)
            .text(
              (window.wpayCheckout &&
                window.wpayCheckout.i18n &&
                window.wpayCheckout.i18n.processingPaymentMethod) ||
                "Processing payment method..."
            );

          // Create payment method
          const { error, paymentMethod } =
            await classicStripeInstance.createPaymentMethod({
              type: "card",
              card: classicCardElement,
            });

          if (error) {
            showCheckoutError(
              error.message ||
                (window.wpayCheckout &&
                  window.wpayCheckout.i18n &&
                  window.wpayCheckout.i18n.failedToCreatePaymentMethod) ||
                "Failed to create payment method"
            );
            $placeOrderBtn.prop("disabled", false).text(originalText);
            return; // Block order (default already prevented)
          }

          // Attach payment method to customer with retry logic
          let attachSuccess = false;
          let lastError = null;

          for (let attempt = 1; attempt <= window.WPAY_CONFIG.MAX_RETRIES; attempt++) {
            try {
              const attachResponse = await new Promise((resolve, reject) => {
                jQuery.ajax({
                  url:
                    window.wpayCheckout.restUrl +
                    "/customers/" +
                    classicCurrentCustomerId +
                    "/attach-payment-method",
                  method: "POST",
                  headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
                  contentType: "application/json",
                  data: JSON.stringify({
                    payment_method: paymentMethod.id,
                  }),
                  success: resolve,
                  error: reject,
                });
              });

              attachSuccess = true;
              break;
            } catch (error) {
              lastError = error;

              // Don't retry if this is the last attempt
              if (attempt < window.WPAY_CONFIG.MAX_RETRIES) {
                const delay = window.WPAY_CONFIG.RETRY_DELAY_BASE_MS * attempt;
                await new Promise((resolve) => setTimeout(resolve, delay));
              }
            }
          }

          if (!attachSuccess) {
            throw lastError;
          }

          // Sync payment methods to WooCommerce after successful attachment
          try {
            await new Promise((resolve, reject) => {
              jQuery.ajax({
                url:
                  window.wpayCheckout.restUrl +
                  "/customers/" +
                  classicCurrentCustomerId +
                  "/sync-payment-methods",
                method: "POST",
                headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
                contentType: "application/json",
                success: resolve,
                error: reject,
              });
            });
          } catch (syncError) {
            // Log sync error but don't fail the checkout
            if (window.wpayCheckout?.wpayDebug) {
              console.warn("Failed to sync payment methods:", syncError);
            }
          }

          // Set session flag on backend (same as blocks checkout does)
          // This is CRITICAL - the backend checks the session flag
          try {
            const selectedPlanInput = jQuery(
              '.payment_method_wc_wholesale_payments input[name="wpay_plan"]:checked'
            );
            const planId = selectedPlanInput ? selectedPlanInput.val() : null;

            const requestData = { wpay_pm_attached: true };
            if (planId) {
              requestData.plan_id = planId;
            }

            await new Promise((resolve, reject) => {
              jQuery.ajax({
                url: window.wpayCheckout.checkoutRestUrl,
                method: "POST",
                headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
                contentType: "application/json",
                data: JSON.stringify(requestData),
                success: resolve,
                error: reject,
              });
            });
          } catch (flagError) {
            // Log but don't fail - we still have the hidden field as fallback
            if (window.wpayCheckout?.wpayDebug) {
              console.warn("[WPay] Failed to set session flag:", flagError);
            }
          }

          // Add hidden field as additional fallback
          // This tells the backend to trust that PM exists without checking Stripe API
          $form.find('input[name="wpay_pm_attached"]').remove();
          $form.append(
            '<input type="hidden" name="wpay_pm_attached" value="1" />'
          );

          // Re-enable button and trigger a new checkout submission
          // We need to trigger the button click to properly invoke WooCommerce's AJAX handler
          $placeOrderBtn.prop("disabled", false).text(originalText);

          // Set flag to prevent infinite loop and trigger button click
          // This ensures WooCommerce's checkout AJAX handler processes the submission
          if (!$form.data("wpay-resubmitting")) {
            $form.data("wpay-resubmitting", true);

            // Clear any existing resubmission timeout to prevent race conditions
            if (resubmitTimeout) {
              clearTimeout(resubmitTimeout);
            }

            // Use setTimeout to let the current event loop complete
            resubmitTimeout = setTimeout(() => {
              resubmitTimeout = null;
              // Verify button still exists and is enabled before clicking
              if ($placeOrderBtn.length && !$placeOrderBtn.prop("disabled")) {
                $placeOrderBtn.trigger("click");
              } else {
                if (window.wpayCheckout?.wpayDebug) {
                  console.error(
                    "[WPay] Place order button not available for resubmission"
                  );
                }
                // Clean up flag if button click fails
                $form.removeData("wpay-resubmitting");
                $placeOrderBtn.prop("disabled", false);
              }
            }, window.WPAY_CONFIG.RESUBMIT_DELAY_MS);
          }

          return false; // Block the current submission
        } catch (error) {
          // Use centralized error handling
          const errorMsg = handleWPayError(
            error,
            'savePaymentMethod',
            (window.wpayCheckout?.i18n?.failedToSavePaymentMethod) ||
            "Failed to save payment method. Please try again."
          );

          showCheckoutError(errorMsg);

          // Clean up on error
          if (resubmitTimeout) {
            clearTimeout(resubmitTimeout);
            resubmitTimeout = null;
          }
          $form.removeData("wpay-resubmitting");
          $form.find('input[name="wpay_pm_attached"]').remove();

          const $placeOrderBtn = jQuery("#place_order");
          $placeOrderBtn
            .prop("disabled", false)
            .text(
              $placeOrderBtn.data("original-text") ||
                (window.wpayCheckout &&
                  window.wpayCheckout.i18n &&
                  window.wpayCheckout.i18n.placeOrder) ||
                "Place order"
            );
          return false; // Block order
        }
      }
    );
  }
});

jQuery(document).ajaxComplete(function (event, jqxhr, settings) {
  const objData = getQueryParams(settings.data);
  if (
    typeof objData?.payment_method !== "undefined" &&
    objData?.payment_method == "wc_wholesale_payments"
  ) {
    jQuery(
      ".payment_method_wc_wholesale_payments input[type=radio][name=wpay_plan]:first"
    ).prop("checked", true);
  }
});

function getQueryParams(data) {
  var params = {};
  var queryString = data;
  var regex = /([^&=]+)=([^&]*)/g;
  var m;

  while ((m = regex.exec(queryString))) {
    params[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
  }

  return params;
}
