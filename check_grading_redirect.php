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
 * AJAX endpoint to check if redirect to grading is needed.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

header('Content-Type: application/json');

// Always clear the preference first to prevent redirect loops.
$redirect_to_grading = get_user_preferences('videoassessment_redirect_to_grading');
unset_user_preference('videoassessment_redirect_to_grading');

if (!empty($redirect_to_grading)) {
    // Parse the preference value: 'id:timestamp' or just 'id' (for backward compatibility).
    $parts = explode(':', $redirect_to_grading);
    $vaid = (int)$parts[0];
    $preftimestamp = isset($parts[1]) ? (int)$parts[1] : 0;

    // Only redirect if preference was set very recently (within 2 seconds).
    // This ensures redirects only happen immediately after clicking "Save and create rubric".
    if ($preftimestamp > 0 && (time() - $preftimestamp) > 2) {
        // Preference is stale - don't redirect.
        echo json_encode(['redirect' => false]);
        exit;
    }

    // Get the course module for this videoassessment instance.
    $va = $DB->get_record('videoassessment', ['id' => $vaid]);
    if ($va) {
        $cm = get_coursemodule_from_instance('videoassessment', $va->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Get or create the grading area.
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $gradingmanager = get_grading_manager($context, 'mod_videoassessment', 'beforeteacher');

        $arearecord = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_videoassessment',
            'areaname' => 'beforeteacher',
        ]);

        if (!$arearecord) {
            // Create the area.
            $gradingmanager->set_active_method('rubric');
            $arearecord = $DB->get_record('grading_areas', [
                'contextid' => $context->id,
                'component' => 'mod_videoassessment',
                'areaname' => 'beforeteacher',
            ]);
        }

        if ($arearecord && $arearecord->id) {
            echo json_encode([
                'redirect' => true,
                'url' => $CFG->wwwroot . '/grade/grading/manage.php?areaid=' . $arearecord->id,
            ]);
            exit;
        }
    }
}

echo json_encode(['redirect' => false]);
