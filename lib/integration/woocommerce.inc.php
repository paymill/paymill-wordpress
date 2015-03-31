<?php
	// PAYMILL Payment Class
	if(!function_exists('paymill_woocommerce_errorHandling')){
		function paymill_woocommerce_errorHandling($errors){
			if(!defined('PAYMILL_WOO_NOTICES_SENT')){
				foreach($errors as $error){
					wc_add_notice('<div class="paymill_error">'.$error.'</div>', 'error' );
				}
				define('PAYMILL_WOO_NOTICES_SENT',true);
			}
		}
	}

	// HOOKED FUNCTIONS FROM PAYMILL WEBHOOKS
	function paymill_webhooks(){
		global $wpdb;
		
		// is there a webhook from Paymill?
		if(class_exists('WC_Subscriptions_Manager')){
		
			// grab data from webhook
			$body = @file_get_contents('php://input');
			$event_json = json_decode($body, true);
			
			// retrieve sub ID
			if(isset($event_json['event']['event_resource']['id']) && strlen($event_json['event']['event_resource']['id']) > 0){
				$paymill_sub_id			= $event_json['event']['event_resource']['id'];
			}elseif(isset($event_json['event']['event_resource']['subscription']['id']) && strlen($event_json['event']['event_resource']['subscription']['id']) > 0){
				$paymill_sub_id			= $event_json['event']['event_resource']['subscription']['id'];
			}
			
			error_log("\n\n########################################################################################################################\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
			error_log(date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' (Resource-ID: '.$paymill_sub_id.') triggered - start processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
			
			/* output example:
				array(1) {
				  ["event"]=>
				  array(4) {
					["event_type"]=>
					string(20) "subscription.deleted"
					["event_resource"]=>
					array(13) {
					  ["id"]=>
					  string(24) "sub_b71adbf5....."
					  ["offer"]=>
					  array(10) {
						["id"]=>
						string(26) "offer_8083a5b....."
						["name"]=>
						string(39) "woo_91_73da6....."
						["amount"]=>
						int(100)
						["currency"]=>
						string(3) "EUR"
						["interval"]=>
						string(5) "1 DAY"
						["trial_period_days"]=>
						int(0)
						["created_at"]=>
						int(1389547028)
						["updated_at"]=>
						int(1389547028)
						["subscription_count"]=>
						array(2) {
						  ["active"]=>
						  string(1) "1"
						  ["inactive"]=>
						  string(1) "1"
						}
						["app_id"]=>
						NULL
					  }
					  ["livemode"]=>
					  bool(false)
					  ["cancel_at_period_end"]=>
					  bool(false)
					  ["trial_start"]=>
					  NULL
					  ["trial_end"]=>
					  NULL
					  ["next_capture_at"]=>
					  int(1389836717)
					  ["created_at"]=>
					  int(1389663382)
					  ["updated_at"]=>
					  int(1389750317)
					  ["canceled_at"]=>
					  NULL
					  ["app_id"]=>
					  NULL
					  ["payment"]=>
					  array(12) {
						["id"]=>
						string(28) "pay_4e3759f....."
						["type"]=>
						string(10) "creditcard"
						["client"]=>
						string(27) "client_dbe164....."
						["card_type"]=>
						string(4) "visa"
						["country"]=>
						NULL
						["expire_month"]=>
						string(2) "12"
						["expire_year"]=>
						string(4) "2020"
						["card_holder"]=>
						string(13) "dfgdfgdfgdfgd"
						["last4"]=>
						string(4) "1111"
						["created_at"]=>
						int(1389663369)
						["updated_at"]=>
						int(1389663380)
						["app_id"]=>
						NULL
					  }
					  ["client"]=>
					  array(8) {
						["id"]=>
						string(27) "client_dbe164....."
						["email"]=>
						string(22) "matthias@pc-intern.com"
						["description"]=>
						string(15) "Matthias Reuter"
						["created_at"]=>
						int(1389547027)
						["updated_at"]=>
						int(1389547027)
						["app_id"]=>
						NULL
						["payment"]=>
						array(2) {
						  [0]=>
						  array(12) {
							["id"]=>
							string(28) "pay_1a5ff8....."
							["type"]=>
							string(10) "creditcard"
							["client"]=>
							string(27) "client_dbe16....."
							["card_type"]=>
							string(4) "visa"
							["country"]=>
							NULL
							["expire_month"]=>
							string(2) "12"
							["expire_year"]=>
							string(4) "2020"
							["card_holder"]=>
							string(10) "dfgdfgdfgd"
							["last4"]=>
							string(4) "1111"
							["created_at"]=>
							int(1389547027)
							["updated_at"]=>
							int(1389547028)
							["app_id"]=>
							NULL
						  }
						  [1]=>
						  array(12) {
							["id"]=>
							string(28) "pay_4e375....."
							["type"]=>
							string(10) "creditcard"
							["client"]=>
							string(27) "client_dbe164....."
							["card_type"]=>
							string(4) "visa"
							["country"]=>
							NULL
							["expire_month"]=>
							string(2) "12"
							["expire_year"]=>
							string(4) "2020"
							["card_holder"]=>
							string(13) "dfgdfgdfgdfgd"
							["last4"]=>
							string(4) "1111"
							["created_at"]=>
							int(1389663369)
							["updated_at"]=>
							int(1389663380)
							["app_id"]=>
							NULL
						  }
						}
						["subscription"]=>
						array(2) {
						  [0]=>
						  string(24) "sub_fcc4....."
						  [1]=>
						  string(24) "sub_b71a....."
						}
					  }
					}
					["created_at"]=>
					int(1389816435)
					["app_id"]=>
					NULL
				  }
				}
				
			*/
			//error_log(var_export($event_json,true)."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
			
			// get subscription info, if available
			if(isset($paymill_sub_id) && strlen($paymill_sub_id) > 0){
				
				$sql = $wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'paymill_subscriptions WHERE paymill_sub_id=%s',
				array(
					$paymill_sub_id
				));
				
				$sub_cache			= $wpdb->get_results($sql,ARRAY_A);
				$sub_cache			= $sub_cache[0];
				
				/* output example:
				SELECT * FROM wp_paymill_subscriptions WHERE paymill_sub_id="sub_b71adbf5e097bbe5ba80"
				*/
				error_log("\n\n".$sql."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
				
				/* output example:
				
				1
				
				30
				
				*/
				//error_log($sub_cache['woo_user_id']."\n\n".$sub_cache['woo_offer_id']."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
				
				$subscription           = WC_Subscriptions_Manager::get_subscription($sub_cache['woo_offer_id']);
				
				// update subscriptions when webhook is triggered
				if(isset($sub_cache['woo_offer_id']) && strlen($sub_cache['woo_offer_id']) > 0){
					// subscription successfully created
					if($event_json['event']['event_type'] == 'subscription.created'){
						
					}
					// tell WooCommerce when payment for subscription is successfully processed
					if($event_json['event']['event_type'] == 'subscription.succeeded'){
						/* example data WC_Subscriptions_Manager::get_subscription:
							array(15) {
							  ["order_id"]=>
							  string(3) "201"
							  ["product_id"]=>
							  string(2) "91"
							  ["variation_id"]=>
							  string(0) ""
							  ["status"]=>
							  string(6) "active"
							  ["period"]=>
							  string(3) "day"
							  ["interval"]=>
							  string(1) "1"
							  ["length"]=>
							  string(2) "12"
							  ["start_date"]=>
							  string(19) "2014-01-12 17:17:10"
							  ["expiry_date"]=>
							  string(19) "2014-01-24 17:17:10"
							  ["end_date"]=>
							  string(1) "0"
							  ["trial_expiry_date"]=>
							  string(1) "0"
							  ["failed_payments"]=>
							  string(1) "0"
							  ["completed_payments"]=>
							  array(1) {
								[0]=>
								string(19) "2014-01-12 17:17:10"
							  }
							  ["suspension_count"]=>
							  string(1) "0"
							  ["last_payment_date"]=>
							  string(19) "2014-01-12 17:17:10"
							}
						*/
						error_log(var_export($subscription,true)."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
						
						if(count($subscription['completed_payments']) >= 1){
							$order = new WC_Order($subscription['order_id']);
							
							//WC_Subscriptions_Manager::process_subscription_payments_on_order($order, $subscription['product_id']);
							WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
						}else{
							$order				= new WC_Order($subscription['order_id']);
							$order->payment_complete();
							
							WC_Subscriptions_Manager::activate_subscriptions_for_order($subscription['order_id']);
						}
						WC_Subscriptions_Manager::set_next_payment_date($sub_cache['woo_offer_id'], $order->customer_user);
					}
					// cancel subscription, as it was deleted through Paymill dashboard
					if($event_json['event']['event_type'] == 'subscription.deleted'){

						$sql = $wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',
						array(
							$sub_cache['woo_user_id'],
							$sub_cache['woo_offer_id']
						));
						$wpdb->query($sql);
						
						error_log("\n\n".$sql."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
						
						//WC_Subscriptions_Manager::cancel_subscriptions_for_order( $order );
						WC_Subscriptions_Manager::cancel_subscription($sub_cache['woo_user_id'], $sub_cache['woo_offer_id']);
					}
					// tell WC that payment failure occured
					if($event_json['event']['event_type'] == 'subscription.failed'){
						WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($subscription['order_id'], $subscription['product_id']);
					}
				}
			}
			error_log(date(DATE_RFC822).' - Webhook '.$event_json['event']['event_type'].' finished - end processing'."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
			error_log("\n\n########################################################################################################################\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
		}
	}
	add_action('woocommerce_api_wc_gateway_paymill_gateway', 'paymill_webhooks');

	add_action('cancelled_subscription_paymill','woo_cancelled_subscription_paymill', 10, 2);
	//add_action( 'updated_users_subscriptions','woo_updated_subscription_paymill', 10, 2 );
	add_action( 'subscription_put_on-hold_paymill','woo_subscription_put_on_hold_paymill', 10, 2 );
	add_action( 'reactivated_subscription_paymill','woo_reactivated_subscription_paymill', 10, 2 );
	function woo_cancelled_subscription_paymill($order, $product_id){
		global $wpdb;

		$client_cache		= $wpdb->get_results($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',array($order->user_id,$order->id.'_'.$product_id)),ARRAY_A);

		if (!isset($client_cache[0]['paymill_sub_id']))
			error_log("could not find paymill sub while trying to cancel $order->user_id $order->id $product_id");

		$subscriptions		= new paymill_subscriptions('woocommerce');
		$subscriptions->remove($client_cache[0]['paymill_sub_id']);
		$wpdb->query($wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',array($user,$subscription_key)));
	}
	function woo_updated_subscription_paymill($user,$subscription_details){
		// @todo: implement support for changing/creating offer later
	}
	function woo_subscription_put_on_hold_paymill($order, $product_id){
		global $wpdb;

		$client_cache		= $wpdb->get_results($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',array($order->user_id,$order->id.'_'.$product_id)),ARRAY_A);

		if (!isset($client_cache[0]['paymill_sub_id']))
			error_log("could not find paymill sub while trying to pause $order->user_id $order->id $product_id");

		if ($order->status == 'on-hold') return; // all subs begin by being on-hold, so don't pause them again

		$subscriptions		= new paymill_subscriptions('woocommerce');
		$subscriptions->pause($client_cache[0]['paymill_sub_id']);
    }
	function woo_reactivated_subscription_paymill($order, $product_id){
		global $wpdb;

		$client_cache		= $wpdb->get_results($wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',array($order->user_id,$order->id.'_'.$product_id)),ARRAY_A);

		if (!isset($client_cache[0]['paymill_sub_id']))
			error_log("could not find paymill sub while trying to reactivate $order->user_id $order->id $product_id");

		$subscriptions		= new paymill_subscriptions('woocommerce');
		$subscriptions->unpause($client_cache[0]['paymill_sub_id']);
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
			
				private $total					= 0;
				private $total_complete			= 0;
				private $total_sub_refund		= 0;
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
						'subscription_reactivation',/*
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change'*/
					);
				}
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
					if(isset($_POST['billing_first_name']) && isset($_POST['billing_last_name'])){
						$desc		= $_POST['billing_first_name'].' '.$_POST['billing_last_name'];
					}elseif(isset($_POST['billing_first_name'])){
						$desc		= $_POST['billing_first_name'];
					}elseif(isset($_POST['billing_last_name'])){
						$desc		= $_POST['billing_last_name'];
					}else{
						$desc		= '';
					}
					
					// create or get client
					$this->clientClass	= new paymill_client($_POST['billing_email'],$desc);
					return $this->clientClass->getCurrentClient();
				}
				private function getTotals(){
					// retrieve subscriptions amount
					if(class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($this->order)){
						foreach($this->cart as $product){
							if(is_object($product) && isset($product->id) && intval($product->id) > 0){
								$woo_sub_key		= WC_Subscriptions_Manager::get_subscription_key($this->order_id,$product->id);
								
								if(!WC_Subscriptions_Manager::user_has_subscription(get_current_user_id(), $woo_sub_key)){
									$sub_amount_total				= floatval(floatval(WC_Subscriptions_Order::get_recurring_total($this->order))*100);
									$this->total					= $this->total-$sub_amount_total;
									if($this->total_complete == 0){
										$this->total_complete = $this->total_complete+$sub_amount_total;
									}
								}
							}
						}
						// currently, there is no initial payment fee possible through paymill, so we are required to make a refund if a coupon is reducing initial fee.
						/*if($this->total < 0){
							$this->total_sub_refund = (($this->total)*(-1));
						}*/
					}
				}
				private function processSubscriptions(){
					global $wpdb;

					// check wether subscriptions addon is activated
					if(class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($this->order)){

						$product	= $this->cart;
						//foreach($this->cart as $product){
							if(is_array($product) && isset($product['product_id']) && intval($product['product_id']) > 0){
								// product is a subscription?
								$woo_sub_key	= WC_Subscriptions_Manager::get_subscription_key($this->order_id,$product['product_id']);

								// check wether user already has subscription
								//if(!WC_Subscriptions_Manager::user_has_subscription(get_current_user_id(), $woo_sub_key)){

									// required vars
									$amount						= (floatval(WC_Subscriptions_Order::get_recurring_total($this->order))*100);
									$currency					= get_woocommerce_currency();
									$interval					= WC_Subscriptions_Order::get_subscription_interval($this->order,$product['product_id']);
									$length						= intval(WC_Subscriptions_Order::get_subscription_length($this->order,$product['product_id']));
									$period						= strtoupper(WC_Subscriptions_Order::get_subscription_period($this->order,$product['product_id']));
									if ($length > 0) {
										$periodOfValidity		= $length.' '.$period;
									} else{
										$periodOfValidity		= false;
									}
									$trial_end					= strtotime(WC_Subscriptions_Product::get_trial_expiration_date($product['product_id'], get_gmt_from_date($this->order->order_date)));
									if($trial_end === false){
										$trial_time				= 0;
									}else{
										$datediff				= $trial_end - time();
										$trial_time				= ceil($datediff/(60*60*24));
									}
									
									// md5 name
									$woo_sub_md5				= md5($amount.$currency.$interval.$trial_time);

									// get offer
									$name						= 'woo_'.$product['product_id'].'_'.$woo_sub_md5;
									$offer						= $this->subscriptions->offerGetDetailByName($name);

									// check wether offer exists in paymill
									if($offer === false){
										// offer does not exist in paymill yet, create it
										$params = array(
											'amount'			=> $amount,
											'currency'			=> $currency,
											'interval'			=> $interval.' '.$period,
											'name'				=> $name,
											'trial_period_days'	=> intval($trial_time)
										);
										$offer = $this->subscriptions->offerCreate($params);
										if($GLOBALS['paymill_loader']->paymill_errors->status()){
											$GLOBALS['paymill_loader']->paymill_errors->getErrors();
											return false;
										}
									}

									// create user subscription
									$user_sub = $this->subscriptions->create($this->client->getId(), $offer['id'], $this->paymentClass->getPaymentID(),(isset($_POST['paymill_delivery_date']) ? $_POST['paymill_delivery_date'] : false),$periodOfValidity);
									
									if($GLOBALS['paymill_loader']->paymill_errors->status()){
										$GLOBALS['paymill_loader']->paymill_errors->getErrors();
										return false;
									}else{
										$wpdb->query($wpdb->prepare('INSERT INTO '.$wpdb->prefix.'paymill_subscriptions (paymill_sub_id, woo_user_id, woo_offer_id) VALUES (%s, %s, %s)',
										array(
											$user_sub,
											get_current_user_id(),
											$woo_sub_key
										)));
									
										// subscription successful
										do_action('paymill_woocommerce_subscription_created', array(
											'product_id'	=> $product['product_id'],
											'offer_id'		=> $offer['id'],
											'offer_data'	=> $offer
										));
										
										return true;
									}
								/*}else{
									// @todo: currently, WooCommerce does not support multiple subscriptions on checkout, so we can stop processing here if first subscription is already subscribed
									$GLOBALS['paymill_loader']->paymill_errors->setError(__('Subscription already subscribed.', 'paymill'));
									if($GLOBALS['paymill_loader']->paymill_errors->status()){
										$GLOBALS['paymill_loader']->paymill_errors->getErrors();
									}
									return false;
								}*/
							}
						//}
					}else{
						return true;
					}
				}
				private function processProducts(){
					global $wpdb;
					if($this->total > 0){
						// make transaction
						$GLOBALS['paymill_loader']->request_transaction->setAmount(round($this->total,2)); // e.g. "4200" for 42.00 EUR
						$GLOBALS['paymill_loader']->request_transaction->setCurrency(get_woocommerce_currency());
						if($this->paymentClass->getPreauthID() != false){
							$GLOBALS['paymill_loader']->request_transaction->setPreauthorization($this->paymentClass->getPreauthID());
						}else{
							$GLOBALS['paymill_loader']->request_transaction->setPayment($this->paymentClass->getPaymentID());
						}
						$GLOBALS['paymill_loader']->request_transaction->setClient($this->client->getId());
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
							'total'			=> $this->total,
							'currency'		=> get_woocommerce_currency(),
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
					
					$this->client					= $this->getCurrentClient();
					// client retrieved, now we are ready to process the payment
					if($this->client->getId() !== false && strlen($this->client->getId()) > 0){
						$this->order_id				= $order_id;
						$this->order_desc			= $_SERVER['HTTP_HOST'].': '.__('Order #','paymill').$this->order_id.__(', Customer-ID #','paymill').get_current_user_id();
						$this->order				= new WC_Order($this->order_id);
						$cart						= $woocommerce->cart->get_cart();
						$cart						= reset($cart);
						$this->cart					= $cart;
						$this->total_complete		=
						$this->total				= (floatval($this->order->get_total())*100);

						// load subscription class
						$this->subscriptions		= new paymill_subscriptions('woocommerce');
						$this->offers				= $this->subscriptions->offerGetList();

						// get the totals for pre authorization
						$this->getTotals();

						// create payment object and preauthorization
						require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
						$this->paymentClass		= new paymill_payment($this->client->getId(),$this->total_complete,get_woocommerce_currency()); // create payment object, as it should be used for next processing instead of the token.
						if($GLOBALS['paymill_loader']->paymill_errors->status()){
							$GLOBALS['paymill_loader']->paymill_errors->getErrors();
							return false;
						}

						// process subscriptions & products
						if($this->processSubscriptions() && $this->processProducts()){
							// success
							if(method_exists($this->order, 'payment_complete')){
								// if order contains subscription, mark payment complete later when webhook triggers succeeded payment
								if(class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($this->order)){
									$this->order->update_status('on-hold', __( 'Awaiting payment confirmation from Paymill.', 'paymill' ));
								}else{
									$this->order->payment_complete();
								}
							}

							// Reduce stock levels
							/*if(method_exists($this->order, 'reduce_order_stock')){
								$this->order->reduce_order_stock();
							}*/

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
					}else{
						$GLOBALS['paymill_loader']->paymill_errors->setError(__('There was an issue with adding you as client for the payment process.', 'paymill'));
						return false;
					}
				}
				public function validate_fields(){
					global $woocommerce;
					// check Paymill payment
					if(empty($_POST['paymillToken'])){
						$woocommerce->add_error(__('Token not Found','paymill'));
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
						$currency						= get_woocommerce_currency();
						$no_logos						= true;
						
						// form ids
						echo '<script>
						paymill_form_checkout_id = "form.checkout, form#order_review";
						paymill_form_checkout_submit_id = "#place_order";
						paymill_shop_name = "woocommerce";
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