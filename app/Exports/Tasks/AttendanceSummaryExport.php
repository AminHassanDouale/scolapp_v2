<?php

namespace App\Exports\Tasks;

use App\Exports\Tasks\Sheets\AttendanceByClassSheet;
use App\Exports\Tasks\Sheets\AttendanceSummarySheet;
use App\Exports\Tasks\Sheets\AttendanceTrendSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceSummaryExport implements WithMultipleSheets
{
    public function __construct(private int $schoolId, private ?int $classId = null) {}

    public function sheets(): array
    {
        return [
            new AttendanceSummarySheet($this->schoolId, $this->classId),
            new AttendanceByClassSheet($this->schoolId),
            new AttendanceTrendSheet($this->schoolId, $this->classId),
        ];
    }
}
