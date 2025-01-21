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
define(['jquery', 'core/yui', 'core/str', 'mod_videoassessment/ajaxcalls'], function ($, Y, Str, Ajaxcalls) {
    return {
        assignclassSortByGroup: function () {
            $(document).ready(function(){
                var sort = $('#sortby').val();
                var groupid = $('#separate-group').val();

                var id = $('.sort-form input[type="hidden"][name="id"]').val();
                var url = $('.sort-form').attr('action');
                $('#separate-group').after('<div class="loading-icon"><i class="fa fa-refresh fa-spin fa-3x fa-fw margin-bottom"></i></div>');
                var ajaxx = require("mod_videoassessment/ajaxcalls");
                var ajaxacsg = new ajaxx();
                ajaxacsg.assignclasssortgroup("assignclasssortgroup", sort, groupid, id);
            });
            /* Load sort manually list */
            $('#sortby').change(function() {
                var t = $(this);
                var sort = $('#sortby').val();
                var groupid = $('#separate-group').val();
                var load = $('#sortby').data('load');

                $('.id_order_students').remove();

                if (sort == 3 && load == 1) {
                    $('#manually-list').removeClass('hidden');
                } else {
                    var id = t.closest('form').find('input[type="hidden"][name="id"]').val();
                    $('#separate-group').after('<div class="loading-icon"><i class="fa fa-refresh fa-spin fa-3x fa-fw margin-bottom"></i></div>');
                    var ajaxx = require("mod_videoassessment/ajaxcalls");
                    var ajaxacsg = new ajaxx();
                    ajaxacsg.assignclasssortgroup("assignclasssortgroup", sort, groupid, id);
                    if (sort != 3) {
                        $('#manually-list').addClass('hidden');
                    }
                }
            });

            $('#separate-group').change(function() {
                var url = $(this).closest('form').attr('action');
                var id = $(this).closest('form').find('input[type="hidden"][name="id"]').val();
                var groupid = $(this).val();
                M.core_formchangechecker.set_form_submitted();
                window.location.replace(url + '?id=' + id + '&groupid=' + groupid);
            });
        }
    };
});

