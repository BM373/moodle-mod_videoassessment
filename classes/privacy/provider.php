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
 * Privacy Subsystem implementation for mod_videoassessment.
 *
 * @package    mod_videoassessment
 * @copyright
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment\privacy;

use \core_privacy\local\request\userlist;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\data_provider;

defined('MOODLE_INTERNAL') || die();



class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider
{


    public static function get_metadata(collection $collection): collection {
        //Some description about the table data
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
            'videoassessment ' => 'privacy:metadata:videoassessment_peers:videoassessment ',
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


    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $params = [
            'modname'       => 'videoassessment',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];

        // videoassessment_aggregation  creators.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoassessment} vv ON vv.id = cm.instance
                  JOIN {videoassessment_aggregation} va ON va.videoassessment = vv.id
                 WHERE va.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'instanceid'    => $context->instanceid,
            'modulename'    => 'videoassessment',
        ];

        // videoassessment_aggregation authors.
        $sql = "SELECT va.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.course = cm.course
                  JOIN {videoassessment_aggregation} va ON va.videoassessment = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // videoassessment_peers authors.
        $sql = "SELECT va.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.course = cm.course
                  JOIN {videoassessment_peers} va ON va.videoassessment = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // videoassessment_sort_order authors.
        $sql = "SELECT va.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {videoassessment} vv ON vv.course = cm.course
                  JOIN {videoassessment_sort_order} va ON va.sortitemid = vv.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
	}

    /**
     * Export all user preferences for the plugin.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $markasreadonnotification = get_user_preference('markasreadonnotification', null, $userid);
        if (null !== $markasreadonnotification) {
            switch ($markasreadonnotification) {
                case 0:
                    $markasreadonnotificationdescription = get_string('markasreadonnotificationno', 'mod_videoassessment');
                    break;
                case 1:
                default:
                    $markasreadonnotificationdescription = get_string('markasreadonnotificationyes', 'mod_videoassessment');
                    break;
            }
            writer::export_user_preference('mod_videoassessment', 'markasreadonnotification', $markasreadonnotification, $markasreadonnotificationdescription);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Check that this is a context_module.
        if (!$context instanceof \context_module) {
            return;
        }

        // Get the course module.
        if (!$cm = get_coursemodule_from_id('videoassessment', $context->instanceid)) {
            return;
        }

        $userid = $cm->instance;

        $DB->delete_records('videoassessment_aggregation', ['userid' => $userid]);
        $DB->delete_records('videoassessment_peers', ['userid' => $userid]);
        $DB->delete_records('videoassessment_sort_order', ['userid' => $userid]);


    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        foreach ($contextlist as $context) {
            // Get the course module.
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            $videoassessment_id = $DB->get_record('videoassessment', ['course' => $cm->instance]);

            //$DB->delete_records('videoassessment', ['id' => $videoassessment_id->id]);
            $DB->delete_records('videoassessment_grades', ['videoassessment' => $videoassessment_id->id]);
            $DB->delete_records('videoassessment_grade_items', ['videoassessment' => $videoassessment_id->id]);
            $DB->delete_records('videoassessment_peers', ['videoassessment' => $videoassessment_id->id]);
            $DB->delete_records('videoassessment_videos', ['videoassessment' => $videoassessment_id->id]);
            $DB->delete_records('videoassessment_video_assocs', ['videoassessment' => $videoassessment_id->id]);

        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $videoassessment_id = $DB->get_record('videoassessment', ['id' => $cm->instance]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['videoassessment' => $videoassessment_id->id], $userinparams);
        $sql = "videoassessment = :videoassessment AND userid {$userinsql}";
        $sql2 = "userid {$userinsql}";

        $DB->delete_records_select('videoassessment_aggregation', $sql, $params);
        $DB->delete_records_select('videoassessment_peers', $sql, $params);
        $DB->delete_records_select('videoassessment_sort_order', $sql2, $params);

    }




}