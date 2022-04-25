<?php
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
