=== Dintero Checkout for WooCommerce Payment Methods ===
Contributors: dintero, krokedil, NiklasHogefjord
Tags: woocommerce, dintero, ecommerce, e-commerce, checkout
Requires at least: 5.8.3
Tested up to: 6.0.2
Requires PHP: 7.0
WC requires at least: 6.1.0
WC tested up to: 6.9.4
Stable tag: 1.3.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==
Accept Visa, MasterCard, Vipps, Swish, MobilePay, Walley, invoices, and installments.

With Dintero Checkout for WooCommerce Payment Methods, you can either embed or redirect our checkout in your WooCommerce installation.

The plugin lets you capture, cancel, refund or partially refund orders, and adapt the checkout to B2B customers, B2C customers or both. You can also customize payment logo colors and placement.

Dintero is available for store owners and merchants in:

- Norway
- Sweden

=== Why choose Dintero Checkout ===

= Payment Methods =

Dintero Checkout provides a frictionless checkout experience with no hidden fees. It is a one-stop shop for digital payment, offering card payments, invoice, installments and mobile payment solutions.

= API =

Dintero checkout supports any business model, and with our APIs you can automate and simplify the user experience for your customers.

= Backoffice =

No more multiple logins. With our powerful Backoffice, there is one place to view all payments, all payouts, and all reports. You can even do reconciliation.


=== Getting started ===

Get started with Dintero Checkout for WooCommerce Payment Methods in 4 easy steps.

1. [Create a Dintero account](https://dintero.com/get-started?utm_source=wordpressplugin&utm_medium=plugin&utm_campaign=WPplugin22&utm_content=Plugin-a1)
4. Log into [Dintero Backoffice](https://backoffice.dintero.com), apply for payment methods and we’ll notify you once approved.
5. [Install the Dintero plugin](https://www.dintero.com/our-services/dintero-checkout/install-woocommerce-plugin?utm_source=wordpressplugin&utm_medium=plugin&utm_campaign=WPplugin22&utm_content=Plugin-a2) on your website.
6. Create API keys in Dintero Backoffice, and enter them into the Dintero plugin’s settings.

== Dependencies ==

= Dintero Web SDK =

The plugin uses Dintero's Web SDK for embedding the Dintero Checkout. The SDK can be found at [https://github.com/Dintero/Dintero.Checkout.Web.SDK](https://github.com/Dintero/Dintero.Checkout.Web.SDK), and is licensed with a MIT license.
The SDK follows the same terms as when creating a Dintero account, with its [terms of service](https://www.dintero.com/terms/terms-of-service) and [privacy policy](https://www.dintero.com/legal/privacy-policy).

== Installation ==
1. Upload plugin folder to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Dintero Checkout settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/dintero-checkout-for-woocommerce/).


== Frequently Asked Questions ==

= Does this require an SSL certificate? =

Yes, you need a certificate with at least TLS 1.2 to use Dintero Checkout

= Does this support both production mode and sandbox mode for testing? =

Yes, a Sandbox environment is instantly available for all new accounts created via [onboarding.dintero.com](https://onboarding.dintero.com).

= Where can I find documentation? =

Go [here](https://www.dintero.com/our-services/dintero-checkout/woocommerce) to find a more thorough documentation.

= Where can I get support? =

Go to [https://www.dintero.com/contact-us](https://www.dintero.com/contact-us) to talk to our amazing support team.

== Screenshots ==

1. The plugin settings screen where you set up the details to connect to Dintero.

== Changelog ==
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
