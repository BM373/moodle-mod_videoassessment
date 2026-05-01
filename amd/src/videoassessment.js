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

/* eslint-disable
   promise/always-return, promise/no-nesting,
   no-restricted-properties, camelcase, no-empty-function,
   consistent-return */
/**
 * Video assessment
 *
 * @package
 * @module     mod_videoassessment/videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/str',
    'core/modal_factory',
    'core/modal_events',
    'core/notification',
    'core/ajax',
], function($, Str, ModalFactory, ModalEvents, Notification, Ajax) {
    'use strict';

    const SELECTORS = {
        commentButton: '.commentbutton',
        uploadButton: '#id_submitbutton',
        mobileForm: '#mobileform',
        mobileVideoInput: '#id_mobilevideo',
        videoError: '#id_error_mobilevideo',
        uploadProgress: '.upload-progress',
    };

    const mobileshowallcomment = () => {
        $(SELECTORS.commentButton).on('click', function() {
            const request = {
                methodname: 'mod_videoassessment_get_getallcomments',
                args: {
                    ajax: 1,
                    action: 'getallcomments',
                    userid: $(this).attr('userid'),
                    timing: $(this).attr('timing'),
                    cmid: $(this).attr('cmid'),
                    id: $(this).attr('id')
                }
            };

            Ajax.call([request])[0]
                .then((response) => {
                    if (response.html) {
                        ModalFactory.create({
                            type: ModalFactory.types.CANCEL,
                            title: `<h1>${M.str.videoassessment.generalcomments}</h1>`,
                            body: response.html,
                        }).then(modal => modal.show())
                            .catch(Notification.exception);
                    }
                })
                .catch(Notification.exception);
        });
    };

    const init_message_sent_window = (messageSent) => {
        if (parseInt(messageSent, 10) === 1) {
            Str.get_string('notificationmessagesent', 'videoassessment').then((message) => {
                ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: '',
                    body: `<h2>${message}</h2>`,
                }).then(modal => {
                    modal.show();
                    setTimeout(() => modal.hide(), 2000);
                }).catch(Notification.exception);
            }).catch(Notification.exception);
        }
    };

    const init_upload_file_step = () => {
        // Placeholder
    };

    const init_mobile_upload_progress_bar = () => {
        $(SELECTORS.uploadButton).on('click', function(e) {
            const uploadType = $('input[name="upload"]:checked').val();
            const videoFile = $(SELECTORS.mobileVideoInput)[0].files[0];

            window.onbeforeunload = null;

            if (uploadType === '1') {
                $(SELECTORS.mobileForm).submit();
                return;
            }

            e.preventDefault();

            const url = $(SELECTORS.mobileForm).attr('action');
            const formData = new FormData($(SELECTORS.mobileForm)[0]);
            const id = formData.get('id');

            const submitForm = () => {
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: () => {
                        const xhr = new XMLHttpRequest();
                        if (xhr.upload) {
                            xhr.upload.addEventListener('progress', () => {
                                $(SELECTORS.uploadProgress).show();
                            });
                        }
                        return xhr;
                    },
                    success: (data) => {
                        try {
                            if (data && data.action) {
                                window.location.href = `${url}?action=${data.action}&id=${id}`;
                            } else {
                                window.location.href = `${url}?id=${id}`;
                            }
                        } catch (error) {
                            Notification.exception(error);
                        }
                    },
                    error: Notification.exception,
                });
            };

            if (!videoFile) {
                $(SELECTORS.videoError).text(M.str.videoassessment.erroruploadvideo).show();
                return false;
            }

            if (videoFile.size > 500000000) {
                Str.get_strings([
                    {key: 'uploadmessage', component: 'videoassessment'},
                    {key: 'upload', component: 'moodle'},
                    {key: 'cancel', component: 'moodle'},
                ]).then(strings => {
                    Notification.confirm(strings[0], '', strings[1], strings[2])
                        .then(submitForm)
                        .catch(() => { });
                }).catch(Notification.exception);
            } else {
                submitForm();
            }
        });
    };

    /**
     * Check if we should redirect to the advanced grading page after activity creation.
     * This is triggered by the "Save and create rubric" button on the mod_form.
     *
     * @param {number} contextId - The context ID of the current activity.
     * @param {number} cmid - The course module ID.
     */
    const checkRedirectToGrading = (contextId, cmid) => {
        const shouldRedirect = localStorage.getItem('videoassessment_redirect_to_grading');

        if (shouldRedirect === '1') {
            // Clear the flag immediately to prevent redirect loops.
            localStorage.removeItem('videoassessment_redirect_to_grading');


            // Redirect to the grading management page.
            // We'll use the view.php with a special parameter that will handle finding the area ID.
            window.location.href = M.cfg.wwwroot + '/mod/videoassessment/view.php?id=' + cmid + '&redirecttograding=1';
        }
    };

    return {
        mobileshowallcomment,
        init_message_sent_window,
        init_upload_file_step,
        init_mobile_upload_progress_bar,
        checkRedirectToGrading
    };
});