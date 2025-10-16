jQuery( function ( $ ) {
    /* Check if WP has added our localized parameters (refer to class-dintero-checkout-assets.php.) */
    if ( ! dinteroCheckoutParams ) {
        return
    }

    const dinteroCheckoutForWooCommerce = {
        bodyEl: $( "body" ),
        checkoutFormSelector: "form.checkout",
        preventPaymentMethodChange: false,
        selectAnotherSelector: "#dintero-checkout-select-other",
        paymentMethodEl: $( 'input[name="payment_method"]' ),
        checkout: null,
        validation: false,
        isLocked: false,

        init() {
            $( document ).ready( dinteroCheckoutForWooCommerce.documentReady )
            dinteroCheckoutForWooCommerce.bodyEl.on(
                "change",
                'input[name="payment_method"]',
                dinteroCheckoutForWooCommerce.maybeChangeToDinteroCheckout,
            )
            dinteroCheckoutForWooCommerce.bodyEl.on(
                "click",
                dinteroCheckoutForWooCommerce.selectAnotherSelector,
                dinteroCheckoutForWooCommerce.changeFromDinteroCheckout,
            )

            if ( $( "#dintero-checkout-iframe" ).length !== 0 ) {
                dinteroCheckoutForWooCommerce.renderIframe()
            } else {
                console.error( "Dintero Checkout: Could not find the container for the iframe." )
            }

            // WC won't reload the checkout page if Dintero becomes available after being unavailable while remaining on the same page. E.g., when changing shipping method that makes the cart amount non-zero. This seems to only happen when WooCommerce Subscriptions is used.
            $( "body" ).on( "updated_checkout", function () {
                if (
                    0 === $( "#dintero-checkout-iframe" ).length &&
                    dinteroCheckoutForWooCommerce.isSelectedGateway()
                ) {
                    window.location.reload()
                }
            } )

            /* These are _WC_ events we attach onto. */
            dinteroCheckoutForWooCommerce.bodyEl.on( "update_checkout", dinteroCheckoutForWooCommerce.updateCheckout )
            dinteroCheckoutForWooCommerce.bodyEl.on( "updated_checkout", dinteroCheckoutForWooCommerce.updatedCheckout )
            dinteroCheckoutForWooCommerce.bodyEl.on(
                "updated_checkout",
                dinteroCheckoutForWooCommerce.maybeDisplayShippingPrice,
            )
        },

        updateCheckout() {
            if ( dinteroCheckoutForWooCommerce.checkout !== null && ! dinteroCheckoutForWooCommerce.validation ) {
                if ( dinteroCheckoutForWooCommerce.isLocked ) {
                    /* If the dintero_locked is present, we'll issue an update request to Dintero. WC takes care of submitting the form through AJAX. */
                    $( dinteroCheckoutForWooCommerce.checkoutFormSelector ).append(
                        '<input type="hidden" name="dintero_locked" id="dintero_locked" value=1>',
                    )
                } else {
                    dinteroCheckoutForWooCommerce.checkout.lockSession()
                }
            }
        },

        updatedCheckout() {
            if ( dinteroCheckoutForWooCommerce.checkout !== null && ! dinteroCheckoutForWooCommerce.validation ) {
                if ( dinteroCheckoutForWooCommerce.isLocked ) {
                    $( "#dintero_locked" ).remove()
                    dinteroCheckoutForWooCommerce.isLocked = false
                    dinteroCheckoutForWooCommerce.checkout.refreshSession()
                }
            }
        },

        /**
         * Render the iframe and register callback functionality.
         */
        async renderIframe() {
            const iframeElement = $( "#dintero-checkout-iframe" )
            const container = iframeElement.length ? iframeElement[ 0 ] : null

            if ( ! container ) {
                console.error( "Dintero Checkout: Could not find the container for the iframe." )
                return
            }

            dintero
                .embed( {
                    container,
                    sid: dinteroCheckoutParams.SID,
                    popOut: true == dinteroCheckoutParams.popOut ? true : false,
                    language: dinteroCheckoutParams.language,
                    onSession( event, checkout ) {
                        // If the session expires, the order object will be missing.
                        if ( event.session === undefined || event.session.order === undefined ) {
                            // Refresh the session to display the error message from Dintero. The error itself should be handled by any of other event handlers.
                            checkout.refreshSession()
                            return
                        }
                    },
                    onPayment(event, checkout) {
                        // Dintero does not have an event for when the modal is closed without proceeding with the payment. Therefore, we'll unlock it, and let Dintero handle the blocking. Since the WC form is unblocked, during the split second it takes for the redirect to happen, the customer can still interact with the form when Dintero remove their block. Therefore, we block the form again while the redirect is in progress.
                        dinteroCheckoutForWooCommerce.blockForm()
                        window.location = event.href
                    },
                    onPaymentCanceled() {
                        console.log('payment canceled', arguments)
                    },
                    onPaymentError( event, checkout ) {
                        checkout.destroy()

                        $.ajax( {
                            type: "POST",
                            dataType: "json",
                            data: {
                                nonce: dinteroCheckoutParams.print_notice_nonce,
                                message: "A payment error was encountered.",
                                notice_type: "error",
                            },
                            url: dinteroCheckoutParams.print_notice_url,
                            complete() {
                                dinteroCheckoutForWooCommerce.unsetSession( event.href )
                            },
                        } )
                    },
                    onSessionCancel( event, checkout ) {
                        checkout.destroy()
                        dinteroCheckoutForWooCommerce.unsetSession( event.href )
                    },
                    onSessionNotFound( event, checkout ) {
                        /* Unset the session, and redirect the customer back to the checkout page (the same page). The checkout will automatically be destroyed. */
                        dinteroCheckoutForWooCommerce.unsetSession( window.location.pathname )
                    },
                    onSessionLocked( event, checkout, callback ) {
                        dinteroCheckoutForWooCommerce.isLocked = true

                        /* A checkout update happened, but the checkout was not locked. The checkout is now locked: */
                        $(document.body).trigger("update_checkout")
                    },
                    onSessionLockFailed( event, checkout ) {
                        console.warn( "Failed to lock the checkout.", event )
                    },
                    onActivePaymentType( event, checkout ) {
                        // Unused.
                    },
                    onValidateSession( event, checkout, callback ) {
                        dinteroCheckoutForWooCommerce.validation = true
                        if ( 0 < $( "form.checkout #terms" ).length ) {
                            $( "form.checkout #terms" ).prop( "checked", true )
                        }

                        const id = checkout.session.id
                        dinteroCheckoutForWooCommerce.submitOrder( callback, id )
                        dinteroCheckoutForWooCommerce.validation = false
                    },
                } )
                .then( function ( checkout ) {
                    dinteroCheckoutForWooCommerce.checkout = checkout
                } )
        },

        unsetSession( redirectUrl ) {
            $.ajax( {
                type: "POST",
                dataType: "json",
                data: {
                    nonce: dinteroCheckoutParams.unset_session_nonce,
                },
                url: dinteroCheckoutParams.unset_session_url,
                complete() {
                    window.location.replace( redirectUrl )
                },
            } )
        },
        /**
         * Triggers on document ready.
         */
        documentReady() {
            if ( 0 < $( 'input[name="payment_method"]' ).length ) {
                dinteroCheckoutForWooCommerce.paymentMethod = $( 'input[name="payment_method"]' )
                    .filter( ":checked" )
                    .val()
            } else {
                dinteroCheckoutForWooCommerce.paymentMethod = "dintero_checkout"
            }

            $( "form.checkout" ).trigger( "update_checkout" )
        },

        /**
         * When the customer changes from Dintero Checkout to other payment methods.
         *
         * @param {Event} e
         */
        changeFromDinteroCheckout( e ) {
            e.preventDefault()
            $( dinteroCheckoutForWooCommerce.checkoutFormSelector ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )

            $.ajax( {
                type: "POST",
                dataType: "json",
                data: {
                    dintero_checkout: false,
                    nonce: dinteroCheckoutParams.change_payment_method_nonce,
                },
                url: dinteroCheckoutParams.change_payment_method_url,
                complete( data ) {
                    window.location.href = data.responseJSON.data.redirect
                },
            } )
        },
        /**
         * When the customer changes to Dintero Checkout from other payment methods.
         */
        maybeChangeToDinteroCheckout() {
            if ( ! dinteroCheckoutForWooCommerce.preventPaymentMethodChange ) {
                if ( "dintero_checkout" === $( this ).val() ) {
                    $( ".woocommerce-info" ).remove()
                    $( dinteroCheckoutForWooCommerce.checkoutFormSelector ).block( {
                        message: null,
                        overlayCSS: {
                            background: "#fff",
                            opacity: 0.6,
                        },
                    } )
                    $.ajax( {
                        type: "POST",
                        data: {
                            dintero_checkout: true,
                            nonce: dinteroCheckoutParams.change_payment_method_nonce,
                        },
                        dataType: "json",
                        url: dinteroCheckoutParams.change_payment_method_url,
                        complete( data ) {
                            window.location.href = data.responseJSON.data.redirect
                        },
                    } )
                }
            }
        },
        /*
         * Check if Dintero Checkout is the selected gateway.
         */
        isSelectedGateway() {
            return $( 'input[name="payment_method"]' ).filter( ":checked" ).val() === "dintero_checkout"
        },

        /**
         * Block form fields from being modified by the user.
         */
        blockForm() {
            /* Order review, address fields. */
            $( ".woocommerce-checkout-review-order-table, #customer_details" ).block( {
                message: dinteroCheckoutParams.pip_text,
                overlayCSS: {
                    background: "#fff",
                },
                css: {
                    width: "fit-content",
                    height: "fit-content",
                    padding: "0.2em 0.8em",
                    border: "none",
                },
                blockMsgClass: "dintero-checkout-pip",
            } )

            $( ".dintero-checkout-pip" ).siblings( ".blockOverlay" ).addClass( "dintero-checkout-no-spinner" )
        },

        /**
         * Unblock form fields.
         */
        unblockForm() {
            /* Order review, address fields. */
            $( ".woocommerce-checkout-review-order-table, #customer_details" ).unblock()
        },

        /**
         * Submit the order using the WooCommerce AJAX function.
         *
         * @param {callback} callback
         * @param {id} id The session id.
         */
        submitOrder( callback, id ) {
            dinteroCheckoutForWooCommerce.blockForm()

            $.ajax( {
                type: "POST",
                url: dinteroCheckoutParams.verifyOrderTotalURL,
                data: {
                    id,
                    nonce: dinteroCheckoutParams.verifyOrderTotalNonce,
                },
                dataType: "json",
                success: ( data ) => {
                    console.log( "order total diff: %s", data.data )
                    if ( ! data.success ) {
                        dinteroCheckoutForWooCommerce.failOrder(
                            "submit order failed",
                            dinteroCheckoutParams.verifyOrderTotalError,
                            callback,
                        )
                        return
                    }

                    $.ajax( {
                        type: "POST",
                        url: dinteroCheckoutParams.submitOrder,
                        data: $( "form.checkout" ).serialize(),
                        dataType: "json",
                        success( data ) {
                            try {
                                console.log( "try" )
                                if ( "success" === data.result ) {
                                    console.log( "submit order success", data )
                                    callback( { success: true } )
                                } else {
                                    throw "Result failed"
                                }
                            } catch ( err ) {
                                console.log( "catch error" )
                                console.error( err )
                                if ( data.messages ) {
                                    // Strip HTML code from messages.
                                    const messages = data.messages.replace( /<\/?[^>]+(>|$)\s+/g, "" )
                                    dinteroCheckoutForWooCommerce.printNotice( messages )
                                    dinteroCheckoutForWooCommerce.logToFile(
                                        dinteroCheckoutParams.SID + " | Checkout error | " + messages,
                                    )
                                    dinteroCheckoutForWooCommerce.failOrder( "submission", messages, callback )
                                } else {
                                    dinteroCheckoutForWooCommerce.logToFile(
                                        dinteroCheckoutParams.SID + " | Checkout error | No message",
                                    )
                                    dinteroCheckoutForWooCommerce.failOrder( "submission", "Checkout error", callback )
                                }
                            }
                        },
                        error( data ) {
                            console.log( "error data", data )
                            console.log( "error data response text", data.responseText )
                            try {
                                dinteroCheckoutForWooCommerce.logToFile(
                                    dinteroCheckoutParams.SID + " | AJAX error | " + JSON.stringify( data ),
                                )
                            } catch ( e ) {
                                dinteroCheckoutForWooCommerce.logToFile(
                                    dinteroCheckoutParams.SID + " | AJAX error | Failed to parse error message.",
                                )
                            }
                            dinteroCheckoutForWooCommerce.failOrder( "ajax-error", "Internal Server Error", callback )
                        },
                        complete() {
                            dinteroCheckoutForWooCommerce.unblockForm()
                        }
                    } )
                },
                error: () => {
                    dinteroCheckoutForWooCommerce.failOrder(
                        "submit order failed",
                        dinteroCheckoutParams.verifyOrderTotalError,
                        callback,
                    )
                },
            } )
        },

        failOrder( event, errorMessage, callback ) {
            console.log( "fail order" )
            callback( { success: false, clientValidationError: errorMessage } )

            // Renable the form.
            $( "body" ).trigger( "updated_checkout" )
            $( dinteroCheckoutForWooCommerce.checkoutFormSelector ).removeClass( "processing" )
            $( dinteroCheckoutForWooCommerce.checkoutFormSelector ).unblock()
            dinteroCheckoutForWooCommerce.unblockForm()
        },

        printNotice( message, noticeType = "error" ) {
            /* There are two wrappers for some reason hence the first() to prevent duplicate notices. */
            $( ".woocommerce-notices-wrapper" )
                .first()
                .append( `<div class='woocommerce-${ noticeType }' role='alert'>${ message }</div>` )
            if ( "error" === noticeType ) {
                $( document.body ).trigger( "checkout_error", [ message ] )
            }
            $( "html, body" ).animate(
                {
                    scrollTop: $( ".woocommerce-notices-wrapper" ).offset().top - 100,
                },
                1000,
            )
        },

        /**
         * Logs the message to the Dintero Checkout log in WooCommerce.
         *
         * @param {string} message
         */
        logToFile( message ) {
            $.ajax( {
                url: dinteroCheckoutParams.log_to_file_url,
                type: "POST",
                dataType: "json",
                data: {
                    message,
                    nonce: dinteroCheckoutParams.log_to_file_nonce,
                },
            } )
        },
    }

    dinteroCheckoutForWooCommerce.init()
} )
