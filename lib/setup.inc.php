<?php
	function paymill_install_webhooks(){
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		if(get_option('paymill_webhook_id') != false){
			// webhook exists?
			try{
				$GLOBALS['paymill_loader']->request_webhook->setId(get_option('paymill_webhook_id'));
				$GLOBALS['paymill_loader']->request_webhook->setUrl(get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway');
				$GLOBALS['paymill_loader']->request_webhook->setActive(true);
				$GLOBALS['paymill_loader']->request_webhook->setEventTypes(array(
					'subscription.created',
					'subscription.deleted',
					'subscription.failed',
					'subscription.succeeded'
				));
				$webhook = $GLOBALS['paymill_loader']->request->update($GLOBALS['paymill_loader']->request_webhook);
				update_option('paymill_webhook_id', $webhook->getId());
				
				return __('Webhook successfully updated.', 'paymill');
			}catch(Exception $e){
				// cache outdated
			}
			
			// cache is outdated, delete option
			delete_option('paymill_webhook_id');
			
			// retrieve orphaned webhooks containing our URL
			try{
				$GLOBALS['paymill_loader']->request_webhook->setFilter(array(
					'url' => get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway'
				));
				$GLOBALS['paymill_loader']->request_webhook = new $GLOBALS['paymill_loader']->request_webhook; // re-init class
				$webhooks = $GLOBALS['paymill_loader']->request->getAll($GLOBALS['paymill_loader']->request_webhook);
			}catch(Exception $e){
				echo __($e->getMessage(),'paymill');
			}
		}
		
		// still here? create new webhook
		try{
			$GLOBALS['paymill_loader']->request_webhook = new $GLOBALS['paymill_loader']->request_webhook; // re-init class
			$GLOBALS['paymill_loader']->request_webhook->setUrl(get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway');
			$GLOBALS['paymill_loader']->request_webhook->setActive(true);
			$GLOBALS['paymill_loader']->request_webhook->setEventTypes(array(
				'subscription.created',
				'subscription.deleted',
				'subscription.failed',
				'subscription.succeeded'
			));
			$webhook = $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_webhook);
			add_option('paymill_webhook_id', $webhook->getId());
			
			echo __('Webhook successfully created.', 'paymill');
		}catch(Exception $e){
			echo __($e->getMessage(),'paymill');
		}
		
		paymill_check_webhook();
	}
	
	function paymill_check_webhook(){
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		try{
			if(get_option('paymill_webhook_id') == false){
				return '<div>'.__('No Webhook created yet.','paymill').' '.__('Please insert API Keys in <strong>General Settings</strong> and submit the form.','paymill').'</div>';
			}else{
				$webhooks = $GLOBALS['paymill_loader']->request->getAll($GLOBALS['paymill_loader']->request_webhook);

				$webhook_found				= false;
				$nothing_found				= true;
				$output						= false;
				
				$output .= '<div><h3>'.__('Webhook Status Check','paymill').'</h3><h4>'.__('Please note that Webhooks are currently used in WooCommerce integration only.','paymill').'</h4>';
				
				if(count($webhooks) > 0){
					foreach($webhooks as $webhook){
						if(get_option('paymill_webhook_id') == $webhook['id']){
							$webhook_found	.= '
							<div>
								<div><strong>'.__('Saved Webhook found:','paymill').'</strong></div>
								<div><strong>'.__('ID:','paymill').' </strong> '.$webhook['id'].'</div>
								<div><strong>'.__('Livemode:','paymill').' </strong> '.($webhook['livemode'] ? __('yes','paymill') : __('no','paymill')).'</div>
								<div><strong>'.__('Active:','paymill').' </strong> '.($webhook['active'] ? 'yes' : 'no - <strong>please activate this webhook!</strong>').'</div>
								<div><strong>'.__('Event Types:','paymill').' </strong> '.implode(', ',$webhook['event_types']).'</div>
								<div><strong>'.__('URL:','paymill').' </strong> '.$webhook['url'].'</div>
';
							if($webhook['url'] != get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway'){
								$webhook_found	.= '<div><strong>'.__('Webhook URL is not correct!','paymill').'</strong> '.__('Please update the Webhook URL via saving Paymill API Keys again. The correct Webhook URL would be','paymill').' <em>'.get_site_url().'/?wc-api=WC_Gateway_Paymill_Gateway</em></div>';
							}elseif($webhook['active']){
								$webhook_found	.= '<div><strong>'.__('Status:').' </strong>'.__('It seems everything is correct.','paymill').'</div>';
							}
							$webhook_found	.= '</div>';
							
							$nothing_found = false;
						}
					}
				}
				
				// add webhook status
				if(!$webhook_found && $nothing_found){
					$output .= '<h3>'.__('No Webhook registered.').'</h3><div>'.__('Please insert API Keys in <strong>General Settings</strong> and submit the form.').'</div>';
				}elseif($webhook_found){
					$output .= $webhook_found;
				}

				$output .= '</div>';
				
				return $output;
			}
		}catch(Exception $e){
			return paymill_install_webhooks();
			//return __($e->getMessage(),'paymill');
		}
	}
	// install the tables
	register_activation_hook(__FILE__,'paymill_install');
	function paymill_install(){
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		global $wpdb;
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
		if($wpdb->get_var('SHOW TABLES LIKE '.$wpdb->prefix.'paymill_clients') == $wpdb->prefix.'paymill_clients') {
			$wpdb->query('ALTER TABLE '.$wpdb->prefix.'paymill_clients DROP INDEX paymill_client_id');
			//$wpdb->query('ALTER TABLE `'.$wpdb->prefix.'paymill_clients` ADD INDEX wp_member_id(`wp_member_id`)');
		}
	
$sql = 'CREATE TABLE '.$wpdb->prefix.'paymill_clients (
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_email varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_description longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  wp_member_id int(11) NOT NULL,
  UNIQUE KEY  wp_member_id (paymill_client_id,wp_member_id));';

$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_transactions (
  paymill_transaction_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_payment_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_transaction_time int(11) NOT NULL,
  paymill_transaction_data longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woocommerce_order_id int(11) NOT NULL,
  pay_button_order_id int(11) NOT NULL,
  shopplugin_order_id int(11) NOT NULL,
  marketpress_order_id int(11) NOT NULL,
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
