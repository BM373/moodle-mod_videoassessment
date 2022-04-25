M.mod_videoassessment = {};

M.mod_videoassessment.init_training_change = function(Y) {
    var trainingnode = Y.one('#id_training');
    var video = Y.one('#fitem_id_trainingvideo');
    var point = Y.one('#fitem_id_accepteddifference');
    var desc = Y.one('#fitem_id_trainingdesc');
    
    if (trainingnode) {
        var originalvalue = trainingnode.get('value');
        if (originalvalue != 1) {
        	video.hide();
    		point.hide();
    		desc.hide();
        }
        
        trainingnode.on('change', function() {
        	if (trainingnode.get('value') == 1) {
        		video.show();
        		point.show();
        		desc.show();
        	} else {
        		video.hide();
        		point.hide();
        		desc.hide();
        	}
        });
    }
};
M.mod_videoassessment.init_fairness_bonus_change = function(Y) {
	var fairnessbonus = Y.one('#id_fairnessbonus');
	var bonuspercentage = Y.one('#fitem_id_bonuspercentage');
	var bonusscoregroup1 = Y.one('#fgroup_id_bonusscoregroup1');
	var bonusscoregroup2 = Y.one('#fgroup_id_bonusscoregroup2');
	var bonusscoregroup3 = Y.one('#fgroup_id_bonusscoregroup3');
	var bonusscoregroup4 = Y.one('#fgroup_id_bonusscoregroup4');
	var bonusscoregroup5 = Y.one('#fgroup_id_bonusscoregroup5');
	var bonusscoregroup6 = Y.one('#fgroup_id_bonusscoregroup6');

	if (fairnessbonus) {
		var fairnessbonusvalue = fairnessbonus.get('value');
		if (fairnessbonusvalue != 1) {
			bonuspercentage.hide();
			bonusscoregroup1.hide();
			bonusscoregroup2.hide();
			bonusscoregroup3.hide();
			bonusscoregroup4.hide();
			bonusscoregroup5.hide();
			bonusscoregroup6.hide();
		}

		fairnessbonus.on('change', function() {
			if (fairnessbonus.get('value') == 1) {
				bonuspercentage.show();
				bonusscoregroup1.show();
				bonusscoregroup2.show();
				bonusscoregroup3.show();
				bonusscoregroup4.show();
				bonusscoregroup5.show();
				bonusscoregroup6.show();
			} else {
				bonuspercentage.hide();
				bonusscoregroup1.hide();
				bonusscoregroup2.hide();
				bonusscoregroup3.hide();
				bonusscoregroup4.hide();
				bonusscoregroup5.hide();
				bonusscoregroup6.hide();
			}
		});
	}

	var selffairnessbonus = Y.one('#id_selffairnessbonus');
	var selfbonuspercentage = Y.one('#fitem_id_selfbonuspercentage');
	var selfbonusscoregroup1 = Y.one('#fgroup_id_selfbonusscoregroup1');
	var selfbonusscoregroup2 = Y.one('#fgroup_id_selfbonusscoregroup2');
	var selfbonusscoregroup3 = Y.one('#fgroup_id_selfbonusscoregroup3');
	var selfbonusscoregroup4 = Y.one('#fgroup_id_selfbonusscoregroup4');
	var selfbonusscoregroup5 = Y.one('#fgroup_id_selfbonusscoregroup5');
	var selfbonusscoregroup6 = Y.one('#fgroup_id_selfbonusscoregroup6');

	if (selffairnessbonus) {
		var selffairnessbonusvalue = selffairnessbonus.get('value');
		if (selffairnessbonusvalue != 1) {
			selfbonuspercentage.hide();
			selfbonusscoregroup1.hide();
			selfbonusscoregroup2.hide();
			selfbonusscoregroup3.hide();
			selfbonusscoregroup4.hide();
			selfbonusscoregroup5.hide();
			selfbonusscoregroup6.hide();
		}

		selffairnessbonus.on('change', function() {
			if (selffairnessbonus.get('value') == 1) {
				selfbonuspercentage.show();
				selfbonusscoregroup1.show();
				selfbonusscoregroup2.show();
				selfbonusscoregroup3.show();
				selfbonusscoregroup4.show();
				selfbonusscoregroup5.show();
				selfbonusscoregroup6.show();
			} else {
				selfbonuspercentage.hide();
				selfbonusscoregroup1.hide();
				selfbonusscoregroup2.hide();
				selfbonusscoregroup3.hide();
				selfbonusscoregroup4.hide();
				selfbonusscoregroup5.hide();
				selfbonusscoregroup6.hide();
			}
		});
	}
};
M.mod_videoassessment.init_upload_type_change = function(Y) {
    var uploadradio = Y.one('#id_upload_0');
    var youtuberadio = Y.one('#id_upload_1');
	var precent = Y.one('#fitem_id_precent')?Y.one('#fitem_id_precent'):Y.one('#fitem_id_mobilevideo');
    var video = Y.one('#fitem_id_video')?Y.one('#fitem_id_video'):Y.one('#fitem_id_mobilevideo');
    var url = Y.one('#id_url')?Y.one('#id_url'):Y.one('#id_mobileurl');
    
    if($('#id_upload_0').length){
	    $('.col-md-3').each(function(){
	    	if($(this).children().length == 0){
	    		$(this).remove();
	    	}
	    });
    }
	if($('#fgroup_id_radios').length){
		var uploadradioEle = $('#id_upload_1').parent();
		var fgroup_id_radios_first_child = $($('#fgroup_id_radios').children("div").get(0));
		var fgroup_id_radios_ffirst_child = $('#fgroup_id_radios').find('span').find('a');
		fgroup_id_radios_first_child.append(uploadradioEle);
		fgroup_id_radios_first_child.append(fgroup_id_radios_ffirst_child);
	}
    if($('.actionmodel').val() == 1){
		document.getElementById("id_upload_0").checked=false;
		document.getElementById("id_upload_0").removeAttribute('checked');
    	youtuberadio.set('checked','checked');
    }else {
		document.getElementById("id_upload_1").checked=false;
		document.getElementById("id_upload_1").removeAttribute('checked');
		uploadradio.set('checked','checked');
	}
	if(uploadradio && uploadradio.get('checked')){
		url.hide();
	}
	if(youtuberadio && youtuberadio.get('checked')){
		video.hide();
		if(precent){
			precent.hide();
		}
	}
	uploadradio.on('change', function() {
	if (uploadradio.get('value') == 0) {
		document.getElementById("id_upload_1").checked=false;
		document.getElementById("id_upload_1").removeAttribute('checked');
		video.show();
			if(precent){
				precent.show();
			}
			url.hide();
		}
	});
	youtuberadio.on('change', function() {
	if (youtuberadio.get('value') == 1) {
		document.getElementById("id_upload_0").checked=false;
		document.getElementById("id_upload_0").removeAttribute('checked');
	  		video.hide();
			if(precent){
				precent.hide();
			}
	  		url.show();
	  	}
	  });
	};
M.mod_videoassessment.init_notification_form_change = function (Y){

	Y.on("click", function(e) {
		e.preventDefault();
		if(Y.one('.teacher-notification-displaybtn').hasClass('expanded')){
			Y.one('.teacher-notification-displaybtn').removeClass('expanded');
			Y.one('.teacher-notification-displaybtn').addClass('collapsed');
			Y.one('#fgroup_id_teachernotificationgroup').hide();
		}else{
			Y.one('.teacher-notification-displaybtn').removeClass('collapsed');
			Y.one('.teacher-notification-displaybtn').addClass('expanded');
			Y.one('#fgroup_id_teachernotificationgroup').show();
		}
	}, ".teacher-notification-displaybtn", this);

	Y.on("click", function(e) {
		e.preventDefault();
		if(Y.one('.reminder-notification-displaybtn').hasClass('expanded')){
			Y.one('.reminder-notification-displaybtn').removeClass('expanded');
			Y.one('.reminder-notification-displaybtn').addClass('collapsed');
			Y.one('#fgroup_id_remindernotificationgroup').hide();
		}else{
			Y.one('.reminder-notification-displaybtn').removeClass('collapsed');
			Y.one('.reminder-notification-displaybtn').addClass('expanded');
			Y.one('#fgroup_id_remindernotificationgroup').show();
		}
	}, ".reminder-notification-displaybtn", this);

	Y.on("click", function(e) {
		e.preventDefault();
		if(Y.one('.peer-notification-displaybtn').hasClass('expanded')){
			Y.one('.peer-notification-displaybtn').removeClass('expanded');
			Y.one('.peer-notification-displaybtn').addClass('collapsed');
			Y.one('#fgroup_id_peernotificationgroup').hide();
		}else{
			Y.one('.peer-notification-displaybtn').removeClass('collapsed');
			Y.one('.peer-notification-displaybtn').addClass('expanded');
			Y.one('#fgroup_id_peernotificationgroup').show();
		}
	}, ".peer-notification-displaybtn", this);

	Y.on("click", function(e) {
		e.preventDefault();
		if(Y.one('.video-notification-displaybtn').hasClass('expanded')){
			Y.one('.video-notification-displaybtn').removeClass('expanded');
			Y.one('.video-notification-displaybtn').addClass('collapsed');
			Y.one('#fgroup_id_videonotificationgroup').hide();
		}else{
			Y.one('.video-notification-displaybtn').removeClass('collapsed');
			Y.one('.video-notification-displaybtn').addClass('expanded');
			Y.one('#fgroup_id_videonotificationgroup').show();
		}
	}, ".video-notification-displaybtn", this);
	Y.one('#fgroup_id_teachernotificationgroup').hide();
	Y.one('#fgroup_id_remindernotificationgroup').hide();
	Y.one('#fgroup_id_peernotificationgroup').hide();
	Y.one('#fgroup_id_videonotificationgroup').hide();
	document.getElementById("id_isbeforeduedate").parentElement.setAttribute('style',"width:auto");
	document.getElementById("id_isafterduedate").parentElement.setAttribute('style',"width:auto");

}

