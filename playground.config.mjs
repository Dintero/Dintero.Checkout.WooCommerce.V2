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
			woocommerce_dintero_checkout_settings: {
				enabled: 'yes',
				test_mode: 'yes',
				logging: 'yes',
				checkout_flow: 'express_popout',
				account_id: envSecret( 'DINTERO_TEST_ACCOUNT_ID' ),
				client_id: envSecret( 'DINTERO_TEST_CLIENT_ID' ),
				client_secret: envSecret( 'DINTERO_TEST_CLIENT_SECRET' ),
				// Omitted when the env var is unset — the plugin then falls
				// back to its default profile id, 'default'.
				profile_id: envSecret( 'DINTERO_TEST_PROFILE_ID' ),
			},
		},
	},

	// For --tunnel (payment redirects/callbacks): the company wildcard gives
	// each worktree its own derived, stable host.
	tunnel: { provider: 'ngrok', domain: '*.krokedil.ngrok.io' },
};
