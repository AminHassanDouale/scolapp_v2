<?php

namespace App\Enums;

/**
 * Report-card approval lifecycle (adapted from the INTEC bulletin spec to
 * ScolApp's roles):
 *
 *   draft → submitted → pedagogie_approved → finance_approved → approved → published
 *   (rejected can happen from any approval step and returns the card to draft-editable)
 *
 * Role mapping (INTEC → ScolApp):
 *   direction  → director
 *   pédagogie  → director / admin
 *   finance    → accountant / admin
 */
enum ReportCardStatus: string
{
    case DRAFT              = 'draft';
    case SUBMITTED          = 'submitted';
    case PEDAGOGIE_APPROVED = 'pedagogie_approved';
    case FINANCE_APPROVED   = 'finance_approved';
    case APPROVED           = 'approved';
    case PUBLISHED          = 'published';
    case REJECTED           = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT              => 'Brouillon',
            self::SUBMITTED          => 'Soumis',
            self::PEDAGOGIE_APPROVED => 'Validé pédagogie',
            self::FINANCE_APPROVED   => 'Validé finance',
            self::APPROVED           => 'Approuvé',
            self::PUBLISHED          => 'Publié',
            self::REJECTED           => 'Rejeté',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT              => 'ghost',
            self::SUBMITTED          => 'info',
            self::PEDAGOGIE_APPROVED => 'primary',
            self::FINANCE_APPROVED   => 'secondary',
            self::APPROVED           => 'warning',
            self::PUBLISHED          => 'success',
            self::REJECTED           => 'error',
        };
    }

    /** The status reached when the current step is approved. */
    public function next(): ?self
    {
        return match ($this) {
            self::DRAFT              => self::SUBMITTED,
            self::SUBMITTED          => self::PEDAGOGIE_APPROVED,
            self::PEDAGOGIE_APPROVED => self::FINANCE_APPROVED,
            self::FINANCE_APPROVED   => self::APPROVED,
            self::APPROVED           => self::PUBLISHED,
            default                  => null,
        };
    }

    /**
     * Roles allowed to advance a card that is currently in this status.
     * (super-admin/admin can always act.)
     */
    public function rolesToAdvance(): array
    {
        return match ($this) {
            self::DRAFT, self::REJECTED => ['teacher', 'director', 'admin', 'super-admin'],
            self::SUBMITTED             => ['director', 'admin', 'super-admin'],          // pédagogie
            self::PEDAGOGIE_APPROVED    => ['accountant', 'director', 'admin', 'super-admin'], // finance
            self::FINANCE_APPROVED      => ['director', 'admin', 'super-admin'],          // direction
            self::APPROVED              => ['director', 'admin', 'super-admin'],          // publish
            default                     => [],
        };
    }

    /** Label for the button that advances from this status. */
    public function advanceLabel(): string
    {
        return match ($this) {
            self::DRAFT, self::REJECTED => 'Soumettre',
            self::SUBMITTED             => 'Valider (pédagogie)',
            self::PEDAGOGIE_APPROVED    => 'Valider (finance)',
            self::FINANCE_APPROVED      => 'Approuver (direction)',
            self::APPROVED              => 'Publier',
            default                     => '',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED], true);
    }

    public function canReject(): bool
    {
        return in_array($this, [self::SUBMITTED, self::PEDAGOGIE_APPROVED, self::FINANCE_APPROVED, self::APPROVED], true);
    }
}
