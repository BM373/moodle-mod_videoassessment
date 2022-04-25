<?php
namespace videoassess;

defined('MOODLE_INTERNAL') || die();

class video implements \renderable {
    public $data;
    /**
     *
     * @var \stored_file
     */
    public $file;
    /**
     *
     * @var \stored_file
     */
    public $thumbnail;
    /**
     *
     * @var \context_module
     */
    public $context;
    public $ready = false;

    /**
     *
     * @param \context_module $context
     * @param \stdClass $data
     */
    public function __construct(\context_module $context, \stdClass $data) {
        $this->context = $context;
        $this->data = $data;

        $fs = get_file_storage();

        if ($file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $data->filepath,
                $data->filename)) {
            $this->file = $file;
            $this->ready = true;
        }
        if($data->tmpname=='Youtube'){
        	$this->ready = true;
        }
        if ($data->thumbnailname
                && $file = $fs->get_file($this->context->id, 'mod_videoassessment', 'video', 0, $data->filepath,
                        $data->thumbnailname)) {
            $this->thumbnail = $file;
        }
    }

    public function __toString() {
        return $this->file;
    }

    public function get_url($forcedownload = false) {
        if (empty($this->file)) {
            return null;
        }
        return \moodle_url::make_pluginfile_url(
                $this->context->id, 'mod_videoassessment', 'video', 0,
                $this->file->get_filepath(), $this->file->get_filename(), $forcedownload);
    }

    public function get_thumbnail_url() {
        if ($this->thumbnail) {
            return \moodle_url::make_pluginfile_url(
                    $this->context->id, 'mod_videoassessment', 'video', 0,
                    $this->thumbnail->get_filepath(), $this->thumbnail->get_filename());
        }
        return null;
    }

    public function render_thumbnail($defaultcontent = null) {
        if ($url = $this->get_thumbnail_url()) {
            return \html_writer::empty_tag('img', array('src' => $url));
        }
        return $defaultcontent;
    }

    public function render_thumbnail_with_preview($defaultcontent = null) {
        return \html_writer::tag('a', $this->render_thumbnail($defaultcontent), array(
                'href' => $this->get_url(),
                'class' => 'videolink',
                'data-videoid' => $this->data->id
        ));
    }

    /**
     *
     * @return boolean
     */
    public function has_file() {
    	return !empty($this->file);
    }

    public function delete_file() {
    	if ($this->file) {
    		$this->file->delete();
    	}
    	if ($this->thumbnail) {
    		$this->thumbnail->delete();
    	}
    }

    /**
     *
     * @param \context_module $context
     * @param int $videoid
     * @return \videoassess\video
     */
    public static function from_id(\context_module $context, $videoid) {
    	global $DB;

    	$data = $DB->get_record('videoassessment_videos', array('id' => $videoid), '*', MUST_EXIST);
    	return new self($context, $data);
    }
}
