<?php

use videoassess\va;

defined('MOODLE_INTERNAL') || die();

// Event types.
define('VIDEOASSESS_EVENT_TYPE_DUE', 'due');
define('VIDEOASSESS_EVENT_TYPE_GRADINGDUE', 'gradingdue');
/**
 *
 * @param stdClass $va
 * @param mod_videoassessment_mod_form $form
 * @return int
 */
function videoassessment_add_instance($va, $form)
{
    global $DB;
    if ($va->isquickSetup == 1) {
        if ($va->isselfassesstype == 1 || $va->ispeerassesstype == 1 || $va->isteacherassesstype == 1 || $va->isclassassesstype == 1) {
            if ($va->isselfassesstype == 1) {
                $va->ratingself = $va->selfassess;
            } else {
                $va->ratingself = 0;
            }
            if ($va->ispeerassesstype == 1) {
                $va->ratingpeer = $va->peerassess;
                if($va->peerassess == 0){
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
//        $cm->completionusegrade = 1;
//        $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
//        $DB->update_record('course_modules', $cm);
            $va->gradepass_videoassessment = $va->gradingsimpledirect;
            $va->gradepass = $va->gradingsimpledirect;
        }
    }
    if(!isset($va->gradepass_videoassessment) || !isset($va->gradepass)){
        $va->gradepass_videoassessment = 0;
        $va->gradepass = 0;
    }
    $va->id = $DB->insert_record('videoassessment', $va);
    update_calendar($va);
    return $va->id;
}

/**
 *
 * @param stdClass $va
 * @param mod_videoassessment_mod_form $form
 * @return boolean
 */
function videoassessment_update_instance($va, $form)
{
    global $DB, $CFG;

    $va->id = $va->instance;
    $cm = get_coursemodule_from_instance('videoassessment', $va->id, 0, false, MUST_EXIST);
    if ($va->isquickSetup == 1) {
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
            $va->gradepass_videoassessment = 0;
            $va->gradepass = 0;
        }

        if($va->advancedgradingmethod_beforeteacher == 'rubric'){
            $va->advancedgradingmethod_beforeteacher = '';
        }
        if($va->advancedgradingmethod_beforetraining == 'rubric'){
            $va->advancedgradingmethod_beforetraining = '';
        }
        if($va->advancedgradingmethod_beforepeer == 'rubric'){
            $va->advancedgradingmethod_beforepeer = '';
        }
        if($va->advancedgradingmethod_beforeclass == 'rubric'){
            $va->advancedgradingmethod_beforeclass = '';
        }
        if($va->advancedgradingmethod_beforeself == 'rubric'){
            $va->advancedgradingmethod_beforeself = '';
        }
    }else{
        if($va->ratingself > 0){
            $va->selfassess = $va->ratingself;
            $va->isselfassesstype =1;
        }else{
            $va->selfassess = 0;
            $va->isselfassesstype = 0;
        }
        if($va->ratingpeer > 0){
            $va->peerassess = $va->ratingpeer;
            $va->ispeerassesstype =1;
        }else{
            $va->peerassess = 0;
            $va->ispeerassesstype = 0;
        }

        if($va->ratingteacher > 0){
            $va->teacherassess = $va->ratingteacher;
            $va->isteacherassesstype =1;
        }else{
            $va->teacherassess = 0;
            $va->isteacherassesstype = 0;
        }
        if($va->ratingclass > 0){
            $va->classassess = $va->ratingclass;
            $va->isclassassesstype =1;
        }else{
            $va->classassess = 0;
            $va->isclassassesstype = 0;
        }
        $va->numberofpeers = $va->usedpeers;
    }


    $oldva = $DB->get_record('videoassessment', array('id' => $va->id));

    $DB->update_record('videoassessment', $va);
    update_calendar($va);
    if ($oldva->ratingteacher != $va->ratingteacher
        || $oldva->ratingself != $va->ratingself
        || $oldva->ratingpeer != $va->ratingpeer) {
        require_once $CFG->dirroot . '/mod/videoassessment/locallib.php';

        $course = $DB->get_record('course', array('id' => $va->course), '*', MUST_EXIST);
        $vaobj = new videoassess\va(context_module::instance($cm->id), $cm, $course);
        $vaobj->regrade();
    }

    return true;
}

/**
 *
 * @param int $id
 * @return boolean
 */
function videoassessment_delete_instance($id)
{
    global $DB;

    $DB->delete_records('videoassessment', array('id' => $id));
    $DB->delete_records('videoassessment_aggregation', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_grades', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_grade_items', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_peers', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_videos', array('videoassessment' => $id));
    $DB->delete_records('videoassessment_video_assocs', array('videoassessment' => $id));

    return true;
}

/**
 *
 * @param string $feature
 * @return boolean
 */
function videoassessment_supports($feature)
{
    switch ($feature){
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
 * @return array
 */
/* MinhTB VERSION 2 07-03-2016 */
function videoassessment_grading_areas_list()
{
    return array(
        'beforeteacher' => get_string('teacher', 'videoassessment'),
        'beforetraining' => get_string('trainingpretest', 'videoassessment'),
        'beforeself' => get_string('self', 'videoassessment'),
        'beforepeer' => get_string('peer', 'videoassessment'),
        'beforeclass' => get_string('class', 'videoassessment'),
    );
}

/* END MinhTB VERSION 2 07-03-2016 */

/**
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 */
function mod_videoassessment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload)
{
    global $CFG, $DB;

    $fullpath = "/{$context->id}/mod_videoassessment/$filearea/" . implode('/', $args);

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    // Self Assessment/Peer Assessment のために、他人のファイルの表示を許可する
    if (!has_capability('mod/videoassessment:gradepeer', $context)) {
        send_file_not_found();
    }

    \core\session\manager::write_close(); // unlock session during fileserving
    send_stored_file($file, HOURSECS, 0, $forcedownload);
}

function videoassessment_convert_video($event, $va)
{
    global $CFG, $DB, $USER;

    require_once $CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php';

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

                $DB->execute("UPDATE {videoassessment} SET trainingvideoid = ?, trainingvideo = 0 WHERE id = ?",
                    array($videoid, $va->id));
            }
        }
    }
}

/**
 * @param int $videoassessment
 * @param array $gradetypes
 * @return array
 */
function videoassessment_check_has_grade($videoassessment)
{
    global $DB;

    $hasgrade = array();
    $gradetypes = videoassessment_grading_areas_list();
    foreach ($gradetypes as $key => $gradetype) {
        $sql = 'SELECT * from {videoassessment_grade_items} WHERE videoassessment=? AND type like ?';
        $params = array($videoassessment, $key);
        $hasgrade[$key] = $DB->record_exists_sql($sql, $params);
    }

    return $hasgrade;
}

/**
 * @param int $contextid
 * @return array
 */
function videoassessment_get_areas($contextid)
{
    global $DB;

    $areas = array();
    $sql = 'SELECT id, areaname FROM {grading_areas} WHERE contextid = ?';
    $params = array($contextid);

    if ($arealists = $DB->get_records_sql($sql, $params)) {
        foreach ($arealists as $area) {
            $areas[$area->id] = $area->areaname;
        }
    }

    return $areas;
}

/*
 * @param int $id
 * @return string
 */
function videoassessment_get_areaname_by_id($id)
{
    global $DB;

    return $DB->get_field('grading_areas', 'areaname', array('id' => $id));
}

function show_intro($va)
{
    if ($va->showdescription ||
        time() > $va->allowsubmissionsfromdate) {
        return true;
    }
    return false;
}

function update_calendar($va)
{
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
    if (show_intro($va)) {
        $event->description = array(
            'text' => $intro,
            'format' => $instance->introformat
        );
    } else {
        $event->description = array(
            'text' => '',
            'format' => $instance->introformat
        );
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
        $params = array('modulename' => 'videoassessment', 'instance' => $instance->id, 'eventtype' => $eventtype);
        $event->id = $DB->get_field_select('event', 'id', $select, $params);

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', array('modulename' => 'videoassessment', 'instance' => $instance->id,
            'eventtype' => $eventtype));
    }

    $eventtype = VIDEOASSESS_EVENT_TYPE_GRADINGDUE;
    if ($instance->gradingduedate) {
        $event->name = get_string('calendargradingdue', 'videoassessment', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->gradingduedate;
        $event->timesort = $instance->gradingduedate;
        $event->id = $DB->get_field('event', 'id', array('modulename' => 'videoassessment',
            'instance' => $instance->id, 'eventtype' => $event->eventtype));

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', array('modulename' => 'videoassessment', 'instance' => $instance->id,
            'eventtype' => $eventtype));
    }

    return true;
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $quiznode
 * @return void
 */
function videoassessment_extend_settings_navigation($settings, navigation_node $videoassessmentnode)
{
    global $PAGE;
    $areaname = '';
    if (!empty($_GET['areaid'])) {
        $areaname = videoassessment_get_areaname_by_id($_GET['areaid']);
    }
    $hasgrade = videoassessment_check_has_grade($PAGE->cm->instance);
    $areas = videoassessment_get_areas($PAGE->cm->context->id);

    echo "<div class='check-has-grade hidden " . ($areaname ? $areaname : '') . "'>";
    echo '<input name="videoassessmentid" text="' . $PAGE->cm->instance . '">';
    if ($hasgrade) {
        foreach ($hasgrade as $key => $grade) {
            if ($areas) {
                foreach ($areas as $k => $area) {
                    if ($area == $key) {
                        echo "<input name='$key' value='$grade' text='$k'>";
                    }
                }
            } else {
                echo "<input name='$key' value='$grade'>";
            }
        }
    }

    echo "</div>";
    $PAGE->requires->jquery();
    $PAGE->requires->js('/mod/videoassessment/grademanage.js', true);

}
function mod_videoassessment_get_fontawesome_icon_map() {
    return [
        'mod_book:chapter' => 'fa-bookmark-o',
        'mod_book:nav_prev' => 'fa-arrow-left',
        'mod_book:nav_sep' => 'fa-minus',
        'mod_book:add' => 'fa-plus',
        'mod_book:nav_next' => 'fa-arrow-right',
        'mod_book:nav_exit' => 'fa-arrow-up'
    ];
}