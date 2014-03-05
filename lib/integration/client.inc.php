<?php

	// this file should normalize and handle all clients from all ecommerce suites.

class paymill_client{

	private $client				= false;
	
	public function __construct($client_email,$client_desc){
		global $wpdb;
	
		require_once(PAYMILL_DIR.'lib/api/Clients.php');
		
		// create client object
		$clientsObject = new Services_Paymill_Clients(
			$GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'],
			$GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']
		);
		
		// get client cache
		$query					= 'SELECT * FROM '.$wpdb->prefix.'paymill_clients WHERE paymill_client_email="'.$client_email.'"';
		$client_cache			= $wpdb->get_results($query,ARRAY_A);
		$client					= false;

		if(count($client_cache) > 0 && ($client_cache[0]['paymill_client_email'] != $client_email || $client_cache[0]['paymill_client_description'] != $client_desc)){
			// update client in paymill
			$params = array(
				'id'			=> $client_cache[0]['paymill_client_id'],
				'email'			=> $client_email,
				'description'	=> $client_desc,
				'source'		=> serialize($GLOBALS['paymill_source'])
			);
			$client				= $clientsObject->update($params);
			
			// update local cache
			$query				= 'UPDATE '.$wpdb->prefix.'paymill_clients SET paymill_client_description="'.$client_desc.'" WHERE paymill_client_email="'.$client_email.'"';
			$wpdb->query($query);
			
			do_action('paymill_paybutton_client_updated', array(
				'client'		=> $client,
				'client_email'	=> $client_email,
				'client_desc'	=> $client_desc
			));
		
		// try loading the client
		}elseif(count($client_cache) > 0){
			$client				= $clientsObject->getOne($client_cache[0]['paymill_client_id']);
			if($client['http_status_code'] == 404){
				$client = false;
			}
		}
		if($client == false){
			$client			 	= $clientsObject->create(array(
				'email'			=> $client_email, 
				'description'	=> $client_desc
			));
			
			// insert new client in local cache
			if(get_current_user_id()){
				$user_id = get_current_user_id();
			}else{
				$user_id = 0;
			}
			
			$query				= 'INSERT INTO '.$wpdb->prefix.'paymill_clients (paymill_client_id, paymill_client_email, paymill_client_description, wp_member_id) VALUES ("'.$client['id'].'", "'.$client_email.'", "'.$client_desc.'", "'.$user_id.'")';
			$wpdb->query($query);
			
			do_action('paymill_paybutton_client_created', array(
				'client'		=> $client,
				'client_email'	=> $client_email,
				'client_desc'	=> $client_desc
			));
		}

		$this->client			= $client;
	}
	
	public function getCurrentClient(){
		return $this->client;
	}
}
?>