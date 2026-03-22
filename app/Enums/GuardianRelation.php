<?php

namespace App\Enums;

enum GuardianRelation: string
{
    case FATHER        = 'father';
    case MOTHER        = 'mother';
    case UNCLE         = 'uncle';
    case AUNT          = 'aunt';
    case GRANDPARENT   = 'grandparent';
    case SIBLING       = 'sibling';
    case LEGAL_GUARDIAN = 'legal_guardian';
    case OTHER         = 'other';

    public function label(): string
    {
        return match($this) {
            self::FATHER         => __('enums.guardian_relation.father'),
            self::MOTHER         => __('enums.guardian_relation.mother'),
            self::UNCLE          => __('enums.guardian_relation.uncle'),
            self::AUNT           => __('enums.guardian_relation.aunt'),
            self::GRANDPARENT    => __('enums.guardian_relation.grandparent'),
            self::SIBLING        => __('enums.guardian_relation.sibling'),
            self::LEGAL_GUARDIAN => __('enums.guardian_relation.legal_guardian'),
            self::OTHER          => __('enums.guardian_relation.other'),
        };
    }
}
