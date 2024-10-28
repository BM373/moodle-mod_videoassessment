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
 * Form for publishing videos for the videoassessment module.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess\form;

use \videoassess\va;
use \videoassess\video;

defined('MOODLE_INTERNAL') || die();

class video_publish extends \moodleform {
    /**
     *
     * @global \stdClass $CFG
     * @global \moodle_database $DB
     * @global \core_renderer $OUTPUT
     * @global \moodle_page $PAGE
     * @global \stdClass $USER
     */
    public function definition() {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;
        //require_once $CFG->libdir . '/coursecatlib.php';

        $mform = $this->_form;
        /* @var $va \videoassess\va */
        $va = $this->_customdata->va;

        $mform->addElement('hidden', 'action', 'publish');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'id', $va->cm->id);
        $mform->setType('id', PARAM_INT);

        $courseopts = array();
        $categories = \core_course_category::make_categories_list('moodle/course:create');
        /* MinhTB VERSION 2 03-03-2016 */

        $sectionopts = array();
        $sectionopts[0] = '';
        if (!empty($categories)) {
            //$mform->addElement('static', 'courseor', get_string('or', 'videoassessment'));
            $mform->addElement('select', 'category', get_string('category'), $categories, array('id' => 'publish-category', 'style' => 'min-width: 270px'));
            if (!empty($categories)) {
                $courseopts[0] = '('.get_string('new').')';
            }
            $courses = \videoassess\va::get_courses_managed_by($USER->id);
            array_walk($courses, function (\stdClass $a) use (&$courseopts, &$sectionopts) {
                $courseopts[$a->id] = $a->fullname;

                $modinfo = get_fast_modinfo($a->id);
                $sections = $modinfo->get_section_info_all();

                foreach ($sections as $key => $section) {
                    $sectionopts[$section->__get('id')] = get_section_name($a->id, $section->__get('section'));
                }
            });
            $mform->addElement('select', 'course', get_string('existingcourseornewcourse', 'videoassessment'), $courseopts, array(
                'class' => 'input-select',
                'id' => 'publish-course'
            ));
            $mform->addHelpButton('course', 'existingcourse', 'videoassessment');
            $mform->addElement('select', 'section', get_string('insertintosection', 'videoassessment'), $sectionopts, array(
                'disabled' => 'disabled',
                'class' => 'input-select',
                'id' => 'publish-section'
            ));
            $mform->addElement('text', 'fullname', get_string('fullnamecourse', 'videoassessment'), array(
                'class' => 'input-select',
                'id' => 'publish-fullname'
            ));
            $mform->setType('fullname', PARAM_TEXT);
            $mform->addElement('text', 'shortname', get_string('shortnamecourse', 'videoassessment'), array(
                'class' => 'input-select',
                'id' => 'publish-shortname'
            ));
            $mform->setType('shortname', PARAM_TEXT);
            $mform->addElement('text', 'prefix', get_string('addprefixtolabel', 'videoassessment'), array(
                'class' => 'input-select',
                'id' => 'publish-prefix'
            ));
            $mform->setType('prefix', PARAM_TEXT);
            $mform->addElement('text', 'suffix', get_string('addsuffixtolabel', 'videoassessment'), array(
                'class' => 'input-select',
                'id' => 'publish-suffix'
            ));
            $mform->setType('suffix', PARAM_TEXT);
            /* MinhTB VERSION 2 07-03-2016 */
            $mform->addElement('hidden', 'video_count', 0, array('id' => 'video-count'));
            $mform->setType('video_count', PARAM_INT);
            /* END MinhTB VERSION 2 07-03-2016 */

        }
        /* END MinhTB VERSION 2 03-03-2016 */
        ob_start();
        $table = new \flexible_table('video-publish');
        $table->set_attribute('class', 'generaltable');
        $table->define_baseurl(new \moodle_url($va->viewurl, array('action' => 'publish')));
        $columns = array(
                'checkbox',
                'thumbnail',
        		'name',
        		'size',
                'grade'
        );
        $checkall = \html_writer::empty_tag('input', array(
        		'type' => 'checkbox',
        		'id' => 'all-video-check'
        ));
        $headers = array(
                $checkall,
                va::str('video'),
        		va::str('originalname'),
        		get_string('size'),
                get_string('grade')
        );
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->setup();

        $videorecs = $DB->get_records('videoassessment_videos', array('videoassessment' => $va->instance));
        $o = '';
        foreach ($videorecs as $videorec) {
            $video = new video($va->context, $videorec);

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
                $timing = $assoc->timing;
				$prop = 'grade' . $timing;
				if ($grade->$prop != -1) {
					$gradecell .= va::str('score') . ': ' . $grade->$prop . ' ';

					$videorec->grade = max($videorec->grade, $grade->$prop);
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

        /* MinhTB VERSION 2 07-03-2016 */
        $videos = array_keys($this->_customdata->videos);

        foreach ($videorecs as $videorec) {
            if (in_array($videorec->id, $videos)) {
                $checked = true;
            } else {
                $checked = false;
            }

            $table->add_data(array(
                    \html_writer::checkbox('videos['.$videorec->id.']', 1, $checked, '',
                    		array('class' => 'video-check')),
                    $videorec->link,
            		$videorec->originalname,
            		display_size($videorec->filesize),
                    $videorec->gradecell
            ));
        }
        /* END MinhTB VERSION 2 07-03-2016 */

        $table->finish_output();
        $o .= ob_get_contents();
        ob_end_clean();

        $mform->addElement('static', 'videos', va::str('publishvideos_videos'), $o);
        $mform->addHelpButton('videos', 'publishvideos_videos', va::VA);

        $this->add_action_buttons(false, va::str('publishvideos'));
    }

    /**
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if (!$data['course']) {
            if (!trim($data['fullname'])) {
                $errors['fullname'] = va::str('inputnewcoursename');
            }

            if (!trim($data['shortname'])) {
                $errors['shortname'] = va::str('inputnewcourseshortname');
            } else {
                if ($DB->get_record('course', array('shortname' => trim($data['shortname'])))) {
                    $errors['shortname'] = va::str('courseshortnameexist');
                }
            }
        }

        if (!$data['video_count']) {
            $errors['videos'] = va::str('pleasechoosevideos');
        }

        return $errors;
    }
}
