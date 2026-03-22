<?php

namespace App\Exports\Tasks;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OverdueAlertExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private int $schoolId, private ?int $classId = null) {}

    public function collection()
    {
        return Invoice::where('school_id', $this->schoolId)
            ->overdue()
            ->with(['student.guardians', 'academicYear'])
            ->when($this->classId, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $this->classId)))
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
            'Jours de retard',
            'Solde dû (DJF)',
            'Pénalité (DJF)',
            'Contact tuteur',
            'Email tuteur',
        ];
    }

    public function map($invoice): array
    {
        $daysLate = $invoice->due_date ? (int) $invoice->due_date->diffInDays(now()) : 0;
        $guardian = $invoice->student?->guardians->first();

        return [
            $invoice->reference,
            $invoice->student?->full_name ?? '—',
            $invoice->academicYear?->name ?? '—',
            $invoice->due_date?->format('d/m/Y'),
            $daysLate,
            number_format((float) $invoice->balance_due, 0, ',', ' '),
            number_format((float) ($invoice->penalty_amount ?? 0), 0, ',', ' '),
            $guardian?->full_name ?? '—',
            $guardian?->email ?? '—',
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
        return 'Alertes retards';
    }
}
