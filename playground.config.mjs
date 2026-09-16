/**
 * WordPress Playground dev-environment config for this plugin.
 * Consumed by @krokedil/wp-playground-tools — see its README for the full schema.
 */
import { envSecret } from '@krokedil/wp-playground-tools';

export default {
	slug: 'dintero-checkout-for-woocommerce',
	siteName: 'Dintero Checkout',
	landingPage: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=dintero_checkout',

	// Claimed in the tooling README's port registry.
	basePort: 8960,

	php: '8.3',

	// dependencies/ is the wpify-scoper output; the plugin bails without it.
	composer: { markers: [ 'vendor/autoload.php', 'dependencies/autoload.php' ] },

	// No `build`: the minified JS and Dintero web SDK are committed.

	// Store defaults (SE/SEK, Storefront) match the Krokedil demo site; its
	// commercial plugins (e.g. WooCommerce Subscriptions) must be installed manually.

	options: {
		all: {
			// Seed every form-field default and keep the ?? fallbacks: the
			// plugin reads some keys from this option without a fallback and
			// warns when they are missing.
			woocommerce_dintero_checkout_settings: {
				enabled: 'yes',
				test_mode: 'yes',
				logging: 'yes',
				checkout_flow: 'express_popout',
				checkout_layout: 'two_column_right',
				redirect_title: 'Dintero Checkout',
				redirect_description: '',
				redirect_select_another_method_text: '',
				order_status_authorized: 'processing',
				order_status_pending_authorization: 'manual-review',
				order_management_manual_refund: 'yes',
				express_customer_type: 'b2bc',
				express_shipping_in_iframe: 'no',
				express_allow_different_billing_shipping_address: 'no',
				branding_logo_color: 'yes',
				branding_logo_color_custom: '',
				branding_logo_color_mode: 'logomark',
				subscription_profile_id: '',
				account_id: envSecret( 'DINTERO_TEST_ACCOUNT_ID' ) ?? '',
				client_id: envSecret( 'DINTERO_TEST_CLIENT_ID' ) ?? '',
				client_secret: envSecret( 'DINTERO_TEST_CLIENT_SECRET' ) ?? '',
				profile_id: envSecret( 'DINTERO_TEST_PROFILE_ID' ) ?? 'default',
			},
		},
	},

	tunnel: { provider: 'ngrok', domain: '*.krokedil.ngrok.io' },
};
