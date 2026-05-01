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

namespace mod_videoassessment;

defined('MOODLE_INTERNAL') || die();

/**
 * Table export utility for video assessment data.
 *
 * This class provides functionality to export tabular data in both
 * Excel (.xls) and CSV formats with proper formatting and encoding.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_export {
    /**
     * Two-dimensional array storing table data.
     *
     * Contains the actual data values indexed by row and column positions.
     *
     * @var array
     */
    private $data;

    /**
     * Number of rows in the table.
     *
     * Tracks the maximum row count based on data entries.
     *
     * @var int
     */
    private $rows = 0;

    /**
     * Number of columns in the table.
     *
     * Tracks the maximum column count based on data entries.
     *
     * @var int
     */
    private $columns = 0;

    /**
     * Default filename for exported files.
     *
     * @var string
     */
    public $filename = 'rubric.xls';

    /**
     * Set a value at specific row and column position.
     *
     * Stores data at the specified position and updates the table
     * dimensions to accommodate the new data point.
     *
     * @param int $row Row index (0-based)
     * @param int $column Column index (0-based)
     * @param string $value Value to store at the position
     * @return void
     */
    public function set($row, $column, $value) {
        $this->data[$row][$column] = $value;

        $this->rows = max($this->rows, $row + 1);
        $this->columns = max($this->columns, $column + 1);
    }

    /**
     * Export table data to Excel format.
     *
     * Generates an Excel workbook with the table data and sends it
     * to the browser for download with optional column width formatting.
     *
     * @param bool $width Whether to apply custom column width formatting
     * @return void
     */
    public function xls($width = false) {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');

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

        if ($width) {
            $xls->set_column(1, 4, 30);
        }

        $workbook->close();
    }

    /**
     * Export table data to CSV format.
     *
     * Generates CSV output with proper escaping for special characters
     * and sends it directly to the browser with UTF-8 encoding.
     *
     * @return void
     */
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
