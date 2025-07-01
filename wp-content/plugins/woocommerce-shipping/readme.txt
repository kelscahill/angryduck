=== WooCommerce Shipping ===
Contributors: woocommerce, automattic, harriswong, waclawjacek, samnajian, kloon, ferdev, kallehauge, samirthemerchant, dustinparkerwebdev
Tags: woocommerce, shipping, usps, dhl, labels
Requires Plugins: woocommerce
Requires PHP: 7.4
Requires at least: 6.7
Tested up to: 6.8.1
WC requires at least: 9.7
WC tested up to: 9.9.2
Stable tag: 1.7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free shipping plugin for US merchants to print discounted shipping labels and compare live label rates directly from your WooCommerce dashboard.

== Description ==

Save time and money with WooCommerce Shipping. This dedicated shipping plugin allows you to print discounted shipping labels and compare live label rates with just a few clicks. There is no need to spend time setting up individual carrier accounts as everything is done directly from your WooCommerce dashboard.

With WooCommerce Shipping, critical services are hosted on Automattic’s best-in-class infrastructure, rather than relying on your store’s hosting. That means your store will be more stable and faster.

To start shipping, simply install this free plugin, create a WooCommerce account, and start saving time and money on your packages.

= Print USPS, UPS, and DHL shipping labels and get heavily discounted rates =
Ship domestically and internationally right from your WooCommerce dashboard. Print USPS, UPS, and DHL labels to save up to 77% instantly. All shipments are 100% carbon-neutral. More carriers are coming soon.

= Compare live shipping label rates =
Compare live rates across carriers to make sure you get the best price without guesswork or complex math.

= Split shipments =
Send orders in multiple shipments as products become ready.

= Optimized tracking =
Our built-in Shipment Tracking feature makes it easier for you and your customers to manage tracking numbers by automatically adding tracking IDs to “Order Complete” emails.

= Address verification at checkout =
Say goodbye to undeliverable packages and the hassle of managing incorrect addresses by enabling address verification at checkout. Including your customers in the shipping process will reduce failed deliveries, costly returns, and guesswork.

= Supported store countries and currencies =
WooCommerce Shipping currently only supports stores shipping from the following countries and using the following currencies. Please note you can still ship internationally, this is only applicable to your store's location.

**Store countries**
- United States (US)
- American Samoa (AS)
- Puerto Rico (PR)
- United States Virgin Islands (VI)
- Guam (GU)
- Northern Mariana Islands (MP)
- United States Minor Outlying Islands (UM)
- Federated States of Micronesia (FM)
- Marshall Islands (MH)

**Store currencies**
- United States Dollar (USD)

== Installation ==

This section describes how to install the plugin and get it working.

1. Install and activate WooCommerce if you haven't already done so
1. Upload the plugin files to the `/wp-content/plugins/woocommerce-shipping` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Connect to your WordPress.com account if you haven't already done so
1. Want to buy shipping labels? First, add your credit card to https://wordpress.com/me/purchases/billing and then print labels for orders right from the Edit Order page

== Frequently Asked Questions ==

= What external services does this plugin rely on? =

This plugin relies on the following external services:

1. WordPress.com connection:
	- Description: The plugin makes requests to our own endpoints at WordPress.com (proxied via https://api.woocommerce.com) to fetch shipping rates, verify addresses, and purchase shipping labels.
	- Website: https://wordpress.com/
	- Terms of Service: https://wordpress.com/tos/
	- Privacy Policy: https://automattic.com/privacy/
2. WooCommerce Usage Tracking:
	- Description: The plugin will send usage statistics, provided the user has opted into WooCommerce Usage Tracking.
	- Script: https://pixel.wp.com/t.gif
	- Terms of Service: https://wordpress.com/tos/
	- Privacy Policy: https://automattic.com/privacy/
3. Sentry.io:
	- Description: The plugin catches critical errors in the user interface and sends a summary of the technical issue to Sentry for debugging purposes.
	- Website: https://sentry.io/
	- Terms of Service: https://sentry.io/terms/
	- Privacy Policy: https://sentry.io/privacy/
4. Sift.com:
	- Description: The plugin utilizes Sift (a fraud prevention and risk management platform) to calculate fraud scores for shipping label purchases made through the WordPress admin interface.
	- Website: https://sift.com/
	- Script: https://cdn.sift.com/s.js
	- Terms of Service: https://sift.com/legal-and-compliance/tos/
	- Privacy Policy: https://sift.com/legal-and-compliance/service-privacy-notice

= Do I need to use WooCommerce Tax or Jetpack? =

There’s no need to have Jetpack or WooCommerce Tax installed on your site — the new experience connects directly through your WordPress.com account for speed and simplicity.

= Why is a WordPress.com account connection required? =

We connect to your WordPress.com account to authenticate your site and user account so we can securely charge the payment method on file for any labels purchased.

= What shipping carriers are currently supported? =

* USPS
* DHL

With more carrier support in the works.

= Can I buy and print shipping labels for US domestic and international packages? =

Yes! You can buy and print USPS shipping labels for domestic destinations and USPS and DHL shipping labels for international destinations. Shipments need to originate from the U.S.

= This works with WooCommerce, right? =

Yep! We follow the L-2 policy, meaning if the latest version of WooCommerce is 8.7, we support back to WooCommerce version 8.5.

= Are there Terms of Service? =

Absolutely! You can read our Terms of Service [here](https://wordpress.com/tos).

== Screenshots ==
1. WooCommerce Shipping label purchase screen.
2. WooCommerce Shipping now supports select UPS services.
3. WooCommerce Shipping split shipment screen.
4. WooCommerce Shipping multiple origin address selection.
5. WooCommerce Shipping print label screen.
6. WooCommerce Shipping address validation at checkout suggestion.

== Changelog ==

= 1.7.4 - 2025-06-23 =
* Fix   - Resolved the issue of duplicate shipments by ensuring refunded labels are ignored during shipment generation.

= 1.7.3 - 2025-06-11 =
* Fix   - Fixed a UI-only issue causing purchased labels to display under incorrect shipments while the data remained accurate.

= 1.7.2 - 2025-06-10 =
* Tweak - WooCommerce 9.9 Compatibility.
* Fix   - Prevent account from being unintentionally disabled via API when settings are updated
* Fix   - Create shipping label modal style issues.

= 1.7.1 - 2025-05-22 =
* Fix   - Address validation assets not loading.

= 1.7.0 - 2025-05-15 =
* Add   - Introduce UPS Ground Saver shipping service with dedicated Terms of Service acceptance flow.

= 1.6.7 - 2025-05-05 =
* Fix   - A failed purchase prevents updating destination address.
* Fix   - Rates response for shipment_id = 0 is now correctly being returned as an object instead of an array.

= 1.6.6 - 2025-04-22 =
* Add   - Display "Tracking is not available" note in the rate for untrackable services.

= 1.6.5 - 2025-04-14 =
* Tweak - Update the link for UPS Terms of Service.

= 1.6.4 - 2025-04-07 =
* Tweak - WooCommerce 9.8 Compatibility.

= 1.6.3 - 2025-04-01 =
* Fix   - Labels created in the mobile apps are not visible in the web view.

= 1.6.2 - 2025-03-17 =
* Fix   - Notice PHP error "Undefined index" on the settings page after fresh install.

= 1.6.1 - 2025-03-06 =
* Tweak - Force browser to download assets of the new release.

= 1.6.0 - 2025-03-04 =
* Add   - New "Print packing slip" option on purchased labels.
* Add   - Add a new "wcshipping_fulfillment_summary" filter to allow third party to modify the fulfillment metabox message.
* Add   - Display a "Non-refundable" note in the rate for non-refundable services.
* Add   - Functionality to specify shipping date of the label.
* Fix   - Sanitize order line item name and variation on shipping label creation form.
* Fix   - Selecting a label size on the "Print label" button would update the default label size.
* Tweak - Remove the paper size selector next to the "Purchase Shipment" button, add a new size selector to the "Print label" button.
* Tweak - ITN format improvements.
* Tweak - Update the package deletion API endpoint to support predefined packages deletion.
* Dev   - Update JS dependencies.

= 1.5.0 - 2025-02-12 =
* Add   - Addtional UPS label options.
* Tweak - WooCommerce 9.7 Compatibility.
* Fix   - Prevent race condition when fetching rates.

= 1.4.1 - 2025-02-06 =
* Tweak - Improve overall frontend performance.
* Tweak - Improve address API documentation.
* Add   - New tax identifier for custom forms (PVA) that can be found on the WooCommerce Shipping settings page.

= 1.4.0 - 2025-01-22 =
* Add   - Added possibility to purchase additional shipping labels, after all items in an order has been included in a shipped parcel.
* Add   - Emoji validation on customs description.
* Fix   - Address validation triggering for guest users before required address fields are filled.
* Fix   - Address validation unnecessarily strict for US addresses.

= 1.3.4 - 2025-01-17 =
* Fix   - Fatal error on settings page for new installs.

= 1.3.3 - 2025-01-15 =
* Add   - New API endpoint to check if the order is eligible for shipping label creation.
* Fix   - Fix CORS warnings on Safari browser when address validation is enabled.
* Fix   - Don't register address validation script if it is disabled.
* Fix   - Change the product description tooltip link in the customs form to better explain the purpose of the information.
* Fix   - Call to undefined function wc_st_add_tracking_number.

= 1.3.2 - 2025-01-07 =
* Fix   - Removing starred carrier packages would also remove the predefined packages of other carriers.
* Fix   - Refrain from automatically fetching rates if totalWeight is 0.
* Fix   - Shipment data type safeguards.
* Fix   - Dynamic property creation notices on PHP 8.2+.
* Fix   - An error in the label purchase API endpoint when no shipment info is provided.
* Fix   - An error in the label purchase API endpoint when the client does not provide list of supported features.
* Tweak - PHP 8.4 compatibility.
* Tweak - Improve the total weight input behaviour and error reporting.
* Tweak - Consolidate the origin address API endpoints and documentation.

= 1.3.1 - 2024-12-13 =
* Fix   - Fix issue preventing the address validation from being applied on the checkout page.
* Tweak - Persist the label purchase modal open state on page refresh.

= 1.3.0 - 2024-12-10 =
* Add   - UPS shipping label support, providing access to discounted rates directly in the WooCommerce dashboard (no individual UPS account required).
* Add   - Two new tax identifiers for customs form (IOSS & VOEC) that can be found on the WooCommerce Shipping settings page.
* Fix   - Fix issue where migrated paper size was making payment method change fail.
* Fix   - Fix package and rates pre-selection for multiple shipments.
* Fix   - Fix issue with fatal errors in some environments when using the Shipment Tracking extension.

= 1.2.3 - 2024-11-18 =
* Add   - Only wp.com account owner can manage payment methods.
* Add   - Emoji validation on shipping address.
* Add   - Label reporting under WooCommerce -> Analytics.
* Add   - GET method for `/package` API endpoint.
* Fix   - Issue where legacy labels were not being migrated if the order had WC Shipping labels.
* Fix   - “Rate not found: First” error by ensuring the package type is correctly set at the time of label purchase.

= 1.2.2 - 2024-11-05 =
* Add   - Option to automatically print a label once the label is successfully purchased.
* Add   - Option to allow users to change the unit of the total shipment weight.
* Tweak - WordPress 6.7 Compatibility.
* Tweak - Add caveat to USPS Media Mail rate to indicate what may be shipped via this service.
* Tweak - Move USPS Media Mail to the bottom of the rates list.
* Tweak - Move last purchased rate that is pre-selected to the top of the rates list.
* Fix   - Added missing separator for zip code in the checkout address verification.
* Fix   - Issue with legacy API endpoint for custom packages to ensure correct data output.
* Fix   - Issue where switching between package tabs would not reset the selected rate.

= 1.2.1 - 2024-10-17 =
* Fix   - Issue with excessive rendering of the shipping label success view.

= 1.2.0 - 2024-10-16 =
* Add   - Option to allow shipping address validation at checkout.
* Fix   - A failed payment would hinder future purchases.
* Tweak - Do not cache new shipping API endpoints.
* Tweak - Improve asset file versioning.

= 1.1.5 - 2024-10-02 =
* Fix   - A single order being shipped within the same country and internationally could cause confusion with the customs form.
* Fix   - Changing a shipment's origin or destination address was not being reflected correctly throughout the entire UI.
* Fix   - Total shipment weight exceeding 1k caused the total weight field to be blank.
* Fix   - Moving shipment items to another shipment can cause the app to crash under certain conditions.
* Fix   - Shipping labels now hide the origin name when the origin address includes a company name.
* Dev   - New `wcshipping_include_email_tracking_info` filter so 3rd party plugins can enable/disable tracking info in emails.

= 1.1.4 - 2024-09-25 =
* Add   - Automate address verification for shipping address on the purchase screen.
* Add   - Improve the purchase status header during the purchase process
* Tweak - Improve timestamp handling on plugin status page.
* Fix   - Selectively migrate WooCommerce Shipping & Tax packages if WCShipping created its own new settings.
* Fix   - Don't remove non-compact options prefixed with "wc_connect_" on uninstallation.
* Fix   - Focusing in the custom package form doesn't deactivate the "Get rates button" button.
* Fix   - Ensure custom items stay in sync with the shipment items.
* Fix   - Surface payment errors to the user.
* Fix   - Remember dismissal of migration banners.
* Fix   - Customs form's weight to represent the total weight instead of individual line item weight.

= 1.1.3 - 2024-09-18 =
* Add   - Remember last order complete checkbox state for next label purchase.
* Add   - Automatically fetch rates on label purchase modal load when all conditions are met for fetching rates.
* Add   - Load the settings data from DB.
* Fix   - Ensure tracking numbers link to the correct carrier tracking url when using the Shipment Tracking extension.
* Fix   - Customs form's value to represent the total value instead of individual line item value.
* Fix   - Hide virtual products in the shipping label modal.
* Tweak - Improve error handling when purchasing shipping labels.
* Dev   - Ensure all API endpoints are loaded using the correct hook.

= 1.1.2 - 2024-09-13 =
* Add   - Functionality to delete saved packages and remove starred carrier packages.
* Add   - Added a package weight field to the save template form.
* Tweak - Store the name of the package that was used for a shipping label as part of the shipping label metadata.
* Tweak - Support product customs data created by WooCommerce Shipping & Tax when purchasing new shipping labels.
* Tweak - Improve error handling when purchasing shipping labels.
* Fix   - Improve responsive behaviour of the "Shipping Label" meta box on order edit pages.
* Fix   - Hide virtual products in the shipping label modal.
* Fix   - Nested items in the split shipment modal was missing dimension units.
* Fix   - Hide WooCommerce Shipping & Tax migration banners if there are no previous history.
* Fix   - Update the background order form when using the "Mark order as completed" option.
* Fix   - Hide "Mark as complete" option on already completed orders.

= 1.1.1 - 2024-09-06 =
* Fix   - Get rates button doesn't get active after correcting customs information.
* Fix   - Accessing products from old labels when migrating shipments causes the process to stall.

= 1.1.0 - 2024-09-03 =
* Add   - Support for migrating WooCommerce Shipping & Tax labels and settings.
* Add   - Tooltip to explain disabled delete button on default origin address.
* Add   - Necessary endpoints to load the plugin dynamically in WooCommerce.
* Add   - Allow the WooCommerce mobile app to access API.
* Tweak - Move shipment tracking metabox to upper position.
* Fix   - Browser always ask to exit the settings screen after settings has been saved.
* Fix   - Force shipments with a purchased label to be locked.
* Fix   - Loading plugin version in Loader class.

= 1.0.5 - 2024-08-21 =
* Add   - Show error in Onboarding Connection component.
* Fix   - Conflict with Jetpack connection class.
* Tweak - Change to sender checkbox text on the customs form.
* Tweak - Added new "source" parameter to the /wpcom-connection endpoint.

= 1.0.4 - 2024-08-13 =
* Add   - New Connect component on the shipping settings page.
* Add   - Upload sourcemaps to sentry.
* Add   - Hook into WPCOM Connection dependency list to communicate we share logic with e.g. WooCommerce.
* Tweak - Make composer package versions specific.
* Tweak - Show confirmation banner after accepting Terms of Service.
* Tweak - Hide connect banners if store currency is not supported by WooCommerce Shipping.
* Tweak - Hide connect banners on the WooCommerce Shipping settings page.

= 1.0.3 - 2024-08-02 =
* Fix - Error accessing the continents API endpoint.

= 1.0.2 - 2024-07-30 =
* Tweak - WordPress 6.6 Compatibility.
* Add   - Display the NUX banner on HPOS Order pages.

= 1.0.1 - 2024-06-24 =
* Tweak - Adhering to the plugin review standards.

= 1.0.0 - 2024-04-18 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
This release includes an automated migration routine for all your existing WooCommerce Shipping & Tax labels and settings.
