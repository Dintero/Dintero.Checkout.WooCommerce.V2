<?php
/**
 * The Dintero Checkout Express form to be embedded (Dintero Checkout Express → Embedded).
 *
 * @package Dintero_Checkout/Templates
 */

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

/* This hook is used for showing WC Gift Cards form. */
do_action( 'woocommerce_review_order_before_payment' );

/* This hook is used for YITH WooCommerce Gift Cards form. */
do_action( 'woocommerce_before_checkout_form', WC()->checkout() );

?>
<form name="checkout" class="checkout woocommerce-checkout">
<?php do_action( 'dintero_express_before_wrapper' ); ?>
	<div id="dintero-express-wrapper">
		<div id="dintero-checkout-order-review">
			<?php do_action( 'dintero_express_before_order_review' ); ?>
			<?php do_action( 'dintero_express_order_review' ); ?>
			<?php do_action( 'dintero_express_after_order_review' ); ?>
		</div>
		<div aria-hidden="true" id="dintero-express-form" style="position:absolute; top:-99999px; left:-99999px;">
			<?php do_action( 'dintero_express_form' ); ?>
		</div>
		<div>
		<?php do_action( 'dintero_express_before_snippet' ); ?>
		<?php do_action( 'dintero_iframe' ); ?>
		<?php do_action( 'dintero_express_after_snippet' ); ?>
		</div>
	</div>
	<?php do_action( 'dintero_express_after_wrapper' ); ?>
</form>

<?php do_action( 'woocommerce_after_checkout_form', WC()->checkout() ); ?>
