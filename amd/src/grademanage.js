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
        init_grademanage: function () {
            var hasgrades = new Array();
            var gradetype = '';
            $('.check-has-grade input').each(function (gradetypes) {
                if (this.value) {
                    hasgrades.push($(this).attr('text'));
                    if ($('.check-has-grade').hasClass(this.name)) {
                        var href = $('.actions .action:first-child img').attr('src');
                        if (href && href.indexOf('document-edit') > -1) {
                            $('.actions .action:first-child').attr('href', 'javascript:void(0)');
                            $('.actions .action:first-child').attr('disabled', 'true');
                        }
                    }
                }
                if ($('.check-has-grade').hasClass(this.name)) {
                    gradetype = this.name;
                }
            });

            $('.type_custom .item_with_icon a').each(function (gradetypes) {
                var areaid = $(this).attr('href').split("?areaid=").pop();
                if (jQuery.inArray(areaid, hasgrades) !== -1) {
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

            $('#id_quickSetupButton').removeClass('btn-secondary').addClass('btn-primary');
            $('#id_quickSetupButton').on('click', function (e) {
                e.preventDefault();
                M.core_formchangechecker.reset_form_dirty_state();
                var quickSetupform = $('#id_quickSetupButton').closest('.mform');
                var action = $('input[name="quickSetupFormUrl"]').val();
                quickSetupform.attr('action',action);
                $('input[name="isquickSetup"]').val(1);
                quickSetupform.submit();
            });
        }
    };
});
