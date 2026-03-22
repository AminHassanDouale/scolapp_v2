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

class PaymentMethodSheet implements FromArray, ShouldAutoSize, WithCharts, WithEvents, WithStyles, WithTitle
{
    private array $methods = [];

    public function __construct(private int $schoolId)
    {
        $this->buildData();
    }

    private function buildData(): void
    {
        $rows = Payment::where('school_id', $this->schoolId)
            ->where('status', 'confirmed')
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        foreach ($rows as $row) {
            $this->methods[] = [
                'method' => $this->translateMethod($row->payment_method),
                'total'  => (int) $row->total,
                'count'  => $row->count,
            ];
        }
    }

    private function translateMethod(string $method): string
    {
        return match ($method) {
            'cash'         => 'Espèces',
            'bank_transfer'=> 'Virement bancaire',
            'check'        => 'Chèque',
            'mobile_money' => 'Mobile Money',
            default        => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function array(): array
    {
        $rows = [
            ['Répartition par méthode de paiement', '', ''],
            ['Période : ' . now()->translatedFormat('F Y') . ' (cumulatif)', '', ''],
            ['', '', ''],
            ['MÉTHODE', 'MONTANT (DJF)', 'NOMBRE'],
        ];

        foreach ($this->methods as $m) {
            $rows[] = [$m['method'], $m['total'], $m['count']];
        }

        if (!empty($this->methods)) {
            $rows[] = ['', '', ''];
            $rows[] = [
                'TOTAL',
                array_sum(array_column($this->methods, 'total')),
                array_sum(array_column($this->methods, 'count')),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');

        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['italic' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3B82F6']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $dataCount = count($this->methods);

                if ($dataCount === 0) {
                    return;
                }

                $dataStart = 5;
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
                        "'{$sheetName}'!\$B\$4",
                        null,
                        1
                    ),
                ];

                $series = new DataSeries(
                    DataSeries::TYPE_PIECHART,
                    null,
                    range(0, 0),
                    $seriesLabels,
                    $xLabels,
                    $dataValues
                );

                $plotArea = new PlotArea(null, [$series]);
                $legend   = new Legend(Legend::POSITION_RIGHT, null, false);
                $title    = new Title('Répartition par méthode');

                $chart = new Chart('paymentMethods', $title, $legend, $plotArea);
                $chart->setTopLeftPosition('E3');
                $chart->setBottomRightPosition('N18');

                $sheet->addChart($chart);
            },
        ];
    }

    public function title(): string
    {
        return 'Méthodes de paiement';
    }
}
