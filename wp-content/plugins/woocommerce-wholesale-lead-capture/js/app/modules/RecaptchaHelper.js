/**
 * Get the reCAPTCHA widget ID for a specific form container.
 *
 * When multiple reCAPTCHA widgets exist on the same page (e.g. login + registration),
 * Google assigns sequential widget IDs (0, 1, 2...) in DOM order. This function
 * determines the correct widget ID by finding the position of the form's .g-recaptcha
 * element among all .g-recaptcha elements on the page.
 *
 * @param {string} containerSelector CSS selector for the form container.
 * @return {number} The widget ID, or 0 if not found.
 */
// eslint-disable-next-line no-unused-vars
function wwlcGetRecaptchaWidgetId(containerSelector) {
  var allWidgets = document.querySelectorAll(".g-recaptcha");
  var widget = document.querySelector(containerSelector + " .g-recaptcha");
  if (!widget) return 0;
  var index = Array.prototype.indexOf.call(allWidgets, widget);
  return index >= 0 ? index : 0;
}

/**
 * Execute a reCAPTCHA v3 challenge and return a Promise that resolves to a token.
 *
 * Wraps grecaptcha.ready + grecaptcha.execute so callers can `await` a token
 * before form submission. Falls back to a rejected Promise if grecaptcha is
 * unavailable.
 *
 * @param {string} siteKey  reCAPTCHA v3 site key.
 * @param {string} action   v3 action label (e.g. "wwlc_register").
 * @return {Promise<string>} Resolves to a v3 token.
 */
// eslint-disable-next-line no-unused-vars
function wwlcExecuteRecaptchaV3(siteKey, action) {
  return new Promise(function (resolve, reject) {
    if (typeof grecaptcha === 'undefined' || typeof grecaptcha.ready !== 'function') {
      reject(new Error('grecaptcha is not available'));
      return;
    }
    grecaptcha.ready(function () {
      try {
        grecaptcha.execute(siteKey, { action: action }).then(resolve, reject);
      } catch (e) {
        reject(e);
      }
    });
  });
}
