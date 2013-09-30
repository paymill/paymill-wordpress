<?php
/*
 * Plugin Name: Settings API Tabs Demo
 * Plugin URI: http://theme.fm/?p=
 * Description: This is a demo showing off usage of tabs with the WordPress Settings API
 * Version: 1.0
 * Author: kovshenin
 * Author URI: http://theme.fm
 * License: GPL2
 */

/*
 * The main plugin class, holds everything our plugin does,
 * initialized right after declaration
 */
class paymill_settings{
	
	/*
	 * For easier overriding we declared the keys
	 * here as well as our tabs array which is populated
	 * when registering settings
	 */
	public $setting_keys = array();
	private $plugin_options_key = 'paymill_options';
	private $plugin_settings_tabs = array();
	
	/*
	 * Fired during plugins_loaded (very very early),
	 * so don't miss-use this, only actions and filters,
	 * current ones speak for themselves.
	 */
	function __construct() {
		$this->setting_keys['paymill_general_settings'] = 'paymill_general_settings';
		$this->setting_keys['paymill_pay_button_settings'] = 'paymill_pay_button_settings';
		
		foreach($this->setting_keys as $key){
			$this->$key = (array) get_option( $key );
		}
		
		// Merge with defaults
		$this->paymill_general_settings = array_merge( array(
			'api_endpoint' => 'https://api.paymill.com/v2/',
			'currency' => 'EUR'
		), $this->paymill_general_settings );
		
		$this->paymill_pay_button_settings = array_merge( array(
			'number_decimal' => '.',
			'number_thousands' => ',',
		), $this->paymill_pay_button_settings );
		
		if(isset($this->paymill_general_settings['api_key_private']) && isset($this->paymill_general_settings['api_key_public']) && $this->paymill_general_settings['api_key_private'] != '' && $this->paymill_general_settings['api_key_public'] != '' && $this->paymill_general_settings['api_endpoint'] != ''){
			define('PAYMILL_ACTIVE',true);
		}else{
			define('PAYMILL_ACTIVE',false);
		}
	
		add_action( 'admin_init', array( &$this, 'register_general_settings' ) );
		if(defined('PAYMILL_ACTIVE') && PAYMILL_ACTIVE === true){
			add_action( 'admin_init', array( &$this, 'register_pay_button_settings' ) );
		}
		add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );
	}
	
	/*
	 * Registers the general settings via the Settings API,
	 * appends the setting to the tabs array of the object.
	 */
	function register_general_settings() {
		$this->plugin_settings_tabs[$this->setting_keys['paymill_general_settings']] = 'General';
		
		register_setting( $this->setting_keys['paymill_general_settings'], $this->setting_keys['paymill_general_settings'] );
		add_settings_section( 'section_general', __('General Plugin Settings', 'paymill'), array( &$this, 'section_general_desc' ), $this->setting_keys['paymill_general_settings'] );

		add_settings_field( 'api_key_private', __('Paymill PRIVATE API key', 'paymill'), array( &$this, 'field_general_option' ), $this->setting_keys['paymill_general_settings'], 'section_general',array('desc' => 'api_key_private', 'option' => 'api_key_private'));
		add_settings_field( 'api_key_public', __('Paymill PUBLIC API key', 'paymill'), array( &$this, 'field_general_option' ), $this->setting_keys['paymill_general_settings'], 'section_general',array('desc' => 'api_key_public', 'option' => 'api_key_public'));
		add_settings_field( 'api_endpoint', __('Paymill API endpoint URL', 'paymill'), array( &$this, 'field_general_option' ), $this->setting_keys['paymill_general_settings'], 'section_general',array('desc' => 'api_endpoint', 'option' => 'api_endpoint'));
		add_settings_field( 'currency',  __('Currency', 'paymill'), array( &$this, 'field_general_option' ), $this->setting_keys['paymill_general_settings'], 'section_general',array('desc' => 'currency', 'option' => 'currency'));
		add_settings_field( 'payments_display',  __('Display Payment Types', 'paymill'), array( &$this, 'field_general_option' ), $this->setting_keys['paymill_general_settings'], 'section_general',array('desc' => 'payments_display', 'option' => 'payments_display'));
	}
	
	/*
	 * Registers the pay_button settings and appends the
	 * key to the plugin settings tabs array.
	 */
	function register_pay_button_settings() {
		$this->plugin_settings_tabs[$this->setting_keys['paymill_pay_button_settings']] = 'Pay Button';
		register_setting( $this->setting_keys['paymill_pay_button_settings'], $this->setting_keys['paymill_pay_button_settings'] );

		// common
		add_settings_section( 'section_pay_button', false, array( &$this, 'section_pay_button_desc' ), $this->setting_keys['paymill_pay_button_settings'] );
		add_settings_field( 'number_decimal',  __('Number Format: Decimal Point', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button',array('desc' => 'number_decimal', 'option' => 'number_decimal'));
		add_settings_field( 'number_thousands',  __('Number Format: Thousands Seperator', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button',array('desc' => 'number_thousands', 'option' => 'number_thousands'));
		add_settings_field( 'email_outgoing',  __('Outgoing Email', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button',array('desc' => 'email_outgoing', 'option' => 'email_outgoing'));
		add_settings_field( 'email_incoming',  __('Incoming Email', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button',array('desc' => 'email_incoming', 'option' => 'email_incoming'));

		// products
		add_settings_section( 'section_pay_button_products', false, array( &$this, 'section_pay_button_products_desc' ), $this->setting_keys['paymill_pay_button_settings'] );
		//if (isset($this->paymill_pay_button_settings['products'])) {
			if(strlen($this->paymill_pay_button_settings['products'][count($this->paymill_pay_button_settings['products'])]['title']) > 0){
				$products = count($this->paymill_pay_button_settings['products'])+5;
			}elseif(!is_array($this->paymill_pay_button_settings['products']) || count($this->paymill_pay_button_settings['products']) < 5){
				$products = 5;
			}else{
				$products = count($this->paymill_pay_button_settings['products']);
			}
			
			for($i = 1; $i <= $products; $i++){
				add_settings_field( 'products_title_'.$i, __('Product', 'paymill').' #'.$i, array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_title', 'option' => 'products', 'id' => $i, 'field' => 'title'));
				add_settings_field( 'products_desc_'.$i, __('Description', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_desc', 'option' => 'products', 'id' => $i, 'field' => 'desc'));
				add_settings_field( 'products_vat_'.$i, __('VAT', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_vat', 'option' => 'products', 'id' => $i, 'field' => 'vat'));

				add_settings_field( 'products_offer_'.$i, __('Subscription Offer', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_offer', 'option' => 'products', 'id' => $i, 'field' => 'offer'));
				
				add_settings_field( 'products_price_'.$i, __('Price', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_price', 'option' => 'products', 'id' => $i, 'field' => 'price'));
				add_settings_field( 'products_quantityhide_'.$i, __('Hide Quantity', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_quantityhide', 'option' => 'products', 'id' => $i, 'field' => 'quantityhide'));
				add_settings_field( 'products_delivery_'.$i, __('Delivery Time', 'paymill'), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_products',array('desc' => 'products_delivery', 'option' => 'products', 'id' => $i, 'field' => 'delivery'));
			}
		//}

		// shipping
		add_settings_section( 'section_pay_button_shipping', false, array( &$this, 'section_pay_button_shipping_desc' ), $this->setting_keys['paymill_pay_button_settings'] );
		//if(isset($this->paymill_pay_button_settings['flat_shipping'])) {
			if(strlen($this->paymill_pay_button_settings['flat_shipping'][count($this->paymill_pay_button_settings['flat_shipping'])]['country']) > 0){
				$countries = count($this->paymill_pay_button_settings['flat_shipping'])+5;
			}elseif(count($this->paymill_pay_button_settings['flat_shipping']) < 5){
				$countries = 5;
			}else{
				$countries = count($this->paymill_pay_button_settings['flat_shipping']);
			}
			for($i = 1; $i <= $countries; $i++){
				add_settings_field( 'flat_shipping_country_'.$i, __('Shipping Country', 'paymill').' #'.$i, array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_shipping',array('desc' => 'flat_shipping_country', 'option' => 'flat_shipping', 'id' => $i, 'field' => 'country'));
				add_settings_field( 'flat_shipping_costs_'.$i, __('Shipping Costs', 'paymill').' '.esc_attr( $this->paymill_pay_button_settings['flat_shipping'][$i]['country'] ), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_shipping',array('desc' => 'flat_shipping_costs', 'option' => 'flat_shipping', 'id' => $i, 'field' => 'costs'));
				add_settings_field( 'flat_shipping_vat_'.$i, __('Shipping VAT', 'paymill').' '.esc_attr( $this->paymill_pay_button_settings['flat_shipping'][$i]['country'] ), array( &$this, 'field_pay_button_option' ), $this->setting_keys['paymill_pay_button_settings'], 'section_pay_button_shipping',array('desc' => 'flat_shipping_vat', 'option' => 'flat_shipping', 'id' => $i, 'field' => 'vat'));
			}
		//}
	}
	
	/*
	 * The following methods provide descriptions
	 * for their respective sections, used as callbacks
	 * with add_settings_section
	 */
	function section_general_desc() { echo __('Please insert your API settings here.', 'paymill'); }
	function section_pay_button_desc() { echo '<h3>'.__('Common Settings', 'paymill').'</h3>'.'<p>'.__('The Paymill Pay Buton is a simple, independent payment solution.', 'paymill').'</p>'.__('Configure common settings', 'paymill').'<br /><a href="#" id="common_toggle">'.__('Toggle View', 'paymill').'</a><div id="common_content" style="display:none;">'; }
	function section_pay_button_products_desc() { echo '</div><h3>'.__('Products', 'paymill').'</h3>'.__('Configure products for the Pay Button. This list has a dynamic length and extends for 5 extra slots when last slot is filled and saved.', 'paymill').'<br /><a href="#" id="products_toggle">'.__('Toggle View', 'paymill').'</a><div id="products_content" style="display:none;">'; }
	function section_pay_button_shipping_desc() { echo '</div><h3>'.__('Shipping', 'paymill').'</h3>'.__('Set delivery countries and shipping costs. This list has a dynamic length and extends for 5 extra slots when last slot is filled and saved.', 'paymill').'<br /><a href="#" id="shipping_toggle">'.__('Toggle View', 'paymill').'</a><div id="shipping_content" style="display:none;">'; }
	
	/*
	 * General Option field callback, renders a
	 * text input, note the name and value.
	 */
	function field_general_option($args) {
	
		$descriptions = array();
		$descriptions['currency']			= __('Currency, <a href="http://en.wikipedia.org/wiki/ISO_4217#Active_codes" target="_blank">ISO 4217</a> e.g. "EUR" or "GBP"', 'paymill');
		$descriptions['payments_display']	= __('Check the boxes which payment types should be announced on payment form', 'paymill');
		$descriptions['api_key_private']	= __('Insert your Paymill <strong>PRIVATE</strong> API key.', 'paymill');
		$descriptions['api_key_public']		= __('Insert your Paymill <strong>PUBLIC</strong> API key.', 'paymill');
		$descriptions['api_endpoint']		= __('Insert your Paymill endpoint URL.', 'paymill');
	
		if($args['desc'] == 'payments_display'){
			echo $descriptions[$args['desc']].'<br />';
		
			$payment_types = array(
			'amex',		
			'dc',		
			'discover',		
			'elv',		
			'jcb',		
			'maestro',		
			'mastercard',		
			'unionpay',		
			'visa',		
			);
			foreach($payment_types as $type){
				$checked = esc_attr( $this->paymill_general_settings[$args['option']][$type] );
				
				echo '
				<fieldset style="float:left;margin-right:20px;">
					<label for="'.$this->setting_keys['paymill_general_settings'].'['.$args['option'].']['.$type.']">
					<input
						'.(($checked == 1) ? 'checked="checked"' : '').'
						type="checkbox"
						name="'.$this->setting_keys['paymill_general_settings'].'['.$args['option'].']['.$type.']"
						id="'.$this->setting_keys['paymill_general_settings'].'['.$args['option'].']['.$type.']"
						value="1" />
						
						<img src="'.plugins_url('',__FILE__ ).'/img/logos/'.$type.'.png" style="vertical-align:middle;" alt="'.$type.'" />
					</label><br />
				</fieldset>
				';
			}
		}else{
			echo '
				<input
				type="text"
				name="'.$this->setting_keys['paymill_general_settings'].'['.$args['option'].']"
				value="'.esc_attr( $this->paymill_general_settings[$args['option']] ).'"
				class="regular-text code" />
				<span class="setting-description">'.$descriptions[$args['desc']].'</span>
			';
		}
	}
	
	/*
	 * pay_button Option field callback, same as above.
	 */
	function field_pay_button_option($args) {
		$descriptions = array();
		
		$descriptions['number_decimal']					= __('Set a symbol used for decimal point. Default: .', 'paymill');
		$descriptions['number_thousands']				= __('Set a symbol used for thousands seperator. Default: ,', 'paymill');
		$descriptions['email_outgoing']					= __('Outgoing Emailaddress for customer order confirmation mail.', 'paymill');
		$descriptions['email_incoming']					= __('Incoming Emailaddress for Copy of customer order confirmation mail.');

		$descriptions['flat_shipping_country']			= __('Name of the available delivery country, e.g. "England"');
		$descriptions['flat_shipping_costs']			= __('Gross fee for the flat shipping costs., e.g. "7" or "4.90"', 'paymill');
		$descriptions['flat_shipping_vat']				= __('Value-Added-Tax Rate in % for the flat shipping costs., e.g. "19" or "7"', 'paymill');

		$descriptions['products_title']					= __('Name of the product', 'paymill');
		$descriptions['products_desc']					= __('Detailed description of the product', 'paymill');
		$descriptions['products_price']					= __('Gross Price of the product, e.g. "40" or "6.99"', 'paymill');
		$descriptions['products_offer']					= __('If you have created a subscription in your Paymill Cockpit, can select it here. If selected, it will overwrite the following settings for this product. <strong>Important: For Performance purposes, subscription plans will be cached. Open this page to recache it.</strong>', 'paymill');
		$descriptions['products_vat']					= __('Value-Added-Tax Rate in % for the product, e.g. "19" or "7"', 'paymill');
		$descriptions['products_delivery']				= __('Delivery Time of the product, e.g. "2 Days" or "1 Week"', 'paymill');
		$descriptions['products_quantityhide']			= __('Hide quantity select field, quantity will be set to 1', 'paymill');

		
		if(strlen($args['option']) > 0){
			$option = '['.$args['option'].']';
			$value = esc_attr($this->paymill_pay_button_settings[$args['option']]);
		}else{
			$option = '';
		}
		
		if(strlen($args['id']) > 0){
			$id = '['.$args['id'].']';
			$value = esc_attr($this->paymill_pay_button_settings[$args['option']][$args['id']]);
		}else{
			$id = '';
		}
		
		if(strlen($args['field']) > 0){
			$field = '['.$args['field'].']';
			$value = esc_attr($this->paymill_pay_button_settings[$args['option']][$args['id']][$args['field']]);
		}else{
			$field = '';
		}
	
		
		if($args['desc'] == 'products_desc'){
			echo '
				<textarea
				name="'.$this->setting_keys['paymill_pay_button_settings'].$option.$id.$field.'"
				class="regular-text code" style="width:300px;">'.$value.'</textarea>
				<span class="setting-description">'.$descriptions[$args['desc']].'</span>
			';
		}elseif($args['desc'] == 'products_offer'){
			$subscriptions = new paymill_subscriptions('pay_button');
			$offers = $subscriptions->offerGetList(true);
			echo '<select class="regular-text code" name="'.$this->setting_keys['paymill_pay_button_settings'].$option.$id.$field.'">';
			echo '<option value="">'.__('Optional: Select Subscription Plan', 'paymill').'</option>';
			foreach($offers as $offer){
				if($value == $offer['id']){
					$selected =' selected="selected"';
				}else{
					$selected ='';
				}
			echo '
				<option value="'.$offer['id'].'"'.$selected.'>'.$offer['name'].' / '.($offer['amount']/100).' '.$offer['currency'].' / '.$offer['interval'].'</option>
			';
			}
			echo '</select><span class="setting-description">'.$descriptions[$args['desc']].'</span>';
		}elseif($args['desc'] == 'products_quantityhide'){
			echo '
				<input
				type="checkbox"
				name="'.$this->setting_keys['paymill_pay_button_settings'].$option.$id.$field.'"
				value="1"
				class="regular-text code" '.($value ? 'checked="checked"' : '').' />
				<span class="setting-description">'.$descriptions[$args['desc']].'</span>
			';
		}else{
			echo '
				<input
				type="text"
				name="'.$this->setting_keys['paymill_pay_button_settings'].$option.$id.$field.'"
				value="'.$value.'"
				class="regular-text code" />
				<span class="setting-description">'.$descriptions[$args['desc']].'</span>
			';
		}
		
		if($args['desc'] == 'flat_shipping_vat' || $args['desc'] == 'products_delivery'){
			echo '</td><tr><td colspan="3" style="background-image:url('.plugins_url('',__FILE__ ).'/img/line.png);background-repeat:no-repeat;height:3px;line-height:3px;padding:0px;margin:0px;">';
		}
	}
	
	/*
	 * Called during admin_menu, adds an options
	 * page under Settings called My Settings, rendered
	 * using the plugin_options_page method.
	 */
	function add_admin_menus() {
		add_menu_page('Paymill', 'Paymill', 8, $this->plugin_options_key, array( &$this, 'plugin_options_page' ),  plugins_url('',__FILE__ ).'/img/icon.png');
	}
	
	/*
	 * Plugin Options page rendering goes here, checks
	 * for active tab and replaces key with the related
	 * settings key. Uses the plugin_options_tabs method
	 * to render the tabs.
	 */
	function plugin_options_page() {
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->setting_keys['paymill_general_settings'];
?>
		<a href="https://www.paymill.com/"><img src="<?php echo plugins_url('',__FILE__ ).'/img/logo.png'; ?>" width="220" height="78" alt="Paymill" /></a>
		<div class="wrap">
			<?php $this->plugin_options_tabs(); ?>
			<form method="post" action="options.php">
				<?php wp_nonce_field( 'update-options' ); ?>
				<?php settings_fields( $tab ); ?>
				<?php do_settings_sections( $tab ); ?>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	
	/*
	 * Renders our tabs in the plugin options page,
	 * walks through the object's tabs array and prints
	 * them one by one. Provides the heading for the
	 * plugin_options_page method.
	 */
	function plugin_options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->setting_keys['paymill_general_settings'];

		screen_icon();
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
			$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';	
		}
		echo '</h2>';
	}
};

// Initialize the plugin
//add_action( 'plugins_loaded', create_function( '', '$this = new paymill_settings;' ) );
$GLOBALS['paymill_settings'] = new paymill_settings;

?>