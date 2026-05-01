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

namespace mod_videoassessment\form;

use mod_videoassessment\va;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for uploading videos for the videoassessment module.
 *
 * This form provides multiple upload methods including file upload,
 * YouTube URL input, and browser-based video recording capabilities.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_upload extends \moodleform {
    /**
     * Define the form structure and elements for video upload.
     *
     * Creates upload options for file upload, YouTube URL input, and
     * browser recording with mobile device support and progress indicators.
     *
     * @return void
     */
    protected function definition() {
        global $COURSE, $CFG, $PAGE;
        $mform = $this->_form;
        /* @var $va \mod_videoassessment\va */
        $va = $this->_customdata->va;

        // Get video publishing settings.
        // Default to true (1) if field doesn't exist or is null (for backwards compatibility).
        $allowyoutube = !isset($va->va->allowyoutube) || $va->va->allowyoutube;
        $allowvideoupload = !isset($va->va->allowvideoupload) || $va->va->allowvideoupload;
        $allowvideorecord = !isset($va->va->allowvideorecord) || $va->va->allowvideorecord;

        // Default to YouTube (1) as the selected option.
        $defaultuploadtype = 1; // YouTube is always default when available.
        if (!$allowyoutube && $allowvideoupload) {
            $defaultuploadtype = 0; // File upload if YouTube not available.
        } else if (!$allowyoutube && !$allowvideoupload && $allowvideorecord) {
            $defaultuploadtype = 2; // Record if others not available.
        }

        $mobile = va::uses_mobile_upload();
        if ($mobile) {
            $mform->updateAttributes(['enctype' => 'multipart/form-data', "id" => "mobileform"]);
            $mform->addElement('hidden', 'mobile', 1);
            $mform->setType('mobile', PARAM_BOOL);
        } else {
            $mform->updateAttributes(["id" => "mform"]);
        }

        $mform->addElement('hidden', 'id', required_param('id', PARAM_INT));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'instance', optional_param('instance', 0, PARAM_INT));
        $mform->setType('instance', PARAM_INT);
        $mform->setDefault('instance', $va->instance);
        $mform->addElement('hidden', 'action', 'upload');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'user', optional_param('user', 0, PARAM_INT));
        $mform->setType('user', PARAM_INT);
        $mform->addElement('hidden', 'timing', optional_param('timing', '', PARAM_ALPHA));
        $mform->setType('timing', PARAM_ALPHA);
        $mform->addElement('hidden', 'actionmodel', optional_param('actionmodel', 0, PARAM_INT), ['class' => 'actionmodel']);
        $mform->setType('actionmodel', PARAM_INT);
        $mform->addElement('header', 'uploadingvideo', get_string('uploadingvideo', 'videoassessment'));
        $mform->addHelpButton('uploadingvideo', 'uploadingvideo', 'videoassessment');

        // YouTube URL option (first).
        if ($allowyoutube) {
            $mform->addElement('radio', 'upload', get_string('uploadyoutube', 'videoassessment'), '', 1);
            $mform->addHelpButton('upload', 'uploadyoutube', 'videoassessment');

            if ($mobile) {
                $mform->addElement('text', 'mobileurl', '', ['size' => 40]);
                $mform->setType('mobileurl', PARAM_URL);
            } else {
                $mform->addElement('text', 'url', '', ['size' => 40]);
                $mform->setType('url', PARAM_URL);
            }
        }

        // Video file upload option (second).
        if ($allowvideoupload) {
            $mform->addElement('radio', 'upload', get_string('uploadfile', 'videoassessment'), '', 0);
            $mform->addHelpButton('upload', 'uploadfile', 'videoassessment');

            if ($mobile) {
                $mform->addElement(
                    "html",
                    "<div class='mdl-align upload-progress' style='display:none'><i class='icon fa fa-circle-o-notch fa-spin fa-fw' aria-hidden='true'></i><br/><h3>" .
                    get_string('uploadingvideonotice', 'videoassessment') .
                    "</h3></div><br/>",
                );
            }
            $maxbytes = $COURSE->maxbytes;
            if ($CFG->version < va::MOODLE_VERSION_23) {
                $acceptedtypes = ['*'];
            } else {
                $acceptedtypes = ['video', 'audio'];
            }

            if ($mobile) {
                $input = \html_writer::empty_tag(
                    'input',
                    [
                        'type' => 'file',
                        'id' => 'id_mobilevideo',
                        'name' => 'mobilevideo',
                        'accept' => 'video/*',
                    ]
                );
                $mform->addElement('static', 'mobilevideo', "", $input);
            } else {
                $str = va::str('video');
                $mform->addElement(
                    'filemanager',
                    'video',
                    "",
                    null,
                    [
                        'subdirs' => 0,
                        'maxbytes' => $maxbytes,
                        'maxfiles' => 1,
                        'accepted_types' => $acceptedtypes,
                    ]
                );
            }
        }

        // Video recording option (third).
        if ($allowvideorecord) {
            $mform->addElement('radio', 'upload', get_string('recordnewvideo', 'videoassessment'), '', 2);
            $mform->addHelpButton('upload', 'recordnewvideo', 'videoassessment');
            $mform->addElement(
                'html',
                '<div id="recordrtc" class="recordrtc"><div id="record-content-div"></div>
                    <span id="btn-start-recording" class="btn btn-secondary">' . get_string('startrecoding', 'videoassessment') . '</span>
                    <span id="btn-pause-recording" class="btn btn-secondary"style="display: none; font-size: 15px;">' . get_string('pause', 'videoassessment') . '</span>
                    </span></div>'
            );
            $mform->addElement(
                "html",
                "<div class='mdl-align upload-progress' style='display:none'><i class='icon fa fa-circle-o-notch fa-spin fa-fw' aria-hidden='true'></i><br/><h3>" .
                get_string('uploadingvideonotice', 'videoassessment') .
                "</h3></div><br/>"
            );

            $PAGE->requires->js('/mod/videoassessment/RecordRTC.js');
            $PAGE->requires->js('/mod/videoassessment/DetectRTC.js');
            $PAGE->requires->js_call_amd('mod_videoassessment/record', 'reCord', []);
        }

        // Set default upload type - must be after all radio buttons are added.
        $mform->setDefault('upload', $defaultuploadtype);

        $PAGE->requires->js_call_amd('mod_videoassessment/mod_form', 'initUploadTypeChange');
        $buttonarray = [];
        if ($mobile) {
            $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'init_mobile_upload_progress_bar', []);
            $btn = "submit";
        } else {
            $btn = "submit";
        }
        $buttonarray[] = &$mform->createElement($btn, 'submitbutton', get_string('upload'));
        $buttonarray[] = &$mform->createElement('button', 'cancelbutton', get_string('cancel'), ['onclick' => 'javascript :history.back(-1)']);
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validate form data for video upload requirements.
     *
     * Ensures that mobile uploads have either a file or URL provided
     * and returns validation errors for missing required data.
     *
     * @param array $data Form data to validate
     * @param array $files Uploaded files array
     * @return string[] Array of validation error messages
     */
    public function validation($data, $files) {
        $errors = [];

        if (isset($data['mobile']) && empty($data['mobileurl'])) {
            if (empty($files['mobilevideo'])) {
                $errors['mobilevideo'] = va::str('erroruploadvideo');
            }
        }

        return $errors;
    }
}
