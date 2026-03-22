<?php

return [
    'enrollment_status' => [
        'hold'      => 'En attente',
        'confirmed' => 'Confirmée',
        'cancelled' => 'Annulée',
    ],
    'invoice_status' => [
        'draft'          => 'Brouillon',
        'issued'         => 'Émise',
        'partially_paid' => 'Partiellement payée',
        'paid'           => 'Payée',
        'cancelled'      => 'Annulée',
        'overdue'        => 'En retard',
    ],
    'invoice_type' => [
        'registration' => 'Inscription',
        'tuition'      => 'Scolarité',
    ],
    'fee_schedule_type' => [
        'monthly'   => 'Mensuel',
        'quarterly' => 'Trimestriel',
        'yearly'    => 'Annuel',
    ],
    'payment_status' => [
        'pending'   => 'En attente',
        'confirmed' => 'Confirmé',
        'cancelled' => 'Annulé',
        'refunded'  => 'Remboursé',
    ],
    'attendance_status' => [
        'present' => 'Présent',
        'absent'  => 'Absent',
        'late'    => 'En retard',
        'excused' => 'Excusé',
    ],
    'gender' => [
        'male'   => 'Masculin',
        'female' => 'Féminin',
    ],
    'guardian_relation' => [
        'father'         => 'Père',
        'mother'         => 'Mère',
        'uncle'          => 'Oncle',
        'aunt'           => 'Tante',
        'grandparent'    => 'Grand-parent',
        'sibling'        => 'Frère/Sœur',
        'legal_guardian' => 'Tuteur légal',
        'other'          => 'Autre',
    ],
    'assessment_type' => [
        'homework' => 'Devoir',
        'quiz'     => 'Interrogation',
        'exam'     => 'Examen',
        'project'  => 'Projet',
        'oral'     => 'Oral',
    ],
    'announcement_level' => [
        'info'    => 'Information',
        'warning' => 'Avertissement',
        'urgent'  => 'Urgent',
    ],
    'report_period' => [
        'trimester_1' => '1er Trimestre',
        'trimester_2' => '2ème Trimestre',
        'trimester_3' => '3ème Trimestre',
        'semester_1'  => '1er Semestre',
        'semester_2'  => '2ème Semestre',
        'annual'      => 'Annuel',
    ],
];
