<?php

namespace App\Exports\Tasks\Sheets;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\School;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceSummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private School $school;
    private array  $stats;

    public function __construct(private int $schoolId, private ?int $classId = null)
    {
        $this->school = School::find($schoolId);
        $this->buildStats();
    }

    private function buildStats(): void
    {
        $sessionIds = AttendanceSession::where('school_id', $this->schoolId)
            ->whereBetween('session_date', [now()->subWeek()->toDateString(), now()->toDateString()])
            ->when($this->classId, fn($q) => $q->where('school_class_id', $this->classId))
            ->pluck('id');

        $total   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->count();
        $present = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::PRESENT->value)->count();
        $absent  = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::ABSENT->value)->count();
        $late    = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::LATE->value)->count();
        $excused = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
            ->where('status', AttendanceStatus::EXCUSED->value)->count();
        $rate    = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        $this->stats = compact('total', 'present', 'absent', 'late', 'excused', 'rate');
    }

    public function array(): array
    {
        $s       = $this->stats;
        $period  = now()->subWeek()->format('d/m/Y') . ' au ' . now()->format('d/m/Y');

        return [
            [$this->school->name, ''],
            ['Résumé des Présences', ''],
            ['Semaine du ' . $period, ''],
            ['Généré le ' . now()->format('d/m/Y à H:i'), ''],
            ['', ''],
            ['INDICATEUR', 'VALEUR'],
            ['Total entrées de présence', $s['total']],
            ['Présents', $s['present']],
            ['Absents', $s['absent']],
            ['En retard', $s['late']],
            ['Excusés', $s['excused']],
            ['', ''],
            ['Taux de présence', $s['rate'] . ' %'],
            ['Taux d\'absentéisme', $s['total'] > 0 ? round(($s['absent'] / $s['total']) * 100, 1) . ' %' : '0 %'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:B2');
        $sheet->mergeCells('A3:B3');
        $sheet->mergeCells('A4:B4');

        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font'      => ['italic' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            6  => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '10B981']]],
            7  => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'ECFDF5']]],
            8  => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D1FAE5']]],
            9  => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEE2E2']]],
            10 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FEF3C7']]],
            11 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EFF6FF']]],
            13 => ['font' => ['bold' => true]],
            14 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Résumé semaine';
    }
}
