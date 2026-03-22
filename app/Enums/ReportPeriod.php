<?php

namespace App\Enums;

enum ReportPeriod: string
{
    case TRIMESTER_1 = 'trimester_1';
    case TRIMESTER_2 = 'trimester_2';
    case TRIMESTER_3 = 'trimester_3';
    case SEMESTER_1  = 'semester_1';
    case SEMESTER_2  = 'semester_2';
    case ANNUAL      = 'annual';

    public function label(): string
    {
        return match($this) {
            self::TRIMESTER_1 => __('enums.report_period.trimester_1'),
            self::TRIMESTER_2 => __('enums.report_period.trimester_2'),
            self::TRIMESTER_3 => __('enums.report_period.trimester_3'),
            self::SEMESTER_1  => __('enums.report_period.semester_1'),
            self::SEMESTER_2  => __('enums.report_period.semester_2'),
            self::ANNUAL      => __('enums.report_period.annual'),
        };
    }
}
