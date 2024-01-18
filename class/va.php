<?php

namespace videoassess;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->libdir . '/tablelib.php';
require_once $CFG->dirroot . '/grade/grading/form/lib.php';

class va
{
    const VA = 'videoassessment';

    const TABLE_GRADE_ITEMS = 'videoassessment_grade_items';
    const TABLE_GRADES = 'videoassessment_grades';

    const FILTER_ALL = 0;
    const FILTER_SUBMITTED = 1;
    const FILTER_REQUIRE_GRADING = 2;

    const THUMBEXT = '.jpg';

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
    public $timings = array('before');
    /**
     *
     * @var array
     */
    /* MinhTB VERSION 2 08-03-2016 */
    public $gradertypes = array('self', 'peer', 'teacher', 'class', 'training');
    /* END MinhTB VERSION 2 08-03-2016 */
    /**
     *
     * @var array
     */
    public $gradingareas;

    /**
     *
     * @param \context_module $context
     * @param \cm_info|\stdClass $cm
     * @param \stdClass $course
     */
    public function __construct(\context_module $context, $cm, \stdClass $course)
    {
        global $DB, $PAGE;

        $this->context = $context;
        $this->cm = $cm;
        $this->course = $course;
        $this->instance = $cm->instance;

        if (!($this->va = $DB->get_record('videoassessment', array('id' => $cm->instance)))) {
            throw new \moodle_exception('videoassessmentnotfound');
        }

        $this->output = $PAGE->get_renderer('mod_videoassessment');
        $this->output->va = $this;

        $this->viewurl = new \moodle_url('/mod/videoassessment/view.php', array('id' => $this->cm->id));

        $this->jsmodule = array(
            'name' => 'mod_videoassessment',
            'fullpath' => '/mod/videoassessment/module.js',
            'requires' => array('panel', 'dd-plugin', 'json-stringify')
        );

        /* MinhTB VERSION 2*/
        $PAGE->requires->strings_for_js(array(
            'liststudents', 'unassociated', 'associated', 'before', 'after', 'saveassociations',
            'teacher', 'self', 'peer', 'class', 'reallyresetallpeers', 'reallydeletevideo'
        ), 'videoassessment');
        /* END */

        $PAGE->requires->strings_for_js(array('all'), 'moodle');

        foreach ($this->timings as $timing) {
            foreach ($this->gradertypes as $gradertype) {
                $this->gradingareas[] = $timing . $gradertype;
            }
        }
    }

    /**
     *
     * @param string $action
     * @return string
     */
    public function view($action = '')
    {
        global $PAGE;

        $this->action = $action;

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
                $this->delete_one_video_byId();
                break;
            case 'videotoresource':
                $this->export_video_to_resource();
                break;
            case 'downloadxls':
                $this->download_xls_report();
                break;
        }

        if ($action == '') {
            // 横に長いページはスクロールバーが出るレイアウトを使用
            $PAGE->set_pagelayout('report');
            $PAGE->requires->css('/mod/videoassessment/view.css');
            $PAGE->requires->css('/mod/videoassessment/font/font-awesome/css/font-awesome.min.css');
        }

        if ($action == 'report' || $action == 'publish' || $action == 'upload') {
            $PAGE->requires->css('/mod/videoassessment/view.css');
            $PAGE->requires->css('/mod/videoassessment/getHTMLMediaElement.css');
        }

        /* MinhTB VERSION 2 09-03-2016 */
        if ($action == 'assess' || $action == 'trainingresult') {
            $PAGE->blocks->show_only_fake_blocks();
            $PAGE->requires->css('/mod/videoassessment/assess.css');
            $PAGE->add_body_class('assess-page'); //Le Xuan Anh Ver2
        }

        /* END MinhTB VERSION 2 09-03-2016 */

        /* MinhTB VERSION2 08-03-2016 */
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
        /* END MinhTB VERSION2 08-03-2016 */
        $o .= $this->output->footer();
        return $o;
    }

    /**
     * @param string $action
     * @param array $params
     */
    private function view_redirect($action = '', $params = null)
    {
        if ($action) {
            $this->viewurl->param('action', $action);
        }

        if ($params) {
            $this->viewurl->params($params);
        }

        redirect($this->viewurl);
    }

    public function emailtostudent($cmid, $timing)
    {
        global $DB, $USER;
        $ismailsent = 0;
        $videoassessment = $DB->get_record("videoassessment", array("id" => $cmid));
        if ($videoassessment->videonotification == 1 && has_capability('mod/videoassessment:submit', $this->context, $USER->id)) {
            if ((!$this->get_associated_video($USER->id, $timing) && $videoassessment->isfirstupload == 1) ||
                ($this->get_associated_video($USER->id, $timing) && $videoassessment->iswheneverupload == 1)) {
                $context = \context_module::instance($cmid);
                $teachers = get_enrolled_users($context, 'mod/videoassessment:grade', NULL);
                $mailTemplate = $videoassessment->videonotificationtemplate;
                $url = new \moodle_url($this->viewurl,
                    array('action' => 'assess', 'userid' => current($teachers)->id));
                $templateArray = array("[[student name]]" => $USER->firstname . ' ' . $USER->lastname,
                    "[[insert link to self-assessment page]]" => $url->out(false),
                    "[[teacher name]]" => current($teachers)->firstname . ' ' . current($teachers)->lastname);

                foreach ($templateArray as $item => $template) {
                    $mailTemplate = str_replace($item, $template, $mailTemplate);
                }
                $quickmailresult = false;
                $registeredemailresult = false;
                if ($videoassessment->isregisteredemail == 1) {
                    $registeredemailresult = email_to_user(current($teachers), $USER, "", $mailTemplate);
                }
                if ($videoassessment->ismobilequickmail == 1) {
                    $quickmail = $DB->get_record('block_quickmailjpn_users', array('userid' => current($teachers)->id));
                    if (!empty($quickmail)) {
                        $mobileuser = current($teachers);
                        $mobileuser->email = $quickmail->mobileemail;
                        $quickmailresult = email_to_user($mobileuser, $USER, "", $mailTemplate);
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
     * @return string
     */
    private function view_upload_video()
    {
        global $CFG, $OUTPUT, $USER, $DB;
        require_once $CFG->dirroot . '/mod/videoassessment/bulkupload/lib.php';

        $o = '';


        $form = new form\video_upload(null, (object)array('va' => $this));

        if ($data = $form->get_data()) {
            $fs = get_file_storage();
            $upload = new \videoassessment_bulkupload($this->cm->id);

            if ((!empty($data->url) || !empty($data->mobileurl)) && $data->upload == 1) {
                if (empty($data->url)) {
                    $url = $data->mobileurl;
                } else {
                    $url = $data->url;
                }
                $urlArr = explode('=', $url);
                $ytinfo = $this->getYouTubeInfo($urlArr[1]);
                $videoid = $upload->youtube_video_data_add("/", $ytinfo['title'], $ytinfo['thumbnail_url'], 'Youtube', $url);
                if ($this->is_teacher()) {
                    if (empty($data->user) || empty($data->timing)) {
                        $this->view_redirect('videos');
                    } else {
                        $this->associate_video($data->user, $data->timing, $videoid);
                        $this->view_redirect();
                    }
                } else {
                    if (empty($data->timing) || !in_array($data->timing, array('before', 'after'))) {
                        throw new \moodle_exception('invaliddata', va::VA);
                    }
                    $this->associate_video($USER->id, $data->timing, $videoid);
                    $this->emailtostudent($this->cm->instance, $data->timing);
                    $this->view_redirect();
                }
            } else {
                if($_POST['isRecordVideo'] == 1){
                    $upload = new \videoassessment_bulkupload($this->cm->id);
                    $file_idx = 'video';
                    $fileName = $_POST['video-filename'];
                    $tempName = $upload->get_temp_name($_FILES[$file_idx]['name']);
                    $upload->create_temp_dirs();
                    $tmppath = $upload->get_tempdir() . '/upload/' . $tempName;
                    if (!move_uploaded_file($_FILES[$file_idx]['tmp_name'], $tmppath)) {
                        throw new \moodle_exception('invaliduploadedfile', va::VA);
                    }
                    $videoid = $upload->video_data_add($tempName, $fileName);
                    $upload->convert($tempName);
                    $action = "";
                    if ($this->is_teacher()) {
                        if (empty($data->user) || empty($data->timing)) {
                            $action = 'videos';
                            $this->view_redirect('videos');
                        } else {
                            $this->associate_video($data->user, $data->timing, $videoid);
                        }
                    } else {
                        if (empty($data->timing) || !in_array($data->timing, array('before', 'after'))) {
                            throw new \moodle_exception('invaliddata', va::VA);
                        }
                        $this->associate_video($USER->id, $data->timing, $videoid);
                        $this->emailtostudent($this->cm->instance, $data->timing);
                    }
                    echo json_encode(array(
                        'action' => $action,
                    ));
                    die;
                }else{
                    if (!empty($data->mobile)) {
                        if (empty($_FILES['mobilevideo'])) {
                            print_error('erroruploadvideo', self::VA);
                        }
                        $upload->create_temp_dirs();
                        $tmpname = $upload->get_temp_name($_FILES['mobilevideo']['name']);
                        $tmppath = $upload->get_tempdir() . '/upload/' . $tmpname;
                        if (!move_uploaded_file($_FILES['mobilevideo']['tmp_name'], $tmppath)) {
                            throw new \moodle_exception('invaliduploadedfile', va::VA);
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
                            if (empty($data->timing) || !in_array($data->timing, array('before', 'after'))) {
                                throw new \moodle_exception('invaliddata', va::VA);
                            }
                            $this->associate_video($USER->id, $data->timing, $videoid);
                            $this->emailtostudent($this->cm->instance, $data->timing);
                        }
                        echo json_encode(array(
                            'action' => $action,
                        ));
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
                                if (empty($data->timing) || !in_array($data->timing, array('before', 'after'))) {
                                    throw new \moodle_exception('invaliddata', va::VA);
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

    function getYouTubeInfo($video_id)
    {
        /*     	$curl = curl_init();
                 $url = "https://youtube.com/get_video_info?video_id=" . $video_id;
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $contents = curl_exec($curl);
                curl_close($curl);
                if($contents){
                    parse_str($contents, $ytarr);
                }else{

                } */
        $ytarr = array(
            'title' => 'video_id=' . $video_id,
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . $video_id . '/1.jpg'
        );
        return $ytarr;
    }

    /**
     * @return string
     */
    private function view_peers()
    {
        global $DB, $PAGE, $OUTPUT;

        $this->teacher_only();

        $PAGE->requires->js_init_call('M.mod_videoassessment.peers_init', null, false, $this->jsmodule);

        $o = '';

        $url = $this->get_view_url('peers');
        $o .= groups_print_activity_menu($this->cm, $url, true);

        $o .= \html_writer::start_tag('div', array('class' => 'right'))
            . self::str('assignpeersrandomly')
            . ': ' . $this->output->action_link(
                $this->get_view_url('randompeer', array('peermode' => 'course')),
                self::str('course'), null,
                array('class' => 'randompeerslink',
                    'onclick' => 'return M.mod_videoassessment.peers_confirm_random();'
                )
            )
            . ' | ' . $this->output->action_link(
                $this->get_view_url('randompeer', array('peermode' => 'group')),
                self::str('group'), null,
                array('class' => 'randompeerslink',
                    'onclick' => 'return M.mod_videoassessment.peers_confirm_random();'
                )
            )
            . \html_writer::end_tag('div');

        $table = new \flexible_table('peers');
        $table->set_attribute('class', 'generaltable');
        $table->define_baseurl('/mod/videoassessment/view.php');
        $columns = array(
            'name',
            'peers'
        );
        $headers = array(
            util::get_fullname_label(),
            self::str('peers')
        );
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();

        $allusers = $this->get_students(null, 0);
        $users = $this->get_students();

        $delicon = new \pix_icon('t/delete', get_string('delete'));
        ob_start();
        foreach ($users as $user) {
            $peers = $DB->get_fieldset_select('videoassessment_peers', 'peerid',
                'videoassessment = :va AND userid = :userid',
                array('va' => $this->instance, 'userid' => $user->id));
            $peernames = array();
            foreach ($peers as $peer) {
                $this->viewurl->params(array('action' => 'peerdel', 'userid' => $user->id,
                    'peerid' => $peer));
                @$peernames[] = fullname($allusers[$peer])
                    . ' ' . $OUTPUT->action_icon($this->viewurl, $delicon);
            }
            \core_collator::asort($peernames);
            $peercell = implode(\html_writer::empty_tag('br'), $peernames);

            $opts = array();
            foreach ($users as $candidate) {
                if ($candidate->id != $user->id && !in_array($candidate->id, $peers)) {
                    $opts[$candidate->id] = fullname($candidate);
                }
            }
            $this->viewurl->params(array('action' => 'peeradd', 'userid' => $user->id));
            $peercell .= $OUTPUT->single_select($this->viewurl, 'peerid', $opts, null, array(self::str('addpeer')));

            $row = array(
                fullname($user),
                $peercell
            );
            $table->add_data($row);
        }
        $table->finish_output();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::start_tag('div', array('class' => 'center-btn')) . $this->output->action_link(new \moodle_url("/course/modedit.php", array('update'=>$this->cm->id, 'return'=>1)),
                'save and return to settings', null,
                array('class' => 'btn btn-primary')
            ) . \html_writer::end_tag('div');
        return $o;
    }

    private function add_peer_member()
    {
        global $DB;

        $DB->insert_record('videoassessment_peer_assocs', (object)array(
            'videoassessment' => $this->instance,
            'userid' => required_param('userid', PARAM_INT),
            'peergroup' => required_param('peergroup', PARAM_INT)
        ));

        $this->view_redirect('peers');
    }

    private function view_peer_add()
    {
        global $DB;

        $DB->insert_record('videoassessment_peers', (object)array(
            'videoassessment' => $this->instance,
            'userid' => required_param('userid', PARAM_INT),
            'peerid' => required_param('peerid', PARAM_INT)));

        $this->view_redirect('peers');
    }

    private function view_peer_delete()
    {
        global $DB;

        $userid = required_param('userid', PARAM_INT);
        $peerid = required_param('peerid', PARAM_INT);

        foreach ($this->timings as $timing) {
            if ($gradeitem = $DB->get_record('videoassessment_grade_items', array(
                'videoassessment' => $this->instance,
                'type' => $timing . 'peer',
                'gradeduser' => $userid,
                'grader' => $peerid
            ))) {
                $DB->delete_records('videoassessment_grades', array('gradeitem' => $gradeitem->id));
                $DB->delete_records('videoassessment_grade_items', array('id' => $gradeitem->id));
            }
        }

        $DB->delete_records('videoassessment_peers', array(
            'videoassessment' => $this->instance,
            'userid' => $userid,
            'peerid' => $peerid
        ));

        $this->view_redirect('peers');
    }

    private function add_peer_group()
    {
        global $DB;

        $DB->insert_record('videoassessment_peer_groups', (object)array(
            'videoassessment' => $this->instance
        ));
    }

    /**
     * @return string
     * @global \moodle_page $PAGE
     * @global \core_renderer $OUTPUT
     */
    private function view_videos()
    {
        global $OUTPUT, $PAGE;

        $this->teacher_only();

        $filter = optional_param('filter', 'unassociated', PARAM_ALPHA);

        $url = $this->get_view_url('videos');
        if ($filter) {
            $url->param('filter', $filter);
        }

        $o = '';

        $o .= groups_print_activity_menu($this->cm, $url, true);

        $opts = array(
            'unassociated' => self::str('unassociated'),
            'associated' => self::str('associated'),
            'all' => get_string('all')
        );
        $o .= $OUTPUT->single_select($this->get_view_url('videos'), 'filter', $opts, $filter,
            null);

        $table = new \flexible_table('videos');
        $table->set_attribute('class', 'generaltable');
        $table->define_baseurl('/mod/videoassessment/videos.php');
        $columns = array(
            'filepath',
            'originalname',
            'timecreated',
            'association',
            'operations'
        );
        $headers = array(
            self::str('video'),
            self::str('originalname'),
            self::str('uploadedtime'),
            self::str('associations'),
            self::str('operations')
        );
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();

        $thumbsize = self::get_thumbnail_size();

        $users = $this->get_students(\user_picture::fields('u'), 0);
        array_walk($users, function (\stdClass $a) {
            global $OUTPUT;
            $a->fullname = fullname($a);
            $a->assocvideos = array();
            $a->userpicture = $OUTPUT->user_picture($a);
        });

        $assocdata = array();

        $groupid = groups_get_activity_group($this->cm, true);
        $groupmembers = groups_get_members($groupid, 'u.id');

        $strtimings = array(
            'before' => $this->timing_str('before'),
            'after' => $this->timing_str('after')
        );
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
                $attr = array(
                    'src' => $v->thumbnailname);
            } else {
                $attr = array(
                    'src' => \moodle_url::make_pluginfile_url(
                        $this->context->id, 'mod_videoassessment', 'video', 0,
                        $v->filepath, $thumbname)
                );
            }
            if ($thumbsize) {
                $attr['width'] = $thumbsize->width;
                $attr['height'] = $thumbsize->height;
            }
            $thumb = \html_writer::empty_tag('img', $attr);

            if ($v->tmpname == 'Youtube') {
                $videocell = '<a href=' . $v->originalname . ' id=' . $v->id . ' class="video-thumb" >' . $thumb . '</a>';
            } else {
                $videocell = $OUTPUT->action_link(\moodle_url::make_pluginfile_url(
                    $this->context->id, 'mod_videoassessment', 'video', 0,
                    $v->filepath, $v->filename),
                    $thumb, null, array('id' => 'video[' . $v->id . ']', 'class' => 'video-thumb'));
            }
            //$assoccell = \html_writer::tag('div', get_string('loading', 'videoassessment'), array(
            //        'id' => 'assoc_'.$v->id, 'class' => 'assoccell'));
            $assoccell = '';
            $assocusers = array();
            foreach ($assocs as $assoc) {
                if (!isset($users[$assoc->associationid])) {
                    continue;
                }
                $user = &$users[$assoc->associationid];
                $assocdelurl = new \moodle_url($url,
                    array('action' => 'assocdel', 'userid' => $user->id, 'timing' => $assoc->timing));
                $assocusers[$user->id] = $user->userpicture . $user->fullname
                    . $OUTPUT->action_icon($assocdelurl, $disassocicon);
                $user->assocvideos[] = (int)$v->id;
            }
            \core_collator::asort($assocusers);
            $assoccell .= implode(\html_writer::empty_tag('br'), $assocusers);
            $assoccell .= \html_writer::empty_tag('br');
//            $assoccell .= \html_writer::empty_tag('br')
//                .\html_writer::empty_tag('input', array(
//                        'type' => 'button',
//                        'value' => get_string('associate', 'videoassessment').'...',
//                        'onclick' => 'M.mod_videoassessment.videos_show_assoc_panel('.$v->id.')'));
            $opts = array();
            foreach ($users as $candidate) {
                if ($groupid && empty($groupmembers[$candidate->id])) {
                    continue;
                }
                if (empty($assocusers[$candidate->id])) {
                    $opts[$candidate->id] = fullname($candidate);
                }
            }
            $assoccell .= \html_writer::start_tag('form',
                array('method' => 'get', 'action' => $url->out_omit_querystring(true))
            );
            $assoccell .= \html_writer::input_hidden_params(
                new \moodle_url($url, array('sesskey' => sesskey(), 'action' => 'assocadd', 'videoid' => $v->id, 'timing' => 'before'))
            );
            $assoccell .= \html_writer::select($opts, 'userid');
            $assoccell .= \html_writer::empty_tag('input', array('type' => 'submit', 'value' => $strassociate));
            $assoccell .= \html_writer::end_tag('form');

            $opcell = $this->output->action_link($this->get_view_url('videodel',
                array('videoid' => $v->id, 'filter' => $filter)),
                $this->output->pix_icon('t/delete', '') . ' ' . self::str('deletevideo'),
                null, array('class' => 'videodel'));

            $row = array(
                $videocell,
                $v->originalname,
                userdate($v->timecreated),
                $assoccell,
                $opcell
            );
            $table->add_data($row);
        }

        $table->finish_output();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::tag('div', '', array('id' => 'assocpanel'));

        $form = new form\video_assoc(null, (object)array(
            'cmid' => $this->cm->id
        ));
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

        $PAGE->requires->js_init_call('M.mod_videoassessment.videos_init',
            array($groupusers, $assocdata), false, $this->jsmodule);
        $PAGE->requires->strings_for_js(array('liststudents', 'unassociated', 'associated', 'before',
            'after', 'saveassociations'), 'videoassessment');
        $PAGE->requires->strings_for_js(array('all'), 'moodle');

        return $o;
    }

    private function delete_video()
    {
        global $DB;

        $videoid = required_param('videoid', PARAM_INT);

        $video = $DB->get_record('videoassessment_videos', array('id' => $videoid));

        $fs = get_file_storage();

        $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $video->filepath, $video->filename);
        if ($file) {
            $file->delete();
        }

        $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $video->filepath, $video->thumbnailname);
        if ($file) {
            $file->delete();
        }

        $DB->delete_records('videoassessment_videos', array('id' => $videoid));
        $DB->delete_records('videoassessment_video_assocs', array('videoid' => $videoid));

        $this->view_redirect('videos',
            array('filter' => optional_param('filter', 'unassociated', PARAM_ALPHA)));
    }

    /**
     *
     * @param int $videoid
     */
    public function delete_one_video_byId()
    {
        global $DB;
        $videoid = required_param('videoid', PARAM_INT);

        $video = video::from_id($this->context, $videoid);
        $video->delete_file();

        $DB->delete_records('videoassessment_videos', array('id' => $videoid));
        $DB->delete_records('videoassessment_video_assocs', array('videoid' => $videoid));

        $this->view_redirect();
    }

    /**
     *
     * @param int $videoid
     */
    public function delete_one_video($videoid)
    {
        global $DB;

        $video = video::from_id($this->context, $videoid);
        $video->delete_file();

        $DB->delete_records('videoassessment_videos', array('id' => $videoid));
        $DB->delete_records('videoassessment_video_assocs', array('videoid' => $videoid));
    }

    /**
     * @return array
     */
    public function get_videos()
    {
        global $DB;

        return $DB->get_records('videoassessment_videos', array('videoassessment' => $this->instance));
    }

    /**
     *
     * @param int $videoid
     * @return array
     */
    public function get_video_associations($videoid)
    {
        global $DB;

        return $DB->get_records('videoassessment_video_assocs', array('videoid' => $videoid));
    }

    /**
     *
     * @param int $userid
     * @param string $timing
     * @return video
     */
    public function get_associated_video($userid, $timing)
    {
        global $DB;

//         if ($assoc = $DB->get_record('videoassessment_video_assocs', array(
//                 'videoassessment' => $this->instance,
//                 'timing' => $timing,
//                 'associationid' => $userid,
//         ))) {
        if ($assocs = $DB->get_records('videoassessment_video_assocs', array(
            'videoassessment' => $this->instance,
            'timing' => $timing,
            'associationid' => $userid
        ))) {
            $assoc = reset($assocs);

            $data = $DB->get_record('videoassessment_videos', array('id' => $assoc->videoid));
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
     * ユーザの評定権限を調べる
     *
     * @param int $gradeduserid
     * @return string teacher/self/peer/class
     */
    public function get_grader_type($gradeduserid, $gradertype = null)
    {
        global $USER;

        if (!empty($gradertype)) return $gradertype;

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
     * @return \stdClass
     */
    private static function get_thumbnail_size()
    {
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
     *
     * @param int $userid
     * @param string $timing
     * @param int $videoid
     * @param int $associationtype
     * @global \moodle_database $DB
     */
    private function associate_video($userid, $timing, $videoid, $associationtype = 1)
    {
        global $DB;

        $this->disassociate_video($userid, $timing);
        $DB->insert_record('videoassessment_video_assocs', array(
            'videoassessment' => $this->instance,
            'videoid' => $videoid,
            'associationtype' => $associationtype,
            'timing' => $timing,
            'associationid' => $userid,
            'timemodified' => time(),
        ));
    }

    /**
     *
     * @param int $userid
     * @param string $timing
     * @global \moodle_database $DB
     */
    private function disassociate_video($userid, $timing)
    {
        global $DB;

        $DB->delete_records('videoassessment_video_assocs', array(
            'videoassessment' => $this->instance,
            'timing' => $timing,
            'associationid' => $userid,
        ));
    }

    private function assign_random_peers()
    {
        global $DB;

        $peermode = required_param('peermode', PARAM_ALPHA);
        if ($peermode == 'group') {
            $groups = groups_get_all_groups($this->course->id);
            $groupids = array_keys($groups);
        } else {
            $groupids = array(0);
        }

        foreach ($groupids as $groupid) {
            $users = get_enrolled_users($this->context, 'mod/videoassessment:submit', $groupid, 'u.id');
            $userids = array_keys($users);

            $mappings = $this->get_random_peers_for_users($userids, $this->va->usedpeers);

            foreach ($mappings as $id => $peers) {
                $DB->delete_records('videoassessment_peers',
                    array('videoassessment' => $this->instance, 'userid' => $id));

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

    /** @global \moodle_database $DB */
    private function view_assoc_add()
    {
        global $DB;

        $this->teacher_only();
        $videoid = required_param('videoid', PARAM_INT);
        $cond = array(
            'videoassessment' => $this->instance,
            'associationtype' => 1,
            'associationid' => required_param('userid', PARAM_INT),
            'timing' => required_param('timing', PARAM_ALPHA),
        );
        if (!empty($cond['associationid']) && !empty($cond['timing'])) {
            if ($id = $DB->get_field('videoassessment_video_assocs', 'id', $cond)) {
                $DB->set_field('videoassessment_video_assocs', 'videoid', $videoid, array('id' => $id));
            } else {
                $record = $cond + array('videoid' => $videoid);
                $DB->insert_record('videoassessment_video_assocs', (object)$record);
            }
        }
        $this->view_redirect('videos',
            array('filter' => optional_param('filter', 'unassociated', PARAM_ALPHA)));
    }

    /** @global \moodle_database $DB */
    private function view_assoc_delete()
    {
        global $DB;

        $this->teacher_only();
        $cond = array(
            'videoassessment' => $this->instance,
            'associationtype' => 1,
            'associationid' => required_param('userid', PARAM_INT),
            'timing' => required_param('timing', PARAM_ALPHA),
            //'videoid' => required_param('videoid', PARAM_INT),
        );
        $DB->delete_records('videoassessment_video_assocs', $cond);
        $this->view_redirect('videos',
            array('filter' => optional_param('filter', 'unassociated', PARAM_ALPHA)));
    }

    private function view_video_associate()
    {
        global $DB;

        $this->teacher_only();

        $assocform = new form\video_assoc();
        $data = $assocform->get_data();

        $assoc = (object)array(
            'videoassessment' => $this->instance,
            'videoid' => $data->videoid,
            'associationtype' => 1,
            'timing' => $data->timing
        );
        $ids = json_decode($data->assocdata);
        foreach ($ids as $item) {
            $assoc->associationid = $item[0];
            $cond = array('videoassessment' => $this->instance, 'associationid' => $assoc->associationid,
                'videoid' => $data->videoid, 'timing' => $data->timing);
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
     * @return string
     */
    /* MinhTB VERSION 2 08-03-2016 */
    private function view_main()
    {
        global $OUTPUT, $PAGE, $DB, $USER;

        $o = '';
        if ($this->cm->showdescription == 1) {
            $o .= $this->va->intro;
        }
        $time = time();
        //$cutoffdate = $this->va->cutoffdate;
        //if ($cutoffdate - $time > 0 || $this->is_teacher()) {
            $gradetable = new grade_table($this);
            if ($this->is_teacher()) {
                $o .= $gradetable->print_teacher_grade_table();
            } else {
                $trainingPassed = $DB->get_field('videoassessment_aggregation', 'passtraining', array(
                    'videoassessment' => $this->va->id,
                    'userid' => $USER->id
                ));

                if (!$this->va->training || $trainingPassed == 1) {
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

            $o .= \html_writer::tag('div', '', array('id' => 'videopreview'));

            $PAGE->requires->js_init_call('M.mod_videoassessment.main_init', array($this->cm->id),
                false, $this->jsmodule);

            if ($this->is_teacher()) {
                $o .= $OUTPUT->box_start();
                $url = new \moodle_url('/mod/videoassessment/print.php',
                    array('id' => $this->cm->id, 'action' => 'report'));
                $o .= $OUTPUT->action_link($url, self::str('printrubrics'),
                    new \popup_action('click', $url, 'popup',
                        array('width' => 800, 'height' => 700, 'menubar' => true)));
                $o .= \html_writer::empty_tag('br');
                $o .= $OUTPUT->action_link(
                    new \moodle_url($this->viewurl, array('action' => 'downloadxls')),
                    self::str('downloadexcel'));
                $o .= $OUTPUT->box_end();
            }
       // }
        $o .= $this->output->render_videoassess_info_status($this->va);

        return $o;
    }
    /* END MinhTB VERSION 2 08-03-2016 */

    /**
     * @return string
     */
    private function view_assess()
    {
        global $DB, $PAGE, $USER, $OUTPUT;

        $PAGE->requires->js_init_call('M.mod_videoassessment.main_init', array($this->cm->id),
            false, $this->jsmodule);
        $PAGE->requires->js_init_call('M.mod_videoassessment.assess_init', null, true, $this->jsmodule);
        /* MinhTB VERSION 2 */
        $PAGE->requires->js('/mod/videoassessment/assess.js');
        /* END */
        $o = '';

        $user = $DB->get_record('user', array('id' => optional_param('userid', 0, PARAM_INT)));

        $gradertype = optional_param('gradertype', '', PARAM_ALPHA);

        if ($gradertype == 'training' && $USER->id != $user->id && !$this->is_teacher()) {
            $this->view_redirect();
        }

        if ($gradertype != 'class' && $gradertype != 'training') {
            $gradertype = $this->get_grader_type($user->id);
        }

        $passtraining = $DB->get_field('videoassessment_aggregation', 'passtraining', array(
            'videoassessment' => $this->va->id,
            'userid' => $user->id
        ));

        $rubrics_passed = array();

        if ($gradertype == 'training' && !$this->is_teacher()) {
            if ($passtraining || !$this->va->training) {
                $this->view_redirect();
            } else {
                $gradingarea = 'beforetraining';
                $rubric = new rubric($this, array($gradingarea));
                $controller = $rubric->get_available_controller($gradingarea);

                $studentid = $USER->id;
                $teacherid = null;

                $teachers = $DB->get_records_sql('
                    SELECT gi.grader
                    FROM {videoassessment_grade_items} gi
                    WHERE gi.type = :type AND videoassessment = :videoassessment AND gi.gradeduser = gi.grader
                    ORDER BY gi.id DESC
                ',
                    array(
                        'type' => $gradingarea,
                        'videoassessment' => $this->va->id,
                    )
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

                $studentfilling = array();
                if (!empty($studentinstance)) {
                    $studentfilling = $studentinstance->get_rubric_filling();
                }

                $teacherfilling = array();
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

                    $result_table = '';
                    $result_table .= \html_writer::start_tag('table', array('id' => 'training-result-table-render'));

                    $result = $this->get_training_result_table($definition, $studentfilling, $teacherfilling);
                    $result_table .= $result[0];
                    $rubrics_passed = $result[2];

                    $result_table .= \html_writer::end_tag('table');
                    $o .= $result_table;
                }

            }
        }

        $mformdata = (object)array(
            'va' => $this,
            'cm' => $this->cm,
            'userid' => optional_param('userid', 0, PARAM_INT),
            'user' => $user,
            'gradingdisabled' => false,
            'gradertype' => $gradertype,
            'rubricspassed' => $rubrics_passed
        );

        $gradingareas = array('before' . $gradertype);
        if ($this->get_associated_video($user->id, 'after')) {
            $gradingareas[] = 'after' . $gradertype;
        }
        $rubric = new rubric($this, $gradingareas);

        foreach ($this->timings as $timing) {
            $gradingarea = $timing . $gradertype;
            $itemid = null;
            $itemid = $this->get_grade_item($gradingarea, $user->id);
            if ($controller = $rubric->get_available_controller($gradingarea)) {

                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                if (!isset($mformdata->advancedgradinginstance)) {
                    $mformdata->advancedgradinginstance = new \stdClass();
                }
                $mformdata->advancedgradinginstance->$timing = $controller->get_or_create_instance(
                    $instanceid, $USER->id, $itemid);
            }

            $mformdata->{'grade' . $timing} = $DB->get_record(self::TABLE_GRADES,
                array(
                    'gradeitem' => $itemid
                ));
        }

        $form = new form\assess('', $mformdata, 'post', '', array(
            'class' => 'gradingform'
        ));

        if ($form->is_cancelled()) {
            $this->view_redirect();
        } else if ($data = $form->get_data($gradertype)) {
            $gradinginstance = $form->use_advanced_grading();
            foreach ($this->timings as $timing) {
                if (!empty($gradinginstance->$timing)) {
                    $gradingarea = $timing . $this->get_grader_type($data->userid, $gradertype);
                    $_POST['xgrade' . $timing] = $gradinginstance->$timing->submit_and_get_grade(
                        $data->{'advancedgrading' . $timing},
                        $this->get_grade_item($gradingarea, $data->userid));
                }
            }
            $gradertype = $this->get_grader_type($data->userid, $gradertype);
            foreach ($this->timings as $timing) {
                $gradingarea = $timing . $gradertype;
                $itemid = $this->get_grade_item($gradingarea, $data->userid);

                if (!($grade = $DB->get_record('videoassessment_grades',
                    array(
                        'gradeitem' => $itemid
                    )))) {
                    $grade = new \stdClass();
                    $grade->videoassessment = $this->instance;
                    $grade->gradeitem = $itemid;
                    $grade->id = $DB->insert_record('videoassessment_grades', $grade);
                }
                if (empty($data->isnotifystudent)) {
                    $grade->isnotifystudent = 0;
                } else {
                    $grade->isnotifystudent = $data->isnotifystudent;
                }

                $grade->grade = $data->{'xgrade' . $timing};
                if (isset($data->{'submissioncomment' . $timing})) {
                    $grade->submissioncomment = $data->{'submissioncomment' . $timing}['text'];
                    $grade->submissioncommentformat = $data->{'submissioncomment' . $timing}['format'];
                }
                $grade->timemarked = time();
                $DB->update_record('videoassessment_grades', $grade);
            }


                $this->aggregate_grades($user->id);


            //adtis
            $ismailsent = 0;
            $videoassessment = $DB->get_record('videoassessment', array('id' => $this->va->id));
            if ($videoassessment->teachercommentnotification == 1 && $grade->isnotifystudent == 1) {
                if (!($this->is_graded_by_current_user($user->id, $timing . $gradertype) && $videoassessment->isfirstassessmentbyteacher == 1 && 'teacher' == $gradertype) &&
                    !($this->is_graded_by_current_user($user->id, $timing . $gradertype) && $videoassessment->isfirstassessmentbystudent == 1 && 'peer' == $gradertype)
                    || ($this->is_graded_by_current_user($user->id, $timing . $gradertype) && $videoassessment->isadditionalassessment == 1 && 'teacher' == $gradertype)) {

                    if ('teacher' == $gradertype) {
                        $mailTemplate = $videoassessment->teachernotificationtemplate;
                    } else {
                        $mailTemplate = $videoassessment->peertnotificationtemplate;
                    }

                    $url = new \moodle_url($this->viewurl,
                        array('action' => 'report', 'userid' => $user->id));
                    $templateArray = array("[[student name]]" => $user->firstname . ' ' . $user->lastname,
                        "[[insert assignment name]]" => $videoassessment->name,
                        "[[insert current date]]" => date("Y-m-d H:i:s"),
                        "[[insert link to student page to view assessment]]" => $url->out(false),
                        "[[teacher email address]]" => $USER->email,
                        "[[teacher name]]" => $USER->firstname . ' ' . $USER->lastname);

                    foreach ($templateArray as $item => $template) {
                        $mailTemplate = str_replace($item, $template, $mailTemplate);
                    }
                    $quickmailresult = false;
                    $registeredemailresult = false;
                    if ($videoassessment->isregisteredemail == 1) {
                        $registeredemailresult = email_to_user($user, $USER, "", $mailTemplate);
                    }
                    if ($videoassessment->ismobilequickmail == 1) {
                        $quickmail = $DB->get_record('block_quickmailjpn_users', array('userid' => $user->id));
                        if (!empty($quickmail)) {
                            $mobileuser = $user;
                            $mobileuser->email = $quickmail->mobileemail;
                            $quickmailresult = email_to_user($mobileuser, $USER, "", $mailTemplate);
                        }
                    }
                    if ($registeredemailresult || $quickmailresult) {
                        $ismailsent = 1;
                    }
                }
            }

            /* MinhTB VERSION 2 08-03-2016 */
            if ($gradertype == 'training' && !$this->is_teacher()) {
                $this->view_redirect('trainingresult', array('userid' => $user->id));
            } else {
                $this->view_redirect("", array('ismailsent' => $ismailsent));
            }
            /* END MinhTB VERSION 2 08-03-2016 */
        }

        $o .= \html_writer::start_tag('div', array('class' => 'clearfix'));
        if ($gradertype != 'class') {
            $o .= \html_writer::start_tag('div', array('class' => 'assess-form-videos'));
            $mobile = va::uses_mobile_upload();

            if ($gradertype == 'training') {
                $data = $DB->get_record('videoassessment_videos', array('id' => $this->va->trainingvideoid));
//                 if($mobile){
//                 	$content = '<img class="assessimg" style="width:100%"  src='.$data->thumbnailname.' />';
//                 	$o .= \html_writer::tag(
//                 			'a', $content, array(
//                 					'onclick' => 'M.mod_videoassessment.videos_show_video_preview('.$data->id.')',
//                 					'href' => 'javascript:void(0)'
//                 			)
//                 			);
//                 	$o .= \html_writer::tag('div', '', array('id' => 'videopreview'));
//                 }else{
                if (!empty($data)) {
                    if ($video = new video($this->context, $data)) {
                        $o .= \html_writer::start_tag('div', array('class' => 'video-wrap'));
                        $o .= $this->output->render($video);
                        $o .= \html_writer::end_tag('div');
                    }
                }
//                 }
            } else {
                foreach ($this->timings as $timing) {
                    if ($video = $this->get_associated_video($user->id, $timing)) {
//                     	if($mobile){
//                     		$content = '<img class="assessimg" style="width:100%" src='.$video->data->thumbnailname.' />';
//                     		$o .= \html_writer::tag(
//                     				'a', $content, array(
//                     						'onclick' => 'M.mod_videoassessment.videos_show_video_preview('.$video->data->id.')',
//                     						'href' => 'javascript:void(0)'
//                     				)
//                     				);
//                     		$o .= \html_writer::tag('div', '', array('id' => 'videopreview'));
//                     	}else{
                        $o .= \html_writer::start_tag('div', array('class' => 'video-wrap'));
                        $o .= $this->output->render($video);
                        $o .= \html_writer::end_tag('div');
//                     	}
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
     * @author MinhTB VERSION 2
     *
     */
    private function view_result()
    {
        global $DB, $USER, $PAGE, $CFG;

        $gradingarea = 'beforetraining';
        $user = $DB->get_record('user', array('id' => optional_param('userid', 0, PARAM_INT)));

        if ($this->is_teacher()) {
            $studentid = $user->id;
            $teacherid = $USER->id;
        } else {
            $studentid = $USER->id;
            $teacherid = null;

            $teachers = $DB->get_records_sql('
                    SELECT gi.grader
                    FROM {videoassessment_grade_items} gi
                    WHERE gi.type = :type AND videoassessment = :videoassessment AND gi.gradeduser = gi.grader
                    ORDER BY gi.id DESC
                ',
                array(
                    'type' => $gradingarea,
                    'videoassessment' => $this->va->id,
                )
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

        $rubric = new rubric($this, array($gradingarea));
        $o = '';

        if (!empty($rubric)) {
            $controller = $rubric->get_available_controller($gradingarea);

            $o .= \html_writer::start_tag('div', array('class' => 'clearfix'));
            $o .= \html_writer::start_tag('div', array('class' => 'assess-form-videos'));

            $data = $DB->get_record('videoassessment_videos', array('id' => $this->va->trainingvideoid));
            if (!empty($data)) {
                if ($video = new video($this->context, $data)) {
                    $o .= \html_writer::start_tag('div', array('class' => 'video-wrap'));
                    $o .= $this->output->render($video);
                    $o .= \html_writer::end_tag('div');
                }
            }

            $o .= \html_writer::end_tag('div');
            $o .= \html_writer::start_tag('div', array('id' => 'training-result-wrap'));

            $o .= \html_writer::start_tag('h2');
            $o .= self::str('results');
            $o .= \html_writer::end_tag('h2');

            if (!empty($controller)) {
                $itemid = null;
                $itemid = $this->get_grade_item($gradingarea, $user->id, $studentid);
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);

                $studentinstance = $controller->get_or_create_instance($instanceid, $studentid, $itemid)->get_current_instance();
                $archiveinstances = $this->get_archive_instances($controller, $itemid);
                $historyfillings = array();

                if (!empty($archiveinstances)) {
                    foreach ($archiveinstances as $instance) {
                        $fillings = $instance->get_rubric_filling();

                        foreach ($fillings['criteria'] as $rid => $filling) {
                            if (!isset($historyfillings[$rid])) {
                                $historyfillings[$rid] = array();
                            }

                            if (!in_array($filling['levelid'], $historyfillings[$rid])) {
                                $historyfillings[$rid][] = $filling['levelid'];
                            }
                        }
                    }
                }

                $studentfilling = array();
                if (!empty($studentinstance)) {
                    $studentfilling = $studentinstance->get_rubric_filling();
                }

                $teacherfilling = array();
                if ($teacherid) {
                    $itemid = $this->get_grade_item($gradingarea, $teacherid, $teacherid);
                    $teacherinstance = $controller->get_or_create_instance($instanceid, $teacherid, $itemid);
                    $teachercurrentinstance = $teacherinstance->get_current_instance();

                    if (!empty($teachercurrentinstance)) {
                        $teacherfilling = $teacherinstance->get_rubric_filling();
                    }
                }

                $definition = $controller->get_definition();

                $o .= \html_writer::start_tag('div', array('id' => 'training-desc'));
                $o .= \html_writer::start_tag('h5');
                $o .= str_replace('xx', $this->va->accepteddifference, $this->va->trainingdesc);
                $o .= \html_writer::end_tag('h5');
                $o .= \html_writer::end_tag('div');

                $o .= \html_writer::start_tag('table', array('id' => 'training-result-table'));

                $result = $this->get_training_result_table($definition, $studentfilling, $teacherfilling, $historyfillings);
                $o .= $result[0];
                $passed = $result[1];

                $o .= \html_writer::end_tag('table');
            }
        }

        $agg = $DB->get_record('videoassessment_aggregation', array(
            'videoassessment' => $this->va->id,
            'userid' => $user->id
        ));

        $o .= \html_writer::start_tag('div', array('class' => 'result-notice'));

        if (!$agg->passtraining && $passed && !empty($teacherfilling) && !empty($studentfilling)) {
            $agg->passtraining = 1;

            $DB->update_record('videoassessment_aggregation', $agg);
        }

        if (!$this->is_teacher()) {
            if ($agg->passtraining) {
                $o .= get_string('passednotice', 'videoassessment', '<a class="button-notice" href="' . new \moodle_url('/mod/videoassessment/view.php', array('id' => $this->cm->id)) . '">' . self::str('selfpeer') . '</a>');
            } else {
                $a = new \stdClass();
                $a->accepteddifference = $this->va->accepteddifference;
                $a->button = '<a class="button-notice" href="' . new \moodle_url('/mod/videoassessment/view.php', array('id' => $this->cm->id, 'action' => 'assess', 'userid' => $user->id, 'gradertype' => 'training')) . '">' . self::str('tryagain') . '</a>';

                $o .= get_string('failednotice', 'videoassessment', $a);
            }
        }

        $o .= \html_writer::end_tag('div');
        $o .= \html_writer::end_tag('div');
        $o .= \html_writer::end_tag('div');

        return $o;

    }

    /**
     *
     * @return string
     */
    private function view_report()
    {
        global $PAGE, $OUTPUT, $DB;

        $PAGE->requires->js_init_call('M.mod_videoassessment.report_combine_rubrics', null, false, $this->jsmodule);

        $o = '';

        $userid = optional_param('userid', 0, PARAM_INT);

        $rubric = new rubric($this);

        $gradingstatus = $this->get_grading_status($userid);
        $usergrades = $this->get_aggregated_grades($userid);
        $hideteacher = (object)array(
            'before' => $usergrades->gradebeforeself == -1 && $this->va->delayedteachergrade && !$this->is_teacher(),
            'after' => $usergrades->gradeafterself == -1 && $this->va->delayedteachergrade && !$this->is_teacher(),
        );

        $o .= \html_writer::start_tag('div', array('class' => 'report-rubrics'));
        foreach ($this->timings as $timing) {
            if (!$gradingstatus->$timing) {
                continue;
            }

            $o .= $OUTPUT->heading($this->str('allscores'));
            $timinggrades = array();
            $rubricTextClass = 0; //Le Xuan Anh Ver2
            $nameRubricTextClass = ''; //Le Xuan Anh Ver2
            foreach ($this->gradertypes as $gradertype) {
                if ($this->va->class && $gradertype == 'class' && !has_capability('mod/videoassessment:grade', $this->context)) {
                    continue;
                }

                $gradingarea = $timing . $gradertype;
                $o .= $OUTPUT->heading(
                    self::str($timing) . ' - ' . self::str($gradertype),
                    2, 'main', 'heading-' . $gradingarea);
                $gradinginfo = grade_get_grades($this->course->id, 'mod', 'videoassessment',
                    $this->instance, $userid);
                $o .= \html_writer::start_tag('div', array('id' => 'rubrics-' . $gradingarea));
                if ($controller = $rubric->get_available_controller($gradingarea)) {
                    $gradeitems = $this->get_grade_items($gradingarea, $userid);
                    foreach ($gradeitems as $gradeitem) {
                        $tmp = $controller->render_grade($PAGE, $gradeitem->id, $gradinginfo, '', false);
                        if ($gradertype == 'teacher' && $hideteacher->$timing) {
                            // hide teacher grade and comment
                            $tmp = preg_replace('@class="(level[^"]+?)\s*checked"@', 'class="$1"', $tmp);
                            $tmp = preg_replace('@<td class="remark">(.*?)</td>@us', '<td class="remark"></td>', $tmp);
                        }
                        $o .= $tmp;

                        $timinggrades[] = \html_writer::tag('span', (int)$gradeitem->grade, array('class' => 'rubrictext-' . $gradertype));
                    }
                }
                $o .= \html_writer::end_tag('div');
            }


            //adtis
            $o .= $OUTPUT->heading("General Comments");
            $o .= \html_writer::start_tag('div', array('class' => 'card  card-body'));
            foreach ($this->gradertypes as $gradertype) {
                if ($gradertype == 'training' || $gradertype == 'class' || ($this->va->class && $gradertype == 'class' && !has_capability('mod/videoassessment:grade', $this->context))) {
                    continue;
                }
                $gradingarea = $timing . $gradertype;
                $grades = $this->get_grade_items($gradingarea, $userid);
                foreach ($grades as $item => $gradeitem) {
                    if (empty($gradeitem->submissioncomment)) {
                        break;
                    }
                    $comment = '<label class="submissioncomment">' . $gradeitem->submissioncomment . '</label>';
                    if ($this->uses_mobile_upload()) {
                        $commentbutton = '';
                        if (strlen($gradeitem->submissioncomment) > 30) {
                            $gradeitem->submissioncomment = substr($gradeitem->submissioncomment, 0, 10);
                            $commentbutton = "<button type='button' class='commentbutton btn btn-secondary' id = '" . $gradeitem->id . "' cmid = '" . $this->va->id . "' userid = '" . $userid . "' timing = '" . $timing . "'><h2>...</h2></button>";
                        }
                        $comment = '<label class="mobile-submissioncomment">' . $gradeitem->submissioncomment . '</label>';
                        $comment = $comment . $commentbutton;
                    }

                    if ($gradertype == "peer") {
                        $lable = '<span class="blue box">Peer</span>';
                    } elseif ($gradertype == "teacher") {
                        $lable = '<span class="green box">Teacher</span>';
                    } elseif ($gradertype == "self") {
                        $lable = '<span class="red box">Self</span>';
                    }

                    $o .= $OUTPUT->heading($lable . $comment);
                }
            }
            $o .= \html_writer::end_tag('div');


            $gradeduser = $DB->get_record('user', array('id' => $userid));
            $o .= \html_writer::start_tag('div', array('class' => 'comment comment-' . $gradertype))
                . $OUTPUT->user_picture($gradeduser)
                . ' ' . fullname($gradeduser)
                . \html_writer::end_tag('div');

            if ($timinggrades || $rubricTextClass > 0) {
                $totalScore = ' =' . \html_writer::start_tag('div', array('class' => 'comment-grade')) . '<span class="comment-score-text">Total    Score</span><span class="comment-score">' . (int)$usergrades->{'grade' . $timing} . '</span>' . \html_writer::end_tag('div');
                $selffairnessBonus = '<span  class="fairness">+</span> ' . \html_writer::start_tag('div', array('class' => 'comment-grade fairness')) . '<span class="comment-score-text" >+Self Fairness Bonus</span><span class="comment-score">' . (int)$usergrades->selffairnessbonus . '</span>' . \html_writer::end_tag('div');
                $fairnessBonus = '<span  class="fairness">+</span> ' . \html_writer::start_tag('div', array('class' => 'comment-grade fairness')) . '<span class="comment-score-text" >+Peer Fairness Bonus</span><span class="comment-score">' . (int)$usergrades->fairnessbonus . '</span>' . \html_writer::end_tag('div');
                $finalscore = ' = ' . \html_writer::start_tag('div', array('class' => 'comment-grade')) . '<span class="comment-score-text">Final    Score</span><span class="comment-score">' . (int)$usergrades->finalscore . '</span>' . \html_writer::end_tag('div');
                $o .= $OUTPUT->container(get_string('grade') . ': ' . implode(', ', $timinggrades) . $totalScore . $selffairnessBonus . $fairnessBonus . $finalscore, 'finalgrade');
            }
        }
        $o .= \html_writer::end_tag('div');
        $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'mobileshowallcomment', array());
        return $o;
    }

    /**
     *
     * @return string
     * @global \core_renderer $OUTPUT
     * @global \moodle_page $PAGE
     * @global \moodle_database $DB
     * @global \stdClass $USER
     * @global \stdClass $CFG
     */
    private function view_publish()
    {
        global $CFG, $OUTPUT, $PAGE, $DB, $USER;
        require_once $CFG->dirroot . '/mod/resource/lib.php';

        /* MinhTB VERSION 2 03-03-2016 */
        $PAGE->requires->js('/mod/videoassessment/publish.js');
        /* END */

        if ($CFG->version < self::MOODLE_VERSION_23) {
            require_once $CFG->dirroot . '/mod/resource/locallib.php'; // resource_set_mainfile
        }

        $this->teacher_only();

        $PAGE->requires->js_init_call('M.mod_videoassessment.init_video_links', array($this->cm->id), false, $this->jsmodule);
        $PAGE->requires->js_init_call('M.mod_videoassessment.init_publish_videos');

        $o = '';

        $o .= $OUTPUT->heading(self::str('publishvideostocourse'));

        /* MinhTB VERSION 2 07-03-2016 */
        $videos = optional_param_array('videos', array(), PARAM_BOOL);

        $form = new form\video_publish(null, (object)array('va' => $this, 'videos' => $videos));
        /* END MinhTB VERSION 2 07-03-2016 */

        if ($form->is_cancelled()) {
            $this->view_redirect();
        }

        if ($data = $form->get_data() and $form->is_validated()) {
            if ($data->course) {
                $course = $DB->get_record('course', array('id' => $data->course));

            } else {
                require_capability('moodle/course:create', \context_coursecat::instance($data->category));

                $course = (object)array(
                    'category' => $data->category,
                    'fullname' => trim($data->fullname),
                    'shortname' => trim($data->shortname)
                );
                $course = create_course($course);

                $context = \context_course::instance($course->id, MUST_EXIST);
                if (!empty($CFG->creatornewroleid) and !is_viewing($context, NULL, 'moodle/role:assign') and !is_enrolled($context, NULL, 'moodle/role:assign')) {
                    \enrol_try_internal_enrol($course->id, $USER->id, $CFG->creatornewroleid);
                }
            }

            require_capability('moodle/course:manageactivities', \context_course::instance($course->id));

            $moduleid = $DB->get_field('modules', 'id', array('name' => 'resource'));

            $fs = get_file_storage();

            $videos = required_param_array('videos', PARAM_BOOL);

            foreach ($videos as $videoid => $value) {
                $video = $DB->get_record('videoassessment_videos', array('id' => $videoid));
                $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $video->filepath, $video->filename);

                if (empty($file)) {
                    continue;
                }

                $assocs = $this->get_video_associations($videoid);
                $assocnames = array();
                foreach ($assocs as $assoc) {
                    $user = $DB->get_record('user', array('id' => $assoc->associationid),
                        'id, lastname, firstname');
                    $assocnames[] = fullname($user);
                }
                $modulename = implode(', ', $assocnames);

                // コースモジュール追加
                $cm = new \stdClass();
                $cm->course = $course->id;
                $cm->module = $moduleid;

                $cm->id = add_course_module($cm);

                // モジュールオプション追加
                $resource = new \stdClass();
                $resource->course = $course->id;
                $resource->name = trim($data->prefix) . $modulename . trim($data->suffix);
                $resource->display = 1;
                $resource->timemodified = time();
                $resource->coursemodule = $cm->id;
                $resource->files = null;


                $resource->id = resource_add_instance($resource, null);

                $DB->set_field('course_modules', 'instance', $resource->id, array('id' => $cm->id));

                // コースセクションに追加
                if (!isset($data->section)) {
                    $sectionnum = 1;
                } else {
                    $sectionnum = $DB->get_field('course_sections', 'section', array('id' => $data->section));
                }
                course_create_sections_if_missing($course, array($sectionnum));

                $cm->coursemodule = $cm->id;
                $cm->section = $sectionnum;

                $sectionid = course_add_cm_to_section($course, $cm->id, $sectionnum);

                $DB->set_field('course_modules', 'section', $sectionid, array('id' => $cm->id));

                // ファイル追加
                $newfile = array(
                    'contextid' => \context_module::instance($cm->id)->id,
                    'component' => 'mod_resource',
                    'filearea' => 'content'
                );
                $fs->create_file_from_storedfile($newfile, $file);
            }
            rebuild_course_cache($course->id);

            redirect(new \moodle_url('/course/view.php', array('id' => $course->id)));
        }

        ob_start();
        $form->display();
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= \html_writer::tag('div', '', array('id' => 'videopreview'));

        return $o;
    }

    public static function get_grade_items_byid($gradingarea, $gradeduser, $id)
    {
        global $DB;

        return $DB->get_records_sql('
                SELECT gi.id, gi.grader, g.grade, g.submissioncomment, g.timemarked
                    FROM {videoassessment_grade_items} gi
                        LEFT JOIN {videoassessment_grades} g ON g.videoassessment = :va2
                            AND g.gradeitem = gi.id
                        JOIN {user} u ON u.id = gi.grader
                    WHERE gi.videoassessment = :va AND gi.type = :type
                        AND gi.gradeduser = :gradeduser
                ',
            array(
                'va' => $id,
                'va2' => $id,
                'type' => $gradingarea,
                'gradeduser' => $gradeduser
            )
        );
    }

    /**
     *
     * @param string $gradingarea
     * @param int $gradeduser
     * @return array
     */
    public function get_grade_items($gradingarea, $gradeduser)
    {
        global $DB;

        return $DB->get_records_sql('
                SELECT gi.id, gi.grader, g.grade, g.submissioncomment, g.timemarked
                    FROM {videoassessment_grade_items} gi
                        LEFT JOIN {videoassessment_grades} g ON g.videoassessment = :va2
                            AND g.gradeitem = gi.id
                        JOIN {user} u ON u.id = gi.grader
                    WHERE gi.videoassessment = :va AND gi.type = :type
                        AND gi.gradeduser = :gradeduser
                ',
            array(
                'va' => $this->instance,
                'va2' => $this->instance,
                'type' => $gradingarea,
                'gradeduser' => $gradeduser
            )
        );
    }


    /**
     *
     * @param string $gradingarea
     * @param int $gradeduser
     * @param int $grader
     * @return int
     */
    public function get_grade_item($gradingarea, $gradeduser, $grader = null)
    {
        global $DB, $USER;

        if (!$grader) {
            $grader = $USER->id;
        }

        if ($gradeitem = $DB->get_record('videoassessment_grade_items', array(
            'videoassessment' => $this->instance,
            'type' => $gradingarea,
            'gradeduser' => $gradeduser,
            'grader' => $grader
        ))) {
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
     *
     * @param int $userid
     * @return \stdClass
     */
    public function get_aggregated_grades($userid)
    {
        global $DB;

        if ($grades = $DB->get_record('videoassessment_aggregation',
            array('videoassessment' => $this->instance, 'userid' => $userid))) {
            return $grades;
        }

        $grades = (object)array(
            'videoassessment' => $this->instance,
            'userid' => $userid,
            'timemodified' => time(),
            'gradebefore' => -1,
            'gradeafter' => -1,
            'gradebeforeteacher' => -1,
            'gradebeforeself' => -1,
            'gradebeforepeer' => -1,
            'gradeafterteacher' => -1,
            'gradeafterself' => -1,
            'gradeafterpeer' => -1,
            'selffairnessbonus'=>0,
            'fairnessbonus' => 0,
            'finalscore' => 0,
        );
        $grades->id = $DB->insert_record('videoassessment_aggregation', $grades);
        return $grades;
    }

    /**
     *
     * @param int $userid
     * @return boolean
     */
    public function is_user_graded($userid)
    {
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
     *
     * @param int $userid
     * @return \stdClass
     */
    public function get_grading_status($userid)
    {
        $agg = $this->get_aggregated_grades($userid);
        $status = (object)array(
            'any' => false,
            'before' => false,
            'after' => false
        );
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
     *
     * @param int $userid
     */
    public function aggregate_grades($userid)
    {
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
                $params = array('gradeduser' => $userid, 'type' => $gradingarea,
                    'va' => $this->instance);
//                 if ($gradingtype == 'peer') {
//                     $sql .= ' AND usedbypeermarking = 1';
//                 }
                if ($grade = $DB->get_field_sql($sql, $params)) {
                    $agg->{'grade' . $gradingarea} = $grade;
                } else {
                    $agg->{'grade' . $gradingarea} = -1;
                }
                /* MinhTB VERSION 2 02-03-2016 */
//                $this->update_grade_item(array(
//                        'userid' => $userid,
//                        'rawgrade' => $agg->{'grade'.$gradingarea}
//                        ), $gradingarea);
                /* END MinhTB VERSIOn 2 02-03-2016 */
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

        // PostgreSQL はINTへの暗黙キャストが行われないので明示的にキャスト
        foreach ($this->timings as $timing) {
            foreach ($this->gradertypes as $gradingtype) {
                $agg->{'grade' . $timing . $gradingtype} = (int)round($agg->{'grade' . $timing . $gradingtype});
            }
            $agg->{'grade' . $timing} = (int)round($agg->{'grade' . $timing});
        }

        /* MinhTB VERSION 2 02-03-2016 */
        if (!empty($agg->gradebefore)) {
            $rawgrade = $agg->gradebefore;
        } else {
            $rawgrade = 0;
        }


        //adtis
        $va = $DB->get_record("videoassessment", array("id" => $this->instance));
        if ($va->fairnessbonus == 1 && ($_POST['gradertype'] == 'peer' || $_POST['gradertype'] == 'teacher')) {
            if ($gradeteacher > $gradepeer) {
                $gradediff = $gradeteacher - $gradepeer;
                $bonusscale = ($gradediff / $gradeteacher) * 100;
            } else {
                $gradediff = $gradepeer - $gradeteacher;
                $bonusscale = ($gradediff / $gradeteacher) * 100;
            }


            $bonusscalearray = array();
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
            $params = array($this->instance, $userid);
            $result = $DB->get_record_sql($sql, $params);
            if (empty($result)) {
                $agg->fairnessbonus = 0;
            } else {
                $agg->fairnessbonus = (($bonuspercent / 100) * ((int)$va->bonuspercentage / 100) * $total);
            }

            $agg->finalscore = ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore) > 100 ? 100 : ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore);
        }

        if ($va->selffairnessbonus == 1 && ($_POST['gradertype'] == 'self' || $_POST['gradertype'] == 'teacher')) {
            if ($gradeteacher > $gradeself) {
                $gradediff = $gradeteacher - $gradeself;
                $selfbonusscale = ($gradediff / $gradeteacher) * 100;
            } else {
                $gradediff = $gradeself - $gradeteacher;
                $selfbonusscale = ($gradediff / $gradeteacher) * 100;
            }


            $selfbonusscalearray = array();
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
            $agg->selffairnessbonus = (($selfbonuspercent / 100) * ((int)$va->bonuspercentage / 100) * $total);
            $agg->finalscore = ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore) > 100 ? 100 : ($agg->selffairnessbonus + $agg->fairnessbonus + $agg->gradebefore);
        }

        if ($rawgrade > 0) {
            $this->update_grade_item(
                array(
                    'userid' => $userid,
                    'rawgrade' => $rawgrade
                ));
            /* END MinhTB VERSION 2 02-03-2016 */

            // Update completion state
            $completion = new \completion_info($this->course);
            if ($completion->is_enabled($this->cm) && $this->cm->completion == COMPLETION_TRACKING_AUTOMATIC
                && ($rawgrade >= $va->gradepass_videoassessment)) {
                $completion->update_state($this->cm, COMPLETION_COMPLETE);
            }
        }


        $agg->timemodified = time();
        $DB->update_record('videoassessment_aggregation', $agg);
    }

    public function regrade()
    {
        $users = $this->get_students();
        foreach ($users as $user) {
            $this->aggregate_grades($user->id);
        }
    }

    /**
     *
     * @param int $gradeduser
     * @param string $gradingarea
     * @return boolean
     */
    /* MinhTB VERSION 2 09-03-2016 */
    public function is_graded_by_current_user($gradeduser, $gradingarea, $grader = null)
    {
        global $DB, $USER;

        if (!$grader) {
            $grader = $USER->id;
        }

        return $DB->record_exists_sql('
                SELECT gi.id
                FROM {videoassessment_grade_items} gi
                    JOIN {videoassessment_grades} g ON gi.id = g.gradeitem
                WHERE gi.videoassessment = :va
                    AND gi.gradeduser = :gradeduser
                    AND gi.grader = :grader
                    AND gi.type = :gradingarea
                    AND g.grade >= 0
                ',
            array(
                'va' => $this->instance,
                'gradeduser' => $gradeduser,
                'grader' => $grader,
                'gradingarea' => $gradingarea
            )
        );
    }
    /* END MinhTB VERSION 2 09-03-2016 */

    /**
     *
     * @param int $userid
     * @return array
     */
    public function get_peers($userid)
    {
        global $DB;

        $peers = $DB->get_records('videoassessment_peers',
            array(
                'videoassessment' => $this->instance,
                'peerid' => $userid
            ));
        $peerids = array();
        foreach ($peers as $peer) {
            $peerids[] = $peer->userid;
        }

        return $peerids;
    }

    /**
     * Get random peers for each user
     *
     * @param array $userids
     * @param int $numpeers
     * @return array ($userid => array($peerid, $peerid, ...), ...)
     */
    public function get_random_peers_for_users(array $userids, $numpeers)
    {
        $MAX_RETRY = 3; // 「詰み」状態回避のための最大リトライ回数

        assert('is_numeric($numpeers)');
        assert('count($userids) > $numpeers');

        $inner = function ($userids, $numpeers) {
            $peers = array_combine(
                $userids, array_fill(0, count($userids), array())
            );

            for ($p = 0; $p < $numpeers; $p++) {
                $slots = array_values($userids);
                $pieces = array_values($userids);

                foreach ($userids as $userid) {

                    $slotavailpieces = array_map(function ($slot) use (&$pieces, &$peers) {
                        return (object)array(
                            'slot' => $slot,
                            'pieces' => array_values(array_filter($pieces, function ($piece) use ($slot, &$peers) {
                                return $piece != $slot && !in_array($piece, $peers[$slot]);
                            }))
                        );
                    }, $slots);
                    uasort($slotavailpieces, function ($a, $b) {
                        return count($a->pieces) - count($b->pieces);
                    });

                    $pieceavailslots = array_map(function ($piece) use (&$slots, &$peers) {
                        return (object)array(
                            'piece' => $piece,
                            'slots' => array_values(array_filter($slots, function ($slot) use ($piece, &$peers) {
                                return $slot != $piece && !in_array($piece, $peers[$slot]);
                            }))
                        );
                    }, $pieces);
                    uasort($pieceavailslots, function ($a, $b) {
                        return count($a->slots) - count($b->slots);
                    });

                    $minslotpieces = reset($slotavailpieces);
                    $minpieceslots = reset($pieceavailslots);
                    if (empty($minslotpieces->pieces) || empty($minpieceslots->slots))
                        throw new Exception();

                    if (count($minslotpieces->pieces) < count($minpieceslots->slots)) {
                        $slot = $minslotpieces->slot;
                        $piece = $minslotpieces->pieces[mt_rand(0, count($minslotpieces->pieces) - 1)];
                    } else {
                        $slot = $minpieceslots->slots[mt_rand(0, count($minpieceslots->slots) - 1)];
                        $piece = $minpieceslots->piece;
                    }

                    assert('in_array($slot, $slots)');
                    assert('in_array($piece, $pieces)');
                    $slots = array_diff($slots, array($slot));
                    $pieces = array_diff($pieces, array($piece));

                    $peers[$slot][] = $piece;
                }
            }

            return $peers;
        };

        // 全ユーザ数と選択ピア数が近い場合、稀に「詰み」の状態になるので、
        // 最大 $MAX_RETRY 回までリトライする
        for ($i = 0; $i < $MAX_RETRY; $i++) try {
            return $inner($userids, $numpeers);
        } catch (Exception $ex) {
            continue;
        }

        // $MAX_RETRY 回を超えて失敗・・・
        throw new RuntimeException(
            sprintf('Failed to select random %d peers for %d users', $numpeers, count($userids))
        );
    }

    /**
     *
     * @return boolean
     */
    public static function check_mp4_support()
    {
        // Flash に対応している場合は FlowPlayer の方が
        // 操作性やレスポンスが良いので、あえてHTML5は使用しない
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
     *
     * @return string
     */
    public function preview_video()
    {
        global $DB, $PAGE, $OUTPUT;

        $width = optional_param('width', 400, PARAM_INT);
        $height = optional_param('height', 300, PARAM_INT);

        $PAGE->set_pagelayout('embedded');

        $o = $OUTPUT->header();

        if ($videoid = optional_param('videoid', 0, PARAM_INT)) {
            $videorec = $DB->get_record('videoassessment_videos', array('id' => $videoid));
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
            $o .= \html_writer::tag('p', 'Video not found.', array('style' => 'color:#fff'));
        }
        $o .= $OUTPUT->footer();

        return $o;
    }

    /**
     *
     * @param array $grades
     * @param string $gradingarea
     * @return int
     */
    public function update_grade_item($grades = null, $gradingarea = null)
    {
        global $CFG;
        require_once $CFG->libdir . '/gradelib.php';

        $itemname = $this->va->name;
        $itemnumber = 0;
        if ($gradingarea) {
            $itemname .= ' (' . self::str($gradingarea) . ')';
            $itemnumber = $this->get_grade_item_number($gradingarea);
        }

        $params = array(
            'itemname' => $itemname,
            'idnumber' => $this->cm->id
        );

        return grade_update('mod/videoassessment', $this->course->id, 'mod', 'videoassessment',
            $this->instance, $itemnumber, $grades, $params);
    }

    /**
     *
     * @param string $gradingarea
     * @return int
     */
    private function get_grade_item_number($gradingarea)
    {
        switch ($gradingarea) {
            // 0: 総合？
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

    private function download_xls_report()
    {
        global $CFG, $DB;

        /* MinhTB VERSION 2 02-03-2016 */
        $groupid = groups_get_activity_group($this->cm, true);
        $currentgroup = groups_get_group($groupid, 'name');

        $table = new table_export();
        $table->filename = $this->cm->name . '.xls';
        $fullnamestr = util::get_fullname_label();
        $table->set(0, 0, va::str('title') . ' ' . $this->cm->name);
        $table->set(1, 0, get_string('idnumber'));
        $table->set(1, 1, $fullnamestr);
//         $table->set(0, 2, va::str('beforeafter'));
        $table->set(1, 2, va::str('groupname'));
        $table->set(1, 3, va::str('teacherselfpeer'));
        $table->set(1, 4, va::str('assessedby') . ' (' . get_string('idnumber') . ')');
        $table->set(1, 5, va::str('assessedby') . ' (' . $fullnamestr . ')');
        $table->set(1, 6, va::str('total'));
        $fixedcolumns = 7;

        $rubric = new rubric($this);
        $headercriteria = array();
        foreach ($this->gradingareas as $gradingarea) {
            $controller = $rubric->get_controller($gradingarea);
            $definition = $controller->get_definition();
            if (isset($definition->rubric_criteria)) {
                foreach ($definition->rubric_criteria as $criterion) {
                    if (!in_array($criterion['description'], $headercriteria)) {
                        $headercriteria[] = $criterion['description'];
                    }
                }
            }
        }
        $headercriteria = array_flip($headercriteria);

        foreach ($headercriteria as $criterion => $index) {
            $table->set(1, $index + $fixedcolumns, $criterion);
        }

        $users = $this->get_students('u.id, u.lastname, u.firstname, u.idnumber', $groupid);
        $timingstrs = array(
            'before' => $this->timing_str('before'),
            'after' => $this->timing_str('after')
        );
        $gradertypestrs = array(
            'teacher' => self::str('teacher'),
            'self' => self::str('self'),
            'peer' => self::str('peer'),
            'class' => self::str('class'),
        );
        $row = 2;
        /* END MinhTB VERSION 2 02-03-2016 */
        foreach ($users as $user) {
            $fullname = fullname($user);

            if (!empty($currentgroup)) {
                $groupname = $currentgroup->name;
            } else {
                $groups = groups_get_all_groups($this->va->course, $user->id);
                $groupname = array();

                if (!empty($groups)) {
                    foreach ($groups as $group) {
                        $groupname[] = $group->name;
                    }
                }

                $groupname = implode(', ', $groupname);
            }

            foreach ($this->gradingareas as $gradingarea) {
                $gradeitems = $this->get_grade_items($gradingarea, $user->id);
                if ($controller = $rubric->get_controller($gradingarea) and $controller->is_form_available()) {
                    foreach ($gradeitems as $gradeitem) {
                        $table->set($row, 0, $user->idnumber);
                        $table->set($row, 1, $fullname);
                        $table->set($row, 2, $groupname);
                        if (preg_match('/^(before|after)(self|peer|teacher|class)$/', $gradingarea, $m)) {
//                             $table->set($row, 2, $timingstrs[$m[1]]);
                            $table->set($row, 3, $gradertypestrs[$m[2]]);

                            if (empty($grader) || $grader->id != $gradeitem->grader) {
                                $grader = $DB->get_record('user', array('id' => $gradeitem->grader),
                                    'id, lastname, firstname, idnumber');
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

                                $table->set($row,
                                    $headercriteria[$criterion['description']] + $fixedcolumns,
                                    $level['score']);
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
     * @param string $userfields
     * @param int $groupid
     * @return array
     */
    public function get_students($userfields = null, $groupid = null)
    {
        if (!$userfields) {
            if (function_exists('get_all_user_name_fields')) {
                $userfields = 'u.id, ' . get_all_user_name_fields(true, 'u');
            } else {
                $userfields = 'u.id, u.firstname, u.lastname';
            }
        }

        if ($groupid === null) {
            $groupid = groups_get_activity_group($this->cm, true);
        }

        return get_enrolled_users($this->context, 'mod/videoassessment:submit', $groupid, $userfields,
            'u.lastname, u.firstname');
    }

    /**
     *
     * @param string $action
     * @param array $params
     * @return \moodle_url
     */
    public function get_view_url($action = '', array $params = array())
    {
        $params['action'] = $action;

        return new \moodle_url($this->viewurl, $params);
    }

    /**
     *
     * @param string $timing
     * @param string $langstring
     * @return string
     */
    public function timing_str($timing, $langstring = null)
    {
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
     *
     * @return boolean
     */
    /* MinhTB VERSION 2 22-03-2016 */
    public function is_teacher($userid = null)
    {
        return has_capability('mod/videoassessment:grade', $this->context, $userid);
    }

    /* END MinhTB VERSION 2 22-03-2016 */

    public function teacher_only()
    {
        require_capability('mod/videoassessment:grade', $this->context);
    }

    /**
     *
     * @param string $identifier
     * @param string|\stdClass $a
     * @return string
     */
    public static function str($identifier, $a = null)
    {
        return get_string($identifier, 'mod_videoassessment', $a);
    }

    /**
     *
     * @param int $vaid
     */
    public static function cleanup_old_peer_grades($vaid)
    {
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
            array(
                'va' => $vaid
            )
        );
        foreach ($gradeitems as $gradeitem) {
            $DB->delete_records('videoassessment_grades', array('gradeitem' => $gradeitem->id));
            $DB->delete_records('videoassessment_grade_items', array('id' => $gradeitem->id));
        }

        $vas = $DB->get_records('videoassessment');
        foreach ($vas as $va) {
            $cm = get_coursemodule_from_instance('videoassessment', $va->id);
            $context = \context_module::instance($cm->id);
            $course = $DB->get_record('course', array('id' => $cm->course));
            $vaobj = new self($context, $cm, $course);
            $vaobj->regrade();
        }
    }

    /**
     *
     * @return boolean
     */
    public static function uses_mobile_upload()
    {
        if (class_exists('core_useragent')) {
            $device = \core_useragent::get_device_type();
        } else {
            $device = get_device_type();
        }

        return $device == 'mobile' || $device == 'tablet';
    }

    /**
     * 指定ユーザが教師ロールとしてアクセスできるコース一覧を取得
     *
     * @param int $userid
     * @return object[]
     * @global object $CFG
     */
    /* MinhTB VERSION 2 03-03-2016 */
    public static function get_courses_managed_by($userid, $catid = null)
    {
        global $CFG;

        $managerroles = explode(',', $CFG->coursecontact);
        $courses = array();
        foreach (\enrol_get_all_users_courses($userid) as $course) {
            if (empty($catid) || $catid == $course->category) {
                $ctx = \context_course::instance($course->id);
                $rusers = \get_role_users($CFG->coursecontact, $ctx, true, 'u.id, u.lastname, u.firstname ');
                if (isset($rusers[$userid]))
                    $courses[$course->id] = $course;
            }
        }
        return $courses;
    }

    /* END MinhTB VERSION 2 03-03-2016 */

    public static function get_users($courseid)
    {
        global $DB;

        $sql = '
                SELECT u.* FROM {user} u
                INNER JOIN {user_enrolments} ue ON u.id = ue.userid
                INNER JOIN {enrol} e ON ue.enrolid = e.id
                INNER JOIN {grade_grades} gg ON u.id = gg.userid
                WHERE e.enrol = :enrol AND e.courseid = :courseid
        ';

        $params = array(
            'enrol' => 'manual',
            'courseid' => $courseid,
        );

        $users = $DB->get_records_sql($sql, $params);
        return $users;
    }

    public static function get_courses()
    {
        global $DB;

        $sql = '
                SELECT c.* FROM {course} c
                INNER JOIN {course_modules} cm ON c.id = cm.course
                INNER JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :name
        ';

        $params = array(
            'name' => 'videoassessment',
        );

        return $DB->get_records_sql($sql, $params);
    }

    public static function get_cm($courseid)
    {
        global $DB;

        $sql = '
                SELECT cm.* FROM {course_modules} cm
                INNER JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :name AND cm.course = :courseid
        ';

        $params = array(
            'name' => 'videoassessment',
            'courseid' => $courseid,
        );

        return $DB->get_record_sql($sql, $params);
    }

    public static function get_grade($courseid, $userid)
    {
        global $DB;

        $sql = '
                SELECT count(gi.id) as count, sum(gg.finalgrade) as total FROM {grade_items} gi
                LEFT JOIN {grade_grades} gg ON gi.id = gg.itemid
                WHERE gi.courseid = :courseid AND gg.userid = :userid AND gi.itemtype = :itemtype
        ';

        $params = array(
            'courseid' => $courseid,
            'userid' => $userid,
            'itemtype' => 'mod',
        );

        return $DB->get_record_sql($sql, $params);
    }

    /* MinhTB VERSION 2 */
    public function get_students_sort($groupid = null, $sort_manually = false, $order = null)
    {
        global $DB;

        if (function_exists('get_all_user_name_fields')) {
            $userfields = 'u.id, ' . get_all_user_name_fields(true, 'u');
        } else {
            $userfields = 'u.id, u.firstname, u.lastname';
        }

        if ($sort_manually) {
            $order = ' ORDER BY sortorder ASC';
        }

        $contextcourse = \context_course::instance($this->course->id);
        $params = array(
            'contextid' => $contextcourse->id,
            'roleid' => 5,
            'courseid' => $this->course->id
        );

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

    public function get_peers_sort($groupid = 0, $userid, $sort_manually = false, $order = null)
    {
        global $DB;

        $contextcourse = \context_course::instance($this->course->id);
        $params = array(
            'videoassessment' => $this->instance,
            'peerid' => $userid,
            'contextid' => $contextcourse->id
        );

        if ($sort_manually) {
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

        $sql = "
            SELECT vp.userid $fields
            FROM {videoassessment_peers} vp
            JOIN {user} u ON vp.userid = u.id
            JOIN {role_assignments} ra ON u.id = ra.userid
            JOIN {user_enrolments} ue ON vp.userid = ue.userid
            JOIN {enrol} e ON ue.enrolid = e.id
            $join
            WHERE vp.videoassessment = :videoassessment AND vp.peerid = :peerid AND ra.contextid = :contextid
        " . $where . $order;

        $students = $DB->get_records_sql($sql, $params);
        $peerids = array();
        foreach ($students as $student) {
            $peerids[] = $student->userid;
        }

        return $peerids;
    }

    /* End */

    public function get_archive_instances($controller, $itemid)
    {
        global $DB;
        $conditions = array('definitionid' => $controller->get_definition()->id,
            'itemid' => $itemid,
            'status' => \gradingform_instance::INSTANCE_STATUS_ARCHIVE);
        $records = $DB->get_recordset('grading_instances', $conditions);
        $rv = array();
        foreach ($records as $record) {
            $rv[] = new \gradingform_rubric_instance($controller, $record);
        }
        return $rv;
    }

    public function get_training_result_table($definition, $studentfilling, $teacherfilling, $historyfillings = array())
    {
        $o = '';
        $passed = true;
        $rubrics_passed = array();
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

                $o .= \html_writer::start_tag('tr', array('class' => 'rubric-result ' . $trclass, 'id' => 'advancedgradingbefore-criteria-' . $rid));

                $o .= \html_writer::start_tag('td', array('class' => 'bold'));
                $o .= $rubric['description'];
                $o .= \html_writer::end_tag('td');

                $scores = array();
                $row = '';
                $icon = '';

                foreach ($rubric['levels'] as $lid => $level) {

                    $selecteds = '';
                    $tdclass = '';
                    $selected = false;

                    if (!empty($studentfilling) && $studentfilling['criteria'][$rid]['levelid'] == $lid) {
                        $selecteds .= \html_writer::start_tag('span', array('class' => 'student-selected score-selected'));
                        $selecteds .= self::str('self');
                        $selecteds .= \html_writer::end_tag('span');
                        $selecteds .= '<br>';

                        $tdclass .= ' student-td';
                        $selected = true;
                    } elseif (!empty($historyfillings) && isset($historyfillings[$rid]) && in_array($lid, $historyfillings[$rid])) {
                        $selecteds .= \html_writer::start_tag('span', array('class' => 'student-selected score-selected'));
                        $selecteds .= self::str('self');
                        $selecteds .= \html_writer::end_tag('span');
                        $selecteds .= '<br>';

                        $tdclass .= ' student-history-td';
                    }

                    if (!empty($teacherfilling) && $teacherfilling['criteria'][$rid]['levelid'] == $lid) {
                        $selecteds .= \html_writer::start_tag('span', array('class' => 'teacher-selected score-selected'));
                        $selecteds .= self::str('teacher');
                        $selecteds .= \html_writer::end_tag('span');
                        $selecteds .= '<br>';

                        $tdclass .= ' teacher-td';
                        $selected = true;
                    }

                    if ($selected) {
                        $tdclass .= ' selected';
                    }

                    $row .= \html_writer::start_tag('td', array('class' => $tdclass));
                    $row .= \html_writer::start_tag('div');
                    $row .= $level['definition'];
                    $row .= \html_writer::end_tag('div');
                    $row .= \html_writer::start_tag('div', array('class' => 'score'));
                    $row .= $level['score'] . ' ' . get_string('points', 'grades');
                    $row .= \html_writer::end_tag('div');
                    $row .= \html_writer::start_tag('div', array('class' => 'score-selected-wrap'));

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
                        $rubrics_passed[] = $rid;
                    }
                }

                $o .= \html_writer::start_tag('td');
                $o .= \html_writer::start_tag('table');
                $o .= \html_writer::start_tag('tr', array('class' => 'criterion-' . $icon));

                $o .= $row;

                $o .= \html_writer::end_tag('tr');
                $o .= \html_writer::end_tag('table');
                $o .= \html_writer::end_tag('td');

                if (!empty($icon)) {
                    $o .= \html_writer::start_tag('td', array('class' => 'status'));
                    $o .= \html_writer::img('images/' . $icon . '.gif', $icon);
                    $o .= \html_writer::end_tag('td');
                }

                $o .= \html_writer::end_tag('tr');
            }
        }

        return array($o, $passed, $rubrics_passed);
    }

    public function get_sort_items($type, $itemid)
    {
        global $DB;
        return $DB->get_record('videoassessment_sort_items', array('type' => $type, 'itemid' => $itemid));
    }
}