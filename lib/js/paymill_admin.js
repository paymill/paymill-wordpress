jQuery(document).ready(function () {
	jQuery('#common_toggle').click(function() {
		jQuery('#common_content').toggle('slow', function() {
			// Animation complete.
			
		});
		return false; 
	});
	jQuery('#products_toggle').click(function() {
		jQuery('#products_content').toggle('slow', function() {
			// Animation complete.
			
		});
		return false; 
	});
	jQuery('#shipping_toggle').click(function() {
		jQuery('#shipping_content').toggle('slow', function() {
			// Animation complete.
			
		});
		return false; 
	});
	
	jQuery('.paymill_settings_section_pay_button_products tr:first-child td, .paymill_settings_section_pay_button_shipping tr:first-child td').dcTooltip({distance: 0,padLeft: 0});
});