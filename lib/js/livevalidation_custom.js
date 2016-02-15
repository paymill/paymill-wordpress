jQuery(document).ready(function () {/*
	if(typeof paymill_shop_name != 'undefined'){
		jQuery("body").on("click", "#paymill_payment_form", function() {
			if(jQuery('#paymill_form_paypal').not(':visible')){
				if(jQuery('#paymill_holdername_c').length > 0){
					var f0c = new LiveValidation('paymill_holdername_c', { validMessage: "✔", onlyOnBlur: true, failureMessage: paymill_livevl.notEmpty} );
					f0c.add( Validate.Presence,{failureMessage: paymill_livevl.notEmpty} );
				}
				if(jQuery('#paymill_holdername_s').length > 0){
					var f0s = new LiveValidation('paymill_holdername_s', { validMessage: "✔", onlyOnBlur: true, failureMessage: paymill_livevl.notEmpty} );
					f0s.add( Validate.Presence,{failureMessage: paymill_livevl.notEmpty} );
				}
				if(jQuery('#paymill_holdername_e').length > 0){
					var f0e = new LiveValidation('paymill_holdername_e', { validMessage: "✔", onlyOnBlur: true, failureMessage: paymill_livevl.notEmpty} );
					f0e.add( Validate.Presence,{failureMessage: paymill_livevl.notEmpty} );
				}

				if(jQuery('#paymill_form_credit').is(':visible')){
					if(jQuery('#paymill_card_number').length){
						var f1 = new LiveValidation('paymill_card_number', { validMessage: "✔", onlyOnBlur: true });
						var maximum = 16;
						f1.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
						f1.add( Validate.Length, { maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum), tooShortMessage:paymill_livevl.tooShort, tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );
					}

					if(jQuery('#paymill_card_cvc').length){
						var f2 = new LiveValidation('paymill_card_cvc', { validMessage: "✔", onlyOnBlur: true });
						var maximum = 4;
						var minimum = 3;
						f2.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
						f2.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );
					}

					if(jQuery('#paymill_card_expiry_month').length){
						var f3 = new LiveValidation('paymill_card_expiry_month', { validMessage: "✔", onlyOnBlur: true });
						var is = 2;
						f3.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
						f3.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );
					}

					if(jQuery('#paymill_card_expiry_year').length){
						var f4 = new LiveValidation('paymill_card_expiry_year', { validMessage: "✔", onlyOnBlur: true });
						var is = 4;
						f4.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
						f4.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );
					}
				}
				
				if(jQuery('#paymill_form_sepa').is(':visible')){
					var f5 = new LiveValidation('paymill_sepa_iban', { validMessage: "✔", onlyOnBlur: true });
					var minimum = 15;
					var maximum = 31;
					f5.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

					var f6 = new LiveValidation('paymill_sepa_bic', { validMessage: "✔", onlyOnBlur: true });
					var minimum = 8;
					var maximum = 11;
					f6.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );
				}

				if(jQuery('#paymill_form_elv').is(':visible')){
					var f5 = new LiveValidation('paymill_elv_number', { validMessage: "✔", onlyOnBlur: true });
					var minimum = 5;
					var maximum = 10;
					f5.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

					var f6 = new LiveValidation('paymill_elv_bank_code', { validMessage: "✔", onlyOnBlur: true });
					var minimum = 8;
					var maximum = 11;
					f6.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );
				}
			}
		});
	}*/
});