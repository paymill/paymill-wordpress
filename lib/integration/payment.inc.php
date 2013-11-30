<?php

	// this file should normalize and handle all payments from all ecommerce suites.

class paymill_payment{

	private $paymentData				= false;

	public function __construct($client_id){
		require_once(PAYMILL_DIR.'lib/api/Payments.php');
	
		// create payment object for future transactions and/or subscriptions
		$paymentsObject = new Services_Paymill_Payments(
			$GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'],
			$GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']
		);
		
		$paymentsParam = array(
			'token'			=> $_POST['paymillToken'],
			'client'		=> $client_id
		);
		
		$this->paymentData	= $paymentsObject->create($paymentsParam); // Use this for other payment
		
		/*$response = $this->paymentData->getResponse();
		var_dump($response);
		if(isset($response['body']['data']['response_code']) && $response['body']['data']['response_code'] != '20000'){
			echo __($response['body']['data']['response_code'], 'paymill');
			die();
		}*/
	}
	
	public function getPaymentID(){
		return $this->paymentData['id'];
	}
	
	
}
?>