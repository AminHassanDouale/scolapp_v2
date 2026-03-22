<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Services\ReportCardService;

class GenerateReportCardAction
{
    public function __construct(private readonly ReportCardService $service) {}

    public function __invoke(Enrollment $enrollment, string $period): ReportCard
    {
        return $this->service->generate($enrollment, $period);
    }
}
