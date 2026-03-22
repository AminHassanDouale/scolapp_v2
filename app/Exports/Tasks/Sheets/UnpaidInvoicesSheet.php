<?php

namespace App\Exports\Tasks\Sheets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UnpaidInvoicesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private int $schoolId) {}

    public function collection()
    {
        return Invoice::where('school_id', $this->schoolId)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->where('balance_due', '>', 0)
            ->with(['student', 'academicYear'])
            ->orderBy('due_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Élève',
            'Année scolaire',
            'Date émission',
            'Date échéance',
            'Montant total (DJF)',
            'Payé (DJF)',
            'Solde dû (DJF)',
            'Statut',
            'En retard',
            'Jours de retard',
        ];
    }

    public function map($invoice): array
    {
        $daysLate = $invoice->due_date && $invoice->due_date->isPast()
            ? (int) $invoice->due_date->diffInDays(now())
            : 0;

        return [
            $invoice->reference,
            $invoice->student?->full_name ?? '—',
            $invoice->academicYear?->name ?? '—',
            $invoice->issue_date?->format('d/m/Y'),
            $invoice->due_date?->format('d/m/Y'),
            number_format((float) $invoice->total, 0, ',', ' '),
            number_format((float) $invoice->paid_total, 0, ',', ' '),
            number_format((float) $invoice->balance_due, 0, ',', ' '),
            $invoice->status->name,
            $invoice->is_overdue ? 'OUI' : 'NON',
            $daysLate > 0 ? $daysLate : '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DC2626']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Factures impayées';
    }
}
