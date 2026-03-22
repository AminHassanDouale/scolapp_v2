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

class InvoiceReminderExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private int $schoolId, private ?int $classId = null, private ?int $gradeId = null) {}

    public function collection()
    {
        return Invoice::where('school_id', $this->schoolId)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->where('balance_due', '>', 0)
            ->with(['student.guardians', 'academicYear'])
            ->when($this->classId, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $this->classId)))
            ->when($this->gradeId,  fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('grade_id', $this->gradeId)))
            ->orderBy('due_date')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Élève',
            'Année scolaire',
            'Émise le',
            'Échéance',
            'Total (DJF)',
            'Payé (DJF)',
            'Solde (DJF)',
            'Statut',
            'Contact tuteur',
        ];
    }

    public function map($invoice): array
    {
        $guardian = $invoice->student?->guardians->first();

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
        return 'Rappels de paiement';
    }
}
