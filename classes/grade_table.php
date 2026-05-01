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

namespace mod_videoassessment;

use mod_videoassessment\form\assign_class;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoassessment/classes/form/assign_class.php');

/**
 * Grade table display class for the videoassessment module.
 *
 * This class handles the generation and display of grade tables for different
 * user types (teachers, students, peers) with various sorting and filtering options.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_table {

    /**
     * Sort order constant for ascending sort.
     *
     * @var int
     */
    const ORDER_ASC = 1;

    /**
     * Sort order constant for descending sort.
     *
     * @var int
     */
    const ORDER_DESC = 2;

    /**
     * Video assessment instance object.
     *
     * @var va
     */
    private $va;

    /**
     * Video assessment instance ID.
     *
     * @var int
     */
    public $instance;

    /**
     * Course module object.
     *
     * @var \stdClass
     */
    public $cm;

    /**
     * Table data array containing rows and columns.
     *
     * @var \stdClass
     */
    public $data;

    /**
     * CSS classes for table elements.
     *
     * @var \stdClass
     */
    public $classes;

    /**
     * DOM ID for the table element.
     *
     * @var string
     */
    private $domid;

    /**
     * CSS class name for the table element.
     *
     * @var string
     */
    public $domclass = 'gradetable';

    /**
     * Starting column positions for different timings.
     *
     * @var array
     */
    public $startcolumns = array('before' => 0, 'after' => 6);

    /**
     * Default user sorting order.
     *
     * @var string
     */
    public $usersort = 'u.firstname, u.lastname';

    /**
     * Text displayed for empty grades.
     *
     * @var string
     */
    public $emptygradetext = '-';

    /**
     * Text displayed for hidden grades.
     *
     * @var string
     */
    public $hiddengradetext = '-';

    /**
     * Initialize the grade table with video assessment instance.
     *
     * Sets up the grade table object with the provided video assessment
     * instance and extracts relevant IDs and course module information.
     *
     * @param va $va Video assessment instance object
     * @return void
     */
    public function __construct(va $va) {
        $this->va = $va;
        $this->instance = $va->va->id;
        $this->cm = $va->cm;
    }

    /**
     * Validate user ID and log error if invalid.
     *
     * Checks if the user object has a valid ID and logs a debugging
     * message if the ID is missing or empty.
     *
     * @param mixed $user The user object being checked
     * @return bool Returns true if the ID is valid, otherwise false
     */
    private function validate_user_id($user) {
        if (empty($user->id)) {
            debugging("Error: Missing user->id for the user. User skipped.");
            return false;
        }
        return true;
    }

    /**
     * Generate and display grade table for teachers.
     *
     * Creates a comprehensive grade table showing all students with their
     * grades from different graders (class, self, peer, teacher) and
     * includes sorting and filtering options.
     *
     * @return string HTML output of the grade table
     */
    public function print_teacher_grade_table() {
        global $CFG, $DB, $USER;

        $this->domid = 'gradetableteacher';

        $this->setup_header();

        $cm = $this->cm;
        $context = $this->va->context;

        $users = $this->va->get_students();
        if (!empty($users)) {
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users && !empty($CFG->enablegroupmembersonly) && $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        if ($users) {
            $groupmode = groups_get_activity_groupmode($cm);
            $aag = has_capability('moodle/site:accessallgroups', $context);

            if ($groupmode == VISIBLEGROUPS || $aag) {
                $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping
            } else {
                $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid); // only assigned groups
            }

            $groupid = groups_get_activity_group($cm, true, $allowedgroups);
            $groupid = optional_param('group', $groupid, PARAM_INT);

            if (!empty($groupid)) {
                $type = 'group';
                $itemid = $groupid;
            } else {
                $type = 'course';
                $itemid = $cm->course;
            }

            $sortitem = $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));

            if (!empty($sortitem)) {
                $sort = $sortitem->sortby;
            } else {
                $sort = assign_class::SORT_ID;
            }

            $nsort = optional_param('nsort', null, PARAM_INT);

            if (!empty($nsort)) {
                $orderstr = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';

                if ($nsort == self::ORDER_ASC) {
                    $orderstr .= ' ASC';
                } else {
                    $orderstr .= ' DESC';
                }

                $users = $this->va->get_students_sort($groupid, false, $orderstr);
            } else {
                if ($sort == assign_class::SORT_MANUALLY) {
                    $users = $this->va->get_students_sort($groupid, true);
                } else {
                    if (in_array($sort, array(assign_class::SORT_ID, assign_class::SORT_NAME))) {
                        if ($sort == assign_class::SORT_ID) {
                            $orderstr = ' ORDER BY u.id';
                        } else {
                            $orderstr = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
                        }
                    } else {
                        $orderstr = '';
                    }

                    $users = $this->va->get_students_sort($groupid, false, $orderstr);
                }
            }

            foreach ($users as $user) {
                if (empty($user->id)) {
                    debugging("Error: Missing user->id for the user in the function print_teacher_grade_table. User skipped.");
                    continue;
                }
                $agg = $this->va->get_aggregated_grades($user->id);
                $fields = [
                    'userid',
                    'gradebeforeteacher',
                    'gradebeforeself',
                    'gradebeforepeer',
                    'gradebeforeclass',
                    'gradebefore',
                    'videoassessment',
                    /*'fairnessbonus',
                    'selffairnessbonus',
                    'finalscore',*/
                    'timemodified',
                ];
                foreach ($fields as $field) {
                    if ($agg) {
                        $user->$field = $agg->$field;
                    }
                }

                $this->add_user_data($user);
            }
        }

        return $this->print_html();
    }

    /**
     * Generate and display grade table for student self-view.
     *
     * Creates a grade table showing the current user's own grades
     * with appropriate visibility controls based on delayed grading settings.
     *
     * @return string HTML output of the grade table
     */
    public function print_self_grade_table() {
        global $DB, $USER;

        $this->domid = 'gradetableself';

        $user = $this->va->get_aggregated_grades($USER->id);

        $this->setup_header();
        $this->add_user_data($user);

        if ($this->va->va->delayedteachergrade) {
            if ($user->gradebeforeself == -1) {
                $this->data[2][4] = $this->hiddengradetext;
                $this->data[2][5] = $this->hiddengradetext;
            }
        }

        return $this->print_html();
    }

    /**
     * Generate and display grade table for peer assessment view.
     *
     * Creates a grade table showing peer students' grades with restricted
     * visibility to hide sensitive grading information from other students.
     *
     * @return string HTML output of the grade table
     */
    public function print_peer_grade_table() {
        global $DB, $USER;

        $this->domid = 'gradetablepeer';

        $this->setup_header();

        $cm = $this->cm;
        $context = $this->va->context;
        $groupmode = groups_get_activity_groupmode($cm);
        $aag = has_capability('moodle/site:accessallgroups', $context);

        if ($groupmode == VISIBLEGROUPS || $aag) {
            $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping
        } else {
            $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid); // only assigned groups
        }

        $groupid = groups_get_activity_group($cm, true, $allowedgroups);
        $groupid = optional_param('group', $groupid, PARAM_INT);

        if (!empty($groupid)) {
            $type = 'group';
            $itemid = $groupid;
        } else {
            $type = 'course';
            $itemid = $cm->course;
        }

        $sortitem = $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));

        if (!empty($sortitem)) {
            $sort = $sortitem->sortby;
        } else {
            $sort = assign_class::SORT_ID;
        }

        $nsort = optional_param('nsort', null, PARAM_INT);

        if (!empty($nsort)) {
            $orderstr = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';

            if ($nsort == self::ORDER_ASC) {
                $orderstr .= ' ASC';
            } else {
                $orderstr .= ' DESC';
            }

            $peers = $this->va->get_peers_sort( $USER->id, $groupid, false, $orderstr);
        } else {
            if ($sort == assign_class::SORT_MANUALLY) {
                $peers = $this->va->get_peers_sort( $USER->id, $groupid,  true);
            } else {
                if (in_array($sort, array(assign_class::SORT_ID, assign_class::SORT_NAME))) {
                    if ($sort == assign_class::SORT_ID) {
                        $orderstr = ' ORDER BY u.id';
                    } else {
                        $orderstr = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
                    }
                } else {
                    $orderstr = '';
                }

                $peers = $this->va->get_peers_sort( $USER->id, $groupid, false, $orderstr);
            }
        }

        foreach ($peers as $peer) {
            $user = $this->va->get_aggregated_grades($peer);
            $this->add_user_data($user);

            $row = count($this->data) - 1;

            $this->data[$row][3] = $this->hiddengradetext;
            $this->data[$row][5] = $this->hiddengradetext;
            $this->data[$row][6] = $this->hiddengradetext;
            $this->data[$row][7] = $this->hiddengradetext;
            $this->data[$row][8] = $this->hiddengradetext;
        }

        return $this->print_html();
    }

    /**
     * Generate and display grade table for class assessment view.
     *
     * Creates a grade table showing all students' grades with class-specific
     * visibility controls and assessment options for class-wide grading.
     *
     * @return string HTML output of the grade table
     */
    public function print_class_grade_table() {
        global $DB, $USER;

        $this->domid = 'gradetableclass';

        $this->setup_header();

        $cm = $this->cm;
        $context = $this->va->context;

        $users = $this->va->get_students();
        if (!empty($users)) {
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users && !empty($CFG->enablegroupmembersonly) && $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $peers = $this->va->get_peers($USER->id);

        if ($users) {
            $groupmode = groups_get_activity_groupmode($cm);
            $aag = has_capability('moodle/site:accessallgroups', $context);

            if ($groupmode == VISIBLEGROUPS || $aag) {
                $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping
            } else {
                $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid); // only assigned groups
            }

            $groupid = groups_get_activity_group($cm, true, $allowedgroups);
            $groupid = optional_param('group', $groupid, PARAM_INT);

            if (!empty($groupid)) {
                $type = 'group';
                $itemid = $groupid;
            } else {
                $type = 'course';
                $itemid = $cm->course;
            }

            $sortitem = $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));

            if (!empty($sortitem)) {
                $sort = $sortitem->sortby;
            } else {
                $sort = assign_class::SORT_ID;
            }

            $nsort = optional_param('nsort', null, PARAM_INT);

            if (!empty($nsort)) {
                $orderstr = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';

                if ($nsort == self::ORDER_ASC) {
                    $orderstr .= ' ASC';
                } else {
                    $orderstr .= ' DESC';
                }

                $users = $this->va->get_students_sort($groupid, false, $orderstr);
            } else {
                if ($sort == assign_class::SORT_MANUALLY) {
                    $users = $this->va->get_students_sort($groupid, true);
                } else {
                    if (in_array($sort, array(assign_class::SORT_ID, assign_class::SORT_NAME))) {
                        if ($sort == assign_class::SORT_ID) {
                            $orderstr = ' ORDER BY u.id';
                        } else {
                            $orderstr = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
                        }
                    } else {
                        $orderstr = '';
                    }

                    $users = $this->va->get_students_sort($groupid, false, $orderstr);
                }
            }

            foreach ($users as $user) {
                if ($user->id == $USER->id) {
                    continue;
                }

                $user = $this->va->get_aggregated_grades($user->id);
                $this->add_user_data($user);

                $row = count($this->data) - 1;
                $this->data[$row][3] = $this->hiddengradetext;
                $this->data[$row][4] = $this->hiddengradetext;
                $this->data[$row][5] = $this->hiddengradetext;
                $this->data[$row][6] = $this->hiddengradetext;
                $this->data[$row][7] = $this->hiddengradetext;
                $this->data[$row][8] = $this->hiddengradetext;

                if ($this->va->va->delayedteachergrade) {
                    if ($user->gradebeforeself == -1) {
                        $this->data[$row][4] = $this->hiddengradetext;
                        $this->data[$row][5] = $this->hiddengradetext;
                    }
                }
            }
        }

        return $this->print_html();
    }

    /**
     * Generate and display grade table for training assessment.
     *
     * Creates a specialized grade table for training/pre-test assessments
     * with video preview and pass/fail status display.
     *
     * @return string HTML output of the training grade table
     */
    public function print_training_grade_table() {
        global $DB, $USER, $OUTPUT;

        $this->domid = 'gradetabletraining';

        $user = $this->va->get_aggregated_grades($USER->id);

        $this->add_data(array('', '', ''));

        $header = array();
        $header[0] = va::str('namesort');
        $header[1] = va::str('weighting');
        $header[2] = '';

        $this->add_data($header);

        if (!isset($user->picture)) {
            $picturefields = \core_user\fields::get_picture_fields();
            $tmp = $DB->get_record('user', array('id' => $user->userid), implode(',', $picturefields));
            foreach ($picturefields as $field) {
                $user->$field = $tmp->$field;
            }
        }

        $row = array();
        $row[0] = $OUTPUT->user_picture($user) . ' ' . fullname($user);
        $row[1] = '';

        $data = $DB->get_record('videoassessment_videos', array('id' => $this->va->va->trainingvideoid));
        if ($data) {
            if ($video = new video($this->va->context, $data)) {
                $PAGE->requires->js_call_amd('mod_videoassessment/module', 'initVideoTrainingPreview');
                $content = $video->render_thumbnail(va::str('previewvideo'));
                $row[1] = \html_writer::tag(
                    '#', $content, array(
                        'class' => 'show-training-video',
                        'data-videoid' => $this->va->va->trainingvideoid,
                    )
                );

            }
        }

        if ($user->passtraining == 1) {
            $row[2] = va::str('passed');
        } else {
            if ($this->va->is_graded_by_current_user($user->id, 'beforetraining', $user->id)) {
                $row[2] = va::str('failed');
            } else {
                $row[2] = va::str('notattempted');
            }
        }

        if ($this->va->is_graded_by_current_user($user->id, 'beforetraining')) {
            $button = 'assessagain';
        } else {
            $button = 'firstassess';
        }

        if ($user->passtraining == 1) {
            $url = new \moodle_url($this->va->viewurl,
                array('action' => 'trainingresult', 'userid' => $user->id));
            $button = 'viewresult';
        } else {
            $url = new \moodle_url($this->va->viewurl,
                array('action' => 'assess', 'userid' => $user->id, 'gradertype' => 'training'));
        }

        $row[2] = $OUTPUT->action_link($url,
                get_string($button, 'videoassessment'), null,
                array('class' => 'button-' . $button)) . '<br />' . $row[2];

        $this->add_data($row);

        return $this->print_html();
    }

    /**
     * Setup table headers and column structure.
     *
     * Initializes the table data structure and creates header rows
     * with column titles, sorting options, and grade type labels.
     *
     * @return void
     */
    private function setup_header() {
        global $USER, $OUTPUT;

        $this->data = array();
        $this->classes = array();

        $row1 = array();
        $row2 = array();

        $timing = 'before';
        $s = $this->startcolumns[$timing];

        if ($this->domid == 'gradetableteacher' && $this->va->va->training) {
            $n = 1;
            $row1[$s + $n + 1] = get_string('training', 'videoassessment');

            if ($this->va->is_graded_by_current_user($USER->id, $timing . 'training', $USER->id)) {
                $button = 'assessagain';
            } else {
                $button = 'firstassess';
            }

            $url = new \moodle_url($this->va->viewurl,
                array('action' => 'assess', 'userid' => $USER->id, 'gradertype' => 'training'));

            $row2[$s + $n + 1] = $OUTPUT->action_link($url,
                get_string($button, 'videoassessment'), null,
                array('class' => 'button-' . $button));

        } else {
            $n = 0;
        }

        $row1[$s + $n + 2] = get_string('class', 'videoassessment');
        $row1[$s + $n + 3] = get_string('self', 'videoassessment');
        $row1[$s + $n + 4] = get_string('peer', 'videoassessment');
        $row1[$s + $n + 5] = get_string('teacher', 'videoassessment');
        $row1[$s + $n + 6] = get_string('total', 'videoassessment');

        if ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 0) {
            $row1[$s + $n + 7] = get_string('peerfairnessbonusfortable', 'videoassessment');
            $row1[$s + $n + 8] = get_string('finalscorefortable', 'videoassessment');
        } else if ($this->va->va->fairnessbonus == 0 && $this->va->va->selffairnessbonus == 1) {
            $row1[$s + $n + 7] = get_string('selffairnessbonusfortable', 'videoassessment');
            $row1[$s + $n + 8] = get_string('finalscorefortable', 'videoassessment');
        } else if ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 1) {
            $row1[$s + $n + 7] = get_string('selffairnessbonusfortable', 'videoassessment');
            $row1[$s + $n + 8] = get_string('peerfairnessbonusfortable', 'videoassessment');
            $row1[$s + $n + 9] = get_string('finalscorefortable', 'videoassessment');
        }
        $params = array('id' => $this->cm->id);
        $group = optional_param('group', null, PARAM_INT);
        $nsort = optional_param('nsort', null, PARAM_INT);

        if (!empty($group)) {
            $params['group'] = $group;
        }
        if (empty($nsort)) {
            $nsort = self::ORDER_ASC;
            $arrow = '';
        } else {
            if ($nsort == self::ORDER_ASC) {
                $nsort = self::ORDER_DESC;
                $arrow = '<i class="fa fa-caret-up"></i>';
            } else {
                $nsort = self::ORDER_ASC;
                $arrow = '<i class="fa fa-caret-down"></i>';
            }
        }

        $params['nsort'] = $nsort;
        $url = new \moodle_url('/mod/videoassessment/view.php', $params);

        $row2[$s] = '<a href="' . $url . '" class="name-sort">' . get_string("namesort", "videoassessment") . $arrow . '</a>';
        $row2[$s + 1] = get_string('weighting', 'videoassessment');
        $row2[$s + $n + 2] = $this->va->va->ratingclass . '%';
        $row2[$s + $n + 3] = $this->va->va->ratingself . '%';
        $row2[$s + $n + 4] = $this->va->va->ratingpeer . '%';
        $row2[$s + $n + 5] = $this->va->va->ratingteacher . '%';

        if ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 0) {
            $row2[$s + $n + 7] = $this->va->va->bonuspercentage . '%';
            $row2[$s + $n + 8] = '100';
        } else if ($this->va->va->fairnessbonus == 0 && $this->va->va->selffairnessbonus == 1) {
            $row2[$s + $n + 7] = $this->va->va->selfbonuspercentage . '%';
            $row2[$s + $n + 8] = '100';
        } else if ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 1) {
            $row2[$s + $n + 7] = $this->va->va->selfbonuspercentage . '%';
            $row2[$s + $n + 8] = $this->va->va->bonuspercentage . '%';
            $row2[$s + $n + 9] = '100';
        }

        $this->add_data($row1);
        $this->add_data($row2);
    }

    /**
     * Add a row of data to the table.
     *
     * Appends a new row with optional CSS classes to the table data structure.
     *
     * @param array $row Array of cell data for the row
     * @param string|null $class Optional CSS class for the row
     * @return void
     */
    private function add_data($row, $class = null) {
        $this->data[] = $row;
        $this->classes[] = $class;
    }

    /**
     * Add user data row to the grade table.
     *
     * Creates a comprehensive row for a user including grades, video thumbnails,
     * assessment buttons, and action links based on user permissions and context.
     *
     * @param \stdClass $user User object with grade data
     * @return void
     */
    private function add_user_data($user) {
        global $DB, $OUTPUT, $USER, $PAGE;

        $PAGE->requires->strings_for_js(array(
            'clickonthe',
            'or',
            'assessagain',
            'firstassess',
            'donotclickhere',
        ), 'mod_videoassessment');
        if (!isset($user->picture)) {
            $picturefields = \core_user\fields::get_picture_fields();
            $tmp = $DB->get_record('user', array('id' => $user->userid), implode(',', $picturefields));
            foreach ($picturefields as $field) {
                $user->$field = $tmp->$field;
            }
        }

        $row = array();
        $class = array();
        $row[0] = $OUTPUT->user_picture($user) . ' ' . fullname($user);
        if ($this->va->is_user_graded($user->id)
            && ($this->va->is_teacher() || $user->id == $USER->id)) {
            $row[0] .= \html_writer::empty_tag('br')
                . $OUTPUT->action_link(new \moodle_url($this->va->viewurl,
                    array('action' => 'report', 'userid' => $user->id)),
                    va::str('seereport'));

            $url = new \moodle_url('/mod/videoassessment/print.php',
                array('id' => $this->va->cm->id, 'action' => 'report', 'userid' => $user->id));
            $row[0] .= \html_writer::empty_tag('br')
                . $OUTPUT->action_link($url, va::str('printreport'),
                    new \popup_action('click', $url, 'popup',
                        array('width' => 800, 'height' => 700, 'menubar' => true)));

            if ($this->va->is_teacher()) {
                $row[0] .= \html_writer::empty_tag('br')
                    . $OUTPUT->action_link(new \moodle_url('/mod/videoassessment/managegrades.php',
                        array('id' => $this->va->cm->id, 'userid' => $user->id)),
                        va::str('managegrades'));
            }
        }
        $strdownload = get_string('download');
        $mobile = va::uses_mobile_upload();

        $timing = 'before';
        $s = $this->startcolumns[$timing];

        if ($this->domid == 'gradetableteacher') {
            if ($this->va->va->training) {
                $n = 1;
                $passed = $DB->get_field('videoassessment_aggregation', 'passtraining', array(
                    'videoassessment' => $this->va->va->id,
                    'userid' => $user->id,
                ));

                if ($passed == 1) {
                    $row[$s + $n + 1] = va::str('passed');
                } else {
                    if ($this->va->is_graded_by_current_user($user->id, 'beforetraining', $user->id)) {
                        $row[$s + $n + 1] = va::str('failed');
                    } else {
                        $row[$s + $n + 1] = va::str('notattempted');
                    }
                }
            } else {
                $n = 0;
            }

            $class[$s + $n + 1] = 'mark';
        } else {
            $n = 0;
        }

        if ($this->va->va->class && !has_capability('mod/videoassessment:grade', $this->va->context)) {
            $row[$s + $n + 2] = $this->emptygradetext;
        } else {
            $row[$s + $n + 2] = $this->format_grade($user->{'grade' . $timing . 'class'});
        }
        $row[$s + $n + 3] = $this->format_grade($user->{'grade' . $timing . 'self'});
        $row[$s + $n + 4] = $this->format_grade($user->{'grade' . $timing . 'peer'});
        $row[$s + $n + 5] = $this->format_grade($user->{'grade' . $timing . 'teacher'});
        $row[$s + $n + 6] = $user->{'grade' . $timing . 'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->{'grade' . $timing});

        if ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 0) {
            $row[$s + $n + 7] = $user->fairnessbonus == 0 ? $this->format_grade(null) : $this->format_grade($user->fairnessbonus);
            $class[$s + $n + 7] = $class[$s + $n + 8] = 'totalmark';
            $row[$s + $n + 8] = $user->{'grade' . $timing . 'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->finalscore);
        } else if ($this->va->va->fairnessbonus == 0 && $this->va->va->selffairnessbonus == 1) {
            $row[$s + $n + 7] = $user->selffairnessbonus == 0 ? $this->format_grade(null) : $this->format_grade($user->selffairnessbonus);
            $class[$s + $n + 7] = $class[$s + $n + 8] = 'totalmark';
            $row[$s + $n + 8] = $user->{'grade' . $timing . 'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->finalscore);

        } else if ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 1) {
            $row[$s + $n + 7] = $user->selffairnessbonus == 0 ? $this->format_grade(null) : $this->format_grade($user->selffairnessbonus);
            $row[$s + $n + 8] = $user->fairnessbonus == 0 ? $this->format_grade(null) : $this->format_grade($user->fairnessbonus);
            $class[$s + $n + 7] = $class[$s + $n + 8] = $class[$s + $n + 9] = 'totalmark';
            $row[$s + $n + 9] = $user->{'grade' . $timing . 'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->finalscore);
        }

        $class[0] = 'user';
        $class[$s + $n + 2] = $class[$s + $n + 3] = $class[$s + $n + 4] = $class[$s + $n + 5] = 'mark';
        $class[$s + $n + 6] = 'totalmark';

        if ($video = $this->va->get_associated_video($user->id, $timing)) {
            $url = $video->get_url(true);
            if ($video->data->tmpname == 'Youtube') {
                $content =
                    '<div class="youtube-div"><img style="width:140px;height:90px;" src='
                    . $video->data->thumbnailname
                    . ' /><p class="youtube-remind youtube-remind-left">Video in Youtube</p></div>';
            } else {
                $content = $video->render_thumbnail(va::str('previewvideo'));
            }

            if ($this->domid == 'gradetableclass' && $this->va->va->class) {
                $viewurl = new \moodle_url($this->va->viewurl,
                    array('action' => 'assess', 'userid' => $user->id, 'gradertype' => 'class'));
            } else {
                $viewurl = new \moodle_url($this->va->viewurl,
                    array('action' => 'assess', 'userid' => $user->id));
            }
            $newspan = '';
            if ($this->is_emptygrade($user->{'grade' . $timing . 'teacher'}) || $video->data->timecreated > $user->timemodified) {
                $newspan = '<span>★new</span>';
            }

            $row[$s + 1] = \html_writer::tag('div',
                $OUTPUT->action_link($viewurl, $content, null) . $newspan);
            $flag = $this->availability_date_check($this->va->va);
            if (($this->va->is_teacher() ||
                $user->id == $USER->id)) {
                $newbuttonclass = '';
                if ($flag == 1) {
                    $newbuttonclass = 'button-assessagain';
                } else if ($flag == 2) {
                    $newbuttonclass = 'button-firstassess';
                } else if ($flag == -1) {
                    $newbuttonclass = 'btn-secondary';
                }

                if ($video->data->tmpname == 'Youtube') {
                    $str = va::str('Reembedthelink');
                    $actionmodel = 1;
                    $btnclass = array('class' => 'button-upload ' . $newbuttonclass);
                    if ($flag == -1) {
                        $btnclass = array('class' => 'button-upload ' . $newbuttonclass, 'disabled' => 'disabled');
                    }
                    $row[$s + 1] .= \html_writer::tag('div',
                        $OUTPUT->action_link(
                            new \moodle_url($this->va->viewurl, array('action' => 'upload', 'user' => $user->id, 'timing' => $timing, 'actionmodel' => $actionmodel)),
                            $str, null, $btnclass)
                    );
                } else {
                    $str = $mobile ? va::str('retakevideo') : va::str('reuploadvideo');
                    $actionmodel = 2;
                    if ($mobile) {
                        $btnclass = array('class' => 'delete-video-button button-upload ' . $newbuttonclass);
                        if ($flag == -1) {
                            $btnclass = array('class' => 'delete-video-button button-upload ' . $newbuttonclass, 'disabled' => 'disabled');
                        }
                        $row[$s + 1] .= \html_writer::tag('div',
                            $OUTPUT->action_link(
                                new \moodle_url($this->va->viewurl, array('action' => 'deletevideo', 'user' => $user->id, 'videoid' => $video->data->id, 'sesskey' => sesskey())),
                                'Delete Video', null, $btnclass)
                        );
                    } else {
                        $btnclass = array('class' => 'button-upload ' . $newbuttonclass);
                        if ($flag == -1) {
                            $btnclass = array('class' => 'button-upload ' . $newbuttonclass, 'disabled' => 'disabled');
                        }
                        $row[$s + 1] .= \html_writer::tag('div',
                            $OUTPUT->action_link(
                                new \moodle_url($this->va->viewurl, array('action' => 'upload', 'user' => $user->id, 'timing' => $timing, 'actionmodel' => $actionmodel)),
                                $str, null, $btnclass)
                        );
                    }

                }

            }
            if ($video->data->tmpname != 'Youtube' && $this->va->is_teacher()) {
                $row[$s + 1] .= \html_writer::tag('div',
                    $OUTPUT->action_link($url, $strdownload, null, array('class' => 'button-download')),
                    array('style' => 'margin-top:5px'));
            }
        } else {
            if ($this->va->is_teacher() ||
                $user->id == $USER->id && $this->is_emptygrade($user->{'grade' . $timing . 'peer'})
                && $this->is_emptygrade($user->{'grade' . $timing . 'teacher'})) {
                $str = $mobile ? va::str('takevideo') : va::str('uploadvideo');
                $actionmodel = 2;
                $row[$s + 1] = \html_writer::tag('div',
                    $OUTPUT->action_link(
                        new \moodle_url($this->va->viewurl, array('action' => 'upload', 'user' => $user->id, 'timing' => $timing, 'actionmodel' => $actionmodel)),
                        $str, null, array('class' => 'button-upload'))
                );
            } else {
                $row[$s + 1] = get_string('novideo', 'videoassessment');
            }
        }

        if ($this->domid == 'gradetableclass') {
            $type = 'class';
        } else {
            $type = $this->va->get_grader_type($user->id);
        }

        if ($type) {
            switch ($type) {
                case 'self':
                    $linkcell = $s + $n + 3;
                    break;
                case 'peer':
                    $linkcell = $s + $n + 4;
                    break;
                case 'teacher':
                    $linkcell = $s + $n + 5;
                    break;
                case 'class':
                    $linkcell = $s + $n + 2;
                    break;
            }

            if ($this->va->is_graded_by_current_user($user->id, $timing . $type)) {
                $button = 'assessagain';
            } else {
                $button = 'firstassess';
            }

            if ($this->domid == 'gradetableclass' && $this->va->va->class) {
                $url = new \moodle_url($this->va->viewurl,
                    array('action' => 'assess', 'userid' => $user->id, 'gradertype' => 'class'));
            } else {
                $url = new \moodle_url($this->va->viewurl,
                    array('action' => 'assess', 'userid' => $user->id));
            }

            if ($this->domid == 'gradetableclass' && !$this->va->va->class) {
                $row[$linkcell] .= '<br />';
            } else {
                $row[$linkcell] = $OUTPUT->action_link($url,
                        get_string($button, 'videoassessment'), null,
                        array('class' => 'button-' . $button)) . '<br />' . $row[$linkcell];
            }
        }
        $this->add_data($row, $class);
    }

    /**
     * Check video assessment availability based on date restrictions.
     *
     * Determines the current availability state of the video assessment
     * based on submission start date, due date, and cutoff date settings.
     *
     * @param \stdClass $va Video assessment instance object
     * @return int Availability state: -1 (closed), 1 (open), 2 (overdue)
     */
    private function availability_date_check($va) {
        $time = time();
        if ($va->allowsubmissionsfromdate != 0 || $va->cutoffdate == 0) {
            if ($time < $va->allowsubmissionsfromdate) {
                $vaavailabilitystate = -1;
            } else {
                $vaavailabilitystate = 1;
            }
        } else if ($va->allowsubmissionsfromdate == 0 || $va->cutoffdate != 0) {
            if ($time < $va->cutoffdate) {
                $vaavailabilitystate = 1;
            } else {
                $vaavailabilitystate = -1;
            }
        } else if ($va->allowsubmissionsfromdate != 0 || $va->cutoffdate != 0) {
            if ($time > $va->allowsubmissionsfromdate && $time < $va->cutoffdate) {
                $vaavailabilitystate = 1;
            } else {
                $vaavailabilitystate = -1;
            }
        } else {
            $vaavailabilitystate = 1;
        }

        if ($vaavailabilitystate == 1) {
            if ($va->duedate != 0) {
                if ($time > $va->duedate) {
                    $vaavailabilitystate = 2;
                }
            }
        }
        return $vaavailabilitystate;

    }

    /**
     * Generate HTML output for the complete grade table.
     *
     * Converts the internal table data structure into HTML table markup
     * with proper styling, group menus, and responsive design elements.
     *
     * @return string Complete HTML markup for the grade table
     */
    private function print_html() {
        $params = array();
        if ($this->domid) {
            $params['id'] = $this->domid;
        }
        if ($this->domclass) {
            $params['class'] = $this->domclass;
        }

        $o = '';

        $o .= groups_print_activity_menu($this->va->cm, $this->va->viewurl, true);

        $o .= '<h3 class="center">' . $this->va->str('scores') . '</h3>';

        $o .= \html_writer::start_tag('table', $params);

        $columncount = 0;
        foreach ($this->data as $row) {
            $columncount = max($columncount, max(array_keys($row)) + 1);
        }

        $parity = 0;
        foreach ($this->data as $r => $row) {
            $o .= \html_writer::start_tag('tr', array('class' => 'r' . $parity));
            $parity ^= 1;

            for ($c = 0; $c < $columncount; $c++) {
                $text = '';
                $params = null;
                if (isset($this->data[$r][$c])) {
                    $text = $this->data[$r][$c];
                }
                if (!empty($this->classes[$r][$c])) {
                    $params['class'] = $this->classes[$r][$c];
                }
                $o .= \html_writer::tag('td', $text, $params);
            }
            $o .= \html_writer::end_tag('tr');
        }
        $o .= \html_writer::end_tag('table');

        return $o;
    }

    /**
     * Format grade value for display.
     *
     * Converts grade values to display format, showing empty grade text
     * for null or invalid grades.
     *
     * @param int|null $grade Grade value to format
     * @return string Formatted grade string for display
     */
    private function format_grade($grade) {
        if ($this->is_emptygrade($grade)) {
            return $this->emptygradetext;
        }
        return $grade;
    }

    /**
     * Check if a grade value is empty or invalid.
     *
     * Determines whether a grade value represents an empty or ungraded state
     * by checking for null values or the special -1 value.
     *
     * @param int|null $grade Grade value to check
     * @return bool True if grade is empty/invalid, false otherwise
     */
    private function is_emptygrade($grade) {
        return $grade == -1 || $grade === null;
    }
}
