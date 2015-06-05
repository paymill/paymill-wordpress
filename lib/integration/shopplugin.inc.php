<?php

	if(!function_exists('paymill_shopp_errorHandling')){
		function paymill_shopp_errorHandling($errors){
			global $woocommerce;
			
			foreach($errors as $error){
				$output		.= '<div class="paymill_error">'.$error.'</div>';
			}

			return $output;
		}
	}



class PaymillShopp extends GatewayFramework implements GatewayModule {

	// Settings
	var $secure = false; // do not require SSL or session encryption
	var $saleonly = true; // force sale event on processing (no auth)
	var $recurring = false; // support for recurring payment

	function __construct(){
		parent::__construct();
		
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_shopp_errorHandling');
		
		if (!isset($this->settings['label'])){
			$this->settings['label'] = 'Paymill';
		}
		
		add_action('shopp_paymillshopp_sale',array(&$this,'sale')); // Process sales
		
		$GLOBALS['paymill_source']['shopp_version'] = SHOPP_VERSION;
	}
	/**
	 * actions
	 *
	 * These action callbacks are only established when the current Order::processor() is set to this module.
	 * All other general actions belong in the constructor
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function actions () {
		//add_action('shopp_process_checkout', array($this,'process')); // intercept checkout request, force confirm
		add_filter( 'shopp_checkout_gateway_inputs', array($this,'form'),10,2);
	}
	function form(){
		if(!$GLOBALS['paymill_active']){
			paymill_load_frontend_scripts(); // load frontend scripts
		
			// settings
			$GLOBALS['paymill_active']	= true;
			$cart_total					= $this->amount('total')*100;
			$currency					= $this->currency();
			$cc_logo					= plugins_url('',__FILE__ ).'/../img/cc_logos_v.png';
			$no_logos					= true;
			
			// form ids
			echo '<script>
			paymill_form_checkout_id = "#checkout";
			paymill_form_checkout_submit_id = "#checkout-button";
			paymill_shop_name = "shopplugin";
			</script>';
			
			// html / icons
			echo '<p style="margin-top:10px;" id="paymill_framebox">';
			$icon = '<a href="https://www.paymill.com/" target="_blank"><img src="'.plugins_url('',__FILE__ ).'/../img/logo.png" alt="PAYMILL" /></a>';
			if(isset($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && is_array($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && count($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) > 0){
				foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
					if($type==1){
						$icon .= '<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
					}
				}
			}
			echo $icon;
			echo '</p><div id="payment">';
			require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
			echo '</div><div style="background-image:url('.plugins_url('',__FILE__ ).'/../img/line.png);background-position:-300px;background-repeat:no-repeat;height:3px;line-height:3px;padding:0px;margin:5px 0px 10px 0px;"></div>';
		}else{
			echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
		}
	}
	private function getCurrentClient(){
		require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
		if(isset($this->order->Customer->firstname) && isset($this->order->Customer->lastname)){
			$desc		= $this->order->Customer->firstname.' '.$this->order->Customer->lastname;
		}elseif(isset($_POST['billing_first_name'])){
			$desc		= $this->order->Customer->firstname;
		}elseif(isset($_POST['billing_last_name'])){
			$desc		= $this->order->Customer->lastname;
		}else{
			$desc		= '';
		}
		
		// create or get client
		$this->clientClass	= new paymill_client($this->order->Customer->email,$desc);
		return $this->clientClass->getCurrentClient();
	}/*
	private function getTotals(){
		$this->total					= $this->Order->Cart->Totals;
		$this->total_complete			= $this->Order->Cart->Totals;
	}*/
	private function processSubscriptions(){
		// subscriptions for shopp not yet supported.
		return true;
	}
	private function processProducts(){
		global $wpdb;
		if($this->total > 0){
			// make transaction
			$GLOBALS['paymill_loader']->request_transaction->setAmount(round($this->total)); // e.g. "4200" for 42.00 EUR
			$GLOBALS['paymill_loader']->request_transaction->setCurrency($this->currency());
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
			INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, pay_button_order_id, paymill_transaction_time, paymill_transaction_data)
			VALUES (%s,%s,%s,%d,%d,%s)',
			array(
				$response['body']['data']['id'],
				$response['body']['data']['payment']['id'],
				$response['body']['data']['client']['id'],
				$this->order_id,
				$this->order_id,
				serialize($_POST)
			)));
			
			do_action('paymill_paybutton_products_paid', array(
				'total'			=> $this->total,
				'currency'		=> $this->currency(),
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
	function sale ($Event) {
		global $wpdb;

		$this->order_id				= $Event->order;
		$this->order_desc			= __('Order #','paymill').$this->order_id;
		$this->order				= $this->Order;
		$this->cart					= $this->order->Cart;
		$this->total_complete		= round((floatval($Event->amount)*100));
		$this->total				= $this->total_complete;
		$Billing					= $this->order->Billing;
		$Paymethod					= $this->order->paymethod();

		$this->client					= $this->getCurrentClient();
		
		// client retrieved, now we are ready to process the payment
		if($this->client->getId() !== false && strlen($this->client->getId()) > 0){
			// load subscription class
			//$this->subscriptions		= new paymill_subscriptions('shopp');
			//$this->offers				= $this->subscriptions->offerGetList();

			// get the totals for pre authorization
			//$this->getTotals();

			// create payment object and preauthorization
			require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');

			$this->paymentClass		= new paymill_payment($this->client->getId(),$this->total_complete,$this->currency()); // create payment object, as it should be used for next processing instead of the token.
			if($GLOBALS['paymill_loader']->paymill_errors->status()){
				$error = Shopp::__($GLOBALS['paymill_loader']->paymill_errors->getErrors());
				new ShoppError($error, 'paymill_error', SHOPP_TRXN_ERR);
				return shopp_add_order_event($Event->order, $Event->type . '-fail', array(
					'amount' => $Event->amount,
					'error' => 0,
					'message' => $error,
					'gateway' => $this->module
				));
						
				return false;
			}

			// process subscriptions & products
			if($this->processSubscriptions() && $this->processProducts()){
				// success
				// the order_id usually comes from the auth or sale event object $Event->order
				shopp_add_order_event( $Event->order, 'authed', array( 
					'txnid' => $transaction['payment']['id'],             // Transaction ID from payment gateway, in some cases will be in $Event->txnid
					'amount' => $orderTotals->total,               // Gross amount authorized
					'gateway' => $Event->gateway,   // Gateway handler name (module name from @subpackage)
					'paymethod' => $Paymethod->label,   // Payment method (payment method label from your payment settings)
					'paytype' => $Billing->cardtype,            // Type of payment (check, MasterCard, etc)
					'payid' => $Billing->card,              // Payment ID (last 4 of card or check number)
				));

				// either immediately after authed (in the case of a sale event)
				// or in response to a capture order event
				shopp_add_order_event( $Event->order, 'captured', array( 
					'txnid' => $transaction['payment']['id'],             // Transaction ID from payment gateway, in some cases will be in $Event->txnid
					'amount' => $orderTotals->total,               // Gross amount captured
					'gateway' => $Event->gateway,   // Gateway handler name (module name from @subpackage)
					'fees' => 0                  // Transaction fee assessed by the payment gateway
				));
				
				do_action('paymill_shopplugin_products_paid', array(
					'total'			=> $total,
					'currency'		=> $this->currency(),
					'client'		=> $client['id']
				));
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
	
	/*function process(){

	}*/

	/**
	 * Defines the settings interface
	 *
	 * @author Jonathan Davis
	 * @since 1.1
	 *
	 * @return void
	 **/
	function settings () {


	}

} // END class PaymillShopp

?>