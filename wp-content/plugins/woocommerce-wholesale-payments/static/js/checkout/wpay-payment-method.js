/**
 * @typedef {Object} WPayCheckout
 * @property {string} nonce REST API nonce for authentication.
 * @property {string} restUrl Base REST API URL for Stripe operations.
 * @property {string} checkoutRestUrl REST API URL for checkout operations.
 * @property {string|null} customerId Stripe customer ID.
 * @property {boolean} overallAutoCharge Whether global auto-charge is enabled.
 * @property {boolean} wpayDebug Whether debug mode is enabled.
 * @property {Object} i18n Internationalization strings.
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

/**
 * Handle Wholesale Payments errors consistently.
 *
 * Extracts error messages from multiple sources and provides user-friendly feedback.
 * Maps common error codes to localized messages.
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

// Only run on Blocks checkout (classic checkout uses wpay-scripts.js instead)
const settings = window.wc?.wcSettings?.getSetting(
  "wc_wholesale_payments_data"
);
if (!settings) {
  // Classic checkout - exit early, this script is for Blocks only
  if (window.wpayCheckout?.wpayDebug) {
    console.warn(
      "[WPay] Blocks checkout script loaded on classic checkout. This script is intended for Blocks checkout only."
    );
  }
  window.wpayPaymentMethodBlockScript = { disabled: true };
  // Exit early without throwing to prevent breaking other scripts on the page
} else {
  // Wrap main script execution in IIFE to prevent execution on classic checkout
  (function () {
    "use strict";

    const label =
      (settings &&
        (window.wp?.htmlEntities?.decodeEntities(settings.title) ||
          window.wp?.i18n?.__(
            "Wholesale Payments",
            "wc-woocommerce-wholesale-payments"
          ))) ||
      "Wholesale Payments";
    const autoChargeLabel =
      (settings &&
        (window.wp?.htmlEntities?.decodeEntities(settings.autoChargeLabel) ||
          window.wp?.i18n?.__(
            "Auto Charge",
            "wc-woocommerce-wholesale-payments"
          ))) ||
      "Auto Charge";

    const paymentPlans =
      settings?.paymentPlans && Array.isArray(settings.paymentPlans)
        ? settings.paymentPlans
        : settings?.paymentPlans && typeof settings.paymentPlans === "object"
        ? Object.values(settings.paymentPlans)
        : [];

    const setPaymentPlan = (planId) => {
      // Only run on Blocks checkout
      if (!settings || !settings.restUrl || !settings.nonce) {
        if (window.wpayCheckout?.wpayDebug) {
          console.warn("[WPay] Cannot set payment plan - settings not available");
        }
        return;
      }

      const requestData = {
        plan_id: planId,
      };

      // Disable the payment button.
      jQuery(".wc-block-checkout__actions_row")
        .find("button")
        .attr("disabled", "disabled");

      jQuery.ajax({
        url: settings.restUrl,
        method: "POST",
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", settings.nonce);
        },
        data: JSON.stringify(requestData),
        contentType: "application/json",
        success: function () {
          // Change radio button to the selected plan on success.
          jQuery('input[name="wpay_plan"]').each(function () {
            if (this.value === planId) {
              this.checked = true;
              // Fire change so Blocks mount logic can react.
              try {
                jQuery(this).trigger("change");
              } catch (_) {}
            }
          });
        },
        error: function (xhr, textStatus, errorThrown) {
          if (window.wpayCheckout?.wpayDebug) {
            console.error("[WPAY] Failed to set payment plan:", {
              planId: planId,
              status: xhr.status,
              statusText: xhr.statusText,
              error: errorThrown,
              response: xhr.responseJSON || xhr.responseText,
            });
          }

          // Revert to previously selected plan on error.
          const previouslySelected = jQuery('input[name="wpay_plan"]:checked');
          if (previouslySelected.length > 0) {
            previouslySelected.prop("checked", true).trigger("change");
          }
        },
        complete: function () {
          // Always re-enable the payment button.
          jQuery(".wc-block-checkout__actions_row")
            .find("button")
            .removeAttr("disabled");
        },
      });
    };

    const Content = (type) => {
      // Unique container id to scope DOM queries for this instance.
      const instanceId = `wpay-block-${Math.random().toString(36).slice(2)}`;

      const toggleHelp = (selectedPlanId, isAuto) => {
        const container = document.getElementById(instanceId);
        if (!container) return;
        const helpEl = container.querySelector("#wpay-auto-charge-help");
        if (!helpEl) return;

        // Always show if global auto-charge is enabled
        if (settings?.autoChargeInvoicesEnabled === true) {
          helpEl.style.display = "block";
          return;
        }

        // Show if selected plan is auto-charge, otherwise hide
        const shouldShow = isAuto === true || isAuto === "yes" || isAuto === 1;
        helpEl.style.display = shouldShow ? "block" : "none";
      };

      const isAutoValue = (val) => {
        if (val === true) return true;
        if (val === 1) return true;
        if (typeof val === "string") {
          const v = val.toLowerCase();
          return v === "yes" || v === "true" || v === "1" || v === "on";
        }
        return false;
      };

      const handleOnChange = () => {
        const selectedPlan = document.querySelector(
          'input[name="wpay_plan"]:checked'
        );
        if (selectedPlan) {
          setPaymentPlan(selectedPlan.value);

          // Check if selected plan has auto-charge class
          const isSelectedAuto = selectedPlan.classList.contains(
            "wpay-auto-charge-plan"
          );

          toggleHelp(selectedPlan.value, isSelectedAuto);
        }
      };

      const mainDiv = wp.element.createElement("div", {
        className: "wpay-block-payment-method",
        id: instanceId,
      });
      const textModeContent = wp.element.createElement(
        "p",
        null,
        settings
          ? window.wp.htmlEntities.decodeEntities(settings.testModeText || "")
          : ""
      );
      const descContent = wp.element.createElement(
        "p",
        null,
        settings
          ? window.wp.htmlEntities.decodeEntities(settings.description || "")
          : ""
      );

      // List of payment plans.
      const listOfPlans = paymentPlans.map((plan, i) => {
        const input = wp.element.createElement("input", {
          id: "wpay_plan-" + plan.post.ID,
          type: "radio",
          name: "wpay_plan",
          value: plan.post.ID,
          checked: i === 0 ? "checked" : "",
          className:
            plan.apply_auto_charge === "yes" ? "wpay-auto-charge-plan" : "",
          onChange: handleOnChange,
        });

        const isAutoCharge =
          plan.apply_auto_charge === true || plan.apply_auto_charge === "yes";
        const chargeLabel = wp.element.createElement(
          "em",
          {
            className: "wpay-auto-charge-status",
            style: settings?.paymentPlanChargeStatusStyle || {},
          },
          autoChargeLabel
        );

        let labelChildren = [plan.post.post_title];
        if (isAutoCharge) {
          labelChildren = [plan.post.post_title, chargeLabel];
        }

        const label = wp.element.createElement(
          "label",
          {
            htmlFor: "wpay_plan-" + plan.post.ID,
            className:
              "wpay-payment-plan-label " +
              (isAutoCharge
                ? "wpay-auto-charge-enabled"
                : "wpay-auto-charge-disabled"),
          },
          labelChildren
        );

        return wp.element.createElement(
          "li",
          {
            style: settings?.paymentPlanItemsStyle || {},
            ...(plan.apply_auto_charge && {
              "data-auto-charge": plan.apply_auto_charge,
            }),
          },
          [input, label]
        );
      });

      const paymentPlanLists = wp.element.createElement("ul", {
        className: "wc-block-payment-method__plans",
        style: settings?.paymentPlansStyle || {},
      });
      paymentPlanLists.props.children = listOfPlans;

      // Single help element above the methods.

      const helpText = window.wp.i18n.__(
        "Auto-charge requires a saved payment method. Add a payment method below.",
        "woocommerce-wholesale-payments"
      );
      const help = wp.element.createElement(
        "div",
        {
          id: "wpay-auto-charge-help",
          className:
            "wpay-auto-charge-help wc-block-components-notice-banner is-warning",
          style: {
            display:
              settings?.autoChargeInvoicesEnabled === true ? "block" : "none",
          },
        },
        [helpText]
      );

      // Persistent placeholders for Payment Element in Blocks (scoped per instance).
      const elementContainerId = `${instanceId}-payment-element`;
      const resultId = `${instanceId}-save-result`;
      const elementContainer = wp.element.createElement("div", {
        id: elementContainerId,
        style: {
          display: "none",
          marginTop: "15px",
          padding: "15px",
          border: "1px solid #ddd",
          borderRadius: "4px",
          backgroundColor: "#f9f9f9",
        },
      });
      const resultEl = wp.element.createElement("p", {
        id: resultId,
        style: {
          display: "none",
          marginTop: "10px",
          padding: "10px 15px",
          borderRadius: "4px",
          fontWeight: "500",
          border: "1px solid",
        },
      });

      // Overdue balance warning notice (on_hold action only — block hides gateway via is_active).
      const overdueNotice =
        settings?.overdueRestriction?.enabled &&
        settings?.overdueRestriction?.isOverdue &&
        settings?.overdueRestriction?.action === "on_hold"
          ? // Context: Blocks checkout on_hold action — mirrors classic checkout wording (payment-fields.php).
            // Intentionally different from My Account (WC.php) and block action (WC_Wholesale_Payments.php).
            wp.element.createElement(
              "div",
              {
                className:
                  "wpay-overdue-notice wc-block-components-notice-banner is-warning",
              },
              wp.element.createElement(
                "p",
                null,
                window.wp.i18n.__(
                  "You have an outstanding overdue balance. Your order will be placed on hold until your overdue invoices are paid.",
                  "woocommerce-wholesale-payments"
                )
              )
            )
          : null;

      // Credit limit notice (on-hold behavior).
      const creditLimitNotice = settings?.creditLimitNotice
        ? wp.element.createElement(
            "div",
            {
              className:
                "wpay-credit-limit-notice wc-block-components-notice-banner is-warning",
              style: { marginBottom: "10px" },
            },
            settings.creditLimitNotice
          )
        : null;

      // Insert the description, help, payment plans and placeholders.
      mainDiv.props.children = [
        textModeContent,
        overdueNotice,
        descContent,
        creditLimitNotice,
        help,
        paymentPlanLists,
        elementContainer,
        resultEl,
      ];

      // Set first plan as default on initial render (both edit and content).
      if (paymentPlans.length > 0) {
        const defaultPlan = paymentPlans[0].post.ID;
        setPaymentPlan(defaultPlan);
        // Initialize help visibility for first plan (unless global forces show).
        setTimeout(() => {
          if (settings?.autoChargeInvoicesEnabled === true) return;

          // Check if first plan input has auto-charge class
          const firstPlanInput = document.querySelector(
            `input[name="wpay_plan"][value="${defaultPlan}"]`
          );
          const firstIsAuto =
            firstPlanInput &&
            firstPlanInput.classList.contains("wpay-auto-charge-plan");

          toggleHelp(String(defaultPlan), !!firstIsAuto);
          // Ensure mount logic runs by dispatching change on the checked radio.
          try {
            const checked = document.querySelector(
              'input[name="wpay_plan"]:checked'
            );
            if (checked) {
              checked.dispatchEvent(new Event("change", { bubbles: true }));
            }
          } catch (_) {}
        }, 0);
      }

      // Payment Element for Blocks (Phase 2): show when auto-charge required and no saved PM.
      setTimeout(() => {
        try {
          if (!window.Stripe) return;

          // Check for a saved default payment method via REST.
          const restBase =
            (window.wpayCheckout && window.wpayCheckout.restUrl) || null;
          const pubKey =
            (window.wpayCheckout && window.wpayCheckout.publishableKey) || null;

          if (!restBase || !pubKey) return;

          const mountIfNeeded = () => {
            const selectedPlanInput = document.querySelector(
              'input[name="wpay_plan"]:checked'
            );
            const overallFlag =
              settings?.autoChargeInvoicesEnabled === true ||
              settings?.overallAutoCharge === true;
            let isSelectedAuto = false;
            if (selectedPlanInput) {
              isSelectedAuto = selectedPlanInput.classList.contains(
                "wpay-auto-charge-plan"
              );
            }

            // Match classic checkout logic exactly
            const isAutoCharge = overallFlag || isSelectedAuto;

            try {
              toggleHelp(
                selectedPlanInput ? selectedPlanInput.value : null,
                isSelectedAuto
              );
            } catch (_) {}

            const container = document.getElementById(elementContainerId);
            const result = document.getElementById(resultId);

            // Hide UI if auto-charge not needed (matching classic checkout pattern)
            if (!window.wpayCheckout || !window.Stripe || !isAutoCharge) {
              if (container) container.style.display = "none";
              if (result) result.style.display = "none";
              currentStripeInstance = null;
              currentCardElement = null;
              isPaymentElementValid = false;
              currentCustomerId = null;
              return;
            }

            // Don't remount if payment element is already visible and mounted for auto-charge plans
            // This prevents interrupting user input in the Stripe iframe
            // Only apply this optimization when auto-charge is active
            if (
              isAutoCharge &&
              container &&
              container.style.display !== "none" &&
              currentStripeInstance &&
              currentCardElement
            ) {
              // Element is already mounted and visible - skip remounting
              return;
            }

            // Query backend to check for existing payment methods
            jQuery
              .ajax({
                url: restBase + "/customer/payment-methods",
                method: "GET",
                headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
              })
              .done((res) => {
                if (res && res.hasDefault === true) {
                  // Hide UI if user already has a saved payment method
                  if (container) container.style.display = "none";
                  if (result) result.style.display = "none";
                  // Hide auto-charge help text since customer already has saved payment method
                  const helpElements = document.querySelectorAll(
                    ".wpay-auto-charge-requirements"
                  );
                  helpElements.forEach((el) => (el.style.display = "none"));
                  // Clear global state since no payment element is needed
                  currentStripeInstance = null;
                  currentCardElement = null;
                  isPaymentElementValid = false;
                  currentCustomerId = null;
                  paymentMethodAttached = false;
                  isAttachingPaymentMethod = false;
                  cleanupButtonObservers();
                  return;
                }

                if (!container || !result) return;

                // Store customer ID for later use in payment processing
                currentCustomerId = res.customer_id;

                // If we still don't have a customer id, surface an error and stop to avoid 404.
                if (!res || !res.customer_id) {
                  result.textContent =
                    (window.wpayCheckout &&
                      window.wpayCheckout.i18n &&
                      window.wpayCheckout.i18n.unableToCreateStripeCustomer) ||
                    "Unable to create Stripe customer. Please reload the page and try again.";
                  result.style.color = "#721c24";
                  result.style.backgroundColor = "#f8d7da";
                  result.style.borderColor = "#f5c6cb";
                  result.style.display = "block";
                  if (container) container.style.display = "none";
                  return;
                }

                // Initialize single Stripe instance and Elements
                if (!window._wpayStripe) {
                  window._wpayStripe = window.Stripe(pubKey);
                }
                currentStripeInstance = window._wpayStripe;

                // Recreate Elements and Card each time to guarantee same instance.
                try {
                  if (
                    window._wpayBlocksCard &&
                    typeof window._wpayBlocksCard.unmount === "function"
                  ) {
                    window._wpayBlocksCard.unmount();
                  }
                } catch (_) {}

                const elements = currentStripeInstance.elements();
                const cardEl = elements.create("card", {
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

                // Store card element globally for payment processing
                window._wpayBlocksCard = cardEl;
                currentCardElement = cardEl;

                // Listen for card element changes to update validation state
                cardEl.on("change", async (event) => {
                  isPaymentElementValid = event.complete && !event.error;

                  // Update result display based on validation
                  if (event.error) {
                    result.textContent = event.error.message;
                    result.style.color = "#721c24";
                    result.style.backgroundColor = "#f8d7da";
                    result.style.borderColor = "#f5c6cb";
                    result.style.display = "block";
                    // Reset attachment state on error
                    paymentMethodAttached = false;
                    enablePlaceOrderButton();
                  } else if (event.complete) {
                    // When card is complete and valid, automatically attach payment method
                    if (
                      !paymentMethodAttached &&
                      !isAttachingPaymentMethod &&
                      currentStripeInstance &&
                      currentCardElement &&
                      currentCustomerId
                    ) {
                      await attachPaymentMethodOnValidation(result);
                    } else if (paymentMethodAttached) {
                      // Payment method already attached, show success message
                      result.textContent =
                        (window.wpayCheckout &&
                          window.wpayCheckout.i18n &&
                          window.wpayCheckout.i18n.paymentInformationReady) ||
                        "Payment information is ready. You can now place your order.";
                      result.style.color = "#155724";
                      result.style.backgroundColor = "#d4edda";
                      result.style.borderColor = "#c3e6cb";
                      result.style.display = "block";
                    }
                  } else {
                    result.style.display = "none";
                  }
                });

                cardEl.mount("#" + container.id);
                if (container) container.style.display = "block";
                if (result) result.style.display = "none";

                // Initialize validation state
                isPaymentElementValid = false;
                // Reset attachment state when element is remounted (e.g., plan change)
                paymentMethodAttached = false;
                isAttachingPaymentMethod = false;
                cleanupButtonObservers();
                // Clear test flag
                if (window.wpayDisableAutoAttach) {
                  delete window.wpayDisableAutoAttach;
                }
              })
              .fail((xhr, status, error) => {
                // Hide UI on API failure (matching classic checkout pattern)
                if (container) container.style.display = "none";
                if (result) result.style.display = "none";

                // Clear global state on error
                currentStripeInstance = null;
                currentCardElement = null;
                isPaymentElementValid = false;
                currentCustomerId = null;
                paymentMethodAttached = false;
                isAttachingPaymentMethod = false;
                cleanupButtonObservers();
                // Clear test flag
                if (window.wpayDisableAutoAttach) {
                  delete window.wpayDisableAutoAttach;
                }
              });
          };

          // Initial mount
          mountIfNeeded();

          // Multiple event listeners for WooCommerce Blocks compatibility
          let lastSelectedPlan = null;

          const checkForPlanChanges = () => {
            const currentPlan = document.querySelector(
              'input[name="wpay_plan"]:checked'
            );
            const currentValue = currentPlan ? currentPlan.value : null;

            if (currentValue !== lastSelectedPlan) {
              lastSelectedPlan = currentValue;
              mountIfNeeded();
            }
          };

          // Listen for change events (traditional)
          document.addEventListener("change", (e) => {
            if (e.target && e.target.name === "wpay_plan") {
              mountIfNeeded();
            }
          });

          // Listen for click events on payment plans (WooCommerce Blocks)
          document.addEventListener("click", (e) => {
            if (e.target && e.target.name === "wpay_plan") {
              // Small delay to allow the radio button state to update
              setTimeout(() => {
                mountIfNeeded();
              }, 50);
            }
          });

          // Polling mechanism as fallback (every 500ms)
          const pollInterval = setInterval(() => {
            // Stop polling if checkout is complete
            const checkoutContainer =
              document.querySelector(".wc-block-checkout");

            // Stop polling if we're on order-received page or checkout container is gone
            if (
              window.location.pathname.includes("/order-received/") ||
              !checkoutContainer
            ) {
              clearInterval(pollInterval);
              return;
            }

            // Continue with plan change check
            checkForPlanChanges();
          }, 500);

          // Clean up polling when page unloads
          window.addEventListener("beforeunload", () => {
            clearInterval(pollInterval);
          });

          // Listen for custom event from Cypress test to force remount
          document.addEventListener("wpay-payment-methods-cleared", () => {
            // Clear any cached state
            currentStripeInstance = null;
            currentCardElement = null;
            isPaymentElementValid = false;
            currentCustomerId = null;
            paymentMethodAttached = false;
            isAttachingPaymentMethod = false;
            cleanupButtonObservers();

            // Force remount after short delay
            setTimeout(() => {
              mountIfNeeded();
            }, 100);
          });
        } catch (_) {}
      }, 0);

      return mainDiv;
    };

    // Global variables to track payment method state
    let currentStripeInstance = null;
    let currentCardElement = null;
    let isPaymentElementValid = false;
    let currentCustomerId = null;
    let paymentMethodAttached = false; // Track if payment method has been attached during this session
    let isAttachingPaymentMethod = false; // Track if attachment is in progress
    let buttonObservers = []; // Track MutationObservers for cleanup

    /**
     * Get all place order button elements.
     *
     * @return {NodeList} All place order button elements.
     */
    const getPlaceOrderButtons = () => {
      return document.querySelectorAll(
        ".wc-block-components-checkout-place-order-button, button[type='button'].wc-block-components-checkout-place-order-button, button[type='submit'][name='woocommerce_checkout_place_order'], #place_order"
      );
    };

    /**
     * Enable a single button element.
     *
     * @param {HTMLElement} button The button element to enable.
     */
    const enableButton = (button) => {
      button.disabled = false;
      button.removeAttribute("disabled");
      button.removeAttribute("aria-disabled");
      button.classList.remove("disabled");

      // Clear any disabled styling
      if (button.style.pointerEvents === "none") {
        button.style.pointerEvents = "";
      }
      if (button.style.opacity && parseFloat(button.style.opacity) < 1) {
        button.style.opacity = "";
      }
      if (button.style.cursor === "not-allowed") {
        button.style.cursor = "";
      }
    };

    /**
     * Disable a single button element.
     *
     * @param {HTMLElement} button The button element to disable.
     */
    const disableButton = (button) => {
      button.disabled = true;
      button.setAttribute("disabled", "disabled");
      button.setAttribute("aria-disabled", "true");
      button.classList.add("disabled");
    };

    /**
     * Enable the place order button and set up monitoring to keep it enabled.
     */
    const enablePlaceOrderButton = () => {
      const buttons = getPlaceOrderButtons();

      buttons.forEach((button) => {
        enableButton(button);

        // If payment method is attached, set up observer to prevent re-disabling
        if (paymentMethodAttached && !button._wpayObserver) {
          const observer = new MutationObserver((mutations) => {
            // Only re-enable if payment method is attached and button gets disabled
            if (paymentMethodAttached && button.disabled) {
              enableButton(button);
            }
          });

          observer.observe(button, {
            attributes: true,
            attributeFilter: ["disabled", "aria-disabled", "class"],
          });

          button._wpayObserver = observer;
          buttonObservers.push({ button, observer });
        }
      });
    };

    /**
     * Disable the place order button and cleanup observers.
     */
    const disablePlaceOrderButton = () => {
      const buttons = getPlaceOrderButtons();

      buttons.forEach((button) => {
        disableButton(button);
      });
    };

    /**
     * Cleanup button observers.
     */
    const cleanupButtonObservers = () => {
      buttonObservers.forEach(({ button, observer }) => {
        observer.disconnect();
        delete button._wpayObserver;
      });
      buttonObservers = [];
    };

    /**
     * Set the wpay_pm_attached flag in the WooCommerce session.
     *
     * @return {Promise} Promise that resolves when flag is set.
     */
    const setPaymentMethodAttachedFlag = async () => {
      // Use settings.restUrl which points directly to the checkout/choose-plan endpoint
      // If settings.restUrl is not available, construct the URL from wpayCheckout base
      // by removing the /rymera-stripe suffix and adding /checkout/choose-plan
      let endpointUrl = null;

      if (settings && settings.restUrl) {
        // settings.restUrl already points to /checkout/choose-plan
        endpointUrl = settings.restUrl;
      } else if (window.wpayCheckout && window.wpayCheckout.restUrl) {
        // Construct from wpayCheckout.restUrl (base: /wpay/v1/rymera-stripe)
        // Remove /rymera-stripe and add /checkout/choose-plan
        endpointUrl =
          window.wpayCheckout.restUrl.replace(/\/rymera-stripe\/?$/, "") +
          "/checkout/choose-plan";
      } else {
        // Fallback: construct manually from known WordPress REST API structure
        const apiRoot = window.wpApiSettings?.root || "/wp-json/";
        endpointUrl = apiRoot + "wpay/v1/checkout/choose-plan";
      }

      // Get currently selected plan ID to include in the request
      const selectedPlanInput = document.querySelector(
        'input[name="wpay_plan"]:checked'
      );
      const planId = selectedPlanInput ? selectedPlanInput.value : null;

      // Build request data - include plan_id if available
      const requestData = { wpay_pm_attached: true };
      if (planId) {
        requestData.plan_id = planId;
      }

      return new Promise((resolve, reject) => {
        jQuery.ajax({
          url: endpointUrl,
          method: "POST",
          headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
          contentType: "application/json",
          data: JSON.stringify(requestData),
          success: resolve,
          error: reject,
        });
      });
    };

    /**
     * Automatically attach payment method when Stripe validation completes.
     *
     * @param {HTMLElement} resultElement The result display element.
     * @return {Promise<void>}
     */
    const attachPaymentMethodOnValidation = async (resultElement) => {
      if (
        !currentStripeInstance ||
        !currentCardElement ||
        !currentCustomerId ||
        isAttachingPaymentMethod
      ) {
        return;
      }

      // Allow tests to disable automatic attachment for error scenarios.
      if (window.wpayDisableAutoAttach === true) {
        if (window.wpayCheckout?.wpayDebug) {
          console.log(
            "[WPay] Automatic payment method attachment disabled for testing"
          );
        }
        return;
      }

      isAttachingPaymentMethod = true;

      // Show loading state
      resultElement.textContent =
        (window.wpayCheckout &&
          window.wpayCheckout.i18n &&
          window.wpayCheckout.i18n.savingPaymentMethod) ||
        "Saving payment method...";
      resultElement.style.color = "#856404";
      resultElement.style.backgroundColor = "#fff3cd";
      resultElement.style.borderColor = "#ffeaa7";
      resultElement.style.display = "block";

      // Disable place order button during attachment
      disablePlaceOrderButton();

      try {
        // Create payment method
        const { error, paymentMethod } =
          await currentStripeInstance.createPaymentMethod({
            type: "card",
            card: currentCardElement,
          });

        if (error) {
          const errorMsg = handleWPayError(
            error,
            'createPaymentMethod',
            (window.wpayCheckout?.i18n?.failedToCreatePaymentMethod) ||
            "Failed to create payment method"
          );
          throw new Error(errorMsg);
        }

        // Attach payment method to customer
        await attachPaymentMethodWithRetry(currentCustomerId, paymentMethod.id);

        // Set session flag to indicate payment method is attached
        try {
          await setPaymentMethodAttachedFlag();
        } catch (flagError) {
          // Log but don't fail - the attachment succeeded
          if (window.wpayCheckout?.wpayDebug) {
            console.warn("[WPay] Failed to set session flag:", flagError);
          }
        }

        // Sync payment methods to WooCommerce
        try {
          await fetch(
            `${window.wpayCheckout.restUrl}/customers/${currentCustomerId}/sync-payment-methods`,
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": window.wpayCheckout.nonce,
              },
            }
          );
        } catch (syncError) {
          // Log but don't fail - the attachment succeeded
          if (window.wpayCheckout?.wpayDebug) {
            console.warn("[WPay] Failed to sync payment methods:", syncError);
          }
        }

        // Success - show success message and enable button
        paymentMethodAttached = true;
        resultElement.textContent =
          (window.wpayCheckout &&
            window.wpayCheckout.i18n &&
            window.wpayCheckout.i18n.paymentInformationReady) ||
          "Payment information is ready. You can now place your order.";
        resultElement.style.color = "#155724";
        resultElement.style.backgroundColor = "#d4edda";
        resultElement.style.borderColor = "#c3e6cb";
        resultElement.style.display = "block";

        // Enable button and set up monitoring to keep it enabled
        enablePlaceOrderButton();
      } catch (error) {
        // Error - show error message and keep button disabled or re-enable for retry
        const errorMessage = handleWPayError(
          error,
          'savePaymentMethod',
          (window.wpayCheckout?.i18n?.failedToSavePaymentMethod) ||
          "Failed to save payment method. Please try again."
        );

        resultElement.textContent = errorMessage;
        resultElement.style.color = "#721c24";
        resultElement.style.backgroundColor = "#f8d7da";
        resultElement.style.borderColor = "#f5c6cb";
        resultElement.style.display = "block";

        // Re-enable button so user can retry
        enablePlaceOrderButton();
        paymentMethodAttached = false;
      } finally {
        isAttachingPaymentMethod = false;
      }
    };

    /**
     * Helper function to attach payment method with retry logic.
     *
     * @param {string} customerId Stripe customer ID.
     * @param {string} paymentMethodId Stripe payment method ID.
     * @param {number} maxRetries Maximum number of retry attempts (defaults to window.WPAY_CONFIG.MAX_RETRIES).
     * @return {Promise} Promise that resolves with the response or rejects with error.
     */
    const attachPaymentMethodWithRetry = async (
      customerId,
      paymentMethodId,
      maxRetries = window.WPAY_CONFIG.MAX_RETRIES
    ) => {
      let lastError = null;

      for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
          const response = await new Promise((resolve, reject) => {
            jQuery.ajax({
              url:
                window.wpayCheckout.restUrl +
                "/customers/" +
                customerId +
                "/attach-payment-method",
              method: "POST",
              headers: { "X-WP-Nonce": window.wpayCheckout.nonce },
              contentType: "application/json",
              data: JSON.stringify({
                payment_method: paymentMethodId,
              }),
              success: resolve,
              error: (xhr, status, errorThrown) => {
                // Create error object with all relevant information
                const errorObj = new Error(
                  errorThrown || status || "Unknown error"
                );
                errorObj.status = xhr.status;
                errorObj.statusText = xhr.statusText;
                errorObj.responseJSON = xhr.responseJSON;
                errorObj.responseText = xhr.responseText;
                reject(errorObj);
              },
            });
          });

          return response;
        } catch (error) {
          lastError = error;

          // Don't retry on client errors (4xx) except for specific cases
          if (
            error.status >= 400 &&
            error.status < 500 &&
            error.status !== 429
          ) {
            throw error;
          }

          // If this is the last attempt, throw the error
          if (attempt === maxRetries) {
            throw lastError;
          }

          // Wait before retrying (exponential backoff)
          const delay = 1000 * attempt;
          await new Promise((resolve) => setTimeout(resolve, delay));
        }
      }

      // This should never be reached, but just in case
      if (lastError) {
        throw lastError;
      }
    };

    // Function to handle payment method capture during order placement
    const handlePaymentSetup = async () => {
      // Validate nonce before proceeding
      if (!window.wpayCheckout || !window.wpayCheckout.nonce) {
        return {
          type: "error",
          message:
            "Security token expired. Please refresh the page and try again.",
        };
      }

      // Check if auto-charge plan is selected
      const selectedPlanInput = document.querySelector(
        'input[name="wpay_plan"]:checked'
      );
      if (!selectedPlanInput) {
        return { type: "success" };
      }

      const selectedPlan = selectedPlanInput.value;
      const isSelectedAuto = selectedPlanInput.classList.contains(
        "wpay-auto-charge-plan"
      );
      const overallFlag = settings?.autoChargeInvoicesEnabled === true;
      const needsPaymentMethod = overallFlag || isSelectedAuto;

      if (!needsPaymentMethod) {
        return { type: "success" };
      }

      // Check if customer already has a saved payment method
      // If they do, we don't need Stripe element initialization
      if (!currentStripeInstance || !currentCardElement) {
        // First check if user has a saved payment method via REST API
        try {
          const hasPaymentMethod = await new Promise((resolve) => {
            jQuery.ajax({
              url: window.wpayCheckout.restUrl + "/customer/payment-methods",
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
            return { type: "success" };
          }
        } catch (error) {
          // If check fails, proceed to Stripe element validation
          if (window.wpayCheckout?.wpayDebug) {
            console.error("Error checking payment method:", error);
          }
        }

        // No saved payment method and Stripe element not initialized
        // This means Stripe payment element must be initialized for auto-charge plans
        return {
          type: "error",
          message:
            (window.wpayCheckout &&
              window.wpayCheckout.i18n &&
              window.wpayCheckout.i18n.paymentMethodNotInitialized) ||
            "Payment method not initialized. Please refresh the page and try again.",
        };
      }

      // If payment method was already attached during validation, skip the attachment process
      if (paymentMethodAttached) {
        return { type: "success" };
      }

      // Validate payment element completion
      if (!isPaymentElementValid) {
        return {
          type: "error",
          message:
            (window.wpayCheckout &&
              window.wpayCheckout.i18n &&
              window.wpayCheckout.i18n.completePaymentInformation) ||
            "Please complete your payment information before placing the order.",
        };
      }

      // Validate customer ID
      if (!currentCustomerId) {
        return {
          type: "error",
          message:
            (window.wpayCheckout &&
              window.wpayCheckout.i18n &&
              window.wpayCheckout.i18n.customerInfoNotAvailable) ||
            "Customer information not available. Please refresh and try again.",
        };
      }

      try {
        // Create payment method
        const { error, paymentMethod } =
          await currentStripeInstance.createPaymentMethod({
            type: "card",
            card: currentCardElement,
          });

        if (error) {
          const errorMsg = handleWPayError(
            error,
            'createPaymentMethod',
            (window.wpayCheckout?.i18n?.failedToCreatePaymentMethod) ||
            "Failed to create payment method"
          );
          return {
            type: "error",
            message: errorMsg,
          };
        }

        // Attach payment method to customer with retry logic
        try {
          await attachPaymentMethodWithRetry(
            currentCustomerId,
            paymentMethod.id
          );
        } catch (attachError) {
          // Extract error message from attachment failure using centralized handler
          const attachErrorMessage = handleWPayError(
            attachError,
            'attachPaymentMethod',
            (window.wpayCheckout?.i18n?.failedToSavePaymentMethod) ||
            "Failed to save payment method. Please try again."
          );

          return {
            type: "error",
            message: attachErrorMessage,
          };
        }

        // Sync payment methods to WooCommerce after successful attachment
        try {
          await fetch(
            `${window.wpayCheckout.restUrl}/customers/${currentCustomerId}/sync-payment-methods`,
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": window.wpayCheckout.nonce,
              },
            }
          );
        } catch (syncError) {
          // Log sync error but don't fail the checkout
          if (window.wpayCheckout?.wpayDebug) {
            console.warn("Failed to sync payment methods:", syncError);
          }
        }

        // Set session flag to indicate payment method is attached
        try {
          await setPaymentMethodAttachedFlag();
        } catch (flagError) {
          // Log but don't fail - the attachment succeeded
          if (window.wpayCheckout?.wpayDebug) {
            console.warn("[WPay] Failed to set session flag:", flagError);
          }
        }

        // Mark as attached for this session
        paymentMethodAttached = true;

        // Return success with payment data for Blocks checkout
        // WooCommerce Blocks converts paymentData object into payment_data array format
        // This ensures wpay_pm_attached is included in the payment_data sent to backend
        // Format: paymentData object keys become payment_data array items with {key, value} structure
        return {
          type: "success",
          paymentData: {
            wpay_pm_attached: "1",
          },
        };
      } catch (error) {
        // Extract the most specific error message available using centralized handler
        const errorMessage = handleWPayError(
          error,
          'savePaymentMethod',
          (window.wpayCheckout?.i18n?.failedToSavePaymentMethod) ||
          "Failed to save payment method. Please try again."
        );

        return {
          type: "error",
          message: errorMessage,
        };
      }
    };

    // Check if there is a payment plans available and settings exist.
    // Only register on Blocks checkout where settings are available.
    if (settings && paymentPlans.length > 0 && window.wc?.wcBlocksRegistry) {
      const Block_Gateway = {
        name: "wc_wholesale_payments",
        label: label,
        content: Content("content"),
        edit: Content("edit"),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: settings.supports || {},
        onPaymentSetup: handlePaymentSetup,
      };

      window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
    }
  })();
}
