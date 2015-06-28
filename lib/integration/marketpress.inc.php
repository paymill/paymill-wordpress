<?php
/*
MarketPress Paymill for WordPress Gateway Plugin
Author: Matthias Reuter
*/

if(!function_exists('paymill_marketpress_errorHandling')){
	function paymill_marketpress_errorHandling($errors){
		global $mp;
		foreach($errors as $error){
			$mp->cart_checkout_error('<div class="paymill_error">'.$error.'. '.sprintf(__('Please <a href="%s">go back and try again</a>.', 'paymill'), mp_checkout_step_url('checkout')).'</div>');
		}
	}
}

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
	
	private function getCurrentClient(){
		require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
		
		if(isset($_SESSION['name'])){
			$desc		= $_SESSION['name'];
		}else{
			$desc		= '';
		}
		
		// create or get client
		$this->clientClass	= new paymill_client($_SESSION['email'],$desc);
		return $this->clientClass->getCurrentClient();
	}
	
	private function processSubscriptions(){
		return true;
	}
	private function processProducts(){
		global $wpdb;
		if($this->total > 0){
			// make transaction
			$GLOBALS['paymill_loader']->request_transaction->setAmount(round($this->total,2)); // e.g. "4200" for 42.00 EUR
			$GLOBALS['paymill_loader']->request_transaction->setCurrency($this->currency);
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
			INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, marketpress_order_id, paymill_transaction_time, paymill_transaction_data)
			VALUES (%s,%s,%s,%d,%d,%s)',
			array(
				$response['body']['data']['id'],
				$response['body']['data']['payment']['id'],
				$response['body']['data']['client']['id'],
				$this->order_id,
				time(),
				serialize($_POST)
			)));
			
			do_action('paymill_marketpress_products_paid', array(
				'total'			=> $this->total,
				'currency'		=> $this->currency,
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
	
	/****** Below are the public methods you may overwrite via a plugin ******/

	/**
	 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
	 */
	function on_creation() {
		global $mp;
		$settings							= get_option('mp_settings');

		//set names here to be able to translate
		$this->admin_name					= __('Paymill for WordPress', 'paymill');
		$this->public_name					= $this->method_button_img_url = $settings['gateways']['paymill-for-wordpress']['name'];

		//button img
		$this->method_img_url				=  $this->method_button_img_url = $settings['gateways']['paymill-for-wordpress']['image-url'];

		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_marketpress_errorHandling');
		$GLOBALS['paymill_source']['marketpress_version'] = $mp->version;
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
		global $mp;
		
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
			paymill_pcidss3 = '.((empty($GLOBALS['paymill_settings']->paymill_general_settings['pci_dss_3']) || $GLOBALS['paymill_settings']->paymill_general_settings['pci_dss_3'] != '1') ? 1 : 0).';
			paymill_pcidss3_lang = "'.substr(apply_filters('plugin_locale', get_locale(), $domain),0,2).'";
			</script>';
			
			echo do_shortcode($mp->get_setting('gateways->paymill-for-wordpress->instructions'));

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

	}

	/**
	 * Use this to capture authorized transactions.
	 *
	 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	 * @param array $authorizations Contains the list of authorization ids
	 */
	function process_payment_capture($authorizations) {
	
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
		if (isset($_POST['paymillToken'])){
			$_SESSION['paymillToken'] = $_POST['paymillToken'];
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
		global $mp, $blog_id, $site_id, $switched_stack, $switched, $wpdb;
		
		$blog_id = (is_multisite()) ? $blog_id : 1;
		$current_blog_id = $blog_id;

		if (!$mp->global_cart){
			$selected_cart[$blog_id] = $global_cart;
		}else{
			$selected_cart = $global_cart;
		}

		if (isset($_SESSION['paymillToken'])){
			$_POST['paymillToken']			= $_SESSION['paymillToken'];
		
			$this->client					= $this->getCurrentClient();
			// client retrieved, now we are ready to process the payment
			if($this->client->getId() !== false && strlen($this->client->getId()) > 0){
				$this->order_id				= $mp->generate_order_id();
				$this->order_desc			= __('Order #','paymill').$this->order_id;
				$this->cart					= $selected_cart;
				$this->total_complete		=
				$this->total				= $this->paymill_getCartTotal($global_cart);
				$this->currency				= $this->paymill_getCurrency();

			
				// load subscription class
				//$this->subscriptions		= new paymill_subscriptions('marketpress');
				//$this->offers				= $this->subscriptions->offerGetList();

				// get the totals for pre authorization
				//$this->getTotals();

				// create payment object and preauthorization
				require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
				$this->paymentClass		= new paymill_payment($this->client->getId(),$this->total_complete,$this->currency); // create payment object, as it should be used for next processing instead of the token.
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					$GLOBALS['paymill_loader']->paymill_errors->getErrors();
					return false;
				}

				// process subscriptions & products
				if($this->processSubscriptions() && $this->processProducts()){
					// success
					$timestamp									= time();
					
					//setup our payment details
					$payment_info['gateway_public_name']		= $this->public_name;
					$payment_info['gateway_private_name']		= $this->admin_name;
					$payment_info['method']						= 'Paymill';
					$payment_info['transaction_id']				= $this->order_id; // todo: insert real paymill transaction id

					//status's are stored as an array with unix timestamp as key
					$payment_info['status']						= array();
					$payment_info['status'][$timestamp]			= 'success';
					$payment_info['currency']					= $this->currency;
					$payment_info['total']						= ($this->total_complete/100);
					//$payment_info['note']						= $result["NOTE"]; //optional, only shown if gateway supports it

					//succesful payment, create our order now
					// last parameter: paid = true / false
					$mp->create_order($_SESSION['mp_order'], $global_cart, $shipping_info, $payment_info, true);

					if (is_multisite()){
						switch_to_blog($current_blog_id, true);
					}
					//success. Do nothing, it will take us to the confirmation page
					
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
		} else {
			$GLOBALS['paymill_loader']->paymill_errors->setError(__('There was a problem finalizing your purchase with Paymill.', 'paymill'));
			return false;
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

		if ($mp->global_cart){
			$content .= '<p>' . sprintf(__('Your order(s) for %s store(s) totaling %s were successful.', 'mp'), $_SESSION['store_count'], $mp->format_currency($this->currencyCode, $_SESSION['final_amt'])) . '</p>';
			/* TODO - create a list of sep store orders*/
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
    ?>
    <div id="mp_paymill_for_wordpress" class="postbox mp-pages-msgs">
    	<h3 class='handle'><span><?php _e('Paymill for WordPress Settings', 'paymill'); ?></span></h3>
      <div class="inside">
	      <span class="description"><?php _e('Pay with credit card and SEPA', 'paymill') ?></span>
	      <table class="form-table">
		      <tr>
						<th scope="row"><label for="paymill-for-wordpress-name"><?php _e('Method Name', 'mp') ?></label></th>
						<td>
		  				<span class="description"><?php _e('Enter a public name for this payment method that is displayed to users - No HTML', 'mp') ?></span>
		          <p>
		          <input value="<?php echo esc_attr($mp->get_setting('gateways->paymill-for-wordpress->name') ? $mp->get_setting('gateways->paymill-for-wordpress->name', __('Paymill', 'mp')) : __('Paymill', 'mp')); ?>" style="width: 100%;" name="mp[gateways][paymill-for-wordpress][name]" id="paymill-for-wordpress-name" type="text" />
		          </p>
		        </td>
	        </tr>
		      <tr>
		        <th scope="row"><label for="paymill-for-wordpress-instructions"><?php _e('User Instructions', 'mp') ?></label></th>
		        <td>
		        <span class="description"><?php _e('These are the manual payment instructions to display on the payments screen - HTML allowed', 'mp') ?></span>
	          <p>
							<?php wp_editor( $mp->get_setting('gateways->paymill-for-wordpress->instructions'), 'manualpaymentsinstructions', array('textarea_name'=>'mp[gateways][paymill-for-wordpress][instructions]') ); ?>
						</p>
	        	</td>
	        </tr>
	        </tr>
		      <tr>
		        <th scope="row"><label for="paymill-for-wordpress-instructions"><?php _e('Method Image URL', 'paymill') ?></label></th>
		        <td>
		        <span class="description"><?php _e('Insert Image URL for replacing Paymill Logo image. You could create an image containing your provided payment types. - no HTML', 'paymill') ?></span>
	          <p><input value="<?php echo esc_attr($mp->get_setting('gateways->paymill-for-wordpress->image-url') ? $mp->get_setting('gateways->paymill-for-wordpress->image-url', plugins_url('',__FILE__ ).'/../img/logo_small.png') : plugins_url('',__FILE__ ).'/../img/logo_small.png'); ?>" style="width: 100%;" name="mp[gateways][paymill-for-wordpress][image-url]" id="paymill-for-wordpress-image-url" type="text" /></p>
	        	</td>
	        </tr>
      	</table>
      </div>
    </div>
    <?php
	}

	/**
	 * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
	 *	array. Don't forget to return!
	 */
	function process_gateway_settings($settings) {
		
		if(isset($settings['gateways']['paymill-for-wordpress']) && !is_array( $settings['gateways']['paymill-for-wordpress'])){
			return $settings;
		}
		
		//strip slashes
		$settings['gateways']['paymill-for-wordpress'] = array_map('stripslashes', (array)$settings['gateways']['paymill-for-wordpress']);
			
		//no html
		$settings['gateways']['paymill-for-wordpress']['name'] = stripslashes(wp_filter_nohtml_kses($settings['gateways']['paymill-for-wordpress']['name']));
		$settings['gateways']['paymill-for-wordpress']['image-url'] = stripslashes(wp_filter_nohtml_kses($settings['gateways']['paymill-for-wordpress']['image-url']));
		
		//filter html if needed
		if (!current_user_can('unfiltered_html')) {
			$settings['gateways']['paymill-for-wordpress']['instructions'] = wp_filter_post_kses($settings['gateways']['paymill-for-wordpress']['instructions']);
		}
		
		$settings['gateways']['paymill-for-wordpress']['instructions'] = wpautop($settings['gateways']['paymill-for-wordpress']['instructions']);

		return $settings;
	}

	/**
	 * Use to handle any payment returns from your gateway to the ipn_url. Do not echo anything here. If you encounter errors
	 *	return the proper headers to your ipn sender. Exits after.
	 */
	function process_ipn_return() {
	
	}
}

//register shipping plugin
mp_register_gateway_plugin( 'MP_Gateway_Paymill_for_Wordpress', 'paymill-for-wordpress', __('Paymill for WordPress', 'paymill'), true );