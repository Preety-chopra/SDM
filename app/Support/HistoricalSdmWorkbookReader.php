<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class HistoricalSdmWorkbookReader
{
    public function read(string $path, string $sheetName): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 10 * 1024 * 1024) {
            $this->fail('Specify a readable XLSX file no larger than 10 MB.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('Specify a valid XLSX workbook.');
        }

        try {
            $book = $this->xml($zip, 'xl/workbook.xml');
            $properties = $book->xpath('//*[local-name()="workbookPr"]');
            if (in_array((string) ($properties[0]['date1904'] ?? ''), ['1', 'true'], true)) {
                $this->fail('The historical import requires the Excel 1900 date system.');
            }
            $sheetId = null;
            foreach ($book->xpath('//*[local-name()="sheet"]') as $sheet) {
                if ((string) $sheet['name'] === $sheetName) {
                    $sheetId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                }
            }
            if ($sheetId === null) {
                $this->fail('The selected historical worksheet was not found.');
            }
            $sheetPath = '';
            foreach ($this->xml($zip, 'xl/_rels/workbook.xml.rels')->children() as $relationship) {
                if ((string) $relationship['Id'] === $sheetId && (string) $relationship['TargetMode'] !== 'External') {
                    $target = (string) $relationship['Target'];
                    $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                }
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->children() as $item) {
                    $strings[] = $this->cellText($item);
                }
            }
            $rows = $this->xml($zip, $sheetPath)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
            if (count($rows) > 25001) {
                $this->fail('Import at most 25,000 worksheet rows at a time.');
            }
            $records = [];
            $headerChecked = false;
            $hasRepeatedAadhaarColumn = false;
            foreach ($rows as $row) {
                $values = array_fill(0, 17, '');
                foreach ($row->children() as $cell) {
                    if (! preg_match('/^([A-Q])[0-9]+$/D', (string) $cell['r'], $matches)) {
                        continue;
                    }
                    $values[ord($matches[1]) - 65] = trim(match ((string) $cell['t']) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->cellText($cell),
                        default => (string) $cell->v,
                    });
                }
                if (! $headerChecked) {
                    $hasRepeatedAadhaarColumn = mb_strtoupper($values[3]) === 'AADHAR NUMBER'
                        && mb_strtoupper($values[9]) === 'AADHAR NUMBER';
                }
                if ($hasRepeatedAadhaarColumn) {
                    // NCES repeats Aadhaar in D. Keep the copy beside File No so the remaining fields align with JMD.
                    array_splice($values, 3, 1);
                } else {
                    array_pop($values);
                }
                // Ignore formatting-only rows and formulas outside the historical data columns.
                if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                    continue;
                }
                if (! $headerChecked) {
                    $this->validateHeaders($values);
                    $headerChecked = true;

                    continue;
                }
                $records[] = ['number' => (int) $row['r'], 'values' => $values];
            }
            if ($records === []) {
                $this->fail('The worksheet contains no historical SDMs.');
            }

            return $records;
        } finally {
            $zip->close();
        }
    }

    private function validateHeaders(array $values): void
    {
        $expected = [2 => ['DATE'], 3 => ['MEMO NO', 'MEMO NO/CHECKED FROM'], 4 => ['LINE'], 5 => ['STATION'], 6 => ['ON DUTY STAFF', 'EMPLOYEE NAME'], 7 => ['FILE NO'], 8 => ['AADHAR NUMBER'], 9 => ['BRIEF DETAIL OF VIOLATION', 'DISCREPANCIES'], 10 => ['RECOMMENDED ACTIONS', 'RECOMMENDED ACTION'], 13 => ['C&P NO', 'C&P'], 15 => ['NEGATIVE POINTS']];
        foreach ($expected as $column => $names) {
            if (! in_array(mb_strtoupper($values[$column]), $names, true)) {
                $this->fail('The historical worksheet headings do not match the expected columns.');
            }
        }
        if (! str_starts_with(mb_strtoupper($values[11]), 'PENALTY ON ')) {
            $this->fail('The contractor penalty heading is missing.');
        }
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > 30 * 1024 * 1024) {
            $this->fail('The workbook is incomplete or its worksheet is too large.');
        }
        $content = $zip->getFromName($name);
        if ($content === false || stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            $this->fail('The workbook contains unsupported XML content.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                $this->fail('The workbook contains invalid XML.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function cellText(SimpleXMLElement $element): string
    {
        return implode('', array_map(fn (SimpleXMLElement $text): string => (string) $text, $element->xpath('.//*[local-name()="t"]')));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
