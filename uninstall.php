<?php
	if(!defined( 'WP_UNINSTALL_PLUGIN')){
		exit();
	}

	global $wpdb;
	
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'paymill_clients');
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'paymill_transactions');
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'paymill_cache');
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'paymill_subscriptions');
	$wpdb->query('DELETE FROM '.$wpdb->options.' WHERE option_name LIKE "paymill_%"');

?>