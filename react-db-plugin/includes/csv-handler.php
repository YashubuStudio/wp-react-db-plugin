<?php
class CSVHandler {
    public static function readCSV($filepath) {
        $rows = [];
        if (($handle = fopen($filepath, 'r')) !== FALSE) {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }

    public static function writeCSV($filepath, $data) {
        $handle = fopen($filepath, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return true;
    }
}
