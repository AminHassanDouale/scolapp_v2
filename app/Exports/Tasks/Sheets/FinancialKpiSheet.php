<?php

namespace App\Exports\Tasks\Sheets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialKpiSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private School $school;
    private array  $stats;

    public function __construct(private int $schoolId)
    {
        $this->school = School::find($schoolId);
        $this->buildStats();
    }

    private function buildStats(): void
    {
        $revenue = Payment::where('school_id', $this->schoolId)
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->sum('amount');

        $pending = Invoice::where('school_id', $this->schoolId)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->where('balance_due', '>', 0)
            ->sum('balance_due');

        $overdue = Invoice::where('school_id', $this->schoolId)
            ->overdue()
            ->sum('balance_due');

        $totalInvoices = Invoice::where('school_id', $this->schoolId)->count();
        $paidInvoices  = Invoice::where('school_id', $this->schoolId)
            ->where('status', InvoiceStatus::PAID->value)->count();
        $overdueCount  = Invoice::where('school_id', $this->schoolId)->overdue()->count();

        $this->stats = compact('revenue', 'pending', 'overdue', 'totalInvoices', 'paidInvoices', 'overdueCount');
    }

    public function array(): array
    {
        $s = $this->stats;
        return [
            [$this->school->name, '', ''],
            ['Résumé Financier — ' . now()->translatedFormat('F Y'), '', ''],
            ['Généré le ' . now()->format('d/m/Y à H:i'), '', ''],
            ['', '', ''],
            ['INDICATEUR', 'VALEUR (DJF)', 'NOTES'],
            ['Revenus collectés (mois en cours)', number_format((int) $s['revenue'], 0, ',', ' '), 'Paiements confirmés'],
            ['Montant en attente', number_format((int) $s['pending'], 0, ',', ' '), 'Factures non réglées'],
            ['Montant en retard', number_format((int) $s['overdue'], 0, ',', ' '), 'Échéance dépassée'],
            ['', '', ''],
            ['STATISTIQUES FACTURES', 'NOMBRE', ''],
            ['Total factures', $s['totalInvoices'], ''],
            ['Factures payées', $s['paidInvoices'], ''],
            ['Factures en retard', $s['overdueCount'], ''],
            ['Taux de recouvrement',
                $s['totalInvoices'] > 0
                    ? round(($s['paidInvoices'] / $s['totalInvoices']) * 100, 1) . ' %'
                    : '0 %',
                '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // School name header
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');
        $sheet->mergeCells('A3:C3');

        // KPI section header
        $sheet->mergeCells('A10:C10');

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3B82F6']],
            ],
            6 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DBEAFE']]],
            7 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEF9C3']]],
            8 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEE2E2']]],
            10 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3B82F6']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Résumé KPIs';
    }
}
