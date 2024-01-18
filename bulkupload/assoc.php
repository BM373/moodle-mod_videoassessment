<?php
/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: assoc.php 823 2012-09-27 05:28:21Z yama $
 */

require_once '../../../config.php';
require_once $CFG->libdir.'/tablelib.php';
require_once $CFG->dirroot.'/mod/videoassessment/bulkupload/lib.php';
require_once $CFG->dirroot.'/filter/mediaplugin/filter.php';

$cmid = required_param('cmid', PARAM_INT);
$cm = $DB->get_record('course_modules', array('id' => $cmid));

$bulkupload = new videoassessment_bulkupload($cmid);
$bulkupload->require_capability();

$baseurl = '/mod/videoassessment/bulkupload/assoc.php';
$PAGE->set_url($baseurl, array('cmid' => $cmid));
$titlestr = get_string('videoassessment:associate', 'videoassessment');
$PAGE->set_title($titlestr);
$PAGE->set_heading($titlestr);
$PAGE->navbar->add($titlestr);

$PAGE->requires->js('/mod/videoassessment/videoassessment.js');
$PAGE->requires->js_init_call('M.mod_videoassessment.init_video_preview', array($cmid), false, videoassessment_get_js_module());
$PAGE->requires->js_init_call('M.mod_videoassessment.assoc_init', null, false, videoassessment_get_js_module());

$files = $bulkupload->get_files();
uasort($files, function($a, $b) { return strnatcasecmp($a->get_filename(), $b->get_filename()); });

$fs = get_file_storage();
if (optional_param('update', 0, PARAM_BOOL) and
    $formusers = optional_param_array('user', 0, PARAM_INT) and
    $formtimings = optional_param_array('timing', '', PARAM_ALPHA))
{
    $changed = 0;
    $error = '';
    foreach ($formusers as $key => $userid) {
        if (empty($formtimings[$key]) || !in_array($formtimings[$key], array('before', 'after'))) {
            continue;
        }
        if (empty($userid)) {
            continue;
        }
        $timing = $formtimings[$key];

        $file = $files[$key];
        if (list ($olduserid, $oldtiming) = videoassessment_get_assoc($file)) {
            // 既に関連づけられてるビデオはスキップ
            // (このページ内での関連づけ変更はせず、一覧ページで解除が必要)
        } else {
            // すでにビデオが関連付けされているユーザーはスキップ
            $context = context_module::instance($cm->id);
            $uservideos = $fs->get_directory_files(
                $context->id, 'mod_videoassessment', 'video', 0,
                '/'.$userid.'/'.$timing.'/');
            $uservideos = array_filter($uservideos, function (stored_file $file)
            {
                return preg_match(videoassessment_base::RE_VIDEOEXT, $file->get_filename());
            });
            if (!empty($uservideos)) {
                $user = $DB->get_record('user', array('id' => $userid));
                $error .= get_string('videoalreadyassociated', 'videoassessment', fullname($user)).'<br/>';
                continue;
            }

            $bulkupload->move_file($file,
                empty($userid)
                    ? '/'
                    : '/'.$userid.'/'.$timing.'/'
                );
            $changed++;
        }
    }
    redirect(new moodle_url($baseurl, array('cmid' => $cmid, 'changed' => $changed, 'error' => $error)));
} else if ($id = optional_param('delete', 0, PARAM_INT)) {
    if ($file = $fs->get_file_by_id($id)) {
        $file->delete();
    }
    redirect(new moodle_url($baseurl, array('cmid' => $cmid)));
}

echo $OUTPUT->header();

echo $OUTPUT->error_text(optional_param('error', '', PARAM_TEXT));

if ($changed = optional_param('changed', 0, PARAM_INT)) {
    $viewurl = new moodle_url('/mod/videoassessment/bulkupload/view.php', array('cmid' => $cmid));
    echo $OUTPUT->notification(
        html_writer::tag(
            'a', get_string('associated', 'videoassessment', $changed),
            array('href' => $viewurl, 'style' => 'font-size:150%;')
            ),
        'notifysuccess');
}

echo $OUTPUT->box_start();

$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
groups_print_activity_menu($cm, new moodle_url($baseurl, array('cmid' => $cm->id)));

echo '<form action="'.$CFG->wwwroot.$baseurl.'" method="post">'
    .'<input type="hidden" name="cmid" value="'.$cmid.'"/>';

$table = new flexible_table('assoc-users');
$table->define_baseurl($baseurl);
$columns = array('video', 'timemodified', 'user', 'timing', 'size', 'action');
$timingall = '<label><input type="radio" name="timingall" value="before" id="timingallbefore"/> '
    .get_string('before', 'videoassessment').'</label>'
    .' <label><input type="radio" name="timingall" value="after" id="timingallafter"/> '
    .get_string('after', 'videoassessment').'</label>';
$headers = array(
        get_string('video', 'videoassessment'),
        get_string('uploadedat', 'videoassessment'),
        get_string('user'),
        $timingall,
        get_string('size'),
        get_string('action')
);
$table->define_columns($columns);
$table->define_headers($headers);
$table->column_style('size', 'text-align', 'right');
$table->setup();

$context = context_module::instance($cmid);
$groupusers = get_enrolled_users($context, '', $currentgroup);

// 合計ファイルサイズはフィルタリング前の全てのビデオで計算
$totalsize = array_reduce($files,
    function ($sum, $file) { return $sum + (float)$file->get_filesize(); },
    0);

// まだ関連づけが済んでいないユーザーとビデオを抽出
$unassociatedusers = $groupusers;
$unassociatedfiles = array();
foreach ($files as $key => $file) {
    if (list ($userid, $timing) = videoassessment_get_assoc($file)) {
        unset($groupusers[$userid]);
    } else {
        $unassociatedfiles[$key] = $file;
    }
}

$useropts = array_map('fullname', $unassociatedusers);
$timingopts = array(
    'before' => get_string('before', 'videoassessment'),
    'after' => get_string('after', 'videoassessment')
    );

/* @var $file stored_file */
foreach ($unassociatedfiles as $key => $file) {
    $radios = array();
    foreach ($timingopts as $optval => $optlabel) {
        $attrs = array('type' => 'radio',
                       'name' => 'timing['.$key.']',
                       'value' => $optval);
        if ($optval == $timing) {
            $attrs += array('checked' => 'checked');
        }
        $radios[] = html_writer::tag(
            'label',
            html_writer::empty_tag('input', $attrs).' '.$optlabel
            );
    }

    $btndelete = $OUTPUT->action_link(
        new moodle_url($baseurl, array('cmid' => $cmid, 'delete' => $file->get_id())),
        $OUTPUT->pix_icon('t/delete', ''), null,
        array('onclick' => 'return confirm("'.get_string('reallydeletevideo', 'videoassessment').'")'));
    $thumbnailfilename = preg_replace('/\.[^.]+$/',
        videoassessment_bulkupload::THUMBNAIL_FORMAT, $file->get_filename());
    $thumbnailurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(), 'mod_videoassessment', 'video', 0,
        $file->get_filepath(), $thumbnailfilename);
    $table->add_data(
        array(
            html_writer::tag(
                'a', sprintf('<img src="%s" />', $thumbnailurl), array(
                    'href' => 'javascript:void(0)',
                    'onclick' => 'M.mod_videoassessment.assoc_preview_video(\''.$key.'\')'
                )
            ),
            userdate($file->get_timemodified()),
            html_writer::select($useropts, 'user['.$key.']', 0, array(0 => '')),
            implode(' ', $radios),
            display_size($file->get_filesize()),
            $btndelete
        )
    );
}

$table->add_data(array(get_string('total'), '', '', '', display_size($totalsize), ''));

$table->finish_output();

echo '<input type="submit" name="update" value="'.get_string('associate', 'videoassessment').'"/>'
    .'</form>';

echo $OUTPUT->box_end();

echo '<div id="videopreview"></div>';

echo html_writer::tag(
	'div',
	$OUTPUT->action_link(
		new moodle_url('/mod/videoassessment/bulkupload/view.php', array('cmid' => $cmid)),
		'&raquo; ' . get_string('videoassessment:associated', 'videoassessment')
		)
	);

echo $OUTPUT->footer();
