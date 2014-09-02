<?php

if(defined('CART66_VERSION_NUMBER')){
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
			$gateway = new Cart66ManualGateway();

			if(!$GLOBALS['paymill_active']){
				paymill_load_frontend_scripts(); // load frontend scripts

				// settings
				$GLOBALS['paymill_active']	= true;
				$cart_total					= $this->_total;
				$currency					= CURRENCY_CODE;
				$no_logos					= false;
				
				ob_start();
				
				// form ids
				echo '<script>
				paymill_form_checkout_id = "#Cart66ManualGateway_form";
				paymill_form_checkout_submit_id = "#Cart66CheckoutButton";
				paymill_shop_name = "cart66";
				</script>';

				require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
				
				$view .= '<h2>'.__('Payment Information','paymill').'</h2>';
				$view .= ob_get_clean();
				$view .= Cart66Common::getView('views/checkout.php', array('gateway' => $gateway), true, true);
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
		
		public function __construct() {

		}
		
		public function setPayment($p) {
			$this->_payment = $p;
			$skip = array('email', 'phone', 'custom-field');
			$custom_payment_fields = apply_filters('cart66_after_payment_form', '');
			if(is_array($custom_payment_fields)) {
				foreach($custom_payment_fields as $key => $payment_field) {
					if(!$payment_field['required']) {
						$skip[] = $payment_field['slug'];
					}
					if(isset($payment_field['validator']) && $payment_field['validator'] != '') {
						if(function_exists($payment_field['validator'])) {
							$skip[] = $payment_field['slug'];
							$data_to_validate = isset($p[$payment_field['slug']]) ? $p[$payment_field['slug']] : '';
							$validated = call_user_func($payment_field['validator'], $data_to_validate);
							if(!$validated['valid']) {
								foreach($validated['errors'] as $key => $error) {
									$this->_errors['Payment ' . $payment_field['slug'] . $key] = $error;
									$this->_jqErrors[] = 'payment-' . $payment_field['slug'];
								}
							}
						}
					}
				}
			}
			foreach($p as $key => $value) {
				if(!in_array($key, $skip)) {
					$value = trim($value);
					if($value == '') {
						$keyName = ucwords(preg_replace('/([A-Z])/', " $1", $key));
						$this->_errors['Payment ' . $keyName] = __('Payment ','cart66') . $keyName . __(' required','cart66');
						$this->_jqErrors[] = "payment-$key";
					}
				}
			}
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
			
		}
		
		 public function getCreditCardTypes() {
			 $noCards = array();
			 return $noCards;
		 }
		 
		 public function initCheckout($total) {
			 $this->_total = $total;
		 }
		 
		 public function getTransactionResponseDescription() {
			 return 'Manual transaction processed: ' . $this->_total;
		 }
		 
		 public function doSale() {
			 $transId = 'MT-' . Cart66Common::getRandString();
			 return $transId;
		 }
		
	}
}