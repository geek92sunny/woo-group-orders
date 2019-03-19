jQuery(function(){
	jQuery(".ets-wgo-group-inputs .user-group-radio").change(function(){
		if ( jQuery(".ets-wgo-group-inputs .user-group-radio:checked").length > 0 ) {
			jQuery("#ets_group_admin").prop('disabled', false);
		} else {
			jQuery("#ets_group_admin").prop('disabled', true);
		}
	});

	jQuery(".ets-wgo-group-inputs .user-group-radio").change();

	jQuery(".ets-wgo-group-inputs .clr-user-group").click(function(){
		jQuery("#ets_group_admin").prop('checked', false).prop('disabled', true);
		jQuery(".ets-wgo-group-inputs .user-group-radio").prop("checked", false).change();
	});
});