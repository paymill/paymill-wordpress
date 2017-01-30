<?php
/*
Plugin Name: PAYMILL GmbH
Plugin URI: https://www.paymill.com
Description: Payments made easy
Version: 1.11
Author: Matthias Reuter info@straightvisions.com
Author URI: http://elbnetz.com
*/

	// common information
	define('PAYMILL_VERSION',11070);
	define('PAYMILL_DIR',WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/');
	define('PAYMILL_PLUGIN_URL',str_replace('http:','',plugins_url( '' , __FILE__ ).'/'));
	$GLOBALS['paymill_active'] = false; // eCommerce channels will set Paymill as active later to prevent showing payment form twice on same page.

	// service mode
	if(file_exists(PAYMILL_DIR.'lib/debug/debug.log')){
		// query logging
		// define('SAVEQUERIES', true);
		
		// benchmarking
		define('paymill_BENCHMARK', false);
		// todo: create benchmark switch in settings
		/*define('paymill_BENCHMARK', true);
		require_once(PAYMILL_DIR.'lib/benchmark.inc.php');
		paymill_doBenchmark(false,'init'); // start benchmark*/
	}else{
		define('paymill_BENCHMARK', false);
	}

	// load translation
	add_action('plugins_loaded', 'paymill_init');
	function paymill_init(){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_load_translation'); // benchmark
		
		$domain = 'paymill';
		// The "plugin_locale" filter is also used in load_plugin_textdomain()
		$locale = apply_filters('plugin_locale', get_locale(), $domain);
		$custom_lang_dir = WP_LANG_DIR.'/'.$domain.'/'.$domain.'-'.$locale.'.mo';
		load_textdomain($domain, $custom_lang_dir);
		load_plugin_textdomain($domain, FALSE, dirname(plugin_basename(__FILE__)). '/lib/translate/');
		
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
	require_once(PAYMILL_DIR.'lib/integration/woocommerce.inc.php'); // WooCommerce
	require_once(PAYMILL_DIR.'lib/integration/pay_button.inc.php'); // Pay Button
	
	add_action('init', 'paymill_load_cart66',11);
	add_action('admin_init', 'paymill_load_cart66',11);
	function paymill_load_cart66(){
		require_once(PAYMILL_DIR.'lib/integration/cart66.inc.php'); // Cart66
	}
	
	add_action('mp_load_gateway_plugins', 'paymill_load_marketpress');
	function paymill_load_marketpress(){
		require_once(PAYMILL_DIR.'lib/integration/marketpress.inc.php'); // Marketpress
	}
	
	if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_load_integration_classes'); // benchmark
	
	// shutdown
	if(paymill_BENCHMARK) add_action('shutdown', 'paymill_shutdownBenchmark'); // finish benchmark
?>