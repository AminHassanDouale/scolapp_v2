<?php

namespace App\Exports\Tasks\Sheets;

use App\Models\Payment;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyRevenueSheet implements FromArray, ShouldAutoSize, WithCharts, WithEvents, WithStyles, WithTitle
{
    private array $months = [];

    public function __construct(private int $schoolId)
    {
        $this->buildData();
    }

    private function buildData(): void
    {
        for ($i = 11; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $revenue = Payment::where('school_id', $this->schoolId)
                ->where('status', 'confirmed')
                ->whereYear('payment_date', $date->year)
                ->whereMonth('payment_date', $date->month)
                ->sum('amount');

            $count = Payment::where('school_id', $this->schoolId)
                ->where('status', 'confirmed')
                ->whereYear('payment_date', $date->year)
                ->whereMonth('payment_date', $date->month)
                ->count();

            $this->months[] = [
                'label'   => $date->translatedFormat('M Y'),
                'revenue' => (int) $revenue,
                'count'   => $count,
            ];
        }
    }

    public function array(): array
    {
        $rows = [
            ['Revenus mensuels — 12 derniers mois', '', ''],
            ['', '', ''],
            ['MOIS', 'REVENUS (DJF)', 'NOMBRE DE PAIEMENTS'],
        ];

        foreach ($this->months as $month) {
            $rows[] = [
                $month['label'],
                $month['revenue'],
                $month['count'],
            ];
        }

        $rows[] = ['', '', ''];
        $total  = array_sum(array_column($this->months, 'revenue'));
        $rows[] = ['TOTAL', $total, array_sum(array_column($this->months, 'count'))];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:C1');
        $lastRow = 3 + count($this->months) + 2;

        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3B82F6']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'DBEAFE']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $dataCount = count($this->months);

                // Data starts at row 4 (A4:A15 for 12 months)
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

                $dataValues = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_NUMBER,
                        "'{$sheetName}'!\$B\${$dataStart}:\$B\${$dataEnd}",
                        null,
                        $dataCount
                    ),
                ];

                $seriesLabels = [
                    new DataSeriesValues(
                        DataSeriesValues::DATASERIES_TYPE_STRING,
                        "'{$sheetName}'!\$B\$3",
                        null,
                        1
                    ),
                ];

                $series = new DataSeries(
                    DataSeries::TYPE_BARCHART,
                    DataSeries::GROUPING_CLUSTERED,
                    range(0, 0),
                    $seriesLabels,
                    $xLabels,
                    $dataValues
                );

                $plotArea = new PlotArea(null, [$series]);
                $legend   = new Legend(Legend::POSITION_BOTTOM, null, false);
                $title    = new Title('Revenus mensuels (DJF)');

                $chart = new Chart('monthlyRevenue', $title, $legend, $plotArea);
                $chart->setTopLeftPosition('E3');
                $chart->setBottomRightPosition('O22');

                $sheet->addChart($chart);
            },
        ];
    }

    public function title(): string
    {
        return 'Revenus mensuels';
    }
}
