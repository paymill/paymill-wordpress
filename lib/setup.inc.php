<?php
	function paymill_install_webhooks(){
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		// load setup routines
		try{
			if(get_option('paymill_webhook_id') == false){
				$GLOBALS['paymill_loader']->request_webhook->setUrl(get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway');
				$GLOBALS['paymill_loader']->request_webhook->setEventTypes(array(
					'subscription.created',
					'subscription.deleted',
					'subscription.failed',
					'subscription.succeeded'
				));
				$webhook = $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_webhook);
				add_option('paymill_webhook_id', $webhook->getId());
			}else{
				$GLOBALS['paymill_loader']->request_webhook->setId(get_option('paymill_webhook_id'));
				$GLOBALS['paymill_loader']->request_webhook->setUrl(get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway');
				$GLOBALS['paymill_loader']->request_webhook->setEventTypes(array(
					'subscription.created',
					'subscription.deleted',
					'subscription.failed',
					'subscription.succeeded'
				));
				$webhook = $GLOBALS['paymill_loader']->request->update($GLOBALS['paymill_loader']->request_webhook);
				update_option('paymill_webhook_id', $webhook->getId());
			}
		}catch(Exception $e){
			echo __($e->getMessage(),'paymill');
		}
	}
	// install the tables
	register_activation_hook(__FILE__,'paymill_install');
	function paymill_install(){
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		global $wpdb;
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
$sql = 'CREATE TABLE '.$wpdb->prefix.'paymill_clients (
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_email varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_description longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  wp_member_id int(11) NOT NULL,
  UNIQUE KEY paymill_client_id (paymill_client_id));';

$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_transactions (
  paymill_transaction_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_payment_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_transaction_time int(11) NOT NULL,
  paymill_transaction_data longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woocommerce_order_id int(11) NOT NULL,
  pay_button_order_id int(11) NOT NULL,
  shopplugin_order_id int(11) NOT NULL,
  UNIQUE KEY paymill_transaction_id (paymill_transaction_id));';
  
$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_cache_offers (
  `id` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `name` longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `amount` int(11) NOT NULL,
  `currency` longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `interval` longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `trial_period_days` int(11) NOT NULL,
  UNIQUE KEY id (id));';
  
$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_subscriptions (
  paymill_sub_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woo_user_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woo_offer_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  mgm_user_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  mgm_offer_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  UNIQUE KEY paymill_sub_id (paymill_sub_id));';
  
		dbDelta($sql);
		
		// get webhooks list
		/*$GLOBALS['paymill_loader']->request_webhook->setId(get_option('paymill_webhook_id'));
		$webhook = $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_webhook);*/
		

		
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
?>