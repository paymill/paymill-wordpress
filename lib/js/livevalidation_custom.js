jQuery(document).ready(function () {
	if(typeof paymill_shop_name != 'undefined'){
		jQuery("body").on("click", "#paymill_payment_form", function() {
			var f0 = new LiveValidation('paymill_holdername', { validMessage: "✔", onlyOnBlur: true, failureMessage: paymill_livevl.notEmpty} );
			f0.add( Validate.Presence,{failureMessage: paymill_livevl.notEmpty} );

			var f1 = new LiveValidation('paymill_card_number', { validMessage: "✔", onlyOnBlur: true });
			var maximum = 16;
			f1.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f1.add( Validate.Length, { maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum), tooShortMessage:paymill_livevl.tooShort, tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f2 = new LiveValidation('paymill_card_cvc', { validMessage: "✔", onlyOnBlur: true });
			var maximum = 4;
			var minimum = 3;
			f2.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f2.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f3 = new LiveValidation('paymill_card_expiry_month', { validMessage: "✔", onlyOnBlur: true });
			var is = 2;
			f3.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f3.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );

			var f4 = new LiveValidation('paymill_card_expiry_year', { validMessage: "✔", onlyOnBlur: true });
			var is = 4;
			f4.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f4.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );

			var f5 = new LiveValidation('paymill_sepa_iban', { validMessage: "✔", onlyOnBlur: true });
			var minimum = 15;
			var maximum = 31;
			f5.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f6 = new LiveValidation('paymill_sepa_bic', { validMessage: "✔", onlyOnBlur: true });
			var minimum = 8;
			var maximum = 11;
			f6.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f5 = new LiveValidation('paymill_elv_number', { validMessage: "✔", onlyOnBlur: true });
			var minimum = 5;
			var maximum = 10;
			f5.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f6 = new LiveValidation('paymill_elv_bank_code', { validMessage: "✔", onlyOnBlur: true });
			var minimum = 8;
			var maximum = 11;
			f6.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );
		});
	}
});