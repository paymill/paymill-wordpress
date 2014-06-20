<?php
/*
MarketPress Paymill for WordPress Gateway Plugin
Author: Matthias Reuter
*/

class MP_Gateway_Paymill_for_WordPress extends MP_Gateway_API {

	//private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
	var $plugin_name = 'paymill-for-wordpress';

	//name of your gateway, for the admin side.
	var $admin_name = '';

	//public name of your gateway, for lists and such.
	var $public_name = '';

	//url for an image for your checkout method. Displayed on checkout form if set
	var $method_img_url = '';

	//url for an submit button image for your checkout method. Displayed on checkout form if set
	var $method_button_img_url = '';

	//whether or not ssl is needed for checkout page
	var $force_ssl = false;

	//always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
	var $ipn_url;

	//whether if this is the only enabled gateway it can skip the payment_form step
	var $skip_form = true;

	//only required for global capable gateways. The maximum stores that can checkout at once
	var $max_stores = 10;

	// Payment action
	var $payment_action = 'Sale';
	
	/* below are custom functions from Paymill */
	private function paymill_getCartTotal($cart){
		global $mp;
		
		$totals = array();
		foreach ($cart as $product_id => $variations) {
				foreach ($variations as $data) {
			$totals[] = $mp->before_tax_price($data['price'], $product_id) * $data['quantity'];
		  }
		}
		$total = array_sum($totals);

		  if ( $coupon = $mp->coupon_value($mp->get_coupon_code(), $total) ) {
			$total = $coupon['new_total'];
		  }

		  //shipping line
		  if ( ($shipping_price = $mp->shipping_price()) !== false ) {
			$total = $total + $shipping_price;
		  }

		  //tax line
		  if ( ($tax_price = $mp->tax_price()) !== false ) {
			$total = $total + $tax_price;
		  }
		  
		  return round(floatval($total)*100);
	}
	
	private function paymill_getCurrency(){
		global $mp;
		return $mp->get_setting('currency');
	}
	
	/****** Below are the public methods you may overwrite via a plugin ******/

	/**
	 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
	 */
	function on_creation() {
		global $mp;
		$settings = get_option('mp_settings');

		//set names here to be able to translate
		$this->admin_name = __('Paymill for WordPress', 'paymill');
		$this->public_name = __('Paymill', 'paymill');

		//button img
		$this->method_img_url = plugins_url('',__FILE__ ).'/../img/logo_small.png';
		$this->method_button_img_url = plugins_url('',__FILE__ ).'/../img/logo_small.png';
	}

	/**
	 * Echo fields you need to add to the payment screen, like your credit card info fields.
	 *	If you don't need to add form fields set $skip_form to true so this page can be skipped
	 *	at checkout.
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	 * @param array $shipping_info. Contains shipping info and email in case you need it
	 */
	function payment_form($global_cart, $shipping_info) {
		if(!$GLOBALS['paymill_active']){
			paymill_load_frontend_scripts(); // load frontend scripts

			// settings
			$GLOBALS['paymill_active']	= true;
			$cart_total					= $this->paymill_getCartTotal($global_cart);
			$currency					= $this->paymill_getCurrency();
			$no_logos					= false;
			
			ob_start();
			
			// form ids
			echo '<script>
			paymill_form_checkout_id = "#mp_payment_form";
			paymill_form_checkout_submit_id = "#mp_payment_confirm";
			paymill_shop_name = "marketpress";
			</script>';

			require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
			
			return ob_get_clean();
		}else{
			echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
		}
	}

	/**
	 * Use this to authorize ordered transactions.
	 *
	 * @param array $order Contains the list of order ids
	 */
	function process_payment_authorize($orders) {
		if (is_array($orders)) {
			foreach ($orders as $order) {
				$transaction_id = $order['transaction_id'];
				$amount = $order['amount'];

				$authorization = $this->DoAuthorization($transaction_id, $amount);

				switch ($result["PAYMENTSTATUS"]) {
					case 'Canceled-Reversal':
						$status = __('A reversal has been canceled; for example, when you win a dispute and the funds for the reversal have been returned to you.', 'mp');
						$authorized = true;
						break;
					case 'Expired':
						$status = __('The authorization period for this payment has been reached.', 'mp');
						$authorized = false;
						break;
					case 'Voided':
						$status = __('An authorization for this transaction has been voided.', 'mp');
						$authorized = false;
						break;
					case 'Failed':
						$status = __('The payment has failed. This happens only if the payment was made from your customer\'s bank account.', 'mp');
						$authorized = false;
						break;
					case 'Partially-Refunded':
						$status = __('The payment has been partially refunded.', 'mp');
						$authorized = true;
						break;
					case 'In-Progress':
						$status = __('The transaction has not terminated, e.g. an authorization may be awaiting completion.', 'mp');
						$authorized = false;
						break;
					case 'Completed':
						$status = __('The payment has been completed, and the funds have been added successfully to your account balance.', 'mp');
						$authorized = true;
						break;
					case 'Processed':
						$status = __('A payment has been accepted.', 'mp');
						$authorized = true;
						break;
					case 'Reversed':
						$status = __('A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance and returned to the buyer', 'mp');
						$reverse_reasons = array(
							'none' => '',
							'chargeback' => __('A reversal has occurred on this transaction due to a chargeback by your customer.', 'mp'),
							'guarantee' => __('A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'mp'),
							'buyer-complaint' => __('A reversal has occurred on this transaction due to a complaint about the transaction from your customer.', 'mp'),
							'refund' => __('A reversal has occurred on this transaction because you have given the customer a refund.', 'mp'),
							'other' => __('A reversal has occurred on this transaction due to an unknown reason.', 'mp')
							);
						$status .= ': ' . $reverse_reasons[$result["REASONCODE"]];
						$authorized = false;
						break;
					case 'Refunded':
						$status = __('You refunded the payment.', 'mp');
						$authorized = false;
						break;
					case 'Denied':
						$status = __('You denied the payment when it was marked as pending.', 'mp');
						$authorized = false;
						break;
					case 'Pending':
						$pending_str = array(
							'address' => __('The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set such that you want to manually accept or deny each of these payments. To change your preference, go to the Preferences	section of your Profile.', 'mp'),
							'authorization' => __('The payment is pending because it has been authorized but not settled. You must capture the funds first.', 'mp'),
							'echeck' => __('The payment is pending because it was made by an eCheck that has not yet cleared.', 'mp'),
							'intl' => __('The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'mp'),
							'multi-currency' => __('You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'mp'),
							'order' => __('The payment is pending because it is part of an order that has been authorized but not settled.', 'mp'),
							'paymentreview' => __('The payment is pending while it is being reviewed by PayPal for risk.', 'mp'),
							'unilateral' => __('The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'mp'),
							'upgrade' => __('The payment is pending because it was made via credit card and you must upgrade your account to Business or Premier status in order to receive the funds. It can also mean that you have reached the monthly limit for transactions on your account.', 'mp'),
							'verify' => __('The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'mp'),
							'other' => __('The payment is pending for an unknown reason. For more information, contact PayPal customer service.', 'mp'),
							'*' => ''
						);
						$status = __('The payment is pending', 'mp');
						if (isset($pending_str[$result["PENDINGREASON"]]))
							$status .= ': ' . $pending_str[$result["PENDINGREASON"]];
						$authorized = false;
						break;
					default:
						// case: various error cases
						$authorized = false;
				}

				if ($authorized) {
					update_post_meta($order['order_id'], 'mp_deal', 'authorized');
					update_post_meta($order['order_id'], 'mp_deal_authorization_id', $authorization['TRANSACTIONID']);
				}
			}
		}
	}

	/**
	 * Use this to capture authorized transactions.
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	 * @param array $authorizations Contains the list of authorization ids
	 */
	function process_payment_capture($authorizations) {
		if (is_array($authorizations)) {
			foreach ($authorizations as $authorization) {
				$transaction_id = $authorization['transaction_id'];
				$amount = $authorization['amount'];

				$capture = $this->DoCapture($transaction_id, $amount);

				update_post_meta($authorization['deal_id'], 'mp_deal', 'captured');
			}
		}
	}

	/**
	 * Use this to process any fields you added. Use the $_POST global,
	 *	and be sure to save it to both the $_SESSION and usermeta if logged in.
	 *	DO NOT save credit card details to usermeta as it's not PCI compliant.
	 *	Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
	 *	it will redirect to the next step.
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	 * @param array $shipping_info. Contains shipping info and email in case you need it
	 */
	function process_payment_form($global_cart, $shipping_info) {
		global $mp;

		//create order id for paypal invoice
		$order_id = $mp->generate_order_id();
		/*
		foreach ($global_cart as $bid => $cart) {
			foreach ($cart as $product_id => $data) {
				if ('deal' == get_post_type($product_id)) {
					$this->payment_action = 'Order';
				}
			}
		}
		*/
		//set it up with PayPal
		$result = $this->SetExpressCheckout($global_cart, $shipping_info, $order_id);

		//check response
		if($result["ACK"] == "Success" || $result["ACK"] == "SuccessWithWarning")	{
			$token = urldecode($result["TOKEN"]);
			$this->RedirectToPayPal($token);
		} else { //whoops, error
			for ($i = 0; $i <= 5; $i++) { //print the first 5 errors
				if (isset($result["L_ERRORCODE$i"])) {
					$error .= "<li>{$result["L_ERRORCODE$i"]} - {$result["L_SHORTMESSAGE$i"]} - {$result["L_LONGMESSAGE$i"]}</li>";
				}
			}
			$error = '<br /><ul>' . $error . '</ul>';
			$mp->cart_checkout_error( __('There was a problem connecting to PayPal to setup your purchase. Please try again.', 'mp') . $error );
		}
	}

	/**
	 * Return the chosen payment details here for final confirmation. You probably don't need
	 *	to post anything in the form as it should be in your $_SESSION var already.
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	 * @param array $shipping_info. Contains shipping info and email in case you need it
	 */
	function confirm_payment_form($global_cart, $shipping_info) {
		global $mp;

		$content = '';

		if (isset($_GET['token']) && isset($_GET['PayerID'])) {
			$_SESSION['token'] = $_GET['token'];
			$_SESSION['PayerID'] = $_GET['PayerID'];

			//get details from PayPal
			$result = $this->GetExpressCheckoutDetails($_SESSION['token']);

			//check response
			if($result["ACK"] == "Success" || $result["ACK"] == "SuccessWithWarning")	{

				$account_name = ($result["BUSINESS"]) ? $result["BUSINESS"] : $result["EMAIL"];

				//set final amount
				$_SESSION['final_amt'] = 0;
				$_SESSION['store_count'] = 0;

				for ($i=0; $i<10; $i++) {
					if (!isset($result['PAYMENTREQUEST_'.$i.'_AMT'])) {
						continue;
					}
					$_SESSION['final_amt'] += $result['PAYMENTREQUEST_'.$i.'_AMT'];
					$_SESSION['store_count']++;
				}

				//print payment details
				$content .= '<p>' . sprintf(__('Please confirm your final payment for this order totaling %s. It will be made via your "%s" PayPal account.', 'mp'), $mp->format_currency('', $_SESSION['final_amt']), $account_name) . '</p>';

			} else { //whoops, error
				for ($i = 0; $i <= 5; $i++) { //print the first 5 errors
					if (isset($result["L_ERRORCODE$i"]))
						$error .= "<li>{$result["L_ERRORCODE$i"]} - {$result["L_SHORTMESSAGE$i"]} - {$result["L_LONGMESSAGE$i"]}</li>";
				}
				$error = '<br /><ul>' . $error . '</ul>';
				$content .= '<div class="mp_checkout_error">' . sprintf(__('There was a problem with your PayPal transaction. Please <a href="%s">go back and try again</a>.', 'mp'), mp_checkout_step_url('checkout')) . $error . '</div>';
			}

		} else {
			$content .= '<div class="mp_checkout_error">' . sprintf(__('Whoops, looks like you skipped a step! Please <a href="%s">go back and try again</a>.', 'mp'), mp_checkout_step_url('checkout')) . '</div>';
		}

		return $content;
	}

	/**
	 * Use this to do the final payment. Create the order then process the payment. If
	 *	you know the payment is successful right away go ahead and change the order status
	 *	as well.
	 *	Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
	 *	it will redirect to the next step.
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	 * @param array $shipping_info. Contains shipping info and email in case you need it
	 */
	function process_payment($global_cart, $shipping_info) {
		global $mp, $blog_id, $site_id, $switched_stack, $switched;
		
		$blog_id = (is_multisite()) ? $blog_id : 1;
		$current_blog_id = $blog_id;

		if (!$mp->global_cart)
			$selected_cart[$blog_id] = $global_cart;
		else
			$selected_cart = $global_cart;

		if (isset($_SESSION['token']) && isset($_SESSION['PayerID']) && isset($_SESSION['final_amt'])) {
			//attempt the final payment
			$result = $this->DoExpressCheckoutPayment($_SESSION['token'], $_SESSION['PayerID']);

			//check response
			if($result["ACK"] == "Success" || $result["ACK"] == "SuccessWithWarning")	{

				//setup our payment details
				$payment_info['gateway_public_name'] = $this->public_name;
				$payment_info['gateway_private_name'] = $this->admin_name;
				for ($i=0; $i<10; $i++) {
					if (!isset($result['PAYMENTINFO_'.$i.'_PAYMENTTYPE'])) {
						continue;
					}
					$payment_info['method'] = ($result["PAYMENTINFO_{$i}_PAYMENTTYPE"] == 'echeck') ? __('eCheck', 'mp') : __('PayPal balance, Credit Card, or Instant Transfer', 'mp');
					$payment_info['transaction_id'] = $result["PAYMENTINFO_{$i}_TRANSACTIONID"];

					$timestamp = time();//strtotime($result["PAYMENTINFO_{$i}_ORDERTIME"]);
					//setup status
					switch ($result["PAYMENTINFO_{$i}_PAYMENTSTATUS"]) {
						case 'Canceled-Reversal':
							$status = __('A reversal has been canceled; for example, when you win a dispute and the funds for the reversal have been returned to you.', 'mp');
							$paid = true;
							break;
						case 'Expired':
							$status = __('The authorization period for this payment has been reached.', 'mp');
							$paid = false;
							break;
						case 'Voided':
							$status = __('An authorization for this transaction has been voided.', 'mp');
							$paid = false;
							break;
						case 'Failed':
							$status = __('The payment has failed. This happens only if the payment was made from your customer\'s bank account.', 'mp');
							$paid = false;
							break;
						case 'Partially-Refunded':
							$status = __('The payment has been partially refunded.', 'mp');
							$paid = true;
							break;
						case 'In-Progress':
							$status = __('The transaction has not terminated, e.g. an authorization may be awaiting completion.', 'mp');
							$paid = false;
							break;
						case 'Completed':
							$status = __('The payment has been completed, and the funds have been added successfully to your account balance.', 'mp');
							$paid = true;
							break;
						case 'Processed':
							$status = __('A payment has been accepted.', 'mp');
							$paid = true;
							break;
						case 'Reversed':
							$status = __('A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance and returned to the buyer:', 'mp');
							$reverse_reasons = array(
								'none' => '',
								'chargeback' => __('A reversal has occurred on this transaction due to a chargeback by your customer.', 'mp'),
								'guarantee' => __('A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'mp'),
								'buyer-complaint' => __('A reversal has occurred on this transaction due to a complaint about the transaction from your customer.', 'mp'),
								'refund' => __('A reversal has occurred on this transaction because you have given the customer a refund.', 'mp'),
								'other' => __('A reversal has occurred on this transaction due to an unknown reason.', 'mp')
								);
							$status .= '<br />' . $reverse_reasons[$result["PAYMENTINFO_{$i}_REASONCODE"]];
							$paid = false;
							break;
						case 'Refunded':
							$status = __('You refunded the payment.', 'mp');
							$paid = false;
							break;
						case 'Denied':
							$status = __('You denied the payment when it was marked as pending.', 'mp');
							$paid = false;
							break;
						case 'Pending':
							$pending_str = array(
								'address' => __('The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set such that you want to manually accept or deny each of these payments. To change your preference, go to the Preferences	section of your Profile.', 'mp'),
								'authorization' => __('The payment is pending because it has been authorized but not settled. You must capture the funds first.', 'mp'),
								'echeck' => __('The payment is pending because it was made by an eCheck that has not yet cleared.', 'mp'),
								'intl' => __('The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'mp'),
								'multi-currency' => __('You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'mp'),
								'order' => __('The payment is pending because it is part of an order that has been authorized but not settled.', 'mp'),
								'paymentreview' => __('The payment is pending while it is being reviewed by PayPal for risk.', 'mp'),
								'unilateral' => __('The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'mp'),
								'upgrade' => __('The payment is pending because it was made via credit card and you must upgrade your account to Business or Premier status in order to receive the funds. It can also mean that you have reached the monthly limit for transactions on your account.', 'mp'),
								'verify' => __('The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'mp'),
								'other' => __('The payment is pending for an unknown reason. For more information, contact PayPal customer service.', 'mp'),
								'*' => ''
							);
							$status = __('The payment is pending.', 'mp');
							$status .= '<br />' . $pending_str[$result["PAYMENTINFO_{$i}_PENDINGREASON"]];
							$paid = false;
							break;
						default:
							// case: various error cases
							$paid = false;
					}
					$status = $result["PAYMENTINFO_{$i}_PAYMENTSTATUS"] . ': '. $status;

					//status's are stored as an array with unix timestamp as key
					$payment_info['status'] = array();
					$payment_info['status'][$timestamp] = $status;
					$payment_info['currency'] = $result["PAYMENTINFO_{$i}_CURRENCYCODE"];
					$payment_info['total'] = $result["PAYMENTINFO_{$i}_AMT"];

					$payment_info['note'] = $result["NOTE"]; //optional, only shown if gateway supports it

					//figure out blog_id of this payment to put the order into it
					$unique_id = ($result["PAYMENTINFO_{$i}_PAYMENTREQUESTID"]) ? $result["PAYMENTINFO_{$i}_PAYMENTREQUESTID"] : $result["PAYMENTREQUEST_{$i}_PAYMENTREQUESTID"]; //paypal docs messed up, not sure which is valid return
					@list($bid, $order_id) = explode(':', $unique_id);
			
					if (is_multisite())	
						switch_to_blog($bid, true);

					//succesful payment, create our order now
					$mp->create_order($_SESSION['mp_order'], $selected_cart[$bid], $shipping_info, $payment_info, $paid);
				}	
		
				if (is_multisite())
					switch_to_blog($current_blog_id, true);
				
				//success. Do nothing, it will take us to the confirmation page
			} else { //whoops, error

				for ($i = 0; $i <= 5; $i++) { //print the first 5 errors
					if (isset($result["L_ERRORCODE$i"]))
						$error .= "<li>{$result["L_ERRORCODE$i"]} - {$result["L_SHORTMESSAGE$i"]} - ".stripslashes($result["L_LONGMESSAGE$i"])."</li>";
				}
				$error = '<br /><ul>' . $error . '</ul>';
				$mp->cart_checkout_error( sprintf(__('There was a problem finalizing your purchase with PayPal. Please <a href="%s">go back and try again</a>.', 'mp'), mp_checkout_step_url('checkout')) . $error );
			}
		} else {
			$mp->cart_checkout_error( sprintf(__('There was a problem finalizing your purchase with PayPal. Please <a href="%s">go back and try again</a>.', 'mp'), mp_checkout_step_url('checkout')) );
		}
	}

	/**
	 * Runs before page load incase you need to run any scripts before loading the success message page
	 */
	function order_confirmation($order) {

	}

	/**
	 * Filters the order confirmation email message body. You may want to append something to
	 *	the message. Optional
	 *
	 * Don't forget to return!
	 */
	function order_confirmation_email($msg, $order) {
		return $msg;
	}

	/**
	 * Return any html you want to show on the confirmation screen after checkout. This
	 *	should be a payment details box and message.
	 *
	 * Don't forget to return!
	 */
	function order_confirmation_msg($content, $order) {
		global $mp;

		if ($mp->global_cart) {
			$content .= '<p>' . sprintf(__('Your order(s) for %s store(s) totaling %s were successful.', 'mp'), $_SESSION['store_count'], $mp->format_currency($this->currencyCode, $_SESSION['final_amt'])) . '</p>';
			/* TODO - create a list of sep store orders*/
		} else {
			if ($order->post_status == 'order_received') {
				$content .= '<p>' . sprintf(__('Your PayPal payment for this order totaling %s is not yet complete. Here is the latest status:', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
				$statuses = $order->mp_payment_info['status'];
				krsort($statuses); //sort with latest status at the top
				$status = reset($statuses);
				$timestamp = key($statuses);
				$content .= '<p><strong>' . $mp->format_date($timestamp) . ':</strong> ' . esc_html($status) . '</p>';
			} else {
				$content .= '<p>' . sprintf(__('Your PayPal payment for this order totaling %s is complete. The PayPal transaction number is <strong>%s</strong>.', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
			}
		}
		return $content;
	}

	/**
	 * Echo a settings meta box with whatever settings you need for you gateway.
	 *	Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
	 *	You can access saved settings via $settings array.
	 */
	function gateway_settings_box($settings) {
		global $mp;
	}

	/**
	 * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
	 *	array. Don't forget to return!
	 */
	function process_gateway_settings($settings) {

		return $settings;
	}

	/**
	 * Use to handle any payment returns from your gateway to the ipn_url. Do not echo anything here. If you encounter errors
	 *	return the proper headers to your ipn sender. Exits after.
	 */
	function process_ipn_return() {
		global $mp;

		// PayPal IPN handling code
		if (isset($_POST['payment_status']) || isset($_POST['txn_type'])) {

			if ($mp->get_setting('gateways->paypal-express->mode') == 'sandbox') {
				$domain = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
			} else {
				$domain = 'https://www.paypal.com/cgi-bin/webscr';
			}

			$req = 'cmd=_notify-validate';
			if (!isset($_POST)) $_POST = $HTTP_POST_VARS;
			foreach ($_POST as $k => $v) {
				if (get_magic_quotes_gpc()) $v = stripslashes($v);
				$req .= '&' . $k . '=' . urlencode($v);
			}

			$args['user-agent'] = "MarketPress/{$mp->version}: http://premium.wpmudev.org/project/e-commerce | PayPal Express Plugin/{$mp->version}";
			$args['body'] = $req;
			$args['sslverify'] = false;
			$args['timeout'] = 30;

			//use built in WP http class to work with most server setups
			$response = wp_remote_post($domain, $args);

			//check results
			if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 || $response['body'] != 'VERIFIED') {
				header("HTTP/1.1 503 Service Unavailable");
				_e( 'There was a problem verifying the IPN string with PayPal. Please try again.', 'mp' );
				exit;
			}

			// process PayPal response
			switch ($_POST['payment_status']) {

				case 'Canceled-Reversal':
					$status = __('A reversal has been canceled; for example, when you win a dispute and the funds for the reversal have been returned to you.', 'mp');
					$paid = true;
					break;

				case 'Expired':
					$status = __('The authorization period for this payment has been reached.', 'mp');
					$paid = false;
					break;

				case 'Voided':
					$status = __('An authorization for this transaction has been voided.', 'mp');
					$paid = false;
					break;

				case 'Failed':
					$status = __("The payment has failed. This happens only if the payment was made from your customer's bank account.", 'mp');
					$paid = false;
					break;

	 			case 'Partially-Refunded':
					$status = __('The payment has been partially refunded.', 'mp');
					$paid = true;
					break;

				case 'In-Progress':
					$status = __('The transaction has not terminated, e.g. an authorization may be awaiting completion.', 'mp');
					$paid = false;
					break;

				case 'Completed':
					$status = __('The payment has been completed, and the funds have been added successfully to your account balance.', 'mp');
					$paid = true;
					break;

				case 'Processed':
					$status = __('A payment has been accepted.', 'mp');
					$paid = true;
					break;

				case 'Reversed':
					$status = __('A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance and returned to the buyer:', 'mp');
					$reverse_reasons = array(
						'none' => '',
						'chargeback' => __('A reversal has occurred on this transaction due to a chargeback by your customer.', 'mp'),
						'guarantee' => __('A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'mp'),
						'buyer-complaint' => __('A reversal has occurred on this transaction due to a complaint about the transaction from your customer.', 'mp'),
						'refund' => __('A reversal has occurred on this transaction because you have given the customer a refund.', 'mp'),
						'other' => __('A reversal has occurred on this transaction due to an unknown reason.', 'mp')
						);
					$status .= '<br />' . $reverse_reasons[$result["PAYMENTINFO_0_REASONCODE"]];
					$paid = false;
					break;

				case 'Refunded':
					$status = __('You refunded the payment.', 'mp');
					$paid = false;
					break;

				case 'Denied':
					$status = __('You denied the payment when it was marked as pending.', 'mp');
					$paid = false;
					break;

				case 'Pending':
					$pending_str = array(
						'address' => __('The payment is pending because your customer did not include a confirmed shipping address and your Payment Receiving Preferences is set such that you want to manually accept or deny each of these payments. To change your preference, go to the Preferences	section of your Profile.', 'mp'),
						'authorization' => __('The payment is pending because it has been authorized but not settled. You must capture the funds first.', 'mp'),
						'echeck' => __('The payment is pending because it was made by an eCheck that has not yet cleared.', 'mp'),
						'intl' => __('The payment is pending because you hold a non-U.S. account and do not have a withdrawal mechanism. You must manually accept or deny this payment from your Account Overview.', 'mp'),
						'multi-currency' => __('You do not have a balance in the currency sent, and you do not have your Payment Receiving Preferences set to automatically convert and accept this payment. You must manually accept or deny this payment.', 'mp'),
						'order' => __('The payment is pending because it is part of an order that has been authorized but not settled.', 'mp'),
						'paymentreview' => __('The payment is pending while it is being reviewed by PayPal for risk.', 'mp'),
						'unilateral' => __('The payment is pending because it was made to an email address that is not yet registered or confirmed.', 'mp'),
						'upgrade' => __('The payment is pending because it was made via credit card and you must upgrade your account to Business or Premier status in order to receive the funds. It can also mean that you have reached the monthly limit for transactions on your account.', 'mp'),
						'verify' => __('The payment is pending because you are not yet verified. You must verify your account before you can accept this payment.', 'mp'),
						'other' => __('The payment is pending for an unknown reason. For more information, contact PayPal customer service.', 'mp'),
						'*' => ''
						);
					$status = __('The payment is pending.', 'mp');
					$status .= '<br />' . $pending_str[$_POST["pending_reason"]];
					$paid = false;
					break;

				default:
					// case: various error cases
			}
			$status = $_POST['payment_status'] . ': '. $status;

			//record transaction
			$mp->update_order_payment_status($_POST['invoice'], $status, $paid);

		} else {
			// Did not find expected POST variables. Possible access attempt from a non PayPal site.
			header('Status: 404 Not Found');
			echo 'Error: Missing POST variables. Identification is not possible.';
			exit;
		}
	}
}

//register shipping plugin
mp_register_gateway_plugin( 'MP_Gateway_Paymill_for_Wordpress', 'paymill-for-wordpress', __('Paymill for WordPress', 'paymill'), true );