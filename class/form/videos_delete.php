<?php
namespace videoassess\form;

use \videoassess\va;
use \videoassess\video;

defined('MOODLE_INTERNAL') || die();

class videos_delete extends \moodleform {
    public function definition() {
        global $DB, $OUTPUT;

        $mform = $this->_form;
        /* @var $va \videoassess\va */
        $va = $this->_customdata->va;

        $mform->addElement('hidden', 'action', 'publish');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'id', $va->cm->id);
        $mform->setType('id', PARAM_INT);

        ob_start();
        $table = new \flexible_table('videos-delete');
        $table->set_attribute('class', 'generaltable');
        $table->define_baseurl(new \moodle_url('/mod/videoassessment/deletevideos.php', array('id' => $va->cm->id)));
        $columns = array(
                'checkbox',
                'thumbnail',
        		'size',
                'grade',
        );
        $checkall = \html_writer::empty_tag('input', array(
        		'type' => 'checkbox',
        		'id' => 'all-video-check'
        ));
        $headers = array(
                $checkall,
                va::str('video'),
        		get_string('size'),
                get_string('grade'),
        );
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();

        $videorecs = $DB->get_records('videoassessment_videos', array('videoassessment' => $va->instance));
        $o = '';
        foreach ($videorecs as $videorec) {
            $video = new video($va->context, $videorec);
            if ($video->file) {
//                 $mform->addElement('checkbox', 'videos['.$videorec->id.']', $videorec->filename);
            }

            if (empty($videorec->grade)) {
                $videorec->grade = -1;
            }
            $videorec->gradecell = '';

            $assocs = $va->get_video_associations($videorec->id);
            $gradecell = '';
            foreach ($assocs as $assoc) {
                if ($user = $DB->get_record('user', array('id' => $assoc->associationid))) {
                    $gradecell .= $OUTPUT->user_picture($user).' ';
                    $gradecell .= fullname($user).' ';
                }
                $grade = $va->get_aggregated_grades($assoc->associationid);
                foreach ($va->timings as $timing) {
                    $prop = 'grade'.$timing;
                    if ($grade->$prop != -1) {
                        $gradecell .= va::str($timing).': '.$grade->$prop.' ';

                       $videorec->grade = max($videorec->grade, $grade->$prop);
                    }
                }
                $gradecell .= \html_writer::empty_tag('br');
            }
            $videorec->gradecell = $gradecell;

            $videorec->link = $video->render_thumbnail_with_preview();

            if ($video->has_file()) {
	            $videorec->filesize = $video->file->get_filesize();
	            $videorec->contenthash = $video->file->get_contenthash();
            } else {
            	$videorec->filesize = 0;
            	$videorec->contenthash = '';
            }
        }

        uasort($videorecs, function (\stdClass $a, \stdClass $b) {
            return $b->grade - $a->grade;
        });

        foreach ($videorecs as $videorec) {
            $table->add_data(array(
                    \html_writer::checkbox('videos['.$videorec->id.']', 1, false, '',
                    		array('class' => 'video-check')),
                    $videorec->link,
            		display_size($videorec->filesize),
                    $videorec->gradecell,
            ));
        }

        $table->finish_output();
        $o .= ob_get_contents();
        ob_end_clean();

        $mform->addElement('header', 'videohdr', va::str('videos'));
        $mform->addElement('static', 'videos', va::str('videos'), $o);
        $mform->addHelpButton('videos', 'deletevideos_videos', va::VA);

        $this->add_action_buttons(false, va::str('deleteselectedvideos'));
    }

    /**
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
