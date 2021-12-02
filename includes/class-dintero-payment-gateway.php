<?php
/**
 * Dintero Payment Gateway Class Doc Comment
 *
 * @category Dintero_Payment_Gateway
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Payment Gateway
 */
class Dintero_Payment_Gateway extends WC_Payment_Gateway {
	/*
	 * Method code
	 */
	const METHOD_CODE = 'dintero';

	/**
	 * Payment processor
	 *
	 * @var Dintero_Payment_Processor $payment_processor
	 */
	protected $payment_processor;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = Dintero::instance()->get_app_id();
		$this->has_fields         = false;
		$this->method_title       = __( 'Dintero' );
		$this->method_description = __( 'Dintero Checkout embedded or redirected' );
		$this->init_form_fields();
		$this->init_settings();
		$this->supports = array(
			'products',
			'refunds',
		);

		do_action( 'dintero_payment_gateway_init_after', $this );

		// This action hook saves the settings.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		// initializing payment processor.
		$this->payment_processor = new Dintero_Payment_Processor( $this );
	}

	/**
	 * Plugin options.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                 => array(
				'title'       => __( 'Enable/Disable' ),
				'label'       => __( 'Enable Dintero Checkout V2 Page Gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                   => array(
				'title'       => __( 'Title' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.' ),
				'default'     => __( 'Dintero' ),
				'desc_tip'    => true,
			),
			'description'             => array(
				'title'       => __( 'Description' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.' ),
				'default'     => __( 'Pay through Dintero gateway.' ),
				'desc_tip'    => true,
			),
			'test_mode'               => array(
				'title'       => __( 'Test mode' ),
				'label'       => __( 'Enable Test Mode' ),
				'type'        => 'checkbox',
				'description' => __( 'Put the payment gateway in test mode using client test credentials.' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'client' 		  		  => array(
				'title' => 'API Keys',
				'type'  => 'Title'
			),
			'account_id'              => array(
				'title'       => __( 'Account ID' ),
				'type'        => 'text',
				'description' => __( 'Found under (SETTINGS >> Account) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_id'               => array(
				'title'       => __( 'Client ID' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_secret'           => array(
				'title'       => __( 'Client Secret' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'profile_id'              => array(
				'title'       => __( 'Payment Profile ID' ),
				'type'        => 'text',
				'description' => __( 'Payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'checkout_settings'       => array(
				'title'       => __( 'Checkout' ),
				'type'        => 'title',
				'description' => __( 'Checkout settings.' ),
			),
			'checkout_logo_width'     => array(
				'title'       => __( 'Dintero Checkout Logo Width (in pixels)' ),
				'type'        => 'number',
				'description' => __( 'The width of Dintero\'s logo on the checkout page in pixels.' ),
				'default'     => 600,
				'desc_tip'    => true,
			),
			'capture_settings'        => array(
				'title'       => __( 'Payment Capture' ),
				'type'        => 'title',
				'description' => __( 'Payment Capture settings.' ),
			),
			'default_order_status'    => array(
				'title'       => __( 'Default Order Status' ),
				'type'        => 'select',
				'options'     => array(
					'wc-processing' => _x( 'Processing', 'Order status' ),
					'wc-on-hold'    => _x( 'On hold', 'Order status' ),
				),
				'default'     => 'wc-processing',
				'description' => __( 'When payment Authorized.' ),
				'desc_tip'    => true,
			),
			'manual_capture_settings' => array(
				'title' => __( 'Capture order when:' ),
				'type'  => 'title',
			),
			'manual_capture_status'   => array(
				'title'       => __( 'Order status is changed to: ' ),
				'type'        => 'select',
				'options'     => wc_get_order_statuses(),
				'default'     => 'wc-completed',
				'description' => __( 'Select a status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true,
			),
			'cancel_refund_settings'  => array(
				'title' => __( 'Cancel or refund order when:' ),
				'type'  => 'title',
			),
			'branding_title'          => array(
				'title'       => __( 'Branding:' ),
				'type'        => 'title',
				'description' => '',
			),
			'branding_enable'         => array(
				'title'       => __( 'Add branding image in footer:' ),
				'label'       => __( 'Check to add dintero image in footer' ),
				'type'        => 'checkbox',
				'description' => __( 'Check to add dintero image in foote' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'branding_footer_url'     => array(
				'title'       => __( 'URL (Footer):' ),
				'type'        => 'text',
				// @codingStandardsIgnoreStart
				'description' => __( 'You can change color & size in Dintero Backoffice. Paste the new URL here:<br />Preview:<div>' . $this->get_icon_footer() . '</div>' ),
				// @codingStandardsIgnoreEnd
				'default'     => '',
				'desc_tip'    => false,
			),
			'branding_checkout_url'   => array(
				'title'       => __( 'URL (In Checkout):' ),
				'type'        => 'text',
				// @codingStandardsIgnoreStart
				'description' => __( 'You can change color & size in Dintero Backoffice. Paste the new URL here:<br />Preview:<div>' . $this->get_icon_checkout() . '</div>' ),
				// @codingStandardsIgnoreEnd
				'default'     => '',
				'desc_tip'    => false,
			),
		);
	}

	/**
	 * Retrieving footer icon
	 *
	 * @return string
	 */
	protected function get_icon_footer() {
		return '';
	}

	/**
	 * Retrieving checkout icon
	 *
	 * @return string
	 */
	protected function get_icon_checkout() {
		return '';
	}

	/**
	 * Processing payment
	 *
	 * @param int $order_id order id.
	 * @return array|string|void
	 */
	public function process_payment( $order_id ) {
		return $this->payment_processor->execute( $order_id );
	}

	/**
	 * Processing refund
	 *
	 * @param int    $order_id order id.
	 * @param null   $amount amount to be refunded.
	 * @param string $reason refund reason.
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->payment_processor->refund( wc_get_order( $order_id ), $amount );
	}
}
