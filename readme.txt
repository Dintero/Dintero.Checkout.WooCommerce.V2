=== Dintero Checkout for WooCommerce ===
Contributors: dintero, krokedil, NiklasHogefjord
Tags: woocommerce, dintero, ecommerce, e-commerce, checkout
Requires at least: 5.8.3
Tested up to: 6.0
Requires PHP: 7.0
WC requires at least: 6.1.0
WC tested up to: 6.5.1
Stable tag: 1.0.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== DESCRIPTION ==
Dintero Checkout provides a frictionless checkout experience, offering card payments, invoice, installments and mobile payment solutions.

With this plugin, you can either embed or redirect our checkout in your WooCommerce installation. The plugin lets you capture, cancel, refund or partially refund orders, and adapt the checkout to B2B customers, B2C customers or both. You can also customize payment logo colors and 
placement.


=== Getting started ===
1. Go to [onboarding.dintero.com](https://onboarding.dintero.com/) to sign up for a Dintero account.
2. Get your payment method application approved by Dintero.
3. Create your [API keys](https://docs.dintero.com/docs/checkout-client.html).
4. Install the plugin on your website.

== Dependencies ==
=== Dintero Web SDK ===
The plugin uses Dintero's Web SDK for embedding the Dintero Checkout. The SDK can be found at https://github.com/Dintero/Dintero.Checkout.Web.SDK, and is licensed with a MIT license.
The SDK follows the same terms as when creating a Dintero account, with its [terms of service](https://www.dintero.com/terms/terms-of-service) and [privacy policy](https://www.dintero.com/legal/privacy-policy).

== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Dintero Checkout settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/dintero-checkout-for-woocommerce/).

== Changelog ==
= 2022.06.09    - version 1.0.2 =
* Fix           - Fixed some text not being translated.
* Fix           - Orders created on callback should now have their order status updated accordingly.
* Tweak         - An order will be set to ON HOLD if it is missing a transaction id when changing its status.

= 2022.06.01    - version 1.0.1 =
* Plugin published on wordpress.org.

= 2022.05.10    - version 1.0.0 =
* Initial release.