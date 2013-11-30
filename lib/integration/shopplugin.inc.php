<?php
class PaymillShopp extends GatewayFramework implements GatewayModule {

	// Settings
	var $secure = false; // do not require SSL or session encryption
	var $saleonly = true; // force sale event on processing (no auth)
	var $recurring = false; // support for recurring payment

	function __construct () {
		parent::__construct();

		if (!isset($this->settings['label'])){
			$this->settings['label'] = "Paymill";
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
			// settings
			$GLOBALS['paymill_active']	= true;
			$cart_total					= $this->amount('total')*100;
			$currency					= $GLOBALS['paymill_settings']->paymill_general_settings['currency'];
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
	
	function sale ($Event) {
		global $wpdb;

		$order = $this->Order;
		$orderTotals = $order->Cart->Totals;
		$Billing = $order->Billing;
		$Paymethod = $order->paymethod();
		
		
		// first retrieve client data, either from cache or from API
		require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
		$clientClass			= new paymill_client(
									$order->Customer->email,
									$order->Customer->firstname.' '.$order->Customer->lastname
								);
		
		$client					= $clientClass->getCurrentClient();

		// client retrieved, now we are ready to process the payment
		if($client['id'] !== false && strlen($client['id']) > 0){
			require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
			
			$paymentClass		= new paymill_payment($client['id']);
			
			// calculate total based on product settings
			$total = (floatval($orderTotals->total)*100);
		
			$order_id				= $Event->order;
	
			// make transaction (single time)
			if($total > 0){
				$transactionsObject = new Services_Paymill_Transactions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
			
				$ordermsg = __('Order', 'paymill').' #'.$order_id.' '.__('Forename', 'paymill').': '.strip_tags($order->Customer->firstname).' '.__('Surname', 'paymill').': '.strip_tags($order->Customer->lastname);
				
				$params = array(
					'amount'		=> str_replace('.','',"$total"),  // e.g. "4200" for 42.00 EUR
					'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],   // ISO 4217
					'payment'		=> $paymentClass->getPaymentID(),
					'client'		=> $client['id'],
					'description'	=> $ordermsg,
					'source'		=> serialize($GLOBALS['paymill_source'])
				);				
				$transaction        = $transactionsObject->create($params);
				
				$response = $transactionsObject->getResponse();
				if(isset($response['body']['data']['response_code']) && $response['body']['data']['response_code'] != '20000'){
						shopp_add_order_event($Purchase->id, 'auth-fail', array(
							'amount' => $orderTotals->total,	// Amount to be authorized
							'gateway' => $Event->gateway,		// Gateway handler name (module name from @subpackage)
							'message' => $response['body']['data']['response_code'], 'paymill')
						);
				}else{
					// save data to transaction table
					$query = 'INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, shopplugin_order_id, paymill_transaction_time) VALUES ("'.$transaction['id'].'", "'.$transaction['payment']['id'].'", "'.$transaction['client']['id'].'", "'.$order_id.'", "'.time().'")';
					$wpdb->query($query);
				
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
						'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],
						'client'		=> $client['id']
					));
				}
			}
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