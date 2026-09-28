<?php

namespace App\Services;

/**
 * Universal Attendance XLS/Export File Parser.
 *
 * Biometric attendance machines (ZKTeco, Dahua, Hikvision, Anviz, etc.) export
 * files with .xls extension in various underlying formats:
 *  1. HTML table saved as .xls (extremely common)
 *  2. XML Spreadsheet 2003 (Workbook / Worksheet)
 *  3. BIFF2 binary format (standalone machine export)
 *  4. Standard XLSX file renamed to .xls (ZIP container)
 *  5. Delimited text (CSV / TSV) saved as .xls
 *  6. Standard Excel 97-2003 BIFF8
 *
 * This parser detects the underlying format and parses records reliably.
 */
class AttendanceXlsParser
{
    private array $rawGrid = [];
    private array $headers = [];

    /**
     * Parse an XLS export file and return structured records.
     *
     * @param  string $filePath
     * @return array  Array of associative arrays
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return [];
        }

        $data = file_get_contents($filePath);
        $head = substr($data, 0, 1024);

        // 1. Check if it's an XLSX file renamed to .xls (starts with 'PK')
        if (substr($head, 0, 2) === "PK") {
            return $this->parseWithOpenSpout($filePath);
        }

        // 2. Check if it's an HTML table saved as .xls
        if (stripos($head, '<html') !== false || stripos($head, '<table') !== false || stripos($head, 'xmlns:') !== false) {
            $htmlRecords = $this->parseHtmlTable($data);
            if (!empty($htmlRecords)) {
                return $htmlRecords;
            }
        }

        // 3. Check if it's XML Spreadsheet 2003
        if (stripos($head, 'urn:schemas-microsoft-com:office:spreadsheet') !== false || stripos($head, '<Workbook') !== false) {
            $xmlRecords = $this->parseXmlSpreadsheet($data);
            if (!empty($xmlRecords)) {
                return $xmlRecords;
            }
        }

        // 4. Check if it's CSV / TSV text saved as .xls
        if (!preg_match('/[\x00-\x08\x0E-\x1F]/', substr($head, 0, 200))) {
            $textRecords = $this->parseDelimitedText($filePath);
            if (!empty($textRecords)) {
                return $textRecords;
            }
        }

        // 5. Try BIFF2 binary parser
        $this->rawGrid = [];
        $this->headers = [];
        $this->readBiff($filePath);

        if (!empty($this->rawGrid)) {
            ksort($this->rawGrid);
            $rowKeys = array_keys($this->rawGrid);
            $headerRowIdx = $rowKeys[0];
            ksort($this->rawGrid[$headerRowIdx]);
            foreach ($this->rawGrid[$headerRowIdx] as $col => $val) {
                $this->headers[$col] = trim((string)$val);
            }

            $records = [];
            for ($i = 1; $i < count($rowKeys); $i++) {
                $rowIdx = $rowKeys[$i];
                ksort($this->rawGrid[$rowIdx]);
                $record = [];
                foreach ($this->headers as $col => $name) {
                    $record[$name] = $this->rawGrid[$rowIdx][$col] ?? null;
                }
                $records[] = $record;
            }
            return $records;
        }

        // 6. Last fallback: Try OpenSpout anyway
        return $this->parseWithOpenSpout($filePath);
    }

    /**
     * Parse HTML table often produced by biometric software.
     */
    private function parseHtmlTable(string $html): array
    {
        $records = [];

        // Suppress HTML parsing warnings for non-standard HTML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            return [];
        }

        $table = $tables->item(0);
        $rows = $table->getElementsByTagName('tr');
        $headers = [];

        foreach ($rows as $rIdx => $tr) {
            $cells = [];
            foreach ($tr->childNodes as $node) {
                if ($node->nodeName === 'td' || $node->nodeName === 'th') {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $node->textContent));
                }
            }

            if (empty($cells) || empty(array_filter($cells))) {
                continue;
            }

            if (empty($headers)) {
                $headers = $cells;
                continue;
            }

            $record = [];
            foreach ($headers as $cIdx => $hName) {
                $record[$hName] = $cells[$cIdx] ?? null;
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Parse XML Spreadsheet 2003 format.
     */
    private function parseXmlSpreadsheet(string $xmlContent): array
    {
        $records = [];
        try {
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                return [];
            }

            $namespaces = $xml->getNamespaces(true);
            $ss = $namespaces['ss'] ?? 'urn:schemas-microsoft-com:office:spreadsheet';
            $xml->registerXPathNamespace('ss', $ss);

            $rows = $xml->xpath('//ss:Row');
            $headers = [];

            foreach ($rows as $row) {
                $cells = [];
                foreach ($row->xpath('ss:Cell') as $cell) {
                    $data = $cell->xpath('ss:Data');
                    $cells[] = isset($data[0]) ? trim((string)$data[0]) : '';
                }

                if (empty($cells) || empty(array_filter($cells))) {
                    continue;
                }

                if (empty($headers)) {
                    $headers = $cells;
                    continue;
                }

                $record = [];
                foreach ($headers as $cIdx => $hName) {
                    $record[$hName] = $cells[$cIdx] ?? null;
                }
                $records[] = $record;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $records;
    }

    /**
     * Parse plain delimited text (CSV/TSV) disguised as XLS.
     */
    private function parseDelimitedText(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return [];

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $records = [];
        $headers = null;

        while (($row = fgetcsv($handle, 2000, $delimiter)) !== false) {
            if ($headers === null) {
                $headers = array_map(function ($h) {
                    return trim(str_replace("\xEF\xBB\xBF", '', (string)$h));
                }, $row);
                continue;
            }

            if (empty(array_filter($row))) {
                continue;
            }

            if (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            } elseif (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            }

            $records[] = array_combine($headers, $row);
        }

        fclose($handle);
        return $records;
    }

    /**
     * Parse using OpenSpout.
     */
    private function parseWithOpenSpout(string $filePath): array
    {
        $rows = [];
        $headers = null;

        try {
            $reader = \OpenSpout\Reader\XLSX\Reader::create();
            $reader->open($filePath);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $values = [];
                    foreach ($cells as $cell) {
                        $val = $cell->getValue();
                        if ($val instanceof \DateTimeInterface) {
                            $values[] = $val->format('Y-m-d H:i:s');
                        } elseif (is_numeric($val) && (float)$val > 30000 && (float)$val < 65000) {
                            $values[] = (string)$val;
                        } else {
                            $values[] = is_scalar($val) ? (string)$val : '';
                        }
                    }

                    if ($headers === null) {
                        $headers = array_map(function ($v) {
                            return trim(str_replace("\xEF\xBB\xBF", '', (string)$v));
                        }, $values);
                        continue;
                    }

                    if (empty(array_filter($values))) {
                        continue;
                    }

                    if (count($values) > count($headers)) {
                        $values = array_slice($values, 0, count($headers));
                    } elseif (count($values) < count($headers)) {
                        $values = array_pad($values, count($headers), null);
                    }

                    $rows[] = array_combine($headers, $values);
                }
                break;
            }

            $reader->close();
        } catch (\Throwable $e) {
            return [];
        }

        return $rows;
    }

    /**
     * Parse raw BIFF2 binary file.
     */
    private function readBiff(string $filePath): void
    {
        $data = file_get_contents($filePath);
        $len  = strlen($data);
        $pos  = 0;

        while ($pos + 4 <= $len) {
            $recType = ord($data[$pos])     | (ord($data[$pos + 1]) << 8);
            $recLen  = ord($data[$pos + 2]) | (ord($data[$pos + 3]) << 8);
            $recData = substr($data, $pos + 4, $recLen);
            $pos    += 4 + $recLen;

            if ($pos > $len + 1) {
                break;
            }

            switch ($recType) {
                case 0x0004: // BIFF2 LABEL
                    $this->parseBiff2Label($recData);
                    break;
                case 0x0002: // BIFF2 INTEGER
                    $this->parseBiff2Integer($recData);
                    break;
                case 0x0003: // BIFF2 NUMBER
                    $this->parseBiff2Number($recData);
                    break;
            }
        }
    }

    private function parseBiff2Label(string $rec): void
    {
        if (strlen($rec) < 8) return;
        $row    = ord($rec[0]) | (ord($rec[1]) << 8);
        $col    = ord($rec[2]) | (ord($rec[3]) << 8);
        $strLen = ord($rec[7]);
        $str    = substr($rec, 8, $strLen);
        $str    = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $str);
        $this->rawGrid[$row][$col] = trim($str);
    }

    private function parseBiff2Integer(string $rec): void
    {
        if (strlen($rec) < 9) return;
        $row = ord($rec[0]) | (ord($rec[1]) << 8);
        $col = ord($rec[2]) | (ord($rec[3]) << 8);
        $val = ord($rec[7]) | (ord($rec[8]) << 8);
        $this->rawGrid[$row][$col] = $val;
    }

    private function parseBiff2Number(string $rec): void
    {
        if (strlen($rec) < 15) return;
        $row    = ord($rec[0]) | (ord($rec[1]) << 8);
        $col    = ord($rec[2]) | (ord($rec[3]) << 8);
        $double = unpack('d', substr($rec, 7, 8));
        $this->rawGrid[$row][$col] = $double[1] ?? null;
    }
}
