<?php

namespace App\Enums;

enum ScheduledTaskType: string
{
    case INVOICE_REMINDER    = 'invoice_reminder';
    case OVERDUE_ALERT       = 'overdue_alert';
    case PAYMENT_DUE_SOON    = 'payment_due_soon';
    case ATTENDANCE_SUMMARY  = 'attendance_summary';
    case FINANCIAL_SUMMARY   = 'financial_summary';
    case CUSTOM_NOTIFICATION = 'custom_notification';

    public function label(): string
    {
        return match($this) {
            self::INVOICE_REMINDER    => 'Rappel de facture',
            self::OVERDUE_ALERT       => 'Alerte facture en retard',
            self::PAYMENT_DUE_SOON    => 'Paiement bientôt dû',
            self::ATTENDANCE_SUMMARY  => 'Résumé des présences',
            self::FINANCIAL_SUMMARY   => 'Résumé financier',
            self::CUSTOM_NOTIFICATION => 'Notification personnalisée',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::INVOICE_REMINDER    => 'Envoie un rappel aux tuteurs ayant des factures impayées.',
            self::OVERDUE_ALERT       => 'Alerte les tuteurs dont les factures sont en retard de paiement.',
            self::PAYMENT_DUE_SOON    => 'Prévient les tuteurs X jours avant l\'échéance d\'une facture.',
            self::ATTENDANCE_SUMMARY  => 'Envoie un récapitulatif hebdomadaire des absences aux administrateurs.',
            self::FINANCIAL_SUMMARY   => 'Envoie un rapport financier périodique aux administrateurs.',
            self::CUSTOM_NOTIFICATION => 'Message personnalisé envoyé à une audience ciblée.',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::INVOICE_REMINDER    => 'o-bell-alert',
            self::OVERDUE_ALERT       => 'o-exclamation-triangle',
            self::PAYMENT_DUE_SOON    => 'o-clock',
            self::ATTENDANCE_SUMMARY  => 'o-clipboard-document-check',
            self::FINANCIAL_SUMMARY   => 'o-banknotes',
            self::CUSTOM_NOTIFICATION => 'o-megaphone',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::INVOICE_REMINDER    => 'warning',
            self::OVERDUE_ALERT       => 'error',
            self::PAYMENT_DUE_SOON    => 'info',
            self::ATTENDANCE_SUMMARY  => 'success',
            self::FINANCIAL_SUMMARY   => 'primary',
            self::CUSTOM_NOTIFICATION => 'secondary',
        };
    }

    public function defaultTarget(): string
    {
        return match($this) {
            self::INVOICE_REMINDER    => 'unpaid_guardians',
            self::OVERDUE_ALERT       => 'overdue_guardians',
            self::PAYMENT_DUE_SOON    => 'unpaid_guardians',
            self::ATTENDANCE_SUMMARY  => 'school_admins',
            self::FINANCIAL_SUMMARY   => 'school_admins',
            self::CUSTOM_NOTIFICATION => 'all_guardians',
        };
    }
}
