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

/* eslint-disable no-restricted-properties */
/**
 * AJAX call helpers for the videoassessment module.
 *
 * @module     mod_videoassessment/ajaxcalls
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery', 'core/notification', 'core/ajax', 'core/modal_factory'],
    function($, notification, ajax, ModalFactory) {

        /**
         * Ajaxcall class to handle async operations in videoassessment.
         *
         * @class
         */
        function Ajaxcall() {
            this.value = 'ajax ok';
        }

        Ajaxcall.prototype.getGetallcomments = function(action, userid, timing, cmid, id) {
            var promises = ajax.call([{
                methodname: 'mod_videoassessment_get_getallcomments',
                args: {ajax: 1, action: action, userid: userid, timing: timing, cmid: cmid, id: id},
                fail: notification.exception,
            }]);
            return promises[0]
                .then(function(data) {
                    var res = $.parseJSON(data.html);
                    if (!res) {
                        return null;
                    }
                    return ModalFactory.create({
                        type: ModalFactory.types.CANCEL,
                        title: '<h1>' + M.str.videoassessment.generalcomments + '</h1>',
                        body: res,
                    });
                })
                .then(function(modal) {
                    if (modal) {
                        modal.show();
                    }
                    return modal;
                })
                .catch(notification.exception);
        };

        Ajaxcall.prototype.getcoursesbycategory = function(action, catid, currentcourseid) {
            var promises = ajax.call([{
                methodname: 'mod_videoassessment_get_coursesbycategory',
                args: {ajax: 1, action: action, catid: catid, currentcourseid: currentcourseid},
                fail: notification.exception,
            }]);
            return promises[0]
                .then(function(data) {
                    if (data.html) {
                        $('#publish-course').html(data.html);
                        $('#publish-course').trigger('change');
                    }
                    return data;
                })
                .catch(notification.exception);
        };

        Ajaxcall.prototype.getsectionsbycourse = function(action, courseid, currentsectionid) {
            var promises = ajax.call([{
                methodname: 'mod_videoassessment_get_sectionsbycourse',
                args: {ajax: 1, action: action, courseid: courseid, currentsectionid: currentsectionid},
                fail: notification.exception,
            }]);
            return promises[0]
                .then(function(data) {
                    if (data.html) {
                        $('#publish-section').html(data.html);
                        $('#publish-section').removeAttr('disabled');
                    } else {
                        $('#publish-section').attr('disabled', 'disabled');
                    }
                    return data;
                })
                .catch(notification.exception);
        };

        Ajaxcall.prototype.assignclasssortgroup = function(action, sort, groupid, id) {
            var promises = ajax.call([{
                methodname: 'mod_videoassessment_assignclass_sort_group',
                args: {action: action, sort: sort, groupid: groupid, id: id},
                fail: notification.exception,
            }]);
            return promises[0]
                .then(function(data) {
                    if (sort == 3) {
                        $('#sortby').data('load', 1);
                    }
                    $('#separate-group').after(data.html);
                    $('#separate-group').parent().find('.loading-icon').remove();
                    return data;
                })
                .catch(notification.exception);
        };

        return Ajaxcall;
    }
);
