<?php

	if(!function_exists('paymill_pay_button_errorHandling')){
		function paymill_pay_button_errorHandling($errors){
			$output			= '<div id="paymill_errors"><div class="paymill_error_title">'.__('Error:', 'paymill').'</div>';
			
			foreach($errors as $error){
				$output		.= '<div class="paymill_error">'.$error.'</div>';
			}
			
			$output			.= '</div>';
			
			return $output;
		}
	}
	
	class paymill_pay_button_processPayment{
		private $order_id			= false;
		private $order_desc			= '';
		private $total				= 0;
		private $total_complete		= 0;
		private $client				= false;
		private $paymentClass		= false;
		
		public function __construct(){
			load_paymill(); // this function-call can and should be used whenever working with Paymill API
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_pay_button_errorHandling');
			$GLOBALS['paymill_source']['pay_button_version'] = PAYMILL_VERSION;
			$this->order_id		= time();
			$this->order_desc	= apply_filters('paymill_paybutton_order_desc', __('Order', 'paymill').' #'.$this->order_id, array($this->order_id, $_POST));
		}
		private function getCurrentClient(){
			require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
			if(isset($_POST['forename']) && isset($_POST['surname'])){
				$desc		= $_POST['forename'].' '.$_POST['surname'];
			}elseif(isset($_POST['forename'])){
				$desc		= $_POST['forename'];
			}elseif(isset($_POST['surname'])){
				$desc		= $_POST['surname'];
			}else{
				$desc		= '';
			}
			
			$desc = apply_filters('paymill_paybutton_client_desc', $desc, array($this->order_id, $_POST));
			
			// create or get client
			$this->clientClass	= new paymill_client($_POST['email'],$desc);
			return $this->clientClass->getCurrentClient();
		}
		private function getTotals(){
			// load subscription class
			$this->subscriptions		= new paymill_subscriptions('pay_button');
			$offers						= $this->subscriptions->offerGetList();
		
			foreach($_POST['paymill_quantity'] as $id => $quantity){
				if(
					isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['products_offer']) &&
					isset($offers[$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['products_offer']]['amount']) &&
					floatval($offers[$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['products_offer']]['amount']) > 0
				){
					// retrieve subscription amount
					$amount = floatval(
										floatval($offers[$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['products_offer']]['amount'])
										*intval($quantity)
									);
				}else{
					// retrieve product amount
					$amount = floatval(
										floatval($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['products_price'])
										*intval($quantity)
										*100
									);
					$this->total		= $this->total+$amount;
				}
				
				$this->total_complete	= $this->total_complete+$amount;
			}

			// add shipping rate
			if(isset($_POST['paymill_shipping']) && strlen($_POST['paymill_shipping']) > 0){
				$shipping_costs			= (floatval($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping'][intval($_POST['paymill_shipping'])]['flat_shipping_costs'])*100);
				$this->total			= $this->total+$shipping_costs;
				$this->total_complete	= $this->total_complete+$shipping_costs;
			}
		}
		private function processSubscriptions(){
			foreach($_POST['paymill_quantity'] as $id => $quantity){
				if(isset($_POST['paymill_offer'][$id])){
					// create subscription
					if(isset($_POST['paymill_quantity'][$id]) && $_POST['paymill_quantity'][$id] == 1){
						$offer = $this->subscriptions->create($this->client->getId(), $_POST['paymill_offer'][$id], $this->paymentClass->getPaymentID());

						// offer cannot be subscribed.
						if($offer === false){
							return false;
						}else{ // subscription successful
							do_action('paymill_paybutton_subscription_created', array(
								'product_id'	=> $id,
								'offer_id'		=> $_POST['paymill_offer'][$id],
								'offer_data'	=> $offer
							));
						}
					}
				}else{ // no subscriptions in cart, so just return true
					return true;
				}
			}
			return true;
		}
		private function processProducts(){
			global $wpdb;
			if($this->total > 0){
				// make transaction
				$GLOBALS['paymill_loader']->request_transaction->setAmount(round($this->total)); // e.g. "4200" for 42.00 EUR
				$GLOBALS['paymill_loader']->request_transaction->setCurrency($GLOBALS['paymill_settings']->paymill_pay_button_settings['currency']);
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
					'currency'		=> $GLOBALS['paymill_settings']->paymill_pay_button_settings['currency'],
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
		private function sendMail(){
			$this->order_desc .= "\n\n";
			
			// customer details
			$email_customer_desc =
			(isset($_POST['company_name']) ? (__('Company Name', 'paymill').': '.strip_tags($_POST['company_name'])."\n") : '').
			(isset($_POST['forename']) ? (__('Forename', 'paymill').': '.strip_tags($_POST['forename'])."\n") : '').
			(isset($_POST['surname']) ? (__('Surname', 'paymill').': '.strip_tags($_POST['surname'])."\n") : '').
			(isset($_POST['street']) ? (__('Street', 'paymill').': '.strip_tags($_POST['street'])."\n") : '').
			(isset($_POST['number']) ? (__('Number', 'paymill').': '.strip_tags($_POST['number'])."\n") : '').
			(isset($_POST['zip']) ? (__('ZIP', 'paymill').': '.strip_tags($_POST['zip'])."\n") : '').
			(isset($_POST['city']) ? (__('City', 'paymill').': '.strip_tags($_POST['city'])."\n") : '').
			(isset($_POST['paymill_shipping']) && isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping'][$_POST['paymill_shipping']]) ? (__('Country', 'paymill').': '.strip_tags($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping'][$_POST['paymill_shipping']]['flat_shipping_country'])."\n") : '').
			(isset($_POST['email']) ? (__('Email', 'paymill').': '.strip_tags($_POST['email'])."\n") : '').
			(isset($_POST['phone']) ? (__('Phone', 'paymill').': '.strip_tags($_POST['phone'])."\n") : '');
			
			// products details
			$order_products = '';
			foreach($_POST['paymill_quantity'] as $product => $quantity){
				if(intval($quantity) > 0){
					$order_products .= $quantity.'x '.$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$product]['products_title']."\n";
				}
			}
			
			$order_mail = $this->order_desc.$email_customer_desc.$order_products;
			
			// allow filtering the order email
			$order_mail = apply_filters('paymill_paybutton_email_text', $order_mail, array($this->order_id, $_POST));
			
			// send confirmation mail
			wp_mail(
				$_POST['email'],
				__('Confirmation of your Order', 'paymill'),
				$order_mail,
				'From: "'.get_option('blogname').'" <'.$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_outgoing'].'>'
			);
			wp_mail(
				$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_incoming'],
				__('New Order received', 'paymill'),
				$order_mail,
				'From: "'.get_option('blogname').'" <'.$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_outgoing'].'>'
			);
		
			// order complete
			do_action('paymill_paybutton_email_sent', array($this->order_id, $_POST, $order_mail));
		}
		public function process_payment(){
			global $wpdb;
			if(isset($_POST['paymill_quantity']) && count($_POST['paymill_quantity']) > 0){
				$this->client				= $this->getCurrentClient();
				
				// client retrieved, now we are ready to process the payment
				if($this->client->getId() !== false && strlen($this->client->getId()) > 0){
					// get the totals for pre authorization
					$this->getTotals();
					
					// create payment object and preauthorization
					require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
					$this->paymentClass		= new paymill_payment($this->client->getId(),$this->total_complete,$GLOBALS['paymill_settings']->paymill_pay_button_settings['currency']); // create payment object, as it should be used for next processing instead of the token.
					if($GLOBALS['paymill_loader']->paymill_errors->status()){
						return false;
					}
					
					// process subscriptions & products
					if($this->processSubscriptions() && $this->processProducts()){
						// order complete
						do_action('paymill_paybutton_order_complete', array($this->order_id, $_POST));
						
						// prepare order confirmation mail
						$this->sendMail();
					
						// success, redirect if thankyou url is set
						if(isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['thankyou_url']) && strlen($GLOBALS['paymill_settings']->paymill_pay_button_settings['thankyou_url']) > 0){
							header('Location: '.$GLOBALS['paymill_settings']->paymill_pay_button_settings['thankyou_url']);
							die();
						}else{ // shpw thank you message and hide form
							define('PAYMILL_PAYBUTTON_ORDER_SUCCESS', true);
						}
					}else{
						return false;
					}
				}else{
					$GLOBALS['paymill_loader']->paymill_errors->setError(__('There was an issue with adding you as client for the payment process.', 'paymill'));
					return false;
				}
			}
		}
	}
	if(isset($_REQUEST['paymill_pay_button_order']) && $_REQUEST['paymill_pay_button_order'] == 1){
		add_action('plugins_loaded', function(){
			$class = new paymill_pay_button_processPayment();
			$class->process_payment();
		});
	}

	class paymill_pay_button_widget extends WP_Widget{
		var $subscriptions = false;
		
		/** constructor */
		function __construct() {
			parent::WP_Widget('paymill_pay_button_widget', 'Paymill Pay Button', array( 'description' => __('Shows a Paymill Payment Button.', 'paymill')));
			
			load_paymill(); // this function-call can and should be used whenever working with Paymill API
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_pay_button_errorHandling');
		}
		function widget($args, $instance){
			global $wpdb;

			if(
				!$GLOBALS['paymill_active'] &&
				isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) &&
				count($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) > 0 &&
				get_the_ID() != get_option('woocommerce_check_page_id') // compatibility with WooCommerce German Market
			){
				paymill_load_frontend_scripts(); // load frontend scripts
			
				$GLOBALS['paymill_active'] = true;
				
				echo $args['before_widget'];
				
				if(strlen($instance['title']) > 0){
					echo $args['before_title']; ?><?php echo $instance['title']; ?><?php echo $args['after_title'];
				}

				if(defined('PAYMILL_PAYBUTTON_ORDER_SUCCESS') && PAYMILL_PAYBUTTON_ORDER_SUCCESS === true){
					echo __('Thank you for your order.', 'paymill');
				}else{
					// settings
					$currency				= $GLOBALS['paymill_settings']->paymill_pay_button_settings['currency'];
					$cc_logo				= plugins_url('',__FILE__ ).'/../img/cc_logos_v.png';
					$title					= apply_filters( 'widget_title', $instance['title'] );
					$show_fields			= isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['fields_show']) ? $GLOBALS['paymill_settings']->paymill_pay_button_settings['fields_show'] : false;

					if(isset($instance['products_list']) && strlen($instance['products_list']) > 0){
						$products_whitelist	= explode(',',$instance['products_list']);
					}else{
						$products_whitelist	= unserialize($instance['products']);
					}
					
					// form ids
					echo '<script>
					paymill_form_checkout_id = ".checkout";
					paymill_form_checkout_submit_id = "#place_order";
					paymill_shop_name = "paybutton";
					</script>';
					
					if($this->subscriptions === false){
						$this->subscriptions = new paymill_subscriptions('pay_button');
					}
					$offers = $this->subscriptions->offerGetList();

					// html / icons
					echo '
					<div id="payment" class="paymill_pay_button">
						<form action="#" method="post" class="checkout">
					';

					if(file_exists(get_template_directory().'/paymill/pay_button.php')){
						require(get_template_directory().'/paymill/pay_button.php');
					}else{
						require(PAYMILL_DIR.'lib/tpl/pay_button.php');
					}
					
					echo '<div class="paymill_payment_title">'.__('Payment', 'paymill').'</div>';
					
					require(PAYMILL_DIR.'lib/tpl/checkout_form.php');
					
					echo '
							<input type="submit" id="place_order" value="'.__('Pay now', 'paymill').'"/>
						</form>
					</div>
					';
				}
				
				echo $args['after_widget'];
			}elseif(empty($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) || count($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) == 0){
				echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> You must have set at least one product.</div>';
			}else{
				echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
			}

		}
		function update($new_instance, $old_instance){
			$instance = $old_instance;
			$instance['title']			= strip_tags($new_instance['title']);
			$instance['products']		= serialize($new_instance['products']);
			
			return $instance;
		}
		function form($instance) {
			$products_whitelist = unserialize($instance['products']);
			echo'
			<fieldset>
				<legend><h4>'.__('Title:', 'paymill').'</h4></legend>
				<label for="'.$this->get_field_id('title').'">
					<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($instance['title']).'" />
				</label>
			</fieldset>
			<br />
			<fieldset>
				<legend><h4>'.__('Show these products only:', 'paymill').'</h5></legend>
				<label for="'.$this->get_field_id('products').'">
					<select class="widefat" style="width:220px;overflow:hidden;" id="'.$this->get_field_id('products').'" name="'.$this->get_field_name('products').'[]" multiple>
						<option value=""'.((!is_array($products_whitelist) || $products_whitelist[0] == '') ? ' selected="selected"' : '').'>'.__('All Products', 'paymill').'</option>
';
						foreach($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'] as $id => $product){
							if(strlen($product['products_title']) > 0){
								echo '<option value="'.$id.'"'.(is_array(unserialize($instance['products'])) && in_array($id,unserialize($instance['products'])) ? ' selected="selected"' : '').'>'.$product['products_title'].'</option>';
							}
						}
echo '
					</select>
				</label>
			</fieldset>
			<br />
			';
		}
	}
	add_action('widgets_init', create_function('','register_widget("paymill_pay_button_widget");'));
	
	// creating shortcodes
	function paymill_pay_button_shortcode($atts){
		ob_start();
		the_widget('paymill_pay_button_widget',$atts,$args);
		return ob_get_clean();
	}
	add_shortcode('paymill_pb', 'paymill_pay_button_shortcode');
?>