<?php
class My_CSV_Reader
{
    var $separator = ',';
    var $enclosure = '"';
    var $header = TRUE;
    public function setLogger($log)
    {
        $this->log = $log;
    }
    function parseFile($Filepath)
    {
        $csv = fopen($Filepath, "r");
        if (!$csv) {
            $this->log->info('Error opening file');
            return false;
        }
        $this->log->info('File received to process');
        $this->log->info('Seperator: ' . $this->separator . ' Enclosure: ' . $this->enclosure);
        if ($this->header) {
            $head = fgetcsv($csv, 1024, $this->separator, $this->enclosure);
        }
        while ($line = fgetcsv($csv, 1024, $this->separator, $this->enclosure)) {
            if (!isset($head)) {
                $data[] = $line;
            } else {
                $newline = array();
                for ($i = 0; $i < count($head); $i++) {
                    $cell = isset($line[$i]) ? $line[$i] : "";
                    $cell = preg_replace("/\"/", "\"\"", $cell);
                    $newline[$head[$i]] = empty($cell) ? '' : $cell;
                }
                $data[] = $newline;
            }
        }
        fclose($csv);
        return $data;
    }
}
