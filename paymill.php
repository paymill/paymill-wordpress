<?php
/*
Plugin Name: Paymill
Plugin URI: https://www.paymill.com
Description: Payments made eady
Version: 1.4.2
Author: Matthias Reuter / Elbnetz
Author URI: http://elbnetz.com
*/
/*
add_action( 'plugins_loaded', 'paymill_test' );
function paymill_test(){
WC_Subscriptions_Manager::cancel_subscription('1', '144_91');
}*/
	/*
		common information
	*/
	define('PAYMILL_VERSION',1402);
	define('PAYMILL_DIR',WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/');
	$GLOBALS['paymill_active'] = false;

	/*
		service mode
	*/
	if(file_exists(PAYMILL_DIR.'lib/debug/PHP_errors.log')){
		error_reporting(E_ALL ^ E_NOTICE);
		ini_set('log_errors',1);
		ini_set('display_errors',0); 
		ini_set('error_log',PAYMILL_DIR.'lib/debug/PHP_errors.log');
	}

	/*
		load translation
	*/
	function paymill_init() {
		load_plugin_textdomain( 'paymill', false, dirname( plugin_basename( __FILE__ ) ). '/lib/translate/' );
	}
	add_action('plugins_loaded', 'paymill_init');
	
	register_activation_hook(__FILE__,'paymill_install');

	/*
		load Paymill API
	*/
	require_once(PAYMILL_DIR.'lib/api/Transactions.php');
	require_once(PAYMILL_DIR.'lib/api/Clients.php');
	require_once(PAYMILL_DIR.'lib/api/Webhooks.php');
	require_once(PAYMILL_DIR.'lib/integration/subscriptions.inc.php');

	/*
		load config
	*/	
	require_once('lib/config.inc.php');
	
	/* gather source info for security purposes and optimization */
	$GLOBALS['paymill_source'] = array(
		'wordpress_version'			=> get_bloginfo('version'),
		'paymill_version'			=> PAYMILL_VERSION
	);
	
	/*
		install the tables
	*/
	function paymill_install() {
		global $wpdb;
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
$sql = 'CREATE TABLE '.$wpdb->prefix.'paymill_clients (
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_email varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_description longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  wp_member_id int(11) NOT NULL,
  PRIMARY KEY  ( paymill_client_id),
  KEY  paymill_client_email ( paymill_client_email));';

$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_transactions (
  paymill_transaction_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_payment_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_transaction_time int(11) NOT NULL,
  paymill_transaction_data longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woocommerce_order_id int(11) NOT NULL,
  pay_button_order_id int(11) NOT NULL,
  shopplugin_order_id int(11) NOT NULL,
  PRIMARY KEY  ( paymill_transaction_id),
  KEY  paymill_payment_id ( paymill_payment_id));';
  
$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_cache (
  cache_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  cache_content longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  UNIQUE KEY  cache_id ( cache_id));';
  
$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_subscriptions (
  paymill_sub_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woo_user_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woo_offer_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  UNIQUE KEY  paymill_sub_id ( paymill_sub_id));';
  
		dbDelta($sql);
		
		// paymill webhooks
		
		// get webhooks list
		$srv = new Services_Paymill_Webhooks($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'],$GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);

		$webhook = $srv->getOne(get_option('paymill_webhook_id'));
		
		if(!$webhook){
			$webhook = $srv->create(array(
				'url'         => get_site_url().'/?paymill_webhook=1',
				'event_types' => array('subscription.deleted')
			));
		
			add_option('paymill_webhook_id', $webhook['id']);
		}
		
		if(!get_option('paymill_db_version')){
			add_option('paymill_db_version', PAYMILL_VERSION);
		}elseif(get_option('paymill_db_version') != PAYMILL_VERSION){
			update_option('paymill_db_version', PAYMILL_VERSION);
		}
	}

	if(!get_option('paymill_db_version')){
		paymill_install();
	}elseif(get_option('paymill_db_version') != PAYMILL_VERSION){
		paymill_install();
	}

	/*
		load admin scripts
	*/
	function pw_load_scripts($hook) {
		if(isset($_GET['tab']) && !in_array($_GET['tab'],$GLOBALS['paymill_settings']->setting_keys)){
			return;
		}

		wp_enqueue_script( 'paymill_admin_js', plugins_url('/lib/js/paymill_admin.js',__FILE__ ), array('jquery'), PAYMILL_VERSION);
	}
	add_action('admin_enqueue_scripts', 'pw_load_scripts');
	
	/*
		load payment forms
	*/
	require_once(PAYMILL_DIR.'lib/integration/pay_button.inc.php'); // pay button
	require_once(PAYMILL_DIR.'lib/integration/woocommerce.inc.php'); // WooCommerce

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