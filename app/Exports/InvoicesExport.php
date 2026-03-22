<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class InvoicesExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle,
    ShouldAutoSize
{
    public function __construct(private Collection $invoices) {}

    public function title(): string
    {
        return 'Factures';
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Élève',
            'Code élève',
            'Classe',
            'Type',
            'Statut',
            'Date émission',
            'Échéance',
            'Montant HT (DJF)',
            'TVA (DJF)',
            'Pénalités (DJF)',
            'Total (DJF)',
            'Payé (DJF)',
            'Solde dû (DJF)',
            'N° versement',
        ];
    }

    public function collection(): Collection
    {
        return $this->invoices->map(fn (Invoice $inv) => [
            $inv->reference,
            $inv->student?->full_name,
            $inv->student?->student_code,
            $inv->enrollment?->schoolClass?->name,
            $inv->invoice_type?->label() ?? $inv->invoice_type,
            $inv->status?->label()       ?? $inv->status,
            $inv->issue_date?->format('d/m/Y'),
            $inv->due_date?->format('d/m/Y'),
            (int) $inv->subtotal,
            (int) $inv->vat_amount,
            (int) $inv->penalty_amount,
            (int) $inv->total,
            (int) $inv->paid_total,
            (int) $inv->balance_due,
            $inv->installment_number,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->invoices->count() + 1;

        // Header row styling
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1B4D8E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);

        // Alternating row colors
        for ($row = 2; $row <= $lastRow; $row++) {
            $color = $row % 2 === 0 ? 'FFF0F4FF' : 'FFFFFFFF';
            $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
            ]);
        }

        // Money columns right-align (I–N = cols 9–14)
        $sheet->getStyle("I2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Balance due column — red if > 0
        for ($row = 2; $row <= $lastRow; $row++) {
            $balance = $sheet->getCell("N{$row}")->getValue();
            if ($balance > 0) {
                $sheet->getStyle("N{$row}")->getFont()->setColor(new Color('FFDC2626'));
                $sheet->getStyle("N{$row}")->getFont()->setBold(true);
            }
        }

        // Outer border on data range
        $sheet->getStyle("A1:O{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFD1D5DB'],
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['argb' => 'FF1B4D8E'],
                ],
            ],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // Référence
            'B' => 28,  // Élève
            'C' => 14,  // Code
            'D' => 16,  // Classe
            'E' => 14,  // Type
            'F' => 16,  // Statut
            'G' => 14,  // Date émission
            'H' => 12,  // Échéance
            'I' => 16,  // HT
            'J' => 12,  // TVA
            'K' => 14,  // Pénalités
            'L' => 16,  // Total
            'M' => 16,  // Payé
            'N' => 16,  // Solde
            'O' => 12,  // N° versement
        ];
    }
}
