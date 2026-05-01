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
 * The videoassess namespace definition.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * Core controller and helper class for the Video Assessment activity.
 *
 * Provides view routing, grading aggregation, peer management, and
 * data access for the `mod_videoassessment` module.
 *
 * @package   mod_videoassessment
 * @copyright 2024 Don Hinkleman (hinkelman@mac.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class va {
    /**
     * Component name for this plugin.
     *
     * @var string
     */
    const VA = 'videoassessment';

    /**
     * DB table name storing grade items for the activity.
     *
     * @var string
     */
    const TABLE_GRADE_ITEMS = 'videoassessment_grade_items';
    /**
     * DB table name storing individual grades.
     *
     * @var string
     */
    const TABLE_GRADES = 'videoassessment_grades';

    /**
     * Filter flag: show all videos.
     *
     * @var int
     */
    const FILTER_ALL = 0;
    /**
     * Filter flag: show submitted/associated videos.
     *
     * @var int
     */
    const FILTER_SUBMITTED = 1;
    /**
     * Filter flag: show videos requiring grading.
     *
     * @var int
     */
    const FILTER_REQUIRE_GRADING = 2;

    /**
     * Thumbnail file extension.
     *
     * @var string
     */
    const THUMBEXT = '.jpg';

    /**
     * Moodle 2.3 version code for conditional behavior.
     *
     * @var int
     */
    const MOODLE_VERSION_23 = 2012062500;

    /**
     *
     * @var \context_module
     */
    public $context;
    /**
     *
     * @var \stdClass
     */
    public $cm;
    /**
     *
     * @var \stdClass
     */
    public $course;
    /**
     *
     * @var int
     */
    public $instance;
    /**
     *
     * @var \mod_videoassessment_renderer|\core_renderer
     */
    public $output;
    /**
     *
     * @var \stdClass
     */
    public $va;
    /**
     *
     * @var \moodle_url
     */
    public $viewurl;
    /**
     *
     * @var string
     */
    public $action;
    /**
     *
     * @var array
     */
    public $jsmodule;
    /**
     *
     * @var array
     */
    public $timings = ['before'];
    /**
     *
     * @var array
     */
    public $gradertypes = ['self', 'peer', 'teacher', 'class', 'training'];
    /**
     *
     * @var array
     */
    public $gradingareas;

    /**
     * Create a new Video Assessment controller instance.
     *
     * Initialises context, course module references, renderer, JS strings,
     * and computes grading areas used throughout the module.
     *
     * @param \context_module $context Course module context for this activity
     * @param \cm_info|\stdClass $cm Course module record or info object
     * @param \stdClass $course Course record object
     * @throws \moodle_exception If the Video Assessment instance is not found
     */
    public function __construct(\context_module $context, $cm, \stdClass $course) {
        global $DB, $PAGE;

        $this->context = $context;
        $this->cm = $cm;
        $this->course = $course;
        $this->instance = $cm->instance;

        if (!($this->va = $DB->get_record('videoassessment', ['id' => $cm->instance]))) {
            throw new \moodle_exception('videoassessmentnotfound', self::VA);
        }

        $this->output = $PAGE->get_renderer('mod_videoassessment');
        $this->output->va = $this;

        $this->viewurl = new \moodle_url('/mod/videoassessment/view.php', ['id' => $this->cm->id]);

        $this->jsmodule = [
            'name' => 'mod_videoassessment',
            'fullpath' => '/mod/videoassessment/module.js',
            'requires' => ['panel', 'dd-plugin', 'json-stringify'],
        ];

        $PAGE->requires->strings_for_js([
            'liststudents',
            'unassociated',
            'associated',
            'before',
            'after',
            'saveassociations',
            'teacher',
            'self',
            'peer',
            'class',
            'reallyresetallpeers',
            'reallydeletevideo',
            'comment',
        ], 'videoassessment');

        $PAGE->requires->strings_for_js(['all'], 'moodle');

        foreach ($this->timings as $timing) {
            foreach ($this->gradertypes as $gradertype) {
                $this->gradingareas[] = $timing . $gradertype;
            }
        }
    }

    /**
     * Render the main view for the activity based on the requested action.
     *
     * Dispatches to sub-views (upload, peers, videos, assess, report, publish,
     * training result) and performs state-changing operations when requested.
     *
     * @param string $action Action key determining which view or operation to run
     * @return string HTML output for the selected view
     * @throws \moodle_exception If the session key is invalid for mutating actions
     */
    public function view($action = '') {
        global $PAGE;

        $this->action = $action;

        $mutatingactions = ['peeradd', 'peerdel', 'randompeer', 'assocadd', 'assocdel', 'videodel', 'deletevideo'];

        if (in_array($action, $mutatingactions)) {
            require_sesskey();
        }

        $o = '';
        switch ($action) {
            case 'peeradd':
                $this->view_peer_add();
                break;
            case 'peerdel':
                $this->view_peer_delete();
                break;
            case 'randompeer':
                $this->assign_random_peers();
                break;
            case 'assocadd':
                $this->view_assoc_add();
                break;
            case 'assocdel':
                $this->view_assoc_delete();
                break;
            case 'videoassoc':
                $this->view_video_associate();
                break;
            case 'videodel':
                $this->delete_video();
                break;
            case 'deletevideo':
                $this->delete_one_video_by_id();
                break;
            case 'downloadxls':
                $this->download_xls_report();
                break;
        }

        if ($action == '') {
            // Use a layout that displays a scrollbar for horizontally long pages.
            $PAGE->set_pagelayout('report');
            $PAGE->requires->css('/mod/videoassessment/view.css');
            $PAGE->requires->css('/mod/videoassessment/font/font-awesome/css/font-awesome.min.css');
        }

        if ($action == 'report' || $action == 'publish' || $action == 'upload') {
            $PAGE->requires->css('/mod/videoassessment/view.css');
            $PAGE->requires->css('/mod/videoassessment/getHTMLMediaElement.css');
        }

        if ($action == 'assess' || $action == 'trainingresult') {
            $PAGE->set_pagelayout('report');
            $PAGE->blocks->show_only_fake_blocks();
            $PAGE->requires->css('/mod/videoassessment/assess.css');
            $PAGE->add_body_class('assess-page');
        }
        $o .= $this->output->header($this);
        switch ($action) {
            case 'upload':
                $o .= $this->view_upload_video();
                break;
            case 'peers':
                $o .= $this->view_peers();
                break;
            case 'videos':
                $o .= $this->view_videos();
                break;
            case 'assess':
                $o .= $this->view_assess();
                break;
            case 'report':
                $o .= $this->view_report();
                break;
            case 'publish':
                $o .= $this->view_publish();
                break;
            case 'trainingresult':
                $o .= $this->view_result();
                break;
            default:
                $o .= $this->view_main();
                break;
        }
        $o .= $this->output->footer();
        return $o;
    }

    /**
     * Redirect to the module view with optional action and parameters.
     *
     * Updates the internal `viewurl` with the given action and additional
     * parameters, then performs a redirect to that URL.
     *
     * @param string $action Optional action name to add to the URL
     * @param array|null $params Optional query parameters to append
     * @return void
     */
    private function view_redirect($action = '', $params = null) {
        if ($action) {
            $this->viewurl->param('action', $action);
        }

        if ($params) {
            $this->viewurl->params($params);
        }

        redirect($this->viewurl);
    }

    /**
     * Send a notification to the student after video association/upload.
     *
     * Sends via registered email and/or QuickmailJPN if enabled by settings
     * and a teacher exists in the course context.
     *
     * @param int $cmid Course module id of the Video Assessment
     * @param string $timing The timing key, e.g. 'before' or 'after'
     * @return int|null 1 if any email was sent, 0 if attempted but not sent, null if not applicable
     */
    public function emailtostudent($cmid, $timing) {
        global $DB, $USER;
        $ismailsent = 0;
        $videoassessment = $DB->get_record("videoassessment", ["id" => $cmid]);
        if ($videoassessment->videonotification == 1 && has_capability('mod/videoassessment:submit', $this->context, $USER->id)) {
            if (
                (!$this->get_associated_video($USER->id, $timing) && $videoassessment->isfirstupload == 1) ||
                ($this->get_associated_video($USER->id, $timing) && $videoassessment->iswheneverupload == 1)
            ) {
                $context = \context_module::instance($cmid);
                $teachers = get_enrolled_users($context, 'mod/videoassessment:grade', null);
                $mailtemplate = $videoassessment->videonotificationtemplate;
                $url = new \moodle_url(
                    $this->viewurl,
                    ['action' => 'assess', 'userid' => current($teachers)->id]
                );
                $templatearray = [
                    "[[student name]]" => $USER->firstname . ' ' . $USER->lastname,
                    "[[insert link to self-assessment page]]" => $url->out(false),
                    "[[teacher name]]" => current($teachers)->firstname . ' ' . current($teachers)->lastname,
                ];

                foreach ($templatearray as $item => $template) {
                    $mailtemplate = str_replace($item, $template, $mailtemplate);
                }
                $quickmailresult = false;
                $registeredemailresult = false;
                if ($videoassessment->isregisteredemail == 1) {
                    $registeredemailresult = email_to_user(current($teachers), $USER, "", $mailtemplate);
                }
                if ($videoassessment->ismobilequickmail == 1) {
                    // NOTE: The Quickmail JPN block is optional.
                    // Only attempt to use its user table if the quickmailjpn plugin is installed
                    // to avoid errors when the block is missing.
                    $dbman = $DB->get_manager();
                    if ($dbman->table_exists('block_quickmailjpn_users')) {
                        $quickmail = $DB->get_record('block_quickmailjpn_users', ['userid' => current($teachers)->id]);
                        if (!empty($quickmail)) {
                            $mobileuser = current($teachers);
                            $mobileuser->email = $quickmail->mobileemail;
                            $quickmailresult = email_to_user($mobileuser, $USER, "", $mailtemplate);
                        }
                    }
                }
                if ($registeredemailresult || $quickmailresult) {
                    $ismailsent = 1;
                }
                return $ismailsent;
            }
        }
    }

    /**
     * Render the video upload page and handle upload/association actions.
     *
     * Handles YouTube URL association, direct uploads, and mobile uploads.
     * On successful non-AJAX actions, redirects to the appropriate view.
     *
     * @return string Rendered HTML for the upload form and page
     * @throws \moodle_exception When provided data is invalid or upload fails
     */
    private function view_upload_video() {
        global $CFG, $OUTPUT, $USER, $DB;
        require_once($CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php');

        $o = '';

        $form = new form\video_upload(null, (object) ['va' => $this]);

        if ($data = $form->get_data()) {
            $fs = get_file_storage();
            $upload = new \videoassessment_bulkupload($this->cm->id);

            if ((!empty($data->url) || !empty($data->mobileurl)) && $data->upload == 1) {
                if (empty($data->url)) {
                    $url = $data->mobileurl;
                } else {
                    $url = $data->url;
                }
                // Item #4: parse YouTube / Shorts / youtu.be URLs through
                // the dedicated helper so that `/shorts/ID` and `youtu.be/ID`
                // forms are accepted alongside the legacy `?v=ID`.
                $youtubeid = youtube_url::extract_id($url);
                if ($youtubeid === null) {
                    // Fall back to the legacy split for non-canonical URLs to
                    // preserve behaviour for hand-edited inputs that already
                    // worked before this refactor.
                    $urlarr = explode('=', $url);
                    $youtubeid = $urlarr[1] ?? '';
                }
                $ytinfo = $this->videoassessment_get_youtube_info($youtubeid);
                $videoid = $upload->youtube_video_data_add(
                    '/',
                    $ytinfo['title'],
                    $ytinfo['thumbnail_url'],
                    'Youtube',
                    $url
                );
                if ($this->is_teacher()) {
                    if (empty($data->user) || empty($data->timing)) {
                        $this->view_redirect('videos');
                    } else {
                        $this->associate_video($data->user, $data->timing, $videoid);
                        $this->view_redirect();
                    }
                } else {
                    if (empty($data->timing) || !in_array($data->timing, ['before', 'after'])) {
                        throw new \moodle_exception('invaliddata');
                    }
                    $this->associate_video($USER->id, $data->timing, $videoid);
                    $this->emailtostudent($this->cm->instance, $data->timing);
                    $this->view_redirect();
                }
            } else {
                if (optional_param('isRecordVideo', 0, PARAM_INT) == 1) {
                    $upload = new \videoassessment_bulkupload($this->cm->id);
                    $fileidx = 'video';
                    $filename = optional_param('video-filename', null, PARAM_FILE);
                    $tempname = $upload->get_temp_name($_FILES[$fileidx]['name']);
                    $upload->create_temp_dirs();
                    $tmppath = $upload->get_tempdir() . '/upload/' . $tempname;
                    if (!move_uploaded_file($_FILES[$fileidx]['tmp_name'], $tmppath)) {
                        throw new \moodle_exception('invaliduploadedfile', self::VA);
                    }
                    $videoid = $upload->video_data_add($tempname, $filename);
                    $upload->convert($tempname);
                    $action = "";
                    if ($this->is_teacher()) {
                        if (empty($data->user) || empty($data->timing)) {
                            $action = 'videos';
                            $this->view_redirect('videos');
                        } else {
                            $this->associate_video($data->user, $data->timing, $videoid);
                        }
                    } else {
                        if (empty($data->timing) || !in_array($data->timing, ['before', 'after'])) {
                            throw new \moodle_exception('invaliddata');
                        }
                        $this->associate_video($USER->id, $data->timing, $videoid);
                        $this->emailtostudent($this->cm->instance, $data->timing);
                    }
                    echo json_encode([
                        'action' => $action,
                    ]);
                    die;
                } else {
                    if (!empty($data->mobile)) {
                        if (empty($_FILES['mobilevideo'])) {
                            throw new \moodle_exception('erroruploadvideo', self::VA);
                        }
                        $upload->create_temp_dirs();
                        $tmpname = $upload->get_temp_name($_FILES['mobilevideo']['name']);
                        $tmppath = $upload->get_tempdir() . '/upload/' . $tmpname;
                        if (!move_uploaded_file($_FILES['mobilevideo']['tmp_name'], $tmppath)) {
                            throw new \moodle_exception('invaliduploadedfile', self::VA);
                        }

                        $videoid = $upload->video_data_add($tmpname, $_FILES['mobilevideo']['name']);

                        $upload->convert($tmpname);
                        $action = "";
                        if ($this->is_teacher()) {
                            if (empty($data->user) || empty($data->timing)) {
                                $action = 'videos';
                                $this->view_redirect('videos');
                            } else {
                                $this->associate_video($data->user, $data->timing, $videoid);
                                $this->view_redirect();
                            }
                        } else {
                            if (empty($data->timing) || !in_array($data->timing, ['before', 'after'])) {
                                throw new \moodle_exception('invaliddata');
                            }
                            $this->associate_video($USER->id, $data->timing, $videoid);
                            $this->emailtostudent($this->cm->instance, $data->timing);
                        }
                        echo json_encode([
                            'action' => $action,
                        ]);
                        die;
                    } else {
                        $files = $fs->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $data->video);
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

                            if ($this->is_teacher()) {
                                if (empty($data->user) || empty($data->timing)) {
                                    $this->view_redirect('videos');
                                } else {
                                    $this->associate_video($data->user, $data->timing, $videoid);
                                    $this->view_redirect();
                                }
                            } else {
                                if (empty($data->timing) || !in_array($data->timing, ['before', 'after'])) {
                                    throw new \moodle_exception('invaliddata');
                                }
                                $this->associate_video($USER->id, $data->timing, $videoid);
                                $this->emailtostudent($this->cm->instance, $data->timing);
                                $this->view_redirect();
                            }
                        }
                    }
                }
            }
        }
        ob_start();
        $form->display();
        $o .= ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Build minimal YouTube metadata used for display and thumbnails.
     *
     * @param string $videoid The YouTube video id
     * @return array{title:string,thumbnail_url:string} Title and thumbnail URL
     */
    public function videoassessment_get_youtube_info($videoid) {
        $ytarr = [
            'title' => 'video_id=' . $videoid,
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . $videoid . '/1.jpg',
        ];
        return $ytarr;
    }

    /**
     * Render and handle the Peers management page for teachers.
     *
     * Requires grading capability. Provides random assignment links and
     * add/delete controls for peer relationships.
     *
     * @return string Rendered HTML output for the peers page
     */
    private function view_peers() {
        global $DB, $PAGE, $OUTPUT;

        $this->teacher_only();

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'peersInit');

        $o = '';

        $url = $this->get_view_url('peers');
        $o .= groups_print_activity_menu($this->cm, $url, true);

        $o .= \html_writer::start_tag('div', ['class' => 'right'])
            . self::str('assignpeerassessorsrandomly')
            . ': ' . $this->output->action_link(
                $this->get_view_url('randompeer', ['peermode' => 'course', 'sesskey' => sesskey()]),
                self::str('course'),
                null,
                [
                        'class' => 'randompeerslink',
                        'onclick' => 'return require("mod_videoassessment/module").peersConfirmRandom();',
                    ]
            )
            . ' | ' . $this->output->action_link(
                $this->get_view_url('randompeer', ['peermode' => 'group', 'sesskey' => sesskey()]),
                self::str('group'),
                null,
                [
                        'class' => 'randompeerslink',
                        'onclick' => 'return require("mod_videoassessment/module").peersConfirmRandom();',
                    ]
            )
            . \html_writer::end_tag('div');

        $table = new \flexible_table('peers');
        $table->set_attribute('class', 'generaltable');
        $table->define_baseurl('/mod/videoassessment/view.php');
        $columns = [
            'name',
            'peers',
        ];
        $headers = [
            util::get_fullname_label(),
            self::str('peers'),
        ];
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();

        // Get students only (excluding teachers) for both table rows and dropdown options.
        $allusers = $this->get_students(null, 0);
        $users = $this->get_students();

        $delicon = new \pix_icon('t/delete', get_string('delete'));
        ob_start();
        foreach ($users as $user) {
            $peers = $DB->get_fieldset_select(
                'videoassessment_peers',
                'peerid',
                'videoassessment = :va AND userid = :userid',
                ['va' => $this->instance, 'userid' => $user->id]
            );
            $peernames = [];
            foreach ($peers as $peer) {
                $this->viewurl->params(['action' => 'peerdel', 'userid' => $user->id, 'peerid' => $peer, 'sesskey' => sesskey()]);
                // Look up peer name from allusers (includes teachers).
                $peername = isset($allusers[$peer]) ? fullname($allusers[$peer]) : 'User ' . $peer;
                @$peernames[] = $peername . ' ' . $OUTPUT->action_icon($this->viewurl, $delicon);
            }
            \core_collator::asort($peernames);
            $peercell = implode(\html_writer::empty_tag('br'), $peernames);

            // Include ALL users (including teachers) in dropdown options.
            $opts = [];
            foreach ($allusers as $candidate) {
                if ($candidate->id != $user->id && !in_array($candidate->id, $peers)) {
                    $opts[$candidate->id] = fullname($candidate);
                }
            }
            $this->viewurl->params(['action' => 'peeradd', 'userid' => $user->id, 'sesskey' => sesskey()]);
            $peercell .= $OUTPUT->single_select($this->viewurl, 'peerid', $opts, null, [self::str('addpeer')]);

            $row = [
                fullname($user),
                $peercell,
            ];
            $table->add_data($row);
        }
        $table->finish_output();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::start_tag('div', ['class' => 'center-btn']) . $this->output->action_link(
            new \moodle_url("/course/modedit.php", ['update' => $this->cm->id, 'return' => 1]),
            'save and return to settings',
            null,
            ['class' => 'btn btn-primary']
        ) . \html_writer::end_tag('div');
        return $o;
    }

    /**
     * Add a user to a peer group association.
     *
     * Reads required params `userid` and `peergroup` and creates a record
     * in `videoassessment_peer_assocs`, then redirects back to peers view.
     *
     * @return void
     */
    private function add_peer_member() {
        global $DB;

        $DB->insert_record('videoassessment_peer_assocs', (object) [
            'videoassessment' => $this->instance,
            'userid' => required_param('userid', PARAM_INT),
            'peergroup' => required_param('peergroup', PARAM_INT),
        ]);

        $this->view_redirect('peers');
    }

    /**
     * Create a direct peer relationship between two users.
     *
     * Inserts a record into `videoassessment_peers` using required params
     * `userid` and `peerid`, then redirects back to peers view.
     *
     * @return void
     */
    private function view_peer_add() {
        global $DB;

        $DB->insert_record('videoassessment_peers', (object) [
            'videoassessment' => $this->instance,
            'userid' => required_param('userid', PARAM_INT),
            'peerid' => required_param('peerid', PARAM_INT),
        ]);

        $this->view_redirect('peers');
    }

    /**
     * Remove a peer relationship and related grading data.
     *
     * Deletes associated grade items and grades for all timings for the
     * specified `userid` and `peerid`, then redirects to peers view.
     *
     * @return void
     */
    private function view_peer_delete() {
        global $DB;

        $userid = required_param('userid', PARAM_INT);
        $peerid = required_param('peerid', PARAM_INT);

        foreach ($this->timings as $timing) {
            if (
                $gradeitem = $DB->get_record('videoassessment_grade_items', [
                    'videoassessment' => $this->instance,
                    'type' => $timing . 'peer',
                    'gradeduser' => $userid,
                    'grader' => $peerid,
                ])
            ) {
                $DB->delete_records('videoassessment_grades', ['gradeitem' => $gradeitem->id]);
                $DB->delete_records('videoassessment_grade_items', ['id' => $gradeitem->id]);
            }
        }

        $DB->delete_records('videoassessment_peers', [
            'videoassessment' => $this->instance,
            'userid' => $userid,
            'peerid' => $peerid,
        ]);

        $this->view_redirect('peers');
    }

    /**
     * Create a new empty peer group for this activity.
     *
     * Inserts a record into `videoassessment_peer_groups` for the instance.
     *
     * @return void
     */
    private function add_peer_group() {
        global $DB;

        $DB->insert_record('videoassessment_peer_groups', (object) [
            'videoassessment' => $this->instance,
        ]);
    }

    /**
     * Render the videos administration page for teachers.
     *
     * Shows unassociated/associated/all uploaded videos with filters and
     * pagination and provides association management actions.
     *
     * @return string Rendered HTML for the videos list
     */
    private function view_videos() {
        global $OUTPUT, $PAGE;

        $this->teacher_only();

        $filter = optional_param('filter', 'unassociated', PARAM_ALPHA);

        $url = $this->get_view_url('videos');
        if ($filter) {
            $url->param('filter', $filter);
        }

        $o = '';

        $o .= groups_print_activity_menu($this->cm, $url, true);

        $opts = [
            'unassociated' => self::str('unassociated'),
            'associated' => self::str('associated'),
            'all' => get_string('all'),
        ];
        $o .= $OUTPUT->single_select(
            $this->get_view_url('videos'),
            'filter',
            $opts,
            $filter,
            null
        );

        $table = new \flexible_table('videos');
        $table->set_attribute('class', 'generaltable');
        $table->define_baseurl('/mod/videoassessment/videos.php');
        $columns = [
            'filepath',
            'originalname',
            'timecreated',
            'association',
            'operations',
        ];
        $headers = [
            self::str('video'),
            self::str('originalname'),
            self::str('uploadedtime'),
            self::str('associations'),
            self::str('operations'),
        ];
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();

        $thumbsize = self::get_thumbnail_size();

        $userfields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        $users = $this->get_students($userfields, 0);
        array_walk($users, function (\stdClass $a) {
            global $OUTPUT;
            $a->fullname = fullname($a);
            $a->assocvideos = [];
            $a->userpicture = $OUTPUT->user_picture($a);
        });

        $assocdata = [];

        $groupid = groups_get_activity_group($this->cm, true);
        $groupmembers = groups_get_members($groupid, 'u.id');

        $strtimings = [
            'before' => $this->timing_str('before'),
            'after' => $this->timing_str('after'),
        ];
        $strassociate = self::str('associate');
        $disassocicon = new \pix_icon('t/delete', self::str('disassociate'));
        ob_start();
        foreach ($this->get_videos() as $v) {
            $assocs = $this->get_video_associations($v->id);
            $assocdata[$v->id] = $assocs;

            if ($groupid && $assocs) {
                $groupmemberassociated = false;
                foreach ($assocs as $assoc) {
                    if (!empty($groupmembers[$assoc->associationid])) {
                        $groupmemberassociated = true;
                        break;
                    }
                }
                if (!$groupmemberassociated) {
                    continue;
                }
            }

            if ($filter == 'unassociated' && $assocs || $filter == 'associated' && !$assocs) {
                continue;
            }

            $imgname = $v->filename;
            $base = pathinfo($imgname, PATHINFO_FILENAME);
            $thumbname = $base . self::THUMBEXT;

            if ($v->tmpname == 'Youtube') {
                $attr = [
                    'src' => $v->thumbnailname,
                ];
            } else {
                $attr = [
                    'src' => \moodle_url::make_pluginfile_url(
                        $this->context->id,
                        'mod_videoassessment',
                        'video',
                        0,
                        $v->filepath,
                        $thumbname
                    ),
                ];
            }
            if ($thumbsize) {
                $attr['width'] = $thumbsize->width;
                $attr['height'] = $thumbsize->height;
            }
            $thumb = \html_writer::empty_tag('img', $attr);

            if ($v->tmpname == 'Youtube') {
                $videocell = '<a href=' . $v->originalname . ' id=' . $v->id . ' class="video-thumb" >' . $thumb . '</a>';
            } else {
                $videocell = $OUTPUT->action_link(
                    \moodle_url::make_pluginfile_url(
                        $this->context->id,
                        'mod_videoassessment',
                        'video',
                        0,
                        $v->filepath,
                        $v->filename
                    ),
                    $thumb,
                    null,
                    ['id' => 'video[' . $v->id . ']', 'class' => 'video-thumb']
                );
            }
            $assoccell = '';
            $assocusers = [];
            foreach ($assocs as $assoc) {
                if (!isset($users[$assoc->associationid])) {
                    continue;
                }
                $user = &$users[$assoc->associationid];
                $assocdelurl = new \moodle_url(
                    $url,
                    ['action' => 'assocdel', 'userid' => $user->id, 'timing' => $assoc->timing, 'sesskey' => sesskey()]
                );
                $assocusers[$user->id] = $user->userpicture . $user->fullname
                    . $OUTPUT->action_icon($assocdelurl, $disassocicon);
                $user->assocvideos[] = (int) $v->id;
            }
            \core_collator::asort($assocusers);
            $assoccell .= implode(\html_writer::empty_tag('br'), $assocusers);
            $assoccell .= \html_writer::empty_tag('br');
            $opts = [];
            foreach ($users as $candidate) {
                if ($groupid && empty($groupmembers[$candidate->id])) {
                    continue;
                }
                if (empty($assocusers[$candidate->id])) {
                    $opts[$candidate->id] = fullname($candidate);
                }
            }
            $assoccell .= \html_writer::start_tag(
                'form',
                ['method' => 'get', 'action' => $url->out_omit_querystring(true)]
            );
            $assoccell .= \html_writer::input_hidden_params(
                new \moodle_url($url, ['sesskey' => sesskey(), 'action' => 'assocadd', 'videoid' => $v->id, 'timing' => 'before'])
            );
            $assoccell .= \html_writer::select($opts, 'userid');
            $assoccell .= \html_writer::empty_tag('input', ['type' => 'submit', 'value' => $strassociate]);
            $assoccell .= \html_writer::end_tag('form');

            $opcell = $this->output->action_link(
                $this->get_view_url(
                    'videodel',
                    ['videoid' => $v->id, 'filter' => $filter, 'sesskey' => sesskey()]
                ),
                $this->output->pix_icon('t/delete', '') . ' ' . self::str('deletevideo'),
                null,
                ['class' => 'videodel']
            );

            $row = [
                $videocell,
                $v->originalname,
                userdate($v->timecreated),
                $assoccell,
                $opcell,
            ];
            $table->add_data($row);
        }

        $table->finish_output();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::tag('div', '', ['id' => 'assocpanel']);

        $form = new form\video_assoc(null, (object) [
            'cmid' => $this->cm->id,
        ]);
        ob_start();
        $form->display();
        $o .= ob_get_contents();
        ob_end_clean();

        $groupusers = $this->get_students();
        array_walk($groupusers, function ($groupuser) use ($users) {
            $user = $users[$groupuser->id];
            $groupuser->fullname = $user->fullname;
            $groupuser->assocvideos = $user->assocvideos;
            $groupuser->userpicture = $user->userpicture;
        });

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'videosInit', [$groupusers, $assocdata]);
        $PAGE->requires->strings_for_js([
            'liststudents',
            'unassociated',
            'associated',
            'before',
            'after',
            'saveassociations',
        ], 'videoassessment');
        $PAGE->requires->strings_for_js(['all'], 'moodle');

        return $o;
    }

    /**
     * Delete a video file and its associations, then redirect to videos list.
     *
     * Reads `videoid` from request, removes stored files and DB records, then
     * redirects back to the videos management page with current filter.
     *
     * @return void
     */
    private function delete_video() {
        global $DB;

        $videoid = required_param('videoid', PARAM_INT);

        $video = $DB->get_record('videoassessment_videos', ['id' => $videoid]);

        $fs = get_file_storage();

        $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $video->filepath, $video->filename);
        if ($file) {
            $file->delete();
        }

        $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $video->filepath, $video->thumbnailname);
        if ($file) {
            $file->delete();
        }

        $DB->delete_records('videoassessment_videos', ['id' => $videoid]);
        $DB->delete_records('videoassessment_video_assocs', ['videoid' => $videoid]);

        $this->view_redirect(
            'videos',
            ['filter' => optional_param('filter', 'unassociated', PARAM_ALPHA)]
        );
    }

    /**
     * Delete a single video by id provided in the request.
     *
     * Uses the `videoid` request param, deletes the stored file through the
     * `video` object API and removes DB records, then redirects to current view.
     *
     * @return void
     */
    public function delete_one_video_by_id() {
        global $DB;
        $videoid = required_param('videoid', PARAM_INT);

        $video = video::from_id($this->context, $videoid);
        $video->delete_file();

        $DB->delete_records('videoassessment_videos', ['id' => $videoid]);
        $DB->delete_records('videoassessment_video_assocs', ['videoid' => $videoid]);

        $this->view_redirect();
    }

    /**
     * Delete a single video by id.
     *
     * @param int $videoid Video identifier
     * @return void
     */
    public function delete_one_video($videoid) {
        global $DB;

        $video = video::from_id($this->context, $videoid);
        $video->delete_file();

        $DB->delete_records('videoassessment_videos', ['id' => $videoid]);
        $DB->delete_records('videoassessment_video_assocs', ['videoid' => $videoid]);
    }

    /**
     * Fetch all videos for this activity instance.
     *
     * @return array List of video records
     */
    public function get_videos() {
        global $DB;

        return $DB->get_records('videoassessment_videos', ['videoassessment' => $this->instance]);
    }

    /**
     * Fetch association records for a given video.
     *
     * @param int $videoid Video identifier
     * @return array List of association records
     */
    public function get_video_associations($videoid) {
        global $DB;

        return $DB->get_records('videoassessment_video_assocs', ['videoid' => $videoid]);
    }

    /**
     * Get the associated video object for a user and timing.
     *
     * @param int $userid User id whose video is requested
     * @param string $timing The timing key, e.g. 'before' or 'after'
     * @return video|null Video object or null if none exists
     */
    public function get_associated_video($userid, $timing) {
        global $DB;
        if (
            $assocs = $DB->get_records('videoassessment_video_assocs', [
            'videoassessment' => $this->instance,
            'timing' => $timing,
            'associationid' => $userid,
            ])
        ) {
            $assoc = reset($assocs);

            $data = $DB->get_record('videoassessment_videos', ['id' => $assoc->videoid]);
            if (!$data) {
                return null;
            }

            $video = new video($this->context, $data);

            if (isset($video->file) || $data->tmpname == 'Youtube') {
                return $video;
            }
        }
        return null;
    }

    /**
     * Determine grader type for the current user relative to graded user.
     *
     * @param int $gradeduserid The user being graded
     * @param string|null $gradertype Optional override type
     * @return string One of 'teacher', 'self', 'peer', or 'class'
     */
    public function get_grader_type($gradeduserid, $gradertype = null) {
        global $USER;

        if (!empty($gradertype)) {
            return $gradertype;
        }

        $peers = $this->get_peers($USER->id);

        if (has_capability('mod/videoassessment:grade', $this->context)) {
            return 'teacher';
        } else if ($gradeduserid == $USER->id) {
            return 'self';
        } else if (in_array($gradeduserid, $peers)) {
            return 'peer';
        }
        return 'class';
    }

    /**
     * Parse thumbnail size from ffmpeg thumbnail command configuration.
     *
     * @return \stdClass|null Object with width and height, or null if not set
     */
    private static function get_thumbnail_size() {
        global $CFG;

        if (preg_match('/(\d+)x(\d+)/', $CFG->videoassessment_ffmpegthumbnailcommand, $m)) {
            $size = new \stdClass();
            $size->width = $m[1];
            $size->height = $m[2];

            return $size;
        }

        return null;
    }

    /**
     * Associate a video with a user and timing.
     *
     * Replaces any existing association for the user and timing, then inserts
     * a new association record.
     *
     * @param int $userid User id to associate
     * @param string $timing Timing key, e.g. 'before' or 'after'
     * @param int $videoid Video identifier
     * @param int $associationtype Association type (default 1)
     * @return void
     */
    private function associate_video($userid, $timing, $videoid, $associationtype = 1) {
        global $DB;

        $this->disassociate_video($userid, $timing);
        $DB->insert_record('videoassessment_video_assocs', [
            'videoassessment' => $this->instance,
            'videoid' => $videoid,
            'associationtype' => $associationtype,
            'timing' => $timing,
            'associationid' => $userid,
            'timemodified' => time(),
        ]);
    }

    /**
     * Remove any video association for a user and timing.
     *
     * @param int $userid User id
     * @param string $timing Timing key
     * @return void
     */
    private function disassociate_video($userid, $timing) {
        global $DB;

        $DB->delete_records('videoassessment_video_assocs', [
            'videoassessment' => $this->instance,
            'timing' => $timing,
            'associationid' => $userid,
        ]);
    }

    /**
     * Assign random peers to users either per group or course-wide.
     *
     * Reads required params, computes mappings, replaces peer records, and
     * redirects back to the peers page.
     *
     * @return void
     */
    private function assign_random_peers() {
        global $DB;

        $peermode = required_param('peermode', PARAM_ALPHA);
        if ($peermode == 'group') {
            $groups = groups_get_all_groups($this->course->id);
            $groupids = array_keys($groups);
        } else {
            $groupids = [0];
        }

        // Always filter out teachers for both course-wide and group assignments.
        $coursecontext = \context_course::instance($this->course->id);

        // Get the student role ID.
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], 'id');
        if (!$studentrole) {
            return; // No student role found, cannot proceed.
        }

        // Get role IDs for non-student roles (teacher, editingteacher, manager).
        $excluderoles = $DB->get_records_select(
            'role',
            "shortname IN ('teacher', 'editingteacher', 'manager', 'coursecreator')",
            null,
            '',
            'id'
        );
        $excluderoleids = array_keys($excluderoles);

        foreach ($groupids as $groupid) {
            // Always filter teachers: use get_enrolled_users with capability check, then filter by role.
            $users = get_enrolled_users($this->context, 'mod/videoassessment:submit', $groupid, 'u.id');

            // Filter out teachers for both course-wide and group assignments.
            $userids = [];
            foreach ($users as $user) {
                // Check if user has student role.
                $hasstudentrole = user_has_role_assignment($user->id, $studentrole->id, $coursecontext->id);

                // Check if user has any excluded role.
                $hasexcludedrole = false;
                if (!empty($excluderoleids)) {
                    foreach ($excluderoleids as $roleid) {
                        if (user_has_role_assignment($user->id, $roleid, $coursecontext->id)) {
                            $hasexcludedrole = true;
                            break;
                        }
                    }
                }

                // Only include users with student role and without excluded roles.
                if ($hasstudentrole && !$hasexcludedrole) {
                    $userids[] = $user->id;
                }
            }

            // Skip if no users found.
            if (empty($userids)) {
                continue;
            }

            $mappings = $this->get_random_peers_for_users($userids, $this->va->usedpeers);

            foreach ($mappings as $id => $peers) {
                $DB->delete_records(
                    'videoassessment_peers',
                    ['videoassessment' => $this->instance, 'userid' => $id]
                );

                foreach ($peers as $peer) {
                    $row = new \stdClass();
                    $row->videoassessment = $this->instance;
                    $row->userid = $id;
                    $row->peerid = $peer;
                    $DB->insert_record('videoassessment_peers', $row);
                }
            }
        }

        $this->view_redirect('peers');
    }

    /**
     * Add or update a single user-video association via request parameters.
     *
     * Requires teacher role. Updates existing association or inserts new, then
     * redirects back to videos management view.
     *
     * @return void
     */
    private function view_assoc_add() {
        global $DB;

        $this->teacher_only();
        $videoid = required_param('videoid', PARAM_INT);
        $cond = [
            'videoassessment' => $this->instance,
            'associationtype' => 1,
            'associationid' => required_param('userid', PARAM_INT),
            'timing' => required_param('timing', PARAM_ALPHA),
        ];
        if (!empty($cond['associationid']) && !empty($cond['timing'])) {
            if ($id = $DB->get_field('videoassessment_video_assocs', 'id', $cond)) {
                $DB->set_field('videoassessment_video_assocs', 'videoid', $videoid, ['id' => $id]);
            } else {
                $record = $cond + ['videoid' => $videoid];
                $DB->insert_record('videoassessment_video_assocs', (object) $record);
            }
        }
        $this->view_redirect(
            'videos',
            ['filter' => optional_param('filter', 'unassociated', PARAM_ALPHA)]
        );
    }

    /**
     * Delete a user-video association via request parameters.
     *
     * Requires teacher role. Removes the association and redirects back to
     * videos management view.
     *
     * @return void
     */
    private function view_assoc_delete() {
        global $DB;

        $this->teacher_only();
        $cond = [
            'videoassessment' => $this->instance,
            'associationtype' => 1,
            'associationid' => required_param('userid', PARAM_INT),
            'timing' => required_param('timing', PARAM_ALPHA),
        ];
        $DB->delete_records('videoassessment_video_assocs', $cond);
        $this->view_redirect(
            'videos',
            ['filter' => optional_param('filter', 'unassociated', PARAM_ALPHA)]
        );
    }

    /**
     * Bulk associate or disassociate a video with multiple users.
     *
     * Processes form submission for bulk operations and redirects back to
     * videos management view.
     *
     * @return void
     */
    private function view_video_associate() {
        global $DB;

        $this->teacher_only();

        $assocform = new form\video_assoc();
        $data = $assocform->get_data();

        $assoc = (object) [
            'videoassessment' => $this->instance,
            'videoid' => $data->videoid,
            'associationtype' => 1,
            'timing' => $data->timing,
        ];
        $ids = json_decode($data->assocdata);
        foreach ($ids as $item) {
            $assoc->associationid = $item[0];
            $cond = [
                'videoassessment' => $this->instance,
                'associationid' => $assoc->associationid,
                'videoid' => $data->videoid,
                'timing' => $data->timing,
            ];
            if ($item[1]) {
                if (!$DB->record_exists('videoassessment_video_assocs', $cond)) {
                    $DB->insert_record('videoassessment_video_assocs', $assoc);
                }
            } else {
                $DB->delete_records('videoassessment_video_assocs', $cond);
            }
        }

        $this->view_redirect('videos');
    }

    /**
     * Render the default landing view for the activity.
     *
     * Shows grade tables for teachers or student-facing assessment summaries
     * including training status and video preview area.
     *
     * @return string Rendered HTML for the main view
     */
    private function view_main() {
        global $OUTPUT, $PAGE, $DB, $USER;

        $o = '';
        if ($this->cm->showdescription == 1) {
            $o .= $this->va->intro;
        }
        $time = time();
        $gradetable = new grade_table($this);
        if ($this->is_teacher()) {
            $o .= $gradetable->print_teacher_grade_table();
        } else {
            $trainingpassed = $DB->get_field('videoassessment_aggregation', 'passtraining', [
                'videoassessment' => $this->va->id,
                'userid' => $USER->id,
            ]);

            if (!$this->va->training || $trainingpassed == 1) {
                if ($this->va->class) {
                    $o .= $this->output->heading(self::str('classassessments'));
                    $o .= $gradetable->print_class_grade_table();
                }
                $o .= $this->output->heading(self::str('selfassessments'));
                $o .= $gradetable->print_self_grade_table();
                $o .= $this->output->heading(self::str('peerassessments'));
                $o .= $gradetable->print_peer_grade_table();
            } else {
                $o .= $this->output->heading(self::str('trainingpretest'));
                $o .= $gradetable->print_training_grade_table();
            }
        }

        $o .= \html_writer::tag('div', '', ['id' => 'videopreview']);

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'mainInit', [$this->cm->id]);

        if ($this->is_teacher()) {
            $o .= $OUTPUT->box_start();
            $url = new \moodle_url(
                '/mod/videoassessment/print.php',
                ['id' => $this->cm->id, 'action' => 'report']
            );
            $o .= $OUTPUT->action_link(
                $url,
                self::str('printrubrics'),
                new \popup_action(
                    'click',
                    $url,
                    'popup',
                    ['width' => 800, 'height' => 700, 'menubar' => true]
                )
            );
            $o .= \html_writer::empty_tag('br');
            $o .= $OUTPUT->action_link(
                new \moodle_url($this->viewurl, ['action' => 'downloadxls']),
                self::str('downloadexcel')
            );
            $o .= $OUTPUT->box_end();
        }
        $o .= $this->output->render_mod_videoassessment_info_status($this->va);

        return $o;
    }

    /**
     * Render the assessment form and handle submissions.
     *
     * Prepares advanced grading instances for each timing, processes submitted
     * grades and comments, updates grade records, and triggers notifications.
     *
     * @return string Rendered HTML for the assess view
     */
    private function view_assess() {
        global $DB, $PAGE, $USER, $OUTPUT;

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'mainInit', [$this->cm->id]);
        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'assessInit');
        // Item #13 (2026-04 fix programme): live rubric total display.
        $PAGE->requires->js_call_amd('mod_videoassessment/live_grade_total', 'init');

        // Add inline script with immediate functionality for remark textarea hide/show.
        $PAGE->requires->js_amd_inline("
            require(['jquery'], function(\$) {
                console.log('[VideoAssessment] Inline script loaded');

                // Mobile detection function.
                function isMobile() {
                    var width = window.innerWidth;
                    var height = window.innerHeight;
                    var isPortrait = window.matchMedia && window.matchMedia('(orientation: portrait)').matches;
                    if (!isPortrait && width <= 768) {
                        isPortrait = height > width || height >= width * 0.8;
                    }
                    return width <= 768 && isPortrait;
                }

                // Get video container.
                function getVideoContainer() {
                    return \$('.assess-form-videos, .path-mod-videoassessment .assess-form-videos');
                }

                // Hide/show video functions with animation.
                function hideVideo() {
                    if (isMobile()) {
                        var \$container = getVideoContainer();
                        console.log('[VideoAssessment] Hiding video, containers found:', \$container.length);
                        if (\$container.length > 0) {
                            \$container.fadeOut(300);
                        }
                    }
                }

                function showVideo() {
                    if (isMobile()) {
                        var \$container = getVideoContainer();
                        if (\$container.length > 0) {
                            \$container.fadeIn(300);
                        }
                    }
                }

                // Setup handlers for remark textareas
                // Mobile: Hide video when textarea is focused, show when blurred.
                function setupRemarkHandlers() {
                    console.log('[VideoAssessment] Setting up remark handlers...');

                    // Find remark textareas.
                    var \$remarkTextareas = \$('.remark textarea, td.remark textarea, .criterion .remark textarea, .gradingform_rubric .remark textarea');
                    console.log('[VideoAssessment] Found remark textareas:', \$remarkTextareas.length);

                    // Handle focus/blur.
                    \$remarkTextareas.off('focus.videoassessment-remark blur.videoassessment-remark')
                        .on('focus.videoassessment-remark', function() {
                            console.log('[VideoAssessment] Remark textarea focused!');
                            hideVideo();
                        })
                        .on('blur.videoassessment-remark', function() {
                            setTimeout(function() {
                                var \$focused = \$('.remark textarea:focus, td.remark textarea:focus');
                                if (\$focused.length === 0) {
                                    console.log('[VideoAssessment] Remark textarea blurred, showing video');
                                    showVideo();
                                }
                            }, 150);
                        });

                    // Catch-all click handler.
                    \$(document).off('click.videoassessment-remark-all').on('click.videoassessment-remark-all', function(e) {
                        var \$target = \$(e.target);
                        var isRemark = \$target.closest('.remark').length > 0 ||
                                       \$target.is('.remark') ||
                                       \$target.closest('.remark textarea').length > 0 ||
                                       \$target.is('.remark textarea');

                        if (isRemark && isMobile()) {
                            console.log('[VideoAssessment] Clicked in remark area');
                            hideVideo();
                        }
                    });
                }

                // Setup immediately and after delays.
                setTimeout(setupRemarkHandlers, 100);
                setTimeout(setupRemarkHandlers, 500);
                setTimeout(setupRemarkHandlers, 1500);
                setTimeout(setupRemarkHandlers, 3000);
            });
        ");

        $PAGE->requires->js_call_amd('mod_videoassessment/assess', 'videoassessmentAssess', []);
        $o = '';

        $user = $DB->get_record('user', ['id' => optional_param('userid', 0, PARAM_INT)]);

        $gradertype = optional_param('gradertype', '', PARAM_ALPHA);

        if ($gradertype == 'training' && $USER->id != $user->id && !$this->is_teacher()) {
            $this->view_redirect();
        }

        if ($gradertype != 'class' && $gradertype != 'training') {
            $gradertype = $this->get_grader_type($user->id);
        }

        $passtraining = $DB->get_field('videoassessment_aggregation', 'passtraining', [
            'videoassessment' => $this->va->id,
            'userid' => $user->id,
        ]);

        $rubricspassed = [];

        if ($gradertype == 'training' && !$this->is_teacher()) {
            if ($passtraining || !$this->va->training) {
                $this->view_redirect();
            } else {
                $gradingarea = 'beforetraining';
                $rubric = new rubric($this, [$gradingarea]);
                $controller = $rubric->get_available_controller($gradingarea);

                $studentid = $USER->id;
                $teacherid = null;

                $teachers = $DB->get_records_sql(
                    '
                    SELECT gi.grader
                    FROM {videoassessment_grade_items} gi
                    WHERE gi.type = :type AND videoassessment = :videoassessment AND gi.gradeduser = gi.grader
                    ORDER BY gi.id DESC
                ',
                    [
                        'type' => $gradingarea,
                        'videoassessment' => $this->va->id,
                    ]
                );

                if (!empty($teachers)) {
                    foreach ($teachers as $teacher) {
                        if ($this->is_teacher($teacher->grader)) {
                            $teacherid = $teacher->grader;
                            break;
                        }
                    }
                }

                $itemid = null;
                $itemid = $this->get_grade_item($gradingarea, $user->id, $studentid);
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);

                $studentinstance = $controller->get_or_create_instance($instanceid, $studentid, $itemid)->get_current_instance();

                $studentfilling = [];
                if (!empty($studentinstance)) {
                    $studentfilling = $studentinstance->get_rubric_filling();
                }

                $teacherfilling = [];
                if ($teacherid) {
                    $itemid = $this->get_grade_item($gradingarea, $teacherid, $teacherid);
                    $teacherinstance = $controller->get_or_create_instance($instanceid, $teacherid, $itemid);
                    $teachercurrentinstance = $teacherinstance->get_current_instance();

                    if (!empty($teachercurrentinstance)) {
                        $teacherfilling = $teacherinstance->get_rubric_filling();
                    }
                }

                if (!empty($teacherfilling)) {
                    $definition = $controller->get_definition();

                    $resulttable = '';
                    $resulttable .= \html_writer::start_tag('table', ['id' => 'training-result-table-render']);

                    $result = $this->get_training_result_table($definition, $studentfilling, $teacherfilling);
                    $resulttable .= $result[0];
                    $rubricspassed = $result[2];

                    $resulttable .= \html_writer::end_tag('table');
                    $o .= $resulttable;
                }
            }
        }

        $mformdata = (object) [
            'va' => $this,
            'cm' => $this->cm,
            'userid' => optional_param('userid', 0, PARAM_INT),
            'user' => $user,
            'gradingdisabled' => false,
            'gradertype' => $gradertype,
            'rubricspassed' => $rubricspassed,
        ];

        $gradingareas = ['before' . $gradertype];
        if ($this->get_associated_video($user->id, 'after')) {
            $gradingareas[] = 'after' . $gradertype;
        }

        // Auto-duplicate rubric if teacher has one but this grader type doesn't.
        // This ensures peers can always see the rubric.
        videoassessment_auto_duplicate_rubric($this->context->id);

        $rubric = new rubric($this, $gradingareas);

        foreach ($this->timings as $timing) {
            $gradingarea = $timing . $gradertype;
            $itemid = null;
            // Use the correct grader for get_grade_item based on gradertype.
            $graderforitem = ($gradertype == 'self') ? $user->id : $USER->id;
            $itemid = $this->get_grade_item($gradingarea, $user->id, $graderforitem);

            // Try to get the controller - this will auto-duplicate if needed.
            $controller = $rubric->get_available_controller($gradingarea);

            // If controller still not available, try one more time after ensuring duplication.
            if (!$controller) {
                // Force duplication check again.
                videoassessment_auto_duplicate_rubric($this->context->id);

                // Reload the rubric object to pick up newly duplicated rubrics.
                $rubric = new rubric($this, $gradingareas);

                // Try again to get the controller.
                $controller = $rubric->get_available_controller($gradingarea);
            }

            if ($controller) {
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                if (!isset($mformdata->advancedgradinginstance)) {
                    $mformdata->advancedgradinginstance = new \stdClass();
                }
                // Use the correct raterid for get_or_create_instance based on gradertype.
                $rateridforinstance = ($gradertype == 'self') ? $user->id : $USER->id;

                // If instanceid is 0, try to get existing instance first to load saved data.
                if ($instanceid == 0 && $itemid) {
                    $existinginstance = $controller->get_current_instance($rateridforinstance, $itemid);
                    if ($existinginstance) {
                        $instanceid = $existinginstance->get_id();
                    }
                }

                $mformdata->advancedgradinginstance->$timing = $controller->get_or_create_instance(
                    $instanceid,
                    $rateridforinstance,
                    $itemid
                );
            } else {
                // Controller not available - check if we should have one.
                // If a rubric definition exists but controller wasn't found, there's a configuration issue.
                $manager = get_grading_manager($this->context, 'mod_videoassessment', $gradingarea);
                $hasdefinition = false;
                try {
                    $testcontroller = $manager->get_controller('rubric');
                    if ($testcontroller && $testcontroller->is_form_defined()) {
                        $hasdefinition = true;
                        // Definition exists but wasn't available - try to set active method and reload.
                        if (!$manager->get_active_method()) {
                            $manager->set_active_method('rubric');
                            // Reload rubric object to pick up the change.
                            $rubric = new rubric($this, $gradingareas);
                            $controller = $rubric->get_available_controller($gradingarea);
                            if ($controller) {
                                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                                if (!isset($mformdata->advancedgradinginstance)) {
                                    $mformdata->advancedgradinginstance = new \stdClass();
                                }
                                // Use the correct raterid for get_or_create_instance based on gradertype.
                                $rateridforinstance = ($gradertype == 'self') ? $user->id : $USER->id;
                                $mformdata->advancedgradinginstance->$timing = $controller->get_or_create_instance(
                                    $instanceid,
                                    $rateridforinstance,
                                    $itemid
                                );
                            }
                        }
                    }
                } catch (\Exception $e) {
                    debugging(
                        'No rubric available for rateridforinstance lookup: ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }

                if (!$hasdefinition) {
                    // Debug: Log why controller is not available with more details.
                    $manager = get_grading_manager($this->context, 'mod_videoassessment', $gradingarea);
                    $activemethod = $manager->get_active_method();
                    $hasdefinitiondetail = false;
                    try {
                        $testcontroller = $manager->get_controller('rubric');
                        if ($testcontroller) {
                            $hasdefinitiondetail = $testcontroller->is_form_defined();
                            $isavailable = $testcontroller->is_form_available();
                            $detail = sprintf(
                                'Active method: %s, Definition exists: %s, Is available: %s',
                                $activemethod ?: 'NULL',
                                $hasdefinitiondetail ? 'YES' : 'NO',
                                $isavailable ? 'YES' : 'NO'
                            );
                            debugging(
                                "Rubric controller not available for grading area: {$gradingarea}, "
                                    . "gradertype: {$gradertype}. {$detail}",
                                DEBUG_NORMAL
                            );
                        }
                    } catch (\Exception $e) {
                        debugging(
                            "Rubric controller not available for grading area: {$gradingarea}, "
                                . "gradertype: {$gradertype}. Active method: "
                                . ($activemethod ?: 'NULL')
                                . ', Error: ' . $e->getMessage(),
                            DEBUG_NORMAL
                        );
                    }
                }
            }

            $mformdata->{'grade' . $timing} = $DB->get_record(
                self::TABLE_GRADES,
                [
                    'gradeitem' => $itemid,
                ]
            );
        }

        // Ensure advancedgradinginstance is set even if empty, so form knows to check for rubrics.
        if (!isset($mformdata->advancedgradinginstance)) {
            $mformdata->advancedgradinginstance = new \stdClass();
        }

        $form = new form\assess('', $mformdata, 'post', '', [
            'class' => 'gradingform',
        ]);

        if ($form->is_cancelled()) {
            $this->view_redirect();
        } else if ($data = $form->get_data($gradertype)) {
            // The form's get_data() method already calls submit_and_get_grade() for advanced grading
            // and sets $data->{'xgrade'.$timing}, so we don't need to call it again here.
            $gradertype = $this->get_grader_type($data->userid, $gradertype);

            // Determine notify student value and save teacher's preference for subsequent gradings.
            $notifystudent = empty($data->isnotifystudent) ? 0 : $data->isnotifystudent;
            global $USER;
            if ($gradertype == 'teacher') {
                set_user_preference('videoassessment_notify_student_default', $notifystudent);
            }

            foreach ($this->timings as $timing) {
                $gradingarea = $timing . $gradertype;
                // Use the correct grader for get_grade_item based on gradertype.
                $graderforitem = ($gradertype == 'self') ? $data->userid : $USER->id;
                $itemid = $this->get_grade_item($gradingarea, $data->userid, $graderforitem);

                if (
                    !($grade = $DB->get_record(
                        'videoassessment_grades',
                        [
                            'gradeitem' => $itemid,
                        ]
                    ))
                ) {
                    $grade = new \stdClass();
                    $grade->videoassessment = $this->instance;
                    $grade->gradeitem = $itemid;
                    $grade->id = $DB->insert_record('videoassessment_grades', $grade);
                }
                $grade->isnotifystudent = $notifystudent;

                // Use the grade from the form data, default to -1 if not set.
                $grade->grade = $data->{'xgrade' . $timing} ?? -1;

                // If grade is still -1, try to get it from the grading instance.
                if ($grade->grade == -1) {
                    $gradingarea = $timing . $gradertype;
                    $rubric = new rubric($this, [$gradingarea]);
                    $controller = $rubric->get_available_controller($gradingarea);
                    if ($controller) {
                        $instance = $controller->get_current_instance($graderforitem, $itemid);
                        if ($instance && $instance->get_status() == \gradingform_instance::INSTANCE_STATUS_ACTIVE) {
                            $instancegrade = $instance->get_grade();
                            if ($instancegrade !== null && $instancegrade >= 0) {
                                $grade->grade = $instancegrade;
                            }
                        }
                    }
                }
                if (isset($data->{'submissioncomment' . $timing})) {
                    $editorvalue = $data->{'submissioncomment' . $timing};

                    // Get maxbytes setting.
                    global $CFG;
                    $maxbytes = get_user_max_upload_file_size($this->context, $CFG->maxbytes, $this->course->maxbytes);

                    // Editor options for file handling.
                    $editoroptions = [
                        'maxfiles' => EDITOR_UNLIMITED_FILES,
                        'maxbytes' => $maxbytes,
                        'noclean' => true,
                        'context' => $this->context,
                        'subdirs' => true,
                    ];

                    // Prepare object for file_postupdate_standard_editor.
                    // It expects an object with 'text' property and 'text_editor' property containing the editor data.
                    $editorobj = new \stdClass();
                    $editorobj->text = isset($editorvalue['text']) ? $editorvalue['text'] : '';
                    $editorobj->text_editor = $editorvalue; // The editor data array.

                    $editorobj = file_postupdate_standard_editor(
                        $editorobj,
                        'text',
                        $editoroptions,
                        $this->context,
                        'mod_videoassessment',
                        'submissioncomment',
                        $grade->id
                    );

                    $grade->submissioncomment = $editorobj->text;
                    $grade->submissioncommentformat = isset($editorobj->textformat) ? $editorobj->textformat : FORMAT_HTML;
                }
                $grade->timemarked = time();
                $DB->update_record('videoassessment_grades', $grade);

                // Item #10: emit a fine-grained logstore event for each
                // rubric save so analytics layers can distinguish teacher
                // grading from self / peer reviews.
                $eventother = [
                    'videoassessmentid' => $this->va->id,
                    'gradertype' => $gradertype,
                    'timing' => $timing,
                ];
                if ($gradertype === 'teacher') {
                    \mod_videoassessment\event\grade_assigned::create([
                        'context' => $this->context,
                        'objectid' => $grade->id,
                        'relateduserid' => $user->id,
                        'other' => $eventother,
                    ])->trigger();
                } else {
                    \mod_videoassessment\event\peer_review_submitted::create([
                        'context' => $this->context,
                        'objectid' => $grade->id,
                        'relateduserid' => $user->id,
                        'other' => $eventother,
                    ])->trigger();
                }
            }

            $this->aggregate_grades($user->id);

            // Adtis.
            $ismailsent = 0;
            $videoassessment = $DB->get_record('videoassessment', ['id' => $this->va->id]);
            if ($videoassessment->teachercommentnotification == 1 && $grade->isnotifystudent == 1) {
                if (
                    !($this->is_graded_by_current_user($user->id, $timing . $gradertype) &&
                    $videoassessment->isfirstassessmentbyteacher == 1 &&
                    'teacher' == $gradertype) &&
                    !($this->is_graded_by_current_user($user->id, $timing . $gradertype) &&
                    $videoassessment->isfirstassessmentbystudent == 1 &&
                    'peer' == $gradertype) ||
                    ($this->is_graded_by_current_user($user->id, $timing . $gradertype) &&
                    $videoassessment->isadditionalassessment == 1 &&
                    'teacher' == $gradertype)
                ) {
                    if ('teacher' == $gradertype) {
                        $mailtemplate = $videoassessment->teachernotificationtemplate;
                    } else {
                        $mailtemplate = $videoassessment->peertnotificationtemplate;
                    }

                    $url = new \moodle_url(
                        $this->viewurl,
                        ['action' => 'report', 'userid' => $user->id]
                    );
                    $templatearray = [
                        "[[student name]]" => $user->firstname . ' ' . $user->lastname,
                        "[[insert assignment name]]" => $videoassessment->name,
                        "[[insert current date]]" => date("Y-m-d H:i:s"),
                        "[[insert link to student page to view assessment]]" => $url->out(false),
                        "[[teacher email address]]" => $USER->email,
                        "[[teacher name]]" => $USER->firstname . ' ' . $USER->lastname,
                    ];

                    foreach ($templatearray as $item => $template) {
                        $mailtemplate = str_replace($item, $template, $mailtemplate);
                    }
                    $quickmailresult = false;
                    $registeredemailresult = false;
                    if ($videoassessment->isregisteredemail == 1) {
                        $registeredemailresult = email_to_user($user, $USER, "", $mailtemplate);
                    }
                    if ($videoassessment->ismobilequickmail == 1) {
                        // NOTE: The Quickmail JPN block is optional.
                        // Only attempt to use its user table if the quickmailjpn plugin is installed.
                        $dbman = $DB->get_manager();
                        if ($dbman->table_exists('block_quickmailjpn_users')) {
                            $quickmail = $DB->get_record('block_quickmailjpn_users', ['userid' => $user->id]);
                            if (!empty($quickmail)) {
                                $mobileuser = $user;
                                $mobileuser->email = $quickmail->mobileemail;
                                $quickmailresult = email_to_user($mobileuser, $USER, "", $mailtemplate);
                            }
                        }
                    }
                    if ($registeredemailresult || $quickmailresult) {
                        $ismailsent = 1;
                    }
                }
            }

            if ($gradertype == 'training' && !$this->is_teacher()) {
                $this->view_redirect('trainingresult', ['userid' => $user->id]);
            } else {
                $this->view_redirect("", ['ismailsent' => $ismailsent]);
            }
        }

        $o .= \html_writer::start_tag('div', ['class' => 'clearfix']);
        if ($gradertype != 'class') {
            $o .= \html_writer::start_tag('div', ['class' => 'assess-form-videos']);
            $mobile = self::uses_mobile_upload();

            if ($gradertype == 'training') {
                $data = $DB->get_record('videoassessment_videos', ['id' => $this->va->trainingvideoid]);
                if (!empty($data)) {
                    if ($video = new video($this->context, $data)) {
                        $o .= \html_writer::start_tag('div', ['class' => 'video-wrap']);
                        $o .= $this->output->render($video);
                        $o .= \html_writer::end_tag('div');
                    }
                }
            } else {
                foreach ($this->timings as $timing) {
                    if ($video = $this->get_associated_video($user->id, $timing)) {
                        $o .= \html_writer::start_tag('div', ['class' => 'video-wrap']);
                        $o .= $this->output->render($video);
                        $o .= \html_writer::end_tag('div');
                    }
                }
            }
            $o .= \html_writer::end_tag('div');
        }

        ob_start();
        $form->display();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::end_tag('div');

        return $o;
    }

    /**
     * Render the training result table for the current or selected user.
     *
     * Compares student rubric filling against teacher's and displays pass/fail
     * per criterion with summary.
     *
     * @return string Rendered HTML for the training result view
     */
    private function view_result() {
        global $DB, $USER, $PAGE, $CFG;

        $gradingarea = 'beforetraining';
        $user = $DB->get_record('user', ['id' => optional_param('userid', 0, PARAM_INT)]);

        if ($this->is_teacher()) {
            $studentid = $user->id;
            $teacherid = $USER->id;
        } else {
            $studentid = $USER->id;
            $teacherid = null;

            $teachers = $DB->get_records_sql(
                '
                    SELECT gi.grader
                    FROM {videoassessment_grade_items} gi
                    WHERE gi.type = :type AND videoassessment = :videoassessment AND gi.gradeduser = gi.grader
                    ORDER BY gi.id DESC
                ',
                [
                    'type' => $gradingarea,
                    'videoassessment' => $this->va->id,
                ]
            );

            if (!empty($teachers)) {
                foreach ($teachers as $teacher) {
                    if ($this->is_teacher($teacher->grader)) {
                        $teacherid = $teacher->grader;
                        break;
                    }
                }
            }
        }

        $rubric = new rubric($this, [$gradingarea]);
        $o = '';

        if (!empty($rubric)) {
            $controller = $rubric->get_available_controller($gradingarea);

            $o .= \html_writer::start_tag('div', ['class' => 'clearfix']);
            $o .= \html_writer::start_tag('div', ['class' => 'assess-form-videos']);

            $data = $DB->get_record('videoassessment_videos', ['id' => $this->va->trainingvideoid]);
            if (!empty($data)) {
                if ($video = new video($this->context, $data)) {
                    $o .= \html_writer::start_tag('div', ['class' => 'video-wrap']);
                    $o .= $this->output->render($video);
                    $o .= \html_writer::end_tag('div');
                }
            }

            $o .= \html_writer::end_tag('div');
            $o .= \html_writer::start_tag('div', ['id' => 'training-result-wrap']);

            $o .= \html_writer::start_tag('h2');
            $o .= self::str('results');
            $o .= \html_writer::end_tag('h2');

            if (!empty($controller)) {
                $itemid = null;
                $itemid = $this->get_grade_item($gradingarea, $user->id, $studentid);
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);

                $studentinstance = $controller->get_or_create_instance($instanceid, $studentid, $itemid)->get_current_instance();
                $archiveinstances = $this->get_archive_instances($controller, $itemid);
                $historyfillings = [];

                if (!empty($archiveinstances)) {
                    foreach ($archiveinstances as $instance) {
                        $fillings = $instance->get_rubric_filling();

                        foreach ($fillings['criteria'] as $rid => $filling) {
                            if (!isset($historyfillings[$rid])) {
                                $historyfillings[$rid] = [];
                            }

                            if (!in_array($filling['levelid'], $historyfillings[$rid])) {
                                $historyfillings[$rid][] = $filling['levelid'];
                            }
                        }
                    }
                }

                $studentfilling = [];
                if (!empty($studentinstance)) {
                    $studentfilling = $studentinstance->get_rubric_filling();
                }

                $teacherfilling = [];
                if ($teacherid) {
                    $itemid = $this->get_grade_item($gradingarea, $teacherid, $teacherid);
                    $teacherinstance = $controller->get_or_create_instance($instanceid, $teacherid, $itemid);
                    $teachercurrentinstance = $teacherinstance->get_current_instance();

                    if (!empty($teachercurrentinstance)) {
                        $teacherfilling = $teacherinstance->get_rubric_filling();
                    }
                }

                $definition = $controller->get_definition();

                $o .= \html_writer::start_tag('div', ['id' => 'training-desc']);
                $o .= \html_writer::start_tag('h5');
                $o .= str_replace('xx', $this->va->accepteddifference, $this->va->trainingdesc);
                $o .= \html_writer::end_tag('h5');
                $o .= \html_writer::end_tag('div');

                $o .= \html_writer::start_tag('table', ['id' => 'training-result-table']);

                $result = $this->get_training_result_table($definition, $studentfilling, $teacherfilling, $historyfillings);
                $o .= $result[0];
                $passed = $result[1];

                $o .= \html_writer::end_tag('table');
            }
        }

        $agg = $DB->get_record('videoassessment_aggregation', [
            'videoassessment' => $this->va->id,
            'userid' => $user->id,
        ]);

        $o .= \html_writer::start_tag('div', ['class' => 'result-notice']);

        if (!$agg->passtraining && $passed && !empty($teacherfilling) && !empty($studentfilling)) {
            $agg->passtraining = 1;

            $DB->update_record('videoassessment_aggregation', $agg);
        }

        if (!$this->is_teacher()) {
            if ($agg->passtraining) {
                $o .= get_string(
                    'passednotice',
                    self::VA,
                    '<a class="button-notice" href="' . new \moodle_url(
                        '/mod/videoassessment/view.php',
                        ['id' => $this->cm->id]
                    ) . '">' . self::str('selfpeer') . '</a>',
                );
            } else {
                $a = new \stdClass();
                $a->accepteddifference = $this->va->accepteddifference;
                $a->button = '<a class="button-notice" href="'
                    . new \moodle_url('/mod/videoassessment/view.php', ['id' => $this->cm->id, 'action' => 'assess', 'userid' => $user->id, 'gradertype' => 'training'])
                    . '">' . self::str('tryagain') . '</a>';

                $o .= self::str('failednotice', $a);
            }
        }

        $o .= \html_writer::end_tag('div');
        $o .= \html_writer::end_tag('div');
        $o .= \html_writer::end_tag('div');

        return $o;
    }

    /**
     * Render the per-user assessment report view.
     *
     * Displays associated videos, grades, and rubric details for a user.
     *
     * @return string Rendered HTML for the report view
     */
    private function view_report() {
        global $PAGE, $OUTPUT, $DB;

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'reportCombineRubrics');

        $o = '';

        $userid = optional_param('userid', 0, PARAM_INT);

        $rubric = new rubric($this);

        $gradingstatus = $this->get_grading_status($userid);
        $usergrades = $this->get_aggregated_grades($userid);
        $hideteacher = (object) [
            'before' => $usergrades->gradebeforeself == -1 && $this->va->delayedteachergrade && !$this->is_teacher(),
            'after' => $usergrades->gradeafterself == -1 && $this->va->delayedteachergrade && !$this->is_teacher(),
        ];

        $o .= \html_writer::start_tag('div', ['class' => 'report-rubrics']);
        foreach ($this->timings as $timing) {
            if (!$gradingstatus->$timing) {
                continue;
            }

            $o .= $OUTPUT->heading($this->str('allscores'));
            $timinggrades = [];
            $rubrictextclass = 0;
            $namerubrictextclass = '';
            foreach ($this->gradertypes as $gradertype) {
                if ($this->va->class && $gradertype == 'class' && !has_capability('mod/videoassessment:grade', $this->context)) {
                    continue;
                }

                $gradingarea = $timing . $gradertype;
                $o .= $OUTPUT->heading(
                    self::str($timing) . ' - ' . self::str($gradertype),
                    2,
                    'main',
                    'heading-' . $gradingarea
                );
                $gradinginfo = grade_get_grades(
                    $this->course->id,
                    'mod',
                    'videoassessment',
                    $this->instance,
                    $userid
                );
                $o .= \html_writer::start_tag('div', ['id' => 'rubrics-' . $gradingarea]);
                if ($controller = $rubric->get_available_controller($gradingarea)) {
                    $gradeitems = $this->get_grade_items($gradingarea, $userid);
                    foreach ($gradeitems as $gradeitem) {
                        $tmp = $controller->render_grade($PAGE, $gradeitem->id, $gradinginfo, '', false);
                        if ($gradertype == 'teacher' && $hideteacher->$timing) {
                            // Hide teacher grade and comment.
                            $tmp = preg_replace('@class="(level[^"]+?)\s*checked"@', 'class="$1"', $tmp);
                            $tmp = preg_replace('@<td class="remark">(.*?)</td>@us', '<td class="remark"></td>', $tmp);
                        }
                        $o .= $tmp;

                        // If grade is -1 or not set, try to get it from the grading instance.
                        $displaygrade = $gradeitem->grade;
                        if ($displaygrade == -1 || $displaygrade === null) {
                            // Try to get the grade from the active grading instance.
                            $instance = $controller->get_current_instance($gradeitem->grader, $gradeitem->id);
                            if ($instance && $instance->get_status() == \gradingform_instance::INSTANCE_STATUS_ACTIVE) {
                                $instancegrade = $instance->get_grade();
                                if ($instancegrade !== null && $instancegrade >= 0) {
                                    $displaygrade = $instancegrade;
                                    // Update the grade in the database for future reference.
                                    global $DB;
                                    if ($gradeitem->gradeid) {
                                        $updategrade = $DB->get_record('videoassessment_grades', ['id' => $gradeitem->gradeid]);
                                        if ($updategrade) {
                                            $updategrade->grade = $displaygrade;
                                            $DB->update_record('videoassessment_grades', $updategrade);
                                        }
                                    }
                                }
                            }
                        }

                        $timinggrades[] = \html_writer::tag('span', (int) $displaygrade, ['class' => 'rubrictext-' . $gradertype]);
                    }
                }
                $o .= \html_writer::end_tag('div');
            }

            // Adtis.
            $o .= $OUTPUT->heading("General Comments");
            $o .= \html_writer::start_tag('div', ['class' => 'card  card-body']);
            foreach ($this->gradertypes as $gradertype) {
                if (
                    $gradertype == 'training'
                    || $gradertype == 'class'
                    || ($this->va->class && $gradertype == 'class'
                    && !has_capability('mod/videoassessment:grade', $this->context))
                ) {
                    continue;
                }
                $gradingarea = $timing . $gradertype;
                $grades = $this->get_grade_items($gradingarea, $userid);
                foreach ($grades as $item => $gradeitem) {
                    if (empty($gradeitem->submissioncomment)) {
                        break;
                    }
                    // Format the comment to convert @@PLUGINFILE@@ placeholders to actual URLs.
                    $commentformat = isset($gradeitem->submissioncommentformat) ? $gradeitem->submissioncommentformat : FORMAT_HTML;
                    // First rewrite @@PLUGINFILE@@ placeholders to actual URLs.
                    // Use gradeid (from videoassessment_grades table) not gradeitem->id (from grade_items table).
                    $gradeid = isset($gradeitem->gradeid) ? $gradeitem->gradeid : $gradeitem->id;
                    $commenttext = file_rewrite_pluginfile_urls(
                        $gradeitem->submissioncomment,
                        'pluginfile.php',
                        $this->context->id,
                        'mod_videoassessment',
                        'submissioncomment',
                        $gradeid
                    );
                    // Then format the text.
                    $formattedcomment = format_text($commenttext, $commentformat, [
                        'context' => $this->context,
                    ]);
                    $comment = '<label class="submissioncomment">' . $formattedcomment . '</label>';
                    if ($this->uses_mobile_upload()) {
                        $commentbutton = '';
                        $plaintext = strip_tags($gradeitem->submissioncomment);
                        if (strlen($plaintext) > 30) {
                            $shortcomment = substr($plaintext, 0, 10);
                            $commentbutton = "<button type='button' class='commentbutton btn btn-secondary' id = '"
                                . $gradeitem->id . "' cmid = '" . $this->va->id . "' userid = '" . $userid . "' timing = '" . $timing . "'><h2>...</h2></button>";
                            $comment = '<label class="mobile-submissioncomment">' . $shortcomment . '</label>';
                            $comment = $comment . $commentbutton;
                        } else {
                            $comment = '<label class="mobile-submissioncomment">' . $formattedcomment . '</label>';
                        }
                    }

                    if ($gradertype == "peer") {
                        $lable = '<span class="blue box">Peer</span>';
                    } else if ($gradertype == "teacher") {
                        $lable = '<span class="green box">Teacher</span>';
                    } else if ($gradertype == "self") {
                        $lable = '<span class="red box">Self</span>';
                    }

                    $o .= $OUTPUT->heading($lable . $comment);
                }
            }
            $o .= \html_writer::end_tag('div');

            $gradeduser = $DB->get_record('user', ['id' => $userid]);
            $o .= \html_writer::start_tag('div', ['class' => 'comment comment-' . $gradertype])
                . $OUTPUT->user_picture($gradeduser)
                . ' ' . fullname($gradeduser)
                . \html_writer::end_tag('div');

            if ($timinggrades || $rubrictextclass > 0) {
                $totalscore = ' ='
                    . \html_writer::start_tag('div', ['class' => 'comment-grade'])
                    . '<span class="comment-score-text">'
                    . self::str('totalscore')
                    . '</span><span class="comment-score">'
                    . (int) $usergrades->{'grade' . $timing}
                    . '</span>'
                    . \html_writer::end_tag('div');
                $selffairnessbonus = '<span  class="fairness">+</span> '
                    . \html_writer::start_tag('div', ['class' => 'comment-grade fairness'])
                    . '<span class="comment-score-text" >'
                    . '+' . self::str('selffairnessbonus')
                    . '</span><span class="comment-score">'
                    . (int) $usergrades->selffairnessbonus
                    . '</span>'
                    . \html_writer::end_tag('div');
                $fairnessbonus = '<span  class="fairness">+</span> '
                    . \html_writer::start_tag('div', ['class' => 'comment-grade fairness'])
                    . '<span class="comment-score-text" >'
                    . '+' . self::str('peerfairnessbonus')
                    . '</span><span class="comment-score">'
                    . (int) $usergrades->fairnessbonus
                    . '</span>'
                    . \html_writer::end_tag('div');
                $finalscore = ' = '
                    . \html_writer::start_tag('div', ['class' => 'comment-grade'])
                    . '<span class="comment-score-text">'
                    . self::str('finalscore')
                    . '</span><span class="comment-score">'
                    . (int) $usergrades->finalscore . '</span>'
                    . \html_writer::end_tag('div');
                $finalgradetext = get_string('grade', 'videoassessment') . ': '
                    . implode(', ', $timinggrades)
                    . $totalscore . $selffairnessbonus . $fairnessbonus . $finalscore;
                $o .= $OUTPUT->container($finalgradetext, 'finalgrade');
            }
        }
        $o .= \html_writer::end_tag('div');
        $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'mobileshowallcomment', []);
        return $o;
    }

    /**
     * Render the publish view to generate and publish resources.
     *
     * Allows teachers to create resource links for published content.
     *
     * @return string Rendered HTML for the publish view
     */
    private function view_publish() {
        global $CFG, $OUTPUT, $PAGE, $DB, $USER;
        require_once($CFG->dirroot . '/mod/resource/lib.php');

        $PAGE->requires->js_call_amd('mod_videoassessment/publish', 'mobilepublishvideo', []);

        if ($CFG->version < self::MOODLE_VERSION_23) {
            require_once($CFG->dirroot . '/mod/resource/locallib.php'); // resource_set_mainfile
        }

        $this->teacher_only();

        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'initVideoLinks');
        $PAGE->requires->js_call_amd('mod_videoassessment/module', 'initPublishVideos');

        $o = '';

        $o .= $OUTPUT->heading(self::str('publishvideostocourse'));

        $videos = optional_param_array('videos', [], PARAM_BOOL);

        $form = new form\video_publish(null, (object)['va' => $this, 'videos' => $videos]);

        if ($form->is_cancelled()) {
            $this->view_redirect();
        }

        if ($data = $form->get_data() && $form->is_validated()) {
            if ($data->course) {
                $course = $DB->get_record('course', ['id' => $data->course]);
            } else {
                require_capability('moodle/course:create', \context_coursecat::instance($data->category));

                $course = (object) [
                    'category' => $data->category,
                    'fullname' => trim($data->fullname),
                    'shortname' => trim($data->shortname),
                ];
                $course = create_course($course);

                $context = \context_course::instance($course->id, MUST_EXIST);
                if (!empty($CFG->creatornewroleid) && !is_viewing($context, null, 'moodle/role:assign') && !is_enrolled($context, null, 'moodle/role:assign')) {
                    \enrol_try_internal_enrol($course->id, $USER->id, $CFG->creatornewroleid);
                }
            }

            require_capability('moodle/course:manageactivities', \context_course::instance($course->id));

            $moduleid = $DB->get_field('modules', 'id', ['name' => 'resource']);

            $fs = get_file_storage();

            $videos = required_param_array('videos', PARAM_BOOL);

            foreach ($videos as $videoid => $value) {
                $video = $DB->get_record('videoassessment_videos', ['id' => $videoid]);
                $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $video->filepath, $video->filename);

                if (empty($file)) {
                    continue;
                }

                $assocs = $this->get_video_associations($videoid);
                $assocnames = [];
                foreach ($assocs as $assoc) {
                    $user = $DB->get_record(
                        'user',
                        ['id' => $assoc->associationid],
                        'id, lastname, firstname'
                    );
                    $assocnames[] = fullname($user);
                }
                $modulename = implode(', ', $assocnames);

                // Add course module.
                $cm = new \stdClass();
                $cm->course = $course->id;
                $cm->module = $moduleid;

                $cm->id = add_course_module($cm);

                // Add module option.
                $resource = new \stdClass();
                $resource->course = $course->id;
                $resource->name = trim($data->prefix) . $modulename . trim($data->suffix);
                $resource->display = 1;
                $resource->timemodified = time();
                $resource->coursemodule = $cm->id;
                $resource->files = null;

                $resource->id = resource_add_instance($resource, null);

                $DB->set_field('course_modules', 'instance', $resource->id, ['id' => $cm->id]);

                // Add to course section.
                if (!isset($data->section)) {
                    $sectionnum = 1;
                } else {
                    $sectionnum = $DB->get_field('course_sections', 'section', ['id' => $data->section]);
                }
                course_create_sections_if_missing($course, [$sectionnum]);

                $cm->coursemodule = $cm->id;
                $cm->section = $sectionnum;

                $sectionid = course_add_cm_to_section($course, $cm->id, $sectionnum);

                $DB->set_field('course_modules', 'section', $sectionid, ['id' => $cm->id]);

                // Add file.
                $newfile = [
                    'contextid' => \context_module::instance($cm->id)->id,
                    'component' => 'mod_resource',
                    'filearea' => 'content',
                ];
                $fs->create_file_from_storedfile($newfile, $file);
            }
            rebuild_course_cache($course->id);

            redirect(new \moodle_url('/course/view.php', ['id' => $course->id]));
        }

        ob_start();
        $form->display();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::tag('div', '', ['id' => 'videopreview']);

        return $o;
    }

    /**
     * Get grade items by explicit Video Assessment id.
     *
     * @param string $gradingarea Grading area key
     * @param int $gradeduser User id being graded
     * @param int $id Video Assessment instance id
     * @return array List of grade item records
     */
    public static function get_grade_items_by_id($gradingarea, $gradeduser, $id) {
        global $DB;

        return $DB->get_records_sql(
            '
                SELECT gi.id, gi.grader, g.id as gradeid, g.grade, g.submissioncomment, g.timemarked
                    FROM {videoassessment_grade_items} gi
                        LEFT JOIN {videoassessment_grades} g ON g.videoassessment = :va2
                            AND g.gradeitem = gi.id
                        JOIN {user} u ON u.id = gi.grader
                    WHERE gi.videoassessment = :va AND gi.type = :type
                        AND gi.gradeduser = :gradeduser
                ',
            [
                'va' => $id,
                'va2' => $id,
                'type' => $gradingarea,
                'gradeduser' => $gradeduser,
            ]
        );
    }

    /**
     * Get grade items for a grading area and graded user.
     *
     * @param string $gradingarea Grading area key
     * @param int $gradeduser User id being graded
     * @return array List of grade item records
     */
    public function get_grade_items($gradingarea, $gradeduser) {
        global $DB;

        return $DB->get_records_sql(
            '
                SELECT gi.id, gi.grader, g.id as gradeid, g.grade, g.submissioncomment, g.timemarked
                    FROM {videoassessment_grade_items} gi
                        LEFT JOIN {videoassessment_grades} g ON g.videoassessment = :va2
                            AND g.gradeitem = gi.id
                        JOIN {user} u ON u.id = gi.grader
                    WHERE gi.videoassessment = :va AND gi.type = :type
                        AND gi.gradeduser = :gradeduser
                ',
            [
                'va' => $this->instance,
                'va2' => $this->instance,
                'type' => $gradingarea,
                'gradeduser' => $gradeduser,
            ]
        );
    }


    /**
     * Get or create a grade item id for the given area and users.
     *
     * @param string $gradingarea Grading area key
     * @param int $gradeduser User id being graded
     * @param int|null $grader Grader user id, defaults to current user
     * @return int Grade item id
     */
    public function get_grade_item($gradingarea, $gradeduser, $grader = null) {
        global $DB, $USER;

        if (!$grader) {
            $grader = $USER->id;
        }

        if (
            $gradeitem = $DB->get_record('videoassessment_grade_items', [
                'videoassessment' => $this->instance,
                'type' => $gradingarea,
                'gradeduser' => $gradeduser,
                'grader' => $grader,
            ])
        ) {
            return $gradeitem->id;
        }

        $gradeitem = new \stdClass();
        $gradeitem->videoassessment = $this->instance;
        $gradeitem->type = $gradingarea;
        $gradeitem->gradeduser = $gradeduser;
        $gradeitem->grader = $grader;
        $gradeitem->usedbypeermarking = 0;

        return $DB->insert_record('videoassessment_grade_items', $gradeitem);
    }

    /**
     * Fetch or build the aggregated grades object for a user.
     *
     * @param int $userid User id
     * @return \stdClass Aggregated grades record
     */
    public function get_aggregated_grades($userid) {
        global $DB;

        if (
            $grades = $DB->get_record(
                'videoassessment_aggregation',
                ['videoassessment' => $this->instance, 'userid' => $userid]
            )
        ) {
            // Ensure bonus fields exist (they might be missing in older records).
            if (!isset($grades->selffairnessbonus)) {
                $grades->selffairnessbonus = 0;
            }
            if (!isset($grades->fairnessbonus)) {
                $grades->fairnessbonus = 0;
            }
            if (!isset($grades->finalscore)) {
                $grades->finalscore = 0;
            }
            // Ensure gradebeforeclass exists (it might be missing in older records).
            if (!isset($grades->gradebeforeclass)) {
                $grades->gradebeforeclass = -1;
            }
            return $grades;
        }

        $grades = (object) [
            'videoassessment' => $this->instance,
            'userid' => $userid,
            'timemodified' => time(),
            'gradebefore' => -1,
            'gradeafter' => -1,
            'gradebeforeteacher' => -1,
            'gradebeforeself' => -1,
            'gradebeforepeer' => -1,
            'gradebeforeclass' => -1,
            'gradeafterteacher' => -1,
            'gradeafterself' => -1,
            'gradeafterpeer' => -1,
            'selffairnessbonus' => 0,
            'fairnessbonus' => 0,
            'finalscore' => 0,
        ];
        $grades->id = $DB->insert_record('videoassessment_aggregation', $grades);
        return $grades;
    }

    /**
     * Determine whether any grading has been completed for a user.
     *
     * @param int $userid User id
     * @return boolean True if any grading area has a grade
     */
    public function is_user_graded($userid) {
        $agg = $this->get_aggregated_grades($userid);
        foreach ($this->gradingareas as $gradingarea) {
            $prop = 'grade' . $gradingarea;
            if ($agg->$prop != -1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get grading completion status per area for a user.
     *
     * @param int $userid User id
     * @return \stdClass Object with area flags: submitted, graded, teachergraded
     */
    public function get_grading_status($userid) {
        $agg = $this->get_aggregated_grades($userid);
        $status = (object) [
            'any' => false,
            'before' => false,
            'after' => false,
        ];
        foreach ($this->timings as $timing) {
            foreach ($this->gradertypes as $gradertype) {
                $gradingarea = $timing . $gradertype;
                $prop = 'grade' . $gradingarea;
                if ($agg->$prop != -1) {
                    $status->any = $status->$timing = true;
                    break;
                }
            }
        }

        return $status;
    }

    /**
     * Aggregate grades for a user across grading areas and store results.
     *
     * @param int $userid User id
     * @return void
     */
    public function aggregate_grades($userid) {
        global $DB;

        $agg = $this->get_aggregated_grades($userid);

        foreach ($this->timings as $timing) {
            foreach ($this->gradertypes as $gradingtype) {
                $gradingarea = $timing . $gradingtype;

                $sql = '
                        SELECT AVG(g.grade)
                        FROM {videoassessment_grades} g
                            JOIN {videoassessment_grade_items} gi ON g.gradeitem = gi.id
                        WHERE g.videoassessment = :va
                            AND gi.gradeduser = :gradeduser AND gi.type = :type
                        ';
                $params = [
                    'gradeduser' => $userid,
                    'type' => $gradingarea,
                    'va' => $this->instance,
                ];
                if ($grade = $DB->get_field_sql($sql, $params)) {
                    $agg->{'grade' . $gradingarea} = $grade;
                } else {
                    $agg->{'grade' . $gradingarea} = -1;
                }
            }

            $gradeself = ($agg->{'grade' . $timing . 'self'} < 0) ? 0 : $agg->{'grade' . $timing . 'self'};
            $gradepeer = ($agg->{'grade' . $timing . 'peer'} < 0) ? 0 : $agg->{'grade' . $timing . 'peer'};
            $gradeteacher = ($agg->{'grade' . $timing . 'teacher'} < 0) ? 0 : $agg->{'grade' . $timing . 'teacher'};
            $gradeclass = ($agg->{'grade' . $timing . 'class'} < 0) ? 0 : $agg->{'grade' . $timing . 'class'};

            $agg->{'grade' . $timing} = ($gradeteacher *
                $this->va->ratingteacher +
                $gradeself * $this->va->ratingself +
                $gradepeer * $this->va->ratingpeer +
                $gradeclass * $this->va->ratingclass) / ($this->va->ratingteacher +
                $this->va->ratingself + $this->va->ratingpeer + $this->va->ratingclass);
        }

        // PostgreSQL does not perform implicit casting to INT, so you must cast explicitly.
        foreach ($this->timings as $timing) {
            foreach ($this->gradertypes as $gradingtype) {
                $agg->{'grade' . $timing . $gradingtype} = (int) round($agg->{'grade' . $timing . $gradingtype});
            }
            $agg->{'grade' . $timing} = (int) round($agg->{'grade' . $timing});
        }

        if (!empty($agg->gradebefore)) {
            $rawgrade = $agg->gradebefore;
        } else {
            $rawgrade = 0;
        }

        // Adtis.
        $va = $DB->get_record("videoassessment", ["id" => $this->instance]);
        if ($va->fairnessbonus == 1 && (optional_param('gradertype', null, PARAM_TEXT) == 'peer' || optional_param('gradertype', null, PARAM_TEXT) == 'teacher')) {
            if ($gradeteacher > $gradepeer) {
                $gradediff = $gradeteacher - $gradepeer;
                $bonusscale = ($gradediff / $gradeteacher) * 100;
            } else {
                $gradediff = $gradepeer - $gradeteacher;
                $bonusscale = ($gradediff / $gradeteacher) * 100;
            }

            $bonusscalearray = [];
            for ($i = 1; $i <= 6; $i++) {
                $bonusscalearray[$va->{'bonusscale' . $i}] = $va->{'bonus' . $i};
            }

            $keys = array_keys($bonusscalearray);

            array_push($keys, $bonusscale);
            sort($keys);
            $item = array_search($bonusscale, $keys);

            $total = $this->va->grade;
            if ($total < 0) {
                $total = 100;
            }
            if ($item + 1 == count($keys)) {
                $bonuspercent = 0;
            } else {
                $key = $keys[$item + 1];
                $bonuspercent = $bonusscalearray[$key];
            }
            $agg->bonusscale = $bonuspercent;

            $sql = 'SELECT *
                FROM {videoassessment_grade_items}  g
                WHERE g.videoassessment = ? and g.grader = ? AND g.type like "%peer%" ';
            $params = [$this->instance, $userid];
            $result = $DB->get_record_sql($sql, $params);
            if (empty($result)) {
                $agg->fairnessbonus = 0;
            } else {
                $agg->fairnessbonus = (($bonuspercent / 100) * ((int) $va->bonuspercentage / 100) * $total);
            }

            $agg->finalscore =
                ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore) > 100
                ? 100 : ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore);
        }

        if ($va->selffairnessbonus == 1 && (optional_param('gradertype', null, PARAM_TEXT) == 'self' || optional_param('gradertype', null, PARAM_TEXT) == 'teacher')) {
            if ($gradeteacher > $gradeself) {
                $gradediff = $gradeteacher - $gradeself;
                $selfbonusscale = ($gradediff / $gradeteacher) * 100;
            } else {
                $gradediff = $gradeself - $gradeteacher;
                $selfbonusscale = ($gradediff / $gradeteacher) * 100;
            }

            $selfbonusscalearray = [];
            for ($i = 1; $i <= 6; $i++) {
                $selfbonusscalearray[$va->{'bonusscale' . $i}] = $va->{'bonus' . $i};
            }

            $selfkeys = array_keys($selfbonusscalearray);

            array_push($selfkeys, $selfbonusscale);
            sort($selfkeys);
            $item = array_search($selfbonusscale, $selfkeys);

            $total = $this->va->grade;
            if ($total < 0) {
                $total = 100;
            }
            if ($item + 1 == count($selfkeys)) {
                $selfbonuspercent = 0;
            } else {
                $key = $selfkeys[$item + 1];
                $selfbonuspercent = $selfbonusscalearray[$key];
            }
            $agg->selfbonusscale = $selfbonuspercent;
            $agg->selffairnessbonus = (($selfbonuspercent / 100) * ((int) $va->bonuspercentage / 100) * $total);
            $agg->finalscore =
                ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore) > 100
                ? 100 : ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore);
        }

        if ($rawgrade > 0) {
            $this->update_grade_item(
                [
                    'userid' => $userid,
                    'rawgrade' => $rawgrade,
                ]
            );

            // Update completion state.
            $completion = new \completion_info($this->course);
            if (
                $completion->is_enabled($this->cm) && $this->cm->completion == COMPLETION_TRACKING_AUTOMATIC
                && ($rawgrade >= $va->gradepass_videoassessment)
            ) {
                $completion->update_state($this->cm, COMPLETION_COMPLETE);
            }
        }

        $agg->timemodified = time();
        $DB->update_record('videoassessment_aggregation', $agg);
    }

    /**
     * Recalculate aggregated grades for all students.
     *
     * @return void
     */
    public function regrade() {
        $users = $this->get_students();
        foreach ($users as $user) {
            $this->aggregate_grades($user->id);
        }
    }

    /**
     * Check whether the current or specified user graded a given user/area.
     *
     * @param int $gradeduser The graded user id
     * @param string $gradingarea Grading area key
     * @param int|null $grader Optional grader id, defaults to current user
     * @return boolean True if a grade by the grader exists
     */
    public function is_graded_by_current_user($gradeduser, $gradingarea, $grader = null) {
        global $DB, $USER;

        if (!$grader) {
            $grader = $USER->id;
        }

        return $DB->record_exists_sql(
            '
                SELECT gi.id
                FROM {videoassessment_grade_items} gi
                    JOIN {videoassessment_grades} g ON gi.id = g.gradeitem
                WHERE gi.videoassessment = :va
                    AND gi.gradeduser = :gradeduser
                    AND gi.grader = :grader
                    AND gi.type = :gradingarea
                    AND g.grade >= 0
                ',
            [
                'va' => $this->instance,
                'gradeduser' => $gradeduser,
                'grader' => $grader,
                'gradingarea' => $gradingarea,
            ]
        );
    }

    /**
     * Get peer user ids for the given user in this activity.
     *
     * @param int $userid User id
     * @return array List of peer user ids
     */
    public function get_peers($userid) {
        global $DB;

        $peers = $DB->get_records(
            'videoassessment_peers',
            [
                'videoassessment' => $this->instance,
                'peerid' => $userid,
            ]
        );
        $peerids = [];
        foreach ($peers as $peer) {
            $peerids[] = $peer->userid;
        }

        return $peerids;
    }

    /**
     * Generate random peer mappings for a set of users.
     *
     * @param int[] $userids List of user ids
     * @param int $numpeers Number of peers per user (-1 for unlimited/all other users)
     * @return array Mapping: userid => int[] peer ids
     */
    public function get_random_peers_for_users(array $userids, $numpeers) {
        assert(is_numeric($numpeers));

        // Initialize peers array for all users.
        $peers = [];
        foreach ($userids as $userid) {
            $peers[$userid] = [];
        }

        // Handle unlimited peers case (-1 means all other users).
        if ($numpeers == -1) {
            foreach ($userids as $userid) {
                // Assign all other users as peers.
                $peers[$userid] = array_values(array_diff($userids, [$userid]));
            }
            return $peers;
        }

        // Check if we have enough users. Need at least numpeers + 1 users.
        if (count($userids) <= $numpeers) {
            // Not enough users - assign as many peers as possible.
            foreach ($userids as $userid) {
                $peers[$userid] = array_values(array_diff($userids, [$userid]));
            }
            return $peers;
        }

        // Item #5 (2026-04 fix programme): the previous algorithm picked
        // the first available peer for each round, which left some users
        // chosen as a peer many more times than others. Replace it with
        // a load-balancing pass that tracks how often each user has
        // already been chosen and always picks the candidate with the
        // lowest count (random tiebreak via the initial shuffle).
        $chosencount = array_fill_keys($userids, 0);

        // Process users in random order so the first user does not
        // systematically get the easiest pickings.
        $shuffled = $userids;
        shuffle($shuffled);

        foreach ($shuffled as $userid) {
            for ($slot = 0; $slot < $numpeers; $slot++) {
                // Candidates are all users except the user themselves and
                // anyone already in their peer list.
                $candidates = array_values(array_diff($userids, [$userid], $peers[$userid]));
                if (empty($candidates)) {
                    // Should not happen because numpeers < count(userids)
                    // is guaranteed by the early return above.
                    debugging(
                        "Could not assign peer slot {$slot} to user {$userid}: "
                            . 'no candidates remain (count='
                            . count($peers[$userid]) . ').',
                        DEBUG_NORMAL
                    );
                    break;
                }

                // Shuffle candidates so equal-count peers are picked
                // randomly, then pick the one with the lowest current
                // chosen-count.
                shuffle($candidates);
                $bestpeer = $candidates[0];
                $bestcount = $chosencount[$bestpeer];
                foreach ($candidates as $candidate) {
                    if ($chosencount[$candidate] < $bestcount) {
                        $bestpeer = $candidate;
                        $bestcount = $chosencount[$candidate];
                    }
                }

                $peers[$userid][] = $bestpeer;
                $chosencount[$bestpeer]++;
            }
        }

        return $peers;
    }

    /**
     *
     * @return boolean
     */
    public static function check_mp4_support() {
        // If Flash is supported, FlowPlayer offers better usability and responsiveness,
        // so we deliberately avoid using HTML5.
        if (class_exists('core_useragent')) {
            return \core_useragent::check_browser_version('MSIE')
                || \core_useragent::check_browser_version('WebKit')
                || \core_useragent::check_browser_version('Edge')
                || \core_useragent::check_browser_version('Firefox');
        } else {
            return check_browser_version('MSIE')
                || check_browser_version('WebKit')
                || check_browser_version('Edge')
                || check_browser_version('Firefox');
        }
    }

    /**
     * Render a lightweight preview of a user's associated videos.
     *
     * @return string Rendered HTML snippet
     */
    public function preview_video() {
        global $DB, $PAGE, $OUTPUT;

        $width = optional_param('width', 400, PARAM_INT);
        $height = optional_param('height', 300, PARAM_INT);

        $PAGE->set_pagelayout('embedded');

        $o = $OUTPUT->header();

        if ($videoid = optional_param('videoid', 0, PARAM_INT)) {
            $videorec = $DB->get_record('videoassessment_videos', ['id' => $videoid]);
            $video = new video($this->context, $videorec);
        } else {
            $userid = required_param('userid', PARAM_INT);
            $timing = required_param('timing', PARAM_ALPHA);
            $video = $this->get_associated_video($userid, $timing);
        }

        if ($video->ready) {
            $video->width = $width;
            $video->height = $height;
            $o .= $this->output->render($video);
        } else {
            $o .= \html_writer::tag('p', self::str('videonotfound'), ['style' => 'color:#fff']);
        }
        $o .= $OUTPUT->footer();

        return $o;
    }

    /**
     * Update gradebook items and optionally push grades.
     *
     * @param null|array|\stdClass $grades Grade data or null to update item only
     * @param null|string $gradingarea Specific area or null for all
     * @return int GRADE_UPDATE_* result code
     */
    public function update_grade_item($grades = null, $gradingarea = null) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $itemname = $this->va->name;
        $itemnumber = 0;
        if ($gradingarea) {
            $itemname .= ' (' . self::str($gradingarea) . ')';
            $itemnumber = $this->get_grade_item_number($gradingarea);
        }

        $params = [
            'itemname' => $itemname,
            'idnumber' => $this->cm->id,
        ];

        return grade_update(
            'mod/videoassessment',
            $this->course->id,
            'mod',
            'videoassessment',
            $this->instance,
            $itemnumber,
            $grades,
            $params
        );
    }

    /**
     * Map grading area key to grade item number.
     *
     * @param string $gradingarea Grading area key
     * @return int|null Grade item number (1..n) or null if not mapped
     */
    private function get_grade_item_number($gradingarea) {
        switch ($gradingarea) {
            case 'beforeteacher':
                return 1;
            case 'beforeself':
                return 2;
            case 'beforepeer':
                return 3;
            case 'afterteacher':
                return 4;
            case 'afterself':
                return 5;
            case 'afterpeer':
                return 6;
        }
        return null;
    }

    /**
     * Generate and send an Excel report of grades to the browser.
     *
     * @return void
     */
    private function download_xls_report() {
        global $CFG, $DB;

        $groupid = groups_get_activity_group($this->cm, true);
        $currentgroup = groups_get_group($groupid, 'name');

        $table = new table_export();
        $table->filename = $this->cm->name . '.xls';
        $fullnamestr = util::get_fullname_label();
        $table->set(0, 0, self::str('title') . ' ' . $this->cm->name);
        $table->set(1, 0, get_string('idnumber'));
        $table->set(1, 1, $fullnamestr);
        $table->set(1, 2, self::str('groupname'));
        $table->set(1, 3, self::str('teacherselfpeer'));
        $table->set(1, 4, self::str('assessedby') . ' (' . get_string('idnumber') . ')');
        $table->set(1, 5, self::str('assessedby') . ' (' . $fullnamestr . ')');
        $table->set(1, 6, self::str('total'));
        $fixedcolumns = 7;

        $rubric = new rubric($this);
        $headercriteria = [];
        foreach ($this->gradingareas as $gradingarea) {
            $controller = $rubric->get_available_controller($gradingarea);
            if ($controller) {
                $definition = $controller->get_definition();
                if (isset($definition->rubric_criteria)) {
                    foreach ($definition->rubric_criteria as $criterion) {
                        if (!in_array($criterion['description'], $headercriteria)) {
                            $headercriteria[] = $criterion['description'];
                        }
                    }
                }
            }
        }
        $headercriteria = array_flip($headercriteria);

        foreach ($headercriteria as $criterion => $index) {
            $table->set(1, $index + $fixedcolumns, $criterion);
        }

        $users = $this->get_students('u.id, u.lastname, u.firstname, u.idnumber', $groupid);
        $timingstrs = [
            'before' => $this->timing_str('before'),
            'after' => $this->timing_str('after'),
        ];
        $gradertypestrs = [
            'teacher' => self::str('teacher'),
            'self' => self::str('self'),
            'peer' => self::str('peer'),
            'class' => self::str('class'),
        ];
        $row = 2;
        foreach ($users as $user) {
            $fullname = fullname($user);

            if (!empty($currentgroup)) {
                $groupname = $currentgroup->name;
            } else {
                $groups = groups_get_all_groups($this->va->course, $user->id);
                $groupname = [];

                if (!empty($groups)) {
                    foreach ($groups as $group) {
                        $groupname[] = $group->name;
                    }
                }

                $groupname = implode(', ', $groupname);
            }

            foreach ($this->gradingareas as $gradingarea) {
                $gradeitems = $this->get_grade_items($gradingarea, $user->id);
                if ($controller = $rubric->get_available_controller($gradingarea)) {
                    foreach ($gradeitems as $gradeitem) {
                        $table->set($row, 0, $user->idnumber);
                        $table->set($row, 1, $fullname);
                        $table->set($row, 2, $groupname);
                        if (preg_match('/^(before|after)(self|peer|teacher|class)$/', $gradingarea, $m)) {
                            $table->set($row, 3, $gradertypestrs[$m[2]]);

                            if (empty($grader) || $grader->id != $gradeitem->grader) {
                                $grader = $DB->get_record(
                                    'user',
                                    ['id' => $gradeitem->grader],
                                    'id, lastname, firstname, idnumber'
                                );
                                if ($grader) {
                                    $gradername = fullname($grader);
                                }
                            }
                            if ($grader) {
                                $table->set($row, 4, $grader->idnumber);
                                $table->set($row, 5, $gradername);
                            }
                        }
                        $instances = $controller->get_active_instances($gradeitem->id);
                        if (isset($instances[0])) {
                            /* @var $instance \gradingform_rubric_instance */
                            $instance = $instances[0];
                            $definition = $instance->get_controller()->get_definition();
                            $filling = $instance->get_rubric_filling();
                            $table->set($row, 6, $gradeitem->grade);

                            foreach ($definition->rubric_criteria as $id => $criterion) {
                                $critfilling = $filling['criteria'][$id];
                                $level = $criterion['levels'][$critfilling['levelid']];

                                $table->set(
                                    $row,
                                    $headercriteria[$criterion['description']] + $fixedcolumns,
                                    $level['score']
                                );
                            }
                        }
                        $row++;
                    }
                }
            }
        }

        if (optional_param('csv', 0, PARAM_BOOL)) {
            $table->csv();
        } else {
            $table->xls(true);
        }
        exit();
    }

    /**
     * Get enrolled students for this activity, optionally filtered by group.
     *
     * @param string|null $userfields Comma-separated user fields to fetch
     * @param int|null $groupid Group id or null to detect current group
     * @return array List of user records
     */
    public function get_students($userfields = null, $groupid = null) {
        if (!$userfields) {
            $userfields = \core_user\fields::for_identity($this->context)->including('id', 'lastname', 'firstname', 'idnumber')->get_sql('u', false, '', '', false)->selects;
        }

        if ($groupid === null) {
            $groupid = groups_get_activity_group($this->cm, true);
        }

        return get_enrolled_users(
            $this->context,
            'mod/videoassessment:submit',
            $groupid,
            $userfields,
            'u.lastname, u.firstname'
        );
    }

    /**
     * Build a view URL for this activity with optional action and params.
     *
     * @param string $action Action key
     * @param array $params Additional URL params
     * @return \moodle_url Resulting URL
     */
    public function get_view_url($action = '', array $params = []) {
        $params['action'] = $action;

        return new \moodle_url($this->viewurl, $params);
    }

    /**
     * Get localized label for a timing, optionally wrapped in another string.
     *
     * @param string $timing Timing key, e.g. 'before' or 'after'
     * @param string|null $langstring Optional parent string identifier
     * @return string Localized label
     */
    public function timing_str($timing, $langstring = null) {
        $customlabel = $this->va->{$timing . 'label'};

        if ($customlabel !== '') {
            $label = $customlabel;
        } else {
            $label = self::str($timing);
        }

        if ($langstring) {
            return ucfirst(self::str($langstring, $label));
        }
        return ucfirst($label);
    }

    /**
     * Check whether a user (or current) has teacher grading capability.
     *
     * @param int|null $userid Optional user id, defaults to current user
     * @return boolean True if user can grade in this context
     */
    public function is_teacher($userid = null) {
        return has_capability('mod/videoassessment:grade', $this->context, $userid);
    }


    /**
     * Require teacher capability in this activity context.
     *
     * @return void
     */
    public function teacher_only() {
        require_capability('mod/videoassessment:grade', $this->context);
    }

    /**
     *
     * @param string $identifier
     * @param string|\stdClass $a
     * @return string
     */
    public static function str($identifier, $a = null) {
        return get_string($identifier, 'mod_videoassessment', $a);
    }

    /**
     *
     * @param int $vaid
     */
    public static function cleanup_old_peer_grades($vaid) {
        global $DB, $CFG;

        $gradeitems = $DB->get_records_sql(
            '
                SELECT * FROM {videoassessment_grade_items} gi
                    WHERE gi.videoassessment = :va
                        AND (gi.type = \'beforepeer\' OR gi.type = \'afterpeer\')
                        AND (
                            SELECT COUNT(*) FROM {videoassessment_peers} p
                                WHERE p.videoassessment = gi.videoassessment
                                    AND p.userid = gi.gradeduser
                                    AND p.peerid = gi.grader
                        ) = 0
                ',
            [
                'va' => $vaid,
            ]
        );
        foreach ($gradeitems as $gradeitem) {
            $DB->delete_records('videoassessment_grades', ['gradeitem' => $gradeitem->id]);
            $DB->delete_records('videoassessment_grade_items', ['id' => $gradeitem->id]);
        }

        $vas = $DB->get_records('videoassessment');
        foreach ($vas as $va) {
            $cm = get_coursemodule_from_instance('videoassessment', $va->id);
            $context = \context_module::instance($cm->id);
            $course = $DB->get_record('course', ['id' => $cm->course]);
            $vaobj = new self($context, $cm, $course);
            $vaobj->regrade();
        }
    }

    /**
     *
     * @return boolean
     */
    public static function uses_mobile_upload() {
        if (class_exists('core_useragent')) {
            $device = \core_useragent::get_device_type();
        } else {
            $device = get_device_type();
        }

        return $device == 'mobile' || $device == 'tablet';
    }

    /**
     * Get the list of courses that the specified user can access as a teacher.
     *
     * @param int $userid
     * @param int|null $catid Optional category id; when provided, restrict to that category.
     * @return object[]
     */
    public static function get_courses_managed_by($userid, $catid = null) {
        global $CFG;

        $managerroles = explode(',', $CFG->coursecontact);
        $courses = [];
        foreach (\enrol_get_all_users_courses($userid) as $course) {
            if (empty($catid) || $catid == $course->category) {
                $ctx = \context_course::instance($course->id);
                $rusers = \get_role_users($CFG->coursecontact, $ctx, true, 'u.id, u.lastname, u.firstname ');
                if (isset($rusers[$userid])) {
                    $courses[$course->id] = $course;
                }
            }
        }
        return $courses;
    }

    /**
     * Get users enrolled via manual enrolments that also have grades.
     *
     * @param int $courseid Course id
     * @return array List of user records
     */
    public static function get_users($courseid) {
        global $DB;

        $sql = '
                SELECT u.* FROM {user} u
                INNER JOIN {user_enrolments} ue ON u.id = ue.userid
                INNER JOIN {enrol} e ON ue.enrolid = e.id
                INNER JOIN {grade_grades} gg ON u.id = gg.userid
                WHERE e.enrol = :enrol AND e.courseid = :courseid
        ';

        $params = [
            'enrol' => 'manual',
            'courseid' => $courseid,
        ];

        $users = $DB->get_records_sql($sql, $params);
        return $users;
    }

    /**
     * Get courses that contain at least one Video Assessment module.
     *
     * @return array List of course records
     */
    public static function get_courses() {
        global $DB;

        $sql = '
                SELECT c.* FROM {course} c
                INNER JOIN {course_modules} cm ON c.id = cm.course
                INNER JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :name
        ';

        $params = [
            'name' => 'videoassessment',
        ];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the course module record for Video Assessment in a course.
     *
     * @param int $courseid Course id
     * @return object|null Course module record or null if not found
     */
    public static function get_cm($courseid) {
        global $DB;

        $sql = '
                SELECT cm.* FROM {course_modules} cm
                INNER JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :name AND cm.course = :courseid
        ';

        $params = [
            'name' => 'videoassessment',
            'courseid' => $courseid,
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get aggregate count and sum of a user's module grades in a course.
     *
     * @param int $courseid Course id
     * @param int $userid User id
     * @return object DB record with fields: count, total
     */
    public static function get_grade($courseid, $userid) {
        global $DB;

        $sql = '
                SELECT count(gi.id) as count, sum(gg.finalgrade) as total FROM {grade_items} gi
                LEFT JOIN {grade_grades} gg ON gi.id = gg.itemid
                WHERE gi.courseid = :courseid AND gg.userid = :userid AND gi.itemtype = :itemtype
        ';

        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
            'itemtype' => 'mod',
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get students list with optional manual sorting by group or course.
     *
     * @param int|null $groupid Group id to filter, or null for course scope
     * @param bool $sortmanually Whether to apply manual sort order if available
     * @param string|null $order Raw ORDER BY fragment when needed
     * @return array List of student records
     */
    public function get_students_sort($groupid = null, $sortmanually = false, $order = null) {
        global $DB;

        $userfields = \core_user\fields::for_identity($this->context)->including('id', 'lastname', 'firstname', 'idnumber')->get_sql('u', false, '', '', false)->selects;

        if ($sortmanually) {
            $order = ' ORDER BY sortorder ASC';
        }

        $contextcourse = \context_course::instance($this->course->id);
        $params = [
            'contextid' => $contextcourse->id,
            'roleid' => 5,
            'courseid' => $this->course->id,
        ];

        if (!empty($groupid)) {
            $join = ' JOIN {groups_members} gm ON gm.userid = u.id';
            $join .= ' LEFT JOIN {videoassessment_sort_items} vsi ON gm.groupid = vsi.itemid AND vsi.type = :type';
            $join .= ' LEFT JOIN {videoassessment_sort_order} vso ON vso.sortitemid = vsi.id AND vso.userid = u.id';
            $where = ' AND gm.groupid = :groupid';
            $params['groupid'] = $groupid;
            $params['type'] = 'group';
            $userfields .= ', vso.id as orderid, vso.sortorder as sortorder';
        } else {
            $join = ' LEFT JOIN {videoassessment_sort_items} vsi ON e.courseid = vsi.itemid AND vsi.type = :type';
            $join .= ' LEFT JOIN {videoassessment_sort_order} vso ON vso.sortitemid = vsi.id AND vso.userid = u.id';
            $where = '';
            $params['type'] = 'course';
            $userfields .= ', vso.id as orderid, vso.sortorder as sortorder';
        }

        $sql = "
            SELECT $userfields
            FROM {user} u
            JOIN {role_assignments} ra ON u.id = ra.userid
            JOIN {user_enrolments} ue ON u.id = ue.userid
            JOIN {enrol} e ON ue.enrolid = e.id" . $join . "
            WHERE ra.contextid = :contextid AND ra.roleid = :roleid AND e.courseid = :courseid
        " . $where . $order;

        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }

    /**
     * Get peer user ids for a user with optional manual sorting.
     *
     * @param int $userid Base user id whose peers are returned
     * @param int $groupid Group id to filter (0 for course scope)
     * @param bool $sortmanually Whether to apply manual sort order if available
     * @param string|null $order Raw ORDER BY fragment when needed
     * @return int[] Ordered list of peer user ids
     */
    public function get_peers_sort($userid, $groupid = 0, $sortmanually = false, $order = null) {
        global $DB;

        $contextcourse = \context_course::instance($this->course->id);
        $params = [
            'videoassessment' => $this->instance,
            'peerid' => $userid,
            'contextid' => $contextcourse->id,
        ];

        if ($sortmanually) {
            $order = ' ORDER BY sortorder ASC';
        }

        if (!empty($groupid)) {
            $join = ' JOIN {groups_members} gm ON gm.userid = u.id';
            $join .= ' LEFT JOIN {videoassessment_sort_items} vsi ON gm.groupid = vsi.itemid AND vsi.type = :type';
            $join .= ' LEFT JOIN {videoassessment_sort_order} vso ON vso.sortitemid = vsi.id AND vso.userid = u.id';
            $where = ' AND gm.groupid = :groupid';
            $params['groupid'] = $groupid;
            $params['type'] = 'group';
            $fields = ', vso.sortorder as sortorder';
        } else {
            $join = ' LEFT JOIN {videoassessment_sort_items} vsi ON e.courseid = vsi.itemid AND vsi.type = :type';
            $join .= ' LEFT JOIN {videoassessment_sort_order} vso ON vso.sortitemid = vsi.id AND vso.userid = u.id';
            $where = '';
            $params['type'] = 'course';
            $fields = ', vso.sortorder as sortorder';
        }

        // Use GROUP BY to handle duplicate userids from multiple role assignments or enrolments.
        // For sortorder, use MIN() to get the first sort order value (or NULL if not set).
        $groupbyfields = str_replace(', vso.sortorder as sortorder', ', MIN(vso.sortorder) as sortorder', $fields);
        $sql = "
            SELECT vp.userid $groupbyfields
            FROM {videoassessment_peers} vp
            JOIN {user} u ON vp.userid = u.id
            JOIN {role_assignments} ra ON u.id = ra.userid
            JOIN {user_enrolments} ue ON vp.userid = ue.userid
            JOIN {enrol} e ON ue.enrolid = e.id
            $join
            WHERE vp.videoassessment = :videoassessment AND vp.peerid = :peerid AND ra.contextid = :contextid
        " . $where . "
            GROUP BY vp.userid" . $order;

        $students = $DB->get_records_sql($sql, $params);
        $peerids = [];
        foreach ($students as $student) {
            $peerids[] = $student->userid;
        }

        return $peerids;
    }

    /**
     * Fetch archived grading instances for a controller and item.
     *
     * @param \gradingform_controller $controller Grading controller
     * @param int $itemid Grade item id
     * @return \gradingform_rubric_instance[] List of archived instances
     */
    public function get_archive_instances($controller, $itemid) {
        global $DB;
        $conditions = [
            'definitionid' => $controller->get_definition()->id,
            'itemid' => $itemid,
            'status' => \gradingform_instance::INSTANCE_STATUS_ARCHIVE,
        ];
        $records = $DB->get_recordset('grading_instances', $conditions);
        $rv = [];
        foreach ($records as $record) {
            $rv[] = new \gradingform_rubric_instance($controller, $record);
        }
        return $rv;
    }

    /**
     * Build HTML for the training result comparison table.
     *
     * @param object $definition Rubric definition
     * @param array $studentfilling Student rubric filling
     * @param array $teacherfilling Teacher rubric filling
     * @param array $historyfillings Optional historic student fillings
     * @return array{0:string,1:bool,2:array} [html, passed, passedCriterionIds]
     */
    public function get_training_result_table($definition, $studentfilling, $teacherfilling, $historyfillings = []) {
        $o = '';
        $passed = true;
        $rubricspassed = [];
        $even = 1;

        if (!empty($definition)) {
            foreach ($definition->rubric_criteria as $rid => $rubric) {
                if ($even == 1) {
                    $even = 0;
                    $trclass = 'even';
                } else {
                    $even = 1;
                    $trclass = 'odd';
                }

                $o .= \html_writer::start_tag('tr', ['class' => 'rubric-result ' . $trclass, 'id' => 'advancedgradingbefore-criteria-' . $rid]);

                $o .= \html_writer::start_tag('td', ['class' => 'bold']);
                $o .= $rubric['description'];
                $o .= \html_writer::end_tag('td');

                $scores = [];
                $row = '';
                $icon = '';

                foreach ($rubric['levels'] as $lid => $level) {
                    $selecteds = '';
                    $tdclass = '';
                    $selected = false;

                    if (!empty($studentfilling) && $studentfilling['criteria'][$rid]['levelid'] == $lid) {
                        $selecteds .= \html_writer::start_tag('span', ['class' => 'student-selected score-selected']);
                        $selecteds .= self::str('self');
                        $selecteds .= \html_writer::end_tag('span');
                        $selecteds .= '<br>';

                        $tdclass .= ' student-td';
                        $selected = true;
                    } else if (!empty($historyfillings) && isset($historyfillings[$rid]) && in_array($lid, $historyfillings[$rid])) {
                        $selecteds .= \html_writer::start_tag('span', ['class' => 'student-selected score-selected']);
                        $selecteds .= self::str('self');
                        $selecteds .= \html_writer::end_tag('span');
                        $selecteds .= '<br>';

                        $tdclass .= ' student-history-td';
                    }

                    if (!empty($teacherfilling) && $teacherfilling['criteria'][$rid]['levelid'] == $lid) {
                        $selecteds .= \html_writer::start_tag('span', ['class' => 'teacher-selected score-selected']);
                        $selecteds .= self::str('teacher');
                        $selecteds .= \html_writer::end_tag('span');
                        $selecteds .= '<br>';

                        $tdclass .= ' teacher-td';
                        $selected = true;
                    }

                    if ($selected) {
                        $tdclass .= ' selected';
                    }

                    $row .= \html_writer::start_tag('td', ['class' => $tdclass]);
                    $row .= \html_writer::start_tag('div');
                    $row .= $level['definition'];
                    $row .= \html_writer::end_tag('div');
                    $row .= \html_writer::start_tag('div', ['class' => 'score']);
                    $row .= $level['score'] . ' ' . get_string('points', 'grades');
                    $row .= \html_writer::end_tag('div');
                    $row .= \html_writer::start_tag('div', ['class' => 'score-selected-wrap']);

                    $row .= $selecteds;

                    $row .= \html_writer::end_tag('td');
                    $row .= \html_writer::end_tag('td');

                    $scores[$lid] = $level['score'];
                }

                if (!empty($teacherfilling) && !empty($studentfilling)) {
                    $minscore = min($scores);
                    $maxscore = max($scores);
                    $differencescore = abs($scores[$studentfilling['criteria'][$rid]['levelid']] - $scores[$teacherfilling['criteria'][$rid]['levelid']]);
                    $accepteddifference = $this->va->accepteddifference;
                    $difference = ($differencescore / ($maxscore - $minscore)) * 100;

                    if ($difference > $accepteddifference) {
                        $passed = false;
                        $icon = 'failed';
                    } else {
                        $icon = 'passed';
                        $rubricspassed[] = $rid;
                    }
                }

                $o .= \html_writer::start_tag('td');
                $o .= \html_writer::start_tag('table');
                $o .= \html_writer::start_tag('tr', ['class' => 'criterion-' . $icon]);

                $o .= $row;

                $o .= \html_writer::end_tag('tr');
                $o .= \html_writer::end_tag('table');
                $o .= \html_writer::end_tag('td');

                if (!empty($icon)) {
                    $o .= \html_writer::start_tag('td', ['class' => 'status']);
                    $o .= \html_writer::img('images/' . $icon . '.gif', $icon);
                    $o .= \html_writer::end_tag('td');
                }

                $o .= \html_writer::end_tag('tr');
            }
        }

        return [$o, $passed, $rubricspassed];
    }

    /**
     * Get a sort item record by type and item id.
     *
     * @param string $type 'course' or 'group'
     * @param int $itemid Course id or group id
     * @return object|null Sort item record
     */
    public function get_sort_items($type, $itemid) {
        global $DB;
        return $DB->get_record('videoassessment_sort_items', ['type' => $type, 'itemid' => $itemid]);
    }
}
