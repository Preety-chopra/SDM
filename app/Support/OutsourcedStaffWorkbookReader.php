<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

final class OutsourcedStaffWorkbookReader
{
    private const HEADERS = ['EMP ID', 'NAME OF EMPLOYEE', "FATHER'S NAME OF EMPLOYEE", 'DATE', 'MONTH', 'YEAR', 'ADHAR NUMBER OF EMPLOYEE', 'CATEGORY', 'GENDER OF EMPLOYEE', 'DESIGNATION OF EMPLOYEE', 'ENTRY PERMIT CARD NUMBER', 'VERIFICATION STATUS', 'CONTRACTOR AGENCY'];

    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('Upload a valid Excel .xlsx workbook.');
        }

        try {
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $xml = $this->xml($zip, 'xl/sharedStrings.xml');
                foreach ($xml->children() as $item) {
                    $strings[] = $this->cellText($item);
                }
            }
            $sheetPath = 'xl/worksheets/sheet1.xml';
            if ($zip->locateName('xl/workbook.xml') !== false) {
                $book = $this->xml($zip, 'xl/workbook.xml');
                $sheets = $book->xpath('//*[local-name()="sheet"]');
                if (empty($sheets)) {
                    $this->fail('The workbook has no worksheets.');
                }
                $id = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
                $sheetPath = '';
                foreach ($relationships->children() as $relationship) {
                    if ((string) $relationship['Id'] === $id && (string) $relationship['TargetMode'] !== 'External') {
                        $target = (string) $relationship['Target'];
                        $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                    }
                }
            }
            $sheet = $this->xml($zip, $sheetPath);
            $rows = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
            if (count($rows) > 10001) {
                $this->fail('Import at most 10,000 staff rows at a time.');
            }
            $result = [];
            foreach ($rows as $row) {
                $values = array_fill(0, 13, '');
                foreach ($row->children() as $cell) {
                    if (! preg_match('/^([A-M])[0-9]+$/', (string) $cell['r'], $matches)) {
                        continue;
                    }
                    $value = match ((string) $cell['t']) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->cellText($cell),
                        default => (string) $cell->v,
                    };
                    $values[ord($matches[1]) - 65] = trim($value);
                }
                if (count(array_filter($values, fn (string $value): bool => $value !== '')) > 0) {
                    $result[] = ['number' => (int) $row['r'], 'values' => $values];
                }
            }
            $header = array_shift($result);
            if ($header === null || array_map('mb_strtoupper', $header['values']) !== self::HEADERS) {
                $this->fail('The first worksheet must use the 13 column headings from the outsourced staff list.');
            }
            if ($result === []) {
                $this->fail('The workbook contains no staff records.');
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > 20 * 1024 * 1024) {
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
