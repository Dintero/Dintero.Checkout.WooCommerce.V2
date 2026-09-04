/**
 * WordPress Playground dev-environment config for this plugin.
 * Consumed by @krokedil/wp-playground-tools — see its README for the full schema.
 */
import { envSecret } from '@krokedil/wp-playground-tools';

export default {
	slug: 'dintero-checkout-for-woocommerce',
	siteName: 'Dintero Checkout',
	landingPage: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=dintero_checkout',

	// Claimed in the org port registry (the package README). Modes listen on
	// basePort (start), +1 (development), +2 (demo); --https proxies on +400.
	basePort: 8960,

	php: '8.3',

	// wpify-scoper plugin: composer install also generates the prefixed
	// dependencies/ folder, without which the plugin bails on activation.
	composer: { markers: [ 'vendor/autoload.php', 'dependencies/autoload.php' ] },

	// No `build`: the minified JS bundles and the Dintero web SDK are
	// committed to the repo (assets/js/*.min.js).

	// Store defaults (SE/SEK, Storefront theme) match the Krokedil demo site.
	// Note: the demo site's commercial plugins (WooCommerce Subscriptions,
	// Aelia Currency Switcher, Sequential Order Numbers Pro) are not on
	// wordpress.org and must be installed manually when needed.

	options: {
		all: {
			// The full form-field defaults are seeded (not only the keys that
			// differ): the plugin reads some of them from the settings array
			// without a fallback (e.g. express_customer_type,
			// branding_logo_color), which warns when the option lacks them.
			// On a real site saving the settings form persists every default.
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
				// The ?? fallbacks matter: the request classes read this
				// option array raw (load_settings() in
				// classes/requests/class-dintero-checkout-request.php), so an
				// omitted key warns at runtime instead of falling back to the
				// form-field default. With the fallbacks, a credentialless
				// playground is silently unconfigured rather than noisy.
				account_id: envSecret( 'DINTERO_TEST_ACCOUNT_ID' ) ?? '',
				client_id: envSecret( 'DINTERO_TEST_CLIENT_ID' ) ?? '',
				client_secret: envSecret( 'DINTERO_TEST_CLIENT_SECRET' ) ?? '',
				profile_id: envSecret( 'DINTERO_TEST_PROFILE_ID' ) ?? 'default',
			},
		},
	},

	// For --tunnel (payment redirects/callbacks): the company wildcard gives
	// each worktree its own derived, stable host.
	tunnel: { provider: 'ngrok', domain: '*.krokedil.ngrok.io' },
};
