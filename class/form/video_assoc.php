<?php
namespace videoassess\form;

defined('MOODLE_INTERNAL') || die();

class video_assoc extends \moodleform {
    private $_name = 'assocform';

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        if (!empty($this->_customdata->cmid)) {
            $mform->setDefault('id', $this->_customdata->cmid);
        }
        $mform->addElement('hidden', 'action', 'videoassoc');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'videoid', '', array('id' => 'id_videoid'));
        $mform->setType('videoid', PARAM_INT);
        $mform->addElement('hidden', 'assocdata', '', array('id' => 'id_assocdata'));
        $mform->setType('assocdata', PARAM_RAW);
        $mform->addElement('hidden', 'timing', 'before', array('id' => 'id_timing'));
        $mform->setType('timing', PARAM_ALPHA);

//         $this->add_action_buttons();
    }
}
