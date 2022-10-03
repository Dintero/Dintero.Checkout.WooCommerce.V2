jQuery( function( $ ) {
	/* Check if WP has added our localized parameters (refer to class-dintero-checkout-assets.php.) */
	if ( ! dinteroCheckoutParams ) {
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
			dinteroCheckoutForWooCommerce.bodyEl.on( 'updated_checkout', dinteroCheckoutForWooCommerce.maybeDisplayShippingPrice );
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
				language: dinteroCheckoutParams.language,
				onSession( event, checkout ) {
					// If the session expires, the order object will be missing.
					if ( event.session === undefined || event.session.order === undefined ) {
						// Refresh the session to display the error message from Dintero. The error itself should be handled by any of other event handlers.
						checkout.refreshSession();
						return;
					}

					// Check for address changes and update shipping.
					dinteroCheckoutForWooCommerce.updateAddress( event.session.order.billing_address, event.session.order.shipping_address );
					if ( event.session.order.shipping_option && dinteroCheckoutParams.shipping_in_iframe ) {
						// @TODO only if shipping in iframe.
						dinteroCheckoutForWooCommerce.shippingMethodChanged( event.session.order.shipping_option );
					}
				},
				onPayment( event, checkout ) {
					window.location = event.href;
				},
				onPaymentError( event, checkout ) {
					checkout.destroy();

					$.ajax( {
						type: 'POST',
						dataType: 'json',
						data: {
							nonce: dinteroCheckoutParams.print_notice_nonce,
							message: 'A payment error was encountered.',
							notice_type: 'error',
						},
						url: dinteroCheckoutParams.print_notice_url,
						complete() {
							dinteroCheckoutForWooCommerce.unsetSession( event.href );
						},
					} );
				},
				onSessionCancel( event, checkout ) {
					checkout.destroy();
					dinteroCheckoutForWooCommerce.unsetSession( event.href );
				},
				onSessionNotFound( event, checkout ) {
					/* Unset the session, and redirect the customer back to the checkout page (the same page). The checkout will automatically be destroyed. */
					dinteroCheckoutForWooCommerce.unsetSession( window.location.pathname );
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

		unsetSession( redirectUrl ) {
			$.ajax( {
				type: 'POST',
				dataType: 'json',
				data: {
					nonce: dinteroCheckoutParams.unset_session_nonce,
				},
				url: dinteroCheckoutParams.unset_session_url,
				complete() {
					window.location.replace( redirectUrl );
				},
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
			$( '.woocommerce-additional-fields' ).appendTo( '#dintero-express-extra-checkout-fields' );

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
						$( 'p#' + name + '_field' ).appendTo( '#dintero-express-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#dintero-express-extra-checkout-fields' );
					}
				}
			}
		},

		/* Maybe update the shipping and billing address. */
		updateAddress( billingAddress, shippingAddress ) {
			let update = false;

			if ( billingAddress ) {
				// Maybe set names if its a b2b purchase.
				if ( billingAddress.co_address ) {
					billingAddress.first_name = billingAddress.first_name || billingAddress.co_address.split( ' ' )[ 0 ] || billingAddress.business_name;
					billingAddress.last_name = billingAddress.last_name || billingAddress.co_address.split( ' ' )[ 1 ] || billingAddress.business_name;
				}

				if ( 'first_name' in billingAddress ) {
					// first_name=shipping_address.first_name || shipping_address.co_address.split(" ")[0] || shipping_address.business_name
					$( '#billing_first_name' ).val( billingAddress.first_name );
				}

				if ( 'last_name' in billingAddress ) {
					// first_name=shipping_address.first_name || shipping_address.co_address.split(" ")[0] || shipping_address.business_name
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
				if ( shippingAddress.co_address ) {
					shippingAddress.first_name = shippingAddress.first_name || shippingAddress.co_address.split( ' ' )[ 0 ] || shippingAddress.business_name;
					shippingAddress.last_name = shippingAddress.last_name || shippingAddress.co_address.split( ' ' )[ 1 ] || shippingAddress.business_name;
				}

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

		shippingMethodChanged( shipping ) {
			$( '#dintero_shipping_data' ).val( JSON.stringify( shipping ) );
			$( 'body' ).trigger( 'dintero_shipping_option_changed', [ shipping ] );
			$( 'body' ).trigger( 'update_checkout' );
		},

		/**
		 * Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		 */
		maybeDisplayShippingPrice() {
			// Check if we already have set the price. If we have, return.
			if ( $( '.dintero-shipping' ).length ) {
				return;
			}
			if ( 'dintero_checkout' === dinteroCheckoutForWooCommerce.paymentMethod && dinteroCheckoutParams.shipping_in_iframe ) {
				if ( $( '#shipping_method input[type=\'radio\']' ).length ) {
					// Multiple shipping options available.
					$( '#shipping_method input[type=\'radio\']:checked' ).each( function() {
						const idVal = $( this ).attr( 'id' );
						const shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
						$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
						$( '.woocommerce-shipping-totals td' ).addClass( 'dintero-shipping' );
					} );
				} else {
					// Only one shipping option available.
					const idVal = $( '#shipping_method input[name=\'shipping_method[0]\']' ).attr( 'id' );
					const shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
					$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
					$( '.woocommerce-shipping-totals td' ).addClass( 'dintero-shipping' );
				}
			}
		},

		/**
		 * Block form fields from being modified by the user.
		 */
		blockForm() {
			/* Order review. */
			$( '.woocommerce-checkout-review-order-table' ).block( {
				message: dinteroCheckoutParams.pip_text,
				overlayCSS: {
					background: '#fff',
				},
				css: {
					width: 'fit-content',
					height: 'fit-content',
					padding: '0.2em 0.8em',
					border: 'none',
				},
				blockMsgClass: 'dintero-checkout-pip',
			} );

			/* Additional checkout fields. */
			$( '#dintero-express-extra-checkout-fields' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
				},
				blockMsgClass: 'dintero-checkout-pip',
			} );

			$( '.dintero-checkout-pip' ).siblings( '.blockOverlay' ).addClass( 'dintero-checkout-no-spinner' );
		},

		/**
		 * Unblock form fields.
		 */
		unblockForm() {
			/* Order review. */
			$( '.woocommerce-checkout-review-order-table' ).unblock();

			/* Additional checkout fields. */
			$( '#dintero-express-extra-checkout-fields' ).unblock();
		},

		/**
		 * Submit the order using the WooCommerce AJAX function.
		 *
		 * @param {callback} callback
		 */
		submitOrder( callback ) {
			this.blockForm();

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
							const messages = data.messages.replace( /<\/?[^>]+(>|$)\s+/g, '' );
							dinteroCheckoutForWooCommerce.printNotice( messages );
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
			this.unblockForm();
		},

		printNotice( message, noticeType = 'error' ) {
			/* There are two wrappers for some reason hence the first() to prevent duplicate notices. */
			$( '.woocommerce-notices-wrapper' ).first().append( `<div class='woocommerce-${ noticeType }' role='alert'>${ message }</div>` );
			if ( 'error' === noticeType ) {
				$( document.body ).trigger( 'checkout_error', [ message ] );
			}
			$( 'html, body' ).animate( {
				scrollTop: ( $( '.woocommerce-notices-wrapper' ).offset().top - 100 ),
			}, 1000 );
		},

		/**
		 * Logs the message to the Dintero Checkout log in WooCommerce.
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
