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
 * This is the css style sheet for pagelayout-report.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess;

use videoassess\va;
use videoassess\form\videos_delete;

require_once '../../config.php';
require_once $CFG->dirroot . '/mod/videoassessment/locallib.php';
require_once $CFG->dirroot . '/mod/videoassessment/class/form/videos_delete.php';

class page_delete_video extends page {
	public function execute() {
		$this->va->teacher_only();

		$this->view_videos();
	}

	private function view_videos() {
		global $CFG, $DB, $PAGE;

		$PAGE->requires->js_init_call('M.mod_videoassessment.init_delete_videos');
		$PAGE->requires->strings_for_js(array(
				'errorcheckvideostodelete',
				'confirmdeletevideos'
		), 'mod_videoassessment');

		$form = new videos_delete(null, (object)array(
				'va' => $this->va
		));

		if ($data = $form->get_data()) {
			$videos = optional_param_array('videos', null, PARAM_BOOL);
			if (!$videos) {
				redirect($this->url);
			}

			foreach ($videos as $videoid => $v) {
				$this->va->delete_one_video($videoid);
			}

			redirect($this->url);
		}

		echo $this->header();
		echo $this->output->heading(va::str('deletevideos'));

		$form->display();

		$datadir = $CFG->dataroot;
		echo $this->output->box(get_string('diskspacetmpl', 'videoassessment', (object)array(
				'free' => display_size(disk_free_space($datadir)),
				'total' => display_size(disk_total_space($datadir))
		)));

		echo $this->output->footer();
	}
}

$page = new page_delete_video('/mod/videoassessment/deletevideos.php');
$page->execute();
