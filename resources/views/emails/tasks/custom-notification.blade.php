<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#1e293b;background:#f8fafc;margin:0;padding:0}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.header{background:#7c3aed;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px;font-weight:700}
.header p{margin:4px 0 0;font-size:12px;opacity:.8}
.body{padding:28px 32px;line-height:1.7}
.content-box{background:#f5f3ff;border-left:4px solid #7c3aed;border-radius:4px;padding:16px 20px;margin:16px 0;white-space:pre-line}
.footer{background:#f1f5f9;padding:14px 32px;font-size:11px;color:#94a3b8;text-align:center}
</style></head>
<body>
<div class="wrap">
    <div class="header" style="text-align:center;">
        <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" style="height:56px;max-width:180px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:8px;padding:4px;display:block;margin:0 auto 10px;">
        <h1>{{ $school->name }}</h1>
        <p>{{ $task->name }}</p>
    </div>
    <div class="body">
        <p>Bonjour <strong>{{ $recipient->full_name ?? $recipient->name }}</strong>,</p>

        <div class="content-box">{{ $body }}</div>

        <p>Cordialement,<br><strong>{{ $school->name }}</strong></p>
    </div>
    <div class="footer">
        {{ $school->name }}@if($school->address) — {{ $school->address }}@endif<br>
        Généré le {{ now()->format('d/m/Y à H:i') }} · ScolApp SMS
    </div>
</div>
</body></html>
