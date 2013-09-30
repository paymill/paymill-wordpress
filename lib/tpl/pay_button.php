<div class="paymill_products paybutton">
<?php
	foreach($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'] as $id => $product){
		if(strlen($product['title']) > 0 && (!is_array($products_whitelist) || $products_whitelist[0] == '' || in_array($id,$products_whitelist))){
?>
		<div class="paymill_product paymill_product_<?php echo $id; ?>">
			<div class="paymill_title"><?php echo $product['title']; ?></div>
			<?php if(strlen($product['desc']) > 0){ ?><div class="paymill_desc"><?php echo $product['desc']; ?></div><?php } ?>

			
<?php
			if($product['offer'] != ''){
?>
			<div class="paymill_quantity">
				<select name="paymill_quantity[<?php echo $id; ?>]">
					<option value="">0</option>
					<option value="1">1</option>
				</select>
			</div>
			<input type="hidden" name="paymill_offer[<?php echo $id; ?>]" value="<?php echo $offers[$product['offer']]['id']; ?>" />
			<div class="paymill_price_calc_<?php echo $id; ?> paymill_hidden"><?php echo ($offers[$product['offer']]['amount']/100); ?></div><div class="paymill_price"><?php echo number_format(($offers[$product['offer']]['amount']/100),2,$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'],$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands']); ?></div><div class="paymmill_subscription"> / <?php echo $offers[$product['offer']]['interval']; ?></div>
			<?php if(strlen($product['vat']) > 0){ ?><div class="paymill_vat"><?php echo $product['vat'].__('% VAT included.', 'paymill'); ?></div><?php } ?>
			<?php if(strlen($product['delivery']) > 0){ ?><div class="paymill_delivery"><?php echo __('Delivery Time: ', 'paymill').$product['delivery']; ?></div><?php } ?>
<?php
			}else{
?>
			<div class="paymill_quantity">
			<?php if($product['quantityhide'] == '1'){ ?>
				<select name="paymill_quantity[<?php echo $id; ?>]" style="display:none;">
					<option value="1">1</option>
				</select>
			<?php }else{ ?>
				<select name="paymill_quantity[<?php echo $id; ?>]">
				<?php for($i = 0; $i <= 10; $i++){ ?>
					<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
				<?php } ?>
				</select>
			<?php } ?>
			</div>
			<?php if(strlen($product['price']) > 0){ ?><div class="paymill_price_calc_<?php echo $id; ?> paymill_hidden"><?php echo $product['price']; ?></div><div class="paymill_price"><?php echo number_format($product['price'],2,$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'],$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands']); ?></div><?php } ?>
			<?php if(strlen($product['vat']) > 0){ ?><div class="paymill_vat"><?php echo $product['vat'].__('% VAT included.', 'paymill'); ?></div><?php } ?>
			<?php if(strlen($product['delivery']) > 0){ ?><div class="paymill_delivery"><?php echo __('Delivery Time: ', 'paymill').$product['delivery']; ?></div><?php } ?>
<?php
			}
?>
		</div>
	<?php
		}
	}
?>
		<select name="paymill_shipping" class="paymill_shipping">
			<option value="" data-deliverycosts="0"><?php echo __('Choose Country', 'paymill'); ?></option>
<?php
		foreach($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping'] as $id => $shipping){
			if(strlen($shipping['country']) > 0){
?>
			<option value="<?php echo $id; ?>" data-deliverycosts="<?php echo $shipping['costs']; ?>" data-deliveryvat="<?php echo $shipping['vat']; ?>"><?php echo $shipping['country']; ?></option>
<?php
			}
		}
?>
		</select>
		<div class="paymill_total_price"><?php echo __('Total Price:', 'paymill'); ?> <span id="paymill_total_number">0</span></div>
		<input type="checkbox" id="payment_method_paymill" checked="checked" />
		<input class="paymill_amount" id="paymill_total" type="hidden" name="paymill_total" value="0" />
		<input type="hidden" name="paymill_pay_button_order" value="1" />
</div>
<?php
	
?>
<div class="paymill_address">
	<div class="paymill_address_title"><?php echo __('Address', 'paymill'); ?></div>
	<div class="form-row">
		<label><?php echo __('Company Name', 'paymill'); ?></label>
		<input type="text" name="company_name" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('Forename', 'paymill'); ?></label>
		<input type="text" name="forename" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('Surname', 'paymill'); ?></label>
		<input type="text" name="surname" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('Street', 'paymill'); ?></label>
		<input type="text" name="street" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('Number', 'paymill'); ?></label>
		<input type="text" name="number" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('ZIP', 'paymill'); ?></label>
		<input type="text" name="zip" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('City', 'paymill'); ?></label>
		<input type="text" name="city" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('Email', 'paymill'); ?></label>
		<input type="text" name="email" value="" size="20" />
	</div>
	<div class="form-row">
		<label><?php echo __('Phone', 'paymill'); ?></label>
		<input type="text" name="phone" value="" size="20" />
	</div>
</div>
<?php

?>