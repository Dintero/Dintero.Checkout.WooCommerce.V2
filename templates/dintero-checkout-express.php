<?php
/**
 * The Dintero Checkout Express form to be embedded.
 *
 * @package Dintero_Checkout/Templates
 */

/* This hook is used for showing WC Gift Cards form. */
do_action( 'woocommerce_review_order_before_payment' );

/* This hook is used for YITH WooCommerce Gift Cards form. */
do_action( 'woocommerce_before_checkout_form', WC()->checkout() );

?>
<form name="checkout" class="checkout woocommerce-checkout">
	<div id="dintero-express-wrapper">
		<div id="dintero-checkout-order-review">
			<?php do_action( 'dintero_express_order_review' ); ?>
		</div>
		<div aria-hidden="true" id="dintero-express-form" style="position:absolute; top:-99999px; left:-99999px;">
			<?php do_action( 'dintero_express_form' ); ?>
		</div>
		<div>
		<?php do_action( 'dintero_iframe' ); ?>
		</div>
	</div>
</form>
