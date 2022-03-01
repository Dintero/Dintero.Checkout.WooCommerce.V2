jQuery(function ($) {
	/* Check if WP has added our localized parameters (refer to class-dintero-checkout-assets.php.) */
	if (typeof dinteroCheckoutParams === undefined) {
		return;
	}

	var dinteroCheckoutForWooCommerce = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',
		preventPaymentMethodChange: false,
		selectAnotherSelector: '#dintero-checkout-select-other',
		paymentMethodEl: $('input[name="payment_method"]'),
		checkout: null,

		init: function () {
			$(document).ready(dinteroCheckoutForWooCommerce.documentReady);
			dinteroCheckoutForWooCommerce.bodyEl.on('change', 'input[name="payment_method"]', dinteroCheckoutForWooCommerce.maybeChangeToDinteroCheckout);
			dinteroCheckoutForWooCommerce.bodyEl.on('click', dinteroCheckoutForWooCommerce.selectAnotherSelector, dinteroCheckoutForWooCommerce.changeFromDinteroCheckout);

			dinteroCheckoutForWooCommerce.renderIframe();

			dinteroCheckoutForWooCommerce.bodyEl.on('update_checkout', dinteroCheckoutForWooCommerce.updateCheckout);
			dinteroCheckoutForWooCommerce.bodyEl.on('updated_checkout', dinteroCheckoutForWooCommerce.updatedCheckout);
		},

		updateCheckout: function() {
			console.log('update_checkout');
			if(dinteroCheckoutForWooCommerce.checkout !== null) {
				console.log("locking session");
				console.log(dinteroCheckoutForWooCommerce.checkout);
				dinteroCheckoutForWooCommerce.checkout.lockSession();
			}
		},

		updatedCheckout: function() {
			console.log('updated_checkout');
			if(dinteroCheckoutForWooCommerce.checkout !== null) {
				console.log("refreshing session");
				dinteroCheckoutForWooCommerce.checkout.refreshSession();
			}
		},

		/**
		 * Render the iframe and register callback functionality.
		 */
		renderIframe: async function() {
			const container = $('#dintero-checkout-iframe')[0];

			dintero.embed({
				container,
				sid: dinteroCheckoutParams.SID,
				onSession: function(event, checkout) {
					// Check for address changes and update shipping.
					console.log(event);
					dinteroCheckoutForWooCommerce.updateAddress(event.session.order.billing_address, event.session.order.shipping_address);
				},
				onPayment: function(event, checkout) {
					// Unused.
				},
				onPaymentError: function(event, checkout) {
					// Unused.
				},
				onSessionCancel: function(event, checkout) {
					// Unused.
				},
				onSessionLocked: function(event, checkout, callback) {
					// Unused.
					console.log('session locked');
				},
				onSessionLockFailed: function(event, checkout) {
					// Unused.
					console.log('session lock failed');
				},
				onActivePaymentType: function(event, checkout) {
					// Unused.
				},
				onValidateSession: function(event, checkout, callback) {
					console.log('validate session');
					callback({
					   success: false,
					   clientValidationError: "testing",
					});
				},
			}).then(function(checkout) {
				dinteroCheckoutForWooCommerce.checkout = checkout;
			});
		},

		/**
		 * Triggers on document ready.
		 */
		documentReady: function () {
			if (0 < $('input[name="payment_method"]').length) {
				dinteroCheckoutForWooCommerce.paymentMethod = $('input[name="payment_method"]').filter(':checked').val();
			} else {
				dinteroCheckoutForWooCommerce.paymentMethod = 'dintero_checkout';
			}

			if (!dinteroCheckoutParams.payForOrder && dinteroCheckoutForWooCommerce.paymentMethod === 'dintero_checkout') {
				dinteroCheckoutForWooCommerce.moveExtraCheckoutFields();
			}
		},

		/**
		 * When the customer changes from Dintero Checkout to other payment methods.
		 * @param {Event} e
		 */
		changeFromDinteroCheckout: function (e) {
			e.preventDefault();
			$(dinteroCheckoutForWooCommerce.checkoutFormSelector).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					dintero_checkout: false,
					nonce: dinteroCheckoutParams.change_payment_method_nonce
				},
				url: dinteroCheckoutParams.change_payment_method_url,
				success: function (data) { },
				error: function (data) { },
				complete: function (data) {
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},
		/**
		 * When the customer changes to Dintero Checkout from other payment methods.
		 */
		maybeChangeToDinteroCheckout: function () {
			if (!dinteroCheckoutForWooCommerce.preventPaymentMethodChange) {
				if ('dintero_checkout' === $(this).val()) {
					$('.woocommerce-info').remove();
					$(dinteroCheckoutForWooCommerce.checkoutFormSelector).block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					$.ajax({
						type: 'POST',
						data: {
							dintero_checkout: true,
							nonce: dinteroCheckoutParams.change_payment_method_nonce
						},
						dataType: 'json',
						url: dinteroCheckoutParams.change_payment_method_url,
						success: function (data) { },
						error: function (data) { },
						complete: function (data) {
							window.location.href = data.responseJSON.data.redirect;
						}
					});
				}
			}
		},
		/*
		 * Check if Dintero Checkout is the selected gateway.
		 */
		checkIfDinteroCheckoutSelected: function () {
			if (dinteroCheckoutForWooCommerce.paymentMethodEl.length > 0) {
				dinteroCheckoutForWooCommerce.paymentMethod = dinteroCheckoutForWooCommerce.paymentMethodEl.filter(':checked').val();
				if ('dintero_checkout' === dinteroCheckoutForWooCommerce.paymentMethod) {
					return true;
				}
			}
			return false;
		},
		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function () {
			// Move order comments.
			$('.woocommerce-additional-fields').appendTo('#dintero-checkout-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for (var i = 0; i < form.length; i++) {
				let name = form[i].name;
				// Check if field is inside the order review.
				if ($('table.woocommerce-checkout-review-order-table').find(form[i]).length) {
					continue;
				}

				// Check if this is a standard field.
				if (-1 === $.inArray(name, dinteroCheckoutParams.standardWooCheckoutFields)) {
					// This is not a standard Woo field, move to our div.
					if (0 < $('p#' + name + '_field').length) {
						$('p#' + name + '_field').appendTo('#dintero-checkout-extra-checkout-fields');
					} else {
						$('input[name="' + name + '"]').closest('p').appendTo('#dintero-checkout-extra-checkout-fields');
					}
				}
			}
		},
		updateAddress: function (billingAddress, shippingAddress) {
			let update = false;
			// Set billing data.
			if( null !== billingAddress || undefined !== billingAddress ) {
				var billingEmail = (('email' in billingAddress) ? billingAddress.email : null);
				var billingPhone = (('phone_number' in billingAddress) ? billingAddress.phone_number : null);
				var billingFirstName = (('first_name' in billingAddress) ? billingAddress.first_name : null);
				var billingLastName = (('last_name' in billingAddress) ? billingAddress.last_name : null);
				var billingAddress1 = (('address_line' in billingAddress) ? billingAddress.address_line : null);
				var billingPostalCode = (('postal_code' in billingAddress) ? billingAddress.postal_code : null);
				var billingCity = (('postal_place' in billingAddress) ? billingAddress.postal_place : null);
				var billingCountry = (('country' in billingAddress) ? billingAddress.country : null);

				(billingEmail !== null && billingEmail !== undefined) ? $('#billing_email').val(billingEmail) : null;
				(billingPhone !== null && billingPhone !== undefined) ? $('#billing_phone').val(billingPhone) : null;
				(billingFirstName !== null && billingFirstName !== undefined) ? $('#billing_first_name').val(billingFirstName) : null;
				(billingLastName !== null && billingLastName !== undefined) ? $('#billing_last_name').val(billingLastName) : null;
				(billingAddress !== null && billingAddress !== undefined) ? $('#billing_address_1').val(billingAddress1) : null;
				(billingPostalCode !== null && billingPostalCode !== undefined) ? $('#billing_postcode').val(billingPostalCode) : null;
				(billingCity !== null && billingCity !== undefined) ? $('#billing_city').val(billingCity) : null;
				(billingCountry !== null && billingCountry !== undefined) ? $('#billing_country').val(billingCountry) : null;
				update = true;
			}

			// Set shipping data.
			if( null !== shippingAddress || undefined !== shippingAddress ) {
				$( '#ship-to-different-address-checkbox' ).prop( 'checked', true);
				var shippingFirstName = (('first_name' in shippingAddress) ? shippingAddress.first_name : null);
				var shippingLastName = (('last_name' in shippingAddress) ? shippingAddress.last_name : null);
				var shippingAddress1 = (('address_line' in shippingAddress) ? shippingAddress.address_line : null);
				var shippingPostalCode = (('postal_code' in shippingAddress) ? shippingAddress.postal_code : null);
				var shippingCity = (('postal_place' in shippingAddress) ? shippingAddress.postal_place : null);
				var shippingCountry = (('country' in shippingAddress) ? shippingAddress.country : null);

				(shippingFirstName !== null && shippingFirstName !== undefined) ? $('#shipping_first_name').val(shippingFirstName) : null;
				(shippingLastName !== null && shippingLastName !== undefined) ? $('#shipping_last_name').val(shippingLastName) : null;
				(shippingAddress !== null && shippingAddress !== undefined) ? $('#shipping_address_1').val(shippingAddress1) : null;
				(shippingPostalCode !== null && shippingPostalCode !== undefined) ? $('#shipping_postcode').val(shippingPostalCode) : null;
				(shippingCity !== null && shippingCity !== undefined) ? $('#shipping_city').val(shippingCity) : null;
				(shippingCountry !== null && shippingCountry !== undefined) ? $('#shipping_country').val(shippingCountry) : null;
				update = true;
			}

			// Trigger changes
			if(update) {
				$('#billing_email').change();
				$('#billing_email').blur();
				$("form.checkout").trigger('update_checkout');
			}
		},

		getDinteroCheckoutOrder: function (data, callback) {
			$.ajax({
				type: 'POST',
				data: {
					nonce: dinteroCheckoutParams.get_order_nonce,
				},
				dataType: 'json',
				url: dinteroCheckoutParams.get_order_url,
				success: function (data) {
				},
				error: function (data) {
				},
				complete: function (data) {
					dinteroCheckoutForWooCommerce.setAddressData(data.responseJSON.data, callback);
					console.log('getdinteroCheckoutOrder completed');
				}
			});
		},

		/*
		 * Sets the WooCommerce form field data.
		 */
		setAddressData: function (addressData, callback) {
			if (0 < $('form.checkout #terms').length) {
				$('form.checkout #terms').prop('checked', true);
			}

			// Billing fields.
			$('#billing_first_name').val(addressData.billingAddress.FirstName);
			$('#billing_last_name').val(addressData.billingAddress.LastName);
			$('#billing_company').val(addressData.billingAddress.CompanyName);
			$('#billing_address_1').val(addressData.billingAddress.Street);
			$('#billing_address_2').val(addressData.billingAddress.Street2);
			$('#billing_city').val(addressData.billingAddress.City);
			$('#billing_postcode').val(addressData.billingAddress.PostalCode);
			$('#billing_phone').val(addressData.customer.MobileNumber);
			$('#billing_email').val(addressData.customer.Email);

			// Shipping fields.
			$('#ship-to-different-address-checkbox').prop('checked', true);
			$('#shipping_first_name').val(addressData.shippingAddress.FirstName);
			$('#shipping_last_name').val(addressData.shippingAddress.LastName);
			$('#shipping_company').val(addressData.shippingAddress.CompanyName);
			$('#shipping_address_1').val(addressData.shippingAddress.Street);
			$('#shipping_address_2').val(addressData.shippingAddress.Street2);
			$('#shipping_city').val(addressData.shippingAddress.City);
			$('#shipping_postcode').val(addressData.shippingAddress.PostalCode);

			// Only set country fields if we have data in them.
			if (addressData.billingAddress) {
				$('#billing_country').val(addressData.billingAddress.CountryCode);
			}
			if (addressData.shippingAddress) {
				$('#shipping_country').val(addressData.shippingAddress.CountryCode);
			}
		},

		/**
		 * Submit the order using the WooCommerce AJAX function.
		 */
		submitOrder: function (callback) {
			$('.woocommerce-checkout-review-order-table').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax({
				type: 'POST',
				url: dinteroCheckoutParams.submitOrder,
				data: $('form.checkout').serialize(),
				dataType: 'json',
				success: function (data) {
					console.log(data);
					try {
						if ('success' === data.result) {
							callback({ shouldProceed: true, errorMessage: "" });
							console.log('submit order success', data);
						} else {
							throw 'Result failed';
						}
					} catch (err) {
						console.log('catch error');
						console.error(err);
						if (data.messages) {
							// Strip HTML code from messages.
							let messages = data.messages.replace(/<\/?[^>]+(>|$)/g, "");
							console.log('error ', messages);
							dinteroCheckoutForWooCommerce.logToFile('Checkout error | ' + messages);
							dinteroCheckoutForWooCommerce.failOrder('submission', messages, callback);
						} else {
							dinteroCheckoutForWooCommerce.logToFile('Checkout error | No message');
							dinteroCheckoutForWooCommerce.failOrder('submission', 'Checkout error', callback);
						}
					}
				},
				error: function (data) {
					try {
						dinteroCheckoutForWooCommerce.logToFile('AJAX error | ' + JSON.stringify(data));
					} catch (e) {
						dinteroCheckoutForWooCommerce.logToFile('AJAX error | Failed to parse error message.');
					}
					dinteroCheckoutForWooCommerce.failOrder('ajax-error', 'Internal Server Error', callback)
				}
			});
		},

		failOrder: function (event, error_message, callback) {
			callback({ shouldProceed: false, errorMessage: error_message });

			// Renable the form.
			$('body').trigger('updated_checkout');
			$(dinteroCheckoutForWooCommerce.checkoutFormSelector).removeClass('processing');
			$(dinteroCheckoutForWooCommerce.checkoutFormSelector).unblock();
			$('.woocommerce-checkout-review-order-table').unblock();
		},

		/**
		 * Logs the message to the klarna checkout log in WooCommerce.
		 * @param {string} message 
		 */
		logToFile: function (message) {
			$.ajax(
				{
					url: dinteroCheckoutParams.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message: message,
						nonce: dinteroCheckoutParams.log_to_file_nonce
					}
				}
			);
		},
	};

	dinteroCheckoutForWooCommerce.init();
});