<?php

	// this is required for getting API work
	spl_autoload_register(function ($class) {
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		$GLOBALS['paymill_loader']->load_class_file($class);
	});

	// there may be more elegant ways for autoloading, but I'm new to namespaces and I'm rare of time atm.
	// @todo: review for better way of autoloading Paymill API classes.
	class paymill_loader{
		public 		$translation		= array(
			// for use in plugin
			'request'									=> array('lib/api/lib/Paymill/Request.php',								'Paymill\Request'),
			'request_client'							=> array('lib/api/lib/Paymill/Models/Request/Client.php',				'Paymill\Models\Request\Client'),
			'request_offer'								=> array('lib/api/lib/Paymill/Models/Request/Offer.php',				'Paymill\Models\Request\Offer'),
			'request_payment'							=> array('lib/api/lib/Paymill/Models/Request/Payment.php',				'Paymill\Models\Request\Payment'),
			'request_preauth'							=> array('lib/api/lib/Paymill/Models/Request/Preauthorization.php',		'Paymill\Models\Request\Preauthorization'),
			'request_refund'							=> array('lib/api/lib/Paymill/Models/Request/Refund.php',				'Paymill\Models\Request\Refund'),
			'request_subscription'						=> array('lib/api/lib/Paymill/Models/Request/Subscription.php',			'Paymill\Models\Request\Subscription'),
			'request_transaction'						=> array('lib/api/lib/Paymill/Models/Request/Transaction.php',			'Paymill\Models\Request\Transaction'),
			'request_webhook'							=> array('lib/api/lib/Paymill/Models/Request/Webhook.php',				'Paymill\Models\Request\Webhook'),
			'request_checksum'							=> array('lib/api/lib/Paymill/Models/Request/Checksum.php',				'Paymill\Models\Request\Checksum'),
			'response_base'								=> array('lib/api/lib/Paymill/Models/Response/Base.php'),
			'response_client'							=> array('lib/api/lib/Paymill/Models/Response/Client.php',				'Paymill\Models\Response\Client'),
			'response_error'							=> array('lib/api/lib/Paymill/Models/Response/Error.php',				'Paymill\Models\Response\Error'),
			'response_offer'							=> array('lib/api/lib/Paymill/Models/Response/Offer.php',				'Paymill\Models\Response\Offer'),
			'response_payment'							=> array('lib/api/lib/Paymill/Models/Response/Payment.php',				'Paymill\Models\Response\Payment'),
			'response_preauth'							=> array('lib/api/lib/Paymill/Models/Response/Preauthorization.php',	'Paymill\Models\Response\Preauthorization'),
			'response_refund'							=> array('lib/api/lib/Paymill/Models/Response/Refund.php',				'Paymill\Models\Response\Refund'),
			'response_subscription'						=> array('lib/api/lib/Paymill/Models/Response/Subscription.php',		'Paymill\Models\Response\Subscription'),
			'response_transaction'						=> array('lib/api/lib/Paymill/Models/Response/Transaction.php',			'Paymill\Models\Response\Transaction'),
			'response_webhook'							=> array('lib/api/lib/Paymill/Models/Response/Webhook.php',				'Paymill\Models\Response\Webhook'),
			'response_checksum'							=> array('lib/api/lib/Paymill/Models/Response/Checksum.php',			'Paymill\Models\Response\Checksum'),
			'curl'										=> array('lib/api/lib/Paymill/API/Curl.php',							'PAYMILL\API\Curl'),
			
			// for use within API
			'Paymill\Models\Response\Base'				=> array('lib/api/lib/Paymill/Models/Response/Base.php'),
			'Paymill\Services\Util'						=> array('lib/api/lib/Paymill/Services/Util.php'),
			'Paymill\Models\Response\Client'			=> array('lib/api/lib/Paymill/Models/Response/Client.php',				'Paymill\Models\Response\Client'),
			'Paymill\Models\Response\Error'				=> array('lib/api/lib/Paymill/Models/Response/Error.php',				'Paymill\Models\Response\Error'),
			'Paymill\Models\Response\Offer'				=> array('lib/api/lib/Paymill/Models/Response/Offer.php',				'Paymill\Models\Response\Offer'),
			'Paymill\Models\Response\Payment'			=> array('lib/api/lib/Paymill/Models/Response/Payment.php',				'Paymill\Models\Response\Payment'),
			'Paymill\Models\Response\Preauthorization'	=> array('lib/api/lib/Paymill/Models/Response/Preauthorization.php',	'Paymill\Models\Response\Preauthorization'),
			'Paymill\Models\Response\Refund'			=> array('lib/api/lib/Paymill/Models/Response/Refund.php',				'Paymill\Models\Response\Refund'),
			'Paymill\Models\Response\Subscription'		=> array('lib/api/lib/Paymill/Models/Response/Subscription.php',		'Paymill\Models\Response\Subscription'),
			'Paymill\Models\Response\Transaction'		=> array('lib/api/lib/Paymill/Models/Response/Transaction.php',			'Paymill\Models\Response\Transaction'),
			'Paymill\Models\Response\Webhook'			=> array('lib/api/lib/Paymill/Models/Response/Webhook.php',				'Paymill\Models\Response\Webhook'),
			'Paymill\Models\Response\Checksum'			=> array('lib/api/lib/Paymill/Models/Response/Checksum.php',			'Paymill\Models\Response\Checksum'),
			
			// paymill plugin classes
			'paymill_errors'							=> array('lib/errors.inc.php',											'paymill_errors')
		);
		
		public function __construct(){
			// make sure to load some dependencies
			require_once(PAYMILL_DIR.'lib/api/lib/Paymill/API/CommunicationAbstract.php');
			require_once(PAYMILL_DIR.'lib/api/lib/Paymill/API/Curl.php');
			require_once(PAYMILL_DIR.'lib/api/lib/Paymill/Models/Request/Base.php');
			require_once(PAYMILL_DIR.'lib/api/lib/Paymill/Models/Response/Error.php');
			require_once(PAYMILL_DIR.'lib/api/lib/Paymill/Services/PaymillException.php');
			require_once(PAYMILL_DIR.'lib/api/lib/Paymill/Services/ResponseHandler.php');
		}
	
		public function __get($name){
			return $this->load_class($name);
		}
		
		public function load_class($name){
			if($this->load_class_file($name)){
				return $this->init_class($name);
			}
		}
		public function load_class_file($name){
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_API_'.$name.'_loaded'); // benchmark
			// requested class in list?
			if(isset($this->translation[$name]) && is_array($this->translation[$name]) && count($this->translation[$name]) > 0){
				// class file exists?
				if(file_exists(PAYMILL_DIR.$this->translation[$name][0])){
					// load file
					require_once(PAYMILL_DIR.$this->translation[$name][0]);
					if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_API_'.$name.'_loaded'); // benchmark
					return true;
				}else{
					return false;
				}
			}
		}
		public function init_class($name){
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_API_'.$name.'_initialized'); // benchmark
			// default parameters
			$default_param = array(
				'request'				=> (isset($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private']) ? $GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'] : false)
			);
			
			// init class?
			if(isset($this->translation[$name][1]) && strlen($this->translation[$name][1]) > 0){
				// parameter for init given?
				if(isset($default_param[$name])){
					$this->$name = new $this->translation[$name][1]($default_param[$name]);
					if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_API_'.$name.'_initialized'); // benchmark
					return $this->$name;
				// no parameter given for init
				}else{
					$this->$name = new $this->translation[$name][1]();
					if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_API_'.$name.'_initialized'); // benchmark
					return $this->$name;
				}
			}
		}
	}
?>