<div class="paymill_products paybutton">
<?php

		if($GLOBALS['paymill_loader']->paymill_errors->status()){
			echo $GLOBALS['paymill_loader']->paymill_errors->getErrors();
		}

		foreach($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'] as $id => $product){
			if(isset($product['products_title']) && strlen($product['products_title']) > 0 && (!is_array($products_whitelist) || $products_whitelist[0] == '' || in_array($id,$products_whitelist))){
?>
		<div class="paymill_product paymill_product_<?php echo $id; ?>">
			<div class="paymill_title"><?php echo $product['products_title']; ?></div>
			<?php
			if(strlen($product['products_desc']) > 0){
				echo '<div class="paymill_desc">'.$product['products_desc'].'</div>';
			}
			if($product['products_offer'] != ''){
				if(isset($product['products_quantityhide']) && $product['products_quantityhide'] == '1'){
			?>
			<div class="paymill_quantity" style="display:none;">
				<select name="paymill_quantity[<?php echo $id; ?>]">
					<option value="1">1</option>
				</select>
			</div>
				<?php }else{ ?>
			<div class="paymill_quantity">
				<select name="paymill_quantity[<?php echo $id; ?>]">
					<option value="">0</option>
					<option value="1">1</option>
				</select>
			</div>
			<?php } ?>
			<input type="hidden" name="paymill_offer[<?php echo $id; ?>]" value="<?php echo $offers[$product['products_offer']]['id']; ?>" />
			<div class="paymill_price_calc_<?php echo $id; ?> paymill_hidden"><?php echo ($offers[$product['products_offer']]['amount']/100); ?></div>
			<div class="paymill_price"><?php echo number_format(($offers[$product['products_offer']]['amount']/100),2,$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'],$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands']); ?></div><div class="paymill_subscription"> /
			<?php
				$interval = explode(' ',$offers[$product['products_offer']]['interval']);
				if($interval[0] == 1){
					echo __($interval[1], 'paymill');
				}else{
					echo $interval[0].' '.__($interval[1].'S', 'paymill');
				}
			?></div>
			<?php if(strlen($product['products_vat']) > 0){ ?><div class="paymill_vat"><?php echo $product['products_vat'].__('% VAT included.', 'paymill'); ?></div><?php } ?>
			<?php if(strlen($product['products_delivery']) > 0){ ?><div class="paymill_delivery"><?php echo __('Delivery Time: ', 'paymill').$product['products_delivery']; ?></div><?php } ?>
<?php
			}else{
?>
			<?php if(isset($product['products_quantityhide']) && $product['products_quantityhide'] == '1'){ ?>
				<div class="paymill_quantity" style="display:none;">
					<select name="paymill_quantity[<?php echo $id; ?>]">
						<option value="1">1</option>
					</select>
				</div>
			<?php }else{ ?>
				<div class="paymill_quantity">
					<select name="paymill_quantity[<?php echo $id; ?>]">
					<?php for($i = 0; $i <= 10; $i++){ ?>
						<option value="<?php echo $i; ?>"<?php if(isset($_REQUEST['paymill_quantity_'.$id]) && intval($_REQUEST['paymill_quantity_'.$id]) == $i){ echo ' selected="selected"'; } ?>><?php echo $i; ?></option>
					<?php } ?>
					</select>
					<?php echo __('á ', 'paymill'); ?>
				</div>
			<?php } ?>
			<?php if(strlen($product['products_price']) > 0){ ?>
				<div class="paymill_price_calc_<?php echo $id; ?> paymill_hidden">
					<?php echo (isset($_POST['paymill']['product'][$id]['price']) ? intval($_POST['paymill']['product'][$id]['price']) : $product['products_price']); ?>
				</div>
				<div class="paymill_price">
				<?php echo
					number_format((isset($_POST['paymill']['product'][$id]['price']) ? intval($_POST['paymill']['product'][$id]['price']) : $product['products_price']),
						2,
						$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'],$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands']);
				?>
				</div>
			<?php } ?>
			<?php if(strlen($product['products_vat']) > 0){ ?><div class="paymill_vat"><?php echo $product['products_vat'].__('% VAT included.', 'paymill'); ?></div><?php } ?>
			<?php if(strlen($product['products_delivery']) > 0){ ?><div class="paymill_delivery"><?php echo __('Delivery Time: ', 'paymill').$product['products_delivery']; ?></div><?php } ?>
<?php
			}
?>
		</div>
	<?php
		}
	}
	if(empty($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping']) || count($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping']) == 0 || empty($show_fields['shipping']) || $show_fields['shipping'] != 1){
		$hide_shipping = ' style="display:none;"';
	}else{
		$hide_shipping = '';
	}
?>
		<div class="paymill_total_price"><?php echo __('Total Price:', 'paymill'); ?> <span id="paymill_total_number">0</span></div>
		<input class="paymill_amount" id="paymill_total" type="hidden" name="paymill_total" value="0" />
		<input type="hidden" name="paymill_pay_button_order" value="1" />
</div>
<div class="paymill_address">
	<div class="paymill_address_title"><?php echo __('Address', 'paymill'); ?></div>
	<?php if(isset($show_fields['forename']) && $show_fields['forename'] == 1){ ?>
	<div class="paymmill_forename">
		<input type="text" name="forename" value="" size="20" placeholder="<?php echo __('Forename', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['surname']) && $show_fields['surname'] == 1){ ?>
	<div class="paymmill_surname">
		<input type="text" name="surname" value="" size="20" placeholder="<?php echo __('Surname', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['company_name']) && $show_fields['company_name'] == 1){ ?>
	<div class="paymill_company_name">
		<input type="text" name="company_name" value="" size="20" placeholder="<?php echo __('Company Name', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['street']) && $show_fields['street'] == 1){ ?>
	<div class="paymmill_street">
		<input type="text" name="street" value="" size="20" placeholder="<?php echo __('Street', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['number']) && $show_fields['number'] == 1){ ?>
	<div class="paymmill_number">
		<input type="text" name="number" value="" size="20" placeholder="<?php echo __('Number', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['city']) && $show_fields['city'] == 1){ ?>
	<div class="paymmill_city">
		<input type="text" name="city" value="" size="20" placeholder="<?php echo __('City', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['state']) && $show_fields['state'] == 1){ ?>
	<div class="paymmill_state">
		<input type="text" name="state" value="" size="20" placeholder="<?php echo __('State/Province', 'paymill'); ?>" />
	</div>
	<?php } if(isset($show_fields['zip']) && $show_fields['zip'] == 1){ ?>
	<div class="paymmill_zip">
		<input type="text" name="zip" value="" size="20" placeholder="<?php echo __('ZIP', 'paymill'); ?>" />
	</div>
	<?php } ?>
		<select name="paymill_shipping" class="paymill_shipping" <?php echo $hide_shipping; ?>>
			<option value="" data-deliverycosts="0"><?php echo __('Choose Country', 'paymill'); ?></option>
<?php

		foreach($GLOBALS['paymill_settings']->paymill_pay_button_settings['flat_shipping'] as $id => $shipping){
			if(strlen($shipping['flat_shipping_country']) > 0){
				if(floatval($shipping['flat_shipping_costs']) > 0){
					 $shipping_costs = '(+'.number_format(floatval($shipping['flat_shipping_costs']),2,$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_decimal'],$GLOBALS['paymill_settings']->paymill_pay_button_settings['number_thousands']).')';
				}else{
					$shipping_costs = '';
				}
?>
			<option value="<?php echo $id; ?>" data-deliverycosts="<?php echo $shipping['flat_shipping_costs']; ?>" data-deliveryvat="<?php echo $shipping['flat_shipping_vat']; ?>"><?php echo $shipping['flat_shipping_country'].$shipping_costs; ?></option>
<?php
			}
		}
?>
		</select>
	<?php if(isset($show_fields['phone']) && $show_fields['phone'] == 1){ ?>
	<div class="paymmill_phone">
		<input type="text" name="phone" value="" size="20" placeholder="<?php echo __('Phone', 'paymill'); ?>" />
	</div>
	<?php } ?>
	<div class="paymmill_email">
		<input type="text" name="email" value="" size="20" placeholder="<?php echo __('Email', 'paymill'); ?>" />
	</div>
</div>