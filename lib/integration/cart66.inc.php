<?php

if(defined('CART66_VERSION_NUMBER')){

	$GLOBALS['cart66_paymill_errors'] = array();

	if(!function_exists('paymill_cart66_errorHandling')){
		function paymill_cart66_errorHandling($errors){
			foreach($errors as $error){
				$GLOBALS['cart66_paymill_errors'][] = '<div class="paymill_error">'.$error.'</div>';
			}
		}
	}

	class paymill_Cart66ShortcodeManager extends Cart66ShortcodeManager {
		public function paymill_for_wordpress_Checkout($attrs) {
			if(!Cart66Session::get('Cart66Cart')->hasSubscriptionProducts()) {
				//require_once(CART66_PATH . "/gateways/Cart66ManualGateway.php");
				$manual = new Cart66_paymill_for_wordpress();
				$view = $this->_buildCheckoutView($manual);
			}
			else {
				$view = "<p>Unable to sell subscriptions using the manual checkout gateway.</p>";
			}
			
			return $view;
		}
		
		protected function _buildCheckoutView($gateway) {
			$ssl = Cart66Setting::getValue('auth_force_ssl');
			if($ssl) {
				if(!Cart66Common::isHttps()) {
				$sslUrl = "https://" . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
				wp_redirect($sslUrl);
				exit;
				}
			}
			
			// use manual gateway form to gather user information
			require_once(CART66_PATH . "/gateways/Cart66ManualGateway.php");
			$gateway = new Cart66_paymill_for_wordpress();
			
			if(!Cart66Session::get('Cart66Cart')) {
				Cart66Session::set('Cart66Cart', new Cart66Cart());
			}

			if(!$GLOBALS['paymill_active']){
				paymill_load_frontend_scripts(); // load frontend scripts

				// settings
				$GLOBALS['paymill_active']	= true;
				$cart_total					= intval((Cart66Session::get('Cart66Cart')->getGrandTotal(false)*100));
				$currency					= CURRENCY_CODE;
				$no_logos					= false;
				
				ob_start();
				
				// form ids
				echo '<script>
				paymill_form_checkout_id = "#Cart66_paymill_for_wordpress_form";
				paymill_form_checkout_submit_id = "#Cart66CheckoutButton";
				paymill_shop_name = "cart66";
				paymill_pcidss3 = '.((empty($GLOBALS['paymill_settings']->paymill_general_settings['pci_dss_3']) || $GLOBALS['paymill_settings']->paymill_general_settings['pci_dss_3'] != '1') ? 1 : 0).';
				</script>
				';

				require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
				
				$view .= '<h2>'.__('Payment Information','paymill').'</h2>';
				$view .= ob_get_clean();
				$checkout = Cart66Common::getView('views/checkout.php', array('gateway' => $gateway), true, true);
				$view .= str_replace(array('Cart66ManualGateway','Payment Information'),array('Cart66_paymill_for_wordpress','Contact Information'),$checkout);
			}else{
				$view = '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
			}
			
			return $view;
		}
		
	}
	$sc = new paymill_Cart66ShortcodeManager();
	add_shortcode('cart66_paymill_for_wordpress', array($sc, 'paymill_for_wordpress_Checkout'));

	class Cart66_paymill_for_wordpress extends Cart66GatewayAbstract {
			
		/**
		 * @var decimal
		 * The total price to charge the customer. Shipping, tax, etc. all included.
		 */
		protected $_total;
		protected $transaction_id;
		
		public function __construct() {
			load_paymill(); // this function-call can and should be used whenever working with Paymill API
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_cart66_errorHandling');
			$GLOBALS['paymill_source']['cart66_version'] = CART66_VERSION_NUMBER;
		}
		public function getErrors() {
			if(!is_array($this->_errors)) {
				$this->_errors = array();
			}
			return array_merge($this->_errors,$GLOBALS['cart66_paymill_errors']);
		}
		private function getCurrentClient(){
			require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
			if(isset($_POST['billing_first_name']) && isset($_POST['billing_last_name'])){
				$desc		= $_POST['billing']['firstName'].' '.$_POST['billing']['lastName'];
			}elseif(isset($_POST['billing_first_name'])){
				$desc		= $_POST['billing']['firstName'];
			}elseif(isset($_POST['billing_last_name'])){
				$desc		= $_POST['billing']['lastName'];
			}else{
				$desc		= '';
			}
			
			// create or get client
			$this->clientClass	= new paymill_client($_POST['payment']['email'],$desc);
			return $this->clientClass->getCurrentClient();
		}
		private function processProducts(){
			global $wpdb;
			if($this->total > 0){
				// make transaction
				$GLOBALS['paymill_loader']->request_transaction->setAmount(round($this->total,2)); // e.g. "4200" for 42.00 EUR
				$GLOBALS['paymill_loader']->request_transaction->setCurrency(CURRENCY_CODE);
				if($this->paymentClass->getPreauthID() != false){
					$GLOBALS['paymill_loader']->request_transaction->setPreauthorization($this->paymentClass->getPreauthID());
				}else{
					$GLOBALS['paymill_loader']->request_transaction->setPayment($this->paymentClass->getPaymentID());
				}
				$GLOBALS['paymill_loader']->request_transaction->setClient($this->client->getId());
				$GLOBALS['paymill_loader']->request_transaction->setDescription($_SERVER['HTTP_HOST'].': '.$this->order_desc);
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
/*
				// save data to transaction table
				$wpdb->query($wpdb->prepare('
				INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, cart66_order_id, paymill_transaction_time, paymill_transaction_data)
				VALUES (%s,%s,%s,%d,%d,%s)',
				array(
					$response['body']['data']['id'],
					$response['body']['data']['payment']['id'],
					$response['body']['data']['client']['id'],
					$this->order_id,
					time(),
					serialize($_POST)
				)));
				**/
				do_action('paymill_woocommerce_products_paid', array(
					'total'			=> $this->total,
					'currency'		=> CURRENCY_CODE,
					'client'		=> $response['body']['data']['client']['id']
				));

				$this->transaction_id = $response['body']['data']['id'];
				
				return true;
			}else{ // total is zero, so just return true
			
				// remove preauth when not used
				// @todo: Once preauths are usable for delayed payment in this plugin, we need to make a condition for this
				$this->paymentClass->removePreauth();
			
				return true;
			}
		}
		private function process_payment(){
			$this->client					= $this->getCurrentClient();
			
			// client retrieved, now we are ready to process the payment
			if($this->client->getId() !== false && strlen($this->client->getId()) > 0){
				$this->total_complete		=
				$this->total				= (floatval($this->_total)*100);

				// create payment object and preauthorization
				require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
				$this->paymentClass		= new paymill_payment($this->client->getId(),$this->total_complete,CURRENCY_CODE); // create payment object, as it should be used for next processing instead of the token.
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					$GLOBALS['paymill_loader']->paymill_errors->getErrors();
					return false;
				}
				
				// process subscriptions & products
				if($this->processProducts()){
					// success
					return true;
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
		
		public function setPayment($p) {
			if($p['email'] == '') {
				$this->_errors['Email address'] = __('Email address is required','cart66');
				$this->_jqErrors[] = "payment-email";
			}

			if($p['phone'] == '') {
				$this->_errors['Phone'] = __('Phone number is required','cart66');
				$this->_jqErrors[] = "payment-phone";
			}
			
			if(!Cart66Common::isValidEmail($p['email'])) {
				$this->_errors['Email'] = __("Email address is not valid","cart66");
				$this->_jqErrors[] = 'payment-email';
			}
			
			if(Cart66Setting::getValue('checkout_custom_field_display') == 'required' && $p['custom-field'] == '') {
				$this->_errors['Custom Field'] = Cart66Setting::getValue('checkout_custom_field_error_label') ? Cart66Setting::getValue('checkout_custom_field_error_label') : __('The Special Instructions Field is required', 'cart66');
				$this->_jqErrors[] = 'checkout-custom-field-multi';
				$this->_jqErrors[] = 'checkout-custom-field-single';
			}
			
			$this->_payment['email'] = $p['email'];
			$this->_payment['phone'] = $p['phone'];
			$this->_payment['custom-field'] = $p['custom-field'];
		}
		
		 public function getCreditCardTypes() {
			 $noCards = array();
			 return $noCards;
		 }
		 
		 public function initCheckout($total) {
			$this->_total = $total;
		 }
		 
		 public function getTransactionResponseDescription() {
			 return array('errorcode' => '', 'errormessage' => 'No Transaction ID could be generated.');
		 }
		 
		 public function doSale() {
			$this->process_payment();
			return $this->transaction_id;
		 }
	}
}