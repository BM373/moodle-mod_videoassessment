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
 * This file replaces the legacy STATEMENTS section in:
 *
 * db/install.xml,
 * lib.php/modulename_install()
 * post installation hook and partially defaults.php
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

/**
 * Create default rubric template for video assessment.
 *
 * Creates a shared rubric template in the system context that can be used
 * as a starting point for video assessments.
 *
 * @return void
 */
function create_default_rubric_template() {
    global $DB, $USER, $CFG;

    // Ensure we have a valid user (required for rubric creation).
    if (empty($USER->id)) {
        $admin = get_admin();
        if ($admin) {
            $USER = $admin;
        } else {
            // Can't create rubric without a user.
            return;
        }
    }

    // Check if rubric tables exist.
    if (!$DB->get_manager()->table_exists('gradingform_rubric_criteria')) {
        return; // Rubric tables don't exist yet, skip template creation.
    }

    try {
        // Check if template already exists by searching for it.
        // Shared templates are in system context with component 'core_grading'.
        $systemcontext = \context_system::instance();
        $existingareas = $DB->get_records_sql(
            "SELECT ga.id, gd.id as definitionid
             FROM {grading_areas} ga
             JOIN {grading_definitions} gd ON gd.areaid = ga.id
             WHERE ga.contextid = ? 
             AND ga.component = 'core_grading'
             AND gd.method = 'rubric'
             AND gd.name = ?",
            [$systemcontext->id, get_string('defaultrubrictemplate', 'videoassessment')]
        );

        if (!empty($existingareas)) {
            return; // Template already exists.
        }

        // Create a shared grading area for the template.
        // Shared templates use component 'core_grading' and are in system context.
        $manager = new \grading_manager();
        $areaid = $manager->create_shared_area('rubric');

        // Get grading manager and controller for the new area.
        $manager = get_grading_manager($areaid);
        $controller = $manager->get_controller('rubric');

        // Ensure we have a user context (required for rubric creation).
        if (empty($USER->id)) {
            // Use admin user if no user is set.
            $admin = get_admin();
            if ($admin) {
                $USER = $admin;
            }
        }

        // Create a new definition structure from scratch.
        $definition = new \stdClass();
        $definition->name = 'Quick-start rubric for general performances (modifiable)';
        $definition->description_editor = [
            'text' => get_string('defaultrubrictemplatedesc', 'videoassessment'),
            'format' => FORMAT_HTML,
            'itemid' => file_get_unused_draft_itemid()
        ];

        // Define criteria and levels with proper NEWID structure.
        // Level IDs must match pattern /^NEWID\d+$/ (e.g., NEWID1, NEWID2, etc.)
        // Each criterion gets its own set of NEWID level keys (they're scoped per criterion).
        $criteria = [];
        $sortorder = 1;

        // Criterion 1: Interesting, engaging content
        $criteria['NEWID1'] = [
            'sortorder' => $sortorder++,
            'description' => 'Interesting, engaging content',
            'descriptionformat' => FORMAT_MOODLE,
            'levels' => [
                'NEWID1' => ['score' => 0, 'definition' => 'Not interesting, or confusing', 'definitionformat' => FORMAT_MOODLE],
                'NEWID2' => ['score' => 5, 'definition' => 'Not very interesting', 'definitionformat' => FORMAT_MOODLE],
                'NEWID3' => ['score' => 10, 'definition' => 'A little interesting', 'definitionformat' => FORMAT_MOODLE],
                'NEWID4' => ['score' => 15, 'definition' => 'Interesting', 'definitionformat' => FORMAT_MOODLE],
                'NEWID5' => ['score' => 20, 'definition' => 'Very interesting', 'definitionformat' => FORMAT_MOODLE],
            ]
        ];

        // Criterion 2: Good body language, facial expression, eye contact
        $criteria['NEWID2'] = [
            'sortorder' => $sortorder++,
            'description' => 'Good body language, facial expression, eye contact',
            'descriptionformat' => FORMAT_MOODLE,
            'levels' => [
                'NEWID1' => ['score' => 0, 'definition' => 'Very poor', 'definitionformat' => FORMAT_MOODLE],
                'NEWID2' => ['score' => 5, 'definition' => 'Poor', 'definitionformat' => FORMAT_MOODLE],
                'NEWID3' => ['score' => 10, 'definition' => 'Good', 'definitionformat' => FORMAT_MOODLE],
                'NEWID4' => ['score' => 15, 'definition' => 'Very good', 'definitionformat' => FORMAT_MOODLE],
                'NEWID5' => ['score' => 20, 'definition' => 'Excellent', 'definitionformat' => FORMAT_MOODLE],
            ]
        ];

        // Criterion 3: Clear voice with stress/intonation
        $criteria['NEWID3'] = [
            'sortorder' => $sortorder++,
            'description' => 'Clear voice with stress/intonation',
            'descriptionformat' => FORMAT_MOODLE,
            'levels' => [
                'NEWID1' => ['score' => 0, 'definition' => 'Cannot hear', 'definitionformat' => FORMAT_MOODLE],
                'NEWID2' => ['score' => 5, 'definition' => 'Can hear a little', 'definitionformat' => FORMAT_MOODLE],
                'NEWID3' => ['score' => 10, 'definition' => 'Good voice', 'definitionformat' => FORMAT_MOODLE],
                'NEWID4' => ['score' => 15, 'definition' => 'Very good voice with stress', 'definitionformat' => FORMAT_MOODLE],
                'NEWID5' => ['score' => 20, 'definition' => 'Excellent voice with stress, intonation', 'definitionformat' => FORMAT_MOODLE],
            ]
        ];

        // Criterion 4: Easy-to-understand language
        $criteria['NEWID4'] = [
            'sortorder' => $sortorder++,
            'description' => 'Easy-to-understand language',
            'descriptionformat' => FORMAT_MOODLE,
            'levels' => [
                'NEWID1' => ['score' => 0, 'definition' => 'Very difficult to understand', 'definitionformat' => FORMAT_MOODLE],
                'NEWID2' => ['score' => 5, 'definition' => 'Difficult to understand', 'definitionformat' => FORMAT_MOODLE],
                'NEWID3' => ['score' => 10, 'definition' => 'A little understandable', 'definitionformat' => FORMAT_MOODLE],
                'NEWID4' => ['score' => 15, 'definition' => 'Easy to understand', 'definitionformat' => FORMAT_MOODLE],
                'NEWID5' => ['score' => 20, 'definition' => 'Very easy to understand', 'definitionformat' => FORMAT_MOODLE],
            ]
        ];

        // Criterion 5: Strong introduction, transitions, conclusions
        $criteria['NEWID5'] = [
            'sortorder' => $sortorder++,
            'description' => 'Strong introduction, transitions, conclusions',
            'descriptionformat' => FORMAT_MOODLE,
            'levels' => [
                'NEWID1' => ['score' => 0, 'definition' => 'No structure', 'definitionformat' => FORMAT_MOODLE],
                'NEWID2' => ['score' => 5, 'definition' => 'Missing an introduction or conclusion', 'definitionformat' => FORMAT_MOODLE],
                'NEWID3' => ['score' => 10, 'definition' => 'Missing transition words', 'definitionformat' => FORMAT_MOODLE],
                'NEWID4' => ['score' => 15, 'definition' => 'Good introduction, transitions, conclusion', 'definitionformat' => FORMAT_MOODLE],
                'NEWID5' => ['score' => 20, 'definition' => 'Very strong introduction, transitions, conclusion', 'definitionformat' => FORMAT_MOODLE],
            ]
        ];

        // Build the rubric definition structure.
        $definition->rubric = [
            'criteria' => $criteria,
            'options' => [
                'sortlevelsasc' => 1,
                'lockzeropoints' => 1,
                'showdescriptionteacher' => 1,
                'showdescriptionstudent' => 1,
                'showscoreteacher' => 1,
                'showscorestudent' => 1,
                'enableremarks' => 1,
                'showremarksstudent' => 1,
            ]
        ];
        $definition->saverubric = 'Save rubric and make it ready';
        $definition->status = \gradingform_controller::DEFINITION_STATUS_READY;

        // Update the controller with the definition.
        // This will call update_or_check_rubric internally.
        // Suppress warnings about undefined array keys as this is a known Moodle core issue
        // when creating new definitions with no existing criteria.
        $olderrorlevel = error_reporting(E_ALL & ~E_WARNING);
        try {
            $controller->update_definition($definition);
        } finally {
            error_reporting($olderrorlevel);
        }
    } catch (Exception $e) {
        debugging('Failed to create default rubric template: ' . $e->getMessage(), DEBUG_NORMAL);
    }
}

/**
 * Install function for video assessment module.
 *
 * This function is called when the module is installed.
 * It checks if the ffmpeg command exists and displays a notification.
 *
 * @return void
 */
function xmldb_videoassessment_install() {
    global $OUTPUT;

    ignore_user_abort(true);
    set_time_limit(0);

    $ffmpegpath = videoassessment_get_ffmpeg_command();
    if ($ffmpegpath) {
        // Set the config values to be reused in settings.
        set_config(
            'ffmpegcommand',
            $ffmpegpath . ' -i {INPUT} {OUTPUT}',
            'videoassessment'
        );
        set_config(
            'ffmpegthumbnailcommand',
            $ffmpegpath . ' -i {INPUT} -vframes 1 -s 137x91 -ss 1 {OUTPUT}',
            'videoassessment'
        );

        $ffmpegversioninfo = videoassessment_get_ffmpeg_version();
        if ($ffmpegversioninfo) {
            echo $OUTPUT->notification(
                get_string('installsuccessffmpeg', 'videoassessment', $ffmpegversioninfo),
                'notifysuccess'
            );
        } else {
            echo $OUTPUT->notification(get_string('installerrorffmpegversionnotfound', 'videoassessment'), 'notifyproblem');
        }
    } else {
        echo $OUTPUT->notification(get_string('installerrorffmpegdoesnotexist', 'videoassessment'), 'notifyproblem');
    }

    putenv('PATH=');
    putenv('LD_LIBRARY_PATH=');
    putenv('DYLD_LIBRARY_PATH=');
}
