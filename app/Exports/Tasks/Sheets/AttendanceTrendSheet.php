<?php

namespace App\Exports\Tasks\Sheets;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use Carbon\Carbon;
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

class AttendanceTrendSheet implements FromArray, ShouldAutoSize, WithCharts, WithEvents, WithStyles, WithTitle
{
    private array $dailyData = [];

    public function __construct(private int $schoolId, private ?int $classId = null)
    {
        $this->buildData();
    }

    private function buildData(): void
    {
        $start = now()->subWeek()->startOfDay();
        $end   = now()->endOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $sessionIds = AttendanceSession::where('school_id', $this->schoolId)
                ->whereDate('session_date', $date->toDateString())
                ->when($this->classId, fn($q) => $q->where('school_class_id', $this->classId))
                ->pluck('id');

            $total   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->count();
            $present = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
                ->where('status', AttendanceStatus::PRESENT->value)->count();
            $absent  = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
                ->where('status', AttendanceStatus::ABSENT->value)->count();

            $this->dailyData[] = [
                'date'    => $date->format('d/m/Y'),
                'day'     => $date->translatedFormat('l'),
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
            ['Tendance journalière des présences', '', '', '', '', ''],
            ['', '', '', '', '', ''],
            ['DATE', 'JOUR', 'TOTAL', 'PRÉSENTS', 'ABSENTS', 'TAUX (%)'],
        ];

        foreach ($this->dailyData as $day) {
            $rows[] = [
                $day['date'],
                $day['day'],
                $day['total'],
                $day['present'],
                $day['absent'],
                $day['rate'],
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:F1');

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
                $dataCount = count($this->dailyData);

                if ($dataCount === 0) {
                    return;
                }

                $dataStart = 4;
                $dataEnd   = $dataStart + $dataCount - 1;
                $sheetName = $this->title();

                $xLabels = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_STRING,
                        "'{$sheetName}'!\$A\${$dataStart}:\$A\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                $presentValues = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_NUMBER,
                        "'{$sheetName}'!\$D\${$dataStart}:\$D\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                $rateValues = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_NUMBER,
                        "'{$sheetName}'!\$F\${$dataStart}:\$F\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                $seriesLabels = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$D\$3", null, 1),
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!\$F\$3", null, 1),
                ];

                $series = new DataSeries(
                    DataSeries::TYPE_LINECHART,
                    DataSeries::GROUPING_STANDARD,
                    range(0, 1),
                    $seriesLabels,
                    $xLabels,
                    array_merge($presentValues, $rateValues)
                );

                $plotArea = new PlotArea(null, [$series]);
                $legend   = new Legend(Legend::POSITION_BOTTOM, null, false);
                $title    = new Title('Tendance journalière (Présents & Taux)');

                $chart = new Chart('trend', $title, $legend, $plotArea);
                $chart->setTopLeftPosition('H3');
                $chart->setBottomRightPosition('R20');

                $sheet->addChart($chart);
            },
        ];
    }

    public function title(): string
    {
        return 'Tendance journalière';
    }
}
