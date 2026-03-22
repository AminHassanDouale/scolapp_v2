<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->reference }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }

        .header { background: #1e3a8a; color: white; padding: 20px 24px 16px; }
        .header-flex { display: table; width: 100%; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; width: 40%; }
        .school-name { font-size: 16px; font-weight: bold; margin-bottom: 3px; }
        .school-sub  { font-size: 9px; opacity: 0.7; line-height: 1.5; }
        .doc-type    { font-size: 22px; font-weight: bold; letter-spacing: -0.5px; }
        .doc-ref     { font-size: 12px; font-weight: bold; opacity: 0.85; margin-top: 2px; }
        .status-pill { display: inline-block; border: 1.5px solid rgba(255,255,255,0.5); border-radius: 20px; padding: 2px 10px; font-size: 9px; font-weight: bold; margin-top: 4px; }

        .meta-bar { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 10px 24px; }
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
        table.detail tbody td { padding: 8px 10px; }
        table.detail tbody td.r { text-align: right; font-weight: bold; }
        table.detail tfoot td { padding: 8px 10px; font-weight: bold; text-align: right; }
        .total-row { background: #eff6ff; border-top: 2px solid #bfdbfe; }
        .total-row td { color: #1e3a8a; font-size: 13px; }
        .paid-row { background: #f0fdf4; }
        .paid-row td { color: #166534; }
        .balance-row { background: #fff7ed; border-top: 2px dashed #fed7aa; }
        .balance-row td { color: #c2410c; font-size: 14px; }

        .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 10px; display: table; width: 100%; }
        .footer-left { display: table-cell; font-size: 8px; color: #94a3b8; line-height: 1.6; }
        .footer-right { display: table-cell; text-align: right; font-size: 9px; font-weight: bold; color: #1d4ed8; }

        .watermark { position: fixed; top: 45%; left: 10%; font-size: 72px; font-weight: 900; color: rgba(22,163,74,0.05); transform: rotate(-35deg); white-space: nowrap; letter-spacing: 8px; }
    </style>
</head>
<body>

@if($invoice->status->value === 'paid')
<div class="watermark">PAYÉ</div>
@endif

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
            <div class="doc-type">FACTURE</div>
            <div class="doc-ref">{{ $invoice->reference }}</div>
            <div class="status-pill">{{ $invoice->status->label() }}</div>
        </div>
    </div>
</div>

<div class="meta-bar">
    <div class="meta-grid">
        <div class="meta-cell">
            <div class="meta-label">Date d'émission</div>
            <div class="meta-value">{{ $invoice->issue_date?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Échéance</div>
            <div class="meta-value" style="{{ $invoice->balance_due > 0 && $invoice->due_date?->isPast() ? 'color:#dc2626;' : '' }}">
                {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}
            </div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Année scolaire</div>
            <div class="meta-value">{{ $invoice->academicYear?->name ?? '—' }}</div>
        </div>
    </div>
</div>

<div class="body">
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
                <div class="party-name">{{ $invoice->student?->full_name }}</div>
                @if($invoice->student?->student_code)
                <div class="party-sub">Réf : {{ $invoice->student->student_code }}</div>
                @endif
                @if($invoice->enrollment?->schoolClass)
                <div class="party-sub">{{ $invoice->enrollment->schoolClass->name }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="section-title">Détail de la facturation</div>
    <table class="detail">
        <thead>
            <tr>
                <th>Description</th>
                @if($invoice->installment_number)<th>Versement</th>@endif
                <th class="r">Montant HT (DJF)</th>
                @if($invoice->vat_rate > 0)<th class="r">TVA ({{ $invoice->vat_rate }}%)</th>@endif
                <th class="r">Total (DJF)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->invoice_type?->label() }}@if($invoice->notes)<br><span style="font-size:9px;color:#64748b;">{{ $invoice->notes }}</span>@endif</td>
                @if($invoice->installment_number)<td style="text-align:center;">#{{ $invoice->installment_number }}</td>@endif
                <td class="r">{{ number_format((int)$invoice->subtotal, 0, ',', ' ') }}</td>
                @if($invoice->vat_rate > 0)<td class="r">{{ number_format((int)$invoice->vat_amount, 0, ',', ' ') }}</td>@endif
                <td class="r">{{ number_format((int)$invoice->subtotal + (int)$invoice->vat_amount, 0, ',', ' ') }}</td>
            </tr>
            @if($invoice->penalty_amount > 0)
            <tr style="background:#fff7f7;">
                <td style="color:#dc2626;">⚠ Pénalités de retard</td>
                @if($invoice->installment_number)<td></td>@endif
                <td class="r" style="color:#dc2626;">{{ number_format((int)$invoice->penalty_amount, 0, ',', ' ') }}</td>
                @if($invoice->vat_rate > 0)<td></td>@endif
                <td class="r" style="color:#dc2626;">{{ number_format((int)$invoice->penalty_amount, 0, ',', ' ') }}</td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="{{ 1 + ($invoice->installment_number ? 1 : 0) + ($invoice->vat_rate > 0 ? 1 : 0) }}" style="text-align:right;text-transform:uppercase;letter-spacing:0.06em;font-size:9px;">Total</td>
                <td style="font-size:14px;color:#1e3a8a;">{{ number_format((int)$invoice->total, 0, ',', ' ') }} DJF</td>
            </tr>
            @if($invoice->paid_total > 0)
            <tr class="paid-row">
                <td colspan="{{ 1 + ($invoice->installment_number ? 1 : 0) + ($invoice->vat_rate > 0 ? 1 : 0) }}" style="text-align:right;font-size:9px;">✓ Montant réglé</td>
                <td style="color:#166534;">− {{ number_format((int)$invoice->paid_total, 0, ',', ' ') }} DJF</td>
            </tr>
            @endif
            @if($invoice->balance_due > 0)
            <tr class="balance-row">
                <td colspan="{{ 1 + ($invoice->installment_number ? 1 : 0) + ($invoice->vat_rate > 0 ? 1 : 0) }}" style="text-align:right;text-transform:uppercase;letter-spacing:0.06em;font-size:9px;">Solde restant dû</td>
                <td style="color:#c2410c;font-size:15px;">{{ number_format((int)$invoice->balance_due, 0, ',', ' ') }} DJF</td>
            </tr>
            @endif
        </tfoot>
    </table>

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
