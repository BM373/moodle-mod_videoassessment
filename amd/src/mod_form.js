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
 * @package
 * @module     mod_videoassessment/mod_form
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    /**
     * Initializes the behavior of the training selector.
     *
     * Shows or hides the training video, accepted difference, and description
     * fields based on the selected training option.
     */
    function initTrainingChange() {
        const training = $('#id_training');
        const video = $('#fitem_id_trainingvideo');
        const point = $('#fitem_id_accepteddifference');
        const desc = $('#fitem_id_trainingdesc');

        if (training.length) {
            if (training.val() != 1) {
                video.hide();
                point.hide();
                desc.hide();
            }

            training.on('change', function() {
                if ($(this).val() == 1) {
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
    }

    /**
     * Initializes peer assessment option change handling.
     *
     * Automatically selects "1" as the number of peers when a peer assessment
     * value greater than 0 is chosen.
     */
    function initQuickSetupPeerChange() {
        const peerAssess = $('#id_peerassess');
        peerAssess.on('change', function() {
            if ($(this).val() > 0) {
                $('#id_numberofpeers').find('option[value="1"]').prop('selected', true);
            }
        });
    }

    /**
     * Initializes display toggling for fairness bonus and self-fairness bonus fields.
     *
     * Shows or hides relevant bonus percentage and score fields based on whether
     * the corresponding toggle is enabled.
     */
    function initFairnessBonusChange() {
        /**
         * Toggles visibility of specified form fields based on the value of a toggle input.
         *
         * If the toggle field's value is `1`, the given fields will be shown;
         * otherwise, they will be hidden. Also attaches a change listener to update
         * visibility dynamically when the user changes the toggle input.
         *
         * @param {string} toggleId - jQuery selector for the toggle input element.
         * @param {string[]} fields - Array of jQuery selectors for the fields to show/hide.
         */
        function toggleBonusFields(toggleId, fields) {
            const toggle = $(toggleId);
            if (toggle.length) {
                const val = toggle.val();
                if (val != 1) {
                    fields.forEach(f => $(f).hide());
                }

                toggle.on('change', function() {
                    if ($(this).val() == 1) {
                        fields.forEach(f => $(f).show());
                    } else {
                        fields.forEach(f => $(f).hide());
                    }
                });
            }
        }

        toggleBonusFields('#id_fairnessbonus', [
            '#fitem_id_bonuspercentage',
            '#fgroup_id_bonusscoregroup1',
            '#fgroup_id_bonusscoregroup2',
            '#fgroup_id_bonusscoregroup3',
            '#fgroup_id_bonusscoregroup4',
            '#fgroup_id_bonusscoregroup5',
            '#fgroup_id_bonusscoregroup6'
        ]);

        toggleBonusFields('#id_selffairnessbonus', [
            '#fitem_id_selfbonuspercentage',
            '#fgroup_id_selfbonusscoregroup1',
            '#fgroup_id_selfbonusscoregroup2',
            '#fgroup_id_selfbonusscoregroup3',
            '#fgroup_id_selfbonusscoregroup4',
            '#fgroup_id_selfbonusscoregroup5',
            '#fgroup_id_selfbonusscoregroup6'
        ]);
    }

    /**
     * Initializes logic for switching between video upload types.
     *
     * Toggles visibility of relevant form fields for uploading a video file,
     * linking to YouTube, or recording a new video. Also reorders some form
     * elements for better UX on mobile.
     */
    function initUploadTypeChange() {
        const uploadRadio = $('#id_upload_0');
        const youtubeRadio = $('#id_upload_1');
        const recordNewVideo = $('#id_upload_2');

        const precent = $('#fitem_id_precent').length ? $('#fitem_id_precent') : $('#fitem_id_mobilevideo');
        const video = $('#fitem_id_video').length ? $('#fitem_id_video') : $('#fitem_id_mobilevideo');
        const url = $('#id_url').length ? $('#id_url') : $('#id_mobileurl');
        const recordContent = $('#recordrtc');
        const submitButtons = $('#fgroup_id_buttonar');

        if ($('#mobileform').length) {
            $('#fgroup_id_recordradios').hide();
        }

        $('.col-md-3').each(function() {
            if ($(this).children().length == 0) {
                $(this).remove();
            }
        });

        const rearrangeRadios = (groupId, radioId) => {
            if ($(groupId).length) {
                const radio = $(radioId).parent();
                const target = $(groupId).children('div').first();
                const label = $(groupId).find('span a');
                target.append(radio);
                target.append(label);
            }
        };

        rearrangeRadios('#fgroup_id_radios', '#id_upload_1');
        rearrangeRadios('#fgroup_id_recordradios', '#id_upload_2');

        // Default to YouTube if it exists, otherwise keep current selection.
        if (youtubeRadio.length) {
            uploadRadio.prop('checked', false);
            recordNewVideo.prop('checked', false);
            youtubeRadio.prop('checked', true);
        }

        const updateVisibility = () => {
            if (uploadRadio.is(':checked')) {
                url.hide();
                recordContent.hide();
                video.show();
                precent.show();
                submitButtons.show();
            } else if (youtubeRadio.is(':checked')) {
                video.hide();
                precent.hide();
                recordContent.hide();
                url.show();
                submitButtons.show();
            } else if (recordNewVideo.is(':checked')) {
                video.hide();
                url.hide();
                precent.hide();
                recordContent.show();
                submitButtons.hide();
            }
        };

        uploadRadio.on('change', updateVisibility);
        youtubeRadio.on('change', updateVisibility);
        recordNewVideo.on('change', updateVisibility);
        updateVisibility();
    }

    /**
     * Initializes the notification settings form toggle behavior.
     *
     * Enables expand/collapse toggling for each notification group section and
     * adjusts form field layout styles.
     */
    function initNotificationFormChange() {
        const toggleGroup = (btnClass, groupId) => {
            $(document).on('click', btnClass, function(e) {
                e.preventDefault();
                const btn = $(this);
                if (btn.hasClass('expanded')) {
                    btn.removeClass('expanded').addClass('collapsed');
                    $(groupId).hide();
                } else {
                    btn.removeClass('collapsed').addClass('expanded');
                    $(groupId).show();
                }
            });
        };

        toggleGroup('.teacher-notification-displaybtn', '#fgroup_id_teachernotificationgroup');
        toggleGroup('.reminder-notification-displaybtn', '#fgroup_id_remindernotificationgroup');
        toggleGroup('.peer-notification-displaybtn', '#fgroup_id_peernotificationgroup');
        toggleGroup('.video-notification-displaybtn', '#fgroup_id_videonotificationgroup');

        $('#fgroup_id_teachernotificationgroup').hide();
        $('#fgroup_id_remindernotificationgroup').hide();
        $('#fgroup_id_peernotificationgroup').hide();
        $('#fgroup_id_videonotificationgroup').hide();

        $('#id_isbeforeduedate').parent().css('width', 'auto');
        $('#id_isafterduedate').parent().css('width', 'auto');
    }

    /**
     * Initializes the visibility toggle and syncing for Assign Peer Assessors section.
     *
     * - Shows or hides the peer assessors section based on Peer % and Number of Peer Assessors
     * - When Peer % is set to 0, Number of Peer Assessors is set to 0 and vice versa
     * - Section is shown by default (when Peer % > 0)
     */
    function initPeerAssessorsVisibility() {
        const peerRating = $('#id_ratingpeer');
        const usedPeers = $('#id_usedpeers');
        const container = $('#assign-peer-assessors-container');

        if (!container.length || !peerRating.length || !usedPeers.length) {
            return;
        }

        // Track if we're currently syncing to prevent infinite loops.
        let isSyncing = false;

        /**
         * Update the visibility of the peer assessors container.
         */
        const updateVisibility = () => {
            const peerRatingVal = parseInt(peerRating.val()) || 0;
            const usedPeersVal = parseInt(usedPeers.val()) || 0;

            if (peerRatingVal === 0 && usedPeersVal === 0) {
                container.slideUp(200);
            } else {
                container.slideDown(200);
            }
        };

        /**
         * When Peer % changes to 0, set Number of Peer Assessors to 0.
         * When Peer % changes from 0 to > 0, set Number of Peer Assessors to 2 (if it was 0).
         */
        const syncFromPeerRating = () => {
            if (isSyncing) {
 return;
}
            isSyncing = true;

            const peerRatingVal = parseInt(peerRating.val()) || 0;
            const usedPeersVal = parseInt(usedPeers.val()) || 0;

            if (peerRatingVal === 0) {
                // Peer % is 0, set Number of Peer Assessors to 0.
                usedPeers.val(0);
            } else if (peerRatingVal > 0 && usedPeersVal === 0) {
                // Peer % is > 0 but Number of Peer Assessors is 0, set to default 2.
                usedPeers.val(2);
            }

            updateVisibility();
            isSyncing = false;
        };

        /**
         * When Number of Peer Assessors changes to 0, set Peer % to 0.
         * When Number of Peer Assessors changes from 0 to > 0, set Peer % to 10 (if it was 0).
         */
        const syncFromUsedPeers = () => {
            if (isSyncing) {
 return;
}
            isSyncing = true;

            const peerRatingVal = parseInt(peerRating.val()) || 0;
            const usedPeersVal = parseInt(usedPeers.val()) || 0;

            if (usedPeersVal === 0) {
                // Number of Peer Assessors is 0, set Peer % to 0.
                peerRating.val(0);
            } else if (usedPeersVal > 0 && peerRatingVal === 0) {
                // Number of Peer Assessors is > 0 but Peer % is 0, set to default 10.
                peerRating.val(10);
            }

            updateVisibility();
            isSyncing = false;
        };

        // Initial state - show container since default Peer % is 10.
        updateVisibility();

        // Listen for changes on Peer %.
        peerRating.on('change', syncFromPeerRating);

        // Listen for changes on Number of Peer Assessors.
        usedPeers.on('change blur', syncFromUsedPeers);
        usedPeers.on('keyup', function() {
            // Delay keyup to avoid syncing on every keystroke.
            clearTimeout($(this).data('syncTimeout'));
            $(this).data('syncTimeout', setTimeout(syncFromUsedPeers, 500));
        });
    }

    /**
     * Initializes the visibility of the "Save and create rubric" button.
     *
     * Shows the button only when "rubric" is selected as the grading method.
     * Also sets up the click handler to set the redirect flag and submit the form.
     */
    function initRubricButtonVisibility() {
        // Find the grading method select (it's the first advancedgradingmethod_ field).
        const gradingMethodSelect = $('select[name^="advancedgradingmethod_"]').first();
        const rubricButton = $('#id_submitbutton_rubric');

        if (!rubricButton.length) {
            return;
        }

        /**
         * Update the visibility of the rubric button.
         */
        const updateVisibility = () => {
            if (gradingMethodSelect.length) {
                const selectedMethod = gradingMethodSelect.val();
                if (selectedMethod === 'rubric') {
                    rubricButton.show();
                } else {
                    rubricButton.hide();
                }
            } else {
                // No grading method select found, hide the button.
                rubricButton.hide();
            }
        };

        // Initial state.
        updateVisibility();

        // Listen for changes.
        if (gradingMethodSelect.length) {
            gradingMethodSelect.on('change', updateVisibility);
        }

        // When rubric button is clicked, set a flag and submit the form normally.
        // The redirect will happen via user preference which is checked by an AJAX call after page load.
        rubricButton.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Set the redirect flag in the form.
            let field = $('input[name="redirect_to_rubric"]');
            if (field.length) {
                field.val('1');
            } else {
                field = $('<input type="hidden" name="redirect_to_rubric" value="1">');
                $('form.mform').append(field);
            }

            // Store the intent in sessionStorage with a timestamp and unique token.
            // The token ensures this specific redirect is only processed once.
            var token = Math.random().toString(36).substring(2, 15);
            var timestamp = Date.now();
            sessionStorage.setItem('videoassessment_check_grading_redirect', timestamp.toString() + ':' + token);

            // Mark this token as processed immediately to prevent re-processing.
            var processedTokens = JSON.parse(sessionStorage.getItem('videoassessment_processed_tokens') || '[]');
            processedTokens.push(token);
            sessionStorage.setItem('videoassessment_processed_tokens', JSON.stringify(processedTokens));

            // Find the form.
            const form = $('form.mform');
            if (!form.length) {
                return;
            }

            // Mark the form as submitted BEFORE submitting to prevent beforeunload warning.
            // Moodle's change checker checks for dataset.formSubmitted === "true" to determine
            // if a form has been submitted and should not trigger the warning.
            form[0].dataset.formSubmitted = "true";
            form[0].dataset.ignoreSubmission = "true";

            // Also try to mark using Moodle's change checker API if available.
            require(['core_form/changechecker'], function(changeChecker) {
                if (typeof changeChecker.markFormSubmitted === 'function') {
                    changeChecker.markFormSubmitted(form[0]);
                }
            });

            // Add submitbutton for "Save and display" behavior (goes to activity view page).
            // The redirect check will now work on the activity view page too.
            // Remove any existing submitbutton hidden inputs.
            form.find('input[name="submitbutton"][type="hidden"]').remove();
            form.append('<input type="hidden" name="submitbutton" value="1">');

            // Submit the form. The formSubmitted flag should prevent the beforeunload warning.
            form[0].submit();
        });
    }

    /**
     * Check if we need to redirect to the grading page after activity creation.
     * This should be called on every page load to check for pending redirects.
     */
    function checkGradingRedirect() {
        // Check sessionStorage for the redirect flag with timestamp.
        const redirectData = sessionStorage.getItem('videoassessment_check_grading_redirect');

        if (redirectData) {
            const storedTime = parseInt(redirectData, 10);
            const now = Date.now();

            // Only proceed if the redirect was set less than 30 seconds ago.
            if (now - storedTime < 30000) {
                // Clear the flag immediately.
                sessionStorage.removeItem('videoassessment_check_grading_redirect');

                // Call the AJAX endpoint to check if redirect is needed.
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/videoassessment/check_grading_redirect.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.redirect && response.url) {
                            window.location.replace(response.url);
                        }
                    },
                    error: function() {
                        // Silently ignore errors; redirect check is best-effort.
                    }
                });
            } else {
                // Expired, just remove it.
                sessionStorage.removeItem('videoassessment_check_grading_redirect');
            }
        }
    }

    return {
        initTrainingChange: initTrainingChange,
        initQuickSetupPeerChange: initQuickSetupPeerChange,
        initFairnessBonusChange: initFairnessBonusChange,
        initUploadTypeChange: initUploadTypeChange,
        initNotificationFormChange: initNotificationFormChange,
        initPeerAssessorsVisibility: initPeerAssessorsVisibility,
        initRubricButtonVisibility: initRubricButtonVisibility,
        checkGradingRedirect: checkGradingRedirect
    };
});
