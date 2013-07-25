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
			$GLOBALS['paymill_active'] = true;
			$country = 'DE';
			$cart_total = $this->amount('total')*100;
			$currency = $GLOBALS['paymill_settings']->paymill_general_settings['currency'];
			$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos.png';
			
			// form ids
			echo '<script>
			paymill_form_checkout_id = "#checkout";
			paymill_form_checkout_submit_id = "#checkout-button";
			paymill_shop_name = "shopplugin";
			</script>';
			
			// html / icons
			echo '<p style="margin-top:10px;"><a href="https://www.paymill.com" target="_blank"><img src="'.plugins_url('',__FILE__ ).'/../img/logo.png" alt="" /></a>';
			echo '<img src="'.plugins_url('',__FILE__ ).'/../img/creditcard-icons.png" alt="" />';
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

		$clientsObject = new Services_Paymill_Clients($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
		$transactionsObject = new Services_Paymill_Transactions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
		
		$client_new_email		= $order->Customer->email;
		$client_new_description	= $order->Customer->firstname.' '.$order->Customer->lastname;
		$total					= $orderTotals->total*100;
		$order_id				= $Event->order;
		
		if(get_current_user_id()){
			$user_id = get_current_user_id();
		}else{
			$user_id = 0;
		}
		
		$query				= 'SELECT * FROM '.$wpdb->prefix.'paymill_clients WHERE paymill_client_email="'.$client_new_email.'"';
		$client_cache		= $wpdb->get_results($query,ARRAY_A);
		
		// check wether it's a new client
		if(intval($client_cache[0]['wp_member_id']) == 0){
			// create new client in paymill
			$client        = $clientsObject->create(array(
				'email'       => $client_new_email, 
				'description' => $client_new_description
				));
			
			// insert new client in local cache
			$query = 'INSERT INTO '.$wpdb->prefix.'paymill_clients (paymill_client_id, paymill_client_email, paymill_client_description, wp_member_id) VALUES ("'.$client['id'].'", "'.$client_new_email.'", "'.$client_new_description.'", "'.$user_id.'")';
			$wpdb->query($query);
			
		// check wether cached userdata is still correct
		}elseif($client_cache[0]['paymill_client_email'] != $client_new_email || $client_cache[0]['paymill_client_description'] != $client_new_description){
			// update client in paymill
			$params = array(
				'id'          => $client_cache[0]['paymill_client_id'],
				'email'       => $client_new_email,
				'description' => $client_new_description
			);
			$client = $clientsObject->update($params);
			
			// update local cache
			$query = 'UPDATE '.$wpdb->prefix.'paymill_clients SET paymill_client_description="'.$client_new_description.'" WHERE paymill_client_email="'.$client_new_email.'"';
			$wpdb->query($query);
		
		// all still synced, just load client object for safety purposes
		}else{
			$client = $clientsObject->getOne($client_cache[0]['paymill_client_id']);
		}
		
		// make transaction
		$ordermsg = __('Order', 'paymill').' #'.$order_id.'<br />'.__('Forename', 'paymill').': '.strip_tags($order->Customer->firstname).'<br />'.__('Surname', 'paymill').': '.strip_tags($order->Customer->lastname);
		
		$params = array(
			'amount'		=> str_replace('.','',"$total"),  // e.g. "4200" for 42.00 EUR
			'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],   // ISO 4217
			'token'			=> $_POST['paymillToken'],
			'client'		=> $client['id'],
			'description'	=> $ordermsg,
			'source'		=> serialize($GLOBALS['paymill_source'])
		);				
		$transaction        = $transactionsObject->create($params);
		
		if(isset($transaction['error']) && (strlen($transaction['error']) > 0 || count($transaction['error']) > 0)){
				shopp_add_order_event($Purchase->id, 'auth-fail', array(
					'amount' => $orderTotals->total,	// Amount to be authorized
					'gateway' => $Event->gateway,		// Gateway handler name (module name from @subpackage)
					'message' => $transaction['error'],
				));
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