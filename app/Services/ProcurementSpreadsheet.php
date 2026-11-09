<?php

namespace App\Services;

use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class ProcurementSpreadsheet
{
    private const HEADERS = [
        '项目编号', '项目名称', '提交时间', '申请部门', '归口部门', '采购方式',
        '组织形式', '经费列支', '采购类型', '项目状态', '预算金额（元）', '实际金额（元）',
    ];

    /** @param Collection<int, ProcurementStatisticRecord> $records */
    public function write(Collection $records): string
    {
        return $this->writeTable(self::HEADERS, $records->map(fn ($record) => [
            $record->project_number, $record->title, $record->submitted_at,
            $record->department_name, $record->responsible_department,
            $record->purchase_method, $record->organization_form, $record->funding_source,
            $record->purchase_type, $record->status,
            $record->budget_cents / 100,
            $record->actual_amount_cents === null ? null : $record->actual_amount_cents / 100,
        ]));
    }

    /** @param list<string> $headers
     * @param  Collection<int, list<string|int|float|null>>  $rows
     */
    public function writeTable(array $headers, Collection $rows, string $sheetName = '采购统计'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'procurement-statistics-');
        if ($path === false) {
            throw new RuntimeException('无法创建统计导出文件');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建 Excel 文件');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.chr(64 + count($headers)).($rows->count() + 1).'"/><sheetData>';
        $sheet .= $this->row(1, $headers);
        foreach ($rows->values() as $index => $values) {
            $sheet .= $this->row($index + 2, $values);
        }
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    /** @param list<string|int|float|null> $values */
    private function row(int $number, array $values): string
    {
        $xml = '<row r="'.$number.'">';
        foreach ($values as $index => $value) {
            $address = chr(65 + $index).$number;
            if ($value === null) {
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$address.'"><v>'.$value.'</v></c>';
            } else {
                $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
                $escaped = htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="'.$address.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }
}
