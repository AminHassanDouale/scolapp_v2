<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#1e293b;background:#f8fafc;margin:0;padding:0}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.header{background:#059669;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px;font-weight:700}
.header p{margin:4px 0 0;font-size:12px;opacity:.8}
.body{padding:28px 32px}
.stats{display:table;width:100%;margin:16px 0;border-radius:8px;overflow:hidden;border:1.5px solid #d1fae5}
.stat-cell{display:table-cell;text-align:center;padding:16px;background:#ecfdf5;border-right:1px solid #d1fae5}
.stat-cell:last-child{border-right:none}
.stat-num{font-size:28px;font-weight:900;color:#059669}
.stat-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-top:4px}
.rate-bar{background:#e5e7eb;border-radius:8px;height:14px;margin:12px 0;overflow:hidden}
.rate-fill{background:#059669;height:14px;border-radius:8px}
.footer{background:#f1f5f9;padding:14px 32px;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
<div class="wrap">
    <div class="header" style="text-align:center;">
        <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="height:56px;max-width:180px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:8px;padding:4px;display:block;margin:0 auto 10px;">
        <h1>{{ $school->name }}</h1>
        <p>Résumé des présences — Semaine du {{ now()->startOfWeek()->format('d/m') }} au {{ now()->endOfWeek()->format('d/m/Y') }}</p>
    </div>
    <div class="body">
        <p>Bonjour <strong>{{ $admin->name }}</strong>,</p>
        <p>Voici le résumé des présences de cette semaine :</p>

        <div class="stats">
            <div class="stat-cell">
                <div class="stat-num">{{ $stats['total'] }}</div>
                <div class="stat-label">Total entrées</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num" style="color:#059669">{{ $stats['present'] }}</div>
                <div class="stat-label">Présents</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num" style="color:#dc2626">{{ $stats['absent'] }}</div>
                <div class="stat-label">Absents</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num">{{ $stats['rate'] }}%</div>
                <div class="stat-label">Taux présence</div>
            </div>
        </div>

        <div class="rate-bar">
            <div class="rate-fill" style="width:{{ $stats['rate'] }}%"></div>
        </div>
        <p style="font-size:12px;color:#6b7280;text-align:center">Taux de présence global : {{ $stats['rate'] }}%</p>
    </div>
    <div class="footer">
        {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }} · ScolApp SMS
    </div>
</div>
</body></html>
