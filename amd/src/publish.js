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
        mobilepublishvideo: function () {
            $('#publish-category').change(function() {
                var catid = $(this).val();
                var url = $(this).closest('form').attr('action');
                var currentcourseid = $('#publish-course').val();
                var ajaxx = require("mod_videoassessment/ajaxcalls");
                var ajaxgcbc = new ajaxx();
                ajaxgcbc.getcoursesbycategory("getcoursesbycategory", catid, currentcourseid);
            }).change();

            $('#publish-course').change(function() {
                var courseid = $(this).val();
                var url = $(this).closest('form').attr('action');
                var currentsectionid = $('#publish-section').val();

                if (courseid != 0) {
                    $('#publish-fullname').attr('disabled', 'disabled');
                    $('#publish-shortname').attr('disabled', 'disabled');
                    var ajaxx = require("mod_videoassessment/ajaxcalls");
                    var ajaxgsbc = new ajaxx();
                    ajaxgsbc.getsectionsbycourse("getsectionsbycourse", courseid, currentsectionid);
                    $('#publish-fullname').attr('disabled', 'disabled').val('');
                    $('#publish-shortname').attr('disabled', 'disabled').val('');
                } else {
                    $('#publish-section').attr('disabled', 'disabled').html('');
                    $('#publish-fullname').removeAttr('disabled');
                    $('#publish-shortname').removeAttr('disabled');
                }
            }).change();

            $(document).on('change', '.video-check', function() {
                var check = $(this).prop('checked');
                var count = $('#video-count').val();

                if (check) {
                    count++;
                } else {
                    count--;
                }

                $('#video-count').val(count);
            });

            $(document).on('change', '#all-video-check', function() {
                var check = $(this).prop('checked');
                var count;

                if (check) {
                    count = $('.video-check').size();
                } else {
                    count = 0;
                }

                $('#video-count').val(count);
            });
        }
    };

});

