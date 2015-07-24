<?php

class paymill_subscriptions{

	private $store						= false;
	private $subscriptionsObject		= false;
	private $cache						= false;

	public function __construct($store){
		$this->store				= $store;
	}

	public function getList(){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_getList'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		try{
			$output			= $request->getAll($GLOBALS['paymill_loader']->request_subscription);
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_getList'); // benchmark
		return $output;
	}
	public function details($sub_id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_details'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
	
		try{
			$GLOBALS['paymill_loader']->request_subscription->setId($sub_id);
			$output			= $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_subscription);
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_details'); // benchmark
		return $output;
	}
	public function create($client, $offer, $payment, $startAt=false, $periodOfValidity=false){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_create'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		try{
			$GLOBALS['paymill_loader']->request_subscription->setClient($client);
			$GLOBALS['paymill_loader']->request_subscription->setOffer($offer);
			$GLOBALS['paymill_loader']->request_subscription->setPayment($payment);

			if($startAt && intval($startAt) > 0 && intval($startAt) > time()){
				$GLOBALS['paymill_loader']->request_subscription->setStartAt(intval($startAt));
			}
			$GLOBALS['paymill_loader']->request_subscription->setPeriodOfValidity($periodOfValidity);

			$subscription	= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_subscription);
			$output			= $subscription->getId();
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_create'); // benchmark
		return $output;
	}/* @todo
	public function update(){
		$params = array(
			'id'					=> '',
			'cancel_at_period_end'	=> true,
			'offer'					=> '',
			'payment'				=> ''
		);
		$subscription				= $this->subscriptionsObject->update($params);
	}*/
	public function remove($sub_id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_remove'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		try{
			$GLOBALS['paymill_loader']->request_subscription->setId($sub_id);

			$response = $GLOBALS['paymill_loader']->request->delete($GLOBALS['paymill_loader']->request_subscription);
			$output			= $response;
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_remove'); // benchmark
		return $output;
	}
	public function pause($sub_id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_pause'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		try{
			$GLOBALS['paymill_loader']->request_subscription->setId($sub_id);
			$GLOBALS['paymill_loader']->request_subscription->setPause(true);

			$response = $GLOBALS['paymill_loader']->request->update($GLOBALS['paymill_loader']->request_subscription);
			$output			= $response;
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}

		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_pause'); // benchmark
		return $output;
	}
	public function unpause($sub_id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_unpause'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		try{
			$GLOBALS['paymill_loader']->request_subscription->setId($sub_id);
			$GLOBALS['paymill_loader']->request_subscription->setPause(false);

			$response = $GLOBALS['paymill_loader']->request->update($GLOBALS['paymill_loader']->request_subscription);
			$output			= $response;
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}

		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_unpause'); // benchmark
		return $output;
	}

	public function offerGetList($reCache=false){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerGetList'); // benchmark
		global $wpdb;

		try{
			if($reCache === true){
				load_paymill(); // this function-call can and should be used whenever working with Paymill API
				$wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'paymill_cache_offers');
				
				$offersList														= $GLOBALS['paymill_loader']->request->getAll($GLOBALS['paymill_loader']->request_offer);

				foreach($offersList as $offer){
					$wpdb->insert(
						$wpdb->prefix.'paymill_cache_offers',
						array(
							'id'												=> $offer['id'],
							'name'												=> $offer['name'],
							'amount'											=> $offer['amount'],
							'currency'											=> $offer['currency'],
							'interval'											=> $offer['interval'],
							'trial_period_days'									=> $offer['trial_period_days']
						),
						array(
							'%s',
							'%s',
							'%d',
							'%s',
							'%s',
							'%d'
						)
					);
					$this->cache['subscription_plans'][$offer['id']]			= $offer;
					$this->cache['subscription_plans_by_name'][$offer['name']]	= $offer;
				}
			}elseif(empty($this->cache['subscription_plans'])){
				$query															= 'SELECT * FROM '.$wpdb->prefix.'paymill_cache_offers';
				$offersList														= $wpdb->get_results($query,ARRAY_A);
				foreach($offersList as $offer){
					$this->cache['subscription_plans'][$offer['id']]			= $offer;
					$this->cache['subscription_plans_by_name'][$offer['name']]	= $offer;
				}
			}else{
				$output															= false;
			}
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output																= false;
		}

		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerGetList'); // benchmark
		return $this->cache['subscription_plans'];
	}
	public function offerGetDetailByID($id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerGetDetailByID'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		if(isset($this->cache['subscription_plans'][$id]) && is_array($this->cache['subscription_plans'][$id]) && count($this->cache['subscription_plans'][$id]) > 0){
			$output = $this->cache['subscription_plans'][$id];
		}else{
			$this->offerGetList(true);
			if(isset($this->cache['subscription_plans'][$id]) && is_array($this->cache['subscription_plans'][$id]) && count($this->cache['subscription_plans'][$id]) > 0){
				$output = $this->cache['subscription_plans'][$id];
			}else{
				$output = false;
			}
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerGetDetailByID'); // benchmark
		return $output;
	}
	public function offerGetDetailByName($name){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerGetDetailByName'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		if(isset($this->cache['subscription_plans_by_name'][$name]) && is_array($this->cache['subscription_plans_by_name'][$name]) && count($this->cache['subscription_plans_by_name'][$name]) > 0){
			$output = $this->cache['subscription_plans_by_name'][$name];
		}else{
			$this->offerGetList(true);
			if(isset($this->cache['subscription_plans_by_name'][$name]) && is_array($this->cache['subscription_plans_by_name'][$name]) && count($this->cache['subscription_plans_by_name'][$name]) > 0){
				$output = $this->cache['subscription_plans_by_name'][$name];
			}else{
				$output = false;
			}
		}
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerGetDetailByName'); // benchmark
		return $output;
	}
	
	public function offerCreate($params){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerCreate'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		try{
			$GLOBALS['paymill_loader']->request_offer->setAmount(round($params['amount']));
			$GLOBALS['paymill_loader']->request_offer->setCurrency($params['currency']);
			$GLOBALS['paymill_loader']->request_offer->setInterval($params['interval']);
			$GLOBALS['paymill_loader']->request_offer->setName($params['name']);
			$GLOBALS['paymill_loader']->request_offer->setTrialPeriodDays($params['trial_period_days']);

			$output			= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_offer);
			
			$offerID		= $output->getId();

			$this->offerGetList(true);
			//$output			= $this->offerGetDetailByID($offerID);
			$output			= $offerID;
		}catch(Exception $e){
			$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			$output			= false;
		}

		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerCreate'); // benchmark
		return $output;
	}
}