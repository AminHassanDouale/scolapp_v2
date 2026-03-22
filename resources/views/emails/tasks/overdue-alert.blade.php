<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#1e293b;background:#f8fafc;margin:0;padding:0}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.header{background:#dc2626;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px;font-weight:700}
.header p{margin:4px 0 0;font-size:12px;opacity:.8}
.body{padding:28px 32px}
.alert-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;padding:14px 18px;margin:16px 0;color:#991b1b;font-weight:600}
.invoice-box{border:1.5px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:18px 0}
.inv-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
.inv-row:last-child{border-bottom:none;font-weight:700;font-size:15px;color:#dc2626}
.footer{background:#f1f5f9;padding:14px 32px;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
<div class="wrap">
    <div class="header" style="text-align:center;">
        <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="height:56px;max-width:180px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:8px;padding:4px;display:block;margin:0 auto 10px;">
        <h1>{{ $school->name }}</h1>
        <p>⚠ Facture en retard de paiement</p>
    </div>
    <div class="body">
        <p>Bonjour <strong>{{ $guardian->full_name }}</strong>,</p>

        <div class="alert-box">
            ⚠ La facture ci-dessous est en retard de paiement. Des pénalités peuvent s'appliquer.
        </div>

        <p>La facture suivante associée à <strong>{{ $invoice->student?->full_name }}</strong> n'a pas été réglée dans les délais impartis.</p>

        <div class="invoice-box">
            <div class="inv-row"><span>Référence</span><span>{{ $invoice->reference }}</span></div>
            <div class="inv-row"><span>Échéance dépassée le</span><span style="color:#dc2626">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</span></div>
            <div class="inv-row"><span>Jours de retard</span><span style="color:#dc2626">{{ $invoice->due_date?->diffInDays(now()) ?? 0 }} jour(s)</span></div>
            @if($invoice->penalty_amount > 0)
            <div class="inv-row"><span>Pénalités</span><span style="color:#dc2626">{{ number_format((int)$invoice->penalty_amount, 0, ',', ' ') }} DJF</span></div>
            @endif
            <div class="inv-row"><span>Solde restant dû</span><span>{{ number_format((int)$invoice->balance_due, 0, ',', ' ') }} DJF</span></div>
        </div>

        <p>Veuillez régulariser votre situation au plus vite. Contactez l'administration pour tout arrangement.</p>
    </div>
    <div class="footer">
        {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }} · ScolApp SMS
    </div>
</div>
</body></html>
