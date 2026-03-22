<?php

namespace App\Enums;

enum AttachmentCategory: string
{
    // Teacher documents
    case DIPLOMA           = 'diploma';
    case CERTIFICATE       = 'certificate';
    case SEMINAR           = 'seminar';
    case CONTRACT          = 'contract';

    // Student documents
    case BIRTH_CERTIFICATE = 'birth_certificate';
    case HEALTH_RECORD     = 'health_record';
    case AUTHORIZATION     = 'authorization';

    // Shared
    case ID_CARD           = 'id_card';
    case PASSPORT          = 'passport';
    case RESIDENCE_PROOF   = 'residence_proof';
    case PHOTO             = 'photo';
    case OTHER             = 'other';

    public function label(): string
    {
        return match($this) {
            self::DIPLOMA           => 'Diplôme',
            self::CERTIFICATE       => 'Certificat / Attestation',
            self::SEMINAR           => 'Attestation de formation',
            self::CONTRACT          => 'Contrat',
            self::BIRTH_CERTIFICATE => 'Acte de naissance',
            self::HEALTH_RECORD     => 'Carnet de santé',
            self::AUTHORIZATION     => 'Autorisation parentale',
            self::ID_CARD           => "Carte d'identité",
            self::PASSPORT          => 'Passeport',
            self::RESIDENCE_PROOF   => 'Justificatif de domicile',
            self::PHOTO             => "Photo d'identité",
            self::OTHER             => 'Autre document',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DIPLOMA, self::CERTIFICATE, self::SEMINAR => 'o-academic-cap',
            self::CONTRACT                                  => 'o-document-text',
            self::BIRTH_CERTIFICATE                         => 'o-document',
            self::HEALTH_RECORD                             => 'o-heart',
            self::AUTHORIZATION                             => 'o-shield-check',
            self::ID_CARD, self::PASSPORT                   => 'o-identification',
            self::RESIDENCE_PROOF                           => 'o-home',
            self::PHOTO                                     => 'o-camera',
            self::OTHER                                     => 'o-paper-clip',
        };
    }

    /** Returns categories relevant for a given model class */
    public static function forModel(string $modelClass): array
    {
        return match(true) {
            str_ends_with($modelClass, 'Teacher') => [
                self::DIPLOMA,
                self::CERTIFICATE,
                self::SEMINAR,
                self::CONTRACT,
                self::ID_CARD,
                self::PASSPORT,
                self::RESIDENCE_PROOF,
                self::PHOTO,
                self::OTHER,
            ],
            str_ends_with($modelClass, 'Student') => [
                self::BIRTH_CERTIFICATE,
                self::ID_CARD,
                self::PASSPORT,
                self::HEALTH_RECORD,
                self::AUTHORIZATION,
                self::PHOTO,
                self::OTHER,
            ],
            str_ends_with($modelClass, 'Guardian') => [
                self::ID_CARD,
                self::PASSPORT,
                self::RESIDENCE_PROOF,
                self::OTHER,
            ],
            default => self::cases(),
        };
    }
}
