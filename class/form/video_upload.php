<?php
namespace videoassess\form;

use videoassess\va;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once $CFG->libdir . '/formslib.php';

class video_upload extends \moodleform {
    protected function definition() {
        global $COURSE, $CFG,$PAGE;
            $mform = $this->_form;
        /* @var $va \videoassess\va */
        $va = $this->_customdata->va;

        $mobile = va::uses_mobile_upload();
        if ($mobile) {
        	$mform->updateAttributes(array('enctype' => 'multipart/form-data',"id"=>"mobileform"));
        	$mform->addElement('hidden', 'mobile', 1);
        	$mform->setType('mobile', PARAM_BOOL);
        }

        $mform->addElement('hidden', 'id', required_param('id', PARAM_INT));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'instance', optional_param('instance', 0,PARAM_INT));
        $mform->setType('instance', PARAM_INT);
        $mform->setDefault('instance',$va->instance);
        $mform->addElement('hidden', 'action', 'upload');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'user', optional_param('user', 0, PARAM_INT));
        $mform->setType('user', PARAM_INT);
        $mform->addElement('hidden', 'timing', optional_param('timing', '', PARAM_ALPHA));
        $mform->setType('timing', PARAM_ALPHA);
        $mform->addElement('hidden','actionmodel',optional_param('actionmodel', 0,PARAM_INT),array('class'=>'actionmodel'));
        $mform->setType('actionmodel', PARAM_INT);
        
//         $mform->addElement('header', 'videohdr', get_string('upload', 'videoassessment'));

        $mform->addElement('header', 'uploadingvideo', get_string('uploadingvideo', 'videoassessment'));
        $mform->addHelpButton('uploadingvideo', 'uploadingvideo', 'videoassessment');
        $mform->addElement('radio','upload',get_string('uploadfile', 'videoassessment'),'',0);
        $mform->addHelpButton('upload', 'uploadfile', 'videoassessment');

        if ($mobile) {
            $mform->addElement("html","<div class='mdl-align upload-progress' style='display:none'><i class='icon fa fa-circle-o-notch fa-spin fa-fw' aria-hidden='true'></i><br/><h3>Uploading... please wait a few minutes</h3></div><br/>");
        }
        $maxbytes = $COURSE->maxbytes;
        if ($CFG->version < va::MOODLE_VERSION_23) {
        	$acceptedtypes = array('*');
        } else {
        	$acceptedtypes = array('video', 'audio');
        }

        if ($mobile) {
			$input = \html_writer::empty_tag('input',
					array(
							'type' => 'file',
							'id'=>'id_mobilevideo',
							'name' => 'mobilevideo',
							'accept' => 'video/*'
					));
			$mform->addElement('static', 'mobilevideo', "", $input);
        } else {
            $str = va::str('video');
//            if ($timing = optional_param('timing', null, PARAM_ALPHA)) {
//                $str .= ' (' . $va->timing_str($timing) . ')';
//            }
            $mform->addElement('filemanager', 'video',
                    "",
                    null,
                    array(
                            'subdirs' => 0,
                            'maxbytes' => $maxbytes,
                            'maxfiles' => 1,
                            'accepted_types' => $acceptedtypes
                    )
            );
        }

        $radios = array();
        $radios[] =& $mform->createElement('radio', 'upload',get_string('uploadyoutube','videoassessment'), '',1);
		//$mform->addElement('radio','upload1',get_string('uploadyoutube','videoassessment'),'',1);

		if ($mobile) {
            $radios[] =& $mform->createElement('text', 'mobileurl','url', array('size' => 40));
			$mform->setType('mobileurl', PARAM_URL);
			$mform->addHelpButton('mobileurl', 'url', 'videoassessment');
			$mform->addRule('mobileurl', get_string('url_error','videoassessment'), 'regex','/^((https?:\/\/)?(w{0,3}\.)?youtu(\.be|(be|be-nocookie)\.\w{2,3}\/))((watch\?v=|v|embed)?[\/]?(?P<video>[a-zA-Z0-9-_]{11}))/si');
		}else{
            $radios[] =& $mform->createElement('text', 'url','url', array('size' => 40));
			$mform->setType('url', PARAM_URL);
			$mform->addHelpButton('url', 'url', 'videoassessment');
			$mform->addRule('url', get_string('url_error','videoassessment'), 'regex','/^((https?:\/\/)?(w{0,3}\.)?youtu(\.be|(be|be-nocookie)\.\w{2,3}\/))((watch\?v=|v|embed)?[\/]?(?P<video>[a-zA-Z0-9-_]{11}))/si');
		}
        $mform->addGroup($radios, 'radios', "", array(' <br/>', '<br/>'), false);
        $mform->addHelpButton('radios', 'uploadyoutube', 'videoassessment');
		$module = array(
				'name' => 'mod_videoassessment',
				'fullpath' => '/mod/videoassessment/mod_form.js',
				'requires' => array('node', 'event'),
				'strings' => array(array('changeuploadtype', 'mod_videoassessment'))
		);
		
//  	$this->add_action_buttons(false, get_string('upload'));


		$PAGE->requires->js_init_call('M.mod_videoassessment.init_upload_type_change', null, false, $module);
		$buttonarray=array();
        if ($mobile) {
            $PAGE->requires->js_call_amd('mod_videoassessment/videoassessment', 'init_mobile_upload_progress_bar',array());
            $btn = "submit";
        }else{
            $btn = "submit";
        }
		$buttonarray[] = &$mform->createElement($btn, 'submitbutton', get_string('upload'));
		$buttonarray[] = &$mform->createElement('button','cancelbutton', get_string('cancel'),array('onclick'=>'javascript :history.back(-1)'));
		$mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
		$mform->closeHeaderBefore('buttonar');
    }

    /**
     *
     * @param array $data
     * @param array $files
     * @return string[]
     */
    public function validation($data, $files) {
    	$errors = array();

    	if (isset($data['mobile']) && empty($data['mobileurl']) ) {
    		if (empty($files['mobilevideo'])) {
    			$errors['mobilevideo'] = va::str('erroruploadvideo');
    		}
    	}

    	return $errors;
    }
}
