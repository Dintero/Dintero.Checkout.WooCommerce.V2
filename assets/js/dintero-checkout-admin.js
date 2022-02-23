jQuery(function ($) {
	const dwc = {
		branding: {
			'logo_color_checkbox': $('#woocommerce_dintero_checkout_branding_logo_color'),
			'logo_custom_color': $('#woocommerce_dintero_checkout_branding_logo_color_custom'),
			'footer_color_checkbox': $('#woocommerce_dintero_checkout_branding_footer_background_color'),
			'footer_custom_color': $('#woocommerce_dintero_checkout_branding_footer_background_color_custom'),
			toggle_logo_color: function () {
				if (dwc.branding.logo_color_checkbox.is(':checked')) {
					dwc.branding.logo_custom_color.parents('tr').fadeOut();
				} else {
					dwc.branding.logo_custom_color.parents('tr').fadeIn();
				}
			},
			toggle_footer_color: function () {
				if (dwc.branding.footer_color_checkbox.is(':checked')) {
					dwc.branding.footer_custom_color.parents('tr').fadeOut();
				} else {
					dwc.branding.footer_custom_color.parents('tr').fadeIn();
				}
			}
		},
		register_events: function () {
			dwc.branding.logo_color_checkbox.on('change', dwc.branding.toggle_logo_color)
			dwc.branding.footer_color_checkbox.on('change', dwc.branding.toggle_footer_color);
		},
		startup_check: function () {
			
			dwc.branding.toggle_logo_color();
			dwc.branding.toggle_footer_color();
		},

	};

	$(document).ready(function () {
		dwc.register_events();
		dwc.startup_check();
	});
});