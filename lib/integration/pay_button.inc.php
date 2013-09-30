<?php

	function paymill_pay_button_process_payment(){
		global $wpdb;
		
		$GLOBALS['paymill_source']['pay_button_version'] = PAYMILL_VERSION;
		
		if(isset($_REQUEST['paymill_pay_button_order']) && $_REQUEST['paymill_pay_button_order'] == 1){				
			$clientsObject = new Services_Paymill_Clients($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
			$transactionsObject = new Services_Paymill_Transactions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
			
			$client_new_email		= $_POST['email'];
			$client_new_description	= $_POST['forename'].' '.$_POST['surname'];
			$total					= intval($_REQUEST['paymill_total']);
			$order_id				= time();

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
			$order = '';
			foreach($_POST['paymill_quantity'] as $product => $quantity){
				$order .= $quantity.'x '.$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$product]['title'].'<br />';
			}
			$order = __('Order', 'paymill').' #'.$order_id.'<br />'.__('Company Name', 'paymill').': '.strip_tags($_POST['company_name']).'<br />'.__('Forename', 'paymill').': '.strip_tags($_POST['forename']).'<br />'.__('Surname', 'paymill').': '.strip_tags($_POST['surname']).'<br />'.__('Street', 'paymill').': '.strip_tags($_POST['street']).'<br />'.__('Number', 'paymill').': '.strip_tags($_POST['number']).'<br />'.__('ZIP', 'paymill').': '.strip_tags($_POST['zip']).'<br />'.__('City', 'paymill').': '.strip_tags($_POST['city']).'<br />'.__('Country', 'paymill').': '.strip_tags($_POST['paymill_shipping']).'<br />'.__('Email', 'paymill').': '.strip_tags($_POST['email']).'<br />'.__('Phone', 'paymill').': '.strip_tags($_POST['phone']).'<br /><br />'.$order;
			
			$order_mail = str_replace('<br />',"\n",$order);
			$order = __('Order', 'paymill').' #'.$order_id;
			
			$params = array(
				'amount'		=> str_replace('.','',"$total"),  // e.g. "4200" for 42.00 EUR
				'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],   // ISO 4217
				'token'			=> $_POST['paymillToken'],
				'client'		=> $client['id'],
				'description'	=> $order,
				'source'		=> serialize($GLOBALS['paymill_source'])
			);				
			$transaction        = $transactionsObject->create($params);

			// save data to transaction table
			$query = 'INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, pay_button_order_id, paymill_transaction_time, paymill_transaction_data) VALUES ("'.$transaction['id'].'", "'.$transaction['payment']['id'].'", "'.$transaction['client']['id'].'", "'.$order_id.'", "'.$order_id.'", "'.$wpdb->escape(serialize($_POST)).'")';
			$wpdb->query($query);
			if(isset($transaction['error']) && (strlen($transaction['error']) > 0 || count($transaction['error']) > 0)){
				echo var_dump($transaction['error']);
				die();
			}
			
			/* create subscription */
			$subscriptions = false;
			if(isset($_POST['paymill_offer'])){
				foreach($_POST['paymill_offer'] as $product_id => $offer_id){
					if(isset($_POST['paymill_quantity'][$product_id]) && $_POST['paymill_quantity'][$product_id] == 1){
						if($subscriptions === false){
							$subscriptions = new paymill_subscriptions('pay_button');
						}
						$offers = $subscriptions->create($transaction['client']['id'], $offer_id, $transaction['payment']['id']);
					}
				}
			}
			
			wp_mail($client_new_email, __('Confirmation of your Order', 'paymill'), $order_mail, 'From: "'.get_option('blogname').'" <'.$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_outgoing'].'>');
			wp_mail($GLOBALS['paymill_settings']->paymill_pay_button_settings['email_incoming'], __('New Order received', 'paymill'), $order_mail, 'From: "'.get_option('blogname').'" <'.$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_outgoing'].'>');
		}
	}
	
	add_action('plugins_loaded', 'paymill_pay_button_process_payment');

	class paymill_pay_button_widget extends WP_Widget{
		var $subscriptions = false;
		
		/** constructor */
		function __construct() {
			parent::WP_Widget('paymill_pay_button_widget', 'Paymill Pay Button', array( 'description' => __('Shows a Paymill Payment Button.', 'paymill')));
		}
		function widget($args, $instance){
			global $wpdb;
			if(
				!$GLOBALS['paymill_active'] &&
				isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) &&
				count($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) > 0
			){
				$GLOBALS['paymill_active'] = true;
				
				echo $args['before_widget'];
				
				if(strlen($instance['title']) > 0){
					echo $args['before_title']; ?><?php echo $instance['title']; ?><?php echo $args['after_title'];
				}
				
				if(isset($_POST['paymill_pay_button_order']) && $_POST['paymill_pay_button_order'] == 1){
					echo __('Thank you for your order.', 'paymill');
				}else{
					// settings
					$country = 'DE';
					$currency = $GLOBALS['paymill_settings']->paymill_general_settings['currency'];
					$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos.png';
					$title = apply_filters( 'widget_title', $instance['title'] );
					
					if(isset($instance['products_list']) && strlen($instance['products_list']) > 0){
					
						$products_whitelist = explode(',',$instance['products_list']);
					}else{
						$products_whitelist = unserialize($instance['products']);
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
					echo '<div id="payment" class="paymill_pay_button"><form action="#" method="post" class="checkout">';
					require(PAYMILL_DIR.'lib/tpl/pay_button.php');
					echo '<div class="paymill_payment_title">'.__('Payment', 'paymill').'</div>';
					require(PAYMILL_DIR.'lib/tpl/checkout_form.php');
					echo '<input type="submit" id="place_order" value="'.__('Pay now', 'paymill').'"/>';
					echo '</form></div>';
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
							if(strlen($product['title']) > 0){
								echo '<option value="'.$id.'"'.(in_array($id,unserialize($instance['products'])) ? ' selected="selected"' : '').'>'.$product['title'].'</option>';
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
	// [sv_cb foo="foo-value"]
	function paymill_pay_button_shortcode($atts){
		ob_start();
		the_widget('paymill_pay_button_widget',$atts,$args);
		return ob_get_clean();
	}
	add_shortcode('paymill_pb', 'paymill_pay_button_shortcode');
?>