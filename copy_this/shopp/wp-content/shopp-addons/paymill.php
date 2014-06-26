<?php
/**
 * Paymill
 * @class PaymillShopp
 *
 * @author Matthias Reuter
 * @version 1.2
 * @copyright Matthias Reuter, GPL licensed
 * @package shopp
 * @since 1.2
 * @subpackage PaymillShopp
 **/
 
/*
class PaymillShopp extends GatewayFramework implements GatewayModule {

*/

if(defined('PAYMILL_DIR') && file_exists(PAYMILL_DIR.'lib/integration/shopplugin.inc.php')){
	require_once(PAYMILL_DIR.'lib/integration/shopplugin.inc.php');
}

?>