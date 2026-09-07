<?php

namespace App\Services;

/**
 * Attendance machine XLS (BIFF2/BIFF3) file parser.
 *
 * Biometric attendance machines (e.g., Dahua, Hikvision, ZKTeco standalone export)
 * often export XLS files in the old BIFF2 format, which is NOT an OLE compound file
 * and therefore cannot be read by standard libraries like PhpSpreadsheet.
 *
 * This class parses the raw BIFF2 binary format to extract attendance records.
 *
 * BIFF2 Record structure:
 *   - 2 bytes: record type (little-endian)
 *   - 2 bytes: record data length (little-endian)
 *   - N bytes: record data
 *
 * BIFF2 LABEL record (type 0x0004):
 *   Data: row(2) + col(2) + cell_attrs(3) + string_len(1) + string_data(N)
 *
 * BIFF2 NUMBER record (type 0x0003):
 *   Data: row(2) + col(2) + cell_attrs(3) + ieee754_double(8)
 *
 * BIFF2 INTEGER record (type 0x0002):
 *   Data: row(2) + col(2) + cell_attrs(3) + integer(2)
 */
class AttendanceXlsParser
{
    /** @var array Parsed raw grid data [rowIndex][colIndex] = value */
    private array $rawGrid = [];

    /** @var array Column headers mapped col index => header name */
    private array $headers = [];

    /**
     * Parse a BIFF2 XLS file and return structured attendance records.
     *
     * @param  string $filePath  Absolute path to the .xls file
     * @return array  Array of associative arrays, one per row (excluding header)
     */
    public function parse(string $filePath): array
    {
        $this->rawGrid = [];
        $this->headers = [];

        $this->readBiff($filePath);

        if (empty($this->rawGrid)) {
            return [];
        }

        // Sort rows by row index
        ksort($this->rawGrid);
        $rowKeys = array_keys($this->rawGrid);

        // First row = headers
        $headerRowIdx = $rowKeys[0];
        ksort($this->rawGrid[$headerRowIdx]);
        foreach ($this->rawGrid[$headerRowIdx] as $col => $val) {
            $this->headers[$col] = trim($val);
        }

        // Remaining rows = data
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

    /**
     * Parse a BIFF2 file into the raw grid.
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

    /**
     * BIFF2 LABEL record: row(2) col(2) attrs(3) str_len(1) str_data(N)
     */
    private function parseBiff2Label(string $rec): void
    {
        if (strlen($rec) < 8) {
            return;
        }

        $row    = ord($rec[0]) | (ord($rec[1]) << 8);
        $col    = ord($rec[2]) | (ord($rec[3]) << 8);
        $strLen = ord($rec[7]);
        $str    = substr($rec, 8, $strLen);

        // Strip control characters but keep tab, newline, carriage return
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $str);
        $str = trim($str);

        $this->rawGrid[$row][$col] = $str;
    }

    /**
     * BIFF2 INTEGER record: row(2) col(2) attrs(3) integer(2)
     */
    private function parseBiff2Integer(string $rec): void
    {
        if (strlen($rec) < 9) {
            return;
        }

        $row = ord($rec[0]) | (ord($rec[1]) << 8);
        $col = ord($rec[2]) | (ord($rec[3]) << 8);
        $val = ord($rec[7]) | (ord($rec[8]) << 8);

        $this->rawGrid[$row][$col] = $val;
    }

    /**
     * BIFF2 NUMBER record: row(2) col(2) attrs(3) double(8)
     */
    private function parseBiff2Number(string $rec): void
    {
        if (strlen($rec) < 15) {
            return;
        }

        $row    = ord($rec[0]) | (ord($rec[1]) << 8);
        $col    = ord($rec[2]) | (ord($rec[3]) << 8);
        $double = unpack('d', substr($rec, 7, 8));

        $this->rawGrid[$row][$col] = $double[1] ?? null;
    }
}
