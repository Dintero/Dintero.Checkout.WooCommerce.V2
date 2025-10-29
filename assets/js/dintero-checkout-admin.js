jQuery( function ( $ ) {
    const dwc = {
        saveButton: $( ".woocommerce-save-button" ),
        checkout_flow: $( "#woocommerce_dintero_checkout_checkout_flow" ),
        branding: {
            logo_color_checkbox: $( "#woocommerce_dintero_checkout_branding_logo_color" ),
            logo_custom_color: $( "#woocommerce_dintero_checkout_branding_logo_color_custom" ),
            logo_custom_color_mode: $( "#woocommerce_dintero_checkout_branding_logo_color_mode" ),
            toggle_logo_color() {
                if ( dwc.branding.logo_color_checkbox.is( ":checked" ) ) {
                    dwc.branding.logo_custom_color.parents( "tr" ).hide()
                    dwc.branding.logo_color_checkbox.parents( "tr" ).css( "flex-basis", "100%" )
                    dwc.branding.logo_custom_color_mode.parents( "tr" ).show()
                } else {
                    dwc.branding.logo_custom_color.parents( "tr" ).show()
                    dwc.branding.logo_color_checkbox.parents( "tr" ).css( "flex", "" )
                    dwc.branding.logo_custom_color_mode.parents( "tr" ).hide()
                }
            },
        },
        register_events() {
            dwc.branding.logo_color_checkbox.on( "change", dwc.branding.toggle_logo_color )
        },
        startup_check() {
            dwc.branding.toggle_logo_color()
        },
        onSave() {
            /* If no custom color was set, assume default state. */
            if ( ! dwc.branding.logo_custom_color.val() ) {
                dwc.branding.logo_color_checkbox.prop( "checked", true )
            }
        },
        toggle_express_shipping( isExpress ) {
            const option = $( "#woocommerce_dintero_checkout_express_shipping_in_iframe" ).parents( "tr" )

            if ( isExpress ) {
                option.fadeIn()
            } else {
                option.fadeOut()
            }
        },
        onFlowChange() {
            // Posible values: express_popout, express_embedded, checkout_redirect, checkout_popout, checkout_embedded.
            const flow = dwc.checkout_flow.val()
            const redirectOnly = $( ".redirect-only" ).parents( "tr" )
            const embeddedOnly = $( ".embedded-only" ).parents( "tr" )

            redirectOnly.toggle( flow.includes( "redirect" ) )
            embeddedOnly.toggle( flow.includes( "embedded" ) )

            dwc.toggle_express_shipping( flow.includes( "express" ) )
        },
    }

    $( document ).ready( function () {
        dwc.register_events()
        dwc.startup_check()

        // Only display "Display Shipping in Checkout Express" option if express checkout is selected.
        dwc.checkout_flow.change( dwc.onFlowChange )
        dwc.onFlowChange()

        dwc.saveButton.on( "click", dwc.onSave )
    } )
} )
