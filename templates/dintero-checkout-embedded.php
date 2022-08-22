<?php
/**
 * The Dintero Checkout Express form to be embedded (Dintero Checkout → Embedded).
 *
 * @package Dintero_Checkout/Templates
 */

?>
<div class="form-row place-order">
<?php
wc_get_template( 'checkout/terms.php' );
do_action( 'woocommerce_review_order_before_submit' );
do_action( 'woocommerce_review_order_after_submit' );
wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
?>
<input id="payment_method_dintero_checkout" type="radio" class="input-radio" name="payment_method" value="dintero_checkout" checked="checked" />
</div>
<?php

if ( ! wp_doing_ajax() ) {
	do_action( 'dintero_iframe' );
}
