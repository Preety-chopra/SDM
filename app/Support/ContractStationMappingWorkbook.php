<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

final class ContractStationMappingWorkbook
{
    private const HEADERS = ['S.no.', 'Line Code', 'Station Code', 'TVS Agnecy', 'CFA Agency', 'W&W Agency', 'Marshal Agency'];

    public function write(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'station-mapping-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the station mapping workbook.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the station mapping workbook.');
            }

            try {
                foreach ($this->parts($rows) as $name => $xml) {
                    if (! $zip->addFromString($name, $xml)) {
                        throw new RuntimeException('Unable to write the station mapping workbook.');
                    }
                }
            } finally {
                $closed = $zip->close();
            }

            $content = $closed ? file_get_contents($path) : false;
            if ($content === false) {
                throw new RuntimeException('Unable to read the station mapping workbook.');
            }

            return $content;
        } finally {
            unlink($path);
        }
    }

    private function parts(array $rows): array
    {
        return [
            '[Content_Types].xml' => <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
                    <Default Extension="xml" ContentType="application/xml"/>
                    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
                    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
                    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
                </Types>
                XML,
            '_rels/.rels' => <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
                </Relationships>
                XML,
            'xl/workbook.xml' => <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                    <sheets><sheet name="Station contract mapping" sheetId="1" r:id="rId1"/></sheets>
                </workbook>
                XML,
            'xl/_rels/workbook.xml.rels' => <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
                    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
                </Relationships>
                XML,
            'xl/styles.xml' => <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
                    <fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>
                    <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF047857"/><bgColor indexed="64"/></patternFill></fill></fills>
                    <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
                    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
                    <cellXfs count="3">
                        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
                        <xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
                        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>
                    </cellXfs>
                    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
                </styleSheet>
                XML,
            'xl/worksheets/sheet1.xml' => $this->worksheet($rows),
        ];
    }

    private function worksheet(array $rows): string
    {
        $lastRow = count($rows) + 1;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:G'.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="9" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="7" width="40" customWidth="1"/></cols><sheetData>';

        foreach ([self::HEADERS, ...$rows] as $index => $row) {
            $rowNumber = $index + 1;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach ($row as $column => $value) {
                $reference = chr(65 + $column).$rowNumber;
                $style = $index === 0 ? 2 : (is_int($value) ? 0 : 1);
                $xml .= is_int($value)
                    ? '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>'
                    : '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                        .htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData><autoFilter ref="A1:G'.$lastRow.'"/></worksheet>';
    }
}
