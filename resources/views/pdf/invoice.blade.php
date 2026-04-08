<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Facture {{ $invoice->reference }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1e293b; background: white; }

    .header { background: #064e3b; padding: 22px 28px 18px; color: white; }
    .header-inner { display: table; width: 100%; }
    .header-left { display: table-cell; vertical-align: top; }
    .header-right { display: table-cell; vertical-align: top; text-align: right; width: 200px; }
    .school-name { font-size: 16px; font-weight: bold; margin-bottom: 3px; }
    .school-meta { font-size: 10px; opacity: 0.7; }
    .doc-label { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; opacity: 0.6; }
    .doc-title { font-size: 22px; font-weight: bold; font-family: monospace; letter-spacing: -1px; }
    .doc-ref { font-size: 12px; font-family: monospace; opacity: 0.85; margin-top: 2px; }
    .doc-status { display: inline-block; padding: 2px 10px; border: 1.5px solid rgba(255,255,255,0.45); border-radius: 20px; font-size: 10px; margin-top: 6px; }

    .metabar { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 10px 28px; }
    .metabar-inner { display: table; width: 100%; }
    .metabar-cell { display: table-cell; width: 33%; }
    .meta-label { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
    .meta-value { font-weight: bold; font-size: 12px; color: #1e293b; }
    .meta-value.overdue { color: #dc2626; }

    .body { padding: 20px 28px; }

    .parties { display: table; width: 100%; margin-bottom: 18px; border-spacing: 8px; }
    .party { display: table-cell; width: 50%; padding: 14px; border-radius: 10px; vertical-align: top; }
    .party-emitter { background: #f8fafc; border: 1px solid #e2e8f0; }
    .party-student { background: #ecfdf5; border: 1px solid #a7f3d0; }
    .party-label { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px; }
    .party-label.student-label { color: #059669; }
    .party-name { font-weight: bold; font-size: 13px; margin-bottom: 3px; }
    .party-detail { font-size: 10px; color: #64748b; margin-bottom: 1px; }

    table.items { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 18px; }
    table.items thead tr { background: #064e3b; color: white; }
    table.items th { padding: 8px 12px; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; text-align: left; }
    table.items th:last-child { text-align: right; }
    table.items tbody tr { border-bottom: 1px solid #e2e8f0; }
    table.items td { padding: 12px; vertical-align: top; }
    table.items td:last-child { text-align: right; font-weight: bold; }
    .installment-badge { background: #f1f5f9; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: bold; color: #475569; }

    table.totals { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 18px; }
    table.totals td { padding: 6px 12px; }
    table.totals td:first-child { text-align: right; color: #64748b; }
    table.totals td:last-child { text-align: right; font-weight: bold; width: 140px; }
    .total-row { background: #ecfdf5; border-top: 2px solid #a7f3d0; }
    .total-row td { padding: 10px 12px; font-weight: 900; color: #064e3b; font-size: 13px; text-transform: uppercase; }
    .balance-row { background: #fff7ed; border-top: 2px dashed #fed7aa; }
    .balance-row td { padding: 10px 12px; font-weight: 900; color: #c2410c; font-size: 13px; }

    .watermark { position: fixed; top: 40%; left: 20%; transform: rotate(-35deg); font-size: 80px; font-weight: 900; color: rgba(22,163,74,0.06); letter-spacing: 8px; text-transform: uppercase; z-index: -1; }

    .footer { border-top: 1px solid #e2e8f0; padding: 10px 28px; display: table; width: 100%; }
    .footer-left { display: table-cell; font-size: 9px; color: #94a3b8; vertical-align: bottom; }
    .footer-right { display: table-cell; text-align: right; font-size: 9px; font-weight: bold; color: #059669; vertical-align: bottom; letter-spacing: 1px; }

    .section-title { font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px; }
    .payments-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px; }
    table.payments { width: 100%; border-collapse: collapse; font-size: 10px; }
    table.payments th { text-align: left; padding: 3px 6px; font-size: 9px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
    table.payments td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; }
    .status-paid { background: #dcfce7; color: #166534; padding: 1px 6px; border-radius: 20px; font-size: 8px; font-weight: bold; }
    .status-pending { background: #fef9c3; color: #854d0e; padding: 1px 6px; border-radius: 20px; font-size: 8px; font-weight: bold; }
    .status-other { background: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 20px; font-size: 8px; font-weight: bold; }

    .installment-summary { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 10px; }
</style>
</head>
<body>

@if($invoice->status?->value === 'paid')
<div class="watermark">PAYÉ</div>
@endif

{{-- Header --}}
<div class="header">
    <div class="header-inner">
        <div class="header-left">
            <div class="school-name">{{ $invoice->school->name }}</div>
            @if($invoice->school->address)
            <div class="school-meta">{{ $invoice->school->address }}@if($invoice->school->city), {{ $invoice->school->city }}@endif</div>
            @endif
            <div class="school-meta" style="margin-top:3px;">
                @if($invoice->school->phone)📞 {{ $invoice->school->phone }}@endif
                @if($invoice->school->email)  ✉ {{ $invoice->school->email }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-label">Document</div>
            <div class="doc-title">FACTURE</div>
            <div class="doc-ref">{{ $invoice->reference }}</div>
            <div class="doc-status">{{ $invoice->status?->label() }}</div>
        </div>
    </div>
</div>

{{-- Meta bar --}}
<div class="metabar">
    <div class="metabar-inner">
        <div class="metabar-cell">
            <div class="meta-label">Date d'émission</div>
            <div class="meta-value">{{ $invoice->issue_date?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="metabar-cell">
            <div class="meta-label">Échéance</div>
            @php $overdue = $invoice->balance_due > 0 && $invoice->due_date?->isPast(); @endphp
            <div class="meta-value {{ $overdue ? 'overdue' : '' }}">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}{{ $overdue ? ' ⚠' : '' }}</div>
        </div>
        <div class="metabar-cell">
            <div class="meta-label">Année scolaire</div>
            <div class="meta-value">{{ $invoice->academicYear?->name ?? '—' }}</div>
        </div>
    </div>
</div>

<div class="body">

    {{-- Parties --}}
    <table class="parties">
        <tr>
            <td class="party party-emitter">
                <div class="party-label">Émetteur</div>
                <div class="party-name">{{ $invoice->school->name }}</div>
                @if($invoice->school->code)<div class="party-detail">Code : {{ $invoice->school->code }}</div>@endif
                @if($invoice->school->address)<div class="party-detail">{{ $invoice->school->address }}</div>@endif
                @if($invoice->school->city)<div class="party-detail">{{ $invoice->school->city }}@if($invoice->school->country), {{ $invoice->school->country }}@endif</div>@endif
                @if($invoice->school->email)<div class="party-detail">{{ $invoice->school->email }}</div>@endif
                @if($invoice->school->phone)<div class="party-detail">{{ $invoice->school->phone }}</div>@endif
            </td>
            <td style="width:12px;"></td>
            <td class="party party-student">
                <div class="party-label student-label">Élève</div>
                <div class="party-name">{{ $invoice->student->full_name }}</div>
                @if($invoice->student->student_code)<div class="party-detail">N° élève : {{ $invoice->student->student_code }}</div>@endif
                @if($invoice->enrollment?->reference)<div class="party-detail">N° inscription : {{ $invoice->enrollment->reference }}</div>@endif
                @if($invoice->enrollment?->schoolClass)<div class="party-detail">{{ $invoice->enrollment->schoolClass->name }}@if($invoice->enrollment->schoolClass->grade) — {{ $invoice->enrollment->schoolClass->grade->name }}@endif</div>@endif
            </td>
        </tr>
    </table>

    {{-- Installment context banner for tuition --}}
    @if($invoice->installment_number && $invoice->invoice_type?->value === 'tuition')
    <div class="installment-summary">
        Versement <strong>n° {{ $invoice->installment_number }}</strong>
        @if($invoice->schedule_type)
         · Fréquence : <strong>{{ $invoice->schedule_type->label() }}</strong>
        @endif
        · Échéance : <strong>{{ $invoice->due_date?->format('d/m/Y') }}</strong>
    </div>
    @endif

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                @if($invoice->installment_number)<th style="text-align:center;">Versement</th>@endif
                <th>Montant (DJF)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $invoice->invoice_type?->label() }}</strong>
                    @if($invoice->notes)<br><span style="color:#64748b;font-size:10px;">{{ $invoice->notes }}</span>@endif
                </td>
                @if($invoice->installment_number)
                <td style="text-align:center;"><span class="installment-badge">#{{ $invoice->installment_number }}</span></td>
                @endif
                <td>{{ number_format((int)$invoice->subtotal, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        @if($invoice->vat_rate > 0)
        <tr>
            <td>TVA ({{ $invoice->vat_rate }}%)</td>
            <td>{{ number_format((int)$invoice->vat_amount, 0, ',', ' ') }} DJF</td>
        </tr>
        @endif
        <tr class="total-row">
            <td>Total</td>
            <td>{{ number_format((int)$invoice->total, 0, ',', ' ') }} DJF</td>
        </tr>
        @if($invoice->paid_total > 0)
        <tr style="background:#f0fdf4;">
            <td style="color:#166534;">✓ Montant réglé</td>
            <td style="color:#166534;">− {{ number_format((int)$invoice->paid_total, 0, ',', ' ') }} DJF</td>
        </tr>
        @endif
        @if($invoice->balance_due > 0)
        <tr class="balance-row">
            <td>Solde restant dû</td>
            <td>{{ number_format((int)$invoice->balance_due, 0, ',', ' ') }} DJF</td>
        </tr>
        @endif
    </table>

    {{-- Payment history --}}
    @if($invoice->paymentAllocations?->isNotEmpty())
    <div class="payments-box">
        <div class="section-title">Historique des paiements</div>
        <table class="payments">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Méthode</th>
                    <th>Statut</th>
                    <th style="text-align:right;">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->paymentAllocations as $alloc)
                <tr>
                    <td style="font-family:monospace;">{{ $alloc->payment->reference }}</td>
                    <td>{{ $alloc->payment->payment_date?->format('d/m/Y') }}</td>
                    <td>{{ $alloc->payment->notes ?? $alloc->payment->payment_method }}</td>
                    <td>
                        @php $ps = $alloc->payment->status; @endphp
                        <span class="{{ $ps === \App\Enums\PaymentStatus::CONFIRMED ? 'status-paid' : ($ps === \App\Enums\PaymentStatus::PENDING ? 'status-pending' : 'status-other') }}">
                            {{ $ps?->label() }}
                        </span>
                    </td>
                    <td style="text-align:right;font-weight:bold;color:#166534;">{{ number_format((int)$alloc->amount, 0, ',', ' ') }} DJF</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

{{-- Footer --}}
<div class="footer">
    <div class="footer-left">
        {{ $invoice->school->name }}@if($invoice->school->address) — {{ $invoice->school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
    <div class="footer-right">ScolApp SMS</div>
</div>

</body>
</html>
