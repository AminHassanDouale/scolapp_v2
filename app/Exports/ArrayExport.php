<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lightweight, reusable spreadsheet export for any tabular data.
 * Pass a list of heading strings and a list of row arrays.
 */
class ArrayExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $headings,
        private array $rows,
        private string $sheetTitle = 'Export',
    ) {}

    public function title(): string { return $this->sheetTitle; }

    public function headings(): array { return $this->headings; }

    public function array(): array { return $this->rows; }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E7490']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        return [];
    }
}
