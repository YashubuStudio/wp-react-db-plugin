<?php
class CSVHandler {
    /**
     * Read CSV file with automatic encoding, delimiter and BOM handling.
     */
    public static function readCSV($filepath) {
        $parsed = self::parseCSV($filepath);
        return $parsed['rows'];
    }

    /**
     * Parse a CSV file and return rows with metadata.
     *
     * @param string $filepath
     * @param array  $args
     * @return array{rows: array<int, array<int, string>>, delimiter: string|null, encoding: string|null}
     */
    public static function parseCSV($filepath, $args = []) {
        $defaults = [
            'skip_empty' => true,
        ];
        if (function_exists('wp_parse_args')) {
            $args = wp_parse_args($args, $defaults);
        } else {
            $args = array_merge($defaults, is_array($args) ? $args : []);
        }

        if (!is_readable($filepath)) {
            return [
                'rows'      => [],
                'delimiter' => null,
                'encoding'  => null,
            ];
        }

        $contents = file_get_contents($filepath);
        if ($contents === false) {
            return [
                'rows'      => [],
                'delimiter' => null,
                'encoding'  => null,
            ];
        }

        $encoding = null;
        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($contents, ['UTF-8', 'SJIS-win', 'eucJP-win', 'EUC-JP', 'JIS', 'ISO-2022-JP', 'ISO-8859-1', 'CP932'], true);
        }

        if ($encoding && strtoupper($encoding) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($contents, 'UTF-8', $encoding);
            if ($converted !== false) {
                $contents = $converted;
            } else {
                $encoding = 'UTF-8';
            }
        } else {
            $encoding = 'UTF-8';
        }

        // Remove BOM if present.
        if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
            $contents = substr($contents, 3);
        }

        // Normalise line endings.
        $contents = preg_replace("/\r\n?|\n/", "\n", $contents);

        $lines = preg_split("/\n/", $contents);
        $sampleLine = '';
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $sampleLine = $line;
            break;
        }

        $delimiter = ',';
        if ($sampleLine !== '') {
            $delimiters = [',', "\t", ';', '|'];
            $bestCount  = 0;
            foreach ($delimiters as $candidate) {
                $count = substr_count($sampleLine, $candidate);
                if ($count > $bestCount) {
                    $bestCount = $count;
                    $delimiter = $candidate;
                }
            }
        }

        $temp = fopen('php://temp', 'r+');
        fwrite($temp, implode("\n", $lines));
        fwrite($temp, "\n");
        rewind($temp);

        $rows = [];
        if (function_exists('ini_set')) {
            @ini_set('auto_detect_line_endings', '1');
        }

        while (($data = fgetcsv($temp, 0, $delimiter)) !== false) {
            if ($data === null) {
                continue;
            }

            // Trim whitespace and normalise nulls to empty strings.
            foreach ($data as $index => $value) {
                if ($value === null) {
                    $data[$index] = '';
                } else {
                    $data[$index] = is_string($value) ? trim($value) : $value;
                }
            }

            if ($args['skip_empty']) {
                $nonEmpty = array_filter($data, function ($item) {
                    return $item !== '' && $item !== null;
                });
                if (empty($nonEmpty)) {
                    continue;
                }
            }

            $rows[] = $data;
        }

        fclose($temp);

        return [
            'rows'      => $rows,
            'delimiter' => $delimiter,
            'encoding'  => $encoding,
        ];
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
