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
     * @return array{
     *     rows: array<int, array<int, string>>,
     *     delimiter: string|null,
     *     encoding: string|null,
     *     errors: array<int, array<string, mixed>>,
     * }
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
                'errors'    => [],
            ];
        }

        $contents = file_get_contents($filepath);
        if ($contents === false) {
            return [
                'rows'      => [],
                'delimiter' => null,
                'encoding'  => null,
                'errors'    => [],
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
        $delimiter = self::detect_delimiter_from_lines($lines);

        $parsedRows = self::parse_rows_with_recovery($contents, $delimiter, $args['skip_empty']);

        return [
            'rows'      => $parsedRows['rows'],
            'delimiter' => $delimiter,
            'encoding'  => $encoding,
            'errors'    => $parsedRows['errors'],
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
    /**
     * Detect the most likely delimiter from an array of lines.
     *
     * @param array<int, string> $lines
     * @return string
     */
    private static function detect_delimiter_from_lines($lines) {
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

        return $delimiter;
    }

    /**
     * Parse CSV rows while attempting to recover from malformed records.
     *
     * @param string $contents
     * @param string $delimiter
     * @param bool   $skipEmpty
     * @return array{rows: array<int, array<int, string>>, errors: array<int, array<string, mixed>>}
     */
    private static function parse_rows_with_recovery($contents, $delimiter, $skipEmpty) {
        $rows = [];
        $errors = [];

        $length = strlen($contents);
        $currentRow = [];
        $currentField = '';
        $inQuotes = false;
        $skippingRow = false;
        $skipMinimumBreaks = 0;
        $pendingRecovery = false;
        $firstColumnPattern = null;

        $expectedColumns = null;
        $isFirstRow = true;
        $currentLine = 1;
        $rowStartLine = 1;
        $rawBuffer = '';


        for ($i = 0; $i < $length; $i++) {
            $char = $contents[$i];

            if ($rawBuffer === '') {
                $rowStartLine = $currentLine;
            }

            if ($char === "\r") {
                continue;
            }

            if ($skippingRow) {
                if ($char === "\n") {
                    $lookahead = substr($contents, $i + 1, 400);
                    if ($skipMinimumBreaks > 0) {
                        $skipMinimumBreaks--;
                    }
                    if ($skipMinimumBreaks <= 0 && self::looks_like_new_row($lookahead, $delimiter)) {
                        $skippingRow = false;
                        $currentRow = [];
                        $currentField = '';
                        $rawBuffer = '';
                        $pendingRecovery = true;
                        $skipMinimumBreaks = 0;
                    }
                    $currentLine++;
                }
                continue;
            }

            $rawBuffer .= $char;

            if ($char === '"') {
                if ($inQuotes) {
                    if ($i + 1 < $length && $contents[$i + 1] === '"') {
                        $currentField .= '"';
                        $rawBuffer   .= '"';
                        $i++;
                    } else {
                        $inQuotes = false;
                    }
                } else {
                    if ($currentField === '' || trim($currentField) === '') {
                        $currentField = '';
                        $inQuotes = true;
                    } else {

                        $currentField .= '"';
                    }
                }
                continue;
            }

            if ($char === $delimiter && !$inQuotes) {
                self::finalize_field($currentRow, $currentField);
                continue;
            }

            if ($char === "\n") {
                if ($inQuotes) {
                    $lookahead = substr($contents, $i + 1, 400);
                    $looksLikeNextRow = self::looks_like_new_row($lookahead, $delimiter);
                    if ($looksLikeNextRow) {
                        $matchesPattern = self::lookahead_matches_first_column_pattern($lookahead, $firstColumnPattern, $delimiter);
                        $matchesExpected = self::lookahead_matches_expected_columns($lookahead, $delimiter, $expectedColumns);
                    } else {
                        $matchesPattern = false;
                        $matchesExpected = false;
                    }
                    if ($looksLikeNextRow && $matchesPattern && ($expectedColumns === null || $matchesExpected)) {
                        if (!empty($currentRow) || $currentField !== '') {
                            $errors[] = self::build_parse_error($rowStartLine, $rawBuffer, 'unterminated_quote');
                        }
                        $currentRow = [];
                        $currentField = '';
                        $rawBuffer = '';
                        $inQuotes = false;
                        $currentLine++;
                        continue;
                    }

                    $currentField .= "\n";
                    $currentLine++;
                    continue;
                }

                $proposedCount = count($currentRow) + 1;
                if ($expectedColumns !== null && $proposedCount < $expectedColumns && !$isFirstRow) {
                    $lookahead = substr($contents, $i + 1, 400);
                    $looksLikeNextRow = self::looks_like_new_row($lookahead, $delimiter);
                    $matchesPattern = self::lookahead_matches_first_column_pattern($lookahead, $firstColumnPattern, $delimiter);
                    $matchesExpected = self::lookahead_matches_expected_columns($lookahead, $delimiter, $expectedColumns);
                    if (!$looksLikeNextRow || !$matchesPattern || !$matchesExpected) {
                        $currentField .= "\n";
                        $currentLine++;
                        continue;
                    }
                }

                self::finalize_field($currentRow, $currentField);

                if ($pendingRecovery) {
                    $shouldSkip = true;
                    $firstValue = isset($currentRow[0]) ? $currentRow[0] : '';
                    if ($firstColumnPattern === 'numeric') {
                        $shouldSkip = !self::value_is_numeric_like($firstValue);
                    } else {
                        $shouldSkip = false;
                    }
                    if ($shouldSkip) {
                        if (!empty($currentRow) || $rawBuffer !== '') {
                            $errors[] = self::build_parse_error($rowStartLine, $rawBuffer, 'dangling_row');
                        }
                    } elseif (!$skipEmpty || self::row_has_value($currentRow)) {
                        $rows[] = $currentRow;
                        if ($isFirstRow) {
                            $expectedColumns = count($currentRow);
                            $isFirstRow = false;
                        } elseif (!empty($currentRow)) {
                            if ($expectedColumns === null) {
                                $expectedColumns = count($currentRow);
                            }
                            self::update_first_column_pattern($firstColumnPattern, $currentRow[0]);
                        }
                    }
                    $pendingRecovery = false;
                } elseif (!$skipEmpty || self::row_has_value($currentRow)) {
                    $rows[] = $currentRow;
                    if ($isFirstRow) {
                        $expectedColumns = count($currentRow);
                        $isFirstRow = false;
                    } elseif (!empty($currentRow)) {
                        if ($expectedColumns === null) {
                            $expectedColumns = count($currentRow);
                        }
                        self::update_first_column_pattern($firstColumnPattern, $currentRow[0]);
                    }
                }

                $currentRow = [];
                $currentField = '';
                $rawBuffer = '';
                $currentLine++;
                continue;
            }


            if (!$inQuotes && ($char === ' ' || $char === "\t")) {
                if ($currentField === '') {
                    continue;
                }
            }

            $currentField .= $char;
        }

        if ($inQuotes) {
            if (!empty($currentRow) || $currentField !== '') {
                $errors[] = self::build_parse_error($rowStartLine, $rawBuffer, 'unterminated_quote');
            }
        } else {
            self::finalize_field($currentRow, $currentField);
            if (!empty($currentRow)) {
                if ($pendingRecovery) {
                    $shouldSkip = true;
                    $firstValue = isset($currentRow[0]) ? $currentRow[0] : '';
                    if ($firstColumnPattern === 'numeric') {
                        $shouldSkip = !self::value_is_numeric_like($firstValue);
                    } else {
                        $shouldSkip = false;
                    }
                    if ($shouldSkip) {
                        $errors[] = self::build_parse_error($rowStartLine, $rawBuffer, 'dangling_row');
                    } elseif (!$skipEmpty || self::row_has_value($currentRow)) {
                        $rows[] = $currentRow;
                        if ($isFirstRow) {
                            $expectedColumns = count($currentRow);
                            $isFirstRow = false;
                        } elseif (!empty($currentRow)) {
                            if ($expectedColumns === null) {
                                $expectedColumns = count($currentRow);
                            }
                            self::update_first_column_pattern($firstColumnPattern, $currentRow[0]);
                        }
                    }
                    $pendingRecovery = false;
                } elseif (!$skipEmpty || self::row_has_value($currentRow)) {
                    $rows[] = $currentRow;
                    if ($isFirstRow) {

                        $expectedColumns = count($currentRow);
                        $isFirstRow = false;
                    } elseif (!empty($currentRow)) {
                        if ($expectedColumns === null) {
                            $expectedColumns = count($currentRow);
                        }
                        self::update_first_column_pattern($firstColumnPattern, $currentRow[0]);
                    }
                }
            }
        }

        return [
            'rows'   => $rows,
            'errors' => $errors,
        ];
    }

    private static function finalize_field(&$currentRow, &$currentField) {
        $value = $currentField;
        $currentRow[] = is_string($value) ? trim($value) : $value;
        $currentField = '';
    }

    private static function row_has_value($row) {
        foreach ($row as $item) {
            if ($item !== '' && $item !== null) {
                return true;
            }
        }
        return false;
    }

    private static function looks_like_new_row($snippet, $delimiter) {
        $snippet = ltrim($snippet, "\r\n");
        if ($snippet === '') {
            return false;
        }

        $newlinePos = strpos($snippet, "\n");
        if ($newlinePos !== false) {
            $snippet = substr($snippet, 0, $newlinePos);
        }

        $trimmed = ltrim($snippet);
        if ($trimmed === '') {
            return false;
        }

        $delimiterPos = strpos($trimmed, $delimiter);
        if ($delimiterPos === false) {
            return false;
        }

        if ($trimmed[0] === '"') {
            $len = strlen($trimmed);
            $inQuotes = true;
            for ($i = 1; $i < $len; $i++) {
                $char = $trimmed[$i];
                if ($char === '"') {
                    if ($i + 1 < $len && $trimmed[$i + 1] === '"') {
                        $i++;
                        continue;
                    }
                    $inQuotes = !$inQuotes;
                    if (!$inQuotes) {
                        $remaining = substr($trimmed, $i + 1);
                        $remaining = ltrim($remaining);
                        if ($remaining === '') {
                            return true;
                        }
                        return isset($remaining[0]) && $remaining[0] === $delimiter;
                    }
                }
            }
            return false;
        }

        $firstField = substr($trimmed, 0, $delimiterPos);
        if (substr_count($firstField, '"') % 2 !== 0) {
            return false;
        }

        return true;
    }

    private static function lookahead_matches_first_column_pattern($snippet, $pattern, $delimiter) {
        if ($pattern === null) {
            return true;
        }

        $snippet = ltrim($snippet, "\r\n");
        if ($snippet === '') {
            return false;
        }

        $newlinePos = strpos($snippet, "\n");
        if ($newlinePos !== false) {
            $snippet = substr($snippet, 0, $newlinePos);
        }

        $trimmed = ltrim($snippet);
        if ($trimmed === '') {
            return false;
        }

        if ($pattern === 'numeric') {
            $delimPos = strpos($trimmed, $delimiter);
            if ($delimPos !== false) {
                $trimmed = substr($trimmed, 0, $delimPos);
            }
            return self::value_is_numeric_like($trimmed);
        }

        return true;
    }

    private static function lookahead_matches_expected_columns($snippet, $delimiter, $expectedColumns) {
        if ($expectedColumns === null) {
            return true;
        }

        $snippet = ltrim($snippet, "\r\n");
        if ($snippet === '') {
            return false;
        }

        $newlinePos = strpos($snippet, "\n");
        if ($newlinePos !== false) {
            $snippet = substr($snippet, 0, $newlinePos);
        }

        if ($snippet === '') {
            return false;
        }

        $len = strlen($snippet);
        $inQuotes = false;
        $fieldCount = 1;

        for ($i = 0; $i < $len; $i++) {
            $char = $snippet[$i];
            if ($char === '"') {
                if ($inQuotes && $i + 1 < $len && $snippet[$i + 1] === '"') {
                    $i++;
                    continue;
                }
                $inQuotes = !$inQuotes;
                continue;
            }

            if ($char === $delimiter && !$inQuotes) {
                $fieldCount++;
                if ($fieldCount > $expectedColumns) {
                    return false;
                }
            }
        }

        return $fieldCount === $expectedColumns;
    }

    private static function build_parse_error($line, $raw, $reason) {
        $sample = $raw;
        if (function_exists('mb_substr')) {
            $sample = mb_substr($raw, 0, 160);
        } else {
            $sample = substr($raw, 0, 160);
        }

        return [
            'line'   => $line,
            'reason' => $reason,
            'sample' => trim($sample),
        ];
    }

    private static function update_first_column_pattern(&$pattern, $value) {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        if ($pattern === 'numeric') {
            if (!self::value_is_numeric_like($trimmed)) {
                $pattern = 'mixed';
            }
            return;
        }

        if ($pattern === null) {
            if (self::value_is_numeric_like($trimmed)) {
                $pattern = 'numeric';
            } else {
                $pattern = 'mixed';
            }
            return;
        }

        if ($pattern === 'mixed') {
            return;
        }
    }

    private static function value_is_numeric_like($value) {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (function_exists('mb_convert_kana')) {
            $value = mb_convert_kana($value, 'n', 'UTF-8');
        }
        return preg_match('/^-?\d+$/', $value) === 1;
    }
}
