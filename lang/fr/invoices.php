<?php

return [
    'title'  => 'Factures',
    'new'    => 'Nouvelle facture',
    'search' => 'Rechercher une facture...',

    'table' => [
        'reference' => 'Référence',
        'student'   => 'Élève',
        'class'     => 'Classe',
        'date'      => 'Date d\'émission',
        'due_date'  => 'Échéance',
        'amount'    => 'Montant',
        'paid'      => 'Payé',
        'balance'   => 'Solde',
        'status'    => 'Statut',
    ],

    'stats' => [
        'total_amount' => 'Montant total',
        'paid_amount'  => 'Montant payé',
        'balance'      => 'Solde restant',
    ],

    'filters' => [
        'title'           => 'Filtres',
        'status'          => 'Statut',
        'student'         => 'Élève',
        'class'           => 'Classe',
        'year'            => 'Année académique',
        'all'             => 'Tous',
        'date_range'      => 'Plages de dates',
        'issue_date_range'=> 'Dates d\'émission',
        'due_date_range'  => 'Dates d\'échéance',
        'pick_range'      => 'Sélectionner une période',
        'amount_range'    => 'Plage de montant',
        'amount_min'      => 'Montant min.',
        'amount_max'      => 'Montant max.',
        'additional'      => 'Filtres supplémentaires',
        'overdue'         => 'État de retard',
        'overdue_only'    => 'En retard',
        'due_soon'        => 'Bientôt dû',
        'schedule_type'   => 'Type d\'échéancier',
        'reset'           => 'Réinitialiser',
        'apply'           => 'Appliquer',
    ],

    'export_title'          => 'Exporter les factures',
    'export_description'    => 'Choisissez le format d\'export.',
    'export_pdf'            => 'Export PDF',
    'export_excel'          => 'Export Excel',
    'export_pdf_desc'       => 'Format imprimable',
    'export_excel_desc'     => 'Fichier tableur',
    'export_started'        => 'Export en cours...',
    'export_selection_note' => 'facture(s) sélectionnée(s) seront exportée(s)',
    'export_filters_note'   => 'filtre(s) actif(s) appliqué(s)',
    'export_all_note'       => 'Toutes les factures seront exportées.',

    'selected'       => 'sélectionnée(s)',
    'deselect'       => 'Désélectionner',
    'deleted'        => 'Facture supprimée.',
    'confirm_delete' => 'Supprimer cette facture ?',
    'filters_reset'  => 'Filtres réinitialisés.',
    'cancel'         => 'Annuler',

    'is_overdue'     => 'En retard',
];
