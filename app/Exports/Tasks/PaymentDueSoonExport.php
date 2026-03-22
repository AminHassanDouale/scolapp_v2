<?php

namespace App\Exports\Tasks;

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

class PaymentDueSoonExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private int $schoolId, private int $daysBefore = 7) {}

    public function collection()
    {
        return Invoice::where('school_id', $this->schoolId)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($this->daysBefore)->toDateString()])
            ->with(['student.guardians', 'academicYear'])
            ->orderBy('due_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Élève',
            'Année scolaire',
            'Date échéance',
            'Jours restants',
            'Total (DJF)',
            'Payé (DJF)',
            'Solde (DJF)',
            'Contact tuteur',
        ];
    }

    public function map($invoice): array
    {
        $daysLeft = $invoice->due_date ? (int) now()->diffInDays($invoice->due_date) : 0;
        $guardian = $invoice->student?->guardians->first();

        return [
            $invoice->reference,
            $invoice->student?->full_name ?? '—',
            $invoice->academicYear?->name ?? '—',
            $invoice->due_date?->format('d/m/Y'),
            $daysLeft,
            number_format((float) $invoice->total, 0, ',', ' '),
            number_format((float) $invoice->paid_total, 0, ',', ' '),
            number_format((float) $invoice->balance_due, 0, ',', ' '),
            $guardian ? ($guardian->full_name . ($guardian->phone ? ' — ' . $guardian->phone : '')) : '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D97706']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Échéances proches';
    }
}
