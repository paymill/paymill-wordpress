<?php
	/*
	
	PAYMILL Payment Class
	
	*/
	add_action( 'plugins_loaded', 'init_paymill_gateway_class' );
	
	function add_paymill_gateway_class( $methods ) {
		$methods[] = 'WC_Gateway_Paymill_Gateway'; 
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_paymill_gateway_class' );
	
	add_action( 'cancelled_subscription_paymill','woo_cancelled_subscription_paymill', 10, 2 );
	// add_action( 'updated_users_subscriptions','woo_updated_subscription_paymill', 10, 2 );
	//add_action( 'subscription_put_on-hold_paymill','woo_subscription_put_on_hold_paymill', 10, 2 );
	//add_action( 'reactivated_subscription_paymill','woo_reactivated_subscription_paymill', 10, 2 );

	function woo_cancelled_subscription_paymill($user,$subscription_key){
		global $wpdb;
		
		$userInfo			= get_userdata(get_current_user_id());
		$subscriptions		= new paymill_subscriptions('woocommerce');
		$query				= 'SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id="'.$userInfo->ID.'" AND woo_offer_id="'.$user->id.'_'.$subscription_key.'"';
		$client_cache		= $wpdb->get_results($query,ARRAY_A);
		
		$subscriptions->remove($client_cache[0]['paymill_sub_id']);
		
		$query				= 'DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id="'.$userInfo->ID.'" AND woo_offer_id="'.$user->id.'_'.$subscription_key.'"';
		$wpdb->query($query);
	}
	function woo_updated_subscription_paymill($user,$subscription_details){
		var_dump($user);
		var_dump($subscription_details);
		die();
	}
	
	function woo_subscription_put_on_hold_paymill(){
	
	}
	function woo_reactivated_subscription_paymill($user,$subscription_key){
	
	}

	
	function init_paymill_gateway_class() {
		global $wpdb;
	
		// update subscriptions when webhook is triggered
		if(class_exists('WC_Subscriptions_Manager') && isset($_GET['paymill_webhook']) && $_GET['paymill_webhook'] == 1){
			$body = @file_get_contents('php://input');
			$event_json = json_decode($body, true);
			
			if($event_json['event']['event_type'] == 'subscription.deleted'){
				$query				= 'SELECT * FROM '.$wpdb->prefix.'paymill_subscriptions WHERE paymill_sub_id="'.$event_json['event']['event_resource']['id'].'"';
				$sub_cache			= $wpdb->get_results($query,ARRAY_A);
				
				$query				= 'DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id="'.$sub_cache[0]['woo_user_id'].'" AND woo_offer_id="'.$sub_cache[0]['woo_offer_id'].'"';
				$wpdb->query($query);
				
				//error_log("\n\n".$query."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
				//error_log($sub_cache[0]['woo_user_id']."\n\n".$sub_cache[0]['woo_offer_id']."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
				
				WC_Subscriptions_Manager::cancel_subscription($sub_cache[0]['woo_user_id'], $sub_cache[0]['woo_offer_id']);
			}
		}
	
		if(class_exists('WC_Payment_Gateway')){
			class WC_Gateway_Paymill_Gateway extends WC_Payment_Gateway{
				public function __construct(){
				
					$GLOBALS['paymill_source']['woocommerce_version'] = ((isset($GLOBALS['woocommerce']) && is_object($GLOBALS['woocommerce']) && isset($GLOBALS['woocommerce']->version)) ? $GLOBALS['woocommerce']->version : 0);
					
					$this->id					= 'paymill';
					$this->icon					= plugins_url('',__FILE__ ).'/../img/icon.png';
					$this->logo					= plugins_url('',__FILE__ ).'/../img/logo.png';
					$this->logo_small			= plugins_url('',__FILE__ ).'/../img/logo_small.png';

					$this->has_fields			= true;
					
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
				
					$this->init_form_fields();
					$this->init_settings();
					
					$this->title				= $this->settings['title'];
					$this->description			= $this->settings['description'];
					
					$this->supports = array(
						'products',
						'subscriptions',
						'subscription_cancellation',/*
						'subscription_suspension', 
						'subscription_reactivation',
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change'*/
					);
				}
				
				// @todo: better icon
				function get_icon() {
					global $woocommerce;

					$icon = '<a href="https://www.paymill.com/" target="_blank"><img src="' . $woocommerce->force_ssl( $this->logo_small ) . '" alt="' . $this->title . '" /></a>';

					if(isset($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && is_array($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && count($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) > 0){
						foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
							if($type==1){
								$icon .= '<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
							}
						}
					}
	
					return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
				}
				
				public function init_form_fields(){
					$this->form_fields = array(
						'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable PAYMILL Payment', 'woocommerce' ),
							'default' => 'yes'
						),
						'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'PAYMILL Payment', 'woocommerce' ),
							'desc_tip'      => true,
						),
						'description' => array(
							'title' => __( 'Customer Message', 'woocommerce' ),
							'type' => 'textarea',
							'default' => 'Payments made easy'
						)
					);
				}
				
				public function process_payment( $order_id ) {
					global $woocommerce,$wpdb;
					
					// first retrieve client data, either from cache or from API
					require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
					$clientClass			= new paymill_client(
												$_POST['billing_email'],
												$_POST['billing_first_name'].' '.$_POST['billing_last_name']
											);
					
					$client					= $clientClass->getCurrentClient();

					// client retrieved, now we are ready to process the payment
					if($client['id'] !== false && strlen($client['id']) > 0){
						require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
						
						$paymentClass		= new paymill_payment($client['id']);
						
						// calculate total based on product settings
						$total = (floatval($woocommerce->cart->total)*100);
						
						$order = new WC_Order( $order_id );
						
						// make subscription
						if($client['id'] && $paymentClass->getPaymentID() && class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($order)){
							$cart = $woocommerce->cart->get_cart();
							
							$subscriptions = new paymill_subscriptions('woocommerce');
						
							foreach($cart as $product){

								$woo_sub_key	= WC_Subscriptions_Manager::get_subscription_key($order_id,$product['product_id']);

								if(!WC_Subscriptions_Manager::user_has_subscription(get_current_user_id(), $woo_sub_key)){
									// required vars
									$amount			= (floatval(WC_Subscriptions_Order::get_item_recurring_amount( $order,$product['product_id'] ))*100);
									$currency		= get_woocommerce_currency();
									$interval		= '1 '.strtoupper(WC_Subscriptions_Order::get_subscription_period( $order,$product['product_id'] ));
									
									// get trial time
									$now = time();
									$trial_end		= strtotime(WC_Subscriptions_Product::get_trial_expiration_date($product['product_id'], get_gmt_from_date($order->order_date)));
									$datediff		= $trial_end - $now;
									$trial_time		= ceil($datediff/(60*60*24));
									
									// md5 name
									$woo_sub_md5	= md5($amount.$currency.$interval.$trial_time);
									
									// get offer
									$name			= 'woo_'.$product['product_id'].'_'.$woo_sub_md5;
									$offer			= $subscriptions->offerGetDetailByName($name);
									$offer			= $offer[0];
									
									// check wether woosub exists in paymill
									if(count($offer) == 0){
										// offer does not exist in paymill yet, create it
										$params = array(
											'amount'			=> $amount,
											'currency'			=> $currency,
											'interval'			=> $interval,
											'name'				=> $name,
											'trial_period_days'	=> $trial_time
										);
										$offer = $subscriptions->offerCreate($params);
										
										if(isset($offer['error']['messages'])){
											foreach($offer['error']['messages'] as $field => $msg){
												$woocommerce->add_error($field.': '.$msg);
											}
											return;
										}
									}
									
									// create user subscription
									$user_sub = $subscriptions->create($client['id'], $offer['id'], $paymentClass->getPaymentID());
									
									//ob_start(); var_dump($offer);var_dump($offer['id']); $var = ob_get_flush();
									//$woocommerce->add_error($var);
									
									if(isset($user_sub['error']) && strlen($user_sub['error']) > 0){
										$woocommerce->add_error(__($user_sub['error'], 'paymill'));
										return;
									}else{
										$query = 'INSERT INTO '.$wpdb->prefix.'paymill_subscriptions (paymill_sub_id, woo_user_id, woo_offer_id) VALUES ("'.$user_sub['id'].'", "'.$userInfo->ID.'", "'.$woo_sub_key.'")';
										$wpdb->query($query);
									
										// subscription successful
											do_action('paymill_paybutton_subscription_created', array(
												'product_id'	=> $id,
												'offer_id'		=> $offer['id'],
												'offer_data'	=> $offer
										));
									}
							
								}
							}
						}
						
						// make transaction (single time)
						if($total > 0){
							$transactionsObject = new Services_Paymill_Transactions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);

							// make transaction
							$params = array(
								'amount'      	=> $total,  // e.g. "4200" for 42.00 EUR
								'currency'   	=> get_woocommerce_currency(),   // ISO 4217
								'payment'		=> $paymentClass->getPaymentID(),
								'client'     	=> $client['id'],
								'description'	=> 'Order #'.$order_id,
								'source'		=> serialize($GLOBALS['paymill_source'])
							);				
							$transaction        = $transactionsObject->create($params);

							$response = $transactionsObject->getResponse();
							if(isset($response['body']['data']['response_code']) && $response['body']['data']['response_code'] != '20000'){
								echo __($response['body']['data']['response_code'], 'paymill');
								die();
							}

							// save data to transaction table
							$query = 'INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, woocommerce_order_id, paymill_transaction_data) VALUES ("'.$transaction['id'].'", "'.$transaction['payment']['id'].'", "'.$transaction['client']['id'].'", "'.$order_id.'", "'.$wpdb->escape(serialize($_POST)).'")';
							$wpdb->query($query);
							
							do_action('paymill_woocommerce_products_paid', array(
								'total'			=> $total,
								'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],
								'client'		=> $client['id']
							));
						}

					}
					
					//$woocommerce->add_error('ende');
					//return;
					
					// Mark as on-hold (we're awaiting the cheque)
					//$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));
					
					$order->payment_complete();

					// Reduce stock levels
					$order->reduce_order_stock();

					// Remove cart
					$woocommerce->cart->empty_cart();

					// Return thankyou redirect
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
				
				public function validate_fields(){
					global $woocommerce;
					// check Paymill payment
					if(empty($_POST['paymillToken'])){
						$woocommerce->add_error('Es konnte kein Token erstellt werden.');

						return false;
					}
					
					return true;
				}
				
				public function payment_fields(){
					global $woocommerce;

					if(!$GLOBALS['paymill_active']){
						// settings
						$GLOBALS['paymill_active'] = true;
						$cart_total = $woocommerce->cart->total*100;
						$currency = get_woocommerce_currency();
						$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos_v.png';
						$no_logos = true;
						
						// form ids
						echo '<script>
						paymill_form_checkout_id = ".checkout";
						paymill_form_checkout_submit_id = "#place_order";
						paymill_shop_name = "woocommerce";
						</script>';
			
						require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
					}else{
						echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
					}
					return true;
				}
			}
		}
	}
?>