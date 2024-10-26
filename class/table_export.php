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
 * The videoassess namespace definition.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace videoassess;

defined('MOODLE_INTERNAL') || die();

class table_export {
    /**
     *
     * @var array
     */
    private $data;
    /**
     *
     * @var int
     */
    private $rows = 0;
    /**
     *
     * @var int
     */
    private $columns = 0;
    public $filename = 'rubric.xls';

    /**
     *
     * @param int $row
     * @param int $column
     * @param string $value
     */
    public function set($row, $column, $value) {
        $this->data[$row][$column] = $value;

        $this->rows = max($this->rows, $row + 1);
        $this->columns = max($this->columns, $column + 1);
    }

    public function xls($width = false) {
        global $CFG;
        require_once $CFG->libdir . '/excellib.class.php';

        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($this->filename);
        $xls = $workbook->add_worksheet('Rubric');

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                if (isset($this->data[$row][$column])) {
                    $value = $this->data[$row][$column];
                    $xls->write_string($row, $column, $value);
                }
            }
        }
        
        if($width) {
            $xls->set_column(1, 4, 30);
        }

        $workbook->close();
    }

    public function csv() {
        header('Content-Type: text/plain; charset=UTF-8');

        $o = '';

        for ($row = 0; $row < $this->rows; $row++) {
            for ($column = 0; $column < $this->columns; $column++) {
                if (isset($this->data[$row][$column])) {
                    $value = $this->data[$row][$column];
                    if (preg_match('/[,"\n]/', $value)) {
                        $value = '"'.str_replace('"', '""', $value).'"';
                    }
                    $o .= $value;
                }
                if ($column < $this->columns - 1) {
                    $o .= ',';
                }
            }
            $o .= "\n";
        }

        echo $o;
    }
}
