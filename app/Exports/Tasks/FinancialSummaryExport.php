<?php

namespace App\Exports\Tasks;

use App\Exports\Tasks\Sheets\FinancialKpiSheet;
use App\Exports\Tasks\Sheets\MonthlyRevenueSheet;
use App\Exports\Tasks\Sheets\PaymentMethodSheet;
use App\Exports\Tasks\Sheets\UnpaidInvoicesSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialSummaryExport implements WithMultipleSheets
{
    public function __construct(private int $schoolId) {}

    public function sheets(): array
    {
        return [
            new FinancialKpiSheet($this->schoolId),
            new MonthlyRevenueSheet($this->schoolId),
            new PaymentMethodSheet($this->schoolId),
            new UnpaidInvoicesSheet($this->schoolId),
        ];
    }
}
