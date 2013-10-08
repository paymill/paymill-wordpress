<div id="paymill_payment_form">
	<script type="text/javascript">
		var PAYMILL_PUBLIC_KEY = '<?php echo $GLOBALS['paymill_settings']->paymill_general_settings['api_key_public']; ?>';
	</script>
	<div class="paymill_payment_errors"></div>
	
	<div class="form-row">
		<p><?php
			foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
				if($type==1){
					echo '<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
				}
			}
		?></p>
	</div>
	
	<?php

		if(
			((esc_attr($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']['elv']) == 1)
			&& (count($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) >= 2))
		){
	?>
		<div id="form-switch-credit" class="paymill_form-switch paymill_form-switch_active"><?php echo __('Credit Card', 'paymill'); ?></div>
		<div id="form-switch-elv" class="paymill_form-switch"><?php echo __('Debit Payment', 'paymill'); ?></div>
	<?php
		}
	?>
	<div class="form-row">
		<label><?php echo __('Name', 'paymill'); ?></label>
		<input class="paymill_holdername" id="holdername" type="text" size="20" value="" autocomplete="off" />
	</div>
	<div id="form-credit">	
		<div class="form-row">
			<label><?php echo __('Credit Card Number', 'paymill'); ?></label>
			<input class="paymill_card-number" id="card-number" type="text" size="20" value="" autocomplete="off" />
		</div>
		<div class="form-row">
			<label><?php echo __('Expire Date (MM/YYYY)', 'paymill'); ?></label>
			<input class="paymill_card-expiry-month" id="card-expiry-month" type="text" size="2" value="" autocomplete="off" />
			<p> / </p>
			<input class="paymill_card-expiry-year" id="card-expiry-year" type="text" size="4" value="" autocomplete="off" />
			<?php echo __('CVC', 'paymill'); ?>: <input class="paymill_card-cvc" id="card-cvc" type="text" size="4" value="" autocomplete="off" />
		</div>
	</div>
	<div id="form-elv">	
		<div class="form-row">
			<label translate="iframe"><?php echo __('Account #', 'paymill'); ?></label>
			<input id="transaction-form-account" class="paymill_elv_number" type="text" value="" autocomplete="off"  maxlength="16">
		</div>
		<div class="form-row">
			<label translate="iframe"><?php echo __('Bank code', 'paymill'); ?></label>
			<input id="transaction-form-code" class="paymill_elv_bank" type="text" value="" autocomplete="off"  maxlength="16">
		</div>
	</div>
		
		<input class="paymill_amount" type="hidden" size="5" value="<?php if(isset($cart_total)){ echo $cart_total; } ?>"/>
		<input class="paymill_currency" type="hidden" size="3" value="<?php echo $currency; ?>"/>
</div>