<!--authorizenet settings box-->
<div id="module_settings_box_<?php echo $data['module']->code?>" class="module_settings_box">
	<form name="frmmodbox_<?php echo $data['module']->code?>" id="frmmodbox_<?php echo $data['module']->code?>" action="admin-ajax.php?action=mgm_admin_ajax_action&page=mgm/admin/payments&method=module_settings&module=<?php echo $data['module']->code?>">
		<div class="name"><?php echo $data['module']->name?></div>
		<div class="logo"><img src="<?php echo $data['module']->logo?>" id="logo_image_<?php echo $data['module']->code?>" alt="<?php echo sprintf(__('%s Logo', 'mgm'),$data['module']->name) ?>" border="0"></div>		
		<div id="box_logo_elements_<?php echo $data['module']->code?>"> 
			<a href="javascript:mgm_toggle('change_logo_<?php echo $data['module']->code?>')"><?php _e('Change Logo','mgm');?></a><br />
			<div id="change_logo_<?php echo $data['module']->code?>" class="displaynone">
				<input type="file" name="logo_<?php echo $data['module']->code?>" id="box_logo_<?php echo $data['module']->code?>" ><!--keep id name box_ to track by uploader-->
			</div>
		</div>
		<div class="description"><?php echo mgm_stripslashes_deep($data['module']->description)?></div>		
		<div class="links">
			<input type="checkbox" name="payment[enable]" value="Y" <?php echo ($data['module']->is_enabled()) ? 'checked' : ''?> /> <span id="status_label_<?php echo $data['module']->code?>" class="<?php echo ($data['module']->is_enabled()) ? 's-enabled' : 's-disabled'?>"><?php echo ($data['module']->is_enabled()) ? __('Enabled','mgm') : __('Disabled','mgm')?></span>
		</div>		
		<input type="hidden" name="update" value="true" />
		<input type="hidden" name="setting_form" value="box" />
		<input type="hidden" name="act" value="" />
	</form>	
</div>