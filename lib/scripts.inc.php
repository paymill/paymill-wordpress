<?php

	// load admin scripts
	add_action('admin_enqueue_scripts', 'paymill_load_admin_scripts');
	function paymill_load_admin_scripts($hook) {
		if(isset($_GET['tab']) && !in_array($_GET['tab'],$GLOBALS['paymill_settings']->setting_keys)){
			return;
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_admin_scripts'); // benchmark
		wp_enqueue_script('paymill_admin.js', PAYMILL_PLUGIN_URL.'lib/js/paymill_admin.js', array('jquery'), PAYMILL_VERSION);
		wp_enqueue_script('jquery.dctooltip.1.0.js', PAYMILL_PLUGIN_URL.'lib/js/jquery.dctooltip.1.0.js', array('jquery'), PAYMILL_VERSION);
		wp_enqueue_script('jquery.hoverIntent.minified.js', PAYMILL_PLUGIN_URL.'lib/js/jquery.hoverIntent.minified.js', array('jquery'), PAYMILL_VERSION);
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_admin_scripts'); // benchmark
	}
	
	add_action('admin_init', 'paymill_load_admin_styles');
	function paymill_load_admin_styles() {
		/* Register our stylesheet. */
		wp_register_style( 'paymill_admin.css', plugins_url('/css/paymill_admin.css', __FILE__) );
	}
	
   function paymill_admin_styles() {
       /*
        * It will be called only on your plugin admin page, enqueue our stylesheet here
        */
       wp_enqueue_style( 'paymill_admin.css' );
   }
	
	// load frontend scripts
	
	// add this action when the payment form is viewed
	// add_action('wp_enqueue_scripts', 'paymill_load_frontend_scripts');
	// or if above doesn't work, try this:
	// paymill_load_frontend_scripts();
	
	function paymill_load_frontend_scripts(){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_frontend_scripts'); // benchmark
		wp_deregister_script(array('paymill_bridge','paymill_bridge_custom'));
		wp_enqueue_script('jquery.formatCurrency-1.4.0.js',PAYMILL_PLUGIN_URL.'lib/js/jquery.formatCurrency-1.4.0.js', array('jquery'), PAYMILL_VERSION);
		wp_enqueue_script('paymill_bridge', 'https://bridge.paymill.de/', array('jquery'), PAYMILL_VERSION);
		wp_localize_script('paymill_bridge', 'paymill_lang', array(
			'validateCardNumber'		=> esc_attr__('Invalid Credit Card Number', 'paymill'),
			'validateExpiry'			=> esc_attr__('Invalid Expiration Date', 'paymill'),
			'validateCvc'				=> esc_attr__('Invalid CVC', 'paymill'),
			'validateAccountNumber'		=> esc_attr__('Invalid Account Number', 'paymill'),
			'validateBankCode'			=> esc_attr__('Invalid Bank Code', 'paymill'),
			'validateIBAN'				=> esc_attr__('Invalid IBAN', 'paymill'),
			'validateBIC'				=> esc_attr__('Invalid BIC', 'paymill'),
			'decimalSymbol'				=> esc_attr__($GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'], 'paymill'),
			'digitGroupSymbol'			=> esc_attr__($GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands'], 'paymill'),
			'symbol'					=> esc_attr__($GLOBALS['paymill_settings']->paymill_pay_button_settings['currency'], 'paymill'),
		));
		wp_enqueue_script('paymill_bridge_custom', PAYMILL_PLUGIN_URL.'lib/js/paymill.js', array('paymill_bridge'), PAYMILL_VERSION);
		wp_enqueue_script('livevalidation', PAYMILL_PLUGIN_URL.'lib/js/livevalidation_standalone.compressed.js', array('paymill_bridge_custom'), PAYMILL_VERSION);
		wp_enqueue_script('livevalidation_custom', PAYMILL_PLUGIN_URL.'lib/js/livevalidation_custom.js', array('livevalidation'), PAYMILL_VERSION);
		wp_localize_script('livevalidation_custom', 'paymill_livevl', array(
			'wrongLength'				=> esc_attr__('Must be {is} characters long!', 'paymill'),
			'tooShort'					=> esc_attr__('Must not be less than {minimum} characters long!', 'paymill'),
			'tooLong'					=> esc_attr__('Must not be more than {maximum} characters long!', 'paymill'),
			'notANumber'				=> esc_attr__('Must be a number!', 'paymill'),
			'notAnInteger'				=> esc_attr__('Must be an integer!', 'paymill'),
			'wrongNumber'				=> esc_attr__('Must be {is}!', 'paymill'),
			'tooLow'					=> esc_attr__('Must not be less than {minimum}!', 'paymill'),
			'tooHigh'					=> esc_attr__('Must not be more than {maximum}!', 'paymill'),
			'notEmpty'					=> esc_attr__('Supply a value!', 'paymill'),
		));
		
		if(empty($GLOBALS['paymill_settings']->paymill_pay_button_settings['no_default_css']) || $GLOBALS['paymill_settings']->paymill_pay_button_settings['no_default_css'] != '1'){
			wp_enqueue_style('paymill', PAYMILL_PLUGIN_URL.'lib/css/paymill.css', false, PAYMILL_VERSION, false);
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_frontend_scripts'); // benchmark
	}
?>