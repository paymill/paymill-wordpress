<?php
	/*
	
	Paymill Payment Class
	
	*/
	add_action( 'plugins_loaded', 'init_paymill_gateway_class' );
	
	function add_paymill_gateway_class( $methods ) {
		$methods[] = 'WC_Gateway_Paymill_Gateway'; 
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_paymill_gateway_class' );
	
	function init_paymill_gateway_class() {
		if(class_exists('WC_Payment_Gateway')){
			class WC_Gateway_Paymill_Gateway extends WC_Payment_Gateway{
				public function __construct(){
					$this->id					= 'paymill';
					$this->icon					= plugins_url('',__FILE__ ).'/../img/icon.png';
					$this->cc_icon				= plugins_url('',__FILE__ ).'/../img/creditcard-icons.png';
					$this->title				= 'Paymill';
					$this->description			= 'Payment with credit card.';
					$this->has_fields			= true;
					
					$this->init_form_fields();
					$this->init_settings();
				}
				
				function get_icon() {
					global $woocommerce;

					$icon = $this->icon ? '<a href="https://www.paymill.com/" target="_blank"><img src="' . $woocommerce->force_ssl( $this->icon ) . '" alt="' . $this->title . '" /></a> <img src="' . $woocommerce->force_ssl( $this->cc_icon ) . '" alt="' . $this->title . '" />' : '';

					return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
				}
				
				public function init_form_fields(){
					$this->form_fields = array(
						'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable Paymill Payment', 'woocommerce' ),
							'default' => 'yes'
						),
						'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'Paymill Payment', 'woocommerce' ),
							'desc_tip'      => true,
						),
						'description' => array(
							'title' => __( 'Customer Message', 'woocommerce' ),
							'type' => 'textarea',
							'default' => ''
						)
					);
				}
				
				public function process_payment( $order_id ) {
					global $woocommerce,$wpdb;
					
					$clientsObject = new Services_Paymill_Clients($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
					$transactionsObject = new Services_Paymill_Transactions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
					
					$client_new_email		= $_POST['billing_email'];
					$client_new_description	= $_POST['billing_first_name'].' '.$_POST['billing_last_name'];
					
					$userInfo = get_userdata(get_current_user_id());
					if($userInfo){
						$query				= 'SELECT * FROM '.$wpdb->prefix.'paymill_clients WHERE wp_member_id="'.$userInfo->ID.'"';
						$client_cache		= $wpdb->get_results($query,ARRAY_A);
						
						// check wether it's a new client
						if(intval($client_cache[0]['wp_member_id']) == 0){
							// create new client in paymill
							$client        = $clientsObject->create(array(
								'email'       => $client_new_email, 
								'description' => $client_new_description
								));
							
							// insert new client in local cache
							$query = 'INSERT INTO '.$wpdb->prefix.'paymill_clients (paymill_client_id, paymill_client_email, paymill_client_description, wp_member_id) VALUES ("'.$client['id'].'", "'.$client_new_email.'", "'.$client_new_description.'", "'.$userInfo->ID.'")';
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
							$query = 'UPDATE '.$wpdb->prefix.'paymill_clients SET paymill_client_email="'.$client_new_email.'",paymill_client_description="'.$client_new_description.'" WHERE wp_member_id="'.$userInfo->ID.'"';
							$wpdb->query($query);
						
						// all still synced, just load client object for safety purposes
						}else{
							$client = $clientsObject->getOne($client_cache[0]['paymill_client_id']);
						}
					}
					
					// make transaction
					$total = $woocommerce->cart->total;
					$params = array(
						'amount'      => str_replace('.','',"$total"),  // e.g. "4200" for 42.00 EUR
						'currency'    => get_woocommerce_currency(),   // ISO 4217
						'token'       => $_POST['paymillToken'],
						'client'      => $client['id'],
						'description' => 'Order #'.$order_id
					);				
					$transaction        = $transactionsObject->create($params);
					
					// save data to transaction table
					$query = 'INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, woocommerce_order_id) VALUES ("'.$transaction['id'].'", "'.$transaction['payment']['id'].'", "'.$transaction['client']['id'].'", "'.$order_id.'")';
					$wpdb->query($query);
					
					if(isset($transaction['error']['messages'])){
						foreach($transaction['error']['messages'] as $field => $msg){
							$woocommerce->add_error($field.': '.$msg);
						}
						return;
					}
				
					$order = new WC_Order( $order_id );

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
						$woocommerce->add_error('Bitte klicken Sie auf "Zahlungsdaten überprüfen", bevor Sie die Bestellung abschicken.');

						return false;
					}
					
					return true;
				}
				
				public function payment_fields(){
					global $woocommerce;
					if(!$GLOBALS['paymill_active']){
						// settings
						$GLOBALS['paymill_active'] = true;
						$country = $_REQUEST['country'];
						$cart_total = $woocommerce->cart->total*100;
						$currency = get_woocommerce_currency();
						$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos.png';
						
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
				}
			}
		}
	}
?>