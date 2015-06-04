<?php
	// gather source info for security purposes and optimization
	$GLOBALS['paymill_source'] = array(
		'wordpress_version'				=> get_bloginfo('version'),
		'paymill_version'				=> PAYMILL_VERSION
	);
	// The main plugin class, holds everything our plugin does, initialized right after declaration
	class paymill_settings{
		// For easier overriding we declared the keys here as well as our tabs array which is populated when registering settings
		public	$setting_keys			= array();
		private	$plugin_options_key		= 'paymill_options';
		private	$plugin_settings_tabs	= array();
		
		// Fired during plugins_loaded (very very early), so don't miss-use this, only actions and filters, current ones speak for themselves.
		public function __construct() {
			$this->setting_keys['paymill_general_settings']			= 'paymill_general_settings';
			$this->setting_keys['paymill_pay_button_settings']		= 'paymill_pay_button_settings';
			$this->setting_keys['paymill_maintenance_settings']		= 'paymill_maintenance_settings';
			
			foreach($this->setting_keys as $key){
				$this->$key = (array) get_option( $key );
			}

			// Merge with defaults
			$this->paymill_pay_button_settings = array_merge( array(
				'number_decimal' => '.',
				'number_thousands' => ',',
				'currency' => 'EUR',
				'currency_format' => '%n%s'
			), $this->paymill_pay_button_settings );
			
			if(isset($this->paymill_general_settings['api_key_private']) && isset($this->paymill_general_settings['api_key_public']) && $this->paymill_general_settings['api_key_private'] != '' && $this->paymill_general_settings['api_key_public'] != ''){
				define('PAYMILL_ACTIVE',true);
			}else{
				define('PAYMILL_ACTIVE',false);
			}
		
			add_action('admin_init', array(&$this, 'paymill_register_general_settings'));
			if(defined('PAYMILL_ACTIVE') && PAYMILL_ACTIVE === true){
				add_action('admin_init', array(&$this, 'paymill_register_pay_button_settings'));
				add_action('admin_init', array(&$this, 'paymill_register_maintenance_settings'));
			}
			add_action('admin_menu', array(&$this, 'add_admin_menus'));
			
			// prepare dynamic language strings
			__('DAY', 'paymill');
			__('WEEK', 'paymill');
			__('MONTH', 'paymill');
			__('YEAR', 'paymill');
			__('DAYS', 'paymill');
			__('WEEKS', 'paymill');
			__('MONTHS', 'paymill');
			__('YEARS', 'paymill');
			
			__('50501','paymill');
			__('50001','paymill');
			__('50201','paymill');
			__('40103','paymill');
			__('50102','paymill');
			__('50103','paymill');
			__('40105','paymill');
			__('40101','paymill');
			__('40100','paymill');
			__('40104','paymill');
			__('40001','paymill');
			__('40102','paymill');
			__('40106','paymill');
			__('40201','paymill');
			__('50300','paymill');
			__('40202','paymill');
			__('50502','paymill');
			__('40301','paymill');
			__('40401','paymill');
			__('40402','paymill');
			__('40403','paymill');
			__('50104','paymill');
			__('50105','paymill');
			__('50600','paymill');
			__('50002','paymill');
			
			__('Token not Found','paymill');
			
			__('shipping');
			__('company_name');
			__('forename');
			__('surname');
			__('street');
			__('number');
			__('zip');
			__('city');
			__('email');
			__('phone');
			
			 // common errors, for translation purposes
			__('Token or Payment required', 'paymill');
			__('Subscription already connected', 'paymill');
		}
		// Registers the general settings via the Settings API, appends the setting to the tabs array of the object.
		public function paymill_register_general_settings(){
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_register_general_settings'); // benchmark
			
			// create or update webhooks when API key changes
			if(isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true' && isset($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private']) && strlen($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private']) > 0){
				paymill_install_webhooks();
			}
			
			$this->plugin_settings_tabs[$this->setting_keys['paymill_general_settings']] = 'General';
			register_setting($this->setting_keys['paymill_general_settings'], $this->setting_keys['paymill_general_settings']);

			add_settings_section('section_general', __('General Plugin Settings', 'paymill'), array( &$this, 'section_general_desc'), $this->setting_keys['paymill_general_settings'] );
			$settings = array(
				'api_key_private'	=> __('Paymill PRIVATE API key', 'paymill'),
				'api_key_public'	=> __('Paymill PUBLIC API key', 'paymill'),
				'payments_display'	=> __('Display Payment Types', 'paymill'),
				'no_default_css'	=> __('Do not load default CSS', 'paymill'),
				'pci_dss_3'			=> __('Deactivate PCI DSS 3.0 Compatibility', 'paymill'),
			);
			
			foreach($settings as $setting => $description){
				add_settings_field($setting, $description, array(&$this, 'print_config_form_fields'), $this->setting_keys['paymill_general_settings'], 'section_general', array('desc' => $setting, 'page' => $this->setting_keys['paymill_general_settings']));
			}
			
			if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_register_general_settings'); // benchmark
		}
		// Registers the pay_button settings and appends the key to the plugin settings tabs array.
		public function paymill_register_pay_button_settings(){
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_register_pay_button_settings'); // benchmark
			
			$this->plugin_settings_tabs[$this->setting_keys['paymill_pay_button_settings']] = 'Pay Button';
			register_setting( $this->setting_keys['paymill_pay_button_settings'], $this->setting_keys['paymill_pay_button_settings'] );
			
			// common
			add_settings_section('section_pay_button', false, array( &$this, 'section_pay_button_desc' ), $this->setting_keys['paymill_pay_button_settings']);
			$settings = array(
				'number_decimal'	=> __('Number Format: Decimal Point', 'paymill'),
				'number_thousands'	=> __('Number Format: Thousands Seperator', 'paymill'),
				'currency'			=> __('Currency', 'paymill'),
				'currency_format'	=> __('Currency Format', 'paymill'),
				'email_outgoing'	=> __('Outgoing Email', 'paymill'),
				'email_incoming'	=> __('Incoming Email', 'paymill'),
				'thankyou_url'		=> __('Thank You URL', 'paymill'),
				'fields_show'		=> __('Show Fields', 'paymill'),
			);
			
			foreach($settings as $setting => $description){
				add_settings_field($setting, $description, array(&$this, 'print_config_form_fields'), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button', array('desc' => $setting, 'page' => $this->setting_keys['paymill_pay_button_settings']));
			}
			
			// products
			add_settings_section('section_pay_button_products', false, array( &$this, 'section_pay_button_products_desc' ), $this->setting_keys['paymill_pay_button_settings']);

			if(isset($this->paymill_pay_button_settings['products'])){
				if(isset($this->paymill_pay_button_settings['products'][count($this->paymill_pay_button_settings['products'])]['products_title']) && strlen($this->paymill_pay_button_settings['products'][count($this->paymill_pay_button_settings['products'])]['products_title']) > 0){
					$products = count($this->paymill_pay_button_settings['products'])+5;
				}else{
					$products = count($this->paymill_pay_button_settings['products']);
				}
			}else{
				$products = 5;
			}
			
			$settings = array(
				'products_title'		=> __('Product', 'paymill'),
				'products_desc'			=> __('Description', 'paymill'),
				'products_quantityhide'	=> __('Hide Quantity', 'paymill'),
				'products_delivery'		=> __('Delivery Time', 'paymill'),
				'products_vat'			=> __('VAT', 'paymill'),
				'products_offer'		=> __('Subscription Offer', 'paymill'),
				'products_price'		=> __('Price', 'paymill'),
			);
			
			for($i = 1; $i <= $products; $i++){
				foreach($settings as $setting => $description){
					add_settings_field($setting.'_'.$i, $description, array(&$this, 'print_config_form_fields'), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products', array('desc' => $setting, 'id' => $i, 'group' => 'products', 'page' => $this->setting_keys['paymill_pay_button_settings']));
				}
			}
			// shipping
			add_settings_section( 'section_pay_button_shipping', false, array( &$this, 'section_pay_button_shipping_desc' ), $this->setting_keys['paymill_pay_button_settings'] );

			if(isset($this->paymill_pay_button_settings['flat_shipping'])){
				if(isset($this->paymill_pay_button_settings['flat_shipping'][count($this->paymill_pay_button_settings['flat_shipping'])]['flat_shipping_country']) && strlen($this->paymill_pay_button_settings['flat_shipping'][count($this->paymill_pay_button_settings['flat_shipping'])]['flat_shipping_country']) > 0){
					$shipping = count($this->paymill_pay_button_settings['flat_shipping'])+5;
				}else{
					$shipping = count($this->paymill_pay_button_settings['flat_shipping']);
				}
			}else{
				$shipping = 5;
			}
			
			$settings = array(
				'flat_shipping_country'	=> __('Shipping Country', 'paymill'),
				'flat_shipping_costs'	=> __('Shipping Costs', 'paymill'),
				'flat_shipping_vat'		=> __('Shipping VAT', 'paymill'),
			);

			for($i = 1; $i <= $shipping; $i++){
				foreach($settings as $setting => $description){
					add_settings_field($setting.'_'.$i, $description, array(&$this, 'print_config_form_fields'), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_shipping', array('desc' => $setting, 'id' => $i, 'group' => 'flat_shipping', 'page' => $this->setting_keys['paymill_pay_button_settings']));
				}
			}
			
			if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_register_pay_button_setting'); // benchmark
		}
		public function paymill_register_maintenance_settings(){
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_register_maintenance_settings'); // benchmark
			
			$this->plugin_settings_tabs[$this->setting_keys['paymill_maintenance_settings']] = 'Maintenance';
			register_setting($this->setting_keys['paymill_maintenance_settings'], $this->setting_keys['paymill_maintenance_settings']);

			add_settings_section('section_maintenance', __('Maintenance', 'paymill'), array( &$this, 'section_maintenance_desc'), $this->setting_keys['paymill_maintenance_settings'] );

			if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_register_maintenance_settings'); // benchmark
		}
		// The following methods provide descriptions for their respective sections, used as callbacks with add_settings_section
		public function section_maintenance_desc() { echo paymill_check_webhook(); }
		public function section_general_desc() { echo __('Please insert your API settings here.', 'paymill'); }
		public function section_pay_button_desc() { echo '<p>'.__('The Paymill Pay Buton is a simple, independent payment solution. As Paymill for WordPress is GPL licensed, feel free to customize that Pay Button to fit your needs.', 'paymill').'</p><h3>'.__('Common Settings', 'paymill').'</h3>'.'<p><strong>'.__('Configure common settings', 'paymill').'</strong></p><a href="#" id="common_toggle">'.__('Toggle View', 'paymill').'</a><div id="common_content" style="display:none;">'; }
		public function section_pay_button_products_desc() { echo '</div><h3>'.__('Products', 'paymill').'</h3><p><strong>'.__('Configure products for the Pay Button. This list has a dynamic length and extends for 5 extra slots when last slot\'s Product Title is filled and saved.', 'paymill').'</strong></p><a href="#" id="products_toggle">'.__('Toggle View', 'paymill').'</a><div id="products_content" style="display:none;">'; }
		public function section_pay_button_shipping_desc() { echo '</div><h3>'.__('Shipping', 'paymill').'</h3><p><strong>'.__('Set delivery countries and shipping costs. This list has a dynamic length and extends for 5 extra slots when last slot\'s Shipping Country is filled and saved.', 'paymill').'</strong></p><a href="#" id="shipping_toggle">'.__('Toggle View', 'paymill').'</a><div id="shipping_content" style="display:none;">'; }
		// Called during admin_menu, adds an options page under Settings called My Settings, rendered using the plugin_options_page method.
		public function add_admin_menus() {
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_add_admin_menus'); // benchmark
			$page = add_menu_page('Paymill', 'Paymill', 'manage_options', $this->plugin_options_key, array( &$this, 'plugin_options_page' ),  plugins_url('',__FILE__ ).'/img/icon.png');
			add_action( 'admin_print_styles-' . $page, 'paymill_load_admin_styles' );
			if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_add_admin_menus'); // benchmark
		}
		// Plugin Options page rendering goes here, checks for active tab and replaces key with the related settings key. Uses the plugin_options_tabs method to render the tabs.
		public function plugin_options_page(){
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_plugin_options_page'); // benchmark
			$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->setting_keys['paymill_general_settings'];
			echo '
			<a href="https://www.paymill.com/"><img src="'.plugins_url('',__FILE__ ).'/img/logo.png'.'" width="220" height="78" alt="Paymill" /></a>
			<div class="wrap">
				'.$this->plugin_options_tabs().'
				<form method="post" action="options.php">';
				settings_fields($tab).$this->paymill_do_settings_sections($tab);
			echo '</div>';
			submit_button();
			echo '</form></div>';
			if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_plugin_options_page'); // benchmark
		}
		// pay_button Option field callback, same as above.
		private function print_config_form_fields($args) {
			if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_field_pay_button_option'); // benchmark

			// setup of value fields
			$option		= '';
			$value		= '';
			$id			= '';
			$page		= $args['page'];

			if(isset($args['desc']) && empty($args['id'])){
				$option		= '['.$args['desc'].']';
				if(isset($this->{$page}[$args['desc']])){
					if(isset($args['group'])){
						$value		= esc_attr($this->{$page}[$args['group']][$args['desc']]);
					}else{
						$value		= esc_attr($this->{$page}[$args['desc']]);
					}
				}
			}elseif(isset($args['id']) && isset($args['desc'][$args['id']])){
				$option		= '['.$args['group'].']['.$args['id'].']['.$args['desc'].']';
				
				if(isset($args['group'])){
					if(isset($this->{$page}[$args['group']][$args['id']][$args['desc']])){
						$value		= esc_attr($this->{$page}[$args['group']][$args['id']][$args['desc']]);
					}else{
						$value		= '';
					}
				}else{
					if(isset($this->{$page}[$args['id']][$args['desc']])){
						$value		= esc_attr($this->{$page}[$args['id']][$args['desc']]);
					}else{
						$value		= '';
					}
				}
			}else{
				$value		= '';
			}
		
			// show settings
			$descriptions['payments_display']				= __('Check the boxes which payment types should be announced on payment form', 'paymill');
			$descriptions['products_desc']					= __('Detailed description of the product', 'paymill');
			$descriptions['products_price']					= __('Gross Price of the product, e.g. 40 or 6.99', 'paymill');
			$descriptions['products_offer']					= __('If you have created a subscription in your <a href="https://app.paymill.com/de-de#!/offers">Paymill Cockpit</a>, you can select it here. If selected, it will overwrite the following settings for this product. Important: For Performance purposes, subscription plans will be cached. Open this page to recache it.', 'paymill');
			$descriptions['products_vat']					= __('Value-Added-Tax Rate in % for the product, e.g. 19 or 7', 'paymill');
			$descriptions['products_delivery']				= __('Delivery Time of the product, e.g. 2 Days or 1 Week', 'paymill');
			$descriptions['products_quantityhide']			= __('Hide quantity select field, quantity will be set to 1', 'paymill');
			$descriptions['products_freeamount']			= __('Allow free amounts (donation feature)', 'paymill');

			if($args['desc'] == 'payments_display'){
				echo $descriptions[$args['desc']].'<br />';
			
				$payment_types = array(
					'amex',
					'cb',
					'dc',
					'discover',
					'elv',
					'jcb',
					'maestro',
					'mastercard',
					'sepa',
					'unionpay',
					'visa',
				);
				foreach($payment_types as $type){
					if(isset($this->{$page}[$args['desc']][$type])){
						$checked = esc_attr( $this->{$page}[$args['desc']][$type] );
					}else{
						$checked = false;
					}
					
					echo '
					<fieldset style="float:left;margin-right:20px;">
						<label for="'.$this->setting_keys[$page].'['.$args['desc'].']['.$type.']">
						<input
							'.(($checked == 1) ? 'checked="checked"' : '').'
							type="checkbox"
							name="'.$this->setting_keys[$page].'['.$args['desc'].']['.$type.']"
							id="'.$this->setting_keys[$page].'['.$args['desc'].']['.$type.']"
							value="1" />
							
							<img src="'.plugins_url('',__FILE__ ).'/img/logos/'.$type.'.png" style="vertical-align:middle;" alt="'.$type.'" />
						</label><br />
					</fieldset>
					';
				}
			}elseif($args['desc'] == 'products_offer'){ // products_offer
				$subscriptions = new paymill_subscriptions('pay_button');
				$offers = $subscriptions->offerGetList(true);
				if(count($offers) > 0){
					echo '<select class="regular-text code" name="'.$this->setting_keys[$page].$option.$id.'">';
					echo '<option value="">'.__('Optional: Select Subscription Plan', 'paymill').'</option>';
					foreach($offers as $offer){
						if($value == $offer['id']){
							$selected =' selected="selected"';
						}else{
							$selected ='';
						}
					echo '
						<option value="'.$offer['id'].'"'.$selected.'>'.$offer['name'].' / '.($offer['amount']/100).' '.$offer['currency'].' / '.__($offer['interval'], 'paymill').'</option>
					';
					}
					echo '</select>';
				}
			}elseif($args['desc'] == 'products_quantityhide' || $args['desc'] == 'no_default_css' || $args['desc'] == 'pci_dss_3'){ // products_quantityhide, no_default_css, pci_dss_3
				echo '
					<input
					type="checkbox"
					name="'.$this->setting_keys[$page].$option.'"
					value="1"
					class="regular-text code" '.($value ? 'checked="checked"' : '').' />
				';
			}elseif($args['desc'] == 'fields_show'){
				echo __('You may want to gather some additional information from your customers. Select them here:', 'paymill').'<br />';
			
				$fields_show = array(
				'shipping',
				'company_name',
				'forename',
				'surname',
				'street',
				'number',
				'state',
				'zip',
				'city',
				/*'email',*/
				'phone',
				);
				foreach($fields_show as $field){
					if(isset($this->{$page}[$args['desc']][$field])){
						$checked = esc_attr($this->{$page}[$args['desc']][$field]);
					}else{
						$checked = false;
					}
					echo '
					<fieldset style="float:left;margin-right:20px;">
						<label for="'.$this->setting_keys[$page].'['.$args['desc'].']['.$field.']">
						<input
							'.(($checked == 1) ? 'checked="checked"' : '').'
							type="checkbox"
							name="'.$this->setting_keys[$page].'['.$args['desc'].']['.$field.']"
							id="'.$this->setting_keys[$page].'['.$args['desc'].']['.$field.']"
							value="1" />
							'.__($field, 'paymill').'
						</label><br />
					</fieldset>
					';
				}
				echo '<div style="clear:both;"></div>';
			}else{
				echo '
					<input
					type="text"
					name="'.$this->setting_keys[$page].$option.'"
					value="'.$value.'"
					class="regular-text code" />
				';
			}
			
			if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_field_pay_button_option'); // benchmark
		}
		private function paymill_do_settings_sections($page){
			global $wp_settings_sections, $wp_settings_fields;
			
			if(!isset($wp_settings_sections[$page])){
				return;
			}
			foreach((array)$wp_settings_sections[$page] as $section){
				if($section['title']){
					echo "<h3>{$section['title']}</h3>\n";
				}
				if($section['callback']){
					call_user_func($section['callback'], $section);
				}
				if(!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])){
					continue;
				}
				echo '<div class="paymill_settings paymill_settings_page_'.$page.' paymill_settings_'.$section['id'].'">';
				$this->paymill_do_settings_fields($page, $section['id']);
				echo '</div>';
			}
		}
		private function paymill_do_settings_fields($page, $section){
			global $wp_settings_fields;
			
			$descriptions = array();
			
			$descriptions['number_decimal']					= __('Set a symbol used for decimal point. Default: .', 'paymill');
			$descriptions['number_thousands']				= __('Set a symbol used for thousands seperator. Default: ,', 'paymill');
			$descriptions['email_outgoing']					= __('Outgoing Emailaddress for customer order confirmation mail.', 'paymill');
			$descriptions['email_incoming']					= __('Incoming Emailaddress for Copy of customer order confirmation mail.', 'paymill');
			$descriptions['thankyou_url']					= __('Redirect URL for custom thank your page.', 'paymill');

			$descriptions['no_default_css']					= __('Advanced users want to fully customize the payment button. Disabling default CSS from Pay Button will make that much easier.', 'paymill');
			$descriptions['pci_dss_3']						= __('Please ask Paymill customer support before deactivating this feature.', 'paymill');
			$descriptions['currency']						= __('Currency, <a href="http://en.wikipedia.org/wiki/ISO_4217#Active_codes" target="_blank">ISO 4217</a> e.g. "EUR" or "GBP"', 'paymill');
			$descriptions['currency_format']				= __('Currency Format - use the following variables: %n = number, %s = symbol.', 'paymill');
			$descriptions['api_key_private']				= __('Insert your Paymill <strong>PRIVATE</strong> API key.', 'paymill');
			$descriptions['api_key_public']					= __('Insert your Paymill <strong>PUBLIC</strong> API key.', 'paymill');
			
			$descriptions['flat_shipping_country']			= __('Name of the available delivery country, e.g. England', 'paymill');
			$descriptions['flat_shipping_costs']			= __('Gross fee for the flat shipping costs., e.g. 7 or 4.90', 'paymill');
			$descriptions['flat_shipping_vat']				= __('Value-Added-Tax Rate in % for the flat shipping costs., e.g. 19 or 7', 'paymill');

			$descriptions['products_title']					= __('Name of the product', 'paymill');
			$descriptions['products_desc']					= __('Detailed description of the product', 'paymill');
			$descriptions['products_price']					= __('Gross Price of the product, e.g. 40 or 6.99', 'paymill');
			$descriptions['products_offer']					= __('If you have created a subscription in your Paymill Cockpit, can select it here. If selected, it will overwrite the following settings for this product. Important: For Performance purposes, subscription plans will be cached. Open this page to recache it.', 'paymill');
			$descriptions['products_vat']					= __('Value-Added-Tax Rate in % for the product, e.g. 19 or 7', 'paymill');
			$descriptions['products_delivery']				= __('Delivery Time of the product, e.g. 2 Days or 1 Week', 'paymill');
			$descriptions['products_quantityhide']			= __('Hide quantity select field, quantity will be set to 1', 'paymill');
			$descriptions['products_freeamount']			= __('Allow free amounts (donation feature)', 'paymill');
			
			if(!isset($wp_settings_fields[$page][$section])){
				return;
			}

			foreach((array)$wp_settings_fields[$page][$section] as $field){
				if(isset($field['args']['group'])){
					$group_titles[$field['args']['desc']]	= $field['title'];
					$group_fields[$field['args']['id']][$field['args']['desc']]		= $field;
				}else{
					echo '<div class="'.$field['id'].'">';
					echo '<div class="title">'.$field['title'].'</div>';
					call_user_func($field['callback'], $field['args']);
					if(isset($descriptions[$field['id']])){
						echo '<span class="desc">'.$descriptions[$field['id']].'</span>';
					}
					echo '</div>';
				}
			}
			if(isset($field['args']['group']) && isset($group_titles) && is_array($group_titles) && count($group_titles) > 0){
				echo '<table>';
				echo '<tr>';
				foreach($group_titles as $name => $desc){
					echo '<td class="'.$name.'" title="'.$descriptions[$name].'"><div class="'.$field['args']['group'].'_name">'.$desc.'</div></td>';
				}
				echo '</tr>';
				foreach($group_fields as $id => $group){
					echo '<tr class="entry_'.$id.'">';
					foreach($group as $group_field){
						echo '<td>';
						call_user_func($group_field['callback'], $group_field['args']);
						echo '</td>';
					}
					echo '</tr>';
				}
				echo '</table>';
			}
		}
		// Renders our tabs in the plugin options page, walks through the object's tabs array and prints them one by one. Provides the heading for the plugin_options_page method.
		private function plugin_options_tabs() {
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_plugin_options_tabs'); // benchmark
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->setting_keys['paymill_general_settings'];

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&amp;tab=' . $tab_key . '">' . $tab_caption . '</a>';	
		}
		echo '</h2>';
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_plugin_options_tabs'); // benchmark
	}
};

// Initialize the plugin
$GLOBALS['paymill_settings'] = new paymill_settings;

?>