<?php
/**
 * The file is used for adding the meta-box functionality.
 *
 * A meta-box appears on the order page, and displays information about the Dintero order that corresponds to WooCommerce order. This must be enabled in the "Screen options" on the order page.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dintero_Meta_Box
 */
class Dintero_Checkout_Meta_Box {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'dintero_checkout_meta_box' ) );
	}

	/**
	 * Makes the meta box available, and ready for display for Dintero Checkout orders only.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function dintero_checkout_meta_box( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			$order_id = get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( 'dintero_checkout' === $order->get_payment_method() ) {
				add_meta_box( 'dintero_checkout_meta_box', __( 'Dintero Checkout', 'dintero-checkout-for-woocommerce' ), array( $this, 'dintero_meta_box_content' ), 'shop_order', 'side', 'core' );
			}
		}
	}

	/**
	 * Determines whether the Dintero order data could be retrieved, and displays the contents of the meta box based on this.
	 *
	 * This is printed as is, and must therefore be properly escaped.
	 *
	 * @return void
	 */
	public function dintero_meta_box_content() {
		$order_id = get_the_ID();
		$order    = wc_get_order( $order_id );

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) && ! in_array( $order->get_status(), array( 'on-hold' ), true ) ) {
			$this->print_error_content( __( 'The order has not been authorized by Dintero.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( ! empty( get_post_meta( $order_id, '_transaction_id', true ) ) ) {

			$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

			if ( is_wp_error( $dintero_order ) ) {
				$this->print_error_content( __( 'Failed to retrieve the order from Dintero.', 'dintero-checkout-for-woocommerce' ) );
				return;
			}

			$this->print_content( $dintero_order );
		}
	}


	/**
	 * Prints the content of the meta box.
	 *
	 * @param array $dintero_order The Dintero order.
	 * @return void
	 */
	public function print_content( $dintero_order ) {
		$order_id    = get_the_ID();
		$environment = ! empty( get_post_meta( $order_id, '_wc_dintero_checkout_environment', true ) ) ? get_post_meta( $order_id, '_wc_dintero_checkout_environment', true ) : '';

		/* Remove duplicate words from the payment method type (e.g., swish.swish → Swish). Otherwise, prints as is (e.g., collector.invoice → Collector Invoice). */
		$payment_method = implode( ' ', array_unique( explode( ' ', ucwords( str_replace( '.', ' ', $dintero_order['payment_product_type'] ) ) ) ) );

		?>
		<div class='dintero-checkout-meta-box-content'>
		<strong><?php esc_html_e( 'Dintero order status: ', 'dintero-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $dintero_order['status'] ); ?><br/>
		<strong><?php esc_html_e( 'Payment method: ', 'dintero-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $payment_method ); ?><br/>
		<?php if ( ! empty( $environment ) ) : ?>
		<strong><?php esc_html_e( 'Environment: ', 'dintero-checkout-for-woocommerce' ); ?> </strong><?php echo esc_html( $environment ); ?><br/>
		<?php endif ?>
		</div>
		<?php
	}

	/**
	 * Prints the content of the meta box containing only the specified error message.
	 *
	 * @param string $error_message
	 * @return void
	 */
	public function print_error_content( $error_message ) {
		?>
		<div class="dintero-checkout-meta-box-content">
			<p><?php echo esc_html( $error_message ); ?></p>
		</div>
		<?php
	}
} new Dintero_Checkout_Meta_Box();
