<?php

namespace App\Http\Controllers\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds a ready-to-fill Excel import template: a styled, frozen header row on
 * sheet 1 (the one importers read — so an untouched template imports nothing),
 * and an optional worked example on sheet 2 for reference only.
 */
trait GeneratesXlsxTemplate
{
    /** How many rows of the fillable sheet get dropdowns / date pickers. */
    private const VALIDATED_ROWS = 400;

    /**
     * @param  list<string>  $headers  column labels
     * @param  list<list<mixed>>  $example  example rows (aligned to $headers); [] to omit
     * @param  list<int>  $widths  per-column widths; falls back to 16
     * @param  array<string,list<string>>  $lists  named option lists → a "Lists" sheet
     * @param  array<string,string>  $rules  column letter → a list name, or 'date' / 'time'
     */
    protected function xlsxTemplate(
        array $headers,
        array $example,
        string $sheetTitle,
        string $filename,
        array $widths = [],
        array $lists = [],
        array $rules = [],
    ): StreamedResponse {
        $ss = new Spreadsheet;

        $list = $ss->getActiveSheet();
        $list->setTitle($sheetTitle);
        $this->writeTemplateHeader($list, $headers, $widths);

        if ($example !== []) {
            $ex = $ss->createSheet();
            $ex->setTitle('Example');
            $this->writeTemplateHeader($ex, $headers, $widths);
            $ex->fromArray($example, null, 'A2');
        }

        // The option lists ride along in the same workbook, so the dropdowns keep
        // working wherever the file is opened.
        $ranges = $lists === [] ? [] : $this->writeListsSheet($ss, $lists);
        if ($rules !== []) {
            $this->applyRules($list, $rules, $ranges);
        }

        $ss->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * One column per list on a "Lists" sheet.
     *
     * @return array<string,string> list name → absolute range, e.g. 'Lists'!$B$2:$B$9
     */
    private function writeListsSheet(Spreadsheet $ss, array $lists): array
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Lists');
        $ranges = [];
        $col = 1;

        foreach ($lists as $name => $options) {
            $options = array_values(array_filter(array_map('strval', $options), fn ($o) => trim($o) !== ''));
            $letter = Coordinate::stringFromColumnIndex($col);

            $sheet->setCellValue($letter.'1', $name);
            foreach ($options as $i => $option) {
                $sheet->setCellValue($letter.($i + 2), $option);
            }

            $sheet->getStyle($letter.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($letter.'1')->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B1F3A');
            $sheet->getColumnDimension($letter)->setWidth(26);

            if ($options !== []) {
                $ranges[$name] = "'Lists'!\$".$letter.'$2:$'.$letter.'$'.(count($options) + 1);
            }
            $col++;
        }

        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col).'1', 'Edit these lists to change the dropdowns');
        $sheet->getStyle(Coordinate::stringFromColumnIndex($col).'1')->getFont()->setItalic(true)->getColor()->setARGB('FF94A3B8');

        return $ranges;
    }

    /** Dropdowns, date pickers and time pickers on the fillable sheet. */
    private function applyRules(Worksheet $sheet, array $rules, array $ranges): void
    {
        $last = self::VALIDATED_ROWS;

        foreach ($rules as $column => $rule) {
            $range = $column.'2:'.$column.$last;
            $dv = new DataValidation;
            $dv->setAllowBlank(true);
            $dv->setShowInputMessage(true);
            $dv->setShowErrorMessage(true);
            $dv->setShowDropDown(true);
            $dv->setErrorStyle(DataValidation::STYLE_WARNING);

            if ($rule === 'date') {
                $dv->setType(DataValidation::TYPE_DATE)
                    ->setOperator(DataValidation::OPERATOR_BETWEEN)
                    ->setFormula1('DATE(2020,1,1)')
                    ->setFormula2('DATE(2100,12,31)')
                    ->setPromptTitle('Date')->setPrompt('Pick or type a date (YYYY-MM-DD).')
                    ->setErrorTitle('Not a date')->setError('Enter a real date, e.g. 2026-07-27.');
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            } elseif ($rule === 'time') {
                $dv->setType(DataValidation::TYPE_TIME)
                    ->setOperator(DataValidation::OPERATOR_BETWEEN)
                    ->setFormula1('TIME(0,0,0)')
                    ->setFormula2('TIME(23,59,0)')
                    ->setPromptTitle('Time')->setPrompt('24-hour time, e.g. 14:30.')
                    ->setErrorTitle('Not a time')->setError('Enter a 24-hour time, e.g. 06:00.');
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('hh:mm');
            } elseif (isset($ranges[$rule])) {
                $dv->setType(DataValidation::TYPE_LIST)
                    ->setFormula1($ranges[$rule])
                    ->setPromptTitle($rule)->setPrompt('Choose from the list — or edit the Lists sheet.')
                    ->setErrorTitle('Not on the list')->setError('Pick one of the options, or add yours on the Lists sheet.');
            } else {
                continue;   // unknown rule — leave the column free text
            }

            $sheet->setDataValidation($range, $dv);
        }
    }

    private function writeTemplateHeader(Worksheet $sheet, array $headers, array $widths): void
    {
        $sheet->fromArray([$headers], null, 'A1');

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B1F3A');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($widths[$i - 1] ?? 16);
        }
    }
}
