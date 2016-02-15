<?php

	// this file should normalize and handle all payments from all ecommerce suites.

class paymill_payment{

	private $paymentData				= false;
	private $preauthData				= false;

	public function __construct($client_id,$amount,$currency,$order=false){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_payment'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		if($this->paymentData === false && !isset($_GET['paymill_payment_id']) && !isset($_GET['paypal_trx_id']) && !isset($_GET['paymill_trx_id'])){
			try{
				$GLOBALS['paymill_loader']->request_payment->setToken($_POST['paymillToken']);
				$GLOBALS['paymill_loader']->request_payment->setClient($client_id);
				
				$this->paymentData	= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_payment); // Use this for further payment processing
				
				// setup preauth only if paymenttype is creditcard and subscription
				/*if(is_object($this->paymentData) && $this->paymentData->getType() == 'creditcard' && $order && class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($order)){
					$GLOBALS['paymill_loader']->request_preauth->setPayment($this->getPaymentID());
					$GLOBALS['paymill_loader']->request_preauth->setAmount(round($amount));
					$GLOBALS['paymill_loader']->request_preauth->setCurrency($currency);

					$this->preauthData	= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_preauth); // Use this for further payment processing, too
				}*/
				
				// preauth deactivated since v1.10.7
			}catch(Exception $e){
				$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			}
		}

		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_payment'); // benchmark
	}
	
	public function getPaymentID(){
		if($this->paymentData){
			return $this->paymentData->getId();
		}elseif(isset($_GET['paymill_payment_id'])){
			return $_GET['paymill_payment_id'];
		}elseif(isset($_GET['paymill_trx_id'])){ // retrieve payment ID from given paymill_trx_id
			$GLOBALS['paymill_loader']->request_transaction->setId($_GET['paymill_trx_id']);
			$transaction = $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_transaction);
			$payment = $transaction->getPayment();
			return $payment->getId();
		}elseif(isset($_GET['paypal_trx_id'])){ // retrieve payment ID from given paypal_trx_id
			$GLOBALS['paymill_loader']->request_transaction->setFilter(array('short_id' => $_GET['paypal_trx_id']));
			$transaction = $GLOBALS['paymill_loader']->request->getAll($GLOBALS['paymill_loader']->request_transaction);

			return $transaction['payment']['id'];
		}else{
			return false;
		}
	}
	
	public function getPreauthID(){
		if(is_object($this->preauthData)){
			return $this->preauthData->getId();
		}else{
			return false;
		}
	}
	
	public function removePreauth(){
		if($this->getPreauthID()){
			$GLOBALS['paymill_loader']->request_preauth->setId($this->getPreauthID());
			$GLOBALS['paymill_loader']->request->delete($GLOBALS['paymill_loader']->request_preauth);
		}
	}
}
?>