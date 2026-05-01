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
 * Allows viewing/use of a particular instance of videoassessment.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoassessment\va;

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/videoassessment/locallib.php');

if (optional_param('ajax', null, PARAM_ALPHANUM)) {
    require_login();
    $action = optional_param('action', null, PARAM_ALPHANUM);

    if ($action == 'getcoursesbycategory') {
        $catid = optional_param('catid', null, PARAM_INT);
        $currentcourseid = optional_param('currentcourseid', 0, PARAM_INT);
        $courseopts = [];

        if (!empty($catid)) {
            $context = context_coursecat::instance($catid);
            require_capability('mod/videoassessment:fetchcourses', $context);

            $courses = va::get_courses_managed_by($USER->id, $catid);
            array_walk($courses, function (\stdClass $a) use (&$courseopts) {
                $courseopts[$a->id] = $a->fullname;
            });

            $courseoptions = [];
            foreach ($courseopts as $courseid => $coursename) {
                $courseoptions[] = [
                    'id' => $courseid,
                    'fullname' => $coursename,
                    'selected' => ($currentcourseid == $courseid),
                ];
            }
        }

        // Build the <option> list directly. A Mustache partial cannot be used
        // here because templates emitting bare <option> elements fail Moodle's
        // mustache lint (HTML5 requires <option> to be inside <select>); the
        // resulting HTML is injected into an existing <select> by the caller.
        $html = html_writer::tag('option', '(' . get_string('new') . ')', ['value' => '0']);
        foreach ($courseoptions as $option) {
            $attributes = ['value' => $option['id']];
            if (!empty($option['selected'])) {
                $attributes['selected'] = 'selected';
            }
            $html .= html_writer::tag('option', s($option['fullname']), $attributes);
        }
        echo json_encode([
            'html' => $html,
        ]);
        die;
    } else if ($action == 'getsectionsbycourse') {
        $courseid = optional_param('courseid', null, PARAM_INT);
        $currentsectionid = optional_param('currentsectionid', null, PARAM_INT);
        $sectionopts = [];

        if (!empty($courseid)) {
            $context = context_course::instance($courseid);
            require_capability('mod/videoassessment:fetchsections', $context);

            $modinfo = get_fast_modinfo($courseid);
            $sections = $modinfo->get_section_info_all();

            if (!empty($sections)) {
                foreach ($sections as $key => $section) {
                    $sectionopts[] = [
                        'id'       => $section->id,
                        'name'     => get_section_name($courseid, $section->section),
                        'selected' => ($currentsectionid == $section->id),
                    ];
                }
            }
        }

        // Same rationale as the course_options branch above: build the
        // <option> list inline rather than via a Mustache partial that would
        // fail HTML5 lint when emitting bare <option> elements.
        $html = '';
        foreach ($sectionopts as $option) {
            $attributes = ['value' => $option['id']];
            if (!empty($option['selected'])) {
                $attributes['selected'] = 'selected';
            }
            $html .= html_writer::tag('option', s($option['name']), $attributes);
        }

        echo json_encode([
            'html' => $html,
        ]);
        die;
    } else if ($action == "getallcomments") {
        global $OUTPUT, $DB, $PAGE;
        $cmid = optional_param('cmid', null, PARAM_INT);
        $userid = optional_param('userid', null, PARAM_INT);
        $timing = optional_param('timing', null, PARAM_RAW);
        $id = optional_param('id', null, PARAM_RAW);
        $context = context_module::instance($cmid);
        require_capability('mod/videoassessment:viewcomments', $context);
        $va = $DB->get_record('videoassessment', ['id' => $cmid]);

        $comments = [];
        $gradertypes = ['self', 'peer', 'teacher'];

        foreach ($gradertypes as $gradertype) {
            $gradingarea = $timing . $gradertype;
            $grades = va::get_grade_items_by_id($gradingarea, $userid, $va->id);
            foreach ($grades as $item => $gradeitem) {
                if ($gradeitem->id == $id) {
                    // Format the comment to convert @@PLUGINFILE@@ placeholders to actual URLs.
                    $commentformat = isset($gradeitem->submissioncommentformat) ? $gradeitem->submissioncommentformat : FORMAT_HTML;
                    // First rewrite @@PLUGINFILE@@ placeholders to actual URLs.
                    // Use gradeid (from videoassessment_grades table) not gradeitem->id (from grade_items table).
                    $gradeid = isset($gradeitem->gradeid) ? $gradeitem->gradeid : $gradeitem->id;
                    $commenttext = file_rewrite_pluginfile_urls(
                        $gradeitem->submissioncomment,
                        'pluginfile.php',
                        $context->id,
                        'mod_videoassessment',
                        'submissioncomment',
                        $gradeid
                    );
                    // Then format the text.
                    $formattedcomment = format_text($commenttext, $commentformat, [
                        'context' => $context,
                    ]);
                    $comment = '<label class="mobile-submissioncomment">' . $formattedcomment . '</label>';
                    if ($gradertype == "peer") {
                        $label = '<span class="blue box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    } else if ($gradertype == "teacher") {
                        $label = '<span class="green box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    } else if ($gradertype == "self") {
                        $label = '<span class="red box">' . get_string($gradertype, 'videoassessment') . '</span>';
                    }
                    $o .= $OUTPUT->heading($label . $comment);
                }
            }

        }
        $o .= \html_writer::end_tag('div');

        echo json_encode([
            'html' => $o,
        ]);
        die;
    }
}
global $DB, $PAGE;
$id = required_param('id', PARAM_INT);
$url = new moodle_url('/mod/videoassessment/view.php', ['id' => $id]);
$ismailsent = optional_param('ismailsent', 0, PARAM_INT);
if ($action = optional_param('action', null, PARAM_ALPHA)) {
    $url->param('action', $action);
}
$cm = get_coursemodule_from_id('videoassessment', $id);
$course = $DB->get_record('course', ['id' => $cm->course]);
require_login($cm->course, true, $cm);
$PAGE->set_url($url);
$PAGE->set_heading($cm->name);
$PAGE->requires->jquery();
if ($action == "") {
    $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'init_message_sent_window', [$ismailsent]);
}
$context = context_module::instance($cm->id);
require_capability('mod/videoassessment:view', $context);

// DISABLED: Redirect logic moved to be more strict and only triggered via explicit user preference.
// The redirect to grading should ONLY happen when "Save and create rubric" button is clicked,
// which sets a user preference with a timestamp. This check is now done server-side only in lib.php
// during add_instance/update_instance, and the preference is cleared immediately after redirect.
// 
// Clear any stale preferences as a safety measure to prevent unwanted redirects.
$redirect_to_grading = get_user_preferences('videoassessment_redirect_to_grading');
if (!empty($redirect_to_grading)) {
    // Parse the preference value: 'id:timestamp' or just 'id' (for backward compatibility).
    $parts = explode(':', $redirect_to_grading);
    $vaid = (int)$parts[0];
    $preftimestamp = isset($parts[1]) ? (int)$parts[1] : 0;
    
    // Check if we're coming from modedit.php (activity creation/editing).
    $isfrommodedit = isset($_SERVER['HTTP_REFERER']) && 
        (strpos($_SERVER['HTTP_REFERER'], '/course/modedit.php') !== false ||
         strpos($_SERVER['HTTP_REFERER'], '/mod/videoassessment/modedit.php') !== false);
    
    // Only redirect if ALL of these conditions are met:
    // 1. Coming from modedit.php (activity creation/editing)
    // 2. Preference was set very recently (within 0.5 seconds - extremely strict)
    // 3. Preference ID matches current activity instance
    // 4. Preference has a valid timestamp
    $should_redirect = false;
    if ($isfrommodedit && $preftimestamp > 0) {
        $recent = (time() - $preftimestamp) <= 0.5; // 0.5 second window - extremely strict
        $matchesactivity = ($vaid == $cm->instance);
        
        if ($recent && $matchesactivity) {
            // Double-check: verify the activity exists and matches
            $va = $DB->get_record('videoassessment', ['id' => $vaid]);
            if ($va && $va->id == $cm->instance) {
                $should_redirect = true;
            }
        }
    }
    
    if ($should_redirect) {
        // Clear the preference immediately to prevent redirect loops.
        unset_user_preference('videoassessment_redirect_to_grading');
        
        // Get or create the grading area and redirect.
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $gradingmanager = get_grading_manager($context, 'mod_videoassessment', 'beforeteacher');
        
        $arearecord = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'areaname' => 'beforeteacher'
        ]);
        
        if (!$arearecord) {
            // Create the area.
            $gradingmanager->set_active_method('rubric');
            $arearecord = $DB->get_record('grading_areas', [
                'contextid' => $context->id,
                'component' => 'mod_videoassessment',
                'areaname' => 'beforeteacher'
            ]);
        }
        
        if ($arearecord && $arearecord->id) {
            // Redirect to grading page.
            redirect(new moodle_url('/grade/grading/manage.php', ['areaid' => $arearecord->id]));
        }
    } else {
        // Don't redirect - clear the preference to prevent future issues.
        unset_user_preference('videoassessment_redirect_to_grading');
    }
} else {
    // No preference found - ensure any stale preferences are cleared.
    // This is a safety measure to prevent issues from previous activity creations.
    $stale_pref = get_user_preferences('videoassessment_redirect_to_grading');
    if (!empty($stale_pref)) {
        unset_user_preference('videoassessment_redirect_to_grading');
    }
}

// Clear sessionStorage flag if it exists (for cleanup).
// Also clear it on any grading-related pages to prevent redirects.
$PAGE->requires->js_amd_inline("
    require(['jquery'], function(\$) {
        var currentUrl = window.location.href;
        // Clear redirect flags on grading pages or activity view pages.
        if (currentUrl.indexOf('/grade/grading/') !== -1 || 
            currentUrl.indexOf('/mod/videoassessment/view.php') !== -1) {
            sessionStorage.removeItem('videoassessment_check_grading_redirect');
            sessionStorage.removeItem('videoassessment_processed_tokens');
        }
    });
");

// Trigger standard "viewed" event.
$videoassessment = $DB->get_record('videoassessment', ['id' => $cm->instance], '*', MUST_EXIST);
$event = \mod_videoassessment\event\course_module_viewed::create([
    'objectid' => $videoassessment->id,
    'context' => $context,
    'courseid' => $course->id,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('videoassessment', $videoassessment);
$event->trigger();

$va = new mod_videoassessment\va($context, $cm, $course);
echo $va->view($action);
