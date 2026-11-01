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
 * AI speech feedback: learners record speech and read AI comments on the
 * transcript; teachers see every learner's attempts.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_videoassessment\aifeedback\attempt_manager;
use mod_videoassessment\aifeedback\highlighter;
use mod_videoassessment\aifeedback\keyword_checker;
use mod_videoassessment\aifeedback\transcriber;

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$mine = optional_param('mine', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'videoassessment');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoassessment:view', $context);

$va = $DB->get_record('videoassessment', ['id' => $cm->instance], '*', MUST_EXIST);
$manager = new attempt_manager($va, $context);
$isteacher = has_capability('mod/videoassessment:grade', $context);
$cansubmit = has_capability('mod/videoassessment:submit', $context);
$siteready = attempt_manager::site_ready();
$policyaccepted = \core_ai\manager::get_user_policy_status($USER->id);

$url = new moodle_url('/mod/videoassessment/aifeedback.php', ['id' => $cm->id]);
if ($mine) {
    $url->param('mine', 1);
}
$PAGE->set_url($userid ? new moodle_url($url, ['userid' => $userid]) : $url);
$PAGE->set_title(format_string($va->name) . ': ' . get_string('aifeedback', 'videoassessment'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($va);

if (!$manager->is_enabled()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('aifb_notenabled', 'videoassessment'), 'info', false);
    echo $OUTPUT->continue_button(new moodle_url('/mod/videoassessment/view.php', ['id' => $cm->id]));
    echo $OUTPUT->footer();
    exit;
}

// Actions (all POST + sesskey).
if ($action === 'acceptpolicy') {
    require_sesskey();
    \core_ai\manager::user_policy_accepted($USER->id, $context->id);
    redirect($url);
}

if ($action === 'submit') {
    require_sesskey();
    require_capability('mod/videoassessment:submit', $context);
    if (!$siteready || !$policyaccepted) {
        redirect($url);
    }
    $upload = $_FILES['audio'] ?? null;
    try {
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            $toolarge = in_array($upload['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            throw new moodle_exception($toolarge ? 'aifb_error_toolarge' : 'aifb_error_nofile', 'videoassessment');
        }
        $manager->create_attempt(
            $USER->id,
            $upload['tmp_name'],
            clean_param($upload['name'], PARAM_FILE),
            optional_param('duration', 0, PARAM_INT)
        );
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url, get_string('aifb_submitted', 'videoassessment'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'retry') {
    require_sesskey();
    $attempt = $manager->get_attempt(required_param('attemptid', PARAM_INT));
    if ($attempt && ((int)$attempt->userid === (int)$USER->id || $isteacher)) {
        attempt_manager::retry($attempt);
    }
    redirect($userid ? new moodle_url($url, ['userid' => $userid]) : $url);
}

/**
 * Template context for one attempt.
 *
 * @param attempt_manager $manager The activity's attempt manager.
 * @param stdClass $attempt The attempt.
 * @param bool $isowner The viewer made this attempt.
 * @param bool $isteacher The viewer can grade.
 * @param int $cmid Course module id, for the retry form.
 * @return array
 */
function mod_videoassessment_aifb_attempt_context(
    attempt_manager $manager,
    stdClass $attempt,
    bool $isowner,
    bool $isteacher,
    int $cmid
): array {
    $status = (int)$attempt->status;
    $names = [
        attempt_manager::STATUS_QUEUED => 'queued',
        attempt_manager::STATUS_PROCESSING => 'processing',
        attempt_manager::STATUS_DONE => 'done',
        attempt_manager::STATUS_FAILED => 'failed',
    ];
    $name = $names[$status] ?? 'failed';
    $audiourl = $manager->get_audio_url($attempt);
    $data = [
        'id' => $attempt->id,
        'heading' => get_string('aifb_attempt', 'videoassessment', $attempt->attempt),
        'time' => userdate($attempt->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'duration' => $attempt->duration ? format_time((int)$attempt->duration) : '',
        'audiourl' => $audiourl ? $audiourl->out(false) : '',
        'statuslabel' => get_string('aifb_status_' . $name, 'videoassessment'),
        'statusclass' => $name,
        'pending' => in_array($status, [attempt_manager::STATUS_QUEUED, attempt_manager::STATUS_PROCESSING], true),
        'failed' => $status === attempt_manager::STATUS_FAILED,
        'done' => $status === attempt_manager::STATUS_DONE,
        'canretry' => $status === attempt_manager::STATUS_FAILED && ($isowner || $isteacher),
        'sesskey' => sesskey(),
        'cmid' => $cmid,
        'userid' => $isowner ? 0 : (int)$attempt->userid,
    ];
    if ($data['failed']) {
        $data['errortext'] = attempt_manager::error_text($attempt, $isteacher);
    }
    if ($data['done']) {
        $feedback = json_decode((string)$attempt->feedback, true) ?: [];
        $marks = [];
        $strengths = [];
        foreach ($feedback['strengths'] ?? [] as $i => $item) {
            $ref = 'S' . ($i + 1);
            $marks[] = ['ref' => $ref, 'type' => 'strength', 'quote' => $item['quote'] ?? ''];
            $strengths[] = ['ref' => $ref] + $item;
        }
        $improvements = [];
        foreach ($feedback['improvements'] ?? [] as $i => $item) {
            $ref = 'I' . ($i + 1);
            $marks[] = ['ref' => $ref, 'type' => 'improvement', 'quote' => $item['quote'] ?? ''];
            $improvements[] = ['ref' => $ref] + $item;
        }
        $rendered = highlighter::render((string)$attempt->transcript, $marks);
        foreach ($strengths as &$item) {
            $item['found'] = $rendered['found'][$item['ref']] ?? false;
        }
        unset($item);
        foreach ($improvements as &$item) {
            $item['found'] = $rendered['found'][$item['ref']] ?? false;
        }
        unset($item);
        $data += [
            'summary' => $feedback['summary'] ?? '',
            'transcripthtml' => $rendered['html'],
            'strengths' => $strengths,
            'hasstrengths' => !empty($strengths),
            'improvements' => $improvements,
            'hasimprovements' => !empty($improvements),
            'keywords' => $feedback['keywords'] ?? [],
            'haskeywords' => !empty($feedback['keywords']),
            'nextstep' => $feedback['nextstep'] ?? '',
        ];
        if ($isowner) {
            $manager->mark_viewed($attempt);
        }
    }
    return $data;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aifeedback', 'videoassessment'));
echo html_writer::div(
    html_writer::link(
        new moodle_url('/mod/videoassessment/view.php', ['id' => $cm->id]),
        get_string('aifb_backtoactivity', 'videoassessment')
    ),
    'mb-3'
);

if (!$siteready) {
    echo $OUTPUT->notification(
        get_string($isteacher ? 'aifb_sitenotready' : 'aifb_sitenotready_learner', 'videoassessment'),
        \core\output\notification::NOTIFY_WARNING,
        false
    );
}

// Teacher: one learner's attempts.
if ($isteacher && $userid) {
    $learner = core_user::get_user($userid, '*', MUST_EXIST);
    echo html_writer::div(
        html_writer::link($url, get_string('aifb_back', 'videoassessment')),
        'mb-2'
    );
    echo $OUTPUT->heading(fullname($learner), 3);
    $attempts = $manager->get_user_attempts($userid);
    if (!$attempts) {
        echo html_writer::tag('p', get_string('aifb_noattempts', 'videoassessment'));
    }
    $pending = [];
    foreach ($attempts as $index => $attempt) {
        $data = mod_videoassessment_aifb_attempt_context($manager, $attempt, false, true, $cm->id);
        $data['collapsed'] = $index > 0;
        echo $OUTPUT->render_from_template('mod_videoassessment/aifeedback_attempt', $data);
        if ($data['pending']) {
            $pending[] = ['id' => (int)$attempt->id, 'status' => (int)$attempt->status];
        }
    }
    $PAGE->requires->js_call_amd('mod_videoassessment/aifeedback', 'init', [['pending' => $pending, 'process' => true]]);
    echo $OUTPUT->footer();
    exit;
}

// Teacher: overview of all learners. A teacher who may also submit (e.g. a
// site admin) can open the learner page with ?mine=1 to try the feature.
if ($isteacher && !$mine) {
    $summary = $manager->get_summary();
    $learners = get_enrolled_users($context, 'mod/videoassessment:submit', 0, 'u.*', 'u.lastname, u.firstname');
    $table = new html_table();
    $table->head = [
        get_string('aifb_learner', 'videoassessment'),
        get_string('aifb_attemptscount', 'videoassessment'),
        get_string('aifb_donecount', 'videoassessment'),
        get_string('aifb_viewedcount', 'videoassessment'),
        get_string('aifb_firstattempt', 'videoassessment'),
        get_string('aifb_lastattempt', 'videoassessment'),
        '',
    ];
    $table->attributes['class'] = 'generaltable va-aifb-overview';
    foreach ($learners as $learner) {
        $row = $summary[$learner->id] ?? null;
        $table->data[] = [
            fullname($learner),
            $row ? (int)$row->attempts : 0,
            $row ? (int)$row->done : 0,
            $row ? (int)$row->viewed : 0,
            $row ? userdate($row->firstattempt, get_string('strftimedatetimeshort', 'langconfig')) : '-',
            $row ? userdate($row->lastattempt, get_string('strftimedatetimeshort', 'langconfig')) : '-',
            $row ? html_writer::link(
                new moodle_url($url, ['userid' => $learner->id]),
                get_string('aifb_viewattempts', 'videoassessment')
            ) : '',
        ];
    }
    echo $OUTPUT->heading(get_string('aifb_overview', 'videoassessment'), 3);
    echo html_writer::table($table);
    if ($cansubmit) {
        echo html_writer::link(
            new moodle_url($url, ['mine' => 1]),
            get_string('aifb_tryityourself', 'videoassessment'),
            ['class' => 'btn btn-secondary']
        );
    }
    echo $OUTPUT->footer();
    exit;
}

// Learner page.
echo html_writer::tag('p', get_string('aifb_intro', 'videoassessment'));

$instructions = trim((string)$va->aifeedbackinstructions);
$keywords = keyword_checker::parse_list((string)$va->aifeedbackkeywords);
if ($instructions !== '' || $keywords) {
    $task = '';
    if ($instructions !== '') {
        $task .= $OUTPUT->heading(get_string('aifb_task', 'videoassessment'), 4);
        $task .= format_text($instructions, FORMAT_PLAIN, ['context' => $context]);
    }
    if ($keywords) {
        $task .= $OUTPUT->heading(get_string('aifb_targetwords', 'videoassessment'), 4);
        $task .= html_writer::alist(array_map('s', $keywords), ['class' => 'va-aifb-targetwords']);
    }
    echo $OUTPUT->box($task, 'generalbox va-aifb-task');
}

if ($siteready && $cansubmit) {
    if (!$policyaccepted) {
        $policy = html_writer::tag('p', get_string('aifb_policyintro', 'videoassessment'));
        $policy .= html_writer::div(get_string('userpolicy', 'ai'), 'va-aifb-policytext');
        $policy .= html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
        $policy .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $policy .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'acceptpolicy']);
        $policy .= html_writer::tag('button', get_string('acceptai', 'ai'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        $policy .= html_writer::end_tag('form');
        echo $OUTPUT->box($policy, 'generalbox va-aifb-policy');
    } else {
        echo $OUTPUT->render_from_template('mod_videoassessment/aifeedback_recorder', [
            'actionurl' => $url->out(false),
            'sesskey' => sesskey(),
            'maxbytes' => transcriber::MAX_BYTES,
            'maxminutes' => 10,
            'accept' => 'audio/*,.' . implode(',.', attempt_manager::EXTENSIONS),
        ]);
    }
}

echo $OUTPUT->heading(get_string('aifb_attempts', 'videoassessment'), 3);
$attempts = $manager->get_user_attempts($USER->id);
if (!$attempts) {
    echo html_writer::tag('p', get_string('aifb_noattempts', 'videoassessment'));
}
$pending = [];
foreach ($attempts as $index => $attempt) {
    $data = mod_videoassessment_aifb_attempt_context($manager, $attempt, true, $isteacher, $cm->id);
    // Only the newest attempt is shown in full; older ones fold away.
    $data['collapsed'] = $index > 0;
    echo $OUTPUT->render_from_template('mod_videoassessment/aifeedback_attempt', $data);
    if ($data['pending']) {
        $pending[] = ['id' => (int)$attempt->id, 'status' => (int)$attempt->status];
    }
}
echo html_writer::tag('p', get_string('aifb_disclaimer', 'videoassessment'), ['class' => 'text-muted small']);

$PAGE->requires->js_call_amd('mod_videoassessment/aifeedback', 'init', [[
    'pending' => $pending,
    'process' => true,
    'maxseconds' => 600,
]]);
echo $OUTPUT->footer();
