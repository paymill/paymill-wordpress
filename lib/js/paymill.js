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
		if(jQuery('#paymill_form_credit').is(':visible')){
			if (false == paymill.validateCardNumber(jQuery('#paymill_card_number').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateCardNumber);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}

			if (false == paymill.validateExpiry(jQuery('#paymill_card_expiry_month').val(), jQuery('#paymill_card_expiry_year').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateExpiry);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}
			
			if (false == paymill.validateCvc(jQuery('#paymill_card_cvc').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateCvc);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}
			
			paymill.createToken({
				number:jQuery('#paymill_card_number').val(),
				exp_month:jQuery('#paymill_card_expiry_month').val(),
				exp_year:jQuery('#paymill_card_expiry_year').val(),
				cvc:jQuery('#paymill_card_cvc').val(),
				cardholder:jQuery('#paymill_holdername').val(),
				amount_int:jQuery('.paymill_amount').val(),
				currency:jQuery('.paymill_currency').val()
			}, function (error, result) {
				if(error){
					// shows error
					jQuery(".paymill_payment_errors").text(error.apierror);
					jQuery(paymill_form_checkout_submit_id).show();
				}else{
					jQuery(".paymill_payment_errors").text("");
					var form = jQuery(paymill_form_checkout_id);

					// insert token into form
					var token = result.token;
					form.append("<input type='hidden' name='paymillToken' value='" + token + "'/>");
					form.submit();
				}
				if(paymill_shop_name == 'shopplugin'){
					jQuery(paymill_form_checkout_submit_id).show();
				}
			});
		}else if(jQuery('#paymill_form_sepa').is(':visible')){
			if (false == paymill.validateIban(jQuery('#paymill_sepa_iban').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateIBAN);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}

			if (false == paymill.validateBic(jQuery('#paymill_sepa_bic').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateBIC);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}
			
			paymill.createToken({
				iban:jQuery('#paymill_sepa_iban').val(),
				bic:jQuery('#paymill_sepa_bic').val(),
				accountholder:jQuery('#paymill_holdername').val(),
				amount_int:jQuery('.paymill_amount').val(),
				currency:jQuery('.paymill_currency').val()
			}, function (error, result) {
				if (error) {
					// shows error
					alert(error.apierror);
					jQuery(".paymill_payment_errors").text(error.apierror);
					jQuery(paymill_form_checkout_submit_id).show();
				} else {
					jQuery(".paymill_payment_errors").text("");
					var form = jQuery(paymill_form_checkout_id);

					// insert token into form
					var token = result.token;
					form.append("<input type='hidden' name='paymillToken' value='" + token + "'/>");
					form.submit();
				}
			});
		}else if(jQuery('#paymill_form_elv').is(':visible')){
			if (false == paymill.validateAccountNumber(jQuery('#paymill_elv_number').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateAccountNumber);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}

			if (false == paymill.validateBankCode(jQuery('#paymill_elv_bank_code').val())) {
				jQuery(".paymill_payment_errors").text(paymill_lang.validateBankCode);
				jQuery(paymill_form_checkout_submit_id).show();
				return false;
			}
			
			paymill.createToken({
				number:jQuery('#paymill_elv_number').val(),
				bank:jQuery('#paymill_elv_bank_code').val(),
				accountholder:jQuery('#paymill_holdername').val(),
				amount_int:jQuery('.paymill_amount').val(),
				currency:jQuery('.paymill_currency').val()
			}, function (error, result) {
				if (error) {
					// shows error
					jQuery(".paymill_payment_errors").text(error.apierror);
					jQuery(paymill_form_checkout_submit_id).show();
				} else {
					jQuery(".paymill_payment_errors").text("");
					var form = jQuery(paymill_form_checkout_id);

					// insert token into form
					var token = result.token;
					form.append("<input type='hidden' name='paymillToken' value='" + token + "'/>");
					form.submit();
				}
			});
		}
		return false;
	}
	
	// display credit cart icons
	jQuery('body').on('change', '#paymill_card_number', function() {
		var cc_type = paymill.cardType(jQuery('#paymill_card_number').val());

		if(cc_type == 'Visa'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -240px");
		}else if(cc_type == 'MasterCard'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -210px");
		}else if(cc_type == 'American Express'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -180px");
		}else if(cc_type == 'Diners Club'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -150px");
		}else if(cc_type == 'Discover'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -120px");
		}else if(cc_type == 'JCB'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -90px");
		}else if(cc_type == 'Maestro'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -60px");
		}else if(cc_type == 'UnionPay'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 -30px");
		}else if(cc_type == 'cb'){
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 0");
		}else{
			jQuery('#paymill_card_number').show().css("backgroundPosition","0 30px");
		}
	});

	// Payment Type Form Switcher
	jQuery('body').on('click', '#paymill_form_switch_credit', function() {
		jQuery('#paymill_form_elv').hide('slow');
		jQuery('#paymill_form_switch_elv').removeClass('paymill_form_switch_active');
		jQuery('#paymill_form_sepa').hide('slow');
		jQuery('#paymill_form_switch_sepa').removeClass('paymill_form_switch_active');
		
		jQuery('#paymill_form_credit').show('slow');
		jQuery('#paymill_form_switch_credit').addClass('paymill_form_switch_active');
	});
	jQuery('body').on('click', '#paymill_form_switch_sepa', function() {
		jQuery('#paymill_form_credit').hide('slow');
		jQuery('#paymill_form_switch_credit').removeClass('paymill_form_switch_active');
		jQuery('#paymill_form_elv').hide('slow');
		jQuery('#paymill_form_switch_elv').removeClass('paymill_form_switch_active');
		
		jQuery('#paymill_form_sepa').show('slow');
		jQuery('#paymill_form_switch_sepa').addClass('paymill_form_switch_active');
	});
	jQuery('body').on('click', '#paymill_form_switch_elv', function() {
		jQuery('#paymill_form_credit').hide('slow');
		jQuery('#paymill_form_switch_credit').removeClass('paymill_form_switch_active');
		jQuery('#paymill_form_sepa').hide('slow');
		jQuery('#paymill_form_switch_sepa').removeClass('paymill_form_switch_active');
		
		jQuery('#paymill_form_elv').show('slow');
		jQuery('#paymill_form_switch_elv').addClass('paymill_form_switch_active');
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