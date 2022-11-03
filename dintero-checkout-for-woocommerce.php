<?php //phpcs:ignore
/**
 * Plugin Name: Dintero Checkout for WooCommerce Payment Methods
 * Plugin URI: https://krokedil.com/products/
 * Description: Dintero offers a complete payment solution. Simplifying the payment process for you and the customer.
 * Author: Dintero, Krokedil
 * Author URI: https://krokedil.com/
 * Version: 1.3.2
 * Text Domain: dintero-checkout-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 6.1.0
 * WC tested up to: 7.0.1
 *
 * Copyright (c) 2022 Krokedil
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DINTERO_CHECKOUT_VERSION', '1.3.2' );
define( 'DINTERO_CHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
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
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var Dintero $instance
		 */
		private static $instance;

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
		}

		/**
		 * Initialize the payment gateway.
		 *
		 * @return void
		 */
		public function init() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

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
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-order-status.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/class-dintero-checkout-meta-box.php';

			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request-get.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request-post.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/class-dintero-checkout-request-put.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-get-access-token.php';

			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/helpers/class-dintero-checkout-helper-base.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/helpers/class-dintero-checkout-cart.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/helpers/class-dintero-checkout-order.php';

			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/get/class-dintero-checkout-get-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/get/class-dintero-checkout-get-session.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-create-session.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-cancel-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-capture-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/post/class-dintero-checkout-refund-order.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/put/class-dintero-checkout-update-checkout-session.php';
			include_once DINTERO_CHECKOUT_PATH . '/classes/requests/put/class-dintero-checkout-update-transaction.php';
			include_once DINTERO_CHECKOUT_PATH . '/includes/dintero-checkout-functions.php';

			$this->api              = new Dintero_Checkout_API();
			$this->order_management = Dintero_Checkout_Order_Management::get_instance();

			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
			load_plugin_textdomain( 'dintero-checkout-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );

			add_action(
				'widgets_init',
				function() {
					register_widget( 'Dintero_Checkout_Widget' );
				}
			);
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
function Dintero() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return Dintero::get_instance();
}
