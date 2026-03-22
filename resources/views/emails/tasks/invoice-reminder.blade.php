<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#1e293b;background:#f8fafc;margin:0;padding:0}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.header{background:#1e3a8a;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px;font-weight:700}
.header p{margin:4px 0 0;font-size:12px;opacity:.7}
.body{padding:28px 32px}
.greeting{font-size:15px;margin-bottom:16px}
.invoice-box{border:1.5px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:18px 0;background:#eff6ff}
.inv-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #dbeafe;font-size:13px}
.inv-row:last-child{border-bottom:none;font-weight:700;font-size:15px;color:#1e3a8a}
.amount{font-weight:bold;color:#1e3a8a}
.footer{background:#f1f5f9;padding:14px 32px;font-size:11px;color:#94a3b8;text-align:center}
.btn{display:inline-block;background:#1e3a8a;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:16px;font-size:13px}
</style></head>
<body>
<div class="wrap">
    <div class="header" style="text-align:center;">
        <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="height:56px;max-width:180px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:8px;padding:4px;display:block;margin:0 auto 10px;">
        <h1>{{ $school->name }}</h1>
        <p>Rappel de paiement</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour <strong>{{ $guardian->full_name }}</strong>,</p>
        <p>Nous vous contactons pour vous rappeler qu'une facture associée à votre enfant <strong>{{ $invoice->student?->full_name }}</strong> est en attente de règlement.</p>

        <div class="invoice-box">
            <div class="inv-row"><span>Référence</span><span>{{ $invoice->reference }}</span></div>
            <div class="inv-row"><span>Type</span><span>{{ $invoice->invoice_type?->label() }}</span></div>
            <div class="inv-row"><span>Année scolaire</span><span>{{ $invoice->academicYear?->name ?? '—' }}</span></div>
            <div class="inv-row"><span>Échéance</span><span>{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</span></div>
            <div class="inv-row"><span>Montant total</span><span>{{ number_format((int)$invoice->total, 0, ',', ' ') }} DJF</span></div>
            <div class="inv-row"><span>Déjà réglé</span><span>{{ number_format((int)$invoice->paid_total, 0, ',', ' ') }} DJF</span></div>
            <div class="inv-row"><span>Solde restant dû</span><span class="amount">{{ number_format((int)$invoice->balance_due, 0, ',', ' ') }} DJF</span></div>
        </div>

        <p>Merci de bien vouloir procéder au règlement dans les meilleurs délais. Pour toute question, n'hésitez pas à contacter l'administration de l'établissement.</p>
    </div>
    <div class="footer">
        {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }} · ScolApp SMS
    </div>
</div>
</body></html>
