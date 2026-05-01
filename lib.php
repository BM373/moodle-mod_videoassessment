<?php
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
 * This file contains the moodle hooks for the videoassessment module.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoassessment\va;

defined('MOODLE_INTERNAL') || die();

// Event types.
define('VIDEOASSESS_EVENT_TYPE_DUE', 'due');
define('VIDEOASSESS_EVENT_TYPE_GRADINGDUE', 'gradingdue');

/**
 * Add a new video assessment instance to the database.
 *
 * Creates a new video assessment activity with proper configuration
 * for assessment types, grading, and calendar events.
 *
 * @param stdClass $va Video assessment instance data
 * @param mod_videoassessment_mod_form $form Form data for validation
 * @return int The ID of the newly created video assessment instance
 * @throws moodle_exception If database insertion fails
 */
function videoassessment_add_instance($va, $form) {
    global $DB, $CFG;

    // Initialize gradepass fields immediately to ensure they're always set
    // Default to 0 if not already set.
    if (!isset($va->gradepass) || $va->gradepass === null) {
        $va->gradepass = 0.0;
    }
    if (!isset($va->gradepass_videoassessment) || $va->gradepass_videoassessment === null) {
        $va->gradepass_videoassessment = 0.0;
    }

    if (isset($va->isquickSetup) && $va->isquickSetup == 1) {
        if ($va->isselfassesstype == 1 || $va->ispeerassesstype == 1 || $va->isteacherassesstype == 1 || $va->isclassassesstype == 1) {
            if ($va->isselfassesstype == 1) {
                $va->ratingself = $va->selfassess;
            } else {
                $va->ratingself = 0;
            }
            if ($va->ispeerassesstype == 1) {
                $va->ratingpeer = $va->peerassess;
                if ($va->peerassess == 0) {
                    $va->numberofpeers = 0;
                }
            } else {
                $va->ratingpeer = 0;
            }
            if ($va->isteacherassesstype == 1) {
                $va->ratingteacher = $va->teacherassess;
            } else {
                $va->ratingteacher = 0;
            }
            if ($va->isclassassesstype == 1) {
                $va->ratingclass = $va->classassess;
            } else {
                $va->ratingclass = 0;
            }
        }

        if ($va->numberofpeers >= 0) {
            $va->usedpeers = $va->numberofpeers;
        }
        if ($va->gradingsimpledirect > 0) {
            $va->gradepass_videoassessment = $va->gradingsimpledirect;
            $va->gradepass = $va->gradingsimpledirect;
        }
    }
    // Ensure gradepass fields are properly initialized.
    // The gradepass field comes from Moodle's standard grading form field.
    // For itemnumber 0 (which maps to 'grading'), the field name is just 'gradepass'.
    // Our form's get_data() ensures gradepass is always set (defaults to 0 if empty).
    // Moodle core processes gradepass AFTER add_instance in edit_module_post_actions(),
    // so we need to ensure it's set in $va (which is $moduleinfo) so Moodle core can read it.

    // ALWAYS initialize to 0 - this ensures the field is never null.
    $gradepassvalue = 0.0;

    // Priority 1: Check $_POST directly first (most reliable - raw form submission)
    // This ensures we get the actual value the user entered, before any form processing
    // Note: Empty text fields may not be in $_POST, or may be empty string.
    if (isset($_POST['gradepass'])) {
        $postvalue = trim((string)$_POST['gradepass']);
        if ($postvalue !== '') {
            // Unformat_float is available from lib/moodlelib.php (always loaded).
            $unformatted = unformat_float($postvalue);
            if ($unformatted !== false && $unformatted !== null) {
                $gradepassvalue = (float)$unformatted;
            }
        }
        // If POST has gradepass but it's empty, keep default of 0.
    }

    // Priority 2: Check $va object (form's get_data() processed value)
    // Our form's get_data() ensures gradepass is always set (defaults to 0 if empty).
    if (property_exists($va, 'gradepass')) {
        $val = $va->gradepass;
        // Check if value is numeric (including 0).
        if ($val !== '' && $val !== null && is_numeric($val)) {
            $floatval = (float)$val;
            // Use $va value if POST didn't have it, or if POST was empty
            // But if POST had a non-zero value, prefer that.
            if (!isset($_POST['gradepass']) || $_POST['gradepass'] === '' || $gradepassvalue == 0) {
                $gradepassvalue = $floatval;
            }
        }
    }

    // If we still don't have a value (shouldn't happen, but be safe), ensure it's 0.
    if ($gradepassvalue === null || !is_numeric($gradepassvalue)) {
        $gradepassvalue = 0.0;
    }

    // Ensure value is valid (non-negative).
    if ($gradepassvalue < 0) {
        $gradepassvalue = 0.0;
    }

    // Convert to float to ensure it's numeric - ALWAYS do this.
    $gradepassvalue = (float)$gradepassvalue;

    // CRITICAL: ALWAYS set gradepass in $va ($moduleinfo) so Moodle core can read it in edit_module_post_actions()
    // Moodle core checks: if (isset($moduleinfo->{$gradepassfieldname})) where fieldname is 'gradepass' for itemnumber 0
    // We MUST set this so Moodle core can process it - ALWAYS set it, even if 0.
    $va->gradepass = $gradepassvalue;

    // Also ALWAYS set the database field for our module table - this ensures it's saved to DB.
    $va->gradepass_videoassessment = $gradepassvalue;

    // FINAL CHECK before database insert: Ensure both fields are ALWAYS set and numeric
    // This is a last resort to guarantee the values are set before DB insert.
    $va->gradepass = (float)$gradepassvalue;
    $va->gradepass_videoassessment = (float)$gradepassvalue;

    // Double-check: if somehow they're still null, force them to 0.
    if (!isset($va->gradepass) || $va->gradepass === null) {
        $va->gradepass = 0.0;
    }
    if (!isset($va->gradepass_videoassessment) || $va->gradepass_videoassessment === null) {
        $va->gradepass_videoassessment = 0.0;
    }

    // Ensure they're numeric (cast to float, then to int for database INTEGER field).
    $va->gradepass = (float)$va->gradepass;
    $va->gradepass_videoassessment = (float)$va->gradepass_videoassessment;

    $va->id = $DB->insert_record('videoassessment', $va);

    videoassessment_update_calendar($va);

    // Process peer assignments from the form.
    if (isset($va->peerassignments) && $va->peerassignments !== '' && $va->peerassignments !== '{}') {
        videoassessment_save_peer_assignments($va->id, $va->peerassignments);
    }

    // Check if "Save and create rubric" button was clicked.
    // Be EXTREMELY strict - only set preference if redirect_to_rubric is explicitly set to '1' or 1.
    // Do NOT check for submitbutton_rubric as that might be set incorrectly.
    // Only rely on the redirect_to_rubric hidden field which is set by JavaScript when the rubric button is clicked.
    $rubricbuttonclicked = false;

    // Check $_POST first (most reliable for form submissions).
    if (!empty($_POST['redirect_to_rubric']) && ($_POST['redirect_to_rubric'] == '1' || $_POST['redirect_to_rubric'] == 1)) {
        $rubricbuttonclicked = true;
    } else if (!empty($va->redirect_to_rubric) && ($va->redirect_to_rubric == '1' || $va->redirect_to_rubric == 1)) {
        $rubricbuttonclicked = true;
    }
    // Explicitly DO NOT check for submitbutton_rubric to avoid false positives.

    // IMPORTANT: If rubric button was NOT clicked, clear advanced grading method fields
    // to prevent Moodle core from redirecting to grading management page.
    // Moodle core redirects when advanced grading method is set but no form exists.
    if (!$rubricbuttonclicked) {
        // Clear all advancedgradingmethod_* fields from the data that will be processed by Moodle core.
        // This prevents unwanted redirects when user clicks "Save and display".
        foreach ($va as $key => $value) {
            if (strpos($key, 'advancedgradingmethod_') === 0) {
                unset($va->$key);
            }
        }
        // Also clear from $_POST to ensure Moodle core doesn't see them.
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'advancedgradingmethod_') === 0) {
                unset($_POST[$key]);
            }
        }
    }

    if ($rubricbuttonclicked) {
        // Use user preference with timestamp to avoid "session mutated after closed" error.
        // Store timestamp to ensure redirect only happens if preference is recent.
        $prefvalue = $va->id . ':' . time();
        set_user_preference('videoassessment_redirect_to_grading', $prefvalue);
    } else {
        // Explicitly clear any existing preference if rubric button was NOT clicked.
        // This prevents stale preferences from causing unwanted redirects.
        $existingpref = get_user_preferences('videoassessment_redirect_to_grading');
        if (!empty($existingpref)) {
            unset_user_preference('videoassessment_redirect_to_grading');
        }
    }

    // FINAL CHECK: Ensure gradepass is always set in $va ($moduleinfo) before returning
    // This ensures Moodle core can read it in edit_module_post_actions().
    if (!isset($va->gradepass) || $va->gradepass === null) {
        $va->gradepass = isset($va->gradepass_videoassessment) ? (float)$va->gradepass_videoassessment : 0.0;
    } else {
        $va->gradepass = (float)$va->gradepass;
    }

    return $va->id;
}

/**
 * Update an existing video assessment instance in the database.
 *
 * Modifies video assessment configuration, handles assessment type changes,
 * and triggers regrading when necessary.
 *
 * @param stdClass $va Video assessment instance data
 * @param mod_videoassessment_mod_form $form Form data for validation
 * @return boolean True if update was successful
 * @throws moodle_exception If database update fails
 */
function videoassessment_update_instance($va, $form) {
    global $DB, $CFG;

    $va->id = $va->instance;
    $cm = get_coursemodule_from_instance('videoassessment', $va->id, 0, false, MUST_EXIST);
    if (isset($va->isquickSetup) && $va->isquickSetup == 1) {
        if ($va->isselfassesstype == 1 || $va->ispeerassesstype == 1 || $va->isteacherassesstype == 1 || $va->isclassassesstype == 1) {
            if ($va->isselfassesstype == 1) {
                $va->ratingself = $va->selfassess;
            } else {
                $va->ratingself = 0;
                $va->selfassess = 0;
            }
            if ($va->ispeerassesstype == 1) {
                $va->ratingpeer = $va->peerassess;
            } else {
                $va->ratingpeer = 0;
                $va->peerassess = 0;
            }
            if ($va->isteacherassesstype == 1) {
                $va->ratingteacher = $va->teacherassess;
            } else {
                $va->ratingteacher = 0;
                $va->teacherassess = 0;
            }
            if ($va->isclassassesstype == 1) {
                $va->ratingclass = $va->classassess;
            } else {
                $va->ratingclass = 0;
                $va->classassess = 0;
            }
        }
        if ($va->numberofpeers > 0) {
            $va->usedpeers = $va->numberofpeers;
        }
        if ($va->gradingsimpledirect > 0) {
            $cm->completionusegrade = 1;
            $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
            $DB->update_record('course_modules', $cm);
            $va->gradepass_videoassessment = $va->gradingsimpledirect;
            $va->gradepass = $va->gradingsimpledirect;
        } else {
            // Ensure gradepass fields are properly initialized.
            // The gradepass field comes from Moodle's standard grading form field.
            // For itemnumber 0 (which maps to 'grading'), the field name is just 'gradepass'.
            // Moodle core processes gradepass AFTER update_instance in edit_module_post_actions(),
            // so we need to ensure it's set in $va (which is $moduleinfo) so Moodle core can read it.

            $gradepassvalue = null;
            $gradepassfound = false;

            // Priority 1: Check $_POST directly (most reliable source for form submissions).
            if (isset($_POST['gradepass']) && $_POST['gradepass'] !== '' && $_POST['gradepass'] !== null) {
                // Unformat_float is available from lib/moodlelib.php (always loaded).
                $unformatted = unformat_float($_POST['gradepass']);
                if ($unformatted !== false && $unformatted !== null) {
                    $gradepassvalue = (float)$unformatted;
                    $gradepassfound = true;
                }
            }

            // Priority 2: Check $va object (processed form data from form->get_data()).
            if (
                !$gradepassfound
                && property_exists($va, 'gradepass')
                && $va->gradepass !== ''
                && $va->gradepass !== null
                && (is_numeric($va->gradepass) || $va->gradepass === 0 || $va->gradepass === '0')
            ) {
                $gradepassvalue = (float)$va->gradepass;
                $gradepassfound = true;
            }

            // Priority 3: Check form object if available.
            if (!$gradepassfound && $form && method_exists($form, 'get_data')) {
                $formdata = $form->get_data();
                if (
                    $formdata
                    && property_exists($formdata, 'gradepass')
                    && $formdata->gradepass !== ''
                    && $formdata->gradepass !== null
                    && (
                        is_numeric($formdata->gradepass)
                        || $formdata->gradepass === 0
                        || $formdata->gradepass === '0'
                    )
                ) {
                    $gradepassvalue = (float)$formdata->gradepass;
                    $gradepassfound = true;
                }
            }

            // Set gradepass from form data if provided, otherwise keep existing value or default to 0.
            if ($gradepassfound && $gradepassvalue !== null) {
                // Convert to float and ensure it's a valid number.
                $gradepassvalue = (float)$gradepassvalue;
                if ($gradepassvalue < 0) {
                    $gradepassvalue = 0;
                }
                // CRITICAL: Set gradepass in $va ($moduleinfo) so Moodle core can read it.
                $va->gradepass_videoassessment = $gradepassvalue;
                $va->gradepass = $gradepassvalue;
            } else {
                // No gradepass provided in form - check if we should keep existing value or set to 0
                // For updates, if field exists but is empty, it means user cleared it, so set to 0.
                if (property_exists($va, 'gradepass') && ($va->gradepass === '' || $va->gradepass === null)) {
                    // User cleared the field, set to 0.
                    $va->gradepass_videoassessment = 0;
                    $va->gradepass = 0;
                } else {
                    // Get existing value from database.
                    $existing = $DB->get_record('videoassessment', ['id' => $va->id], 'gradepass_videoassessment, gradepass');
                    if ($existing && isset($existing->gradepass_videoassessment) && $existing->gradepass_videoassessment !== null) {
                        $va->gradepass_videoassessment = (float)$existing->gradepass_videoassessment;
                        $va->gradepass = (float)$existing->gradepass_videoassessment;
                    } else {
                        // No existing value, default to 0.
                        $va->gradepass_videoassessment = 0;
                        $va->gradepass = 0;
                    }
                }
            }
        }

        // Note: advancedgradingmethod_* fields are processed by Moodle core's edit_module_post_actions()
        // which calls set_active_method() on the grading manager. We should not clear these fields.
        // If a rubric definition exists but method is not set, ensure it's set to 'rubric'.
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $gradingman = get_grading_manager($cm->context, 'mod_videoassessment');
        $areas = $gradingman->get_available_areas();

        foreach ($areas as $areaname => $areatitle) {
            $formfield = 'advancedgradingmethod_' . $areaname;
            $gradingman->set_area($areaname);

            // If method is not set but a rubric definition exists, set it to 'rubric'.
            if (empty($va->$formfield)) {
                $controller = $gradingman->get_controller('rubric');
                if ($controller && $controller->is_form_defined()) {
                    // Rubric definition exists but method not set - set it to 'rubric'.
                    $va->$formfield = 'rubric';
                }
            }
        }
    } else {
        if ($va->ratingself > 0) {
            $va->selfassess = $va->ratingself;
            $va->isselfassesstype = 1;
        } else {
            $va->selfassess = 0;
            $va->isselfassesstype = 0;
        }
        if ($va->ratingpeer > 0) {
            $va->peerassess = $va->ratingpeer;
            $va->ispeerassesstype = 1;
        } else {
            $va->peerassess = 0;
            $va->ispeerassesstype = 0;
        }

        if ($va->ratingteacher > 0) {
            $va->teacherassess = $va->ratingteacher;
            $va->isteacherassesstype = 1;
        } else {
            $va->teacherassess = 0;
            $va->isteacherassesstype = 0;
        }
        if ($va->ratingclass > 0) {
            $va->classassess = $va->ratingclass;
            $va->isclassassesstype = 1;
        } else {
            $va->classassess = 0;
            $va->isclassassesstype = 0;
        }
        $va->numberofpeers = $va->usedpeers;
    }

    $oldva = $DB->get_record('videoassessment', ['id' => $va->id]);

    $DB->update_record('videoassessment', $va);
    videoassessment_update_calendar($va);
    if (
        $oldva->ratingteacher != $va->ratingteacher
        || $oldva->ratingself != $va->ratingself
        || $oldva->ratingpeer != $va->ratingpeer
    ) {
        require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

        $course = $DB->get_record('course', ['id' => $va->course], '*', MUST_EXIST);
        $vaobj = new mod_videoassessment\va(context_module::instance($cm->id), $cm, $course);
        $vaobj->regrade();
    }

    // Process peer assignments from the form.
    if (isset($va->peerassignments) && $va->peerassignments !== '' && $va->peerassignments !== '{}') {
        videoassessment_save_peer_assignments($va->id, $va->peerassignments);
    }

    // Check if "Save and create rubric" button was clicked.
    // Be EXTREMELY strict - only set preference if redirect_to_rubric is explicitly set to '1' or 1.
    // Do NOT check for submitbutton_rubric as that might be set incorrectly.
    // Only rely on the redirect_to_rubric hidden field which is set by JavaScript when the rubric button is clicked.
    $rubricbuttonclicked = false;

    // Check $_POST first (most reliable for form submissions).
    if (!empty($_POST['redirect_to_rubric']) && ($_POST['redirect_to_rubric'] == '1' || $_POST['redirect_to_rubric'] == 1)) {
        $rubricbuttonclicked = true;
    } else if (!empty($va->redirect_to_rubric) && ($va->redirect_to_rubric == '1' || $va->redirect_to_rubric == 1)) {
        $rubricbuttonclicked = true;
    }
    // Explicitly DO NOT check for submitbutton_rubric to avoid false positives.

    // IMPORTANT: If rubric button was NOT clicked, clear advanced grading method fields
    // to prevent Moodle core from redirecting to grading management page.
    // Moodle core redirects when advanced grading method is set but no form exists.
    if (!$rubricbuttonclicked) {
        // Clear all advancedgradingmethod_* fields from the data that will be processed by Moodle core.
        // This prevents unwanted redirects when user clicks "Save and display".
        foreach ($va as $key => $value) {
            if (strpos($key, 'advancedgradingmethod_') === 0) {
                unset($va->$key);
            }
        }
        // Also clear from $_POST to ensure Moodle core doesn't see them.
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'advancedgradingmethod_') === 0) {
                unset($_POST[$key]);
            }
        }
    }

    if ($rubricbuttonclicked) {
        // Use user preference with timestamp to avoid "session mutated after closed" error.
        // Store timestamp to ensure redirect only happens if preference is recent.
        $prefvalue = $va->id . ':' . time();
        set_user_preference('videoassessment_redirect_to_grading', $prefvalue);
    } else {
        // Explicitly clear any existing preference if rubric button was NOT clicked.
        // This prevents stale preferences from causing unwanted redirects.
        $existingpref = get_user_preferences('videoassessment_redirect_to_grading');
        if (!empty($existingpref)) {
            unset_user_preference('videoassessment_redirect_to_grading');
        }
    }

    return true;
}

/**
 * Save peer assignments from the form data.
 *
 * Processes the JSON-encoded peer assignments and updates the database.
 * Clears existing assignments and creates new ones based on form data.
 *
 * @param int $videoassessmentid Video assessment instance ID
 * @param string $peerassignmentsjson JSON-encoded peer assignments
 * @return void
 */
function videoassessment_save_peer_assignments($videoassessmentid, $peerassignmentsjson) {
    global $DB;

    $peerassignments = json_decode($peerassignmentsjson, true);
    if (empty($peerassignments) || !is_array($peerassignments)) {
        return;
    }

    // Delete existing peer assignments for this activity.
    $DB->delete_records('videoassessment_peers', ['videoassessment' => $videoassessmentid]);

    // Insert new peer assignments.
    foreach ($peerassignments as $userid => $peers) {
        if (!is_array($peers)) {
            continue;
        }
        foreach ($peers as $peerid) {
            $record = new stdClass();
            $record->videoassessment = $videoassessmentid;
            $record->userid = (int)$userid;
            $record->peerid = (int)$peerid;
            $DB->insert_record('videoassessment_peers', $record);
        }
    }
}

/**
 * Delete a video assessment instance and all associated data.
 *
 * Removes the video assessment activity and cleans up all related
 * database records including grades, videos, and peer assignments.
 *
 * @param int $id Video assessment instance ID
 * @return boolean True if deletion was successful
 * @throws moodle_exception If database deletion fails
 */
function videoassessment_delete_instance($id) {
    global $DB;

    $DB->delete_records('videoassessment', ['id' => $id]);
    $DB->delete_records('videoassessment_aggregation', ['videoassessment' => $id]);
    $DB->delete_records('videoassessment_grades', ['videoassessment' => $id]);
    $DB->delete_records('videoassessment_grade_items', ['videoassessment' => $id]);
    $DB->delete_records('videoassessment_peers', ['videoassessment' => $id]);
    $DB->delete_records('videoassessment_videos', ['videoassessment' => $id]);
    $DB->delete_records('videoassessment_video_assocs', ['videoassessment' => $id]);

    return true;
}

/**
 * Check which Moodle features are supported by video assessment module.
 *
 * Returns feature support flags for groups, grading, completion tracking,
 * and other Moodle core functionality.
 *
 * @param string $feature Feature name to check support for
 * @return boolean|null True if supported, false if not, null if unknown
 */
function videoassessment_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Get list of available grading areas for video assessment.
 *
 * Returns an array mapping grading area keys to their display names
 * for use in advanced grading configuration.
 *
 * @return array Associative array of grading area keys and names
 */
function videoassessment_grading_areas_list() {
    return [
        'beforeteacher' => get_string('teacher', 'videoassessment'),
        'beforetraining' => get_string('trainingpretest', 'videoassessment'),
        'beforeself' => get_string('self', 'videoassessment'),
        'beforepeer' => get_string('peer', 'videoassessment'),
        'beforeclass' => get_string('class', 'videoassessment'),
    ];
}

/**
 * Handle file serving for video assessment module files.
 *
 * Serves uploaded video files and other module assets with proper
 * security checks and capability validation.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param stdClass $context Context object
 * @param string $filearea File area identifier
 * @param array $args Additional file path arguments
 * @param bool $forcedownload Force download instead of inline display
 * @return void
 * @throws moodle_exception If user lacks required capabilities
 */
function mod_videoassessment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    // Handle editor fileareas (like submissioncomment) which have itemid in args.
    if ($filearea === 'submissioncomment') {
        // Students can view feedback comments if they have viewcomments capability.
        if (!has_capability('mod/videoassessment:viewcomments', $context)) {
            send_file_not_found();
        }

        $itemid = (int)array_shift($args);
        $filename = array_pop($args);
        // URL decode the filename in case it contains encoded characters (e.g., %20 for space).
        $filename = urldecode($filename);

        // Build filepath from remaining args (subdirectories).
        $filepath = '/';
        if (!empty($args)) {
            $filepath = '/' . implode('/', $args) . '/';
        }

        $fs = get_file_storage();

        // First try to get the file with the constructed filepath.
        $file = $fs->get_file($context->id, 'mod_videoassessment', $filearea, $itemid, $filepath, $filename);

        // If not found, search all files in this itemid for a matching filename.
        // This handles cases where files are stored in subdirectories or filepath doesn't match exactly.
        if (!$file || $file->is_directory()) {
            // Get all files in this filearea/itemid (excluding directories).
            $files = $fs->get_area_files($context->id, 'mod_videoassessment', $filearea, $itemid, false, 'id', false);
            foreach ($files as $areafile) {
                // Skip directories.
                if ($areafile->is_directory()) {
                    continue;
                }
                // Compare filenames - try exact match first, then case-insensitive.
                $storedfilename = $areafile->get_filename();
                if ($storedfilename === $filename || strcasecmp($storedfilename, $filename) === 0) {
                    $file = $areafile;
                    break;
                }
                // Also try URL-encoded version in case filename wasn't decoded properly.
                if ($storedfilename === urldecode($filename) || strcasecmp($storedfilename, urldecode($filename)) === 0) {
                    $file = $areafile;
                    break;
                }
            }
        }

        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }

        \core\session\manager::write_close(); // Unlock session during fileserving.
        send_stored_file($file, HOURSECS, 0, $forcedownload);
        return;
    }

    // Allow Self Assessment/Peer Assessment to view other people's files.
    if (!has_capability('mod/videoassessment:gradepeer', $context)) {
        send_file_not_found();
    }

    // For other fileareas, use the original method.
    $fullpath = "/{$context->id}/mod_videoassessment/$filearea/" . implode('/', $args);

    $fs = get_file_storage();
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    \core\session\manager::write_close(); // Unlock session during fileserving.
    send_stored_file($file, HOURSECS, 0, $forcedownload);
}

/**
 * Convert training video files to appropriate format.
 *
 * Processes uploaded training videos through bulk upload system
 * and converts them to web-compatible formats.
 *
 * @param stdClass $event Event object containing instance information
 * @param stdClass $va Video assessment instance data
 * @return void
 * @throws moodle_exception If video conversion fails
 */
function videoassessment_convert_video($event, $va) {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');

    if ($va->training && !empty($va->trainingvideo)) {
        $fs = get_file_storage();
        $upload = new \videoassessment_bulkupload($event->instanceid);

        $files = $fs->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $va->trainingvideo);

        if (!empty($files)) {
            foreach ($files as $file) {
                if ($file->get_filename() == '.') {
                    continue;
                }

                $upload->create_temp_dirs();
                $tmpname = $upload->get_temp_name($file->get_filename());
                $tmppath = $upload->get_tempdir() . '/upload/' . $tmpname;
                $file->copy_content_to($tmppath);

                $videoid = $upload->video_data_add($tmpname, $file->get_filename());

                $upload->convert($tmpname);

                $DB->execute(
                    "UPDATE {videoassessment} SET trainingvideoid = ?, trainingvideo = 0 WHERE id = ?",
                    [$videoid, $va->id]
                );
            }
        }
    }
}

/**
 * Check if video assessment has grades for specific grading areas.
 *
 * Verifies which grading types have been configured and have
 * associated grade items in the database.
 *
 * @param int $videoassessment Video assessment instance ID
 * @return array Associative array of grading area keys and boolean values
 */
function videoassessment_check_has_grade($videoassessment) {
    global $DB;

    $hasgrade = [];
    $gradetypes = videoassessment_grading_areas_list();
    foreach ($gradetypes as $key => $gradetype) {
        $sql = 'SELECT * from {videoassessment_grade_items} WHERE videoassessment=? AND type like ?';
        $params = [$videoassessment, $key];
        $hasgrade[$key] = $DB->record_exists_sql($sql, $params);
    }

    return $hasgrade;
}

/**
 * Get grading areas for a specific context.
 *
 * Retrieves all grading areas associated with a given context
 * for advanced grading configuration.
 *
 * @param int $contextid Context ID to get areas for
 * @return array Associative array of area IDs and names
 */
function videoassessment_get_areas($contextid) {
    global $DB;

    $areas = [];
    $sql = 'SELECT id, areaname FROM {grading_areas} WHERE contextid = ?';
    $params = [$contextid];

    if ($arealists = $DB->get_records_sql($sql, $params)) {
        foreach ($arealists as $area) {
            $areas[$area->id] = $area->areaname;
        }
    }

    return $areas;
}

/**
 * Get grading area name by its ID.
 *
 * Retrieves the display name of a specific grading area
 * for use in user interfaces.
 *
 * @param int $id Grading area ID
 * @return string Area name or empty string if not found
 */
function videoassessment_get_areaname_by_id($id) {
    global $DB;

    return $DB->get_field('grading_areas', 'areaname', ['id' => $id]);
}

/**
 * Determine if video assessment intro should be displayed.
 *
 * Checks timing and configuration settings to determine
 * whether the activity introduction should be visible to users.
 *
 * @param stdClass $va Video assessment instance data
 * @return boolean True if intro should be shown, false otherwise
 */
function videoassessment_show_intro($va) {
    if (
        $va->showdescription ||
        time() > $va->allowsubmissionsfromdate
    ) {
        return true;
    }
    return false;
}

/**
 * Update calendar events for video assessment activity.
 *
 * Creates or updates calendar events for due dates and grading
 * deadlines associated with the video assessment activity.
 *
 * @param stdClass $va Video assessment instance data
 * @return boolean True if calendar update was successful
 * @throws moodle_exception If calendar event creation fails
 */
function videoassessment_update_calendar($va) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/calendar/lib.php');

    // Special case for add_instance as the coursemodule has not been set yet.
    $instance = $va;

    // Start with creating the event.
    $event = new stdClass();
    $event->modulename = 'videoassessment';
    $event->courseid = $instance->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->instance = $instance->id;
    $event->type = CALENDAR_EVENT_TYPE_ACTION;

    // Convert the links to pluginfile. It is a bit hacky but at this stage the files
    // might not have been saved in the module area yet.
    $intro = $instance->intro;
    if ($draftid = file_get_submitted_draft_itemid('introeditor')) {
        $intro = file_rewrite_urls_to_pluginfile($intro, $draftid);
    }

    // We need to remove the links to files as the calendar is not ready
    // to support module events with file areas.
    $intro = strip_pluginfile_content($intro);
    if (videoassessment_show_intro($va)) {
        $event->description = [
            'text' => $intro,
            'format' => $instance->introformat,
        ];
    } else {
        $event->description = [
            'text' => '',
            'format' => $instance->introformat,
        ];
    }

    $eventtype = VIDEOASSESS_EVENT_TYPE_DUE;
    if ($instance->duedate) {
        $event->name = get_string('calendardue', 'videoassessment', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->duedate;
        $event->timesort = $instance->duedate;
        $select = "modulename = :modulename
                       AND instance = :instance
                       AND eventtype = :eventtype
                       AND groupid = 0
                       AND courseid <> 0";
        $params = ['modulename' => 'videoassessment', 'instance' => $instance->id, 'eventtype' => $eventtype];
        $event->id = $DB->get_field_select('event', 'id', $select, $params);

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', ['modulename' => 'videoassessment', 'instance' => $instance->id,
            'eventtype' => $eventtype]);
    }

    $eventtype = VIDEOASSESS_EVENT_TYPE_GRADINGDUE;
    if ($instance->gradingduedate) {
        $event->name = get_string('calendargradingdue', 'videoassessment', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->gradingduedate;
        $event->timesort = $instance->gradingduedate;
        $event->id = $DB->get_field('event', 'id', ['modulename' => 'videoassessment',
            'instance' => $instance->id, 'eventtype' => $event->eventtype]);

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', ['modulename' => 'videoassessment', 'instance' => $instance->id,
            'eventtype' => $eventtype]);
    }

    return true;
}

/**
 * Extend settings navigation for video assessment module.
 *
 * Adds grade management interface elements to the settings navigation
 * block for advanced grading configuration.
 *
 * @param settings_navigation $settings Settings navigation object
 * @param navigation_node $videoassessmentnode Video assessment navigation node
 * @return void
 */
/**
 * Automatically duplicate teacher rubric to peer, self, and class areas if needed.
 *
 * This ensures that when a rubric is created for the teacher area,
 * it's automatically available for peer, self, and class assessors.
 *
 * @param int $contextid The context ID of the video assessment.
 * @param bool $forceupdate When true, regenerate the duplicates even when targets already exist.
 * @return void
 */
function videoassessment_auto_duplicate_rubric($contextid, $forceupdate = false) {
    global $DB, $CFG, $_SERVER;

    require_once($CFG->dirroot . '/grade/grading/lib.php');
    require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');

    $scriptpath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $requesturi = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    // Check for deleteform parameter (deletion in progress).
    $deleteform = optional_param('deleteform', null, PARAM_INT);
    if (!empty($deleteform) || strpos($requesturi, 'deleteform=') !== false) {
        return; // Don't auto-duplicate during deletion.
    }

    // If forceupdate is true, we're being called after template selection - allow execution.
    // Otherwise, skip auto-duplication if we're on the grading management page during other operations.
    if (!$forceupdate) {
        // Skip auto-duplication if we're on the grading management page.
        // This includes deletion, selection, and any other rubric management operations.
        if (
            strpos($scriptpath, '/grade/grading/manage.php') !== false ||
            strpos($requesturi, '/grade/grading/manage.php') !== false ||
            strpos($scriptpath, '/grade/grading/pick.php') !== false ||
            strpos($requesturi, '/grade/grading/pick.php') !== false
        ) {
            // We're on a grading management page - skip auto-duplication to avoid interference.
            return;
        }

        // Check for other grading management actions that might interfere.
        if (strpos($requesturi, 'action=') !== false) {
            // Check if it's a grading-related action.
            $action = optional_param('action', null, PARAM_ALPHA);
            if (in_array($action, ['delete', 'edit', 'copy', 'duplicate', 'pick'])) {
                return; // Skip auto-duplication during grading management actions.
            }
        }
    }

    // Get all grading areas for this context.
    $allareas = $DB->get_records('grading_areas', ['contextid' => $contextid, 'component' => 'mod_videoassessment']);

    if (empty($allareas)) {
        return; // No grading areas found.
    }

    // Find any area that has a rubric definition (prefer teacher, but use any that exists).
    $sourcearea = null;
    $sourcedefinition = null;

    // When forceupdate is true (template selection), find the most recently updated rubric.
    // Otherwise, prefer teacher area.
    if ($forceupdate) {
        // Find the most recently updated rubric definition (likely the one just selected).
        $recentdefinition = $DB->get_record_sql(
            "SELECT gd.*
             FROM {grading_definitions} gd
             JOIN {grading_areas} ga ON ga.id = gd.areaid
             WHERE ga.contextid = ? AND ga.component = 'mod_videoassessment'
               AND gd.method = 'rubric' AND gd.status = ?
             ORDER BY gd.timemodified DESC
             LIMIT 1",
            [$contextid, gradingform_controller::DEFINITION_STATUS_READY]
        );
        if ($recentdefinition) {
            $sourcearea = $DB->get_record('grading_areas', ['id' => $recentdefinition->areaid]);
            $sourcedefinition = $recentdefinition;
        }
    }

    // If not found yet, try to find teacher area with rubric.
    if (!$sourcearea) {
        foreach ($allareas as $area) {
            if ($area->areaname == 'beforeteacher') {
                $definition = $DB->get_record('grading_definitions', [
                    'areaid' => $area->id,
                    'method' => 'rubric',
                ]);
                if ($definition && $definition->status == gradingform_controller::DEFINITION_STATUS_READY) {
                    $sourcearea = $area;
                    $sourcedefinition = $definition;
                    break;
                }
            }
        }
    }

    // If no teacher rubric found, check any other area that has a rubric.
    if (!$sourcearea) {
        foreach ($allareas as $area) {
            $definition = $DB->get_record('grading_definitions', [
                'areaid' => $area->id,
                'method' => 'rubric',
            ]);
            if ($definition && $definition->status == gradingform_controller::DEFINITION_STATUS_READY) {
                $sourcearea = $area;
                $sourcedefinition = $definition;
                break; // Use the first one found.
            }
        }
    }

    if (!$sourcearea || !$sourcedefinition) {
        return; // No rubric found in any area - don't try to duplicate.
    }

    // Additional safety check: verify the source definition still exists and has criteria.
    // This prevents issues if the definition was deleted between the check and duplication.
    $criteriaexists = $DB->record_exists('gradingform_rubric_criteria', ['definitionid' => $sourcedefinition->id]);
    if (!$criteriaexists) {
        return; // Source definition has no criteria - don't duplicate.
    }

    // Get video assessment instance to check which areas are enabled.
    $context = context::instance_by_id($contextid);
    if ($context->contextlevel != CONTEXT_MODULE) {
        return; // Not a module context.
    }

    $cm = get_coursemodule_from_id('videoassessment', $context->instanceid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return; // Cannot find course module.
    }

    $va = $DB->get_record('videoassessment', ['id' => $cm->instance]);
    if (!$va) {
        return;
    }

    // Determine which areas need rubrics based on settings.
    $areastoduplicate = [];

    // When forceupdate is true (template selection), always duplicate to class and teacher areas.
    $forceclassandteacher = false;
    if ($forceupdate) {
        // Check if class or teacher areas are enabled.
        $forceclassandteacher = (!empty($va->ratingclass) || !empty($va->ratingteacher));
    }

    foreach ($allareas as $area) {
        if ($area->id == $sourcearea->id) {
            continue; // Skip the source area (wherever the rubric came from).
        }

        // Check if this area should have a rubric based on settings.
        $needsrubric = false;
        if ($area->areaname == 'beforeteacher' && !empty($va->ratingteacher)) {
            $needsrubric = true;
        } else if ($area->areaname == 'beforepeer' && !empty($va->ratingpeer)) {
            $needsrubric = true;
        } else if ($area->areaname == 'beforeself' && !empty($va->ratingself)) {
            $needsrubric = true;
        } else if ($area->areaname == 'beforeclass' && !empty($va->ratingclass)) {
            $needsrubric = true;
        }

        // When template is selected, always duplicate to class and teacher areas.
        if ($forceclassandteacher && ($area->areaname == 'beforeclass' || $area->areaname == 'beforeteacher')) {
            $needsrubric = true;
        }

        if ($needsrubric) {
            // Check if the target area already has a rubric definition.
            $targetdefinition = $DB->get_record('grading_definitions', [
                'areaid' => $area->id,
                'method' => 'rubric',
            ]);

            // If forceupdate is true (template selection), always update class and teacher areas.
            // Also update if teacher rubric is newer than existing self/peer rubrics.
            $shouldupdate = false;
            if ($forceupdate && ($area->areaname == 'beforeclass' || $area->areaname == 'beforeteacher')) {
                // Force update class and teacher areas when template is selected.
                $shouldupdate = true;
            } else if (
                $forceupdate && $sourcearea->areaname == 'beforeteacher' &&
                ($area->areaname == 'beforeself' || $area->areaname == 'beforepeer')
            ) {
                // Force update self and peer areas when teacher template is selected.
                $shouldupdate = true;
            } else if (
                $sourcearea->areaname == 'beforeteacher' &&
                       ($area->areaname == 'beforeself' || $area->areaname == 'beforepeer') &&
                       $targetdefinition && $targetdefinition->status == gradingform_controller::DEFINITION_STATUS_READY
            ) {
                // Check if teacher rubric is newer than self/peer rubric.
                if ($sourcedefinition->timemodified > $targetdefinition->timemodified) {
                    $shouldupdate = true;
                }
            } else if (!$targetdefinition || $targetdefinition->status != gradingform_controller::DEFINITION_STATUS_READY) {
                $shouldupdate = true;
            }

            if ($shouldupdate) {
                $areastoduplicate[] = $area;
            }
        }
    }

    if (empty($areastoduplicate)) {
        return; // All areas already have rubrics.
    }

    // Duplicate the rubric to each area that needs it.
    $transaction = $DB->start_delegated_transaction();

    try {
        foreach ($areastoduplicate as $targetarea) {
            // Set the active method to 'rubric' for this area BEFORE creating the definition.
            // Use context/component/area approach to get the manager.
            $targetmanager = get_grading_manager($context, 'mod_videoassessment', $targetarea->areaname);
            $targetmanager->set_active_method('rubric');

            // Check if this area already has a rubric definition - if so, delete it first.
            $existingdefinition = $DB->get_record('grading_definitions', [
                'areaid' => $targetarea->id,
                'method' => 'rubric',
            ]);

            if ($existingdefinition) {
                // Delete existing criteria and levels first.
                $existingcriteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $existingdefinition->id]);
                foreach ($existingcriteria as $criterion) {
                    $DB->delete_records('gradingform_rubric_levels', ['criterionid' => $criterion->id]);
                }
                $DB->delete_records('gradingform_rubric_criteria', ['definitionid' => $existingdefinition->id]);
                $DB->delete_records('grading_definitions', ['id' => $existingdefinition->id]);
            }

            // Clone the definition from source area.
            $newdefinition = clone $sourcedefinition;
            unset($newdefinition->id);
            $newdefinition->areaid = $targetarea->id;
            $newdefinition->timecreated = time();
            $newdefinition->timemodified = time();

            $newdefinitionid = $DB->insert_record('grading_definitions', $newdefinition);

            // Copy criteria from source area.
            $criteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $sourcedefinition->id], 'sortorder ASC');
            foreach ($criteria as $criterion) {
                // Create new criterion record with only the necessary fields.
                $newcriterion = new \stdClass();
                $newcriterion->definitionid = $newdefinitionid;
                $newcriterion->sortorder = $criterion->sortorder;
                $newcriterion->description = $criterion->description;
                $newcriterion->descriptionformat = $criterion->descriptionformat;
                $newcriterionid = $DB->insert_record('gradingform_rubric_criteria', $newcriterion);

                // Copy levels for this criterion.
                $levels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $criterion->id], 'score ASC');
                foreach ($levels as $level) {
                    // Create new level record with only the necessary fields.
                    $newlevel = new \stdClass();
                    $newlevel->criterionid = $newcriterionid;
                    $newlevel->score = $level->score;
                    $newlevel->definition = $level->definition;
                    $newlevel->definitionformat = $level->definitionformat;
                    $DB->insert_record('gradingform_rubric_levels', $newlevel);
                }
            }
        }

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        debugging('Failed to auto-duplicate rubric: ' . $e->getMessage(), DEBUG_NORMAL);
    }
}

/**
 * Add Video Assessment-specific entries to the activity settings navigation.
 *
 * Adds rubric / grade navigation items so the activity settings block
 * exposes them next to Moodle's standard module entries.
 *
 * @param settings_navigation $settings The settings_navigation root node.
 * @param navigation_node $videoassessmentnode Module-specific navigation node.
 * @return void
 */
function videoassessment_extend_settings_navigation($settings, navigation_node $videoassessmentnode) {
    global $PAGE, $DB;
    $areaname = '';
    if (optional_param('areaid', null, PARAM_INT)) {
        $areaname = videoassessment_get_areaname_by_id(required_param('areaid', PARAM_INT));
    }
    $hasgrade = videoassessment_check_has_grade($PAGE->cm->instance);
    $areas = videoassessment_get_areas($PAGE->cm->context->id);

    // Auto-duplicate rubric ONLY when template is selected and confirmed.
    // Check if we're on manage.php after template selection (not during deletion or other operations).
    $deleteform = optional_param('deleteform', null, PARAM_INT);
    $shareform = optional_param('shareform', null, PARAM_INT);
    $setmethod = optional_param('setmethod', null, PARAM_ALPHANUMEXT);

    // Only run if no management actions are in progress and we're on manage.php.
    if (
        empty($deleteform) && empty($shareform) && empty($setmethod) &&
        strpos($PAGE->url->get_path(), '/grade/grading/manage.php') !== false
    ) {
        $areaid = optional_param('areaid', null, PARAM_INT);
        if ($areaid) {
            $area = $DB->get_record('grading_areas', ['id' => $areaid]);

            // Trigger auto-duplication for any area when a template is selected.
            if ($area && $area->component == 'mod_videoassessment') {
                // Check if definition was just updated (within last 10 seconds) to detect template selection.
                // This is a narrow window to catch only immediate template selections, not other updates.
                $definition = $DB->get_record('grading_definitions', [
                    'areaid' => $areaid,
                    'method' => 'rubric',
                ]);
                if ($definition) {
                    $recentlyupdated = (time() - $definition->timemodified) < 10;

                    // Only duplicate if definition was very recently updated (likely from template selection).
                    // This prevents interference with deletion or other operations that happen later.
                    if ($recentlyupdated) {
                        videoassessment_auto_duplicate_rubric($PAGE->cm->context->id, true);
                    }
                }
            }
        }
    }

    // Build the HTML but don't echo it directly (which would break DOCTYPE).
    // Instead, add it to the page footer via JavaScript to ensure it's after DOCTYPE.
    $checkgradehtml = "<div class='check-has-grade hidden " . ($areaname ? $areaname : '') . "'>";
    $checkgradehtml .= '<input name="videoassessmentid" text="' . $PAGE->cm->instance . '">';
    if ($hasgrade) {
        foreach ($hasgrade as $key => $grade) {
            if ($areas) {
                foreach ($areas as $k => $area) {
                    if ($area == $key) {
                        $checkgradehtml .= "<input name='" . s($key) . "' value='" . s($grade) . "' text='" . s($k) . "'>";
                    }
                }
            } else {
                $checkgradehtml .= "<input name='" . s($key) . "' value='" . s($grade) . "'>";
            }
        }
    }
    $checkgradehtml .= "</div>";

    // Add to page footer via JavaScript to ensure it's output after DOCTYPE.
    $PAGE->requires->js_amd_inline("
        require(['jquery'], function(\$) {
            \$('body').append(" . json_encode($checkgradehtml) . ");
        });
    ");

    $PAGE->requires->jquery();
    $PAGE->requires->js_call_amd('mod_videoassessment/grademanage', 'init_grademanage', []);

    // Always check if we're on the grading management page and change heading if needed.
    // This works even if this function is called from other contexts.
    $PAGE->requires->js_call_amd('mod_videoassessment/grading_heading', 'init');
}

/**
 * Returns a map of video assessment actions to FontAwesome icon classes.
 *
 * Provides icon mappings for various video assessment interface elements
 * to maintain consistent visual design.
 *
 * @return array Action to icon class mapping
 */
function mod_videoassessment_get_fontawesome_icon_map() {
    return [
        'mod_book:chapter' => 'fa-bookmark-o',
        'mod_book:nav_prev' => 'fa-arrow-left',
        'mod_book:nav_sep' => 'fa-minus',
        'mod_book:add' => 'fa-plus',
        'mod_book:nav_next' => 'fa-arrow-right',
        'mod_book:nav_exit' => 'fa-arrow-up',
    ];
}

/**
 * Add page requirements for videoassessment module.
 *
 * This function is called for course pages and allows us to inject
 * JavaScript that checks for pending grading redirects.
 *
 * @param cm_info $cm Course module info object
 * @return void
 */
function videoassessment_cm_info_view(cm_info $cm) {
    global $PAGE, $CFG;

    // Don't add redirect check if we're on a grading page - prevent any redirect logic from running.
    $currentpath = $PAGE->url->get_path();
    if (strpos($currentpath, '/grade/grading/') !== false) {
        // We're on a grading page - don't add any redirect check JavaScript.
        return;
    }

    // Check if there's a pending redirect to grading page.
    // This handles the case where "Save and create rubric" was clicked.
    static $redirectchecked = false;
    if (!$redirectchecked) {
        $redirectchecked = true;

        // Add inline JavaScript to check sessionStorage and redirect if needed.
        // Uses a unique token to ensure the redirect only happens once.
        $checkurl = $CFG->wwwroot . '/mod/videoassessment/check_grading_redirect.php';
        $inlinejs = "
            (function() {
                // Don't redirect if we're already on the grading management page or any grading-related page.
                var currentUrl = window.location.href;
                if (currentUrl.indexOf('/grade/grading/') !== -1 ||
                    currentUrl.indexOf('/grade/grading/form/') !== -1 ||
                    currentUrl.indexOf('/grade/grading/pick.php') !== -1 ||
                    currentUrl.indexOf('/grade/grading/edit.php') !== -1) {
                    // Clear redirect flag when already on grading page.
                    sessionStorage.removeItem('videoassessment_check_grading_redirect');
                    sessionStorage.removeItem('videoassessment_processed_tokens');
                    return;
                }

                // Only proceed if we're on the course page or activity view page.
                if (currentUrl.indexOf('/course/view.php') === -1 &&
                    currentUrl.indexOf('/mod/videoassessment/view.php') === -1) {
                    return;
                }

                var redirectData = sessionStorage.getItem('videoassessment_check_grading_redirect');

                // Only proceed if we have the sessionStorage flag.
                if (!redirectData) {
                    return;
                }

                // Parse the data: 'timestamp:token'.
                var parts = redirectData.split(':');
                var storedTime = parseInt(parts[0], 10);
                var token = parts[1] || '';

                // Check if this token was already processed.
                var processedTokens = JSON.parse(sessionStorage.getItem('videoassessment_processed_tokens') || '[]');
                if (processedTokens.indexOf(token) !== -1) {
                    // Already processed this redirect, clean up and exit.
                    sessionStorage.removeItem('videoassessment_check_grading_redirect');
                    return;
                }

                // Remove the redirect flag immediately to prevent re-triggering.
                sessionStorage.removeItem('videoassessment_check_grading_redirect');

                var now = Date.now();

                // Only proceed if the redirect was set less than 2 seconds ago.
                // Very short time window to prevent redirects when navigating away.
                if (now - storedTime > 2000) {
                    return;
                }

                // Mark this token as processed immediately.
                processedTokens.push(token);
                // Keep only last 10 tokens to prevent storage bloat.
                if (processedTokens.length > 10) {
                    processedTokens = processedTokens.slice(-10);
                }
                sessionStorage.setItem('videoassessment_processed_tokens', JSON.stringify(processedTokens));

                // Check for redirect via AJAX.
                fetch('{$checkurl}', {credentials: 'same-origin'})
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.redirect && data.url) {
                            window.location.replace(data.url);
                        }
                    })
                    .catch(function(error) {
                        // Silently fail - don't show errors.
                    });
            })();
        ";
        $PAGE->requires->js_init_code($inlinejs, false);
    }
}


/**
 * Get association (userid, timing) from a stored video file.
 *
 * Extracts user ID and timing information from video file path
 * structure for proper file organization and access control.
 *
 * @param stored_file $file Stored file object to analyze
 * @return array|false Array with [userid, timing] if found, false otherwise
 */
function videoassessment_get_assoc(stored_file $file) {
    $path = trim($file->get_filepath(), '/');
    $parts = explode('/', $path);

    if (count($parts) >= 2) {
        $userid = (int)$parts[0];
        $timing = $parts[1]; // 'Before' or 'after'.

        if ($userid > 0 && in_array($timing, ['before', 'after'])) {
            return [$userid, $timing];
        }
    }

    return false;
}
