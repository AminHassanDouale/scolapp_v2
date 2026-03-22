<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#1e293b;background:#f8fafc;margin:0;padding:0}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.header{background:#d97706;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px;font-weight:700}
.header p{margin:4px 0 0;font-size:12px;opacity:.8}
.body{padding:28px 32px}
.info-box{background:#fffbeb;border:1.5px solid #fde68a;border-radius:8px;padding:14px 18px;margin:16px 0;color:#92400e;font-weight:600}
.invoice-box{border:1.5px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:18px 0}
.inv-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
.inv-row:last-child{border-bottom:none;font-weight:700;font-size:15px;color:#d97706}
.footer{background:#f1f5f9;padding:14px 32px;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
<div class="wrap">
    <div class="header" style="text-align:center;">
        <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="height:56px;max-width:180px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:8px;padding:4px;display:block;margin:0 auto 10px;">
        <h1>{{ $school->name }}</h1>
        <p>Rappel — Échéance de paiement proche</p>
    </div>
    <div class="body">
        <p>Bonjour <strong>{{ $guardian->full_name }}</strong>,</p>

        <div class="info-box">
            🕐 L'échéance de la facture ci-dessous approche dans moins de {{ $daysBefore }} jours.
        </div>

        <p>Nous vous informons que la facture suivante de <strong>{{ $invoice->student?->full_name }}</strong> arrive bientôt à échéance.</p>

        <div class="invoice-box">
            <div class="inv-row"><span>Référence</span><span>{{ $invoice->reference }}</span></div>
            <div class="inv-row"><span>Type</span><span>{{ $invoice->invoice_type?->label() }}</span></div>
            <div class="inv-row"><span>Échéance</span><span style="color:#d97706;font-weight:bold">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</span></div>
            <div class="inv-row"><span>Jours restants</span><span>{{ now()->diffInDays($invoice->due_date) }} jour(s)</span></div>
            <div class="inv-row"><span>Solde restant dû</span><span>{{ number_format((int)$invoice->balance_due, 0, ',', ' ') }} DJF</span></div>
        </div>

        <p>Merci d'effectuer le règlement avant la date d'échéance pour éviter tout retard.</p>
    </div>
    <div class="footer">
        {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }} · ScolApp SMS
    </div>
</div>
</body></html>
