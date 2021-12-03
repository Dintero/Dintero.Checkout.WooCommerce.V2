jQuery( function($) {
	'use strict';
	var titles = $('h3.wc-settings-sub-title');
	var tables = $('h3.wc-settings-sub-title + table.form-table');
	var submitButton = $('.button-primary, .woocommerce-save-button');

	titles.append(' <a href="#" class="collapsed" style="font-size:12px; font-weight: normal; text-decoration: none"><span class="dashicons dashicons-arrow-down-alt2"></span></a>');
	tables.css('marginLeft', '10px').hide();
    
	var clientIdTitle = $('#woocommerce_dintero-checkout-v2_client');
    clientIdTitle.find('a').html('<span class="dashicons dashicons-arrow-up-alt2">');
	clientIdTitle.find('a').removeClass('collapsed');
    clientIdTitle.next().show();

	titles.find('a').on('click', function(e) {
		e.preventDefault();

		if ($(this).hasClass('collapsed')) {
			$(this).parent().next().show();
			$(this).removeClass('collapsed');
			$(this).html('<span class="dashicons dashicons-arrow-up-alt2"></span>');
		} else {
			$(this).parent().next().hide();
			$(this).addClass('collapsed');
			$(this).html('<span class="dashicons dashicons-arrow-down-alt2"></span>');

		}
	});

	titles.before('<hr style="margin-top:2em;margin-bottom:2em" />');
	submitButton.before('<hr style="margin-top:2em;margin-bottom:2em" />');

	function validateInput(inputRef, regex, errorText) {
		var input = $(inputRef);
		input.removeClass('invalid_input');
		submitButton.prop('disabled', false);

		input.parent().find('div:contains(' + errorText + ')').remove();
		if( input.val() === '') {
			return;
		}
		if (!regex.test(input.val())) {
			input.addClass('invalid_input');
			submitButton.prop( 'disabled', 'disabled' );
			input.after('<div className="input_validation_text">' + errorText + '</div>');
			input.trigger('focus');
			return;
		}
	}
	function validateNotEmpty(inputRef, errorText) {
		var input = $(inputRef);
		input.removeClass('invalid_input');
		submitButton.prop('disabled', false);

		input.parent().find('div:contains(' + errorText + ')').remove();
		if( input.val() === '') {
			input.addClass('invalid_input');
			submitButton.prop( 'disabled', 'disabled' );
			input.after('<div className="input_validation_text">' + errorText + '</div>');
			input.trigger('focus');
			return;
		}
	}

	var accountIdField = $('#woocommerce_dintero-checkout-v2_account_id');
	var clientIdField = $('#woocommerce_dintero-checkout-v2_client_id');
	var clientSecretField = $('#woocommerce_dintero-checkout-v2_client_secret');

	accountIdField.on('change', function() {
		validateInput(this, /\d{8}/, "Account ID should contain 8 digits and no letters. Remove leading T or P.");
		validateNotEmpty(this, "Account ID must not be empty");
	});
	clientIdField.on('change', function() {
		console.log("changes");
		validateNotEmpty(this, "Client ID must not be empty");
	});
	clientSecretField.on('change', function() {
		validateNotEmpty(this, "Client Secret must not be empty");
	});
});




