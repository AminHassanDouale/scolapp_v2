<?php

return [
    'enrollment_status' => [
        'hold'      => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'cancelled' => 'ملغى',
    ],
    'invoice_status' => [
        'draft'          => 'مسودة',
        'issued'         => 'صادرة',
        'partially_paid' => 'مدفوعة جزئياً',
        'paid'           => 'مدفوعة',
        'cancelled'      => 'ملغاة',
        'overdue'        => 'متأخرة',
    ],
    'invoice_type' => [
        'registration' => 'تسجيل',
        'tuition'      => 'رسوم دراسية',
    ],
    'fee_schedule_type' => [
        'monthly'   => 'شهري',
        'quarterly' => 'ربع سنوي',
        'yearly'    => 'سنوي',
    ],
    'payment_status' => [
        'pending'   => 'في الانتظار',
        'confirmed' => 'مؤكد',
        'cancelled' => 'ملغى',
        'refunded'  => 'مسترد',
    ],
    'attendance_status' => [
        'present' => 'حاضر',
        'absent'  => 'غائب',
        'late'    => 'متأخر',
        'excused' => 'معذور',
    ],
    'gender' => [
        'male'   => 'ذكر',
        'female' => 'أنثى',
    ],
    'guardian_relation' => [
        'father'         => 'أب',
        'mother'         => 'أم',
        'uncle'          => 'عم/خال',
        'aunt'           => 'عمة/خالة',
        'grandparent'    => 'جد/جدة',
        'sibling'        => 'أخ/أخت',
        'legal_guardian' => 'ولي أمر قانوني',
        'other'          => 'أخرى',
    ],
    'assessment_type' => [
        'homework' => 'واجب منزلي',
        'quiz'     => 'اختبار قصير',
        'exam'     => 'امتحان',
        'project'  => 'مشروع',
        'oral'     => 'شفهي',
    ],
    'announcement_level' => [
        'info'    => 'معلومة',
        'warning' => 'تحذير',
        'urgent'  => 'عاجل',
    ],
    'report_period' => [
        'trimester_1' => 'الفصل الأول',
        'trimester_2' => 'الفصل الثاني',
        'trimester_3' => 'الفصل الثالث',
        'semester_1'  => 'الفصل الدراسي الأول',
        'semester_2'  => 'الفصل الدراسي الثاني',
        'annual'      => 'السنوي',
    ],
];
