=== Dintero Checkout for WooCommerce Payment Methods ===
Contributors: dintero, krokedil, NiklasHogefjord
Tags: woocommerce, dintero, ecommerce, e-commerce, checkout
Requires at least: 5.8.3
Tested up to: 6.8.1
Requires PHP: 7.4
WC requires at least: 6.1.0
WC tested up to: 9.9.3
Stable tag: 1.11.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==
**Accept Visa, MasterCard, Vipps, Apple Pay, Google Pay, Click to Pay, Swish, MobilePay, 
Klarna, Walley, Billie, invoices, installments, and buy now pay later (BNPL) payment 
solutions.**

With Dintero Checkout for WooCommerce Payment Methods, you can either embed or redirect our checkout in your WooCommerce installation. The plugin lets you capture, cancel, refund or partially refund orders, and adapt the checkout for B2B / B2C customers or just one customer type. You can also customize branding with logo colors and placement.

**Dintero is available for online/offline store owners and merchants in:**

- Norway
- Sweden
- Denmark

### Why choose Dintero payment?

Dintero Checkout delivers the ultimate formula for high conversions with customer-first designed features, such as: 

-  Remembering customer details  
-  Localised payment methods and language  
-  Proactive technical solutions to prevent shipping errors before they occur.  

Our feature-rich components create a seamless, effortless buying experience for customers while offering full customisation for businesses. And is a one stop shop for digital payment, offering card payments, invoice, installments, BNPL, and mobile payment solutions.

### High conversions
Supercharge your sales with a high converting checkout solution. Offer all the payment methods your customers want, globally and locally. One payment solution, endless possibilities. 

### Manage all your payments in one place with Dintero Backoffice

No more multiple logins. With our powerful Backoffice, there is one place to view and manage all payments, payouts, and reports. You can even do reconciliation and integrate account software.

### Payment Methods:

**Cards**
- Visa
- MasterCard

**Mobile**
- Vipps
- Swish
- MobilePay

**Wallets**
- Apple Pay
- Google Pay
- Click to Pay

**BNPL (Buy Now Pay Later), Installments, & Invoices**
- Klarna
- Walley
- Billie

For the full list of Payment Methods, supported countries, and pricing, visit our [Pricing](https://www.dintero.com/pricing?utm_source=wordpressplugin&utm_medium=plugin) page.

### Dintero Express Checkout Features  

The Dintero Express Checkout remembers customer details (if they opt-in) so we can give them an even faster and frictionless checkout experience with their next purchases, which increases conversion rates for returning customers. Available for embedded solutions.

### API  
Dintero Checkout supports any business model, and with our APIs you can automate and simplify the user experience for your customers.

=== Getting started ===

Get started with Dintero Checkout for WooCommerce Payment Methods in 4 easy steps. 

1. [Create a Dintero account](https://dintero.com/get-started?utm_source=wordpressplugin&utm_medium=plugin)
2. In [Dintero Backoffice](https://backoffice.dintero.com?utm_source=wordpressplugin&utm_medium=plugin), go to Settings > Payment methods and click Activate 
payments. We’ll notify you once approved.
3. [Install the Dintero plugin](https://www.dintero.com/our-services/dintero-checkout/install-woocommerce-plugin?utm_source=wordpressplugin&utm_medium=plugin) on your website.
4. [Create API keys](https://docs.dintero.com/docs/checkout/checkout-client/?utm_source=wordpressplugin&utm_medium=plugin) in Dintero Backoffice, and enter them into the WooCommerce Dintero plugin’s settings.

### Dependencies  

**Dintero Web SDK**
The plugin uses Dintero’s Web SDK for embedding the Dintero Checkout. The SDK can be found at [GitHub](https://github.com/Dintero/Dintero.Checkout.Web.SDK?utm_source=wordpressplugin&utm_medium=plugin), and is licensed with an MIT license.  

The SDK follows the same terms as when creating a Dintero account, with its [terms of service](https://www.dintero.com/terms/terms-of-service?utm_source=wordpressplugin&utm_medium=plugin) and [privacy policy](https://www.dintero.com/legal/privacy-policy?utm_source=wordpressplugin&utm_medium=plugin).  

### FAQ  

Does this require an SSL certificate?  
> Yes, you need a certificate with at least TLS 1.2 to use Dintero Checkout.  

Does this support both production mode and sandbox mode for testing?  
> Yes, sandbox is instantly available for all new accounts.  

Where can I find documentation?  
> Go to [Dintero Documentation](https://docs.dintero.com/docs/checkout/plugins/woo-plugin?utm_source=wordpressplugin&utm_medium=plugin) to find more thorough documentation.  

Where can I get support?  
> Go to [Dintero Support](https://www.dintero.com/support)

== Installation ==
1. Upload plugin folder to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Dintero Checkout settings.
4. Read more about the configuration process in the [plugin documentation](https://www.dintero.com/our-services/dintero-checkout/install-woocommerce-plugin?utm_source=wordpressplugin&utm_medium=plugin).

== Frequently Asked Questions ==

= Does this require an SSL certificate? =

Yes, you need a certificate with at least TLS 1.2 to use Dintero Checkout.

= Does this support both production mode and sandbox mode for testing? =

Yes, a Sandbox environment is instantly available for all new accounts created via [onboarding.dintero.com](https://onboarding.dintero.com?utm_source=wordpressplugin&utm_medium=plugin).

= Where can I find documentation? =

Go [here](https://www.dintero.com/our-services/dintero-checkout/install-woocommerce-plugin?utm_source=wordpressplugin&utm_medium=plugin) to find a more thorough documentation.

= Where can I get support? =

Go to [https://www.dintero.com/contact-us](https://www.dintero.com/contact-us?utm_source=wordpressplugin&utm_medium=plugin) to talk to our amazing support team.

== Screenshots ==

1. High converting feature-rich Checkout that creates a seamless, effortless buying experience for customers while offering full customisation for businesses.
2. Exceed customer expectations by offering the most relevant local and global payment methods.
3. Our Checkout remembers customer details (if they opt-in) so we can give them an even faster and frictionless checkout experience with their next purchases, which increases conversion rates for returning customers.
4. Over 10 languages, including all the Nordics, customers can choose, plus automatic language selection based off browser settings.
5. Auto detect and suggest potential incorrect shipping details to reduce shipping errors.
6. Gift cards and Discount cards available.
7. Remove headaches and manage your reconciliation, reports, transactions, refunds (transaction level), Gift Cards, payment methods, and more in the Dintero backoffice.
== Changelog ==
= 2025.06.17    - version 1.11.3 =
* Fix           - Fixed a potential fatal error during checkout that could occur when the shipping option price was returned as a string that is not a valid number, caused by WooCommerce version 9.9.x. This issue has been addressed in WooCommerce version 9.9.4.
* Fix           - Addresses uncaught error caused when calculating the shipping cost.
* Fix           - Fixed undefined 'use_default' index warning in widget.
* Fix           - Fixed a critical error when processing the backtrace in the log handler.
* Tweak         - Delayed loading textdomain until init. This should address the issue where the error log would be flooded with warnings related to the textdomain being loaded too early.

= 2025.06.11    - version 1.11.2 =
* Fix           - Addresses uncaught error caused when calculating the shipping cost.

= 2025.04.28    - version 1.11.1 =
* Fix           - Updated deployment script to include assets.

= 2025.04.28    - version 1.11.0 =
* Feature       - Added support for WooCommerce Subscriptions.
* Feature       - Added support for pickup points.
* Tweak         - Updated Web SDK to v0.8.23.
* Tweak         - Updated POT translation file.
* Tweak         - Removed the 'Dintero order created' order note.
* Tweak         - Error notices won't be displayed if an API error occurs during the serving of a REST request.
* Tweak         - Decodes encoded HTML entities in the shipping title.
* Tweak         - Limit the max size of a log message from the frontend to 1000 to prevent large logs from being created.
* Fix           - Added checks for if WP error in case the session could not be retrieved during the order totals verification AJAX request.
* Fix           - Fixed a critical error that could occur when the cart only contained virtual products.
* Fix           - Fixed pay for order not available when subscriptions is enabled.
* Fix           - Update the shipping line ID to account for pickup points.
* Fix           - Prevents the customer-entered address from being overwritten in non-express embedded checkout.
* Enhancement   - Added the 'dintero-logos' class to the payment method icon.
* Enhancement   - Added an admin notice if zero decimals is enabled in WooCommerce.

= 2024.08.19    - version 1.10.7 =
* Fix           - Fixed an issue related to 'woocommerce_new_order_item' where calling save() would result in the order ID being set to 0. This should improve compatibility with other third-party plugins that hook onto this action.

= 2024.07.11    - version 1.10.6 =
* Fix           - Fixed an issue related to the plugin settings overhaul. The setting migration should now work as intended. Please verify that the new "Checkout flow" plugin setting option is what you expect.

= 2024.07.01    - version 1.10.5 =
* Fix           - Fixed an issue where if the "Shipping destination" in Woo was set to default to shipping, the checkout validation would always be rejected due to missing shipping address.
* Fix           - Fixed an issue where the shipping address fields were not always enabled, although the plugin setting "Allow separate shipping address" was enabled.

= 2024.06.26    - version 1.10.4 =
* Fix           - Fixed an issue where the CSS file was not enqueued causing the checkout layout for Express checkout to always be a single-column.

= 2024.06.25    - version 1.10.3 =
* Fix           - Fixed critical error when reading settings.

= 2024.06.25    - version 1.10.2 =
* Fix           - Fixed an issue where if only one customer type was set, and separate shipping was enabled, a JSON error would occur.
* Tweak         - The plugin settings has been overhauled. While your existing settings should be automatically converted, please verify that this is the case, and make sure to save the settings again.

= 2024.06.19    - version 1.10.1 =
* Tweak         - Bump plugin version in main PHP file.

= 2024.06.19    - version 1.10.0 =
* Feature       - Add support for different billing and shipping address in Express checkout.
* Fix           - The shipping company should now be saved to the order as intended.
* Tweak         - WooCommerce Blocks is not yet supported.
* Tweak         - Updated Web SDK to v0.8.8.

= 2024.05.27    - version 1.9.3 =
* Tweak         - Respond with a 500 status code in callback if the order is not found.
* Tweak         - Account for manually refunded items in calculation when performing a capture.
* Tweak         - Add the captured or refunded amount to the order note.

= 2024.04.25    - version 1.9.2 =
* Tweak         - Add a maximum threshold when verifying order total to account for rounding error.

= 2024.04.17    - version 1.9.1 =
* Enhancement   - The payment method (also known as "payment product type") can be retrieved from the order's metadata as '_dintero_payment_method'.
* Fix           - Display an error if order total does not match.

= 2023.11.21    - version 1.9.0 =
* Feature       - Added setting for using the new pop-out modal in embedded checkout.

= 2023.11.07    - version 1.8.1 =
* Fix           - Fixed an issue where the order could not be identified by merchant reference.

= 2023.10.31    - version 1.8.0 =
* Feature       - Added support for WooCommerce's "High-Performance Order Storage" ("HPOS).
* Tweak         - The plugin "Footer background color" setting has been removed. Please, use the more versatile and customizable [branding widget](https://docs.krokedil.com/dintero-checkout-for-woocommerce/get-started/introduction/#dintero-checkout-branding-widget) instead.
* Fix           - Fixed an issue where the widget settings were not always properly saved.

= 2023.09.26    - version 1.7.1 =
* Fix           - Fixed an issue related to Embedded Dintero Checkout where if a guest user entered their billing and shipping information, WooCommerce wouldn't pass all the available field data for prefilling the form fields in the Dintero payment.

= 2023.09.20    - version 1.7.0 =
* Feature       - You can now set the default order status for orders pending authorization to "on-hold". Defaults to "manual-review".
* Fix           - An attempt to capture order that contain a product that has been permanently removed will now fail without causing an critical error.
* Fix           - Fixed an issue where a refund would fail due to missing order metadata, and the default shipping line data would be used instead of the one stored in the parent order.

= 2023.08.28    - version 1.6.1 =
* Fix           - Fixed an issue where you could no longer cancel an on-hold orders if the default order status was set to on-hold for not yet authorized orders.
* Fix           - Fixed an issue where orders pending authorization were locked even after being authorized.
* Tweak         - The order information will now be displayed in the meta box even while pending authorization.

= 2023.05.10    - version 1.6.0 =
* Feature       - If the product image and/or thumbnail is available, their URL will be sent in the request.
* Tweak         - The access token is now always renewed whenever the settings are updated.
* Tweak         - To easier identify checkout issues, the SID is logged in case of AJAX or other frontend WooCommerce errors.
* Tweak         - Updated SDK to v0.5.5.

= 2023.03.09    - version 1.5.1 =
* Fix           - Email notification should now be sent as expected when changing order status from "Manual review" provided that email notifications is enabled in the WooCommerce settings.
* Fix           - Fixed an issue where placing an order failed when the shipping address fields were unset, and Embedded Dintero Checkout was used. 
* Tweak         - A blank character is used as a placeholder when shipping first and last name is not available. Previously, this was "N/A".

= 2023.02.01    - version 1.5.0 =
* Feature       - Manually changing the order status to "Refunded" will now refund the corresponding Dintero order. This behavior must be enabled in the plugin's settings (see "Refund by changing order status").
* Fix           - Delay filling placeholder data until the purchase is about to be finalized. This fixes the issue with first and last name having "N/A" as a value for certain B2B purchases.
* Tweak         - Updated SDK.
* Tweak         - Updated WordPress catalog README.

= 2022.12.08    - version 1.4.0 =
* Feature       - Added a direct link in the order's meta box to the corresponding backoffice transaction.
* Fix           - Fixed a JavaScript runtime error due to the shipping fields being unset.

= 2022.11.28    - version 1.3.4 =
* Tweak         - Removed link to Dintero from payment gateway icon to prevent accidental redirection.

= 2022.11.03    - version 1.3.3 =
* Fix           - Fixed an issue where business purchases failed due to missing first and last name.
* Fix           - Fix stable tag (from 1.3.2 to 1.3.3).

= 2022.11.03    - version 1.3.2 =
* Fix           - Fixed an issue where business purchases failed due to missing first and last name.

= 2022.10.27    - version 1.3.1 =
* Tweak         - The setting for changing the layout should now only be shown when the chosen form factor is "embedded".
* Tweak         - Updated to the most recent Web SDK.

= 2022.10.11    - version 1.3.0 =
* Feature       - Added branding backlinks.
* Fix           - The company name (if available) should now be saved to the order.
* Fix           - Fixed an issue with a third-party plugin that prevented customers from finalizing the order.
* Tweak         - Enhanced logging for easier troubleshooting.

= 2022.10.03    - version 1.2.3 =
- Fix           - Fixed an error in the embedded checkout that would occur if the cart contained shippable products, but no shipping method is available.
- Fix           - The "Display shipping in the iframe" option should now work as expected.
- Tweak         - It should now be more clear for customers when a payment is in progress.

= 2022.09.15    - version 1.2.2 =
* Fix           - Fixed an issue where the embedded checkout layout would sometimes switch from two-column to single-column when trying to change payment method.
* Fix           - Fixed an issue with switching from embedded checkout if Dintero Checkout was set as the first payment gateway.
* Enhancement   - If customer data is available it will be used for automatically filling the payment form when the checkout page is first loaded.

= 2022.09.07    - version 1.2.1 =
* Fix           - Prevent fatal error with latest YITH WooCommerce Gift Card version.

= 2022.08.24    - version 1.2.0 =
* Feature       - Added additional checkout fields, and hooks.
* Tweak         - Updated to most recent Web SDK.

= 2022.08.18    - version 1.1.2 =
* Tweak         - Better error handling especially in situations where there is no front end to display the error message (e.g., in a cronjob environment).
* Fix           - Fixed various PHP notices.
* Fix           - Fixed backward compatibility problem for orders that were placed prior to version 1.1.0. 

= 2022.08.16    - version 1.1.1 =
* Fix           - The metadata 'dintero_checkout_line_id' should now be hidden.

= 2022.08.15    - version 1.1.0 =
* Feature       - Added compatibility with bundle products.
* Tweak         - Handle expired session during checkout (embedded only).
* Fix           - New access token is now automatically generated whenever switching between test and production mode.
* Fix           - The "Go to payment" option label (redirect only) should now apply on the correct buttons.

= 2022.07.14    - version 1.0.8 =
* Tweak         - Removed obsolete setting.
* Tweak         - Tweaked meta box formatting.
* Tweak         - The access token is no longer logged.
* Fix           - Fixed branding image not appearing on checkout page, and in the widget.
* Enhancement   - Checkout error will now appear as a notice on the checkout page.

= 2022.06.30    - version 1.0.7 =
* Feature       - Added a meta box (must be enabled in the "Screen options") to the order page that displays the Dintero order's status, which payment method was chosen, as well as the environment (test v. production).

= 2022.06.27    - version 1.0.6 =
* Feature       - You can now display the embedded express checkout horizontally ("two-column" layout).

= 2022.06.16    - version 1.0.5 =
* Fix           - Callbacks are now scheduled for later processing. This should fix an issue with certain callback events not being handled.

= 2022.06.15    - version 1.0.4 =
* Fix           - Fix transaction ID missing for some orders.
* Fix           - Fix merchant_reference_2 missing for some orders.
* Fix           - The VAT for display purposes should now appear on the backoffice.
* Tweak         - Streamlined the logic for in-store and callback orders.

= 2022.06.09    - version 1.0.3 =
* Fix           - Fix stable tag (from 1.0.2 to 1.0.3).

= 2022.06.09    - version 1.0.2 =
* Fix           - Fixed some text not being translated.
* Fix           - Orders created on callback should now have their order status updated accordingly.
* Tweak         - An order will be set to ON HOLD if it is missing a transaction id when changing its status.

= 2022.06.01    - version 1.0.1 =
* Plugin published on wordpress.org.

= 2022.05.10    - version 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
If you've previously used our old plugin and want to upgrade to this one, contact us [here](https://www.dintero.com/contact-us).