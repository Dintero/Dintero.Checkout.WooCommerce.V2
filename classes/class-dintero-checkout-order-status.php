<?php
/**
 * This file is used for creating a new custom order status.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dintero_Checkout_Order_Status
 */
class Dintero_Checkout_Order_Status {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		/* Register the custom status. */
		add_filter(
			'wc_order_statuses',
			function( $order_statuses ) {
				array_splice( $order_statuses, 1, 0, array( 'wc-manual-review' => _x( 'Manual review', 'Order status', 'dintero-checkout-for-woocommerce' ) ) );
				return $order_statuses;
			}
		);

		/* Used in the order notes amongst others for naming the custom order status. */
		add_filter(
			'woocommerce_register_shop_order_post_statuses',
			function( $post_statuses ) {
				$post_statuses['wc-manual-review'] = array(
					'label'                     => _x( 'Manual review', 'Order status', 'dintero-checkout-for-woocommerce' ),
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop( 'Manual review <span class="count">(%s)</span>', 'Manual review <span class="count">(%s)</span>', 'dintero-checkout-for-woocommerce' ),

				);

				return $post_statuses;
			}
		);

		/* The status "Manual review" is a valid payment complete status. */
		add_filter(
			'woocommerce_valid_order_statuses_for_payment_complete',
			function( $order_statuses ) {
				array_push( $order_statuses, 'manual-review' );
				return $order_statuses;
			}
		);

		/* Send the generic status "On hold" mail to customer. */
		add_action(
			'woocommerce_order_status_manual-review',
			function( $order_id ) {
				WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order']->trigger( $order_id );
			}
		);

		/* Let the merchant modify the total amount for partial capture. */
		add_filter(
			'wc_order_is_editable',
			function( $is_editable, $order ) {
				return ( 'manual-review' === $order->get_status() ) ? true : $is_editable;
			},
			10,
			2
		);

		/* Update used coupon amount for each coupon within an order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_update_coupon_usage_counts' );

		/* Update total sales amount for each product within a paid order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_update_total_sales_counts' );

		/* When a payment is complete, we can reduce stock levels for items within an order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_maybe_reduce_stock_levels' );

		/* Release held stock for an order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_release_stock_for_order', 11 );
	}

} new Dintero_Checkout_Order_Status();
