jQuery(document).ready(function () {
	if(typeof paymill_shop_name != 'undefined'){
		jQuery("body").on("click", "#payment", function() {
			var f1 = new LiveValidation('card-number');
			var maximum = 16;
			f1.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f1.add( Validate.Length, { maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum), tooShortMessage:paymill_livevl.tooShort, tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f2 = new LiveValidation('card-cvc');
			var maximum = 4;
			var minimum = 3;
			f2.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f2.add( Validate.Length, { minimum: minimum, maximum: maximum, wrongLengthMessage:paymill_livevl.wrongLength.replace('{maximum}',maximum).replace('{minimum}',minimum), tooShortMessage:paymill_livevl.tooShort.replace('{minimum}',minimum), tooLongMessage:paymill_livevl.tooLong.replace('{maximum}',maximum) } );

			var f3 = new LiveValidation('card-expiry-month');
			var is = 2;
			f3.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f3.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );

			var f4 = new LiveValidation('card-expiry-year');
			var is = 4;
			f4.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f4.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );

			var f5 = new LiveValidation('transaction-form-account');
			f5.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			
			var f6 = new LiveValidation('transaction-form-code');
			var is = 8;
			f6.add( Validate.Numericality, { notANumberMessage:paymill_livevl.notANumber, notAnIntegerMessage:paymill_livevl.notAnInteger, wrongNumberMessage:paymill_livevl.wrongNumber, tooLowMessage:paymill_livevl.tooLow, Message:paymill_livevl.tooHigh } );
			f6.add( Validate.Length, { is: is, wrongLengthMessage:paymill_livevl.wrongLength.replace('{is}',is), tooShortMessage:paymill_livevl.tooShort.replace('{is}',is), tooLongMessage:paymill_livevl.tooLong.replace('{is}',is) } );
		});
	}
});