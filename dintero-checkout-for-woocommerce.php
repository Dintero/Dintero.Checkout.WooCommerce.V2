<?php //phpcs:ignore
/**
 * Plugin Name: Dintero Checkout for WooCommerce Payment Methods
 * Plugin URI: https://krokedil.com/products/
 * Description: Dintero offers a complete payment solution. Simplifying the payment process for you and the customer.
 * Author: Dintero, Krokedil
 * Author URI: https://krokedil.com/
 * Version: 1.13.1
 * Text Domain: dintero-checkout-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 6.1.0
 * WC tested up to: 10.3.4
 *
 * Copyright (c) 2025 Krokedil
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use KrokedilDinteroCheckoutDeps\Krokedil\Shipping\Interfaces\PickupPointServiceInterface;
use KrokedilDinteroCheckoutDeps\Krokedil\Shipping\PickupPoints;
use KrokedilDinteroCheckoutDeps\Krokedil\Shipping\ShippingRate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DINTERO_CHECKOUT_VERSION', '1.13.1' );
define( 'DINTERO_CHECKOUT_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'DINTERO_CHECKOUT_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'DINTERO_CHECKOUT_MAIN_FILE', __FILE__ );

if ( ! class_exists( 'Dintero' ) ) {

	/**
	 * Class Dintero.
	 */
	class Dintero {

		/**
		 * Reference to the API class.
		 *
		 * @var Dintero_Checkout_API $api
		 */
		public $api;

		/**
		 * Reference to the order management class.
		 *
		 * @var Dintero_Checkout_Order_Management
		 */
		public $order_management;

		/**
		 * Pickup points service.
		 *
		 * @var PickupPointServiceInterface $pickup_points
		 */
		private $pickup_points;

		/**
		 * Shipping rate helper.
		 *
		 * @var ShippingRate
		 */
		private $shipping_rate;

		/**
		 * Subscription class instance.
		 *
		 * @var Dintero_Checkout_Subscription $subscriptions
		 */
		private $subscriptions;

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var Dintero $instance
		 */
		private static $instance;

		/**
		 * Get the shipping rate helper.
		 *
		 * @return ShippingRate
		 */
		public function shipping_rate() {
			return $this->shipping_rate;
		}

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @static
		 * @return Dintero The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}


		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'plugin_loaded', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			// Postpone loading of textdomain until the plugin is loaded.
			add_action( 'init', array( $this, 'load_textdomain' ), 0 );
			// Since the Widget class references the textdomain, we need to load it after the textdomain is registered.
			add_action( 'widgets_init', array( $this, 'register_widget' ) );
		}

		public function register_widget() {
			register_widget( 'Dintero_Checkout_Widget' );
		}

		/**
		 * Initialize composers autoloader.
		 *
		 * @return bool Whether it was successfully initialized.
		 */
		public function init_composer() {
			$autoloader_dependencies = __DIR__ . '/dependencies/scoper-autoload.php';

			// Check if the autoloaders was read.
			$autoloader_dependencies_result = is_readable( $autoloader_dependencies ) && require $autoloader_dependencies;
			if ( ! $autoloader_dependencies_result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( //phpcs:ignore
						sprintf(
							/* translators: 1: composer command. 2: plugin directory */
							esc_html__( 'Your installation of the Dintero Checkout plugin is incomplete. Please run %1$s within the %2$s directory.', 'ledyer-payments-for-woocommerce' ),
							'`composer install`',
							'`' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '`'
						)
					);
				}

				// Add an admin notice, use anonymous function to simplify, this does not need to be removable.
				add_action(
					'admin_notices',
					function () {
						?>
						<div class="notice notice-error">
							<p>
								<?php
								printf(
									/* translators: 1: composer command. 2: plugin directory */
									esc_html__( 'Your installation of the Dintero Checkout plugin is incomplete. Please run %1$s within the %2$s directory.', 'ledyer-payments-for-woocommerce' ),
									'<code>composer install</code>',
									'<code>' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '</code>'
								);
								?>
							</p>
						</div>
						<?php
					}
				);

				return false;
			}

			return true;
		}

		/**
		 * Initialize the payment gateway.
		 *
		 * @return void
		 */
		public function init() {
			if ( ! $this->init_composer() ) {
				return;
			}

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			include_once DINTERO_CHECKOUT_PATH . '/includes/dintero-checkout-functions.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-settings-fields.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-gateway.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-logger.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-assets.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-api.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-confirmation.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-order-management.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-callback.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-widget.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-templates.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-ajax.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-embedded.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-shipping-settings.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-order-status.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-meta-box.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-subscription.php';

			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request-get.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request-post.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request-put.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-get-access-token.php';

			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/helpers/class-dintero-checkout-helper-base.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/helpers/class-dintero-checkout-cart.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/helpers/class-dintero-checkout-order.php';

			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/get/class-dintero-checkout-get-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/get/class-dintero-checkout-get-session-profile.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/get/class-dintero-checkout-get-session-profile.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/get/class-dintero-checkout-get-session.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-create-session.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-cancel-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-capture-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-refund-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-sessions-pay.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-payment-token.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/put/class-dintero-checkout-update-checkout-session.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/put/class-dintero-checkout-update-transaction.php';

			$this->api              = new Dintero_Checkout_API();
			$this->pickup_points    = new PickupPoints();
			$this->shipping_rate    = new ShippingRate( array( 'show_description' => false ) );
			$this->order_management = Dintero_Checkout_Order_Management::get_instance();
			$this->subscriptions    = new Dintero_Checkout_Subscription();

			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

			add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
			add_action( 'admin_notices', array( $this, 'dintero_wc_zero_decimal_notice' ) );
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'dintero-checkout-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );
		}

		/**
		 * Display a notice if the WooCommerce decimal setting is set to 0.
		 *
		 * @return void
		 */
		public function dintero_wc_zero_decimal_notice() {

			if ( get_user_meta( get_current_user_id(), 'dismissed_dintero_wc_zero_decimal_notice', true ) ) {
				return;
			}

			$decimals_setting = get_option( 'woocommerce_price_num_decimals', 2 );

			if ( 0 == $decimals_setting ) {
				$article_url = 'https://krokedil.com/dont-display-prices-with-0-decimals-in-woocommerce/';
				?>
				<div class="notice notice-warning woocommerce-message">
						<a class="woocommerce-message-close notice-dismiss"
							href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'dintero_wc_zero_decimal' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>">
							<?php esc_html_e( 'Dismiss', 'woocommerce' ); ?>
						</a>
					<p><strong><?php esc_html_e( 'The decimal setting for prices in WooCommerce is currently set to 0.', 'dintero-checkout-for-woocommerce' ); ?></strong></p>
					<p><?php esc_html_e( 'This may cause rounding issues with pricing and taxes. Learn more about why this can be problematic — and what to do instead: ', 'dintero-checkout-for-woocommerce' ); ?><a href="<?php echo esc_url( $article_url ); ?>" target="_blank"><?php esc_html_e( 'Don’t display prices with 0 decimals in WooCommerce', 'dintero-checkout-for-woocommerce' ); ?></a>.</p>
				</div>
				<?php
			}
		}

		/**
		 * Declare compatibility with WooCommerce features.
		 *
		 * @return void
		 */
		public function declare_wc_compatibility() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				// Declare HPOS compatibility.
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				// Declare Checkout Blocks incompatibility.
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
			}
		}


		/**
		 * Add plugin action links.
		 *
		 * @param array $links Plugin action link before filtering.
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$settings_link = $this->get_settings_link();
			$plugin_links  = array(
				'<a href="' . $settings_link . '">' . __( 'Settings', 'dintero-checkout-for-woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get URL link to Dintero's settings in WooCommerce.
		 *
		 * @return string Settings link
		 */
		public function get_settings_link() {
			$section_slug = 'dintero_checkout';

			$params = array(
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => $section_slug,
			);

			return esc_url( add_query_arg( $params, 'admin.php' ) );
		}

		/**
		 * Add the gateways to WooCommerce.
		 *
		 * @param array $methods Payment methods.
		 * @return array Payment methods with Dintero added.
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'Dintero_Checkout_Gateway';

			return $methods;
		}

		/**
		 * Get the pickup points service.
		 *
		 * @return PickupPointServiceInterface
		 */
		public function pickup_points() {
			return $this->pickup_points;
		}

		/**
		 * Get the subscriptions service.
		 *
		 * @return Dintero_Checkout_Subscription
		 */
		public function subscriptions() {
			return $this->subscriptions;
		}
	}

	Dintero::get_instance();
}

/**
 * Main instance Dintero.
 *
 * Returns the main instance of Dintero.
 *
 * @return Dintero
 */
function Dintero() { // phpcs:ignore
	return Dintero::get_instance();
}
