<?php
/**
 * The Dintero Checkout Express form to be embedded.
 *
 * @package Dintero_Checkout/Templates
 */

do_action( 'woocommerce_before_checkout_form', WC()->checkout() );

?>

<form name="checkout" class="checkout woocommerce-checkout">
	<?php do_action( 'dintero_checkout_wc_before_wrapper' ); ?>
	<div id="dintero-checkout-wrapper">
		<div id="dintero-checkout-order-review">
			<?php do_action( 'dintero_checkout_wc_before_order_review' ); ?>
			<?php woocommerce_order_review(); ?>
			<?php do_action( 'dintero_checkout_wc_after_order_review' ); ?>
		</div>
		<div id="dintero-checkout-iframe-wrapper">
			<?php do_action( 'dintero_checkout_wc_before_snippet' ); ?>
			<div id='dintero-checkout-iframe'></div>
			<?php do_action( 'dintero_checkout_wc_after_snippet' ); ?>
		</div>
	</div>
	<?php do_action( 'dintero_checkout_wc_after_wrapper' ); ?>
</form>
<?php do_action( 'dintero_checkout_wc_after_checkout_form' ); ?>
