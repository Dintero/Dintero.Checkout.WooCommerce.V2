jQuery(function ($) {
	const dwc = {
		form_factor: $('#woocommerce_dintero_checkout_form_factor'),
		toggle_form_factor: function () {
			let siblings = dwc.form_factor.parents('tr').siblings();

			if (dwc.form_factor.val() === 'embedded') {
				siblings.fadeOut();
			} else {
				siblings.fadeIn();
			}
		},
	};

	$(document).ready(function () {
		dwc.toggle_form_factor();
		dwc.form_factor.change(dwc.toggle_form_factor);
	});
});
