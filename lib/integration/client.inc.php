<?php

	// this file should normalize and handle all clients from all ecommerce suites.

class paymill_client{

	private $client				= false;
	
	public function __construct($client_email,$client_desc){
		global $wpdb;
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		$client = false;
	
		// get client cache
		$sql = $wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'paymill_clients WHERE paymill_client_email=%s',
		array(
			$client_email
		));

		$client_cache			= $wpdb->get_results($sql,ARRAY_A);
		if(count($client_cache) > 0 && ($client_cache[0]['paymill_client_email'] != $client_email || $client_cache[0]['paymill_client_description'] != $client_desc)){
			// update client in paymill
			$GLOBALS['paymill_loader']->request_client->setId($client_cache[0]['paymill_client_id']);
			$GLOBALS['paymill_loader']->request_client->setEmail($client_email);
			$GLOBALS['paymill_loader']->request_client->setDescription($client_desc);

			// @todo: handle response
			$client = $GLOBALS['paymill_loader']->request->update($GLOBALS['paymill_loader']->request_client);
			
			// update local cache
			$wpdb->query($wpdb->prepare('UPDATE '.$wpdb->prefix.'paymill_clients SET paymill_client_description=%s WHERE paymill_client_email=%s',
			array(
				$client_desc,
				$client_email
			)));
			
			do_action('paymill_paybutton_client_updated', array(
				'client'		=> $client,
				'client_email'	=> $client_email,
				'client_desc'	=> $client_desc
			));
		// try loading the client
		}elseif(count($client_cache) > 0){
			try{
				$GLOBALS['paymill_loader']->request_client->setId($client_cache[0]['paymill_client_id']);
				$client = $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_client);
			}catch(Exception $e){
			}
		// client does not exist in Paymill, so create
		}
		
		if($client == false){
			try{
				$GLOBALS['paymill_loader']->request_client = new $GLOBALS['paymill_loader']->request_client;
				$GLOBALS['paymill_loader']->request_client->setEmail($client_email);
				$GLOBALS['paymill_loader']->request_client->setDescription($client_desc);
				
				// @todo: handle response
				$client = $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_client);
			}catch(Exception $e){
				$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			}
			
			// insert new client in local cache
			if(get_current_user_id()){
				$user_id = get_current_user_id();
			}else{
				$user_id = 0;
			}
			
			$wpdb->query($wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_clients WHERE wp_member_id="%s"',array($user_id)));

			$sql = $wpdb->prepare('INSERT INTO '.$wpdb->prefix.'paymill_clients
			SET paymill_client_id="%s", paymill_client_email="%s", paymill_client_description="%s", wp_member_id="%s"',
			array(
				$client->getId(),
				$client_email,
				$client_desc,
				$user_id
			));
			$wpdb->query($sql);
			
			do_action('paymill_paybutton_client_created', array(
				'client'		=> $client,
				'client_email'	=> $client_email,
				'client_desc'	=> $client_desc
			));
		}
		
		$this->client			= $client;
	}
	
	public function getCurrentClient(){
		if(get_current_user_id()){
			return $this->client;
		}else{
			return false;
		}
	}
	public function getCurrentClientID(){
		if(get_current_user_id()){
			return $this->client->getId();
		}else{
			return false;
		}
	}
	
}
?>