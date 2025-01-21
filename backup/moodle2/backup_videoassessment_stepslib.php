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
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

class backup_videoassessment_activity_structure_step extends backup_activity_structure_step {
    /**
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        $va = new backup_nested_element('videoassessment', array('id'), array(
                'name', 'intro', 'introformat', 'maxbytes', 'timedue', 'timeavailable',
                'grade', 'timemodified', 'ratingteacher', 'ratingself', 'ratingpeer',
                'usedpeers', 'beforelabel', 'afterlabel', 'delayedteachergrade',
                'allowstudentupload'
        ));
        $va->set_source_table('videoassessment', array('id' => backup::VAR_ACTIVITYID));

        if ($userinfo) {
            $videos = new backup_nested_element('videos');
            $video = new backup_nested_element('video', array('id'), array(
                    'filepath', 'filename', 'thumbnailname', 'originalname', 'timecreated',
                    'timemodified'));

            $videoassocs = new backup_nested_element('video_assocs');
            $videoassoc = new backup_nested_element('video_assoc', array('id'), array(
                    'videoid', 'associationtype', 'timing', 'associationid', 'timemodified'));

            $gradeitems = new backup_nested_element('grade_items');
            $gradeitem = new backup_nested_element('grade_item', array('id'), array(
                    'gradeduser', 'type', 'grader', 'usedbypeermarking'));

            $grades = new backup_nested_element('grades');
            $grade = new backup_nested_element('grade', array('id'), array(
                    'gradeitem', 'timemarked', 'grade', 'submissioncomment', 'mailed'));

            $aggregations = new backup_nested_element('aggregations');
            $aggregation = new backup_nested_element('aggregation', array('id'), array(
                    'userid', 'timing', 'timemodified', 'grade', 'gradebefore', 'gradeafter',
                    'gradebeforeteacher', 'gradebeforeself', 'gradebeforepeer', 'gradeafterteacher',
                    'gradeafterself', 'gradeafterpeer'));

            $va->add_child($videos);
            $va->add_child($videoassocs);
            $va->add_child($gradeitems);
            $va->add_child($grades);
            $va->add_child($aggregations);

            $videos->add_child($video);
            $videoassocs->add_child($videoassoc);
            $gradeitems->add_child($gradeitem);
            $grades->add_child($grade);
            $aggregations->add_child($aggregation);

            $video->set_source_table('videoassessment_videos', array('videoassessment' => backup::VAR_PARENTID));
            $videoassoc->set_source_table('videoassessment_video_assocs', array('videoassessment' => backup::VAR_PARENTID));
            $gradeitem->set_source_table('videoassessment_grade_items', array('videoassessment' => backup::VAR_PARENTID));
            $grade->set_source_table('videoassessment_grades', array('videoassessment' => backup::VAR_PARENTID));
            $aggregation->set_source_table('videoassessment_aggregation', array('videoassessment' => backup::VAR_PARENTID));

            $va->annotate_files('mod_videoassessment', 'video', null);
        }

        return $this->prepare_activity_structure($va);
    }
}
