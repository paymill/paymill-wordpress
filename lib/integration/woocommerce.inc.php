<?php

	/**
	 * @param $msg
	 * @param $replacement,...
	 */
	function paymill_log_debug($msg, $replacement) {
		$args = func_get_args();
		$msg = array_shift($args);
		// PATRICK printf tends to misbehave => suppress error
		error_log("\n\n" . @vsprintf($msg, $args), 3, PAYMILL_DIR.'lib/debug/debug.log');
	}

	// PAYMILL Payment Class
	if(!function_exists('paymill_woocommerce_errorHandling')){
		function paymill_woocommerce_errorHandling($errors){
			if(!defined('PAYMILL_WOO_NOTICES_SENT')){
				foreach($errors as $error){
					if(function_exists('wc_add_notice')){
						wc_add_notice('<div class="paymill_error">'.$error.'</div>', 'error' );
					}else{
						echo '<div class="paymill_error">'.$error.'</div>';
					}
				}
				define('PAYMILL_WOO_NOTICES_SENT',true);
			}
		}
	}
	if(!function_exists('paymill_woocommerce_errorJustReturn')){
		function paymill_woocommerce_errorJustReturn($errors){
			$output = '';
			foreach($errors as $error){
				$output .= $error."<br /><br />";
			}
			return $output;
		}
	}

	// HOOKED FUNCTIONS FROM PAYMILL WEBHOOKS
	/*
	function paymill_webhook_transaction_get_order($event_json){
		global $wpdb;
		
		error_log("\n\nTrying to get order by given event resource ID '".$event_json['event']['event_resource']['id']."'\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		
		// retrieve transaction ID
		if(isset($event_json['event']['event_resource']['id']) && strlen($event_json['event']['event_resource']['id']) > 0){
			$paymill_transaction_id			= $event_json['event']['event_resource']['id'];
			
			if($paymill_transaction_id){
				$sql = $wpdb->prepare('SELECT woocommerce_order_id FROM '.$wpdb->prefix.'paymill_transactions WHERE paymill_transaction_id=%s',
				array(
					$paymill_transaction_id
				));
				
				$tran_cache			= $wpdb->get_var($sql);
				
				if($tran_cache){
					$order = new WC_Order($tran_cache);
					
					if($order){
						return $order;
					}else{
						return false;
					}
				}else{
					return false;
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
	}*/
	function paymill_webhook_subscription_get_order_by_transaction_event($event_json){
		global $wpdb;
		error_log("\n\nmethod paymill_webhook_subscription_get_order_by_transaction_event started\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		
		// retrieve subscription ID
		if(isset($event_json['event']['event_resource']['description']) && strlen($event_json['event']['event_resource']['description']) > 0 &&
			(function_exists('wcs_get_subscription') || function_exists('wcs_get_subscription_from_key'))
		){
			// explode if format is e.g. "Subscription#sub_dc11f28692123a0d8b0c woo_5874_194ee562f973a7fddb14565e8ef4bd84"
			preg_match_all("/#(\d+)/", $event_json['event']['event_resource']['description'], $paymill_sub_desc);
			$woo_offer_id				= $paymill_sub_desc[1][0];
			$paymill_sub_customer_id	= $paymill_sub_desc[1][1];
			
			$subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($woo_offer_id) : false;
			
			// since WC-Subscriptions v2.0, they subscription key is deprecated, instead, subscription id will be used
			if(!$subscription){ // still subscription key
				error_log("\n\nNo subscription found, trying to retrieve it by subscription key... ", 3, PAYMILL_DIR.'lib/debug/debug.log');
			
				try{
					$subscription = function_exists('wcs_get_subscription_from_key') ? wcs_get_subscription_from_key($woo_offer_id) : function_exists('wcs_get_subscription_from_key');
					
					if($subscription){
						// update cache
						 $wpdb->update(
							$wpdb->prefix.'paymill_subscriptions',
							array('woo_offer_id'	=> $subscription->id),
							array('paymill_sub_id'	=> $paymill_sub_id),
							'%d',
							'%s'
						);
					}
				}
				catch(Exception $e) {
					error_log("\n\n".$e->getMessage()."\n\n".'Subscription could not be loaded via wcs_get_subscription_from_key, submitted key: '.$woo_offer_id.', retrieved by subid '.$paymill_sub_id, 3, PAYMILL_DIR.'lib/debug/debug.log');
				}
			}
			
			if($subscription){
				//$sub_object		= new WC_Subscription($sub_cache);
				//$order			= $sub_object->get_last_order('all');
				
				$order				= $subscription->get_last_order('all');
				
				if($order){
					return $order;
				}else{
					error_log("\n\norder not found.", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}else{
				error_log("\n\nsubscription not found.", 3, PAYMILL_DIR.'lib/debug/debug.log');
				return false;
			}
		}else{
			error_log("\n\npaymill sub id cannot be retrieved from json data", 3, PAYMILL_DIR.'lib/debug/debug.log');
			return false;
		}
	}
	function paymill_webhook_subscription_get_order($event_json){
		global $wpdb;
		
		// retrieve sub ID
		if(isset($event_json['event']['event_resource']['id']) && strlen($event_json['event']['event_resource']['id']) > 0){
			$paymill_sub_id			= $event_json['event']['event_resource']['id'];
		}elseif(isset($event_json['event']['event_resource']['subscription']['id']) && strlen($event_json['event']['event_resource']['subscription']['id']) > 0){
			$paymill_sub_id			= $event_json['event']['event_resource']['subscription']['id'];
		}
		
		error_log("\n\nPaymill sub ID: ".$paymill_sub_id, 3, PAYMILL_DIR.'lib/debug/debug.log');
		
		// get subscription info, if available
		if(isset($paymill_sub_id) && strlen($paymill_sub_id) > 0){
			$sql = $wpdb->prepare('SELECT woo_offer_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE paymill_sub_id=%s',
			array(
				$paymill_sub_id
			));
			
			$sub_cache			= $wpdb->get_var($sql);
			
			error_log("\n\nSub Cache: ".$sub_cache, 3, PAYMILL_DIR.'lib/debug/debug.log');
			
			$subscription = function_exists('wcs_get_subscription') ? wcs_get_subscription($sub_cache) : false;
			
			// since WC-Subscriptions v2.0, they subscription key is deprecated, instead, subscription id will be used
			if(!$subscription){ // still subscription key
				error_log("\n\nNo subscription found, trying to retrieve it by subscription key... ", 3, PAYMILL_DIR.'lib/debug/debug.log');
			
				try{
					// PATRICK this equals $x ? $y : $x => $x ? $y : false
					$subscription = function_exists('wcs_get_subscription_from_key') ? wcs_get_subscription_from_key($sub_cache) : function_exists('wcs_get_subscription_from_key');
					
					if($subscription){
						// update cache
						 $wpdb->update(
							$wpdb->prefix.'paymill_subscriptions',
							array('woo_offer_id'	=> $subscription->id),
							array('paymill_sub_id'	=> $paymill_sub_id),
							'%d',
							'%s'
						);
					}
				}
				catch(Exception $e) {
					error_log("\n\n".$e->getMessage()."\n\n".'Subscription could not be loaded via wcs_get_subscription_from_key, submitted key: '.$sub_cache.', retrieved by subid '.$paymill_sub_id, 3, PAYMILL_DIR.'lib/debug/debug.log');
				}
			}
			
			if($subscription){
				paymill_log_debug("Subscription found: \n%s", var_export($subscription, true));
				return $subscription;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	function paymill_webhook_subscription_created($subscription){
		$subscription->add_order_note(__('Subscription created via Paymill Webhook','paymill'));
	}
	function paymill_webhook_subscription_succeeded($subscription){
		error_log("\n\nmethod paymill_webhook_subscription_succeeded started\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');

		// prevent multiple subscription renewals because of multiple webhook attempts.
		$whole_period = 0;
		switch ($subscription->billing_period){
			case 'day':
			default:
			$whole_period = intval($subscription->billing_interval) * 86400;
			break;
			case 'week':
			$whole_period = intval($subscription->billing_interval) * 604800;
			break;
			case 'month':
			$whole_period = intval($subscription->billing_interval) * 2160000; // using 25 days to prevent problems with shorter months
			break;
			case 'year':
			$whole_period = intval($subscription->billing_interval) * 30240000; // using 350 days to prevent any timezone problems whatsoever
			break;
		}

		if($subscription->get_completed_payment_count() >= 1){
			paymill_log_debug('BRANCH A');
			paymill_log_debug('now %s vs last_payment %s', strtotime(date(DATE_RFC822)), strtotime($subscription->get_date('last_payment')) + $whole_period - 18000);
			// PATRICK $subscription->get_date('last_payment') also seems to count pending renewal orders created by WCS - these can never be paid??
			//if (strtotime(date(DATE_RFC822)) > strtotime($subscription->get_date('last_payment')) + $whole_period - 18000) { // minus 5 hours to prevent any problems with pending triggers
			//paymill_log_debug(var_export($subscription->get_last_order( 'all' ),true));
			
				//$renewal_order = wcs_create_renewal_order($subscription);
				//paymill_log_debug('Renewal Order:' . "\n", var_export($renewal_order, true));
				
				$renewal_order = $subscription->get_last_order( 'all' );
				if(isset($renewal_order) && is_object($renewal_order) && isset($renewal_order->post_status) && $renewal_order->post_status == 'wc-pending'){
					WC_Subscriptions_Manager::process_subscription_payments_on_order($renewal_order);
					$renewal_order->payment_complete();
				}elseif(strtotime(date(DATE_RFC822)) > strtotime($subscription->get_date('last_payment')) + $whole_period - 18000){
					$renewal_order = wcs_create_renewal_order($subscription);
					WC_Subscriptions_Manager::process_subscription_payments_on_order($renewal_order);
					$renewal_order->payment_complete();
				}
			
			//}
		}else{
			paymill_log_debug('BRANCH B');
			$renewal_order = wcs_create_renewal_order($subscription);
			$renewal_order->payment_complete();
			
			$subscription->update_status('active',__('Subscription succeeded via Paymill Webhook','paymill'));
			$subscription->add_order_note(__('Subscription succeeded via Paymill Webhook','paymill'));
		}
		try{
			//$subscription->update_dates(array('next_payment' => $subscription->calculate_date('next_payment')));
			
			// Recalculate and set next payment date
			$next_payment = $subscription->get_time('next_payment');

			// Make sure the next payment date is more than 2 hours in the future
			if($next_payment < ( gmdate( 'U' ) + 2 * HOUR_IN_SECONDS)){ // also accounts for a $next_payment of 0, meaning it's not set
				// PATRICK calculate_date() returns a formatted string, not a timestamp!
				$next_payment_date = $subscription->calculate_date('next_payment');
				paymill_log_debug('original %s', $next_payment_date);
				if($next_payment > 0){
					// PATRICK plz comment on why the hour is subtracted
					// (this will not work on < 5.3)
					$next_payment_date = DateTime::createFromFormat('Y-m-d H:i:s', $next_payment_date)
						->sub(DateInterval::createFromDateString('1 hour'))
						->format('Y-m-d H:i:s');
					paymill_log_debug('modified %s', $next_payment_date);

					$subscription->update_dates( array( 'next_payment' => $next_payment_date));
				}
			}
		}
		catch(Exception $e) {
			error_log("\n\n".$e->getMessage()."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		}
	}
	function paymill_webhook_subscription_deleted($subscription){
		global $wpdb;
		$sql = $wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',
		array(
			$subscription->id
		));
		$wpdb->query($sql);
		
		$subscription->update_status('cancelled',__('Subscription deleted via Paymill Webhook','paymill'));
	}
	function paymill_webhook_subscription_cancelled($subscription){
		$subscription->cancel_order(__('Subscription cancelled via Paymill Webhook','paymill'));
	}
	function paymill_webhook_subscription_failed($subscription){
		try{
			$subscription->payment_failed();
		}catch(Exception $e){
			$subscription->add_order_note('Paymill subscription payment failed: '.$e->getMessage());
		}
	}
	function paymill_webhook_transaction_failed($order){
		$order->update_status('failed', __('Payment via Paymill failed','paymill'));
	}
	function paymill_webhook_transaction_succeeded($order){
		if(method_exists($order, 'payment_complete') && !$order->has_status('completed') && !$order->has_status('processing')){
			try{
				error_log("\n\n".'Trying to complete the order.', 3, PAYMILL_DIR.'lib/debug/debug.log');
				$order->payment_complete();
			}
			catch(Exception $e){
				error_log("\n\n".'Failed to complete order: '.$e->getMessage(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				//$order->update_status('failed',$e->getMessage());
			}
		}else{
			error_log("\n\n".'Method may not exist', 3, PAYMILL_DIR.'lib/debug/debug.log');
		}
	}
	function paymill_webhooks_get_data(){
		$body = @file_get_contents('php://input');
		$event_json = json_decode($body, true);
		
		return $event_json;
	}
	function paymill_webhooks(){
		global $wpdb;
		
		$event_json = paymill_webhooks_get_data();
		
		// retrieve sub ID
		if(isset($event_json['event']['event_resource']['subscription']['id']) && strlen($event_json['event']['event_resource']['subscription']['id']) > 0){
			$paymill_sub_id			= $event_json['event']['event_resource']['subscription']['id'];
		}elseif(isset($event_json['event']['event_resource']['id']) && strlen($event_json['event']['event_resource']['id']) > 0){
			$paymill_sub_id			= $event_json['event']['event_resource']['id'];
		}
		
		error_log("\n\n########################################################################################################################\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		error_log(date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' (Resource-ID: '.$paymill_sub_id.') triggered - start processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		error_log("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		
		error_log("\n\n".var_export($event_json,true)."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
		error_log("\n\n".var_export(serialize($event_json),true)."\n\n", 3, PAYMILL_DIR.'lib/debug/event_json.log');
		
		
		// subscriptionb or transaction?
		if($event_json){
			if($event_json['event']['event_type'] == 'subscription.created'){ // subscription successfully created
				if($subscription = paymill_webhook_subscription_get_order($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'Current WC:subscription status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_subscription_created($subscription);
					
					error_log("\n\n".'New WC:subscription Status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - subscription not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}elseif($event_json['event']['event_type'] == 'subscription.succeeded'){ // tell WooCommerce when payment for subscription is successfully processed
				if($subscription = paymill_webhook_subscription_get_order($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'Current WC:subscription status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_subscription_succeeded($subscription);
					
					error_log("\n\n".'New WC:subscription Status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - subscription not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}elseif($event_json['event']['event_type'] == 'subscription.deleted'){ // cancel subscription, as it was deleted through Paymill dashboard
				if($subscription = paymill_webhook_subscription_get_order($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'Current WC:subscription status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_subscription_deleted($subscription);
					
					error_log("\n\n".'New WC:subscription Status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - subscription not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}elseif($event_json['event']['event_type'] == 'subscription.canceled'){
				if($subscription = paymill_webhook_subscription_get_order($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'Current WC:subscription status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_subscription_cancelled($subscription);
					
					error_log("\n\n".'New WC:subscription Status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - subscription not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}elseif($event_json['event']['event_type'] == 'subscription.failed'){
				if($subscription = paymill_webhook_subscription_get_order($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'Current WC:subscription status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_subscription_failed($subscription);
					
					error_log("\n\n".'New WC:subscription Status: '.$subscription->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - subscription not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}elseif($event_json['event']['event_type'] == 'transaction.failed'){
				if($order = paymill_webhook_subscription_get_order_by_transaction_event($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'Current WC:order status: '.$order->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_transaction_failed($order);
					
					error_log("\n\n".'New WC:order Status: '.$order->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - order not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}elseif($event_json['event']['event_type'] == 'transaction.succeeded'){
				if($order = paymill_webhook_subscription_get_order_by_transaction_event($event_json)){
					define('PAYMILL_WEBHOOK_ACTIVE',true);
					error_log("\n\n".'WC:order ID: '.$order->id."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					error_log("\n\n".'Current WC:order status: '.$order->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
					
					paymill_webhook_transaction_succeeded($order);
					
					error_log("\n\n".'New WC:order Status: '.$order->get_status(), 3, PAYMILL_DIR.'lib/debug/debug.log');
				}else{
					error_log("\n\n".date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - order not found - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					return false;
				}
			}
		}else{
			return;
		}
	}
	add_action('woocommerce_api_wc_gateway_paymill_gateway', 'paymill_webhooks');

	add_action('woocommerce_subscription_trash_paymill','woo_subscription_remove_paymill');
	add_action('woocommerce_subscription_cancelled_paymill','woo_subscription_cancel_paymill');
	add_action('woocommerce_subscription_activated_paymill','woo_subscription_unpause_paymill');
	add_action('woocommerce_subscription_on-hold_paymill','woo_subscription_pause_paymill');
	add_action('woocommerce_subscription_expired_paymill','woo_subscription_pause_paymill');
	add_action('woocommerce_subscription_pending-cancel_paymill','woo_subscription_pause_paymill');
	function woo_subscription_remove_paymill($subscription){
		if(!defined('PAYMILL_WEBHOOK_ACTIVE') && !$_POST['paymillToken']){
			global $wpdb;
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_woocommerce_errorJustReturn');
			
			$client_cache		= $wpdb->get_row($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',array($subscription->id)),ARRAY_A);

			if(!isset($client_cache['paymill_sub_id'])){
				$subscription->add_order_note(__('Subscription not removed in Paymill - not found in cache table. Please perform that action manually in Paymill Dashboard.','paymill'));
			}else{
				$subscriptions		= new paymill_subscriptions('woocommerce');
				$subscriptions->remove($client_cache['paymill_sub_id']);
				
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					$subscription->add_order_note(__('Subscription cannot be cancelled in Paymill','paymill').$GLOBALS['paymill_loader']->paymill_errors->getErrors());
				}else{
					$wpdb->query($wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',array($subscription->id)));
					$subscription->add_order_note(__('Subscription cancelled in Paymill','paymill'));
				}
			}
		}
	}
	function woo_subscription_cancel_paymill($subscription){
		if(!defined('PAYMILL_WEBHOOK_ACTIVE') && !$_POST['paymillToken']){
			global $wpdb;
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_woocommerce_errorJustReturn');
			
			$client_cache		= $wpdb->get_row($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',array($subscription->id)),ARRAY_A);

			if(!isset($client_cache['paymill_sub_id'])){
				$subscription->add_order_note(__('Subscription not cancelled in Paymill - not found in cache table. Please perform that action manually in Paymill Dashboard.','paymill'));
			}else{
				$subscriptions		= new paymill_subscriptions('woocommerce');
				$subscriptions->cancel($client_cache['paymill_sub_id']);
					
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					$subscription->add_order_note(__('Subscription cannot be cancelled in Paymill','paymill').$GLOBALS['paymill_loader']->paymill_errors->getErrors());
				}else{
					$wpdb->query($wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',array($subscription->id)));
					$subscription->add_order_note(__('Subscription cancelled in Paymill','paymill'));
				}
			}
		}
	}
	function woo_subscription_pause_paymill($subscription){
		if(!defined('PAYMILL_WEBHOOK_ACTIVE') && !$_POST['paymillToken']){
			global $wpdb;
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_woocommerce_errorJustReturn');

			$client_cache		= $wpdb->get_row($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',array($subscription->id)),ARRAY_A);

			if(!isset($client_cache['paymill_sub_id'])){
				$subscription->add_order_note(__('Subscription not paused in Paymill - not found in cache table. Please perform that action manually in Paymill Dashboard.','paymill'));
			}elseif($order->status == 'on-hold'){
				return; // all subs begin by being on-hold, so don't pause them again
			}else{
				$subscriptions		= new paymill_subscriptions('woocommerce');
				$subscriptions->pause($client_cache['paymill_sub_id']);
				
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					$subscription->add_order_note(__('Subscription cannot be paused in Paymill','paymill').$GLOBALS['paymill_loader']->paymill_errors->getErrors());
				}else{
					$subscription->add_order_note(__('Subscription paused in Paymill','paymill'));
				}
			}
		}
    }
	function woo_subscription_unpause_paymill($subscription){
		if(!defined('PAYMILL_WEBHOOK_ACTIVE') && !$_POST['paymillToken']){
			global $wpdb;
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_woocommerce_errorJustReturn');

			$client_cache		= $wpdb->get_row($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_offer_id=%s',array($subscription->id)),ARRAY_A);

			if(!isset($client_cache['paymill_sub_id'])){
				$subscription->add_order_note(__('Subscription not unpaused in Paymill - not found in cache table. Please perform that action manually in Paymill Dashboard.','paymill'));
			}else{
				$subscriptions		= new paymill_subscriptions('woocommerce');
				$subscriptions->unpause($client_cache['paymill_sub_id']);
				
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					$subscription->add_order_note(__('Subscription "'.$subscription->id.'" cannot be unpaused in Paymill ','paymill').$GLOBALS['paymill_loader']->paymill_errors->getErrors());
				}else{
					$subscription->add_order_note(__('Subscription unpaused in Paymill','paymill'));
				}
			}
		}
	}
	function add_paymill_gateway_class($methods){
		$methods[] = 'WC_Gateway_Paymill_Gateway'; 
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_paymill_gateway_class');
	function init_paymill_gateway_class(){
		global $wpdb;

		if(class_exists('WC_Payment_Gateway')){
			class WC_Gateway_Paymill_Gateway extends WC_Payment_Gateway{
			
				private $cart					= false;
				private $order_id				= false;
				private $order					= false;
				private $subscriptions			= false;
				private $offers					= false;
				private $order_desc				= '';
				public $has_fields				= true;
			
				public function __construct(){
					load_paymill(); // this function-call can and should be used whenever working with Paymill API
					$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_woocommerce_errorHandling');
					$GLOBALS['paymill_source']['woocommerce_version'] = ((isset($GLOBALS['woocommerce']) && is_object($GLOBALS['woocommerce']) && isset($GLOBALS['woocommerce']->version)) ? $GLOBALS['woocommerce']->version : 0);
					
					$this->id					= 'paymill';
					$this->icon					= plugins_url('',__FILE__ ).'/../img/icon.png';
					$this->logo					= plugins_url('',__FILE__ ).'/../img/logo.png';
					$this->logo_small			= plugins_url('',__FILE__ ).'/../img/logo_small.png';

					$this->has_fields			= true;
					
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
					//add_action('woocommerce_api_wc_gateway_paymill_gateway', array($this,'process_payment_paypal'));
					add_action('woocommerce_before_checkout_form', function(){ echo '<div class="paymill_payment_errors" id="paymill_payment_errors"></div>'; });
				
					$this->has_fields = true;
					$this->init_form_fields();
					$this->init_settings();
					
					$this->title				= $this->settings['title'];
					$this->description			= $this->settings['description'];
					
					$this->supports = array(
						'products',
						'subscriptions',
						'subscription_cancellation',
						'subscription_suspension',
						'subscription_reactivation',
						//'multiple_subscriptions',
						//'subscription_amount_changes',
						//'subscription_date_changes',
						//'subscription_payment_method_change'
					);
				}
				/*
				public function prepare_payment_paypal(){
					$GLOBALS['paymill_loader']->request_checksum->setClient($this->clientClass->getCurrentClientID());
					$GLOBALS['paymill_loader']->request_checksum->setChecksumType('paypal');
					if(function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($this->order) && $this->order_get_total() == 0){
						$GLOBALS['paymill_loader']->request_checksum->setChecksumAction('payment');
					}else{
						$GLOBALS['paymill_loader']->request_checksum->setChecksumAction('transaction');
					}
					$GLOBALS['paymill_loader']->request_checksum->setCurrency($this->get_currency());
					$GLOBALS['paymill_loader']->request_checksum->setDescription($this->order_desc);
					$redirect_url		= '/wc-api/WC_Gateway_Paymill_Gateway?order_id='.$this->order_id.'&billing_first_name='.urlencode($_POST['billing_first_name']).'&billing_last_name='.urlencode($_POST['billing_last_name']).'&billing_email='.urlencode($_POST['billing_email']);
					error_log("\n\n### ".$redirect_url." \n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					$GLOBALS['paymill_loader']->request_checksum->setReturnUrl(site_url($redirect_url));
					$GLOBALS['paymill_loader']->request_checksum->setCancelUrl($this->order->get_cancel_order_url());
					
					if(isset($_POST['shipping_first_name']) && strlen($_POST['shipping_first_name']) > 0){
						$GLOBALS['paymill_loader']->request_checksum->setShippingAddress(array(
							'name'								=> $_POST['shipping_first_name'].' '.$_POST['shipping_last_name'].', '.$_POST['billing_company'],
							'street_address'					=> $_POST['shipping_address_1'],
							'street_address_addition'			=> ((isset($_POST['shipping_address_2']) && strlen($_POST['shipping_address_2']) > 0) ? $_POST['shipping_address_2'] : NULL),
							'city'								=> $_POST['shipping_city'],
							//'state'								=> $_POST[''],
							'postal_code'						=> $_POST['shipping_postcode'],
							'country'							=> $_POST['shipping_country'],
							'phone'								=> $_POST['shipping_phone'],
						));
					}
					$GLOBALS['paymill_loader']->request_checksum->setBillingAddress(array(
						'name'								=> $_POST['billing_first_name'].' '.$_POST['billing_last_name'].', '.$_POST['shipping_company'],
						'street_address'					=> $_POST['billing_address_1'],
						'street_address_addition'			=> ((isset($_POST['billing_address_2']) && strlen($_POST['billing_address_2']) > 0) ? $_POST['billing_address_2'] : NULL),
						'city'								=> $_POST['billing_city'],
						//'state'								=> $_POST[''],
						'postal_code'						=> $_POST['billing_postcode'],
						'country'							=> $_POST['billing_country'],
						'phone'								=> $_POST['billing_phone'],
					));
					$items = array();
					$products	= $this->order->get_items();
					foreach($products as $product){
						$p = new WC_Product($product['product_id']);
						$item[$product['product_id']]['name']			= $product['name'];
						//$item[$product['product_id']]['description']	= $p->;
						$line_total										= round(($product['line_subtotal']+$product['line_subtotal_tax'])*100);
						if($product['qty'] == 1){
							$item[$product['product_id']]['amount']			= $line_total;
						}else{
							$item[$product['product_id']]['amount']			= round(($line_total/$product['qty']));
						}
						$item[$product['product_id']]['quantity']		= $product['qty'];
						$item[$product['product_id']]['item_number']	= $p->get_sku() ? $p->get_sku() : $product['product_id'];
						$item[$product['product_id']]['url']			= $p->get_permalink();
					}
					$GLOBALS['paymill_loader']->request_checksum->setItems($item);
					// $GLOBALS['paymill_loader']->request_checksum->setHandlingAmount();

					// set amount on single transactions only
					if($this->order_get_total() > 0){
						if($this->order->get_total_shipping() > 0){
							$shipping = round((($this->order->get_total_shipping()+$this->order->get_shipping_tax())*100));
							$total = round(($this->order_get_total()));
							$GLOBALS['paymill_loader']->request_checksum->setShippingAmount($shipping);
							$GLOBALS['paymill_loader']->request_checksum->setAmount($total);
						}else{
							$GLOBALS['paymill_loader']->request_checksum->setAmount($this->order_get_total());
						}
					}
					
					// subscriptions included in this order, so make payment object reusable for future subscription transactions
					if(function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($this->order)){
						$GLOBALS['paymill_loader']->request_checksum->setRequireReusablePayment(true);
						$GLOBALS['paymill_loader']->request_checksum->setReusablePaymentDescription(__('Automatically pay invoices using this account.','paymill'));
					}
					
					$checksumData			= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_checksum); // Use this for further payment processing, too
					
					error_log("\n\n### Paypal processing: Client ID ".$this->clientClass->getCurrentClientID()." for checksum object ".$checksumData->getId()." \n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');

					try{
						$ch = curl_init();
						curl_setopt($ch, CURLOPT_URL, 'https://test-psp.paymill.de/rdp/start?checksum='.$checksumData->getId().'&public_key='.$GLOBALS['paymill_settings']->paymill_general_settings['api_key_public']);
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
						$result = json_decode(curl_exec($ch));
						if(!$result){
							$GLOBALS['paymill_loader']->paymill_errors->setError(curl_error($ch));
						}
						curl_close($ch);
					}catch(Exception $e){
						$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
					}
					
					if($GLOBALS['paymill_loader']->paymill_errors->status()){
						$GLOBALS['paymill_loader']->paymill_errors->getErrors();
						return false;
					}

					if($result->data->url){
						return array(
							'result'   => 'success',
							'redirect' => $result->data->url
						);
					}else{
						$GLOBALS['paymill_loader']->paymill_errors->setError(__('No Redirect URL received upon checksum creation','paymill'));
						$GLOBALS['paymill_loader']->paymill_errors->getErrors();
						return false;
					}
				}
				public function process_payment_paypal(){
					if(isset($_GET['order_id'])){
						$this->order				= new WC_Order($_GET['order_id']);
						$this->order_id				= intval($_GET['order_id']);
						if($this->order){
							global $woocommerce;
							// check status
							if(isset($_GET['paymill_trx_status']) && $_GET['paymill_trx_status'] == 'pending'){ // there will be some delay, webhooks will trigger and decide how to proceed
								$this->order->update_status('on-hold', __('Awaiting payment confirmation from PayPal via Paymill.', 'paymill'));
								
								// Remove cart
								$woocommerce->cart->empty_cart();
								
								// Return thankyou redirect
								wp_redirect($this->get_return_url($this->order));
							}elseif(isset($_GET['paymill_trx_status']) && $_GET['paymill_trx_status'] == 'failed'){ // 
								$this->order->update_status('failed', __('PayPal payment via Paymill failed.','paymill'));
								wp_redirect($this->get_return_url($this->order));
							}elseif(intval($_GET['paymill_response_code']) === 20000 || (isset($_GET['paymill_trx_status']) && $_GET['paymill_trx_status'] == 'closed')){
								require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
								
								$this->paymentClass		= new paymill_payment(false,false,$this->get_currency(),false); // create payment object, as it should be used for next processing instead of the token.
								if($GLOBALS['paymill_loader']->paymill_errors->status()){
									$GLOBALS['paymill_loader']->paymill_errors->getErrors();
									wc_print_notices();
									die();
								}

								$this->client				= $this->getCurrentClient(); // retrieve client
								error_log("\n\n### Paypal processing: Client ID ".$this->clientClass->getCurrentClientID()." for payment object ".$this->paymentClass->getPaymentID()." \n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
								$this->subscriptions		= new paymill_subscriptions('woocommerce');
								
								if($this->processSubscriptions()){
									$this->order->payment_complete();

									// Remove cart
									$woocommerce->cart->empty_cart();
									
									// Return thankyou redirect
									wp_redirect($this->get_return_url($this->order));
									die();
								}
								
								if($GLOBALS['paymill_loader']->paymill_errors->status()){
									$GLOBALS['paymill_loader']->paymill_errors->getErrors();
									wc_print_notices();
									die();
								}else{
									//still here? something's gone wrong, but we don't know what exactly
									error_log("\n\n".__('unexpected error while payment process.','paymill')."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log'); // @todo: make this translatable with sprintf
								}
							}else{ // ooops
								$this->order->update_status('failed', __('Status for PayPal payment via Paymill cannot be retrieved.','paymill'));
								wp_redirect($this->get_return_url($this->order));
							}
						}else{
							error_log("\n\n".__('PayPal via Paymill: No order for order id '.$_GET['order_id'].' found','paymill')."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log'); // @todo: make this translatable with sprintf
						}
					}else{
						error_log("\n\n".__('PayPal via Paymill: No order ID found. If this payment should be paid via Paymill-PayPal-Feature, something went wrong. Otherwise ignore this message.','paymill')."\n\n", 3, PAYMILL_DIR.'lib/debug/debug.log');
					}
				}
				*/
				public function get_icon() {
					global $woocommerce;
					
					$icon = '';

					if(isset($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && is_array($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && count($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) > 0){
						foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
							if($type==1){
								$icon .= '<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
							}
						}
					}

					return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
				}
				public function init_form_fields(){
					$this->form_fields = array(
						'enabled' => array(
							'title'			=> __('Enable/Disable', 'woocommerce'),
							'type'			=> 'checkbox',
							'label'			=> __('Enable PAYMILL Payment', 'woocommerce'),
							'default'		=> 'yes'
						),
						'title' => array(
							'title'			=> __('Title', 'woocommerce'),
							'type'			=> 'text',
							'description'	=> __('This controls the title which the user sees during checkout.', 'woocommerce'),
							'default'		=> __('PAYMILL Payment', 'woocommerce'),
							'desc_tip'		=> true,
						),
						'description' => array(
							'title'			=> __('Description', 'woocommerce'),
							'description'	=> __('This controls the description which the user sees during checkout.', 'woocommerce'),
							'type'			=> 'textarea',
							'default'		=> 'Payments made easy'
						)
					);
				}
				private function getCurrentClient(){
					require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
					if(isset($_REQUEST['billing_first_name']) && isset($_REQUEST['billing_last_name'])){
						$desc		= urldecode($_REQUEST['billing_first_name']).' '.urldecode($_REQUEST['billing_last_name']);
					}elseif(isset($_REQUEST['billing_first_name'])){
						$desc		= urldecode($_REQUEST['billing_first_name']);
					}elseif(isset($_REQUEST['billing_last_name'])){
						$desc		= urldecode($_REQUEST['billing_last_name']);
					}else{
						$desc		= '';
					}
					
					// create or get client
					$this->clientClass	= new paymill_client(urldecode($_REQUEST['billing_email']),$desc);
					return $this->clientClass->getCurrentClient();
				}
				private function order_get_total(){
					return round((floatval($this->order->get_total())*100),2);
				}
				/**
				 * @param object $subscription Subscription Post Object
				 * @return float recurring fee
				 * @since  1.10.7
				 */
				private function item_subscription_get_recurring_fee($subscription){
					$total = $subscription->get_total();
					return (floatval($total)*100);
				}
				/**
				 * @param object $subscription Subscription Post Object
				 * @return int Unix timestamp start at
				 * @since  1.10.7
				 */
				private function item_subscription_get_start_at($subscription){
					return (isset($_POST['paymill_delivery_date']) ? $_POST['paymill_delivery_date'] : $subscription->get_time('next_payment'));
				}
				/**
				 * @param object $subscription Subscription Post Object
				 * @return int interval amount
				 * @since  1.10.7
				 */
				private function item_subscription_get_interval($subscription){
					return intval($subscription->billing_interval);
				}
				/**
				 * @param object $subscription Subscription Post Object
				 * @param string $name_for_md5 subscription name for checksum
				 * @return string name for subscription
				 * @since  1.10.7
				 */
				private function item_subscription_get_name($subscription,$name_for_md5){
					// md5 name
					$woo_sub_md5				= md5($name_for_md5);
					
					return 'woo_'.$subscription->id.'_'.$woo_sub_md5;
				}
				/**
				 * @param object subscription Subscription Post Object
				 * @return string period
				 * @since  1.10.7
				 */
				private function item_subscription_get_period($subscription){
					return strtoupper($subscription->billing_period);
				}
				private function item_subscription_get_trial_period_days($subscription){
					/*$trial_end					= strtotime(WC_Subscriptions_Product::get_trial_expiration_date($product['product_id'], get_gmt_from_date($this->order->order_date)));
					if($trial_end === false){
						$trial_time				= 0;
					}else{
						$datediff				= $trial_end - time();
						$trial_time				= ceil($datediff/(60*60*24));
					}*/
					
					// this is not used anymore, as start_at on subscription level replaces default offer trial period
					return false;
				}
				/**
				 * @param object subscription Subscription Post Object
				 * @return string period of validity
				 * @since  1.10.7
				 */
				private function item_subscription_get_period_of_validity($subscription){
					
					
					if($subscription->get_time('trial_end') > 0){
						$subscription_length = wcs_estimate_periods_between($subscription->get_time('trial_end'), $subscription->get_time('end'), $subscription->billing_period);
					}else{
						$subscription_length = wcs_estimate_periods_between($this->item_subscription_get_start_at($subscription), $subscription->get_time('end'), $subscription->billing_period);
					}
					$subscription_installments = $subscription_length / $this->item_subscription_get_interval($subscription);

					if ($subscription_installments > 0){
						$periodOfValidity		= round($subscription_installments).' '.$this->item_subscription_get_period($subscription);
					}else{
						$periodOfValidity		= false;
					}
					
					return $periodOfValidity;
				}
				/**
				 * @param float $amount Offer amount
				 * @param string $currency Offer currency name
				 * @param int $interval Offer interval
				 * @param string $period Offer period
				 * @param string $name Offer name
				 * @param int $trial Offer trial period days
				 * @return object|false offer object on success, otherwise false
				 * @since  1.10.7
				 */
				private function create_offer($amount,$currency,$interval,$period,$name,$trial){
					$params = array(
						'amount'				=> $amount,
						'currency'				=> $currency,
						'interval'				=> $interval.' '.$period,
						'name'					=> $name,
						'trial_period_days'		=> $trial
					);
					$offer = $this->subscriptions->offerCreate($params);
					
					if($GLOBALS['paymill_loader']->paymill_errors->status()){
						$GLOBALS['paymill_loader']->paymill_errors->getErrors();
						return false;
					}
					return $offer;
				}
				/**
				 * @return string Currency
				 * @since  1.10.7
				 */
				private function get_currency(){
					return get_woocommerce_currency();
				}
				private function processSubscriptions(){
					global $wpdb;
					
					// check wether subscriptions addon is activated
					if(function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($this->order)){
						foreach(wcs_get_subscriptions_for_order($this->order, array('order_type' => 'any')) as $subscription){
							// required vars
							$name_for_md5		= '';
							$name_for_md5		.=		$offer_data['amount']		= $this->item_subscription_get_recurring_fee($subscription);
							$name_for_md5		.=		$offer_data['currency']		= $this->get_currency();
							$name_for_md5		.=		$offer_data['interval']		= $this->item_subscription_get_interval($subscription);
							$name_for_md5		.=		$offer_data['period']		= $this->item_subscription_get_period($subscription);
							$name_for_md5		.=		$offer_data['trial']		= $this->item_subscription_get_trial_period_days($subscription);
							$offer_data['name']										= $this->item_subscription_get_name($subscription,$name_for_md5);
							$sub_data['period_of_validity']							= $this->item_subscription_get_period_of_validity($subscription);
							$sub_data['start_at']									= $this->item_subscription_get_start_at($subscription);
/*
							$GLOBALS['paymill_loader']->paymill_errors->setError(var_export(time(),true));
							$GLOBALS['paymill_loader']->paymill_errors->setError(var_export($offer_data,true));
							$GLOBALS['paymill_loader']->paymill_errors->setError(var_export($sub_data,true));

							if($GLOBALS['paymill_loader']->paymill_errors->status()){
								$GLOBALS['paymill_loader']->paymill_errors->getErrors();
								return false;
							}
							*//*
							if(($offer = $this->subscriptions->offerGetDetailByName($offer_data['name'])) === false){ // offer does not exist in paymill yet, create it
								$offer = $this->create_offer($offer_data['amount'],$offer_data['currency'],$offer_data['interval'],$offer_data['period'],$offer_data['name'],$offer_data['trial']);
							}*/
							// create user subscription
							$user_sub = $this->subscriptions->create($this->clientClass->getCurrentClientID(), false, $this->paymentClass->getPaymentID(), $sub_data['start_at'], $sub_data['period_of_validity'],$offer_data['amount'],$offer_data['currency'],$offer_data['interval'].' '.$offer_data['period']);
							/*
							if($GLOBALS['paymill_loader']->paymill_errors->status()){
								//maybe offer cache is outdated, recache and try again
								
								$GLOBALS['paymill_loader']->paymill_errors->reset(); // reset error status

								$this->subscriptions->offerGetList(true);
								if(($offer = $this->create_offer($offer_data['amount'],$offer_data['currency'],$offer_data['interval'],$offer_data['period'],$offer_data['name'],$offer_data['trial'])) === false){
									return false;
								}

								$user_sub = $this->subscriptions->create($this->clientClass->getCurrentClientID(), $offer, $this->paymentClass->getPaymentID(), $sub_data['start_at'], $sub_data['period_of_validity']);
								
								if($GLOBALS['paymill_loader']->paymill_errors->status()){
									$GLOBALS['paymill_loader']->paymill_errors->getErrors();
									return false;
								}
							}
							*/
							$wpdb->query($wpdb->prepare('INSERT INTO '.$wpdb->prefix.'paymill_subscriptions (paymill_sub_id, woo_user_id, woo_offer_id) VALUES (%s, %s, %s)',
							array(
								$user_sub,
								get_current_user_id(),
								$subscription->id
							)));
						
							// subscription successful
							do_action('paymill_woocommerce_subscription_created', array(
								'product_id'	=> $product['product_id'],
								'offer_id'		=> $offer,
								//'offer_data'	=> $offer
							));
						}
					}else{
						return true;
					}
					return true;
				}
				private function processProducts(){					
					global $wpdb;

					if($this->order_get_total() > 0){
						// make transaction
						$GLOBALS['paymill_loader']->request_transaction->setAmount($this->order_get_total()); // e.g. "4200" for 42.00 EUR
						$GLOBALS['paymill_loader']->request_transaction->setCurrency($this->get_currency());
						if($this->paymentClass->getPreauthID() != false){
							$GLOBALS['paymill_loader']->request_transaction->setPreauthorization($this->paymentClass->getPreauthID());
						}else{
							$GLOBALS['paymill_loader']->request_transaction->setPayment($this->paymentClass->getPaymentID());
						}
						$GLOBALS['paymill_loader']->request_transaction->setClient($this->clientClass->getCurrentClientID());
						$GLOBALS['paymill_loader']->request_transaction->setDescription($this->order_desc);
						$GLOBALS['paymill_loader']->request->setSource(serialize($GLOBALS['paymill_source']));
						
						$GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_transaction);

						$response = $GLOBALS['paymill_loader']->request->getLastResponse();
						
						if(isset($response['body']['data']['response_code']) && $response['body']['data']['response_code'] != '20000'){
							$GLOBALS['paymill_loader']->paymill_errors->setError(__($response['body']['data']['response_code'], 'paymill'));
							if($GLOBALS['paymill_loader']->paymill_errors->status()){
								$GLOBALS['paymill_loader']->paymill_errors->getErrors();
							}
							return false;
						}

						// save data to transaction table
						$wpdb->query($wpdb->prepare('
						INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, woocommerce_order_id, paymill_transaction_time, paymill_transaction_data)
						VALUES (%s,%s,%s,%d,%d,%s)',
						array(
							$response['body']['data']['id'],
							$response['body']['data']['payment']['id'],
							$response['body']['data']['client']['id'],
							$this->order_id,
							time(),
							serialize($_POST)
						)));
						
						do_action('paymill_woocommerce_products_paid', array(
							'total'			=> $this->order_get_total(),
							'currency'		=> $this->get_currency(),
							'client'		=> $response['body']['data']['client']['id']
						));
						
						return true;
					}else{ // total is zero, so just return true
					
						// remove preauth when not used
						// @todo: Once preauths are usable for delayed payment in this plugin, we need to make a condition for this
						$this->paymentClass->removePreauth();
					
						return true;
					}
				}
				public function process_payment($order_id){
					global $woocommerce,$wpdb;
					
					$this->client				= $this->getCurrentClient(); // retrieve client

					$this->order_id				= $order_id;
					$this->order_desc			= $_SERVER['HTTP_HOST'].': '.__('Order #','paymill').$this->order_id.__(', Customer-ID #','paymill').get_current_user_id();
					$this->order				= new WC_Order($this->order_id);

					// load subscription class
					$this->subscriptions		= new paymill_subscriptions('woocommerce');
					$this->offers				= $this->subscriptions->offerGetList();

					// get the totals for pre authorization
					//$this->getTotals();
					
					/*if(isset($_POST['paypal_via_paymill']) && $_POST['paypal_via_paymill'] == 1){
						return $this->prepare_payment_paypal();
					}else{*/
						// create payment object and preauthorization
						// update v1.10.7: preauth deactivated
						require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
						$this->paymentClass		= new paymill_payment($this->clientClass->getCurrentClientID(),$this->order_get_total(),$this->get_currency(),$this->order); // create payment object, as it should be used for next processing instead of the token.
						if($GLOBALS['paymill_loader']->paymill_errors->status()){
							$GLOBALS['paymill_loader']->paymill_errors->getErrors();
							return false;
						}

						// process subscriptions & products
						if($this->processSubscriptions() && $this->processProducts()){
							// success
							if(method_exists($this->order, 'payment_complete')){
								$this->order->payment_complete();
							}
							
							// Remove cart
							$woocommerce->cart->empty_cart();
							
							// Return thankyou redirect
							return array(
								'result' => 'success',
								'redirect' => $this->get_return_url($this->order)
							);
						}else{
							if($GLOBALS['paymill_loader']->paymill_errors->status()){
								$GLOBALS['paymill_loader']->paymill_errors->getErrors();
							}
							return false;
						}
					//}
				}
				public function validate_fields(){
					global $woocommerce;
					// check Paymill payment
					//if(empty($_POST['paymillToken']) && !isset($_POST['paypal_via_paymill'])){
					if(empty($_POST['paymillToken'])){
						$GLOBALS['paymill_loader']->paymill_errors->setError(__('Token not Found','paymill'));
						if($GLOBALS['paymill_loader']->paymill_errors->status()){
							$GLOBALS['paymill_loader']->paymill_errors->getErrors();
						}
						return false;
					}
					return true;
				}
				public function payment_fields(){
					global $woocommerce;

					if(!$GLOBALS['paymill_active']){
						paymill_load_frontend_scripts(); // load frontend scripts
						
						// settings
						$GLOBALS['paymill_active']		= true;
						$cart_total						= WC_Payment_Gateway::get_order_total()*100;
						$currency						= $this->get_currency();
						$no_logos						= true;
						
						// form id
						if(isset($GLOBALS['paymill_settings']->paymill_advanced_settings['custom_form_key']) && strlen($GLOBALS['paymill_settings']->paymill_advanced_settings['custom_form_key']) > 0){
							$form_id = $GLOBALS['paymill_settings']->paymill_advanced_settings['custom_form_key'];
						}else{
							$form_id = 'form.checkout, #order_review';
						}
						// submit id
						if(isset($GLOBALS['paymill_settings']->paymill_advanced_settings['custom_submit_key']) && strlen($GLOBALS['paymill_settings']->paymill_advanced_settings['custom_submit_key']) > 0){
							$submit_id = $GLOBALS['paymill_settings']->paymill_advanced_settings['custom_submit_key'];
						}else{
							$submit_id = '#place_order';
						}
						
						echo '<script type="text/javascript">
						paymill_form_checkout_id = "'.$form_id.'";
						paymill_form_checkout_submit_id = "'.$submit_id.'";
						paymill_shop_name = "woocommerce";
						paymill_pcidss3 = '.((empty($GLOBALS['paymill_settings']->paymill_general_settings['pci_dss_3']) || $GLOBALS['paymill_settings']->paymill_general_settings['pci_dss_3'] != '1') ? 1 : 0).';
						</script>';
						
						
						echo '<a href="https://www.paymill.com/" target="_blank"><img src="' . WC_HTTPS::force_https_url( $this->logo_small ) . '" alt="' . $this->title . '" /></a>';
						
						echo '<p class="paymill_payment_description">'.$this->settings['description'].'</p>';
			
						require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
					}else{
						echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
					}
					return true;
				}
			}
		}
	}
	add_action('plugins_loaded', 'init_paymill_gateway_class');
?>