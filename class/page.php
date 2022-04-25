<?php
namespace videoassess;

defined('MOODLE_INTERNAL') || die();

abstract class page {
    /**
     *
     * @var va
     */
    protected $va;
    /**
     *
     * @var \moodle_url
     */
    protected $url;
    /**
     *
     * @var \mod_videoassessment_renderer|\core_renderer
     */
    protected $output;

    /**
     *
     * @param string $url
     */
    public function __construct($url) {
        global $DB, $PAGE;

        $cmid = required_param('id', PARAM_INT);
        $cm = get_coursemodule_from_id('videoassessment', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cmid);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        require_login($course, true, $cm);

        $PAGE->set_title($cm->name);
        $PAGE->set_heading($cm->name);

        $this->va = new va($context, $cm, $course);
        $this->url = new \moodle_url($url, array('id' => $cm->id));
        $PAGE->set_url($this->url);
        $this->output = $PAGE->get_renderer('mod_videoassessment');
    }

    public abstract function execute();

    /**
     *
     * @return string
     */
    protected function header() {
        return $this->output->header($this->va);
    }
}
