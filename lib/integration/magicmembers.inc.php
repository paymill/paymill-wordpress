<?php
/**

 * Paymill payment module, integrates AIM and ARB

 * ps: ARB = 1 AIM + ARB for remaining cycles

 *

 * @author     MagicMembers

 * @copyright  Copyright (c) 2011, MagicMembers 

 * @package    MagicMembers plugin

 * @subpackage Payment Module

 * @category   Module 

 * @version    3.0

 */

class mgm_paymill extends mgm_payment{

	// construct

	function __construct(){

		// php4 construct

		$this->mgm_paymill();

	}

	

	// construct

	function mgm_paymill(){

		// parent

		parent::__construct();

		// set code

		$this->code = __CLASS__;

		// set module

		$this->module = str_replace('mgm_', '', $this->code);

		// set name

		$this->name = 'Paymill';		

		// logo

		$this->logo = $this->module_url( 'assets/paymill.png' );

		// description

		$this->description = __('Paymill - Online payments made easy', 'mgm');

		// supported buttons types

	 	$this->supported_buttons = array('subscription', 'buypost');

		// trial support available ?

		$this->supports_trial= 'Y';	

		// cancellation support available ?

		$this->supports_cancellation= 'Y';	

		// do we depend on product mapping	

		$this->requires_product_mapping = 'N'; 

		// type of integration

		$this->hosted_payment = 'N';// credit card process onsite

		// if supports rebill status check	

		$this->supports_rebill_status_check = 'Y';		

		// default settings

		$this->_default_setting();

		// set path

		parent::set_tmpl_path();

		// read settings

		$this->read();	

	}		

	

	// MODULE API COMMON HOOKABLE CALLBACKS  //////////////////////////////////////////////////////////////////

	

	// settings

	function settings(){

		global $wpdb;

		

		// data

		$data = array();		

		// set 

		$data['module'] = $this;		

		// load template view

		$this->load->template('settings', array('data'=>$data));

	}	

	

	// settings_box

	function settings_box(){

		global $wpdb;

		// data

		$data = array();	

		// set 

		$data['module'] = $this;		

		// load template view

		return $this->load->template('settings_box', array('data'=>$data), true);

	}

	

	// update

	function settings_update(){

		// form type 

		switch($_POST['setting_form']){

			case 'box':

			// from box	

				switch($_POST['act']){

					case 'logo_update':

						// logo if uploaded

						if(isset($_POST['logo_new_'.$this->code]) && !empty($_POST['logo_new_'.$this->code])){

							// set

							$this->logo = $_POST['logo_new_'.$this->code];

							// save

							$this->save();

						}

						// message

						$message = sprintf(__('%s logo updated', 'mgm'), $this->name);			

						$extra   = array();

					break;

					case 'status_update':

					default:

						// enable

						$enable_state = (isset($_POST['payment']) && $_POST['payment']['enable'] == 'Y') ? 'Y' : 'N';

						// enable

						if( bool_from_yn($enable_state) ){

							$this->install();

							$stat = ' enabled.';

						}else{

						// disable

							$this->uninstall();	

							$stat = ' disabled.';

						}							

						// message

						$message = sprintf(__('%s module has been %s', 'mgm'), $this->name, $stat);							

						$extra   = array('enable' => $enable_state);	

					break;

				}							

				// print message

				echo json_encode(array_merge(array('status'=>'success','message'=>$message,'module'=>array('name'=>$this->name,'code'=>$this->code,'tab'=>$this->settings_tab)), $extra));

			break;

			case 'main':

			default:

			// from main						

				// authorize.net specific				

				$this->setting['loginid']  = $_POST['setting']['loginid'];

				$this->setting['tran_key'] = $_POST['setting']['tran_key'];	

				// csutom end points flag

				if( isset($_POST['setting']['end_points']) ){

					$this->setting['end_points'] = $_POST['setting']['end_points'];		

				}

				// update supported card types

				if( isset($_POST['card_types']) && !empty($_POST['card_types']) ){

					$this->setting['supported_card_types'] = $_POST['card_types'];

				}else{

					$this->setting['supported_card_types'] = array();	

				}

				// purchase price

				if(isset($_POST['setting']['purchase_price'])){

					$this->setting['purchase_price'] = $_POST['setting']['purchase_price'];

				}

				// common

				$this->description = $_POST['description'];

				$this->status      = $_POST['status'];

				// logo if uploaded

				if(isset($_POST['logo_new_'.$this->code]) && !empty($_POST['logo_new_'.$this->code])){

					$this->logo = $_POST['logo_new_'.$this->code];

				}				

				// fix old data

				$this->hosted_payment = 'N';	

				// setup callback messages				

				$this->_setup_callback_messages($_POST['setting']);

				// re setup callback urls

				$this->_setup_callback_urls($_POST['setting']);

				// re setup endpoints

				$end_points = (isset($_POST['end_points'])) ? $_POST['end_points'] : array(); 

				// save

				$this->save();

				// message

				echo json_encode(array('status'=>'success','message'=> sprintf(__('%s settings updated','mgm'), $this->name)));

			break;

		}		

	}

	

	// return process api hook, link back to site after payment is made

	function process_return(){

		// init

		if(!isset($this->response)) $this->response = array();

		// check and show message		

		if(isset($this->response['response_status']) && $this->response['response_status'] != 3 ){// 3 == Error							

			// process notify, internally called

			$this->process_notify(true);

			// redirect as success if not already redirected

			$query_arg = array('status'=>'success', 'trans_ref' => mgm_encode_id($_POST['x_custom']));

			// is a post redirect?

			$post_redirect = $this->_get_post_redirect($_POST['x_custom']);

			// set post redirect

			if($post_redirect !== false){

				$query_arg['post_redirect'] = $post_redirect;

			}			

			// is a register redirect?			

			$register_redirect = $this->_auto_login($_POST['x_custom']);

			// set register redirect

			if($register_redirect !== false){

				$query_arg['register_redirect'] = $register_redirect;

			}

			// redirect			

			mgm_redirect(add_query_arg($query_arg, $this->_get_thankyou_url()));			

		}else{			

			// error

			mgm_redirect(add_query_arg(array('status'=>'error','errors'=>urlencode($this->response['message_text'])), $this->_get_thankyou_url()));

		}		

	}

	

	// notify process api hook, background IPN url, used as proxy IPN for this module

	function process_notify($interal=false){

		// init

		if(!isset($this->response)) $this->response = array();

		//record POST/GET data

		do_action('mgm_print_module_data', $this->module, __FUNCTION__ );			

		// consider Silent Post Url:(Silent Post Will contain x_subscription_id post param  if it is an ARB recurring payment response)

		if(isset($_POST['x_subscription_id']) && isset($_POST['x_invoice_num'])) {

			// post

			if(!empty($_POST['x_invoice_num'])) {

				$trans_id = $_POST['x_invoice_num']; //x_cust_id

			}else {

				// exit if invoice id(transaction ref id) not found.

				die('Can\'t find invoice id' );

			}

			// treat invoice id as transaction reference:

			$_POST['x_custom'] = $_POST['x_invoice_num'];

			// pass post array to be processed:

			// 'arb_silent_post' is used as gateway method in this case

			$this->_process_response('arb_silent_post', $_POST);

		}

		

		// verify			

		if ($this->_verify_callback()){	

			// log data before validate

			$tran_id = $this->_log_transaction();

			// payment type

			$payment_type = $this->_get_payment_type($_POST['x_custom']);

			// custom

			$custom = $this->_get_transaction_passthrough($_POST['x_custom']);

			// hook for pre process

			do_action('mgm_notify_pre_process_'.$this->module, array('tran_id'=>$tran_id,'custom'=>$custom));

			// check			

			switch($payment_type){

				// buypost

				case 'post_purchase': 

				case 'buypost':

					$this->_buy_post(); //run the code to process a purchased post/page

				break;

				// subscription	

				case 'subscription':						

					$this->_buy_membership(); //run the code to process a new/extended membership

				break;											

			}

			// after process		

			do_action('mgm_notify_post_process_'.$this->module, array('tran_id'=>$tran_id,'custom'=>$custom));

		}		

		// after process unverified		

		do_action('mgm_notify_post_process_unverified_'.$this->module);	

		

		// 200 OK to gateway, only external		

		if( ! headers_sent() && ! $interal){

			@header('HTTP/1.1 200 OK');

			exit('OK');

		}	

	}

	

	// process cancel api hook 

	function process_cancel(){

		// redirect to cancel page

		mgm_redirect(add_query_arg(array('status'=>'cancel'), $this->_get_thankyou_url()));

	}

	

	// unsubscribe process, proxy for unsubscribe

	function process_unsubscribe() {				

		// get user id

		$user_id = (int)$_POST['user_id'];		

		//issue #1521

		$is_admin = (is_super_admin()) ? true : false;		

		// get user

		$user = get_userdata($user_id);	

		$member = mgm_get_member($user_id);		

		// multiple membesrhip level update:

		if(isset($_POST['membership_type']) && $member->membership_type != $_POST['membership_type'])

			$member = mgm_get_member_another_purchase($user_id, $_POST['membership_type']);				

		

		// init

		$cancel_account = true;		

		// check

		if(isset($member->payment_info->module) && $member->payment_info->module == $this->code) {// self check

			$subscr_id = null;				

			if(!empty($member->payment_info->subscr_id))

				$subscr_id = $member->payment_info->subscr_id;

			elseif (!empty($member->pack_id)) {	

				//check the pack is recurring

				$s_packs = mgm_get_class('subscription_packs');				

				$sel_pack = $s_packs->get_pack($member->pack_id);										

				if($sel_pack['num_cycles'] != 1) 

					$subscr_id = 0;// 0 stands for a lost subscription id

			}



			// cancel at authorize.net

			$cancel_account = $this->cancel_recurring_subscription(null, $user_id, $subscr_id);						

		}	

			

		// cancel in MGM

		if($cancel_account === true){

			$this->_cancel_membership($user_id, true);// redirected

		}



		// message

		$message = isset($this->response['message_text']) ? $this->response['message_text'] : __('Error while cancelling subscription', 'mgm') ;

		//issue #1521

		if( $is_admin ){

			mgm_redirect( add_query_arg(array('user_id'=>$user_id,'unsubscribe_errors'=>urlencode($message)), admin_url('user-edit.php')) );

		}			

		// force full url, bypass custom rewrite bug

		mgm_redirect(mgm_get_custom_url('membership_details', false,array('unsubscribe_errors'=>urlencode($message))));		

	}

	

	// process credit_card, proxy for credit_card processing

	function process_credit_card(){			

		// read tran id

		if(!$tran_id = $this->_read_transaction_id()){		

			return $this->throw_cc_error(__('Transaction Id invalid','mgm'));

		}	

		

		// get trans

		if(!$tran = mgm_get_transaction($tran_id)){

			return $this->throw_cc_error(__('Transaction invalid','mgm'));

		}		



		// Check user id is set if subscription_purchase. issue #1049

		if ($tran['payment_type'] == 'subscription_purchase' && 

			(!isset($tran['data']['user_id']) || (isset($tran['data']['user_id']) && (int) $tran['data']['user_id']  < 1))) {

			return $this->throw_cc_error(__('Transaction invalid . User id field is empty','mgm'));		

		}

		// system

		$system_obj  = mgm_get_class('system');	

		// get data		

		$data = $this->_get_button_data($tran['data'], $tran_id);

		// merge

		$post_data = array_merge($_POST, $data); 

		

		// set email

		$this->_set_default_email($post_data, 'x_email');

		

		// issue#: 581 changes ----------------------------------

		// adjust $post_data for recurring: which is(AIM+ARB)

		$bypass_aim = false;

		$temp_data = array();

		$recurring = false;			

		if($post_data['x_recurring_billing'] == 'TRUE') {

			$recurring = true;	

		 	//if valid trial amount found 	

			if(isset($post_data['x_trial_amount'])) {

				// consider trial amount for AIM transaction

				// store in  temp array: This will be reverted before ARB transaction

				$temp_data['x_amount'] = $post_data['x_amount'];

				$post_data['x_amount'] = $post_data['x_trial_amount'];

				// bypass AIM

		 		if($post_data['x_amount'] == 0) {

					$bypass_aim = true;

		 		}

				// reduce one trial cycle as nextStart date will be ajjusted for the first transaction

				$post_data['x_trial_occurrences']--;

				if($post_data['x_trial_occurrences'] <= 0) {

					//if no remaining trials, reset trial

					unset($post_data['x_trial_occurrences']);

					unset($post_data['x_trial_amount']);

				}

			}elseif ($post_data['x_total_occurrences'] != 9999) {//if not infinite

				// reduce number of cycles by one as first cycle will be charged through AIM				

				$post_data['x_total_occurrences']--;

			}

		}		

		// gateway			

		// issue#: 581 changes: treat first payment as AIM as first cycle will be billed through AIM if recurring

		$gateway_method = 'aim';		

		// add internal vars

		$secure = array(

					'x_login'    => $this->setting['loginid'],	

					'x_tran_key' => $this->setting['tran_key']								

				  );				 			

		// merge

		$post_data = array_merge($post_data, $secure);// overwrite post data array with secure params		

		

		// store x_custom

		$_POST['x_custom'] = $post_data['x_custom'];

		

		// filter post data

		$post_data_aim = $this->_filter_postdata($gateway_method, $post_data, 'array');// ok, aim array

		// headers

		$http_headers = ($gateway_method == 'arb') ? array('Content-Type' => 'text/xml') : array();

		// end  point

		$endpoint = $this->_get_endpoint($this->status.'_'.$gateway_method) ; // test_arb, live_aim etc.		

		//if to be charged

		if(!$bypass_aim) {

			// log

			// create curl post				

			$http_response = mgm_remote_post($endpoint, $post_data_aim, array('headers'=>$http_headers, 'timeout'=>30, 'sslverify'=>false));

			// $this->_curl_post($endpoint, $post_string, $http_headers);

			// parse response		

			$this->_process_response($gateway_method, $http_response);	

		}else {

			// log

			// mark as success if amount is for trial

			$this->_process_response('bypass', null);					

		}



		// if recurring CREATE ARB subscription:

		// issue#: 581 changes:

		// if AIM is success 			

		if((isset($this->response['response_status']) && (int)$this->response['response_status'] == 1) || $bypass_aim === true ) {			

			// cancel previous subscription:		

			// no need of passing previous pack id here as ARB creation happens after AIM transaction

			$this->cancel_recurring_subscription((isset($post_data['x_custom']) ? $post_data['x_custom'] : NULL)); sleep(1);

			// if recurring

			if($recurring) {

				// reset amount if taken from trial:

				// otherwise trial amount will be treated as actual amount for all the transactions

				if(isset($temp_data['x_amount'])) $post_data['x_amount'] = $temp_data['x_amount'];	

				// method							

				$gateway_method = 'arb';			

				// headers

				$http_headers = array('Content-Type' => 'text/xml');

				// end  point

				$endpoint = $this->_get_endpoint($this->status.'_' . $gateway_method) ; // test_arb, live_aim etc.	

				// filter post data		

				$post_data_arb = $this->_filter_postdata($gateway_method, $post_data, 'string');// ok, xml string

				// create curl post								

				$http_response = mgm_remote_post($endpoint, $post_data_arb, array('headers'=>$http_headers,'timeout'=>30,'sslverify'=>false));

				// $this->_curl_post($endpoint, $post_string, $http_header);

				// parse response		

				$arb_response = $this->_process_response($gateway_method, $http_response, false);	

				// log

				// check							

				if($arb_response['response_status'] == 1) {

					//add recurring subscription id to the response

					$this->response['subscription_id'] = $arb_response['subscription_id'];

					//created ARB

				}else { //ARB creation failed.

					// notify admin:

					$blogname = get_option('blogname');

					// mail

					@mgm_notify_admin_arb_creation_failed($blogname, $post_data, $arb_response);			

				}				

			}

			// treat as return

			$this->process_return();

		}else {			

			// return to credit card form

			return $this->throw_cc_error();			

		}

		// important: issue#: 871		

		// treat as return

		// update: only if payment is true

		// $this->process_return();			

	}	

	

	// process html_redirect, proxy for form submit

	//The credit card form will get submitted to the same function, then validate the card and if everything is clear

	//() will be called internally

	function process_html_redirect(){	

		// read tran id

		if(!$tran_id = $this->_read_transaction_id()){		

			return __('Transaction Id invalid','mgm');

		}

		

		// get trans

		if(!$tran = mgm_get_transaction($tran_id)){

			return __('Transaction invalid','mgm');

		}

		// Check user id is set if subscription_purchase. issue #1049	

		if ($tran['payment_type'] == 'subscription_purchase' && 

			(!isset($tran['data']['user_id']) || (isset($tran['data']['user_id']) && (int) $tran['data']['user_id']  < 1))) {

			return __('Transaction invalid . User id field is empty','mgm');		

		}

		// get user

		$user_id = $tran['data']['user_id'];

		$user    = get_userdata($user_id);		

		

		// log

		// mgm_pr($tran);

		

		// update pack/transaction: this is to confirm the module code if it is different

		mgm_update_transaction(array('module'=>$this->module), $tran_id);

				

		// cc field

		// $cc_fields = $this->_get_ccfields($user, $tran);

		

		// validate card: This will validate card and reload the form with errors

		// if validated process_credit_card() method will be called internally			

		$html = $this->validate_cc_fields_process(__FUNCTION__);		

		// the html
/*
		$html .='<form action="'. $this->_get_endpoint('html_redirect') .'" method="post" class="mgm_form" name="' . $this->code . '_form" id="' . $this->code . '_form">

					<input type="hidden" name="tran_id" value="'.$tran_id.'">

					<input type="hidden" name="submit_from" value="'.__FUNCTION__.'">

					'. $cc_fields .'

			   </form>';
*/

		// html / icons
		
		ob_start();

		if(!$GLOBALS['paymill_active']){
			// settings
			$GLOBALS['paymill_active'] = true;
			$country = 'DE';
			$cart_total = $tran['data']['cost']*100;
			$currency = $tran['data']['currency'];
			$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos_v.png';
		
			// form ids
			echo '<script>
			paymill_form_checkout_id = ".checkout";
			paymill_form_checkout_submit_id = "#place_order";
			paymill_shop_name = "magicmembers";
			</script>';
			
			echo '<div id="payment" class="paymill_pay_button paymill_magicmembers"><form action="'. $this->_get_endpoint('html_redirect') .'" name="' . $this->code . '_form" method="post" id="' . $this->code . '_form" class="checkout">';
			require(PAYMILL_DIR.'lib/tpl/checkout_form.php');
			echo '
			<input type="submit" id="place_order" value="'.__('Pay now', 'paymill').'"/>
			<input type="hidden" name="tran_id" value="'.$tran_id.'">
			<input type="hidden" name="submit_from" value="'.__FUNCTION__.'">
			';
			echo '</form></div>';
		}else{
			echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
		}
		
		$html = ob_get_clean();

		// return 	  

		return $html;					

	}	

	

	// subscribe button api hook

	function get_button_subscribe($options=array()){	

		$include_permalink = (isset($options['widget'])) ? false : true;

		// get html

		$html='<form action="'. $this->_get_endpoint('html_redirect',$include_permalink) .'" method="post" class="mgm_form" name="' . $this->code . '_form" id="' . $this->code . '_form">

				   <input type="hidden" name="tran_id" value="'.$options['tran_id'].'">

				   <input class="mgm_paymod_logo" type="image" src="' . mgm_site_url($this->logo) . '" border="0" name="submit" alt="' . $this->name . '">

				   <div class="mgm_paymod_description">'. mgm_stripslashes_deep($this->description) .'</div>
					<p class="paymill_payments_allowed">'.__('You can pay with:', 'paymill').'</p>
					<p>';
					foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
						if($type==1){
							$html.='<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
						}
					}
			$html.='</p>
			   </form>';

		// return	   

		return $html;

	}

	

	// buypost button api hook

	function get_button_buypost($options=array(), $return = false) {

		// get html

		$html='<form action="'. $this->_get_endpoint('html_redirect') .'" method="post" class="mgm_form" name="' . $this->code . '_form" id="' . $this->code . '_form">

					<input type="hidden" name="tran_id" value="'.$options['tran_id'].'">

					<input class="mgm_paymod_logo" type="image" src="' . mgm_site_url($this->logo) . '" border="0" name="submit" alt="' . $this->name . '">

					<div class="mgm_paymod_description">'. mgm_stripslashes_deep($this->description) .'</div>
					<p class="paymill_payments_allowed">'.__('You can pay with:', 'paymill').'</p>
					<p>';
					foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
						if($type==1){
							$html.='<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
						}
					}
			$html.='</p>
			   </form>';				

		// return or print

		if ($return) {

			return $html;

		} else {

			echo $html;

		}

	}

	

	// unsubscribe button api hook

	function get_button_unsubscribe($options=array()){	

		// action

		$action = add_query_arg(array('module'=>$this->code,'method'=>'payment_unsubscribe'), mgm_home_url('payments'));	

		// message

		$message = sprintf(__('You have subscribed to <b>%s</b> via <b>%s</b>, if you wish to unsubscribe, please click the following link. <br>','mgm'), get_option('blogname'), $this->name);		

		// html

		$html='<div class="mgm_margin_bottom_10px">

					<h4>'.__('Unsubscribe','mgm').'</h4>

					<div class="mgm_margin_bottom_10px">' . $message . '</div>

			   </div>

			   <form name="mgm_unsubscribe_form" id="mgm_unsubscribe_form" method="post" action="' . $action . '">

					<input type="hidden" name="user_id" value="' . $options['user_id'] . '"/>

					<input type="hidden" name="membership_type" value="' . $options['membership_type'] . '"/>

					<input type="button" name="btn_unsubscribe" value="' . __('Unsubscribe','mgm') . '" onclick="confirm_unsubscribe(this)" class="button" />	

			   </form>';	

		// return

		return $html;		

	}	

	

	// dependency_check

	function dependency_check(){

		// default

		$this->dependency = array();

		// SIM dependency

		/*****************************

		if(!extension_loaded('mhash')){

			$this->dependency[] = '<b class="mgm_module_dependency_high">MHASH PHP extension must be loaded for Paymill.</b>';

		}

		******************************/

		if(!extension_loaded('SimpleXML')){

			$this->dependency[] = '<b class="mgm_module_dependency_high">'.__('SimpleXML PHP extension must be loaded for Paymill ARB','mgm').'.</b>';

		}

		// transaction details api

		$this->dependency[] = '<b class="mgm_module_dependency_medium">'.__('Transaction Details API in Paymill must be enabled for Rebill Status Query','mgm').'.</b>';

		// error		

		return (count($this->dependency)>0) ? true : false ;		

	}

	

	// get module transaction info

	function get_transaction_info($member, $date_format){		

		// data

		$subscription_id = $member->payment_info->subscr_id;

		$transaction_id  = $member->payment_info->txn_id;	

		// return

		// return print_r($member->payment_info, 1);	

		// set default

		$paymill_txn_id  = __('N/A','mgm');

		// eway tran

		if(isset($member->payment_info->paymill_txn_id)){

			$paymill_txn_id = $member->payment_info->paymill_txn_id;

		}

		// info

		$info = sprintf('<b>%s:</b><br>%s: %s<br>%s: %s', __('AUTHORIZE.NET INFO','mgm'), __('SUBSCRIPTION ID','mgm'), $subscription_id, 

						__('TRANSACTION ID','mgm'), $transaction_id);					

		// set

		$transaction_info = sprintf('<div class="overline">%s</div>', $info);

		

		// return 

		return $transaction_info;

	}

	

	/**

	 * get gateway tracking fields for sync

	 *

	 * @todo process another subscription

	 */

	function get_tracking_fields_html(){

		// html

		$html = sprintf('<p>%s: <input type="text" size="20" name="paymill[subscriber_id]"/></p>

				 		 <p>%s: <input type="text" size="20" name="paymill[transaction_id]"/></p>', 

						 __('Subscription ID','mgm'), __('Transaction ID','mgm'));

		

		// return			

		return $html;				

	}

	

	/**

      * update and sync gateway tracking fields

	  *

	  * @param array $data

	  * @param object $member	  

	  * @return boolean 

	  * @uses _save_tracking_fields()

	  */

	 function update_tracking_fields($post_data, &$member){

	 	// validate

		if(isset($member->payment_info->module) && $member->payment_info->module != $this->code) return false;

		

	 	// fields, module_field => post_field

		$fields = array('subscr_id'=>'subscriber_id','txn_id'=>'transaction_id');

		// data

		$data = $post_data['paymill'];

	 	// return

	 	return $this->_save_tracking_fields($fields, $member, $data); 			

	 }

						

	// MODULE API COMMON PRIVATE HELPERS /////////////////////////////////////////////////////////////////	

	

	// get button data

	function _get_button_data($pack, $tran_id=NULL) {

		// system

		$system_obj = mgm_get_class('system');	

		$user_id = $pack['user_id'];

		$user = get_userdata($user_id);		

		// item 		

		$item = $this->get_pack_item($pack);

		// set data

		$data = array(			

			'x_invoice_num' => $tran_id, 					

			'x_description' => $item['name'],			

			'x_email'       => $user->user_email,			

			'x_amount'      => $pack['cost'] // base amount is always same for both type of payment

		);		

		

		// additional fields,see parent for all fields, only different given here	

		$this->_set_address_fields($user, $data);		

		

		// subscription purchase with ongoing/limited

		if( !isset($pack['buypost']) && isset($pack['duration_type']) && $pack['num_cycles'] != 1 ){ // does not support one-time recurring

		// if ( isset($pack['num_cycles']) && $pack['num_cycles'] != 1 && isset($pack['duration_type'])) { // old style

			// recurring

			$data['x_recurring_billing'] = 'TRUE';	

			// cust id

			$data['x_cust_id'] = $user_id;

			// types

			$unit_types = array('d'=>'days', 'm'=>'months', 'y'=>'months');	// treat year a 12 x months	

			// interval

			$data['x_interval_unit']   = $unit_types[$pack['duration_type']]; // days|months

			$data['x_interval_length'] = ($pack['duration_type']=='y') ? ((int)$pack['duration'] * 12) : $pack['duration']; // 3|12|365 etc.

			

			// start date

			// $data['x_start_date'] = date('Y-m-d') ;

			// issue#: 581 changes: calculate billing date for 2nd cycle as first cycle will be billed through AIM

			$add_by = str_replace('s', '',$data['x_interval_unit']);// DAY|MONTH

			$data['x_start_date'] = date( 'Y-m-d', strtotime("+{$data['x_interval_length']} {$add_by}", strtotime(date('Y-m-d')))) ;

					

			// trial

			if ($pack['trial_on']) {

				$data['x_trial_occurrences'] = $pack['trial_num_cycles'];

				$data['x_trial_amount']      = $pack['trial_cost'];	

				//rewrite start date:

				$trial_interval_unit  		 = $unit_types[$pack['trial_duration_type']]; // days|months

				$trial_interval_length 		 = ($pack['trial_duration_type']=='y') ? ((int)$pack['trial_duration'] * 12) : $pack['trial_duration']; // 3|12|365 etc.

				$trial_add_by = str_replace('s', '', $trial_interval_unit);

				$data['x_start_date'] = date( 'Y-m-d', strtotime("+{$trial_interval_length} {$trial_add_by}", strtotime(date('Y-m-d')) )) ;

			}

			// arb ongoing =0 must be set as 9999, or integer 1-99

			// occurrences: greater than 0

			if ( (int)$pack['num_cycles'] > 0 ) {

				// $data['x_total_occurrences'] = $pack['num_cycles'];

				// issue#: 581 changes: reduce total cycles by one as first cycle will be billed through AIM

				$data['x_total_occurrences'] = $pack['num_cycles'];

			}else{

				$data['x_total_occurrences'] = 9999;// ongoing for ARB

			}						

		}else{

		// post purchase/ one time billing

			// recurring

			$data['x_recurring_billing'] = 'FALSE';	

			// apply addons

			$this->_apply_addons($pack, $data, array('amount'=>'x_amount','description'=>'x_description'));			

		}

		

		// custom passthrough

		$data['x_custom'] = $tran_id;

		

		// strip

		$data = mgm_stripslashes_deep($data);

		

		// add filter @todo test

		$data = apply_filters('mgm_payment_button_data', $data, $tran_id, $this->module, $pack);

		

		// update pack/transaction

		mgm_update_transaction(array('data'=>json_encode($pack),'module'=>$this->module), $tran_id);

		

		// return data		

		return $data;

	}	

	

	// buy post

	function _buy_post() {

		global $wpdb;

		// system

		$system_obj = mgm_get_class('system');

		$dge = bool_from_yn($system_obj->get_setting('disable_gateway_emails'));

		$dpne = bool_from_yn($system_obj->get_setting('disable_payment_notify_emails'));

		

		// passthrough

		$alt_tran_id = $this->_get_alternate_transaction_id();

		

		// get passthrough, stop further process if fails to parse

		$custom = $this->_get_transaction_passthrough($alt_tran_id);

		// local var

		extract($custom);



		// find user

		$user = null;

		// check

		if(isset($user_id) && (int)$user_id > 0) $user = get_userdata($user_id);	

		

		// errors

		$errors = array();

		// purchase status

		$purchase_status = 'Error';



		// response code

		$response_code = $this->_get_response_code($this->response['response_status'], 'status');

		// process on response code

		switch ($response_code) {

			case 'Approved':

				// status

				$status_str = __('Last payment was successful','mgm');

				// purchase status

				$purchase_status = 'Success';				

				

				// transaction id

				$transaction_id = $this->_get_transaction_id();

				// hook args

				$args = array('post_id'=>$post_id, 'transaction_id'=>$transaction_id);

				// user purchase

				if(isset($user_id) && (int)$user_id > 0){

					$args['user_id'] = $user_id;

				}else{

				// guest purchase	

					$args['guest_token'] = $guest_token;

				}

				// after succesful payment hook

				do_action( 'mgm_buy_post_transaction_success', $args );// backward compatibility

				do_action( 'mgm_post_purchase_payment_success', $args );// new organized name

			break;



			case 'Declined':

			case 'Refunded':

			case 'Denied':

				// status

				$status_str = __('Last payment was refunded or denied','mgm');				

				// purchase status

				$purchase_status = 'Failure';

										  

				// error

				$errors[] = $status_str;	

			break;



			case 'Pending':

			case 'Held for Review':

				// status

				$status_str = sprintf(__('Last payment is pending. Reason: %s','mgm'), $this->response['message_text']);				

				// purchase status

				$purchase_status = 'Pending';	

										  

				// error

				$errors[] = $status_str;

			break;



			default:

				// status

				$status_str = sprintf(__('Last payment status: %s','mgm'),$response_code);

				// purchase status

				$purchase_status = 'Unknown';					

																							  

				// error

				$errors[] = $status_str;

			break;	

		}

		

		// do action

		do_action('mgm_return_post_purchase_payment_'.$this->module, array('post_id' => $post_id));// new, individual

		do_action('mgm_return_post_purchase_payment', array('post_id' => $post_id));// new, global 	

		

		// status

		$status = __('Failed join', 'mgm'); // overridden on a successful payment

		// check status

		if ( $purchase_status == 'Success' ) {

			// mark as purchased

			if( isset($user->ID) ){	// purchased by user	

				// call coupon action

				do_action('mgm_update_coupon_usage', array('user_id' => $user_id));		

				// set as purchased	

				$this->_set_purchased($user_id, $post_id, NULL, $alt_tran_id);

			}else{

				// purchased by guest

				if( isset($guest_token) ){

					// issue #1421, used coupon

					if(isset($coupon_id) && isset($coupon_code)) {

						// call coupon action

						do_action('mgm_update_coupon_usage', array('guest_token' => $guest_token,'coupon_id' => $coupon_id));

						// set as purchased

						$this->_set_purchased(NULL, $post_id, $guest_token, $alt_tran_id, $coupon_code);

					}else {

						$this->_set_purchased(NULL, $post_id, $guest_token, $alt_tran_id);				

					}

				}

			}	



			// status

			$status = __('The post was purchased successfully', 'mgm');

		}

		

		// transaction status

		mgm_update_transaction_status($alt_tran_id, $status, $status_str);

		

		// blog

		$blogname = get_option('blogname');			

		// post being purchased			

		$post = get_post($post_id);



		// notify user and admin, only if gateway emails on	

		if ( ! $dpne ) {			

			// notify user

			if( isset($user->ID) ){

				// mgm post setup object

				$post_obj = mgm_get_post($post_id);

				// check

				if( $this->send_payment_email($alt_tran_id) ) {	

				// check

					if( mgm_notify_user_post_purchase($blogname, $user, $post, $purchase_status, $system_obj, $post_obj, $status_str) ){

					// update as email sent 

						$this->update_paymentemail_sent($alt_tran_id);

					}	

				}					

			}			

		}

		

		// notify admin, only if gateway emails on

		if ( ! $dge ) {

			// notify admin, 

			mgm_notify_admin_post_purchase($blogname, $user, $post, $status);

		}



		// error condition redirect

		if(count($errors)>0){

			mgm_redirect(add_query_arg(array('status'=>'error', 'errors'=>implode('|', $errors)), $this->_get_thankyou_url()));

		}

	}

	

	// buy membership

	function _buy_membership() {	

		// system	

		$system_obj = mgm_get_class('system');		

		$s_packs = mgm_get_class('subscription_packs');

		$dge = bool_from_yn($system_obj->get_setting('disable_gateway_emails'));

		$dpne = bool_from_yn($system_obj->get_setting('disable_payment_notify_emails'));

		

		// passthrough

		$alt_tran_id = $this->_get_alternate_transaction_id();

				

		// get passthrough, stop further process if fails to parse

		$custom = $this->_get_transaction_passthrough($alt_tran_id);		

		// local var

		extract($custom);

		

		// currency

		if (!$currency) $currency = $system_obj->get_setting('currency');		

		// find user

		$user = get_userdata($user_id);

		//another_subscription modification

		if(isset($custom['is_another_membership_purchase']) && bool_from_yn($custom['is_another_membership_purchase'])) {

			$member = mgm_get_member_another_purchase($user_id, $custom['membership_type']);			

		}else {

			$member = mgm_get_member($user_id);			

		}

		

		// Get the current AC join date		

		if (!$join_date = $member->join_date) $member->join_date = time(); // Set current AC join date		



		//if there is no duration set in the user object then run the following code

		if (empty($duration_type)) {

			//if there is no duration type then use Months

			$duration_type = 'm';

		}

		// membership type default

		if (empty($membership_type)) {

			//if there is no account type in the custom string then use the existing type

			$membership_type = md5($member->membership_type);

		}

		// validate parent method

		$membership_type_verified = $this->_validate_membership_type($membership_type, 'md5|plain');

		// verified

		if (!$membership_type_verified) {

			if (strtolower($member->membership_type) != 'free') {

				// notify admin, only if gateway emails on

				if( ! $dge ) mgm_notify_admin_membership_verification_failed( $this->name );

				// abort

				return;

			} else {

				$membership_type_verified = $member->membership_type;

			}

		}		

		// set

		$membership_type = $membership_type_verified;

		// sub pack

		$subs_pack = $s_packs->get_pack($pack_id);		

		// if trial on		

		if ($subs_pack['trial_on']) {

			$member->trial_on            = $subs_pack['trial_on'];

			$member->trial_cost          = $subs_pack['trial_cost'];

			$member->trial_duration      = $subs_pack['trial_duration'];

			$member->trial_duration_type = $subs_pack['trial_duration_type'];

			$member->trial_num_cycles    = $subs_pack['trial_num_cycles'];

		}	

		// duration

		$member->duration        = $duration;

		$member->duration_type   = strtolower($duration_type);

		$member->amount          = $amount;

		$member->currency        = $currency;

		$member->membership_type = $membership_type;		

		$member->pack_id         = $pack_id;

		// $member->payment_type = 'subscription';		

		//save num_cycles in mgm_member object:(issue#: 478)

		$member->active_num_cycles = (isset($num_cycles) && !empty($num_cycles)) ? $num_cycles : $subs_pack['num_cycles']; 

		$member->payment_type    = ((int)$member->active_num_cycles == 1) ? 'one-time' : 'subscription';

		// payment info for unsubscribe		

		if(!isset($member->payment_info)) $member->payment_info = new stdClass;

		// module

		$member->payment_info->module = $this->code;		

		// transaction type

		if(isset($this->response['transaction_type'])){	

			$member->payment_info->txn_type = $this->response['transaction_type'];	

		}

		// subscription

		if(isset($this->response['subscription_id'])){

			// set

			$member->payment_info->subscr_id = $this->response['subscription_id'];

			// reset rebilled count

			if(isset($member->rebilled)) unset($member->rebilled);		

		}

		// transaction	

		if(isset($this->response['transaction_id'])){	

			$member->payment_info->txn_id = $this->response['transaction_id'];	

		}

		// mgm transaction id

		$member->transaction_id = $alt_tran_id;

		// process response

		$new_status = $update_role = false;

		// errors

		$errors = array();	

		// response code

		$response_code = $this->_get_response_code($this->response['response_status'], 'status');			

		// status

		switch ($response_code) {

			case 'Approved':

				// status

				$new_status = MGM_STATUS_ACTIVE;

				$member->status_str = __('Last payment was successful','mgm');	

										

				// current time

				$time = time();

				$last_pay_date = isset($member->last_pay_date) ? $member->last_pay_date : null;			

				// last pay date			

				$member->last_pay_date = date('Y-m-d', $time);	

				

				// default expire_date_ts to calculate next cycle expire date

				$expire_date_ts = $time;

				// check subscription_option

				if(isset($subscription_option)){

					// on option

					switch($subscription_option){

						// @ToDo, apply expire date login

						case 'create':

						// expire date will be based on current time					

						case 'upgrade':

						// expire date will be based on current time

							// already on top

							$expire_date_ts = $time;

						break;

						case 'downgrade':

						// expire date will be based on expire_date if exists, current time other wise					

						case 'extend':

						// expire date will be based on expire_date if exists, current time other wise

							$expire_date_ts = $time;

							// extend/expire date

							//if (!empty($member->expire_date) && $member->last_pay_date != date('Y-m-d', $expire_date_ts)) {

							// calc expiry	- issue #1226

							// membership extend functionality broken if we try to extend the same day so removed && $last_pay_date != date('Y-m-d', $time) check	

							if (!empty($member->expire_date) ) {

								// expiry

								$expire_date_ts2 = strtotime($member->expire_date);

								// valid

								// valid && expiry date is greater than today

								if ($expire_date_ts2 > 0 && $expire_date_ts2 > $expire_date_ts) {

									// set it for next calc

									$expire_date_ts = $expire_date_ts2;

								}

							}

						break;

					}	

				}					

				

				// type expanded

				$duration_exprs = $s_packs->get_duration_exprs();

				// if not lifetime/date range

				if(in_array($member->duration_type, array_keys($duration_exprs))) {// take only date exprs

					// consider trial duration if trial period is applicable

					if(isset($trial_on) && $trial_on == 1 ) {

						// Do it only once

						if(!isset($member->rebilled) && isset($member->active_num_cycles) && $member->active_num_cycles != 1 ) {							

							$expire_date_ts = strtotime('+' . $trial_duration . ' ' . $duration_exprs[$trial_duration_type], $expire_date_ts);								

						}					

					}else {

						// recalc - issue #1068

						$expire_date_ts = strtotime('+' . $member->duration . ' ' . $duration_exprs[$member->duration_type], $expire_date_ts);										

					}

					// formatted

					$expire_date = date('Y-m-d', $expire_date_ts);		

					// date extended				

					if (!$member->expire_date || $expire_date_ts > strtotime($member->expire_date)) {

						$member->expire_date = $expire_date;			

					}	

				}else{

					//if lifetime:

					if($member->duration_type == 'l'){// el = lifetime

						$member->expire_date = '';

					}

					//issue #1096

					if($member->duration_type == 'dr'){// el = /date range

						$member->expire_date = $duration_range_end_dt;

					}																	

				}					

					

				// update rebill: issue #: 489				

				if($member->active_num_cycles != 1){

					// check			

					if(!isset($member->rebilled)){

						$member->rebilled = 1;

					}else if((int)$member->rebilled < (int)$member->active_num_cycles) { // 100 

						// rebill

						$member->rebilled = ((int)$member->rebilled + 1);	

					}	

				}

				

				//clear cancellation status if already cancelled:

				if(isset($member->status_reset_on)) unset($member->status_reset_on);

				if(isset($member->status_reset_as)) unset($member->status_reset_as);				

				

				// role update

				if ($role) $update_role = true;					

				

				// transaction_id

				$transaction_id = $this->_get_transaction_id();

				// hook args

				$args = array('user_id' => $user_id, 'transaction_id'=>$transaction_id);

				// another membership

				if(isset($custom['is_another_membership_purchase']) && bool_from_yn($custom['is_another_membership_purchase'])) {

					$args['another_membership'] = $custom['membership_type'];

				}

				// after succesful payment hook

				do_action('mgm_membership_transaction_success', $args);// backward compatibility				

				do_action('mgm_subscription_purchase_payment_success', $args);// new organized name	

							

			break;



			case 'Declined':

			case 'Refunded':

			case 'Denied':

				$new_status = MGM_STATUS_NULL;

				$member->status_str = __('Last payment was refunded or denied','mgm');

				// error

				$errors[] = $member->status_str;

			break;

			

			case 'Pending':

			case 'Held for Review':

				$new_status = MGM_STATUS_PENDING;

				$reason = $this->response['message_text'];

				$member->status_str = sprintf(__('Last payment is pending. Reason: %s','mgm'), $reason);				

				// error

				$errors[] = $member->status_str;

			break;



			default:

				$new_status = MGM_STATUS_ERROR;

				$member->status_str = sprintf(__('Last payment status: %s','mgm'), $response_code.' - '.$this->response['message_text']);

				// error

				$errors[] = $member->status_str;

			break;

		}

		

		// old status

		$old_status = $member->status;	

		// set new status

		$member->status = $new_status;			

				

		// whether to acknowledge the user by email - This should happen only once

		$acknowledge_user = $this->send_payment_email($alt_tran_id);

		// whether to subscriber the user to Autoresponder - This should happen only once

		$acknowledge_ar = mgm_subscribe_to_autoresponder($member, $alt_tran_id);

		

		// update member

		// another_subscription modification

		if(isset($custom['is_another_membership_purchase']) && bool_from_yn($custom['is_another_membership_purchase'])) {// issue #1227

			// hide old content

			if($subs_pack['hide_old_content']) $member->hide_old_content = $subs_pack['hide_old_content']; 

			

			// save

			mgm_save_another_membership_fields($member, $user_id);



			// Multiple membership upgrade: first time

			if (isset($custom['multiple_upgrade_prev_packid']) && is_numeric($custom['multiple_upgrade_prev_packid'])) {

				mgm_multiple_upgrade_save_memberobject($custom, $member->transaction_id);	

			}

		}else {

			$member->save();			

		}

		

		// status change event

		do_action('mgm_user_status_change', $user_id, $new_status, $old_status, 'module_' . $this->module, $member->pack_id);	

		

		//update coupon usage

		do_action('mgm_update_coupon_usage', array('user_id' => $user_id));

		

		// update role

		if ($update_role) {			

			$obj_role = new mgm_roles();				

			$obj_role->add_user_role($user_id, $role);	

		}

				

		// return action

		do_action('mgm_return_'.$this->module, array('user_id' => $user_id));// backward compatibility

		do_action('mgm_return_subscription_payment_'.$this->module, array('user_id' => $user_id));// new , individual	

		do_action('mgm_return_subscription_payment', array('user_id' => $user_id, 'acknowledge_ar' => $acknowledge_ar, 'mgm_member' => $member));// new, global: pass mgm_member object to consider multiple level purchases as well. 	



		// read member again for internal updates if any

		// another_subscription modification

		if(isset($custom['is_another_membership_purchase']) && bool_from_yn($custom['is_another_membership_purchase'])) {

			$member = mgm_get_member_another_purchase($user_id, $custom['membership_type']);				

		}else {

			$member = mgm_get_member($user_id);			

		}		

		

		// transaction status

		mgm_update_transaction_status($member->transaction_id, $member->status, $member->status_str);

		

		// send email notification to client

		$blogname = get_option('blogname');



		// notify

		if( $acknowledge_user ) {

			// notify user, only if gateway emails on 

			if ( ! $dpne ) {			

				// notify

				if( mgm_notify_user_membership_purchase($blogname, $user, $member, $custom, $subs_pack, $s_packs, $system_obj) ){						

					// update as email sent 

					$this->update_paymentemail_sent($alt_tran_id);	

				}				

			}

			// notify admin, only if gateway emails on 

			if ( ! $dge ) {

				// pack duration

				$pack_duration = $s_packs->get_pack_duration($subs_pack);

				// notify admin,

				mgm_notify_admin_membership_purchase($blogname, $user, $member, $pack_duration);

			}

		}	



		//exit if from Silent Post:

		if(isset($_POST['x_subscription_id'])) {

			exit();	

		}		

		// error condition redirect

		if(count($errors)>0){			

			mgm_redirect(add_query_arg(array('status'=>'error', 'errors'=>implode('|', $errors)), $this->_get_thankyou_url()));

		}

	}

	

	// cancel membership

	function _cancel_membership($user_id, $redirect = false){

		// system	

		$system_obj = mgm_get_class('system');		

		$s_packs = mgm_get_class('subscription_packs');

		$dge = bool_from_yn($system_obj->get_setting('disable_gateway_emails'));

		$dpne = bool_from_yn($system_obj->get_setting('disable_payment_notify_emails'));	

		//issue #1521

		$is_admin = (is_super_admin()) ? true : false;		

		// find user

		$user = get_userdata($user_id);

		$member = mgm_get_member($user_id);

		// multiple membesrhip level update:					

		$multiple_update = false;	

		// check

		if(isset($_POST['membership_type']) && $member->membership_type != $_POST['membership_type']){

			$multiple_update = true;

			$member = mgm_get_member_another_purchase($user_id, $_POST['membership_type']);	

		}

			

		// get pack

		if($member->pack_id){

			$subs_pack = $s_packs->get_pack($member->pack_id);

		}else{

			$subs_pack = $s_packs->validate_pack($member->amount, $member->duration, $member->duration_type, $member->membership_type);

		}

				

		// reset payment info

		$member->payment_info->txn_type = 'subscription_cancel';

		

		// types

		$duration_exprs = $s_packs->get_duration_exprs();

						

		// default expire date				

		$expire_date = $member->expire_date;

		// if lifetime:

		if($member->duration_type == 'l') $expire_date = date('Y-m-d');	

							

		// if trial on 

		if ($subs_pack['trial_on'] && isset($duration_exprs[$subs_pack['trial_duration_type']])) {			

			// if cancel data is before trial end, set cancel on trial expire_date

			$trial_expire_date = strtotime('+' . $subs_pack['trial_duration'] . ' ' . $duration_exprs[$subs_pack['trial_duration_type']], $member->join_date);

			

			// if lower

			if(time() < $trial_expire_date){

				$expire_date = date('Y-m-d',$trial_expire_date);

			}

		}

			

		// transaction_id

		$trans_id = $member->transaction_id;	

		// if today 

		if($expire_date == date('Y-m-d')){

			// status

			$new_status          = MGM_STATUS_CANCELLED;

			$new_status_str      = __('Subscription cancelled','mgm');

			// set

			$member->status      = $new_status;

			$member->status_str  = $new_status_str;					

			$member->expire_date = date('Y-m-d');

				

			// reassign expiry membership pack if exists: issue#: 535			

			$member = apply_filters('mgm_reassign_member_subscription', $user_id, $member, 'CANCEL', true);					

		}else{

			// date

			$date_format = mgm_get_date_format('date_format');

			// status

			$new_status     = MGM_STATUS_AWAITING_CANCEL;	

			$new_status_str = sprintf(__('Subscription awaiting cancellation on %s','mgm'), date($date_format, strtotime($expire_date)));

			// set		

			$member->status      = $new_status;

			$member->status_str  = $new_status_str;		

			// set reset date

			$member->status_reset_on = $expire_date;

			$member->status_reset_as = MGM_STATUS_CANCELLED;

		}

						

		// multiple memberhip level update:	

		if($multiple_update) {			

			mgm_save_another_membership_fields($member, $user_id);

		}else{ 			

			$member->save();	 					

		}	

		

		// transaction status

		mgm_update_transaction_status($trans_id, $new_status, $new_status_str);

			

		// send email notification to client

		$blogname = get_option('blogname');		

									  

		// notify user

		if( ! $dpne ) {

			// notify user

			mgm_notify_user_membership_cancellation($blogname, $user, $member, $system_obj, $new_status, $membership_type);			

		}

		// notify admin

		if ( ! $dge ) {

			// notify admin	

			mgm_notify_admin_membership_cancellation($blogname, $user, $member);

		}

		

		// after cancellation hook

		do_action('mgm_membership_subscription_cancelled', array('user_id' => $user_id));			

		

		// redirect only internal

		if( $redirect ) {

			// message

			$lformat = mgm_get_date_format('date_format_long');

			$message = sprintf(__("You have successfully unsubscribed. Your account has been marked for cancellation on %s", "mgm"), 

			                  ($expire_date == date('Y-m-d') ? 'Today' : date($lformat, strtotime($expire_date))));		

			//issue #1521

			if( $is_admin ){

				mgm_redirect( add_query_arg(array('user_id'=>$user_id,'unsubscribe_errors'=>urlencode($message)), admin_url('user-edit.php')) );

			}		

			// redirect 		

			mgm_redirect(mgm_get_custom_url('membership_details', false,array('unsubscribed'=>'true','unsubscribe_errors'=>urlencode($message))));

		}

	}

	

	/**

	 * Cancel Recurring Subscription

	 * This is not a private function

	 * @param int/string $trans_ref	

	 * @param int $user_id	

	 * @param int/string $subscr_id	

	 * @return boolean

	 */	

	function cancel_recurring_subscription($trans_ref = null, $user_id = null, $subscr_id = null, $pack_id = null) {

		//if coming form process return after a subscription payment

		if(!empty($trans_ref)) {

			$transdata = $this->_get_transaction_passthrough($trans_ref);

			if($transdata['payment_type'] != 'subscription_purchase')

				return false;				

					

			$user_id = $transdata['user_id'];

							

			if(isset($transdata['is_another_membership_purchase']) && $transdata['is_another_membership_purchase'] == 'Y') {

				$member = mgm_get_member_another_purchase($user_id, $transdata['membership_type']);			

			}else {

				$member = mgm_get_member($user_id);			

			}

			

			if(isset($member->payment_info->module) && !empty($member->payment_info->module)) {

				if(isset($member->payment_info->subscr_id)) {

					$subscr_id = $member->payment_info->subscr_id; 

				}else {

					//check pack is recurring:

					$pid = $pack_id ? $pack_id : $member->pack_id;

					

					if($pid) {

						$s_packs = mgm_get_class('subscription_packs');

						$sel_pack = $s_packs->get_pack($pid);												

						if($sel_pack['num_cycles'] != 1)

							$subscr_id = 0;

					}										

				}

				

												

				//check for same module: if not call the same function of the applicale module.

				if(str_replace('mgm_','' , $member->payment_info->module) != str_replace( 'mgm_','' , $this->code ) ) {

					// log					

					// mgm_log('RECALLing '. $member->payment_info->module .': cancel_recurring_subscription FROM: ' . $this->code);

					// return

					return mgm_get_module($member->payment_info->module, 'payment')->cancel_recurring_subscription($trans_ref, null, null, $pack_id);				

				}				

				//skip if same pack is updated

				if(empty($member->pack_id) || (is_numeric($pack_id) && $pack_id == $member->pack_id) )

					return false;

				

			}else 

				return false;

		}

		

		//only for subscription_purchase		

		if($subscr_id) {				

			// set xml content											

			$post_data ='<ARBCancelSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">

							<merchantAuthentication>

								<name>'.$this->setting['loginid'].'</name>

								<transactionKey>'.$this->setting['tran_key'].'</transactionKey>

							</merchantAuthentication>

							<refId>'.$user_id.'</refId>

							<subscriptionId>'.$subscr_id.'</subscriptionId>

						</ARBCancelSubscriptionRequest>';

			// end  point

			$endpoint = $this->_get_endpoint($this->status.'_arb') ; // test_arb, live_aim etc.	

			// headers

			$http_headers = array('Content-Type' => 'text/xml');		

			// create curl post				

			$http_response = mgm_remote_post($endpoint, $post_data, array('headers'=>$http_headers,'timeout'=>30,'sslverify'=>false));

			// parse response and store into a different array:

			// do not use $this->response here as this will overwrite previous theprevious values in one scenario		

			$arb_response = $this->_process_response('arb', $http_response, false);	

			// check		

			if(isset($arb_response['response_status']) && $arb_response['response_status'] == 1){	

				return true;

			}

		}elseif(is_null($subscr_id) || $subscr_id === 0) {			

			//send email to admin if subscription Id is absent		

			$system_obj = mgm_get_class('system');			

			$dge = bool_from_yn($system_obj->get_setting('disable_gateway_emails'));

			//send email only if setting enabled

			if( ! $dge ) {

				// blog

				$blogname = get_option('blogname');

				// user

				$user = get_userdata($user_id);

				// notify admin

				mgm_notify_admin_membership_cancellation_manual_removal_required($blogname, $user, $member);				

			}

			// return			

			return true;

		}		

		// return

		return false;

	}



	/**

	 * Specifically check recurring status of each rebill for an expiry date

	 * ALong with IPN post mechanism for rebills, the module will need to specifically request for the rebill status

	 * @param int $user_id

	 * @param object $member

	 * @return boolean

	 */

	function query_rebill_status($user_id, $member=NULL) {	

		// check	

		if (isset($member->payment_info->subscr_id) && !empty($member->payment_info->subscr_id)) {					

			$post_data ='<ARBGetSubscriptionStatusRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">

							<merchantAuthentication>

								<name>'.$this->setting['loginid'].'</name>

								<transactionKey>'.$this->setting['tran_key'].'</transactionKey>

							</merchantAuthentication>

							<subscriptionId>'.$member->payment_info->subscr_id.'</subscriptionId>

						</ARBGetSubscriptionStatusRequest>';			

			

			// end  point

			$endpoint = $this->_get_endpoint($this->status . '_arb') ; // test_arb, live_aim etc.	

			// headers

			$http_headers = array('Content-Type' => 'text/xml');			

			// create curl post				

			$http_response = mgm_remote_post($endpoint, $post_data, array('headers'=>$http_headers,'timeout'=>30,'sslverify'=>false));

			// $this->_curl_post($endpoint, $content, array("Content-Type: text/xml") );	

			

			// parse response		

			$this->_process_response('subscription_status', $http_response);				

			

			// check		

			if (isset($this->response['subscription_status'])) {

				// old status

				$old_status = $member->status;	

				// set status

				switch(strtolower($this->response['subscription_status'])){

					case 'active':

						// set new status

						$member->status = $new_status = MGM_STATUS_ACTIVE;

						// status string

						$member->status_str = __('Last payment cycle processed successfully','mgm');

						

						// get transaction

						$tran_response = $this->get_transaction_details($member);	

						// last pay date

						$member->last_pay_date = (isset($tran_response['transDate']) && !empty($tran_response['transDate'])) ? date('Y-m-d', strtotime($tran_response['transDate'])) : date('Y-m-d');	

						// expire date

						if(isset($tran_response['transDate']) && !empty($tran_response['transDate']) && !empty($member->expire_date)){													

							// date to add

						 	$date_add = mgm_get_pack_cycle_date((int)$member->pack_id, $member);		

							// check 

							if($date_add !== false){

								// new expire date should be later than current expire date, #1223

								$new_expire_date = date('Y-m-d', strtotime($date_add, strtotime($member->last_pay_date)));

								// apply on last pay date so the calc always treat last pay date form gateway, 

								if(strtotime($new_expire_date) > strtotime($member->expire_date)){

									$member->expire_date = $new_expire_date;

								}

							}else{

							// set last pay date if greater than expire date

								if(strtotime($member->last_pay_date) > strtotime($member->expire_date)){

									$member->expire_date = $member->last_pay_date;

								}

							}				

						} 						

						// set eway txn no

						if(isset($tran_response['transId'])){

							$member->payment_info->paymill_txn_id = $tran_response['transId'];

						}

						// set eway txn no

						if(isset($tran_response['batchId'])){

							$member->payment_info->paymill_batch_id = $tran_response['batchId'];

						}

						// save

						$member->save();	



						// only run in cron, other wise too many tracking will be added

						// if( defined('DOING_QUERY_REBILL_STATUS') && DOING_QUERY_REBILL_STATUS != 'manual' ){

						// transaction_id

						$transaction_id = $member->transaction_id;

						// hook args

						$args = array('user_id' => $user_id, 'transaction_id' => $transaction_id);

						// after succesful payment hook

						do_action('mgm_membership_transaction_success', $args);// backward compatibility				

						do_action('mgm_subscription_purchase_payment_success', $args);// new organized name	

						// }											

					break;

					case 'canceled':

						// if expire date in future, let as awaiting

						if(!empty($member->expire_date) && strtotime($member->expire_date) > time()){

							// date format

							$date_format = mgm_get_date_format('date_format');				

							// status				

							$member->status = $new_status = MGM_STATUS_AWAITING_CANCEL;	

							// status string	

							$member->status_str = sprintf(__('Subscription awaiting cancellation on %s','mgm'), date($date_format, strtotime($member->expire_date)));							

							// set reset date				

							$member->status_reset_on = $member->expire_date;

							// reset as

							$member->status_reset_as = MGM_STATUS_CANCELLED;

						}else{

						// set cancelled

							// status			

							$member->status = $new_status = MGM_STATUS_CANCELLED;

							// status string

							$member->status_str = __('Last payment cycle cancelled','mgm');	

						}

						// save

						$member->save();



						// only run in cron, other wise too many tracking will be added

						// if( defined('DOING_QUERY_REBILL_STATUS') && DOING_QUERY_REBILL_STATUS != 'manual' ){

						// after cancellation hook

						do_action('mgm_membership_subscription_cancelled', array('user_id' => $user_id));	

						// }

					break;					

					case 'suspended':

					case 'terminated':

					case 'expired':						

						// set new statis

						$member->status = $new_status = MGM_STATUS_EXPIRED;

						// status string

						$member->status_str = __('Last payment cycle expired','mgm');	

						// save

						$member->save();						

					break;

				}					

				// action

				if( isset($new_status)  && $new_status != $old_status){

					// user status change

					do_action('mgm_user_status_change', $user_id, $new_status, $old_status, 'module_' . $this->module, $member->pack_id);	

					// rebill status change

					do_action('mgm_rebill_status_change', $user_id, $new_status, $old_status, 'query');// query or notify

				}		

				// return as a successful rebill

				return true;

			}			

		}

		// return

		return false;//default to false to skip normal modules

	}

	

	// get transaction

	function get_transaction_details($member){

		// array

		$response = array();

		// verify txn id exists

		$this->_fetch_merchant_txn_id($member);

		// check

		if (isset($member->payment_info->txn_id) && !empty($member->payment_info->txn_id)) {

			// set xml content											

			$post_data ='<getTransactionDetailsRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">

							<merchantAuthentication>

								<name>'.$this->setting['loginid'].'</name>

								<transactionKey>'.$this->setting['tran_key'].'</transactionKey>

							</merchantAuthentication>

							<transId>'.$member->payment_info->txn_id.'</transId>

						</getTransactionDetailsRequest>';

			// end  point

			$endpoint = $this->_get_endpoint($this->status . '_arb') ; // test_arb, live_aim etc.				

			

			// headers

			$http_headers = array('Content-Type' => 'text/xml');	

			

			// create curl post				

			$http_response = mgm_remote_post($endpoint, $post_data, array('headers'=>$http_headers,'timeout'=>30,'sslverify'=>false));

			// $this->_curl_post($endpoint, $content, array("Content-Type: text/xml") );				

			

			// parse response		

			$response = $this->_process_response('transaction_details', $http_response, false);			

		}		

		

		// return

		return $response;	

	}

			

	// default setting

	function _default_setting(){

		// authorize.net specific

		$this->setting['loginid']  = '';

		$this->setting['tran_key'] = '';	

		// purchase price

		if(in_array('buypost', $this->supported_buttons)){

			$this->setting['purchase_price']  = 4.00;		

		}				

		// callback messages				

		$this->_setup_callback_messages();

		// callback urls

		$this->_setup_callback_urls();	

	}

	

	// log transaction

	function _log_transaction(){

		// check

		if($this->_is_transaction($_POST['x_custom'])){	

			// tran id

			$tran_id = (int)$_POST['x_custom'];			

			// return data				

			if(isset($this->response['transaction_type'])){

				$option_name = $this->module.'_'.strtolower($this->response['transaction_type']).'_return_data';

			}else{

				$option_name = $this->module.'_return_data';

			}

			// set

			mgm_add_transaction_option(array('transaction_id'=>$tran_id,'option_name'=>$option_name,'option_value'=>json_encode($this->response)));

			

			// options 

			$options = array('transaction_type','subscription_id','transaction_id');

			// loop

			foreach($options as $option){

				if(isset($this->response[$option])){

					mgm_add_transaction_option(array('transaction_id'=>$tran_id,'option_name'=>strtolower($this->module.'_'.$option),'option_value'=>$this->response[$option]));

				}

			}

			// return transaction id

			return $tran_id;	

		}	

		// error

		return false;	

	}

	

	// get tran id

	function _get_transaction_id(){

		// validate

		if($this->_is_transaction($_POST['x_custom'])){	

			// tran id

			return $tran_id = (int)$_POST['x_custom'];

		}

		// return 

		return 0;	

	}


	// set 

	function _set_address_fields($user, &$data){

		// mappings

		$mappings= array('first_name'=>'x_first_name','last_name'=>'x_last_name','address'=>'x_address',

		                 'city'=>'x_city','state'=>'x_state','zip'=>'x_zip','country'=>'x_country',

						 'phone'=>'x_phone');

						 

		// parent

		parent::_set_address_fields($user, $data, $mappings, array($this,'_address_fields_filter'));				 

	}

	

	// filter

	function _address_fields_filter($name, $value){

		// reuse parent filter unless needed

		switch($name){

			default:

				 $value = parent::_address_field_filter($name, $value);		

			break;

		}	

		// return 

		return $value;

	}

	

	// verify callback 

	function _verify_callback(){	

		// keep it simple		

		return (isset($_POST['x_custom']) && !empty($_POST['x_custom'])) ? true : false;

	}

	

	// custom pt var

	function _get_alternate_transaction_id(){

		// var

		$alt_tran_id = '';

		// post

		if(isset($_POST['x_custom']) && !empty($_POST['x_custom'])){

			$alt_tran_id = $_POST['x_custom'];

		}elseif(isset($_GET['x_custom']) && !empty($_GET['x_custom'])){

			$alt_tran_id = $_GET['x_custom'];

		}		

		// return 

		return $alt_tran_id;

	}



	// MODULE SPECIFIC PRIVATE HELPERS /////////////////////////////////////////////////////////////////

	

	// filter postdata

	function _filter_postdata($gateway_method, $post_data, $return='string'){	

		// card holder name

		list($ch_first_name, $ch_last_name) = explode(' ', $post_data['mgm_card_holder_name']);	

		// gateway method

		switch($gateway_method){

			case 'arb':

				// request xml

				$content =

						"<?xml version=\"1.0\" encoding=\"utf-8\"?>" .

							"<ARBCreateSubscriptionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .

								"<merchantAuthentication>".

									"<name>" . $post_data['x_login'] . "</name>".

									"<transactionKey>" . $post_data['x_tran_key'] . "</transactionKey>".

								"</merchantAuthentication>".

								"<refId>" . $post_data['x_cust_id'] . "</refId>".

								"<subscription>".

									"<name>" . substr($post_data['x_description'],0,50) . "</name>". // iss#165, name overflow		

									"<paymentSchedule>".

										"<interval>".

											"<length>" . $post_data['x_interval_length'] . "</length>".

											"<unit>" . $post_data['x_interval_unit'] . "</unit>".

										"</interval>".	

										"<startDate>" . $post_data['x_start_date'] . "</startDate>".

										"<totalOccurrences>" . $post_data['x_total_occurrences'] . "</totalOccurrences>";

				$content .= (isset($post_data['x_trial_occurrences'])) ? "<trialOccurrences>" . $post_data['x_trial_occurrences'] . "</trialOccurrences>": "";

				$content .=		

									"</paymentSchedule>".		

									"<amount>" . $post_data['x_amount'] . "</amount>";	

				$content .= (isset($post_data['x_trial_occurrences'])) ? "<trialAmount>" . $post_data['x_trial_amount'] . "</trialAmount>" : "";

				$content .=	

									"<payment>".

										"<creditCard>".

											"<cardNumber>" . $post_data['mgm_card_number'] . "</cardNumber>".

											"<expirationDate>" . $post_data['mgm_card_expiry_year'].'-'.$post_data['mgm_card_expiry_month'] . "</expirationDate>".

										"</creditCard>".

									"</payment>";

				//include transaction id as invoice number(to be processed in Silent Post)					

				$content .= 		"<order>".

									"<invoiceNumber>". $post_data['x_custom'] . "</invoiceNumber>".	

									"<description>". substr($post_data['x_description'],0,254) . "</description>".

									"</order>";

								

				$content .=			"<customer>".

										"<id>" . $post_data['x_cust_id'] . "</id>".

										"<email>" . $post_data['x_email'] . "</email>";										

										

				$content .=	(isset($post_data['x_phone'])) ? "<phoneNumber>" .$this->_format_phone($post_data['x_phone']) ."</phoneNumber>" : "";

										

				$content .=			"</customer>".

									"<billTo>".

										"<firstName>". (($post_data['x_first_name']) ? $post_data['x_first_name'] : $ch_first_name) . "</firstName>".

										"<lastName>" . (($post_data['x_last_name']) ? $post_data['x_last_name'] : $ch_last_name) . "</lastName>";

										

										// address					

										if(isset($post_data['x_address'])){									

											$content .=	"<address>" . substr($post_data['x_address'], 0, 60) . "</address>";	

										}

										// city

										if(isset($post_data['x_city'])){	

											$content .="<city>" . substr($post_data['x_city'], 0, 40) . "</city>";

										}	

										// city

										if(isset($post_data['x_state']) && strlen($post_data['x_state'])==2){	

											$content .="<state>" . substr($post_data['x_state'], 0, 2) . "</state>";

										}

										// city

										if(isset($post_data['x_zip'])){	

											$content .="<zip>" . substr($post_data['x_zip'], 0, 20) . "</zip>";

										}

										// city

										if(isset($post_data['x_country'])){	

											$content .="<country>" . substr($post_data['x_country'],0, 60) . "</country>";

										}

										

				 $content .=		"</billTo>".

								"</subscription>".

							"</ARBCreateSubscriptionRequest>";	

				// return

				return $content;			

			break;

			case 'aim':

				// set delim

				$this->delim_char = '|';

				// custom set

				$aim_fields = array('x_version'        => '3.1',

									'x_type'           => 'AUTH_CAPTURE',

									'x_invoice_num'    => $post_data['x_invoice_num'],

									'x_delim_data'     => 'TRUE',

									'x_delim_char'     => $this->delim_char,

									'x_method'         => 'CC',	

									'x_relay_response' => 'FALSE',

									'x_email_customer' => 'TRUE',	

									'x_card_num'	   => $post_data['mgm_card_number'],

									'x_card_code'      => $post_data['mgm_card_code'],

									'x_exp_date'	   => $post_data['mgm_card_expiry_month'].'-'.$post_data['mgm_card_expiry_year'] // MM-YYYY	

							);

				// capture some as sent

				$fields_sent = array('x_login', 'x_tran_key', 'x_description', 'x_first_name', 'x_last_name', 'x_email', 

									 'x_cust_id', 'x_amount', 'x_address', 'x_city', 'x_state', 'x_zip', 'x_country');	

				                

				// first last name

				if($post_data['x_first_name'] == '' || $post_data['x_first_name']==$post_data['x_last_name']) {

					$post_data['x_first_name'] = $ch_first_name;

				}

				// last name

				if($post_data['x_last_name'] == '' || $post_data['x_first_name']==$post_data['x_last_name']) {

					$post_data['x_last_name'] = $ch_last_name;

				}

				// set

				foreach($fields_sent as $field){

					// take only when set

					if(isset($post_data[$field]) && !empty($post_data[$field])){

						$aim_fields[$field] = $post_data[$field];

					}

				}

				// format

				if(isset($aim_fields['x_phone'])){

					// format phone

					$phone = $this->_format_phone($aim_fields['x_phone']);

					// check

					if($phone){					

						$aim_fields['x_phone'] = $phone;

					}else{

						unset($aim_fields['x_phone']);

					}	

				}

				// send filtered

				return ($return == 'string') ? mgm_http_build_query($aim_fields) : $aim_fields;			

			break;

		}		

	}

	

	// process response

	//Process response has been modified to process Silent Post Url for ARB

	function _process_response($gateway_method, $content, $set_response = true){		

		// init

		if($set_response) $this->response = array();

		//temp response data	

		$temp_resp = array();

		// gateway method

		switch($gateway_method){

			case 'arb':

				$xml = @simplexml_load_string($content);				

				if($xml){

					$resultCode                   = (string)$xml->messages->resultCode;

					$temp_resp['response_status'] = (strtolower($resultCode) == 'ok') ? 1 : 3; // 1 success, 3 error

					$temp_resp['message_code']    = (string)$xml->messages->message->code ;	

					$temp_resp['message_text']    = (string)$xml->messages->message->text ;

					$temp_resp['subscription_id'] = (string)$xml->subscriptionId;						

				}else{

					$temp_resp['response_status'] = 3;

					$temp_resp['message_text']    = 'Error parsing XML';					

				}				

			break;

			case 'aim':

				// split

				$tmp_data = explode($this->delim_char, $content);

				// error

				if(count($tmp_data)==0){

					$temp_resp['response_status'] = 3;

					$temp_resp['message_text']    = strip_tags($tmp_data[0]);

				}else{

					// pos params

					$fields = array(0=>"x_response_code", 1=>"x_response_subcode", 2=>"x_response_reason_code", 3=>"x_response_reason_text",

		                            4=>"x_authorization_code", 5=>"x_avs_response", 6=>"x_transaction_id", 9=>"x_amount", 10=>"x_method",

								    11=>"x_transaction_type", 37=>"x_md5_hash", 38=>"x_card_code_response");

					// data

					$data = array();

					// store

					foreach($fields as $index=>$field){

						// check

						if(isset($tmp_data[$index])){

							$data[$field] = urldecode($tmp_data[$index]);			

						}

					}					

					// set 

					$temp_resp['response_status']  = (isset($data['x_response_code'])) ? $data['x_response_code'] : '';	

					$temp_resp['message_code']     = (isset($data['x_response_reason_code'])) ? $data['x_response_reason_code'] : '';

					$temp_resp['message_text']     = (isset($data['x_response_reason_text'])) ? $data['x_response_reason_text'] : '';

					$temp_resp['transaction_id']   = (isset($data['x_transaction_id'])) ?  $data['x_transaction_id'] : '';

					$temp_resp['transaction_type'] = (isset($data['x_transaction_type'])) ? $data['x_transaction_type'] : '';				

				}	

			break;

			// check response in each iterations of ARB 

			case 'arb_silent_post':

				// Note that the content is an array here:								

				// error

				if(count($content)==0){					

					$temp_resp['response_status'] = 3;

					$temp_resp['message_text']    = __('Error occured', 'mgm');

				}else{					

					// pos params

					$fields = array(0=>"x_response_code", 1=>"x_response_subcode", 2=>"x_response_reason_code", 3=>"x_response_reason_text",

		                            4=>"x_invoice_num", 5=>"x_trans_id", 6=>"x_transaction_id", 9=>"x_amount", 10 => "x_type", 11 => "x_subscription_id");

					// store					

					foreach($fields as $index=>$field){						

						${$field} = urldecode($content[$field]);									

					}					

					// set 

					$temp_resp['response_status']  = $x_response_code;	

					$temp_resp['message_code']     = $x_response_reason_code;

					$temp_resp['message_text']     = $x_response_reason_text;

					$temp_resp['transaction_id']   = $x_transaction_id;

					$temp_resp['transaction_type'] = $x_type;				

					$temp_resp['invoice_id'] 	   = $x_invoice_num;				

					$temp_resp['subscription_id']  = $x_subscription_id;				

				}

			break;	

			//bypass trial cost if 0

			case 'bypass':				

				$temp_resp['response_status']  = 1;						

				$temp_resp['transaction_type'] = 'trial_bypass';

			break;

			case 'transaction_details':

				$xml = @simplexml_load_string($content);				

				if($xml){

					// transaction

					$temp_resp['transId']            = (string)$xml->transaction->transId;	

					// batch

					if(isset($xml->transaction->batch->batchId)){

						$temp_resp['batchId']      = (string)$xml->transaction->batch->batchId;	

					}						

					// tran date is update in rebill

					if(isset($xml->transaction->batch->settlementTimeLocal)){

						$temp_resp['transDate']      = (string)$xml->transaction->batch->settlementTimeUTC;

					}else{

						$temp_resp['transDate']      = (string)$xml->transaction->submitTimeUTC;		

					}			

				}else{

					$temp_resp['response_status']    = 3;

					$temp_resp['message_text']       = 'Error parsing XML';					

				}				

			break;

			case 'subscription_status':

				$xml = @simplexml_load_string($content);				

				if($xml){

					$resultCode                       = (string)$xml->messages->resultCode;

					$temp_resp['response_status']     = (strtolower($resultCode) == 'ok') ? 1 : 3; // 1 success, 3 error

					// subscription_status

					$temp_resp['subscription_status'] = (string)$xml->status;

					$temp_resp['message_code']        = (string)$xml->messages->message->code ;	

					$temp_resp['message_text']        = (string)$xml->messages->message->text ;

				}else{

					$temp_resp['response_status']     = 3;

					$temp_resp['message_text']        = 'Error parsing XML';					

				}

			break;

		}		

		

		// return

		if(!$set_response) return $temp_resp;

			

		// set

		$this->response = $temp_resp;		

	}	

	

	// get code

	function _get_response_code($key, $type='status'){

		// status

		$response_code['status']  = array(1 => "Approved", 2 => "Declined", 3 => "Error", 4 => "Held for Review");	

		// avs: address verification

		$response_code['avs']     = 

			array("A" => "Address (Street) matches, ZIP does not",

				  "B" => "Address information not provided for AVS check",

				  "E" => "AVS error",

				  "G" => "Non-U.S. Card Issuing Bank",

				  "N" => "No Match on Address (Street) or ZIP",

				  "P" => "AVS not applicable for this transaction",

				  "R" => "Retry System unavailable or timed out",

				  "S" => "Service not supported by issuer",

				  "U" => "Address information is unavailable",

				  "W" => "Nine digit ZIP matches, Address (Street) does not",

				  "X" => "Address (Street) and nine digit ZIP match",

				  "Y" => "Address (Street) and five digit ZIP match",

				  "Z" => "Five digit ZIP matches, Address (Street) does not)");

										 

		// cvv: card verification						 

		$response_code['cvv2'] = array("M" => "Match", "N" => "No Match", "P" => "Not Processed", "S" => "Should have been present", 

								       "U" => "Issuer unable to process request");									   		

		// check

		if(isset($response_code[$type][$key])){

			return $response_code[$type][$key];

		}				   

		// default

		return 'error';

	}

	

	// _format_phone

	function _format_phone($phone){

		// 111- 111-1111 /  (111) 111-1111.

		// clean any non digizs

		$phone = preg_replace("/[^0-9]+/i",'',$phone);

		// init 

		if(strlen($phone) != 10){

			return '';

		}else{

			return sprintf('(%d) %d-%d', substr($phone,0,3), substr($phone,3,3),substr($phone,6,4));

		}

	}

	

	// fetch

	function _fetch_merchant_txn_id(&$member){



		if (!isset($member->payment_info->txn_id) || empty($member->payment_info->txn_id)) {

			// check from transaction table

			$paymill_transaction_id = mgm_get_transaction_option($member->transaction_id, 'paymill_transaction_id');



			if( (int)$paymill_transaction_id > 0 ){

			// set	

				$member->payment_info->txn_id = $paymill_transaction_id;

				// log

				// mgm_log('fetch from transaction option: '. $member->payment_info->txn_id, __FUNCTION__);

				// save

				return $member->save();

			}else{

				// // fetch from batch, @todo test before running

				$paymill_transaction_id = mgm_fetch_paymill_missing_txn_id($this, $member->id);

				

				if( (int)$paymill_transaction_id > 0 ){

					// set

				 	$member->payment_info->txn_id = $paymill_transaction_id;

				 	// log

					// mgm_log('fetch from refetch using batch api: '. $member->payment_info->txn_id, __FUNCTION__);

				 	// save

				 	return $member->save();

				}

			}

		}

	}



	// get settled batches

	function get_settled_batches($date_from, $date_to=NULL){

		// ISO 8601 date

		if( is_null($date_to) ){

			$date_from_iso = date('c', strtotime('-1 DAY', strtotime($date_from)));// makes 1 day range

			$date_to_iso   = date('c', strtotime('+1 DAY', strtotime($date_from)));// makes 1 day range

		}else{

			$date_from_iso = date('c', strtotime($date_from));

			$date_to_iso   = date('c', strtotime($date_to));

		}

		// xml request

		$post_data = '

		<getSettledBatchListRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">

		    <merchantAuthentication>

		        <name>'.$this->setting['loginid'].'</name>

		        <transactionKey>'.$this->setting['tran_key'].'</transactionKey>

		    </merchantAuthentication>

		    <includeStatistics>1</includeStatistics>

		    <firstSettlementDate>'.$date_from_iso.'</firstSettlementDate>

		    <lastSettlementDate>'.$date_to_iso.'</lastSettlementDate>

		</getSettledBatchListRequest>';



		// end  point

		$endpoint = $this->_get_endpoint($this->status . '_arb') ; // test_arb, live_aim etc.	



		// headers

		$http_headers = array('Content-Type' => 'text/xml');	

		

		// create curl post				

		$http_response = mgm_remote_post($endpoint, $post_data, array('headers'=>$http_headers,'timeout'=>30,'sslverify'=>false));

	

		// init

		$batches = array();

		// object

		if($xml_o = @simplexml_load_string($http_response)){

			// log

			// mgm_log('batches response:' . mgm_pr($xml_o, true), __FUNCTION__);

			// check

			if( isset($xml_o->batchList->batch) ){				

				// array of batches

				if( count($xml_o->batchList->batch) > 0 ){

					// loop

					foreach ($xml_o->batchList->batch as $batch) {

						$batches[] = (string)$batch->batchId;

					}

				}elseif(isset($xml_o->batchList->batch->batchId)){

				// one batch	

					$batches[] = (string)$xml_o->batchList->batch->batchId;

				}				

			}	

			// log

			// mgm_log('batches: '. mgm_pr($batches, true), __FUNCTION__);		

		}

		// return

		return $batches;

	}



	// get batch transactions

	function get_batch_transactions($batches){	

		// xml request 

		$post_data_s ='

		<getTransactionListRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">

		    <merchantAuthentication>

		        <name>'.$this->setting['loginid'].'</name>

		        <transactionKey>'.$this->setting['tran_key'].'</transactionKey>

		    </merchantAuthentication>

		    <batchId>{batch_id}</batchId>

		</getTransactionListRequest>';



		// end  point

		$endpoint = $this->_get_endpoint($this->status . '_arb') ; // test_arb, live_aim etc.	



		// headers

		$http_headers = array('Content-Type' => 'text/xml');	

				

		// transactions

		$transactions = array();

		// loop

		foreach($batches as $batchId){

			// id replace

			$post_data = str_replace('{batch_id}', $batchId, $post_data_s);

			// create curl post				

			$http_response = mgm_remote_post($endpoint, $post_data, array('headers'=>$http_headers,'timeout'=>30,'sslverify'=>false));



			// object

			if($xml_o = @simplexml_load_string($http_response)){

				// log

				// mgm_log('batch transactions response:' . mgm_pr($xml_o, true), __FUNCTION__);

				// check

				if( isset($xml_o->transactions->transaction) ){

					// array

					if( count($xml_o->transactions->transaction) ){

						// loop

						foreach ($xml_o->transactions->transaction as $transaction) {

							// set

							$transactions[(string)$transaction->invoiceNumber] = (string)$transaction->transId;

						}

					}elseif( isset($xml_o->transactions->transaction->transId) ){

						// set

						$transactions[(string)$xml_o->transactions->transaction->invoiceNumber] = (string)$xml_o->transactions->transaction->transId;

					}					

				}

				// log

				// mgm_log('batch transactions: '. mgm_pr($transactions, true), __FUNCTION__);

			}

		}

		

		// return 

		return $transactions;

	}



	function get_settled_transactions($date_from, $date_to=NULL){

		// init

		$transactions = array();

		// fetch batches

		if( $batches = $this->get_settled_batches($date_from, $date_to) ){

			// transactions

			$transactions = $this->get_batch_transactions($batches);

		}

		// return

		return $transactions;

	}

}
?>