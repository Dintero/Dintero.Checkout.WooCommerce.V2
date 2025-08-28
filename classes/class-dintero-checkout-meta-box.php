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

	/**
	 * Class constructor.
	 */
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
		if ( in_array( $post_type, array( 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			$order_id = dintero_get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( 'dintero_checkout' === $order->get_payment_method() ) {
				add_meta_box( 'dintero_checkout_meta_box', __( 'Dintero Checkout', 'dintero-checkout-for-woocommerce' ), array( $this, 'dintero_meta_box_content' ), $post_type, 'side', 'core' );
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
		$order = wc_get_order( dintero_get_the_ID() );

		if ( ! empty( $order->get_transaction_id() ) ) {

			$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

			if ( is_wp_error( $dintero_order ) ) {
				$this->print_error_content( __( 'Failed to retrieve the order from Dintero.', 'dintero-checkout-for-woocommerce' ) );
				return;
			}

			$this->print_content( $dintero_order );

			if ( empty( $order->get_date_paid() ) && ! in_array( $order->get_status(), array( 'on-hold' ), true ) ) {
				$this->print_error_content( __( 'The order has not been authorized by Dintero.', 'dintero-checkout-for-woocommerce' ) );
			}
		}
	}


	/**
	 * Prints the content of the meta box.
	 *
	 * @param array $dintero_order The Dintero order.
	 * @return void
	 */
	public function print_content( $dintero_order ) {
		$order       = wc_get_order( dintero_get_the_ID() );
		$environment = ! empty( $order->get_meta( '_wc_dintero_checkout_environment' ) ) ? $order->get_meta( '_wc_dintero_checkout_environment' ) : '';

		$account_id     = trim( get_option( 'woocommerce_dintero_checkout_settings', array( 'account_id' => '' ) )['account_id'] );
		$transaction_id = $order->get_meta( '_dintero_transaction_id' );

		$payment_method = $order->get_meta( '_dintero_payment_method' );
		if ( empty( $payment_method ) ) {
			$payment_method = dintero_get_payment_method_name( $dintero_order['payment_product_type'] );
			$order->update_meta_data( '_dintero_payment_method', $payment_method );
			$order->save();
		}

		?>
		<div class='dintero-checkout-meta-box-content'>
		<strong><?php esc_html_e( 'Dintero order status: ', 'dintero-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( ucfirst( strtolower( str_replace( '_', ' ', $dintero_order['status'] ) ) ) ); ?><br/>
		<strong><?php esc_html_e( 'Payment method: ', 'dintero-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $payment_method ); ?><br/>
		<?php if ( ! empty( $environment ) ) : ?>
		<strong><?php esc_html_e( 'Environment: ', 'dintero-checkout-for-woocommerce' ); ?> </strong><?php echo esc_html( $environment ); ?><br/>
		<?php endif ?>
		<?php
		if ( ! empty( $account_id ) && ! empty( $transaction_id ) ) {
			$env = 'test' === strtolower( $environment ) ? 'T' : 'P';
			$url = esc_url( "https://backoffice.dintero.com/{$env}{$account_id}/payments/transactions/{$transaction_id}" );
			echo "<p><a href='" . esc_url( $url ) . "' target='_blank'>View transaction details</a></p>";
		}
		?>
		</div>
		<?php
	}

	/**
	 * Prints the content of the meta box containing only the specified error message.
	 *
	 * @param string $error_message Error message.
	 * @return void
	 */
	public function print_error_content( $error_message ) {
		?>
		<div class="dintero-checkout-meta-box-content">
			<p><?php echo esc_html( $error_message ); ?></p>
		</div>
		<?php
	}
}
new Dintero_Checkout_Meta_Box();
