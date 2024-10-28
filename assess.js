/* MinhTB VERSION 2 */
jQuery(function($) {

    if($("#fitem_id_advancedgradingbefore").find(".col-md-9").length > 0){
        $("#fitem_id_advancedgradingbefore").find(".col-md-3").attr('style',"max-width:100%;");
        $("#fitem_id_advancedgradingbefore").find(".col-md-9").attr('style',"max-width:100%;");
        $("#fitem_id_advancedgradingbefore").find(".levels").find(".level").attr('style',"max-width:100%;");
        $("#fitem_id_advancedgradingbefore").find(".description").attr('style',"min-width: 120px !important;");
        $("#fitem_id_advancedgradingbefore").find(".remark").attr('style',"min-width: 150px !important;");
    }

    $(window).scroll(function() {
        var $video = $('.assess-form-videos > .video-wrap');
        if($video.parent().offset()){
            var $video_top = $video.parent().offset().top;
            var $video_height = $video.height();
            var $form = $('.path-mod-videoassessment .gradingform');
            var $scroll_form = $form.offset().top + $form.height();

            if ($(this).scrollTop() >= ($video_top - 62) && $(this).scrollTop() < ($scroll_form - $video_height - 62)) {
                var $padding = $(this).scrollTop() - $video_top + 62;
                $video.css({'padding-top' : $padding});
            } else if ($(this).scrollTop() < ($video_top - 62)) {
                $video.css({'padding-top' : 0});
            }
        }
    });

    if($('iframe')){
        $('iframe')[0].addEventListener('load',function(){
            if( /Android|webOS|iPhone|iPad|iPod|BlackBerry/i.test(navigator.userAgent) ) {
                var youtubemediaplugin  = $('iframe');
                youtubemediaplugin.attr('style','width:100% !important;top:0;left:0;position:static');
                youtubemediaplugin.attr('allowfullscreen','false');
                youtubemediaplugin.removeAttr('width');
                youtubemediaplugin.removeAttr('height');
            }
        })
    }

    $(window).load(function() {

        var rubrics_passed = $('input[name="rubrics_passed"]').val();

        if (typeof(rubrics_passed) != 'undefined') {
            rubrics_passed = $.parseJSON(rubrics_passed);

            for (var key in rubrics_passed) {
                var rid = rubrics_passed[key];
                var id = "advancedgradingbefore-criteria-" + rid;
                var rubric = $("table#advancedgradingbefore-criteria").find('#' + id);
                var rubric_result = $('#training-result-table-render').find('#' + id);
                rubric_result.addClass(rubric.attr('class'));

                rubric.after(rubric_result);
                rubric.hide();
            }
        }
    });

});
