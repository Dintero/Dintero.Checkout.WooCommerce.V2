jQuery( function( $ ) {
	/* Check if WP has added our localized parameters (refer to class-dintero-checkout-assets.php.) */
	if ( typeof dinteroCheckoutParams === undefined ) {
		return;
	}

	const dinteroCheckoutForWooCommerce = {
		bodyEl: $( 'body' ),
		checkoutFormSelector: 'form.checkout',
		preventPaymentMethodChange: false,
		selectAnotherSelector: '#dintero-checkout-select-other',
		paymentMethodEl: $( 'input[name="payment_method"]' ),
		checkout: null,
		validation: false,
		isLocked: false,

		init() {
			$( document ).ready( dinteroCheckoutForWooCommerce.documentReady );
			dinteroCheckoutForWooCommerce.bodyEl.on( 'change', 'input[name="payment_method"]', dinteroCheckoutForWooCommerce.maybeChangeToDinteroCheckout );
			dinteroCheckoutForWooCommerce.bodyEl.on( 'click', dinteroCheckoutForWooCommerce.selectAnotherSelector, dinteroCheckoutForWooCommerce.changeFromDinteroCheckout );

			dinteroCheckoutForWooCommerce.renderIframe();

			/* These are _WC_ events we attach onto. */
			dinteroCheckoutForWooCommerce.bodyEl.on( 'update_checkout', dinteroCheckoutForWooCommerce.updateCheckout );
			dinteroCheckoutForWooCommerce.bodyEl.on( 'updated_checkout', dinteroCheckoutForWooCommerce.updatedCheckout );
		},

		updateCheckout() {
			if ( dinteroCheckoutForWooCommerce.checkout !== null && ! dinteroCheckoutForWooCommerce.validation ) {
				if ( dinteroCheckoutForWooCommerce.isLocked ) {
					/* If the dintero_locked is present, we'll issue an update request to Dintero. WC takes care of submitting the form through AJAX. */
					$( dinteroCheckoutForWooCommerce.checkoutFormSelector ).append( '<input type="hidden" name="dintero_locked" id="dintero_locked" value=1>' );
				} else {
					dinteroCheckoutForWooCommerce.checkout.lockSession();
				}
			}
		},

		updatedCheckout() {
			if ( dinteroCheckoutForWooCommerce.checkout !== null && ! dinteroCheckoutForWooCommerce.validation ) {
				if ( dinteroCheckoutForWooCommerce.isLocked ) {
					$( '#dintero_locked' ).remove();
					dinteroCheckoutForWooCommerce.isLocked = false;
					dinteroCheckoutForWooCommerce.checkout.refreshSession();
				}
			}
		},

		/**
		 * Render the iframe and register callback functionality.
		 */
		async renderIframe() {
			const container = $( '#dintero-checkout-iframe' )[ 0 ];

			dintero.embed( {
				container,
				sid: dinteroCheckoutParams.SID,
				onSession( event, checkout ) {
					// Check for address changes and update shipping.
					dinteroCheckoutForWooCommerce.updateAddress( event.session.order.billing_address, event.session.order.shipping_address );
				},
				onPayment( event, checkout ) {
					window.location = event.href;
				},
				onPaymentError( event, checkout ) {
					$( dinteroCheckoutForWooCommerce.checkoutFormSelector ).unblock();
				},
				onSessionCancel( event, checkout ) {
					checkout.destroy();

					$.ajax( {
						type: 'POST',
						dataType: 'json',
						data: {
							nonce: dinteroCheckoutParams.unset_session_nonce,
						},
						url: dinteroCheckoutParams.unset_session_url,
						complete( ) {
							window.location.replace( event.href );
						},
					} );
				},
				onSessionLocked( event, checkout, callback ) {
					dinteroCheckoutForWooCommerce.isLocked = true;

					/* A checkout update happened, but the checkout was not locked. The checkout is now locked: */
					$( document.body ).trigger( 'update_checkout' );
				},
				onSessionLockFailed( event, checkout, ) {
					console.warn( 'Failed to lock the checkout.', event );
				},
				onActivePaymentType( event, checkout ) {
					// Unused.
				},
				onValidateSession( event, checkout, callback ) {
					$( '#dintero-checkout-wc-form' ).block( {
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6,
						},
					} );
					dinteroCheckoutForWooCommerce.validation = true;
					dinteroCheckoutForWooCommerce.updateAddress( event.session.order.billing_address, event.session.order.shipping_address );
					if ( 0 < $( 'form.checkout #terms' ).length ) {
						$( 'form.checkout #terms' ).prop( 'checked', true );
					}
					dinteroCheckoutForWooCommerce.submitOrder( callback );
					dinteroCheckoutForWooCommerce.validation = false;
				},
			} ).then( function( checkout ) {
				dinteroCheckoutForWooCommerce.checkout = checkout;
			} );
		},

		/**
		 * Triggers on document ready.
		 */
		documentReady() {
			if ( 0 < $( 'input[name="payment_method"]' ).length ) {
				dinteroCheckoutForWooCommerce.paymentMethod = $( 'input[name="payment_method"]' ).filter( ':checked' ).val();
			} else {
				dinteroCheckoutForWooCommerce.paymentMethod = 'dintero_checkout';
			}

			if ( ! dinteroCheckoutParams.payForOrder && dinteroCheckoutForWooCommerce.paymentMethod === 'dintero_checkout' ) {
				dinteroCheckoutForWooCommerce.moveExtraCheckoutFields();
			}

			$( 'form.checkout' ).trigger( 'update_checkout' );
		},

		/**
		 * When the customer changes from Dintero Checkout to other payment methods.
		 *
		 * @param {Event} e
		 */
		changeFromDinteroCheckout( e ) {
			e.preventDefault();
			$( dinteroCheckoutForWooCommerce.checkoutFormSelector ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );

			$.ajax( {
				type: 'POST',
				dataType: 'json',
				data: {
					dintero_checkout: false,
					nonce: dinteroCheckoutParams.change_payment_method_nonce,
				},
				url: dinteroCheckoutParams.change_payment_method_url,
				complete( data ) {
					window.location.href = data.responseJSON.data.redirect;
				},
			} );
		},
		/**
		 * When the customer changes to Dintero Checkout from other payment methods.
		 */
		maybeChangeToDinteroCheckout() {
			if ( ! dinteroCheckoutForWooCommerce.preventPaymentMethodChange ) {
				if ( 'dintero_checkout' === $( this ).val() ) {
					$( '.woocommerce-info' ).remove();
					$( dinteroCheckoutForWooCommerce.checkoutFormSelector ).block( {
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6,
						},
					} );
					$.ajax( {
						type: 'POST',
						data: {
							dintero_checkout: true,
							nonce: dinteroCheckoutParams.change_payment_method_nonce,
						},
						dataType: 'json',
						url: dinteroCheckoutParams.change_payment_method_url,
						complete( data ) {
							window.location.href = data.responseJSON.data.redirect;
						},
					} );
				}
			}
		},
		/*
		 * Check if Dintero Checkout is the selected gateway.
		 */
		checkIfDinteroCheckoutSelected() {
			if ( dinteroCheckoutForWooCommerce.paymentMethodEl.length > 0 ) {
				dinteroCheckoutForWooCommerce.paymentMethod = dinteroCheckoutForWooCommerce.paymentMethodEl.filter( ':checked' ).val();
				if ( 'dintero_checkout' === dinteroCheckoutForWooCommerce.paymentMethod ) {
					return true;
				}
			}
			return false;
		},
		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields() {
			// Move order comments.
			$( '.woocommerce-additional-fields' ).appendTo( '#dintero-checkout-extra-checkout-fields' );

			const form = $( 'form[name="checkout"] input, form[name="checkout"] select, textarea' );
			for ( let i = 0; i < form.length; i++ ) {
				const name = form[ i ].name;
				// Check if field is inside the order review.
				if ( $( 'table.woocommerce-checkout-review-order-table' ).find( form[ i ] ).length ) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, dinteroCheckoutParams.standardWooCheckoutFields ) ) {
					// This is not a standard Woo field, move to our div.
					if ( 0 < $( 'p#' + name + '_field' ).length ) {
						$( 'p#' + name + '_field' ).appendTo( '#dintero-checkout-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#dintero-checkout-extra-checkout-fields' );
					}
				}
			}
		},

		/* Maybe update the shipping and billing address. */
		updateAddress( billingAddress, shippingAddress ) {
			let update = false;

			if ( billingAddress ) {
				if ( 'first_name' in billingAddress ) {
					$( '#billing_first_name' ).val( billingAddress.first_name );
				}

				if ( 'last_name' in billingAddress ) {
					$( '#billing_last_name' ).val( billingAddress.last_name );
				}

				if ( 'address_line' in billingAddress ) {
					$( '#billing_address_1' ).val( billingAddress.address_line );
				}

				if ( 'postal_code' in billingAddress ) {
					$( '#billing_postcode' ).val( billingAddress.postal_code );
				}

				if ( 'postal_place' in billingAddress ) {
					$( '#billing_city' ).val( billingAddress.postal_place );
				}

				if ( 'country' in billingAddress ) {
					$( '#billing_country' ).val( billingAddress.country );
				}

				if ( 'email' in billingAddress ) {
					$( '#billing_email' ).val( billingAddress.email );
				}

				if ( 'phone_number' in billingAddress ) {
					$( '#billing_phone' ).val( billingAddress.phone_number );
				}

				update = true;
			}

			if ( shippingAddress ) {
				$( '#ship-to-different-address-checkbox' ).prop( 'checked', true );

				if ( 'first_name' in shippingAddress ) {
					$( '#shipping_first_name' ).val( shippingAddress.first_name );
				}

				if ( 'last_name' in shippingAddress ) {
					$( '#shipping_last_name' ).val( shippingAddress.last_name );
				}

				if ( 'address_line' in shippingAddress ) {
					$( '#shipping_address_1' ).val( shippingAddress.address_line );
				}

				if ( 'postal_code' in shippingAddress ) {
					$( '#shipping_postcode' ).val( shippingAddress.postal_code );
				}

				if ( 'postal_place' in shippingAddress ) {
					$( '#shipping_city' ).val( shippingAddress.postal_place );
				}

				if ( 'country' in shippingAddress ) {
					$( '#shipping_country' ).val( shippingAddress.country );
				}

				update = true;
			}

			// Trigger changes
			if ( update && dinteroCheckoutForWooCommerce.validation !== true ) {
				$( '#billing_email' ).change();
				$( '#billing_email' ).blur();
				$( 'form.checkout' ).trigger( 'update_checkout' );
			}
		},

		getDinteroCheckoutOrder( _data, callback ) {
			$.ajax( {
				type: 'POST',
				data: {
					nonce: dinteroCheckoutParams.get_order_nonce,
				},
				dataType: 'json',
				url: dinteroCheckoutParams.get_order_url,
				complete( data ) {
					dinteroCheckoutForWooCommerce.setAddressData( data.responseJSON.data, callback );
					console.log( 'getdinteroCheckoutOrder completed' );
				},
			} );
		},

		/**
		 * Submit the order using the WooCommerce AJAX function.
		 *
		 * @param {callback} callback
		 */
		submitOrder( callback ) {
			$( '.woocommerce-checkout-review-order-table' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
			$.ajax( {
				type: 'POST',
				url: dinteroCheckoutParams.submitOrder,
				data: $( 'form.checkout' ).serialize(),
				dataType: 'json',
				success( data ) {
					console.log( data );
					try {
						console.log( 'try' );
						if ( 'success' === data.result ) {
							console.log( 'submit order success', data );
							callback( { success: true } );
						} else {
							throw 'Result failed';
						}
					} catch ( err ) {
						console.log( 'catch error' );
						console.error( err );
						if ( data.messages ) {
							// Strip HTML code from messages.
							const messages = data.messages.replace( /<\/?[^>]+(>|$)/g, '' );
							console.log( 'error ', messages );
							dinteroCheckoutForWooCommerce.logToFile( 'Checkout error | ' + messages );
							dinteroCheckoutForWooCommerce.failOrder( 'submission', messages, callback );
						} else {
							dinteroCheckoutForWooCommerce.logToFile( 'Checkout error | No message' );
							dinteroCheckoutForWooCommerce.failOrder( 'submission', 'Checkout error', callback );
						}
					}
				},
				error( data ) {
					console.log( 'error data', data );
					console.log( 'error data response text', data.responseText );
					try {
						dinteroCheckoutForWooCommerce.logToFile( 'AJAX error | ' + JSON.stringify( data ) );
					} catch ( e ) {
						dinteroCheckoutForWooCommerce.logToFile( 'AJAX error | Failed to parse error message.' );
					}
					dinteroCheckoutForWooCommerce.failOrder( 'ajax-error', 'Internal Server Error', callback );
				},
			} );
		},

		failOrder( event, errorMessage, callback ) {
			console.log( 'fail order' );
			callback( { success: false, clientValidationError: errorMessage } );

			// Renable the form.
			$( 'body' ).trigger( 'updated_checkout' );
			$( dinteroCheckoutForWooCommerce.checkoutFormSelector ).removeClass( 'processing' );
			$( dinteroCheckoutForWooCommerce.checkoutFormSelector ).unblock();
			$( '.woocommerce-checkout-review-order-table' ).unblock();
		},

		/**
		 * Logs the message to the klarna checkout log in WooCommerce.
		 *
		 * @param {string} message
		 */
		logToFile( message ) {
			$.ajax(
				{
					url: dinteroCheckoutParams.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message,
						nonce: dinteroCheckoutParams.log_to_file_nonce,
					},
				}
			);
		},
	};

	dinteroCheckoutForWooCommerce.init();
} );
