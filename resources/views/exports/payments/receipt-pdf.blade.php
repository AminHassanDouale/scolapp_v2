<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu {{ $payment->reference }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }

        .header {
            background: #1e3a8a;
            color: white;
            padding: 20px 24px 16px;
            position: relative;
        }
        .header-flex { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; width: 40%; }
        .school-name { font-size: 16px; font-weight: bold; margin-bottom: 3px; }
        .school-sub  { font-size: 9px; opacity: 0.7; line-height: 1.5; }
        .doc-type    { font-size: 22px; font-weight: bold; letter-spacing: -0.5px; }
        .doc-ref     { font-size: 12px; font-weight: bold; opacity: 0.85; margin-top: 2px; }
        .status-pill {
            display: inline-block;
            border: 1.5px solid rgba(255,255,255,0.5);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 9px;
            font-weight: bold;
            margin-top: 4px;
            letter-spacing: 0.05em;
        }

        .meta-bar {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 24px;
        }
        .meta-grid { display: table; width: 100%; }
        .meta-cell { display: table-cell; width: 33.33%; }
        .meta-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 2px; }
        .meta-value { font-size: 11px; font-weight: bold; color: #1e293b; }

        .body { padding: 18px 24px; }

        .parties { display: table; width: 100%; margin-bottom: 18px; }
        .party-cell { display: table-cell; width: 50%; padding-right: 10px; vertical-align: top; }
        .party-cell:last-child { padding-right: 0; padding-left: 10px; }
        .party-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .party-box-blue { border-color: #bfdbfe; background: #eff6ff; }
        .party-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 6px; }
        .party-name { font-size: 12px; font-weight: bold; color: #1e293b; margin-bottom: 2px; }
        .party-sub { font-size: 9px; color: #64748b; }

        .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 8px; margin-top: 14px; }

        table.detail { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.detail thead tr { background: #1e3a8a; color: white; }
        table.detail thead th { padding: 7px 10px; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.06em; }
        table.detail thead th.r { text-align: right; }
        table.detail tbody tr { border-bottom: 1px solid #f1f5f9; }
        table.detail tbody tr:nth-child(even) { background: #f8fafc; }
        table.detail tbody td { padding: 7px 10px; }
        table.detail tbody td.r { text-align: right; font-weight: bold; }
        table.detail tfoot td { padding: 8px 10px; font-weight: bold; }
        .total-row { background: #eff6ff !important; border-top: 2px solid #bfdbfe; }
        .total-row td { color: #1e3a8a; font-size: 12px; }
        .paid-row { background: #f0fdf4 !important; }
        .paid-row td { color: #166534; }
        .balance-row { background: #fff7ed !important; border-top: 2px dashed #fed7aa; }
        .balance-row td { color: #c2410c; font-size: 13px; }

        .method-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 14px; }
        .method-row { display: table; width: 100%; margin-bottom: 4px; }
        .method-key { display: table-cell; width: 40%; font-size: 9px; color: #64748b; }
        .method-val { display: table-cell; font-size: 9px; font-weight: bold; color: #1e293b; }

        .footer {
            margin-top: 24px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            display: table;
            width: 100%;
        }
        .footer-left { display: table-cell; font-size: 8px; color: #94a3b8; line-height: 1.6; }
        .footer-right { display: table-cell; text-align: right; font-size: 9px; font-weight: bold; color: #1d4ed8; }

        .watermark {
            position: fixed;
            top: 45%;
            left: 15%;
            font-size: 72px;
            font-weight: 900;
            color: rgba(22, 163, 74, 0.05);
            transform: rotate(-35deg);
            white-space: nowrap;
            letter-spacing: 8px;
        }
    </style>
</head>
<body>

@if($payment->status->value === 'confirmed')
<div class="watermark">PAYÉ</div>
@endif

{{-- Header --}}
<div class="header">
    <div class="header-flex">
        <div class="header-left">
            <div class="school-name">{{ $school->name }}</div>
            <div class="school-sub">
                @if($school->address){{ $school->address }}@if($school->city), {{ $school->city }}@endif<br>@endif
                @if($school->phone)Tél : {{ $school->phone }}@endif
                @if($school->email) — {{ $school->email }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-type">REÇU</div>
            <div class="doc-ref">{{ $payment->reference }}</div>
            <div class="status-pill">{{ $payment->status->label() }}</div>
        </div>
    </div>
</div>

{{-- Meta bar --}}
<div class="meta-bar">
    <div class="meta-grid">
        <div class="meta-cell">
            <div class="meta-label">Date du paiement</div>
            <div class="meta-value">{{ $payment->payment_date?->format('d/m/Y') }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Méthode</div>
            <div class="meta-value">{{ match($payment->payment_method ?? '') {
                'cash'          => 'Espèces',
                'bank_transfer' => 'Virement bancaire',
                'check'         => 'Chèque',
                'mobile_money'  => 'Mobile Money',
                default         => $payment->payment_method ?? '—'
            } }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Montant réglé</div>
            <div class="meta-value" style="color:#1e3a8a;font-size:14px;">{{ number_format((int)$payment->amount, 0, ',', ' ') }} DJF</div>
        </div>
    </div>
</div>

<div class="body">

    {{-- Parties --}}
    <div class="parties">
        <div class="party-cell">
            <div class="party-box">
                <div class="party-label">Émetteur</div>
                <div class="party-name">{{ $school->name }}</div>
                @if($school->address)<div class="party-sub">{{ $school->address }}</div>@endif
                @if($school->city)<div class="party-sub">{{ $school->city }}</div>@endif
            </div>
        </div>
        <div class="party-cell">
            <div class="party-box party-box-blue">
                <div class="party-label" style="color:#3b82f6;">Destinataire</div>
                <div class="party-name">{{ $payment->student?->full_name }}</div>
                @if($payment->student?->student_code)
                <div class="party-sub">Réf : {{ $payment->student->student_code }}</div>
                @endif
                @if($guardian ?? null)
                <div class="party-sub" style="margin-top:4px;">Tuteur : {{ $guardian->name }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Invoices settled --}}
    <div class="section-title">Factures réglées</div>
    <table class="detail">
        <thead>
            <tr>
                <th>Référence facture</th>
                <th>Type</th>
                <th>Année scolaire</th>
                <th class="r">Montant alloué</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment->paymentAllocations as $alloc)
            <tr>
                <td>{{ $alloc->invoice?->reference }}</td>
                <td>{{ $alloc->invoice?->invoice_type?->label() }}</td>
                <td>{{ $alloc->invoice?->academicYear?->name ?? '—' }}</td>
                <td class="r">{{ number_format((int)$alloc->amount, 0, ',', ' ') }} DJF</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align:right;text-transform:uppercase;letter-spacing:0.06em;font-size:9px;">Total réglé</td>
                <td style="text-align:right;font-size:14px;color:#1e3a8a;">{{ number_format((int)$payment->amount, 0, ',', ' ') }} DJF</td>
            </tr>
        </tfoot>
    </table>

    {{-- Payment method details --}}
    @if($payment->transaction_ref || $payment->bank_name || !empty($payment->meta))
    <div class="method-box">
        <div class="section-title" style="margin-top:0;">Détails du règlement</div>
        @if($payment->transaction_ref)
        <div class="method-row">
            <div class="method-key">Référence transaction</div>
            <div class="method-val">{{ $payment->transaction_ref }}</div>
        </div>
        @endif
        @if($payment->bank_name)
        <div class="method-row">
            <div class="method-key">Banque</div>
            <div class="method-val">{{ $payment->bank_name }}</div>
        </div>
        @endif
        @if(!empty($payment->meta['provider']))
        <div class="method-row">
            <div class="method-key">Opérateur</div>
            <div class="method-val">{{ match($payment->meta['provider']) {
                'd_money'   => 'D-Money',
                'waafi'     => 'Waafi',
                'cac_pay'   => 'CaC Pay',
                'exim_bank' => 'Exim Bank Mobile',
                'saba_bank' => 'Saba Bank',
                default     => $payment->meta['provider']
            } }}</div>
        </div>
        @endif
        @if(!empty($payment->meta['phone']))
        <div class="method-row">
            <div class="method-key">Téléphone</div>
            <div class="method-val">{{ $payment->meta['phone'] }}</div>
        </div>
        @endif
    </div>
    @endif

    @if($payment->notes)
    <div style="margin-top:12px;font-size:9px;color:#64748b;font-style:italic;">Note : {{ $payment->notes }}</div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <div class="footer-left">
            {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
            Généré le {{ now()->format('d/m/Y à H:i') }}
        </div>
        <div class="footer-right">ScolApp SMS</div>
    </div>
</div>

</body>
</html>
