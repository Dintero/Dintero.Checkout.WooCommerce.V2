jQuery(function($) {
    const dwc = {
        saveButton: $(".woocommerce-save-button"),
        branding: {
            logo_color_checkbox: $(
                "#woocommerce_dintero_checkout_branding_logo_color"
            ),
            logo_custom_color: $(
                "#woocommerce_dintero_checkout_branding_logo_color_custom"
            ),
            toggle_logo_color: function() {
                if (dwc.branding.logo_color_checkbox.is(":checked")) {
                    dwc.branding.logo_custom_color.parents("tr").hide();
                    dwc.branding.logo_color_checkbox
                        .parents("tr")
                        .css("flex-basis", "100%");
                } else {
                    dwc.branding.logo_custom_color.parents("tr").show();
                    dwc.branding.logo_color_checkbox.parents("tr").css("flex", "");
                }
            },
        },
        register_events: function() {
            dwc.branding.logo_color_checkbox.on(
                "change",
                dwc.branding.toggle_logo_color
            );
        },
        startup_check: function() {
            dwc.branding.toggle_logo_color();
        },
        onSave: function() {
            /* If no custom color was set, assume default state. */
            if (!dwc.branding.logo_custom_color.val()) {
                dwc.branding.logo_color_checkbox.prop("checked", true);
            }
        },
    };

    $(document).ready(function() {
        dwc.register_events();
        dwc.startup_check();

        dwc.saveButton.on("click", dwc.onSave);
    });
});