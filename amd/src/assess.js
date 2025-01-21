// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
define(['jquery', 'core/yui', 'core/str'], function ($, Y, Str) {
    return {
        videoassessmentAssess: function () {
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
                const iframe = document.createElement('iframe');
                iframe.addEventListener('load',function(){
                    if( /Android|webOS|iPhone|iPad|iPod|BlackBerry/i.test(navigator.userAgent) ) {
                        var youtubemediaplugin  = iframe;
                        youtubemediaplugin.attr('style','width:100% !important;top:0;left:0;position:static');
                        youtubemediaplugin.attr('allowfullscreen','false');
                        youtubemediaplugin.removeAttr('width');
                        youtubemediaplugin.removeAttr('height');
                    }
                })
            }

            //$(window).load(function() {

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
            //});
        }
    };

});

