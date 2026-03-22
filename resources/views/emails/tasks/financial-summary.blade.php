<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#1e293b;background:#f8fafc;margin:0;padding:0}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.header{background:#1e3a8a;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px;font-weight:700}
.header p{margin:4px 0 0;font-size:12px;opacity:.8}
.body{padding:28px 32px}
.kpi-grid{display:table;width:100%;margin:16px 0}
.kpi{display:table-cell;text-align:center;padding:14px;border-radius:8px;margin:4px}
.kpi-val{font-size:22px;font-weight:900}
.kpi-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;margin-top:4px;opacity:.7}
.footer{background:#f1f5f9;padding:14px 32px;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
<div class="wrap">
    <div class="header" style="text-align:center;">
        <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="height:56px;max-width:180px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:8px;padding:4px;display:block;margin:0 auto 10px;">
        <h1>{{ $school->name }}</h1>
        <p>Résumé financier — {{ now()->format('F Y') }}</p>
    </div>
    <div class="body">
        <p>Bonjour <strong>{{ $admin->name }}</strong>,</p>
        <p>Voici le résumé financier du mois en cours :</p>

        <div class="kpi-grid">
            <div class="kpi" style="background:#eff6ff">
                <div class="kpi-val" style="color:#1e3a8a">{{ number_format((int)$stats['revenue'], 0, ',', ' ') }}</div>
                <div class="kpi-label" style="color:#1e3a8a">Revenus collectés (DJF)</div>
            </div>
            <div class="kpi" style="background:#fefce8">
                <div class="kpi-val" style="color:#a16207">{{ number_format((int)$stats['pending'], 0, ',', ' ') }}</div>
                <div class="kpi-label" style="color:#a16207">En attente (DJF)</div>
            </div>
            <div class="kpi" style="background:#fef2f2">
                <div class="kpi-val" style="color:#dc2626">{{ number_format((int)$stats['overdue'], 0, ',', ' ') }}</div>
                <div class="kpi-label" style="color:#dc2626">En retard (DJF)</div>
            </div>
        </div>

        <p>Pour plus de détails, veuillez consulter le tableau de bord administrateur.</p>
    </div>
    <div class="footer">
        {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }} · ScolApp SMS
    </div>
</div>
</body></html>
