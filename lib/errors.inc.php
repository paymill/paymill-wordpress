<?php

class paymill_errors{

	private $errors				= array();
	private $outputFunction		= false;

	public function __construct(){
	
	}
	
	public function amount(){
		return count($this->errors);
	}
	
	public function status(){
		if($this->amount() > 0){
			return true;
		}
	}
	
	public function setFunction($function){
		if(is_string($function) && ($this->outputFunction === false || $this->outputFunction == 'paymill_pay_button_errorHandling')){
			$this->outputFunction		= $function;
			return true;
		}else{
			return false;
		}
	}
	
	public function getFunction(){
		return $this->outputFunction;
	}
	
	public function setError($error){
		$this->errors[]		= $error;
	}
	
	public function getErrors($function=false,$flush=false){
		if($function === false && $this->getFunction() !== false && is_string($this->getFunction())){
			$function = $this->getFunction();
		}
		if(strlen($function) > 0){
			return $function($this->errors);
		}else{
			return $this->errors;
		}
		if($flush === true){
			$this->errors = array();
		}
	}
	
	public function reset(){
		$this->errors		= array();
	}
}

?>