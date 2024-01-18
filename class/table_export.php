<?php
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
