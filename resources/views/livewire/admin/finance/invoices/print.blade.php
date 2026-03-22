<?php
use App\Models\Invoice;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.empty')] class extends Component {
    public Invoice $invoice;

    public function mount(string $uuid): void
    {
        $this->invoice = Invoice::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with(['student.attachments', 'school', 'academicYear', 'enrollment.schoolClass.grade', 'paymentAllocations.payment', 'attachments'])
            ->firstOrFail();
    }

    public function with(): array { return ['invoice' => $this->invoice]; }
};
?>

@push('head-styles')
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Instrument Sans', 'Inter', sans-serif; background: #f1f5f9; }

    @media print {
        .no-print { display: none !important; }
        .print-only { display: block !important; }
        body { background: white; margin: 0; padding: 0; }
        .invoice-paper { box-shadow: none !important; border-radius: 0 !important; margin: 0 !important; max-width: 100% !important; }
        @page { margin: 10mm; size: A4; }
    }
    .print-only { display: none; }

    .invoice-paper { background: white; max-width: 820px; margin: 24px auto; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.12); }
    .watermark-paid { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%) rotate(-35deg); font-size: 72px; font-weight: 900; color: rgba(22,163,74,0.06); pointer-events: none; white-space: nowrap; letter-spacing: 8px; text-transform: uppercase; }
</style>
@endpush

<div>
    {{-- Toolbar --}}
    <div class="no-print sticky top-0 z-50 bg-white border-b border-slate-200 px-6 py-3 flex items-center gap-3 shadow-sm">
        <a href="{{ route('admin.finance.invoices.show', $invoice->uuid) }}" class="btn btn-ghost btn-sm gap-2">
            <x-icon name="o-arrow-left" class="w-4 h-4" /> Retour
        </a>
        <div class="flex-1 flex items-center gap-2">
            <span class="font-mono font-bold text-sm text-slate-700">{{ $invoice->reference }}</span>
            @php
                $sc = match($invoice->status->value ?? '') {
                    'paid'           => 'badge-success',
                    'overdue'        => 'badge-error',
                    'issued'         => 'badge-info',
                    'partially_paid' => 'badge-warning',
                    default          => 'badge-ghost',
                };
            @endphp
            <span class="badge {{ $sc }} badge-sm">{{ $invoice->status->label() }}</span>
        </div>
        <button onclick="window.print()" class="btn btn-primary btn-sm gap-2">
            <x-icon name="o-printer" class="w-4 h-4" /> Imprimer / PDF
        </button>
    </div>

    {{-- Invoice paper --}}
    <div class="invoice-paper relative">

        @if(($invoice->status->value ?? '') === 'paid')
        <div class="watermark-paid">PAYÉ</div>
        @endif

        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 55%,#3b82f6 100%);padding:28px 32px 24px;color:white;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-50px;right:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.04);"></div>
            <div style="position:absolute;bottom:-70px;right:100px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.03);"></div>

            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;position:relative;">
                {{-- School identity --}}
                <div style="display:flex;align-items:flex-start;gap:16px;">
                    @if($invoice->school->logo)
                    <img src="{{ Storage::disk('public')->url($invoice->school->logo) }}"
                         alt="{{ $invoice->school->name }}"
                         style="width:60px;height:60px;object-fit:contain;border-radius:10px;background:rgba(255,255,255,0.15);padding:4px;flex-shrink:0;">
                    @else
                    <div style="width:60px;height:60px;border-radius:10px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;flex-shrink:0;">
                        {{ strtoupper(substr($invoice->school->name, 0, 2)) }}
                    </div>
                    @endif
                    <div>
                        <h1 style="font-size:1.35rem;font-weight:900;margin:0;line-height:1.2;">{{ $invoice->school->name }}</h1>
                        @if($invoice->school->address)
                        <p style="margin:5px 0 0;font-size:0.8rem;opacity:0.7;">{{ $invoice->school->address }}@if($invoice->school->city), {{ $invoice->school->city }}@endif</p>
                        @endif
                        <div style="display:flex;gap:14px;margin-top:5px;font-size:0.75rem;opacity:0.6;">
                            @if($invoice->school->phone)<span>📞 {{ $invoice->school->phone }}</span>@endif
                            @if($invoice->school->email)<span>✉ {{ $invoice->school->email }}</span>@endif
                        </div>
                    </div>
                </div>

                {{-- Invoice id block --}}
                <div style="text-align:right;flex-shrink:0;">
                    <p style="font-size:0.65rem;letter-spacing:0.15em;text-transform:uppercase;opacity:0.55;margin:0;">Document</p>
                    <p style="font-size:2rem;font-weight:900;font-family:monospace;margin:2px 0;letter-spacing:-1px;">FACTURE</p>
                    <p style="font-size:1rem;font-weight:700;font-family:monospace;opacity:0.85;margin:0 0 8px;">{{ $invoice->reference }}</p>
                    <div style="display:inline-block;padding:3px 12px;border-radius:20px;border:1.5px solid rgba(255,255,255,0.4);font-size:0.75rem;font-weight:600;letter-spacing:0.05em;">
                        {{ $invoice->status->label() }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Meta bar --}}
        <div style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:14px 32px;">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                @foreach([
                    ['Date d\'émission', $invoice->issue_date?->format('d/m/Y') ?? '—', false],
                    ['Échéance', $invoice->due_date?->format('d/m/Y') ?? '—', $invoice->balance_due > 0 && $invoice->due_date?->isPast()],
                    ['Année scolaire', $invoice->academicYear?->name ?? '—', false],
                ] as [$lbl, $val, $overdue])
                <div>
                    <p style="font-size:0.62rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#94a3b8;margin:0 0 3px;">{{ $lbl }}</p>
                    <p style="font-weight:700;color:{{ $overdue ? '#dc2626' : '#1e293b' }};margin:0;font-size:0.95rem;">
                        {{ $val }}{{ $overdue ? ' ⚠' : '' }}
                    </p>
                </div>
                @endforeach
            </div>
        </div>

        <div style="padding:24px 32px;">

            {{-- Parties --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;">
                    <p style="font-size:0.62rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#94a3b8;margin:0 0 8px;">Émetteur</p>
                    <p style="font-weight:800;color:#1e293b;margin:0 0 4px;">{{ $invoice->school->name }}</p>
                    @if($invoice->school->code)<p style="font-size:0.72rem;font-family:monospace;color:#94a3b8;margin:0 0 3px;">Code : {{ $invoice->school->code }}</p>@endif
                    @if($invoice->school->address)<p style="font-size:0.82rem;color:#64748b;margin:0;">{{ $invoice->school->address }}</p>@endif
                    @if($invoice->school->city)<p style="font-size:0.82rem;color:#64748b;margin:0;">{{ $invoice->school->city }}@if($invoice->school->country), {{ $invoice->school->country }}@endif</p>@endif
                    @if($invoice->school->email)<p style="font-size:0.78rem;color:#64748b;margin:2px 0 0;">{{ $invoice->school->email }}</p>@endif
                    @if($invoice->school->phone)<p style="font-size:0.78rem;color:#64748b;margin:0;">{{ $invoice->school->phone }}</p>@endif
                </div>
                <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe;border-radius:12px;padding:16px;">
                    <p style="font-size:0.62rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#3b82f6;margin:0 0 8px;">Destinataire</p>
                    <p style="font-weight:800;color:#1e293b;margin:0 0 4px;">{{ $invoice->student->full_name }}</p>
                    @if($invoice->student->student_code)<p style="font-size:0.78rem;font-family:monospace;color:#64748b;margin:0 0 2px;">N° élève : <strong>{{ $invoice->student->student_code }}</strong></p>@endif
                    @if($invoice->enrollment?->reference)<p style="font-size:0.78rem;font-family:monospace;color:#64748b;margin:0 0 2px;">N° inscription : <strong>{{ $invoice->enrollment->reference }}</strong></p>@endif
                    @if($invoice->enrollment?->schoolClass)<p style="font-size:0.82rem;color:#64748b;margin:0;">{{ $invoice->enrollment->schoolClass->name }}@if($invoice->enrollment->schoolClass->grade) — {{ $invoice->enrollment->schoolClass->grade->name }}@endif</p>@endif
                </div>
            </div>

            {{-- Items table --}}
            <table style="width:100%;border-collapse:collapse;font-size:0.88rem;margin-bottom:24px;">
                <thead>
                    <tr style="background:linear-gradient(90deg,#1e3a8a,#1d4ed8);color:white;border-radius:8px;">
                        <th style="text-align:left;padding:10px 14px;font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;border-radius:8px 0 0 0;">Description</th>
                        @if($invoice->installment_number)
                        <th style="text-align:center;padding:10px 14px;font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;">Versement</th>
                        @endif
                        <th style="text-align:right;padding:10px 14px;font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;border-radius:0 8px 0 0;">Montant (DJF)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:14px;vertical-align:top;">
                            <p style="font-weight:700;color:#1e293b;margin:0;">{{ $invoice->invoice_type->label() }}</p>
                            @if($invoice->notes)<p style="font-size:0.78rem;color:#64748b;margin:4px 0 0;">{{ $invoice->notes }}</p>@endif
                        </td>
                        @if($invoice->installment_number)
                        <td style="padding:14px;text-align:center;">
                            <span style="background:#f1f5f9;padding:2px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;color:#475569;">#{{ $invoice->installment_number }}</span>
                        </td>
                        @endif
                        <td style="padding:14px;text-align:right;font-weight:700;">{{ number_format((int)$invoice->subtotal, 0, ',', ' ') }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    @if($invoice->vat_rate > 0)
                    <tr><td colspan="{{ $invoice->installment_number ? 2 : 1 }}" style="padding:8px 14px;text-align:right;font-size:0.82rem;color:#64748b;">TVA ({{ $invoice->vat_rate }}%)</td><td style="padding:8px 14px;text-align:right;color:#64748b;">{{ number_format((int)$invoice->vat_amount, 0, ',', ' ') }}</td></tr>
                    @endif
                    @if($invoice->penalty_amount > 0)
                    <tr style="background:#fff7f7;"><td colspan="{{ $invoice->installment_number ? 2 : 1 }}" style="padding:8px 14px;text-align:right;font-size:0.82rem;color:#dc2626;">⚠ Pénalités de retard</td><td style="padding:8px 14px;text-align:right;font-weight:600;color:#dc2626;">{{ number_format((int)$invoice->penalty_amount, 0, ',', ' ') }}</td></tr>
                    @endif
                    <tr style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-top:2px solid #bfdbfe;">
                        <td colspan="{{ $invoice->installment_number ? 2 : 1 }}" style="padding:13px 14px;text-align:right;font-weight:800;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.06em;font-size:0.85rem;">Total</td>
                        <td style="padding:13px 14px;text-align:right;font-weight:900;font-size:1.2rem;color:#1e3a8a;">{{ number_format((int)$invoice->total, 0, ',', ' ') }} DJF</td>
                    </tr>
                    @if($invoice->paid_total > 0)
                    <tr style="background:#f0fdf4;">
                        <td colspan="{{ $invoice->installment_number ? 2 : 1 }}" style="padding:8px 14px;text-align:right;font-size:0.82rem;color:#166534;">✓ Montant réglé</td>
                        <td style="padding:8px 14px;text-align:right;font-weight:700;color:#166534;">− {{ number_format((int)$invoice->paid_total, 0, ',', ' ') }} DJF</td>
                    </tr>
                    @endif
                    @if($invoice->balance_due > 0)
                    <tr style="background:#fff7ed;border-top:2px dashed #fed7aa;">
                        <td colspan="{{ $invoice->installment_number ? 2 : 1 }}" style="padding:13px 14px;text-align:right;font-weight:800;color:#c2410c;text-transform:uppercase;letter-spacing:0.06em;font-size:0.85rem;">Solde restant dû</td>
                        <td style="padding:13px 14px;text-align:right;font-weight:900;font-size:1.25rem;color:#c2410c;">{{ number_format((int)$invoice->balance_due, 0, ',', ' ') }} DJF</td>
                    </tr>
                    @endif
                </tfoot>
            </table>

            {{-- Payment history --}}
            @if($invoice->paymentAllocations->count() > 0)
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:24px;">
                <p style="font-size:0.65rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#94a3b8;margin:0 0 10px;">Paiements reçus</p>
                <table style="width:100%;border-collapse:collapse;font-size:0.82rem;">
                    <thead>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <th style="text-align:left;padding:4px 8px;font-size:0.72rem;color:#64748b;">Référence</th>
                            <th style="text-align:center;padding:4px 8px;font-size:0.72rem;color:#64748b;">Date</th>
                            <th style="text-align:center;padding:4px 8px;font-size:0.72rem;color:#64748b;">Méthode</th>
                            <th style="text-align:right;padding:4px 8px;font-size:0.72rem;color:#64748b;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->paymentAllocations as $alloc)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:6px 8px;font-family:monospace;color:#64748b;">{{ $alloc->payment->reference }}</td>
                            <td style="padding:6px 8px;text-align:center;color:#64748b;">{{ $alloc->payment->payment_date?->format('d/m/Y') }}</td>
                            <td style="padding:6px 8px;text-align:center;color:#64748b;text-transform:capitalize;">{{ $alloc->payment->payment_method }}</td>
                            <td style="padding:6px 8px;text-align:right;font-weight:700;color:#166534;">{{ number_format((int)$alloc->amount, 0, ',', ' ') }} DJF</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Attachments (invoice files + student documents) --}}
            @php
                $invoiceFiles  = $invoice->attachments ?? collect();
                $studentFiles  = $invoice->student?->attachments ?? collect();
                $allFiles      = $invoiceFiles->concat($studentFiles)->unique('id');
            @endphp
            @if($allFiles->isNotEmpty())
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:24px;" class="no-print">
                <p style="font-size:0.65rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#94a3b8;margin:0 0 10px;">Fichiers joints</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
                    @foreach($allFiles as $file)
                    <a href="{{ $file->url() }}" target="_blank"
                       style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:white;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#1e293b;">
                        @if($file->isPdf())
                            <span style="font-size:1.1rem;">📄</span>
                        @elseif($file->isImage())
                            <span style="font-size:1.1rem;">🖼️</span>
                        @else
                            <span style="font-size:1.1rem;">📎</span>
                        @endif
                        <div style="min-width:0;">
                            <p style="font-size:0.78rem;font-weight:600;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;">{{ $file->label ?? $file->original_name }}</p>
                            <p style="font-size:0.65rem;color:#94a3b8;margin:0;">{{ $file->humanSize() }}{{ $file->category ? ' · ' . $file->category->label() : '' }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Attachments listed on print (names only, no links) --}}
            @if($allFiles->isNotEmpty())
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;margin-bottom:24px;display:none;" class="print-only">
                <p style="font-size:0.65rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#94a3b8;margin:0 0 8px;">Fichiers joints ({{ $allFiles->count() }})</p>
                @foreach($allFiles as $file)
                <p style="font-size:0.78rem;color:#475569;margin:2px 0;">📎 {{ $file->label ?? $file->original_name }} ({{ $file->humanSize() }})</p>
                @endforeach
            </div>
            @endif

            {{-- Footer --}}
            <div style="border-top:1px solid #e2e8f0;padding-top:14px;display:flex;align-items:flex-end;justify-content:space-between;">
                <div style="font-size:0.7rem;color:#94a3b8;line-height:1.7;">
                    <p style="margin:0;">{{ $invoice->school->name }}@if($invoice->school->address) — {{ $invoice->school->address }}@endif</p>
                    <p style="margin:0;">Généré le {{ now()->format('d/m/Y à H:i') }}</p>
                </div>
                <div style="display:flex;align-items:center;gap:5px;opacity:0.45;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="5" fill="#1d4ed8"/><path d="M6 8h12M6 12h8M6 16h10" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
                    <span style="font-size:0.7rem;font-weight:700;color:#1d4ed8;letter-spacing:0.05em;">ScolApp SMS</span>
                </div>
            </div>
        </div>
    </div>

    <div class="no-print pb-12"></div>
</div>
