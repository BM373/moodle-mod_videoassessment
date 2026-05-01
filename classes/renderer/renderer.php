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

namespace mod_videoassessment\output;

use plugin_renderer_base;
use renderable;
use mod_videoassessment\va;
use mod_videoassessment\video;
use filter_mediaplugin\text_filter;
use moodle_url;
use html_table;
use html_table_row;
use html_table_cell;
use html_writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Main renderer for video assessment module.
 *
 * This renderer handles the display of video assessment elements including
 * headers, footers, task links, video players, and status information.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Video assessment instance object.
     *
     * @var va|null
     */
    public $va = null;

    /**
     * Render a renderable object using appropriate method.
     *
     * Routes renderable objects to their specific render methods
     * or falls back to the default output renderer if no specific
     * method exists.
     *
     * @param renderable $widget The renderable object to render
     * @return string HTML output of the rendered object
     */
    public function render(renderable $widget) {
        $rendermethod = 'render_'.str_replace('\\', '_', get_class($widget));
        if (method_exists($this, $rendermethod)) {
            return $this->$rendermethod($widget);
        }
        return $this->output->render($widget);
    }

    /**
     * Generate page header with task navigation links.
     *
     * Creates the standard page header including the main header
     * and task navigation links for teachers.
     *
     * @param va $va Video assessment instance object
     * @return string HTML content for the page header
     */
    public function header(va $va) {
        $this->page->set_title($va->va->name);

        $o = '';
        $o .= $this->output->header();
        $o .= $this->task_link($va);

        return $o;
    }

    /**
     * Generate page footer.
     *
     * Returns the standard page footer HTML content.
     *
     * @return string HTML content for the page footer
     */
    public function footer() {
        return $this->output->footer();
    }

    /**
     * Generate task navigation links for teachers.
     *
     * Creates navigation links for upload, associate, and assess tasks
     * with highlighting for the current active task.
     *
     * @param va $va Video assessment instance object
     * @return string HTML content for task navigation links
     */
    public function task_link(va $va) {
        $highlight = (object)array('upload' => null, 'associate' => null, 'assess' => null);
        $current = array('class' => 'tasklink-current');
        switch ($va->action) {
            case 'videos':
                $highlight->associate = $current;
                break;
            case 'assess':
                $highlight->assess = $current;
                break;
        }

        $o = '';
        if ($va->is_teacher()) {
            $links = array(
                    $this->output->action_link(new \moodle_url('/mod/videoassessment/bulkupload/index.php',
                            array('cmid' => $va->cm->id)),
                            get_string('uploadvideos', 'videoassessment'), $highlight->upload),
                    $this->output->action_link(new \moodle_url('/mod/videoassessment/view.php',
                            array('id' => $va->cm->id, 'action' => 'videos')),
                            get_string('associate', 'videoassessment'), null, $highlight->associate),
                    $this->output->action_link(new \moodle_url('/mod/videoassessment/view.php',
                            array('id' => $va->cm->id)),
                            get_string('assess', 'videoassessment'), null, $highlight->assess),
            );
            $o .= $this->output->box(implode(get_separator(), $links));
        }

        return $o;
    }

    /**
     * Render video player with appropriate format support.
     *
     * Generates HTML for video playback with support for both HTML5
     * video tags and FlowPlayer based on browser capabilities and file format.
     *
     * @param \mod_videoassessment\video $video Video object to render
     * @return string HTML content for the video player
     */
    public function render_mod_videoassessment_video(video $video) {
        global $CFG;

        if ($CFG->release < 2012062500) {
            // Moodle 2.2
            require_once($CFG->dirroot.'/filter/mediaplugin/filter.php');
        }

        if (optional_param('novideo', 0, PARAM_BOOL)) {
            return;
        }
        if ($video->data->tmpname == 'Youtube') {
            $url = $video->data->originalname;
        } else {
            $url = moodle_url::make_pluginfile_url(
                    $video->context->id, 'mod_videoassessment', 'video', 0,
                    $video->file->get_filepath(), $video->file->get_filename());
        }

        $url = (string)$url;
        @$alt = $this->alt ?? $url;

        // Use width and height from $video->data if available, otherwise default.
        $width = !empty($video->data->width) ? $video->data->width : 400;
        $height = !empty($video->data->height) ? $video->data->height : 300;

        $dim = is_numeric($width) && is_numeric($height) && $width > 0 && $height > 0
        ? sprintf('#d=%dx%d', $width, $height)
        : '';

        // Use the video object's context instead of $this->va (which may not exist here).
        $filter = new \filter_mediaplugin\text_filter($video->context, array());
        if (va::check_mp4_support()) {
            // Browsers supporting the MP4 format use the HTML5 <video> tag.
            $prevfiltermediapluginenablehtml5video = !empty($CFG->filtermediapluginenablehtml5video);
            $CFG->filtermediapluginenablehtml5video = true;
            $html = $filter->filter('<a href="'.$url.$dim.'">'.$alt.'</a>');
            $CFG->filtermediapluginenablehtml5video = $prevfiltermediapluginenablehtml5video;
            return $html;
        }
        // Other browsers use FlowPlayer.
        // (Since QuickTime is not widely used on Windows, FlowPlayer is also used for .mp4 files.)

        // Since the .mp4 extension doesn't match the FLV filter,
        // we replace it with the dummy .flv extension to pass through the filter,
        // then rewrite the resulting HTML with the original extension.
        $mp4 = null;
        if (preg_match('/\.mp4$/i', $url, $m)) {
            list ($mp4) = $m;
            $url = substr_replace($url, '.flv', -4);
        }
        $prevfiltermediapluginenableflv = !empty($CFG->filtermediapluginenableflv);
        $CFG->filtermediapluginenableflv = true;
        $html = $filter->filter('<a href="'.$url.$dim.'">'.$alt.'</a>');
        $CFG->filtermediapluginenableflv = $prevfiltermediapluginenableflv;
        if ($mp4) {
            $html = preg_replace('/\.flv(?=["#])/', $mp4, $html);
        }

        $o = $this->container($html, 'video');

        return $o;
    }

    /**
     * Render video assessment status information.
     *
     * Displays submission dates, due dates, and cutoff information
     * in a formatted table for users to understand assessment timing.
     *
     * @param va $va Video assessment instance object
     * @return string HTML content for status information table
     */
    public function render_mod_videoassessment_info_status($va) {
        $o = '';
        if ($va->allowsubmissionsfromdate != 0 || $va->duedate != 0 || $va->cutoffdate != 0) {
            $o .= $this->output->container_start('submissionstatustable');
            $o .= $this->output->heading("Videoassess state info", 3);
            $o .= $this->output->box_start('boxaligncenter submissionsummarytable');

            $t = new html_table();
            $time = time();
            $duedate = $va->duedate;
            $cutoffdate = $va->cutoffdate;
            if ($duedate > 0) {
                if ($va->allowsubmissionsfromdate) {
                    // allowsubmissionsfrom date.
                    $cell1content = get_string('allowsubmissionsfromdate', 'assign');
                    $cell2content = userdate($va->allowsubmissionsfromdate);
                    $this->add_table_row_tuple($t, $cell1content, $cell2content);
                }

                // Due date.
                $cell1content = get_string('duedate', 'assign');
                if ($duedate - $time <= 0) {
                    $cell2content = userdate($duedate).'('.get_string('assignmentisdue', 'videoassessment').')';
                } else {
                    $cell2content = format_time($duedate - $time);
                }
                $this->add_table_row_tuple($t, $cell1content, $cell2content);

                if ($va->cutoffdate) {
                    // Cut off date.
                    $cell1content = get_string('cutoffdate', 'assign');
                    if ($cutoffdate > $time) {
                        $cell2content = get_string('latesubmissionsaccepted', 'videoassessment', userdate($va->cutoffdate));
                    } else {
                        $cell2content = userdate($va->cutoffdate).'('.get_string('nomoresubmissionsaccepted', 'videoassessment').')';
                    }
                    $this->add_table_row_tuple($t, $cell1content, $cell2content);
                }

            }
            $o .= html_writer::table($t);
            $o .= $this->output->box_end();
            $o .= $this->output->container_end();
            return $o;
        }
    }

    /**
     * Add a two-column row to an HTML table.
     *
     * Creates a table row with two cells where the first cell is
     * treated as a header cell with optional attributes.
     *
     * @param html_table $table The table to add the row to
     * @param string $first Content for the first cell
     * @param string $second Content for the second cell
     * @param array $firstattributes Optional attributes for the first cell
     * @param array $secondattributes Optional attributes for the second cell
     * @return void
     */
    private function add_table_row_tuple(html_table $table, $first, $second, $firstattributes = [],
                                        $secondattributes = []) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $cell1->header = true;
        if (!empty($firstattributes)) {
            $cell1->attributes = $firstattributes;
        }
        $cell2 = new html_table_cell($second);
        if (!empty($secondattributes)) {
            $cell2->attributes = $secondattributes;
        }
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;
    }
}
