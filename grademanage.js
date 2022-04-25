jQuery(function($) {
    var hasgrades = new Array();
    var gradetype = '';
    $('.check-has-grade input').each(function (gradetypes) {
        if (this.value){
            hasgrades.push($(this).attr('text'));
            if ($('.check-has-grade').hasClass(this.name)){
                var href = $('.actions .action:first-child img').attr('src');
                if (href && href.indexOf('document-edit') > -1) {
                    $('.actions .action:first-child').attr('href', 'javascript:void(0)');
                    $('.actions .action:first-child').attr('disabled', 'true');
                }
            }
        }
        if ($('.check-has-grade').hasClass(this.name)){
            gradetype = this.name;
        }
    });

    $('.type_custom .item_with_icon a').each(function (gradetypes) {
        var areaid = $(this).attr('href').split("?areaid=").pop();
        if(jQuery.inArray(areaid, hasgrades) !== -1) {
            $(this).attr('href', 'javascript:void(0)');
            $(this).attr('disabled', 'true');
        }
    });
    var helpbtn = $('#fgroup_id_notificationcarriergroup').next().find('.col-md-9').find('.btn.btn-link')
    helpbtn.addClass('float-sm-right');
    $('#fgroup_id_notificationcarriergroup').next().find('.col-md-3').append(helpbtn);

    var classhelpbtn = $('#fitem_id_gradecat').next().find('.col-md-9').find('.btn.btn-link');
    classhelpbtn.addClass('float-sm-right');
    $('#fitem_id_gradecat').next().find('.col-md-3').append(classhelpbtn);

    // $('.retabke-button-upload').click(function(){
    //     $(this).hide();
    //     $(this).parent().find('.delete-video-button').show();
    // });
});