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

		/* Send the generic status "On hold" mail to customer. */
		add_action( 'woocommerce_order_status_changed', array( $this, 'send_email_notification' ), 10, 3 );

		/* Register the custom status. */
		add_filter( 'wc_order_statuses', array( $this, 'register_custom_wc_status' ) );

		/* Used in the order notes amongst others for naming the custom order status. */
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_custom_post_status' ) );

		/* The status "Manual review" is a valid payment complete status. */
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'register_on_payment_complete' ) );

		/* Let the merchant modify the total amount for partial capture. */
		add_filter( 'wc_order_is_editable', array( $this, 'register_as_editable_status' ), 10, 2 );

		/* Update used coupon amount for each coupon within an order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_update_coupon_usage_counts' );

		/* Update total sales amount for each product within a paid order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_update_total_sales_counts' );

		/* When a payment is complete, we can reduce stock levels for items within an order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_maybe_reduce_stock_levels' );

		/* Release held stock for an order. */
		add_action( 'woocommerce_order_status_manual-review', 'wc_release_stock_for_order', 11 );
	}

	/**
	 * Registers the custom order status.
	 *
	 * @param array $order_statuses
	 * @return array
	 */
	public function register_custom_wc_status( $order_statuses ) {
		$order_statuses['wc-manual-review'] = _x( 'Manual review', 'Order status', 'dintero-checkout-for-woocommerce' );
		return $order_statuses;
	}

	/**
	 * Registers the custom order status as a valid post entry.
	 *
	 * @param array $post_statuses
	 * @return array
	 */
	public function register_custom_post_status( $post_statuses ) {
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

	/**
	 * Registers the custom order status as a valid status for payment_processing.
	 *
	 * @param array $order_statuses
	 * @return array
	 */
	public function register_on_payment_complete( $order_statuses ) {
		array_push( $order_statuses, 'manual-review' );
		return $order_statuses;
	}

	/**
	 * Permit an order with the order status "Manual review" to be editable in the order page.
	 *
	 * @param bool     $is_editable
	 * @param WC_Order $order
	 * @return bool
	 */
	public function register_as_editable_status( $is_editable, $order ) {
		return ( 'manual-review' === $order->get_status() ) ? true : $is_editable;
	}


	/**
	 * Send the correct email to the customer when the order status is changed.
	 *
	 * @param int    $order_id The order id.
	 * @param string $old_status The old order status.
	 * @param string $new_status The new order status.
	 */
	public function send_email_notification( $order_id, $old_status, $new_status ) {
		if ( 'manual-review' === $new_status ) {
			if ( WC()->mailer->emails['WC_Email_Customer_On_Hold_Order']->is_enabled() ?? false ) {
				WC()->mailer->emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id );
			}
		}

		if ( 'manual-review' === $old_status ) {
			switch ( $new_status ) {
				case 'processing':
					if ( WC()->mailer->emails['WC_Email_Customer_Processing_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );
					}
					break;
				case 'completed':
					if ( WC()->mailer->emails['WC_Email_Customer_Completed_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Customer_Completed_Order']->trigger( $order_id );
					}
					break;
				case 'cancelled':
					if ( WC()->mailer->emails['WC_Email_Cancelled_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Cancelled_Order']->trigger( $order_id );
					}
					break;
				case 'refunded':
					if ( WC()->mailer->emails['WC_Email_Customer_Refunded_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Customer_Refunded_Order']->trigger( $order_id );
					}
					break;
				case 'failed':
					if ( WC()->mailer->emails['WC_Email_Failed_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Failed_Order']->trigger( $order_id );
					}
					break;
				case 'on-hold':
					if ( WC()->mailer->emails['WC_Email_Customer_On_Hold_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id );
					}
					break;
				case 'pending':
					if ( WC()->mailer->emails['WC_Email_Customer_Pending_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Customer_Pending_Order']->trigger( $order_id );
					}
					break;
				case 'pending-payment':
					if ( WC()->mailer->emails['WC_Email_Customer_Pending_Order']->is_enabled() ?? false ) {
						WC()->mailer->emails['WC_Email_Customer_Pending_Order']->trigger( $order_id );
					}
					break;
				default:
					break;
			}
		}
	}
}
new Dintero_Checkout_Order_Status();
