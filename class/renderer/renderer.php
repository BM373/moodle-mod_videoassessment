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
 * Renderer used to display special elements of the videoassessment module.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use videoassess\va;

defined('MOODLE_INTERNAL') || die();

class mod_videoassessment_renderer extends plugin_renderer_base {
    /**
     *
     * @param renderable $widget
     * @return string
     */
    public function render(renderable $widget) {
        $rendermethod = 'render_'.str_replace('\\', '_', get_class($widget));
        if (method_exists($this, $rendermethod)) {
            return $this->$rendermethod($widget);
        }
        return $this->output->render($widget);
    }

    /**
     * @return string
     */
    public function header(va $va) {
        $this->page->set_title($va->va->name);

        $o = '';
        $o .= $this->output->header();
        $o .= $this->task_link($va);

        return $o;
    }

    /**
     * @return string
     */
    public function footer() {
        return $this->output->footer();
    }

    /**
     *
     * @return string
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
                            get_string('assess', 'videoassessment'), null, $highlight->assess)
            );
            $o .= $this->output->box(implode(get_separator(), $links));
        }

        return $o;
    }

    /**
     *
     * @param videoassessment_video $video
     * @return string
     */
    public function render_videoassess_video(videoassess\video $video) {
        global $CFG;

        if ($CFG->release < 2012062500) {
            // Moodle 2.2
            require_once $CFG->dirroot.'/filter/mediaplugin/filter.php';
        }

        if (optional_param('novideo', 0, PARAM_BOOL)) {
            return;
        }
		if($video->data->tmpname == 'Youtube'){
			$url = $video->data->originalname;
		}else{
			$url = moodle_url::make_pluginfile_url(
					$video->context->id, 'mod_videoassessment', 'video', 0,
					$video->file->get_filepath(), $video->file->get_filename());
		}


        $url = (string)$url; // moodle_url->__toString()
        @$alt = $this->alt ?: $url;

        $width = !empty($video->width) ? $video->width : 400;
        $height = !empty($video->height) ? $video->height : 300;
        
        $dim = is_numeric($width) && is_numeric($height) && $width > 0 && $height > 0
        ? sprintf('#d=%dx%d', $width, $height)
        : '';
        
        $filter = new filter_mediaplugin($this->va->context, array());
        if (videoassess\va::check_mp4_support()) {
            // MP4形式をサポートするブラウザは HTML5 <video> タグ使用
            $prev_filter_mediaplugin_enable_html5video = !empty($CFG->filter_mediaplugin_enable_html5video);
            $CFG->filter_mediaplugin_enable_html5video = true;
            $html = $filter->filter('<a href="'.$url.$dim.'">'.$alt.'</a>');
            $CFG->filter_mediaplugin_enable_html5video = $prev_filter_mediaplugin_enable_html5video;
            return $html;
        }
        // それ以外のブラウザは FlowPlayer 使用
        // (Windows では QuickTime は一般的ではないので .mp4 にも FlowPlayer を使用する)

        // 拡張子が .mp4 だとFLVフィルタにマッチしないので、
        // ダミーの拡張子 .flv に付け替えてフィルタを通し、
        // 得られたHTMLを元の拡張子に書き換える
        $mp4 = null;
        if (preg_match('/\.mp4$/i', $url, $m)) {
            list ($mp4) = $m;
            $url = substr_replace($url, '.flv', -4);
        }
        $prev_filter_mediaplugin_enable_flv = !empty($CFG->filter_mediaplugin_enable_flv);
        $CFG->filter_mediaplugin_enable_flv = true;
        $html = $filter->filter('<a href="'.$url.$dim.'">'.$alt.'</a>');
        $CFG->filter_mediaplugin_enable_flv = $prev_filter_mediaplugin_enable_flv;
        if ($mp4) {
            $html = preg_replace('/\.flv(?=["#])/', $mp4, $html);
        }

        $o = $this->container($html, 'video');

        return $o;
    }

    function render_videoassess_info_status($va){
        $o ='';
        if($va->allowsubmissionsfromdate != 0 || $va->duedate!=0|| $va->cutoffdate!=0){
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
                //$cell2content = userdate($duedate);
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
