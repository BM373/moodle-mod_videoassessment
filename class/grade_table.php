<?php
/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: grade_table.php 1031 2014-04-13 08:06:02Z malu $
 */

namespace videoassess;

use videoassess\form\assign_class;

require_once $CFG->dirroot . '/mod/videoassessment/class/form/assign_class.php';

class grade_table {

    CONST ORDER_ASC = 1;
    CONST ORDER_DESC = 2;

    /**
     * @var va
     */
    private $va;
    /**
     * @var int
     */
    public $instance;
    /**
     * @var \stdClass
     */
    public $data;
    /**
     * @var \stdClass
     */
    public $classes;
    /**
     * @var string
     */
    private $domid;
    /**
     * @var string
     */
    public $domclass = 'gradetable';
    /**
     * @var array
     */
    public $startcolumns = array('before' => 0, 'after' => 6);
    /**
     * @var string
    */
    public $usersort = 'u.firstname, u.lastname';
    /**
     * @var string
     */
    public $emptygradetext = '-';
    /**
     * @var string
     */
    public $hiddengradetext = '-';

    /**
     * @param va $videoassessment
     */
    function __construct(va $va) {
        $this->va = $va;
        $this->instance = $va->va->id;
        $this->cm = $va->cm;
    }

    /**
     * 教員用一覧表示
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
        if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        if ($users) {
            /* MinhTB VERSION 2 */
            $groupmode = groups_get_activity_groupmode($cm);
            $aag = has_capability('moodle/site:accessallgroups', $context);

            if ($groupmode == VISIBLEGROUPS or $aag) {
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
                $order_str = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';

                if ($nsort == self::ORDER_ASC) {
                    $order_str .= ' ASC';
                } else {
                    $order_str .= ' DESC';
                }

                $users = $this->va->get_students_sort($groupid, false, $order_str);
            } else {
                if ($sort == assign_class::SORT_MANUALLY) {
                    $users = $this->va->get_students_sort($groupid, true);
                } else {
                    if (in_array($sort, array(assign_class::SORT_ID, assign_class::SORT_NAME))) {
                        if ($sort == assign_class::SORT_ID) {
                            $order_str = ' ORDER BY u.id';
                        } else {
                            $order_str = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
                        }
                    } else {
                        $order_str = '';
                    }

                    $users = $this->va->get_students_sort($groupid, false, $order_str);
                }
            }
            /* End */

            foreach ($users as $user) {
                $agg = $this->va->get_aggregated_grades($user->id);
                    foreach (array(
                            'userid', 'gradebeforeteacher', 'gradebeforeself', 'gradebeforepeer', 'gradebeforeclass',
                            'gradebefore', 'videoassessment','fairnessbonus','selffairnessbonus','finalscore')
                            as $field) {
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
     * 学生用表示
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
     * 他学生一覧表示
     */
    public function print_peer_grade_table() {
        global $DB, $USER;

        $this->domid = 'gradetablepeer';

        $this->setup_header();

        /* MinhTB VERSION 2 */
        $cm = $this->cm;
        $context = $this->va->context;
        $groupmode = groups_get_activity_groupmode($cm);
        $aag = has_capability('moodle/site:accessallgroups', $context);

        if ($groupmode == VISIBLEGROUPS or $aag) {
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
            $order_str = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';

            if ($nsort == self::ORDER_ASC) {
                $order_str .= ' ASC';
            } else {
                $order_str .= ' DESC';
            }

            $peers = $this->va->get_peers_sort($groupid, $USER->id, false, $order_str);
        } else {
            if ($sort == assign_class::SORT_MANUALLY) {
                $peers = $this->va->get_peers_sort($groupid, $USER->id, true);
            } else {
                if (in_array($sort, array(assign_class::SORT_ID, assign_class::SORT_NAME))) {
                    if ($sort == assign_class::SORT_ID) {
                        $order_str = ' ORDER BY u.id';
                    } else {
                        $order_str = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
                    }
                } else {
                    $order_str = '';
                }

                $peers = $this->va->get_peers_sort($groupid, $USER->id, false, $order_str);
            }
        }
        /* End */

        foreach ($peers as $peer) {
            $user = $this->va->get_aggregated_grades($peer);
            $this->add_user_data($user);
            
            $row = count($this->data) - 1;
            
            $this->data[$row][3] = $this->hiddengradetext;
            $this->data[$row][5] = $this->hiddengradetext;
            $this->data[$row][6] = $this->hiddengradetext;
            $this->data[$row][7] = $this->hiddengradetext;
            $this->data[$row][8] = $this->hiddengradetext;
//            if ($this->va->va->delayedteachergrade) {
//                $row = count($this->data) - 1;
//                if ($user->gradebeforeself == -1) {
//                    $this->data[$row][4] = $this->hiddengradetext;
//                    $this->data[$row][5] = $this->hiddengradetext;
//                }
//            }
        }

        return $this->print_html();
    }
    
    /**
     * TienNV Version2
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
        if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }
        
        $peers = $this->va->get_peers($USER->id);
        
        if ($users) {
            /* MinhTB VERSION 2 */
            $groupmode = groups_get_activity_groupmode($cm);
            $aag = has_capability('moodle/site:accessallgroups', $context);

            if ($groupmode == VISIBLEGROUPS or $aag) {
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
                $order_str = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';

                if ($nsort == self::ORDER_ASC) {
                    $order_str .= ' ASC';
                } else {
                    $order_str .= ' DESC';
                }

                $users = $this->va->get_students_sort($groupid, false, $order_str);
            } else {
                if ($sort == assign_class::SORT_MANUALLY) {
                    $users = $this->va->get_students_sort($groupid, true);
                } else {
                    if (in_array($sort, array(assign_class::SORT_ID, assign_class::SORT_NAME))) {
                        if ($sort == assign_class::SORT_ID) {
                            $order_str = ' ORDER BY u.id';
                        } else {
                            $order_str = ' ORDER BY CONCAT(u.firstname, " ", u.lastname)';
                        }
                    } else {
                        $order_str = '';
                    }

                    $users = $this->va->get_students_sort($groupid, false, $order_str);
                }
            }
            /* End */
        
            foreach ($users as $user) {
                if($user->id == $USER->id) {
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

    /* MinhTB VERSION 2 08-03-2016 */
    /**
     * @author MinhTB VERSION 2
     *
     * get grade table for training pre-test
     */
    public function print_training_grade_table()
    {
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
            $tmp = $DB->get_record('user', array('id' => $user->userid), \user_picture::fields());
            foreach (explode(',', \user_picture::fields()) as $field) {
                $user->$field = $tmp->$field;
            }
        }

        $row = array();
        $row[0] = $OUTPUT->user_picture($user).' '.fullname($user);
        $row[1] = '';

        $data = $DB->get_record('videoassessment_videos', array('id' => $this->va->va->trainingvideoid));
        if ($data) {
            if ($video = new video($this->va->context, $data)) {
                $content = $video->render_thumbnail(va::str('previewvideo'));
                $row[1] = \html_writer::tag(
                    'a', $content, array(
                        'onclick' => 'M.mod_videoassessment.videos_show_video_training_preview('.$this->va->va->trainingvideoid.')',
                        'href' => 'javascript:void(0)'
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
                array('class' => 'button-'.$button)) . '<br />' . $row[2];
        
        $this->add_data($row);

        return $this->print_html();
    }

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

            if ($this->va->is_graded_by_current_user($USER->id, $timing.'training', $USER->id)) {
                $button = 'assessagain';
            } else {
                $button = 'firstassess';
            }

            $url = new \moodle_url($this->va->viewurl,
                array('action' => 'assess', 'userid' => $USER->id, 'gradertype' => 'training'));

            $row2[$s + $n + 1] = $OUTPUT->action_link($url,
                    get_string($button, 'videoassessment'), null,
                    array('class' => 'button-'.$button));

        } else {
            $n = 0;
        }

        $row1[$s + $n + 2] = get_string('class', 'videoassessment');
        $row1[$s + $n + 3] = get_string('self', 'videoassessment');
        $row1[$s + $n + 4] = get_string('peer', 'videoassessment');
        $row1[$s + $n + 5] = get_string('teacher', 'videoassessment');
        $row1[$s + $n + 6] = get_string('total', 'videoassessment');

        if($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 0){
            $row1[$s + $n + 7] = get_string('fairnessbonus', 'videoassessment');
            $row1[$s + $n + 8] = get_string('finalscore', 'videoassessment');
        }elseif ($this->va->va->fairnessbonus == 0 && $this->va->va->selffairnessbonus == 1){
            $row1[$s + $n + 7] = get_string('selffairnessbonus', 'videoassessment');
            $row1[$s + $n + 8] = get_string('finalscore', 'videoassessment');
        }elseif ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 1){
            $row1[$s + $n + 7] = get_string('selffairnessbonus', 'videoassessment');
            $row1[$s + $n + 8] = get_string('fairnessbonus', 'videoassessment');
            $row1[$s + $n + 9] = get_string('finalscore', 'videoassessment');
        }
        /* MinhTB VERSION 2 */
        $params = array('id' => $this->cm->id);
        $group = optional_param('group', null, PARAM_INT);
        $nsort = optional_param('nsort', null, PARAM_INT);

        if (!empty($group)) $params['group'] = $group;
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
        $row2[$s + $n + 2] = $this->va->va->ratingclass.'%';
        $row2[$s + $n + 3] = $this->va->va->ratingself.'%';
        $row2[$s + $n + 4] = $this->va->va->ratingpeer.'%';
        $row2[$s + $n + 5] = $this->va->va->ratingteacher.'%';

        if($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 0){
            $row2[$s + $n + 7] = $this->va->va->bonuspercentage.'%';
            $row2[$s + $n + 8] = '100';
        }elseif ($this->va->va->fairnessbonus == 0 && $this->va->va->selffairnessbonus == 1){
            $row2[$s + $n + 7] = $this->va->va->selfbonuspercentage.'%';
            $row2[$s + $n + 8] = '100';
        }elseif ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 1){
            $row2[$s + $n + 7] = $this->va->va->selfbonuspercentage.'%';
            $row2[$s + $n + 8] = $this->va->va->bonuspercentage.'%';
            $row2[$s + $n + 9] = '100';
        }
        /* End */

        $this->add_data($row1);
        $this->add_data($row2);
    }
    /* END MinhTB VERSION 2 08-03-2016 */

    /**
     *
     * @param array $row
     * @param string $class
     */
    private function add_data($row, $class = null) {
        $this->data[] = $row;
        $this->classes[] = $class;
    }

    /**
     *
     * @global \moodle_database $DB
     * @global \core_renderer $OUTPUT
     * @global \stdClass $USER
     * @param \stdClass $user
     */
    /* MinhTB VERSION 2 08-03-2016 */
    private function add_user_data($user) {
         global $DB, $OUTPUT, $USER, $PAGE;
        
         $PAGE->requires->strings_for_js(array(
        		            'clickonthe',
        		            'or',
        		            'assessagain',
        		            'firstassess',
         					'donotclickhere'
        		       ), 'mod_videoassessment');
        if (!isset($user->picture)) {
            $tmp = $DB->get_record('user', array('id' => $user->userid), \user_picture::fields());
            foreach (explode(',', \user_picture::fields()) as $field) {
                $user->$field = $tmp->$field;
            }
        }

        $row = array();
        $class = array();
        $row[0] = $OUTPUT->user_picture($user).' '.fullname($user);
        if ($this->va->is_user_graded($user->id)
                && ($this->va->is_teacher() || $user->id == $USER->id)) {
            $row[0] .= \html_writer::empty_tag('br')
                .$OUTPUT->action_link(new \moodle_url($this->va->viewurl,
                        array('action' => 'report', 'userid' => $user->id)),
                        va::str('seereport'));
						
			$url = new \moodle_url('/mod/videoassessment/print.php',
						array('id' => $this->va->cm->id, 'action' => 'report', 'userid' => $user->id));
			$row[0] .= \html_writer::empty_tag('br')
				.$OUTPUT->action_link($url, va::str('printreport'),
						new \popup_action('click', $url, 'popup',
                            array('width' => 800, 'height' => 700, 'menubar' => true)));
						
            if ($this->va->is_teacher()) {
                $row[0] .= \html_writer::empty_tag('br')
                    .$OUTPUT->action_link(new \moodle_url('/mod/videoassessment/managegrades.php',
                            array('id' => $this->va->cm->id, 'userid' => $user->id)),
                            va::str('managegrades'));
            }
        }
        //$pixresource = new \pix_icon('icon', get_string('pluginname', 'resource'), 'resource');
        //$pixdownload = new \pix_icon('t/download', get_string('download'));
        $strdownload = get_string('download');
        $mobile = va::uses_mobile_upload();

        $timing = 'before';
        $s = $this->startcolumns[$timing];

        if ($this->domid == 'gradetableteacher') {
            if ($this->va->va->training) {
                $n = 1;
                $passed = $DB->get_field('videoassessment_aggregation', 'passtraining', array(
                        'videoassessment' => $this->va->va->id,
                        'userid' => $user->id
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
            $row[$s + $n + 2] = $this->format_grade($user->{'grade'.$timing.'class'});
        }
        $row[$s + $n + 3] = $this->format_grade($user->{'grade'.$timing.'self'});
        $row[$s + $n + 4] = $this->format_grade($user->{'grade'.$timing.'peer'});
        $row[$s + $n + 5] = $this->format_grade($user->{'grade'.$timing.'teacher'});
//      $row[$s + $n + 6] = $this->format_grade($user->{'grade'.$timing});
        $row[$s + $n + 6] = $user->{'grade'.$timing.'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->{'grade'.$timing});

        if($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 0){
            $row[$s + $n + 7] = $user->fairnessbonus == 0 ? $this->format_grade(null) :$this->format_grade($user->fairnessbonus);
            $class[$s + $n + 7]  = $class[$s + $n + 8] = 'totalmark';
            $row[$s + $n + 8] = $user->{'grade'.$timing.'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->finalscore);
        }elseif ($this->va->va->fairnessbonus == 0 && $this->va->va->selffairnessbonus == 1){
            $row[$s + $n + 7] = $user->selffairnessbonus == 0 ? $this->format_grade(null) :$this->format_grade($user->selffairnessbonus);
            $class[$s + $n + 7]  = $class[$s + $n + 8] = 'totalmark';
            $row[$s + $n + 8] = $user->{'grade'.$timing.'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->finalscore);

        }elseif ($this->va->va->fairnessbonus == 1 && $this->va->va->selffairnessbonus == 1){
            $row[$s + $n + 7] = $user->selffairnessbonus == 0 ? $this->format_grade(null) :$this->format_grade($user->selffairnessbonus);
            $row[$s + $n + 8] = $user->fairnessbonus == 0 ? $this->format_grade(null) :$this->format_grade($user->fairnessbonus);
            $class[$s + $n + 7]  = $class[$s + $n + 8] = $class[$s + $n + 9] ='totalmark';
            $row[$s + $n + 9] = $user->{'grade'.$timing.'self'} === '-1' ? $this->format_grade(null) : $this->format_grade($user->finalscore);
        }

        $class[0] = 'user';
        $class[$s + $n + 2] = $class[$s + $n + 3] = $class[$s + $n + 4] = $class[$s + $n + 5] = 'mark';
        $class[$s + $n + 6] ='totalmark';

        if ($video = $this->va->get_associated_video($user->id, $timing)) {
            $url = $video->get_url(true);
            if($video->data->tmpname == 'Youtube'){
            	$content = '<div class="youtube-div"><img style="width:140px;height:90px;" src='.$video->data->thumbnailname.' /><p class="youtube-remind youtube-remind-left">Video in Youtube</p></div>';
            }else{
            	$content = $video->render_thumbnail(va::str('previewvideo'));
            }
            if ($this->domid == 'gradetableclass' && $this->va->va->class) {
                $viewurl = new \moodle_url($this->va->viewurl,
                    array('action' => 'assess', 'userid' => $user->id, 'gradertype' => 'class'));
            } else {
                $viewurl = new \moodle_url($this->va->viewurl,
                    array('action' => 'assess', 'userid' => $user->id));
            }
            $row[$s + 1] = \html_writer::tag('div',
                $OUTPUT->action_link($viewurl, $content, null));

            if (($this->va->is_teacher() ||
                $user->id == $USER->id))
            {
                if($video->data->tmpname == 'Youtube'){
                	$str = va::str('Reembedthelink');
                	$actionModel = 1;
                    $row[$s + 1] .= \html_writer::tag('div',
                        $OUTPUT->action_link(
                            new \moodle_url($this->va->viewurl, array('action' => 'upload', 'user' => $user->id, 'timing' => $timing, 'actionmodel'=>$actionModel)),
                            $str, null, array('class' => 'button-upload'))
                    );
                }else{
                	$str = $mobile ? va::str('retakevideo') : va::str('reuploadvideo');
                	$actionModel = 2;
                	if($mobile){
//                        $row[$s + 1] .= \html_writer::tag('div', $str,array('class' => 'retabke-button-upload button-upload'));
                        $row[$s + 1] .= \html_writer::tag('div',
                            $OUTPUT->action_link(
                                new \moodle_url($this->va->viewurl, array('action' => 'deletevideo', 'user' => $user->id, 'videoid' => $video->data->id,)),
                                'Delete Video', null, array('class' => 'delete-video-button button-upload'))
                        );
                    }else{
                        $row[$s + 1] .= \html_writer::tag('div',
                            $OUTPUT->action_link(
                                new \moodle_url($this->va->viewurl, array('action' => 'upload', 'user' => $user->id, 'timing' => $timing, 'actionmodel'=>$actionModel)),
                                $str, null, array('class' => 'button-upload'))
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
                $user->id == $USER->id && $this->is_emptygrade($user->{'grade'.$timing.'peer'})
                                       && $this->is_emptygrade($user->{'grade'.$timing.'teacher'}))
            {
                $str = $mobile ? va::str('takevideo') : va::str('uploadvideo');
                $actionModel = 2;
                $row[$s + 1] = \html_writer::tag('div',
                    $OUTPUT->action_link(
                        new \moodle_url($this->va->viewurl, array('action' => 'upload', 'user' => $user->id, 'timing' => $timing, 'actionmodel'=>$actionModel)),
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

            //if ($user->{'grade'.$timing.$type} > -1) {
            if ($this->va->is_graded_by_current_user($user->id, $timing.$type)) {
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
            
            if($this->domid == 'gradetableclass' && !$this->va->va->class) {
                $row[$linkcell] .= '<br />';
            } else {
                $row[$linkcell] = $OUTPUT->action_link($url,
                        get_string($button, 'videoassessment'), null,
                        array('class' => 'button-'.$button)) . '<br />' . $row[$linkcell];
            }

//             if ($type == 'teacher') {
//                 if ($this->va->is_graded_by_current_user($user->id, $timing.'training')) {
//                     $button = 'assessagain';
//                 } else {
//                     $button = 'firstassess';
//                 }

//                 $url = new \moodle_url($this->va->viewurl,
//                         array('action' => 'trainingresult', 'userid' => $user->id));
//                 $button = 'viewresult';
                
//                 $row[$s + $n + 1] = $OUTPUT->action_link($url,
//                         get_string($button, 'videoassessment'), null,
//                         array('class' => 'button-'.$button)) . '<br />' . $row[$s + $n + 1];
//             }
        }
        $this->add_data($row, $class);
    }
    /* END MinhTB VERSION 2 08-03-2016 */

    /**
     *
     * @return string
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

        $o .= '<h3 class="center">'.$this->va->str('scores').'</h3>';

        $o .= \html_writer::start_tag('table', $params);

        $columncount = 0;
        foreach ($this->data as $row) {
            $columncount = max($columncount, max(array_keys($row)) + 1);
        }

        $parity = 0;
        foreach ($this->data as $r => $row) {
            $o .= \html_writer::start_tag('tr', array('class' => 'r'.$parity));
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
     *
     * @param int|null $grade
     * @return string
     */
    private function format_grade($grade) {
        if ($this->is_emptygrade($grade)) {
            return $this->emptygradetext;
        }
        return $grade;
    }

    /**
     * 
     * @param int|null $grade
     * @return boolean
     */
    private function is_emptygrade($grade) {
        return $grade == -1 || $grade === null;
    }
}
