jQuery( function( $ ) {
	const dwc = {
		saveButton: $( '.woocommerce-save-button' ),
		form_factor: $( '#woocommerce_dintero_checkout_form_factor' ),
		branding: {
			logo_color_checkbox: $(
				'#woocommerce_dintero_checkout_branding_logo_color'
			),
			logo_custom_color: $(
				'#woocommerce_dintero_checkout_branding_logo_color_custom'
			),
			toggle_logo_color() {
				if ( dwc.branding.logo_color_checkbox.is( ':checked' ) ) {
					dwc.branding.logo_custom_color.parents( 'tr' ).hide();
					dwc.branding.logo_color_checkbox
						.parents( 'tr' )
						.css( 'flex-basis', '100%' );
				} else {
					dwc.branding.logo_custom_color.parents( 'tr' ).show();
					dwc.branding.logo_color_checkbox.parents( 'tr' ).css( 'flex', '' );
				}
			},
		},
		register_events() {
			dwc.branding.logo_color_checkbox.on(
				'change',
				dwc.branding.toggle_logo_color
			);
		},
		startup_check() {
			dwc.branding.toggle_logo_color();
		},
		onSave() {
			/* If no custom color was set, assume default state. */
			if ( ! dwc.branding.logo_custom_color.val() ) {
				dwc.branding.logo_color_checkbox.prop( 'checked', true );
			}
		},
		toggle_form_factor() {
			const siblings = dwc.form_factor.parents( 'tr' ).siblings();

			if ( dwc.form_factor.val() === 'embedded' ) {
				siblings.fadeOut();
			} else {
				siblings.fadeIn();
			}
		},
	};

	$( document ).ready( function() {
		dwc.toggle_form_factor();
		dwc.form_factor.change( dwc.toggle_form_factor );
		dwc.register_events();
		dwc.startup_check();

		dwc.saveButton.on( 'click', dwc.onSave );
	} );
} );
