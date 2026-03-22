<?php

namespace App\Enums;

enum AssessmentType: string
{
    case HOMEWORK     = 'homework';
    case QUIZ         = 'quiz';
    case EXAM         = 'exam';
    case PROJECT      = 'project';
    case ORAL         = 'oral';
    case PRACTICAL    = 'practical';
    case TEST         = 'test';
    case DICTATION    = 'dictation';

    public function label(): string
    {
        return match($this) {
            self::HOMEWORK  => 'Devoir maison',
            self::QUIZ      => 'Quiz',
            self::EXAM      => 'Examen',
            self::PROJECT   => 'Projet',
            self::ORAL      => 'Oral',
            self::PRACTICAL => 'Travaux Pratiques',
            self::TEST      => 'Contrôle',
            self::DICTATION => 'Dictée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::EXAM      => 'badge-error',
            self::TEST      => 'badge-warning',
            self::QUIZ      => 'badge-warning',
            self::HOMEWORK  => 'badge-info',
            self::PROJECT   => 'badge-success',
            self::ORAL      => 'badge-secondary',
            self::PRACTICAL => 'badge-accent',
            self::DICTATION => 'badge-primary',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::EXAM      => 'o-clipboard-document-list',
            self::TEST      => 'o-pencil-square',
            self::QUIZ      => 'o-question-mark-circle',
            self::HOMEWORK  => 'o-home',
            self::PROJECT   => 'o-beaker',
            self::ORAL      => 'o-microphone',
            self::PRACTICAL => 'o-wrench-screwdriver',
            self::DICTATION => 'o-pencil',
        };
    }

    public function gradient(): string
    {
        return match($this) {
            self::EXAM      => 'from-error/80 to-error/50 text-error-content',
            self::TEST      => 'from-warning/80 to-warning/50 text-warning-content',
            self::QUIZ      => 'from-orange-400/80 to-orange-400/50 text-white',
            self::HOMEWORK  => 'from-info/80 to-info/50 text-info-content',
            self::PROJECT   => 'from-success/80 to-success/50 text-success-content',
            self::ORAL      => 'from-secondary/80 to-secondary/50 text-secondary-content',
            self::PRACTICAL => 'from-accent/80 to-accent/50 text-accent-content',
            self::DICTATION => 'from-primary/80 to-primary/50 text-primary-content',
        };
    }
}
