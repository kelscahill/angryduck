/**
 * FunnelKit Cart integration for WooCommerce Wholesale Order Form.
 *
 * This script listens for WWOF add to cart events and triggers the FunnelKit Cart
 * side cart to open when items are added via the WWOF order form.
 *
 * @package RymeraWebCo\WWOF\Integrations
 * @since 3.0.8
 */

(function () {
  "use strict";

  // Constants for timeout values.
  const CART_TRIGGER_DELAY = 100; // Delay before triggering cart.
  const CART_RESET_DELAY = 500; // Delay before resetting trigger flag.
  const FRAGMENT_REFRESH_DELAY = 150; // Delay for fragment refresh handler.
  const FALLBACK_TRIGGER_TIMEOUT = 2000; // Fallback timeout for button click.

  /**
   * Trigger FunnelKit Cart to open.
   *
   * @return void
   */
  function triggerFunnelKitCart() {
    // Check if FunnelKit Cart app data exists.
    if (typeof fkcart_app_data === "undefined") {
      return;
    }

    // Check if auto open cart is enabled.
    if (fkcart_app_data.should_open_cart !== "yes") {
      return;
    }

    // Add the trigger class to HTML element.
    document.documentElement.classList.add("fkcart-trigger-open");

    // Get the modal element and add the show class.
    const modal = document.getElementById("fkcart-modal");
    if (modal) {
      modal.classList.add("fkcart-show");
      // Ensure modal is visible.
      modal.style.display = "block";
    }

    /**
     * Also try to trigger via event dispatch as a fallback.
     * Some implementations might listen for this event.
     */
    document.body.dispatchEvent(
      new CustomEvent("fkcart:trigger:open", {
        bubbles: true,
        cancelable: true,
      })
    );

    // Trigger WooCommerce fragment refresh to ensure cart updates.
    document.body.dispatchEvent(new Event("wc_fragment_refresh"));

    /**
     * Try to trigger the cart programmatically if there's a global function.
     * Some cart implementations expose a global function to open the cart.
     */
    if (typeof window.fkcartOpenCart === "function") {
      window.fkcartOpenCart();
    }
  }

  /**
   * Track if cart is being triggered to prevent multiple triggers from the same event.
   */
  let isTriggeringCart = false;

  /**
   * Handle WWOF add to cart success event.
   *
   * @param {CustomEvent} event The WWOF add to cart success event.
   * @return void
   */
  function handleWWOFAddToCartSuccess(event) {
    // Prevent multiple triggers from the same event bubbling through multiple listeners.
    if (isTriggeringCart) {
      return;
    }

    // Only proceed if the add to cart was successful (no error).
    if (event.detail && event.detail.hasError) {
      return;
    }

    // Mark that we're triggering to prevent duplicate triggers.
    isTriggeringCart = true;

    // Small delay to ensure cart fragments are updated.
    setTimeout(function () {
      triggerFunnelKitCart();
      // Reset flag after a short delay to allow for future events.
      setTimeout(function () {
        isTriggeringCart = false;
      }, CART_RESET_DELAY);
    }, CART_TRIGGER_DELAY);
  }

  /**
   * Listen for WWOF add to cart success event.
   *
   * Use capture phase on document to catch the event early.
   * Only one listener is needed since events bubble.
   *
   * @param {CustomEvent} event The WWOF add to cart success event.
   * @return void
   */
  document.addEventListener(
    "wwof:add-to-cart:success",
    handleWWOFAddToCartSuccess,
    true
  );

  /**
   * Also listen for wc_fragment_refresh as a fallback since WWOF dispatches this after adding to cart.
   *
   * @param {CustomEvent} event The WWOF add to cart success event.
   * @return void
   */
  let wwofButtonClicked = false;
  const wwofFragmentRefreshHandler = function () {
    // Only trigger if a WWOF button was recently clicked and cart isn't already being triggered.
    if (wwofButtonClicked && !isTriggeringCart) {
      wwofButtonClicked = false;
      isTriggeringCart = true;
      setTimeout(function () {
        triggerFunnelKitCart();
        // Reset flag after a short delay to allow for future events.
        setTimeout(function () {
          isTriggeringCart = false;
        }, CART_RESET_DELAY);
      }, FRAGMENT_REFRESH_DELAY);
    } else if (wwofButtonClicked) {
      // Reset flag if cart is already being triggered.
      wwofButtonClicked = false;
    }
  };

  document.body.addEventListener(
    "wc_fragment_refresh",
    wwofFragmentRefreshHandler,
    false
  );

  /**
   * Directly hook into WWOF add to cart button clicks as a more reliable method.
   *
   * Use event delegation to catch dynamically added buttons.
   *
   * @param {CustomEvent} event The WWOF add to cart success event.
   * @return void
   */
  document.addEventListener(
    "click",
    function (event) {
      const target = event.target;
      // Check if the clicked element or its parent is a WWOF add to cart button.
      const addToCartButton = target.closest(".add-to-cart-btn");
      if (addToCartButton) {
        const parentContainer = addToCartButton.closest(
          ".wwof-table-field.add-to-cart-button"
        );
        if (parentContainer) {
          // Mark that a WWOF button was clicked.
          wwofButtonClicked = true;
          // The main listener on document will handle the success event.
          // This click handler is just for tracking button clicks for the fragment refresh fallback.
          // Fallback: if event doesn't fire within timeout, trigger anyway.
          setTimeout(function () {
            if (wwofButtonClicked && !isTriggeringCart) {
              isTriggeringCart = true;
              triggerFunnelKitCart();
              setTimeout(function () {
                isTriggeringCart = false;
              }, CART_RESET_DELAY);
              wwofButtonClicked = false;
            } else {
              wwofButtonClicked = false;
            }
          }, FALLBACK_TRIGGER_TIMEOUT);
        }
      }
    },
    true
  );
})();
