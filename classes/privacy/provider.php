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

namespace mod_videoassessment\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_module;

/**
 * Privacy Subsystem implementation for mod_videoassessment.
 *
 * @package     mod_videoassessment
 * @copyright   2024 Don Hinkleman (hinkelman@mac.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_data_provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata about the plugin's database tables and how user data is stored.
     *
     * This informs Moodle's privacy subsystem about what user data is stored and where.
     *
     * @param collection $collection The metadata collection to populate.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        // Some description about the table data
        $collection->add_database_table('videoassessment', [
            'course' => 'privacy:metadata:videoassessment:course',
            'name' => 'privacy:metadata:videoassessment:name',
            'intro' => 'privacy:metadata:videoassessment:intro',
            'trainingdesc' => 'privacy:metadata:videoassessment:trainingdesc',
            'timemodified' => 'privacy:metadata:videoassessment:timemodified',
            'ratingteacher' => 'privacy:metadata:videoassessment:ratingteacher',
            'ratingself' => 'privacy:metadata:videoassessment:ratingself',
            'ratingpeer' => 'privacy:metadata:videoassessment:ratingpeer',
            'class' => 'privacy:metadata:videoassessment:class',
        ], 'privacy:metadata:videoassessment');

        $collection->add_database_table('videoassessment_aggregation', [
            'videoassessment' => 'privacy:metadata:videoassessment_aggregation:videoassessment',
            'userid' => 'privacy:metadata:videoassessment_aggregation:userid',
            'timing' => 'privacy:metadata:videoassessment_aggregation:timing',
            'timemodified' => 'privacy:metadata:videoassessment_aggregation:timemodified',
        ], 'privacy:metadata:videoassessment_aggregation');

        $collection->add_database_table('videoassessment_grades', [
            'videoassessment' => 'privacy:metadata:videoassessment_grades:videoassessment',
            'gradeitem' => 'privacy:metadata:videoassessment_grades:gradeitem',
            'timemarked' => 'privacy:metadata:videoassessment_grades:timemarked',
            'grade' => 'privacy:metadata:videoassessment_grades:grade',
            'submissioncomment' => 'privacy:metadata:videoassessment_grades:submissioncomment',
        ], 'privacy:metadata:videoassessment_grades');

        $collection->add_database_table('videoassessment_grade_items', [
            'videoassessment' => 'privacy:metadata:videoassessment_grade_items:videoassessment',
            'type' => 'privacy:metadata:videoassessment_grade_items:type',
            'gradeduser' => 'privacy:metadata:videoassessment_grade_items:gradeduser',
        ], 'privacy:metadata:videoassessment_grade_items');

        $collection->add_database_table('videoassessment_peers', [
            'videoassessment' => 'privacy:metadata:videoassessment_peers:videoassessment',
            'userid' => 'privacy:metadata:videoassessment_peers:userid',
            'peerid' => 'privacy:metadata:videoassessment_peers:peerid',
        ], 'privacy:metadata:videoassessment_peers');

        $collection->add_database_table('videoassessment_sort_items', [
            'itemid' => 'privacy:metadata:videoassessment_sort_items:itemid',
            'type' => 'privacy:metadata:videoassessment_sort_items:type',
        ], 'privacy:metadata:videoassessment_sort_items');

        $collection->add_database_table('videoassessment_sort_order', [
            'sortitemid' => 'privacy:metadata:videoassessment_sort_order:sortitemid',
            'userid' => 'privacy:metadata:videoassessment_sort_order:userid',
        ], 'privacy:metadata:videoassessment_sort_order');

        $collection->add_database_table('videoassessment_videos', [
            'videoassessment' => 'privacy:metadata:videoassessment_videos:videoassessment',
            'filepath' => 'privacy:metadata:videoassessment_videos:filepath',
            'filename' => 'privacy:metadata:videoassessment_videos:filename',
            'originalname' => 'privacy:metadata:videoassessment_videos:originalname',
            'timecreated' => 'privacy:metadata:videoassessment_videos:timecreated',
            'timemodified' => 'privacy:metadata:videoassessment_videos:timemodified',
        ], 'privacy:metadata:videoassessment_videos');

        $collection->add_database_table('videoassessment_video_assocs', [
            'videoassessment' => 'privacy:metadata:videoassessment_video_assocs:videoassessment',
            'videoid' => 'privacy:metadata:videoassessment_video_assocs:videoid',
            'associationid' => 'privacy:metadata:videoassessment_video_assocs:associationid',
            'timemodified' => 'privacy:metadata:videoassessment_video_assocs:timemodified',
        ], 'privacy:metadata:videoassessment_video_assocs');

        return $collection;
    }
    /**
     * Returns a list of contexts where the specified user has data.
     *
     * Used by the privacy API to determine where a user's data resides within this plugin.
     *
     * @param int $userid The ID of the user.
     * @return contextlist List of contexts that contain user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $params = [
            'modname' => 'videoassessment',
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
        ];

        // Check if user has any data
        $aggregationcount = $DB->count_records('videoassessment_aggregation', ['userid' => $userid]);
        $peerscount = $DB->count_records('videoassessment_peers', ['userid' => $userid]);
        $sortcount = $DB->count_records('videoassessment_sort_order', ['userid' => $userid]);
        $gradeitemscount = $DB->count_records('videoassessment_grade_items', ['gradeduser' => $userid]);

        // Only proceed if user has data
        if ($aggregationcount == 0 && $peerscount == 0 && $sortcount == 0 && $gradeitemscount == 0) {
            return $contextlist;
        }

        $sql = "SELECT DISTINCT c.id
                FROM {context} c
                JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {videoassessment} va ON va.id = cm.instance
                WHERE va.id IN (
                    SELECT DISTINCT videoassessment FROM {videoassessment_aggregation} WHERE userid = :userid1
                    UNION
                    SELECT DISTINCT videoassessment FROM {videoassessment_peers} WHERE userid = :userid2
                    UNION
                    SELECT DISTINCT sortitemid FROM {videoassessment_sort_order} WHERE userid = :userid3
                    UNION
                    SELECT DISTINCT videoassessment FROM {videoassessment_grade_items} WHERE gradeduser = :userid4
                )";

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Adds user IDs to the userlist for the given context.
     *
     * Identifies all users who have data within the given context.
     *
     * @param userlist $userlist List of users for a particular context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, context_module::class)) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
            'modulename' => 'videoassessment',
        ];

        // videoassessment_aggregation authors.
        $sql = "SELECT va.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.id = cm.instance
                  JOIN {videoassessment_aggregation} va ON va.videoassessment = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // videoassessment_peers authors.
        $sql = "SELECT va.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.id = cm.instance
                  JOIN {videoassessment_peers} va ON va.videoassessment = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // videoassessment_sort_order authors.
        $sql = "SELECT va.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.id = cm.instance
                  JOIN {videoassessment_sort_order} va ON va.sortitemid = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // videoassessment_grade_items gradeduser
        $sql = "SELECT gi.gradeduser as userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.id = cm.instance
                  JOIN {videoassessment_grade_items} gi ON gi.videoassessment = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Exports all user data for the specified user across all approved contexts.
     *
     * Used when a user requests a copy of their personal data.
     *
     * @param approved_contextlist $contextlist The contexts and user whose data will be exported.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('videoassessment', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $videoassessmentid = $cm->instance;

            // Export aggregation data
            $aggregations = $DB->get_records('videoassessment_aggregation', [
                'videoassessment' => $videoassessmentid,
                'userid' => $user->id,
            ]);

            if ($aggregations) {
                writer::with_context($context)->export_data([
                    get_string('pluginname', 'mod_videoassessment'),
                    'aggregations',
                ], (object) ['aggregations' => array_values($aggregations)]);
            }

            // Export peers data
            $peers = $DB->get_records('videoassessment_peers', [
                'videoassessment' => $videoassessmentid,
                'userid' => $user->id,
            ]);

            if ($peers) {
                writer::with_context($context)->export_data([
                    get_string('pluginname', 'mod_videoassessment'),
                    'peers',
                ], (object) ['peers' => array_values($peers)]);
            }

            // Export sort order data
            $sortorders = $DB->get_records('videoassessment_sort_order', ['userid' => $user->id]);

            if ($sortorders) {
                writer::with_context($context)->export_data([
                    get_string('pluginname', 'mod_videoassessment'),
                    'sort_orders',
                ], (object) ['sort_orders' => array_values($sortorders)]);
            }

            // Export grade items data
            $gradeitems = $DB->get_records('videoassessment_grade_items', [
                'videoassessment' => $videoassessmentid,
                'gradeduser' => $user->id,
            ]);

            if ($gradeitems) {
                writer::with_context($context)->export_data([
                    get_string('pluginname', 'mod_videoassessment'),
                    'grade_items',
                ], (object) ['grade_items' => array_values($gradeitems)]);
            }

            // Export grade items data
            $videoassocs = $DB->get_records('videoassessment_video_assocs', [
                'videoassessment' => $videoassessmentid,
                'associationid' => $user->id,
            ]);

            if ($videoassocs) {
                writer::with_context($context)->export_data([
                    get_string('pluginname', 'mod_videoassessment'),
                    'video_assocs',
                ], (object) ['video_assocs' => array_values($videoassocs)]);
            }
        }
    }

    /**
     * Deletes all user data for all users in a given context.
     *
     * Typically called when an activity/module is being deleted.
     *
     * @param \context $context The context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Check that this is a context_module.
        if (!$context instanceof context_module) {
            return;
        }

        // Get the course module.
        if (!$cm = get_coursemodule_from_id('videoassessment', $context->instanceid)) {
            return;
        }

        $videoassessmentid = $cm->instance;

        $DB->delete_records('videoassessment_aggregation', ['videoassessment' => $videoassessmentid]);
        $DB->delete_records('videoassessment_peers', ['videoassessment' => $videoassessmentid]);
        $DB->delete_records('videoassessment_sort_order', ['sortitemid' => $videoassessmentid]);
        $DB->delete_records('videoassessment_grades', ['videoassessment' => $videoassessmentid]);
        $DB->delete_records('videoassessment_grade_items', ['videoassessment' => $videoassessmentid]);
        $DB->delete_records('videoassessment_videos', ['videoassessment' => $videoassessmentid]);
        $DB->delete_records('videoassessment_video_assocs', ['videoassessment' => $videoassessmentid]);

    }

    /**
     * Deletes all user data for a specific user in the provided contexts.
     *
     * Used when a user requests deletion of their data.
     *
     * @param approved_contextlist $contextlist Contexts and user ID to delete data for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            // Get the course module.
            $cm = get_coursemodule_from_id('videoassessment', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $videoassessmentid = $cm->instance;

            $DB->delete_records('videoassessment_aggregation', ['videoassessment' => $videoassessmentid, 'userid' => $userid]);
            $DB->delete_records('videoassessment_peers', ['videoassessment' => $videoassessmentid, 'userid' => $userid]);
            $DB->delete_records('videoassessment_sort_order', ['userid' => $userid]);
            $DB->delete_records('videoassessment_grade_items', ['videoassessment' => $videoassessmentid, 'gradeduser' => $userid]);
            $DB->delete_records('videoassessment_video_assocs', ['videoassessment' => $videoassessmentid, 'associationid' => $userid]);
        }
    }

    /**
     * Deletes all user data for a list of users in a given context.
     *
     * Used for bulk deletion of user data, such as when multiple users leave a course.
     *
     * @param approved_userlist $userlist List of users and context to delete data for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoassessment', $context->instanceid);
        if (!$cm) {
            return;
        }
        $videoassessmentid = $cm->instance;

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['videoassessment' => $videoassessmentid], $userinparams);
        $params2 = $userinparams;

        $DB->delete_records_select('videoassessment_aggregation', "videoassessment = :videoassessment AND userid $userinsql", $params);
        $DB->delete_records_select('videoassessment_peers', "videoassessment = :videoassessment AND userid $userinsql", $params);
        $DB->delete_records_select('videoassessment_sort_order', "userid $userinsql", $params2);
        $DB->delete_records_select('videoassessment_grades', "videoassessment = :videoassessment AND gradeitem $userinsql", $params);
        $DB->delete_records_select('videoassessment_grade_items', "videoassessment = :videoassessment AND gradeduser $userinsql", $params);
        $DB->delete_records_select('videoassessment_video_assocs', "videoassessment = :videoassessment AND associationid $userinsql", $params);

    }
}
