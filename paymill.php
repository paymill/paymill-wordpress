<?php
/*
Plugin Name: Paymill
Plugin URI: https://www.paymill.com
Description: Payments made eady
Version: 1.2
Author: Matthias Reuter / Elbnetz
Author URI: http://elbnetz.com
*/

	/*
		common information
	*/
	define('PAYMILL_VERSION',1200);
	define('PAYMILL_DIR',WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/');
	$GLOBALS['paymill_active'] = false;

	/*
		service mode
	*/
	if(file_exists(PAYMILL_DIR.'lib/debug/PHP_errors.log')){
		error_reporting(E_ALL);
		ini_set('log_errors',1);
		ini_set('error_log',PAYMILL_DIR.'lib/debug/PHP_errors.log');
		define('ipbwi_BENCHMARK',true);
	}
	

	/*
		load translation
	*/
	function paymill_init() {
		load_plugin_textdomain( 'paymill', false, dirname( plugin_basename( __FILE__ ) ). '/lib/translate/' );
	}
	add_action('plugins_loaded', 'paymill_init');
	
	/*
		install the tables
	*/
function paymill_install() {
	global $wpdb;
	global $jal_db_version;
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
$sql = 'CREATE TABLE '.$wpdb->prefix.'paymill_clients (
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_email varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_description varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  wp_member_id int(11) NOT NULL,
  PRIMARY KEY  ( paymill_client_id),
  KEY  paymill_client_email ( paymill_client_email));';

$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_transactions (
  paymill_transaction_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_payment_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_transaction_time int(11) NOT NULL,
  paymill_transaction_data varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  woocommerce_order_id int(11) NOT NULL,
  pay_button_order_id int(11) NOT NULL,
  shopplugin_order_id int(11) NOT NULL,
  PRIMARY KEY  ( paymill_transaction_id),
  KEY  paymill_payment_id ( paymill_payment_id));';
  
	dbDelta($sql);
	
	if(!get_option('paymill_db_version')){
		add_option('paymill_db_version', PAYMILL_VERSION);
	}elseif(get_option('paymill_db_version') != PAYMILL_VERSION){
		update_option('paymill_db_version', PAYMILL_VERSION);
	}
}

register_activation_hook(__FILE__,'paymill_install');

if(!get_option('paymill_db_version')){
	paymill_install();
}elseif(get_option('paymill_db_version') != PAYMILL_VERSION){
	paymill_install();
}

	/*
		load config
	*/	
	require_once('lib/config.inc.php');
	
	/*
		load admin scripts
	*/
	function pw_load_scripts($hook) {
		if(!in_array($_GET['tab'],$GLOBALS['paymill_settings']->setting_keys)){
			return;
		}

		wp_enqueue_script( 'paymill_admin_js', plugins_url('/lib/js/paymill_admin.js',__FILE__ ), array('jquery'), PAYMILL_VERSION);
	}
	add_action('admin_enqueue_scripts', 'pw_load_scripts');
	
	/*
		load Paymill API
	*/
	require_once('lib/api/Transactions.php');
	require_once('lib/api/Clients.php');
	
	/*
		load payment forms
	*/
	require_once(PAYMILL_DIR.'lib/integration/pay_button.inc.php'); // pay button
	require_once(PAYMILL_DIR.'lib/integration/woocommerce.inc.php'); // WooCommerce
	
	 // ShopPlugin
	/*add_action('shopp_init', 'init_paymill_gateway_class_shopp');
	function init_paymill_gateway_class_shopp() {
		global $Shopp;
		
		// Add the module file to the registry
		$paymillModule = new ModuleFile(PAYMILL_DIR.'lib/integration/','shopplugin.inc.php');
		if ($paymillModule->addon)  $Shopp->Gateways->modules[$paymillModule->subpackage] = $paymillModule;
	}*/
	
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
			'symbol'					=> esc_attr__($GLOBALS['paymill_settings']->paymill_pay_button_settings['currency'], 'paymill'),
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
		));
		
		wp_enqueue_style('paymill', plugins_url( '/lib/css/paymill.css' , __FILE__ ), $deps, PAYMILL_VERSION, $media);
	}
	add_action('wp_enqueue_scripts', 'paymill_scripts');

?>