<?php

class paymill_subscriptions{

	var $store						= false;
	var $subscriptionsObject		= false;
	var $cache						= false;

	public function __construct($store){
		require_once(PAYMILL_DIR.'lib/api/Subscriptions.php');
		require_once(PAYMILL_DIR.'lib/api/Offers.php');
		require_once(PAYMILL_DIR.'lib/api/Preauthorizations.php');
	
		$this->subscriptionsObject	= new Services_Paymill_Subscriptions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
		$this->offersObject			= new Services_Paymill_Offers($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);
		$this->preAuthObject		= new Services_Paymill_Preauthorizations($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);

		$this->store				= $store;
	}

	public function getList(){
		return						$this->subscriptionsObject->get();
	}
	public function details($sub_id){
		return						$this->subscriptionsObject->getOne($sub_id);
	}
	public function create($client, $offer, $payment){
	
		// retrieve offer amount for preauthorization
		$offerDetail = $this->offerGetDetailByID($offer);

		// make preauth
		$params = array(
			'payment'				=> $payment,
			'amount'				=> $offerDetail[0]['amount'],
			'currency'				=> $offerDetail[0]['currency']
		);
		
		$preAuthResponse = $this->preAuthObject->create($params);
		
		if($preAuthResponse['preauthorization']['status'] == 'failed'){
			echo __($preAuthResponse['response_code'],'paymill');
			die();
		}else{
			$params = array(
				'client'				=> $client,
				'offer'					=> $offer,
				'payment'				=> $payment
			);

			$subscription				= $this->subscriptionsObject->create($params);
		
			return $subscription;
		}
	}/*
	public function update(){
		$params = array(
			'id'					=> '',
			'cancel_at_period_end'	=> true,
			'offer'					=> '',
			'payment'				=> ''
		);
		$subscription				= $this->subscriptionsObject->update($params);
	}*/
	public function remove($subscriptionid){
		$this->subscriptionsObject->delete($subscriptionid);
	}
	
	public function offerGetList($reCache=false){
		global $wpdb;
	
		if($reCache === true){
			$offersList = $this->offersObject->get();
			foreach($offersList as $offer){
				$offersListSorted[$offer['id']] = $offer;
			}
			$query = "REPLACE INTO ".$wpdb->prefix."paymill_cache SET cache_id='subscription_plans',cache_content='".$wpdb->escape(serialize($offersListSorted))."'";

			$wpdb->query($query);
			
			$this->cache['subscription_plans'] = $offersListSorted;

			return $offersListSorted;
		}elseif(is_array($this->cache['subscription_plans'])){
			return $this->cache['subscription_plans'];
		}else{
			$query				= 'SELECT * FROM '.$wpdb->prefix.'paymill_cache WHERE cache_id="subscription_plans"';
			$offersList			= $wpdb->get_results($query,ARRAY_A);
			
			$offersList = unserialize($offersList[0]['cache_content']);
			$this->cache['subscription_plans'] = $offersList;
			return $offersList;
		}
	}
	/*
	public function offerGetDetail($reCache=false){
		if(!$reCache && isset($this->cache[$offer_id]) && is_array($this->cache[$offer_id])){
			return $this->cache[$offer_id];
		}else{
			$this->offerGetList($reCache);
			return (isset($this->cache[$offer_id]) ? $this->cache[$offer_id] : false);
		}
	}*/
	public function offerGetDetailByID($id){
		return $this->offersObject->get($id);
	}
	public function offerGetDetailByName($name){
		return $this->offersObject->get(array('name' => $name));
	}
	
	public function offerCreate($params){
		/*$params = array(
			'amount'   => '4200',       // E.g. "4200" for 42.00 EUR
			'currency' => 'EUR',        // ISO 4217
			'interval' => '1 MONTH',
			'name'     => 'Test Offer'
		);*/
		
		return $this->offersObject->create($params);
	}
}