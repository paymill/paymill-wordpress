<?php
/*
Plugin Name: Paymill
Plugin URI: https://www.paymill.com
Description: Payments made easy
<<<<<<< HEAD
<<<<<<< HEAD
Version: 1.6.0
Author: Matthias Reuter info@straightvisions.com
=======
=======
>>>>>>> ff593a5371bcaa3080ee669f96c8ceeeff9df6e4
Version: 1.5.2
Author: Matthias Reuter / Elbnetz
>>>>>>> ff593a5371bcaa3080ee669f96c8ceeeff9df6e4
Author URI: http://elbnetz.com
*/

	// common information
	define('PAYMILL_VERSION',1600);
	define('PAYMILL_DIR',WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/');
	define('PAYMILL_PLUGIN_URL',plugins_url( '' , __FILE__ ).'/');
	$GLOBALS['paymill_active'] = false; // eCommerce channels will set Paymill as active later to prevent showing payment form twice on same page.

	// service mode
	if(file_exists(PAYMILL_DIR.'lib/debug/PHP_errors.log')){
		// error logging
		error_reporting(E_ALL ^ E_NOTICE);
		ini_set('log_errors',1);
		ini_set('display_errors',0); 
		ini_set('error_log',PAYMILL_DIR.'lib/debug/PHP_errors.log');
		
		// query logging
		define('SAVEQUERIES', true);
		
		// benchmarking
		define('paymill_BENCHMARK', true);
		require_once(PAYMILL_DIR.'lib/benchmark.inc.php');
		paymill_doBenchmark(false,'init'); // start benchmark
	}else{
		define('paymill_BENCHMARK', false);
	}

	// load translation
	add_action('plugins_loaded', 'paymill_init');
	function paymill_init(){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_translation'); // benchmark
		load_plugin_textdomain('paymill', false, dirname(plugin_basename(__FILE__)). '/lib/translate/');
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_translation'); // benchmark
	}

	// load config
	if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_config'); // benchmark
	require_once(PAYMILL_DIR.'lib/config.inc.php');
	if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_config'); // benchmark
	
	// load the Paymill API
	require_once(PAYMILL_DIR.'lib/loader.inc.php');
	function load_paymill(){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_API'); // benchmark
		if(!isset($GLOBALS['paymill_loader']) || get_class($GLOBALS['paymill_loader']) != 'paymill_loader'){
			$GLOBALS['paymill_loader'] = new paymill_loader();
		}
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_API'); // benchmark
	}
	
	// this function-call can and should be used whenever working with Paymill API:
	// load_paymill();
	
	// Example call when working with Paymill API directly:
	//var_dump($GLOBALS['paymill_loader']->request->getAll($GLOBALS['paymill_loader']->request_client));
	
	// Load Wrapper Classes - you may use them or not, but they make my life easier
	if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_subscription_wrapper'); // benchmark
	require_once(PAYMILL_DIR.'lib/integration/subscriptions.inc.php');
	if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_subscription_wrapper'); // benchmark
	
	// load setup routines
	if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_setup'); // benchmark
	require_once(PAYMILL_DIR.'lib/setup.inc.php');
	if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_setup'); // benchmark
	
	// load scripts
	if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_scripts'); // benchmark
	require_once(PAYMILL_DIR.'lib/scripts.inc.php');
	if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_scripts'); // benchmark
	
	// load integration classes
	if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_integration_classes'); // benchmark
	require_once(PAYMILL_DIR.'lib/integration/pay_button.inc.php'); // pay button
	require_once(PAYMILL_DIR.'lib/integration/woocommerce.inc.php'); // WooCommerce
<<<<<<< HEAD
	if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_integration_classes'); // benchmark
	
	// shutdown
	if(paymill_BENCHMARK) add_action('shutdown', 'paymill_shutdownBenchmark'); // finish benchmark
?>
=======

	function paymill_scripts(){
		wp_deregister_script(array('paymill_bridge','paymill_bridge_custom'));
		wp_enqueue_script('jquery.formatCurrency-1.4.0.js', plugins_url( '/lib/js/jquery.formatCurrency-1.4.0.js' , __FILE__ ), array('jquery'), PAYMILL_VERSION);
		wp_enqueue_script('paymill_bridge', 'https://bridge.paymill.de/', array('jquery'), PAYMILL_VERSION);
		wp_localize_script('paymill_bridge', 'paymill_lang', array(
			'validateCardNumber'		=> esc_attr__('Invalid Credit Card Number', 'paymill'),
			'validateExpiry'			=> esc_attr__('Invalid Expiration Date', 'paymill'),
			'validateCvc'				=> esc_attr__('Invalid CVC', 'paymill'),
			'validateAccountNumber'		=> esc_attr__('Invalid Account Number', 'paymill'),
			'validateBankCode'			=> esc_attr__('Invalid Bank Code', 'paymill'),
			'decimalSymbol'				=> esc_attr__($GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'], 'paymill'),
			'digitGroupSymbol'			=> esc_attr__($GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands'], 'paymill'),
			'symbol'					=> esc_attr__($GLOBALS['paymill_settings']->paymill_general_settings['currency'], 'paymill'),
		));
		wp_enqueue_script('paymill_bridge_custom', plugins_url( '/lib/js/paymill.js' , __FILE__ ), array('paymill_bridge'), PAYMILL_VERSION);
		wp_enqueue_script('livevalidation', plugins_url( '/lib/js/livevalidation_standalone.compressed.js' , __FILE__ ), array('paymill_bridge_custom'), PAYMILL_VERSION);
		wp_enqueue_script('livevalidation_custom', plugins_url( '/lib/js/livevalidation_custom.js' , __FILE__ ), array('livevalidation'), PAYMILL_VERSION);
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
			wp_enqueue_style('paymill', plugins_url( '/lib/css/paymill.css' , __FILE__ ), false, PAYMILL_VERSION, false);
		}
	}
	add_action('wp_enqueue_scripts', 'paymill_scripts');

?>
<<<<<<< HEAD
>>>>>>> ff593a5371bcaa3080ee669f96c8ceeeff9df6e4
=======
>>>>>>> ff593a5371bcaa3080ee669f96c8ceeeff9df6e4
