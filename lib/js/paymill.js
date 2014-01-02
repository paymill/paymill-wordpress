jQuery(document).ready(function () {
	if(typeof paymill_shop_name != 'undefined'){
		if(paymill_shop_name == 'woocommerce'){
			jQuery('body').on('click', paymill_form_checkout_submit_id, function(event) {
				if(jQuery('#payment_method_paymill').is(':checked')){
					event.preventDefault();
					jQuery(paymill_form_checkout_submit_id).hide();
					if(bridgePreparePayment() == false){
						jQuery(paymill_form_checkout_submit_id).show();
					}else{
						//jQuery(paymill_form_checkout_submit_id).show();
					}
				}
			});
		}
		if(paymill_shop_name != 'woocommerce'){
			jQuery('body').on('click', paymill_form_checkout_submit_id, function(event) {
				event.preventDefault();
				jQuery(paymill_form_checkout_submit_id).hide();
				bridgePreparePayment();
			});
		}
	}
	function bridgePreparePayment(){
		// check which payment method is active
			if(jQuery('#form-credit').is(':visible')){
				if (false == paymill.validateCardNumber(jQuery('.paymill_card-number').val())) {
					jQuery(".paymill_payment_errors").text(paymill_lang.validateCardNumber);
					jQuery(paymill_form_checkout_submit_id).show();
					return false;
				}

				if (false == paymill.validateExpiry(jQuery('.paymill_card-expiry-month').val(), jQuery('.paymill_card-expiry-year').val())) {
					jQuery(".paymill_payment_errors").text(paymill_lang.validateExpiry);
					jQuery(paymill_form_checkout_submit_id).show();
					return false;
				}
				
				if (false == paymill.validateCvc(jQuery('#card-cvc').val())) {
					jQuery(".paymill_payment_errors").text(paymill_lang.validateCvc);
					jQuery(paymill_form_checkout_submit_id).show();
					return false;
				}
				
					paymill.createToken({
						number:jQuery('.paymill_card-number').val(),
						exp_month:jQuery('.paymill_card-expiry-month').val(),
						exp_year:jQuery('.paymill_card-expiry-year').val(),
						cvc:jQuery('.paymill_card-cvc').val(),
						cardholder:jQuery('.paymill_holdername').val(),
						amount_int:jQuery('.paymill_amount').val(),
						currency:jQuery('.paymill_currency').val()
					}, function (error, result) {
						if (error) {
							// Zeigt den Fehler überhalb des Formulars an
							jQuery(".paymill_payment_errors").text(error.apierror);
							
							alert(error.apierror);
							
							jQuery(paymill_form_checkout_submit_id).show();
						} else {
							jQuery(".paymill_payment_errors").text("");

							var form = jQuery(paymill_form_checkout_id);

							// Token
							var token = result.token;

							//alert(token);
							// Token in das Formular einfügen damit es an den Server übergeben wird
							form.append("<input type='hidden' name='paymillToken' value='" + token + "'/>");
							
							form.submit();
						}
						//jQuery(paymill_form_checkout_submit_id).removeAttr("disabled");
					});
			}else if(jQuery('#form-elv').is(':visible')){
				if (false == paymill.validateAccountNumber(jQuery('#transaction-form-account').val())) {
					jQuery(".paymill_payment_errors").text(paymill_lang.validateAccountNumber);
					jQuery(paymill_form_checkout_submit_id).show();
					return false;
				}

				if (false == paymill.validateBankCode(jQuery('#transaction-form-code').val())) {
					jQuery(".paymill_payment_errors").text(paymill_lang.validateBankCode);
					jQuery(paymill_form_checkout_submit_id).show();
					return false;
				}
				
				paymill.createToken({
					number:jQuery('#transaction-form-account').val(),
					bank:jQuery('#transaction-form-code').val(),
					accountholder:jQuery('.paymill_holdername').val(),
					amount_int:jQuery('.paymill_amount').val(),
					currency:jQuery('.paymill_currency').val()
				}, function (error, result) {
					if (error) {
						// Zeigt den Fehler überhalb des Formulars an
						jQuery(".paymill_payment_errors").text(error.apierror);
						
						jQuery(paymill_form_checkout_submit_id).show();
						
						alert(error.apierror);
					} else {
						jQuery(".paymill_payment_errors").text("");

						var form = jQuery(paymill_form_checkout_id);

						// Token
						var token = result.token;

						// Token in das Formular einfügen damit es an den Server übergeben wird
						form.append("<input type='hidden' name='paymillToken' value='" + token + "'/>");
						
						form.submit();
					}
					jQuery(paymill_form_checkout_submit_id).removeAttr("disabled");
				});
			}
			return false;
	}
	
	// display credit cart icons
	jQuery('body').on('change', '#card-number', function() {
		var cc_type = paymill.cardType(jQuery('#card-number').val());

		if(cc_type == 'Visa'){
			jQuery('#card-number').show().css("backgroundPosition","0 -210px");
		}else if(cc_type == 'MasterCard'){
			jQuery('#card-number').show().css("backgroundPosition","0 -180px");
		}else if(cc_type == 'American Express'){
			jQuery('#card-number').show().css("backgroundPosition","0 -150px");
		}else if(cc_type == 'Diners Club'){
			jQuery('#card-number').show().css("backgroundPosition","0 -120px");
		}else if(cc_type == 'Discover'){
			jQuery('#card-number').show().css("backgroundPosition","0 -90px");
		}else if(cc_type == 'JCB'){
			jQuery('#card-number').show().css("backgroundPosition","0 -60px");
		}else if(cc_type == 'Maestro'){
			jQuery('#card-number').show().css("backgroundPosition","0 -30px");
		}else if(cc_type == 'CuP'){
			jQuery('#card-number').show().css("backgroundPosition","0 0");
		}else{
			jQuery('#card-number').show().css("backgroundPosition","0 30px");
		}
	});
	
	jQuery("body").on("change", "#billing-country", function() {
		var country = jQuery('#billing-country').val();
		
		if(country == 'DE'){
			jQuery('#form-switch-elv').show('slow');
		}else{
			jQuery('#form-switch-elv').hide('slow');
			
			jQuery('#form-elv').hide('slow');
			jQuery('#form-switch-elv').removeClass('paymill_form-switch_active');
			
			jQuery('#form-credit').show('slow');
			jQuery('#form-switch-credit').addClass('paymill_form-switch_active');
		}
		
	});
	
	
	// ELV switcher
	jQuery('body').on('click', '#form-switch-elv', function() {
		jQuery('#form-credit').hide('slow');
		jQuery('#form-switch-credit').removeClass('paymill_form-switch_active');
		
		jQuery('#form-elv').show('slow');
		jQuery('#form-switch-elv').addClass('paymill_form-switch_active');
	});
	jQuery('body').on('click', '#form-switch-credit', function() {
		jQuery('#form-elv').hide('slow');
		jQuery('#form-switch-elv').removeClass('paymill_form-switch_active');
		
		jQuery('#form-credit').show('slow');
		jQuery('#form-switch-credit').addClass('paymill_form-switch_active');
	});
	
	// Paymill Pay Button	
	jQuery('#paymill_total_number').formatCurrency({
		decimalSymbol: paymill_lang.decimalSymbol,
		digitGroupSymbol: paymill_lang.digitGroupSymbol,
		symbol: ' '+paymill_lang.symbol,
		negativeFormat: '%n%s',
		positiveFormat: '%n%s'
	});
	jQuery('.paymill_price').formatCurrency({
		decimalSymbol: paymill_lang.decimalSymbol,
		digitGroupSymbol: paymill_lang.digitGroupSymbol,
		symbol: ' '+paymill_lang.symbol,
		negativeFormat: '%n%s',
		positiveFormat: '%n%s'
	});
	
	function PaymillPayButtonCalcTotal(){
		var paymill_pay_button_sum = 0;
		jQuery.each(jQuery('select[name^="paymill_quantity"]'), function() {
			var index = jQuery(this).attr('name').match(/\[(\d+)\]/);
			if (index) { index = index[1]; }
			
			if(jQuery('.paymill_price_calc_'+index).html()){
				paymill_pay_button_sum = paymill_pay_button_sum+(Number(jQuery(this).val())*Number(jQuery('.paymill_price_calc_'+index).html()));
			}
		});
		
		var shipping = jQuery('.paymill_shipping option:selected').attr('data-deliverycosts');
		
		paymill_pay_button_sum = paymill_pay_button_sum+Number(shipping);
		
		jQuery('#paymill_total_number').html(paymill_pay_button_sum);
		jQuery('#paymill_total').val(Math.round(paymill_pay_button_sum*100));
		jQuery('#paymill_total_number').formatCurrency({
			decimalSymbol: paymill_lang.decimalSymbol,
			digitGroupSymbol: paymill_lang.digitGroupSymbol,
			symbol: ' '+paymill_lang.symbol,
			negativeFormat: '%n%s',
			positiveFormat: '%n%s'
		});
	}

	jQuery('body').on('click', '.paymill_pay_button', function() {
		PaymillPayButtonCalcTotal();
	});
	
	jQuery('.paymill_shipping').change(function(){
		PaymillPayButtonCalcTotal();
	});
	
	PaymillPayButtonCalcTotal();

});