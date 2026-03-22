<?php

namespace App\Exports\Tasks\Sheets;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceByClassSheet implements FromArray, ShouldAutoSize, WithCharts, WithEvents, WithStyles, WithTitle
{
    private array $classData = [];

    public function __construct(private int $schoolId)
    {
        $this->buildData();
    }

    private function buildData(): void
    {
        $classes = SchoolClass::where('school_id', $this->schoolId)->get();

        foreach ($classes as $class) {
            $sessionIds = AttendanceSession::where('school_id', $this->schoolId)
                ->where('school_class_id', $class->id)
                ->whereBetween('session_date', [now()->subWeek()->toDateString(), now()->toDateString()])
                ->pluck('id');

            if ($sessionIds->isEmpty()) {
                continue;
            }

            $total   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->count();
            $present = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
                ->where('status', AttendanceStatus::PRESENT->value)->count();
            $absent  = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
                ->where('status', AttendanceStatus::ABSENT->value)->count();

            $this->classData[] = [
                'name'    => $class->name,
                'total'   => $total,
                'present' => $present,
                'absent'  => $absent,
                'rate'    => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            ];
        }
    }

    public function array(): array
    {
        $rows = [
            ['Présences par classe — Semaine du ' . now()->subWeek()->format('d/m/Y') . ' au ' . now()->format('d/m/Y'), '', '', '', ''],
            ['', '', '', '', ''],
            ['CLASSE', 'TOTAL', 'PRÉSENTS', 'ABSENTS', 'TAUX (%)'],
        ];

        foreach ($this->classData as $row) {
            $rows[] = [$row['name'], $row['total'], $row['present'], $row['absent'], $row['rate']];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:E1');

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '047857']],
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '10B981']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $dataCount = count($this->classData);

                if ($dataCount === 0) {
                    return;
                }

                $dataStart = 4;
                $dataEnd   = $dataStart + $dataCount - 1;
                $sheetName = $this->title();

                // Classes (X axis labels)
                $xLabels = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_STRING,
                        "'{$sheetName}'!\$A\${$dataStart}:\$A\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                // Present series
                $presentValues = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_NUMBER,
                        "'{$sheetName}'!\$C\${$dataStart}:\$C\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                // Absent series
                $absentValues = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_NUMBER,
                        "'{$sheetName}'!\$D\${$dataStart}:\$D\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                $seriesLabels = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$C\$3", null, 1),
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$D\$3", null, 1),
                ];

                $series = new DataSeries(
                    DataSeries::TYPE_BARCHART,
                    DataSeries::GROUPING_CLUSTERED,
                    range(0, 1),
                    $seriesLabels,
                    $xLabels,
                    array_merge($presentValues, $absentValues)
                );

                $plotArea = new PlotArea(null, [$series]);
                $legend   = new Legend(Legend::POSITION_BOTTOM, null, false);
                $title    = new Title('Présences / Absences par classe');

                $chart = new Chart('byClass', $title, $legend, $plotArea);
                $chart->setTopLeftPosition('G3');
                $chart->setBottomRightPosition('R22');

                $sheet->addChart($chart);
            },
        ];
    }

    public function title(): string
    {
        return 'Par classe';
    }
}
