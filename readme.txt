=== Dintero Checkout for WooCommerce Payment Methods ===
Contributors: dintero, krokedil, NiklasHogefjord
Tags: woocommerce, dintero, ecommerce, e-commerce, checkout
Requires at least: 5.8.3
Tested up to: 6.0
Requires PHP: 7.0
WC requires at least: 6.1.0
WC tested up to: 6.5.1
Stable tag: 1.0.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==
Accept Visa, MasterCard, Vipps, Swish, MobilPay, Walley, invoices, and installments.

With Dintero Checkout for WooCommerce Payment Methods, you can either embed or redirect our checkout in your WooCommerce installation.

The plugin lets you capture, cancel, refund or partially refund orders, and adapt the checkout to B2B customers, B2C customers or both. You can also customize payment logo colors and placement.

Dintero is available for store owners and merchants in:

- Norway
- Sweden

=== Why choose Dintero Checkout ===

==== Payment Methods ====

Dintero Checkout provides a frictionless checkout experience with no hidden fees. It is a one stop shop for digital payment, offering card payments, invoice, installments and mobile payment solutions.

==== API ====

Dintero checkout supports any business model, and with our APIs you can automate and simplify the user experience for your customers.

==== Backoffice ====

No more multiple logins. With our powerful Backoffice, there is one place to view all payments, all payouts, and all reports. You can even do reconciliation.


==== Getting started ====

It’s  easy to get started with Dintero Checkout for WooCommerce .

1. Create a Dintero account at onboarding.dintero.com.
2. Verify your Dintero account by clicking the link in the email we’ll send you.
3. Return to [onboarding.dintero.com](https://onboarding.dintero.com), and create a password.
4. Log into [Dintero Backoffice](https://backoffice.dintero.com), apply for payment methods, and wait for approval by Dintero.
5. Install the Dintero plugin on your website.
6. Create API keys in Dintero Backoffice, and enter them into the Dintero plugin’s settings.

== Dependencies ==
=== Dintero Web SDK ===
The plugin uses Dintero's Web SDK for embedding the Dintero Checkout. The SDK can be found at [https://github.com/Dintero/Dintero.Checkout.Web.SDK](https://github.com/Dintero/Dintero.Checkout.Web.SDK), and is licensed with a MIT license.
The SDK follows the same terms as when creating a Dintero account, with its [terms of service](https://www.dintero.com/terms/terms-of-service) and [privacy policy](https://www.dintero.com/legal/privacy-policy).

== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
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
