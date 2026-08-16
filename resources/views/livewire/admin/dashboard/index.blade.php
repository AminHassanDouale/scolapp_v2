<?php
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceEntry;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;

new #[Layout('layouts.app')] class extends Component
{
    public string $preset = 'this_month';
    public ?string $from = null;
    public ?string $to = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to   = now()->toDateString();
    }

    private function schoolId(): int
    {
        return (int) auth()->user()->school_id;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(?AcademicYear $year): array
    {
        return match ($this->preset) {
            'last_month'  => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'last30'      => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'year'        => [now()->startOfYear(), now()->endOfYear()],
            'school_year' => $year
                ? [Carbon::parse($year->start_date)->startOfDay(), Carbon::parse($year->end_date)->endOfDay()]
                : [now()->startOfYear(), now()->endOfYear()],
            'custom'      => [Carbon::parse($this->from ?: now()->startOfMonth())->startOfDay(), Carbon::parse($this->to ?: now())->endOfDay()],
            default       => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function with(): array
    {
        $sid  = $this->schoolId();
        $year = AcademicYear::where('school_id', $sid)->where('is_current', true)->first();
        [$start, $end] = $this->range($year);

        // ── KPIs ──────────────────────────────────────────────────────────────
        $paymentsPeriod = Payment::where('school_id', $sid)
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);

        $revenuePeriod = (float) (clone $paymentsPeriod)->sum('amount');
        $paymentsCount = (clone $paymentsPeriod)->count();

        $newEnrollments = Enrollment::where('school_id', $sid)
            ->whereBetween('enrolled_at', [$start, $end])->count();

        $totalStudents = Student::where('school_id', $sid)->where('is_active', true)->count();
        $totalBalance  = (float) Invoice::where('school_id', $sid)
            ->whereNotIn('status', ['paid', 'cancelled'])->sum('balance_due');
        $overdueCount  = Invoice::where('school_id', $sid)->where('status', 'overdue')->count();

        // ── Revenue trend (day if span <= 45d, else month) ────────────────────
        $spanDays = $start->diffInDays($end);
        $revLabels = []; $revData = [];
        if ($spanDays <= 45) {
            $rows = Payment::where('school_id', $sid)->where('status', 'confirmed')
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('DATE(payment_date) as d, SUM(amount) as total')
                ->groupBy('d')->pluck('total', 'd');
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $revLabels[] = $d->format('d/m');
                $revData[]   = (float) ($rows[$d->toDateString()] ?? 0);
            }
        } else {
            $rows = Payment::where('school_id', $sid)->where('status', 'confirmed')
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') as m, SUM(amount) as total")
                ->groupBy('m')->pluck('total', 'm');
            for ($d = $start->copy()->startOfMonth(); $d->lte($end); $d->addMonth()) {
                $revLabels[] = $d->translatedFormat('M Y');
                $revData[]   = (float) ($rows[$d->format('Y-m')] ?? 0);
            }
        }

        // ── Payments by method (doughnut) ─────────────────────────────────────
        $methodMap = ['cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money'];
        $methodRows = (clone $paymentsPeriod)->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')->pluck('total', 'payment_method');
        $methodLabels = []; $methodData = [];
        foreach ($methodRows as $m => $t) { $methodLabels[] = $methodMap[$m] ?? ucfirst((string) $m); $methodData[] = (float) $t; }

        // ── Invoices by status (doughnut) ─────────────────────────────────────
        $statusMap = ['draft' => 'Brouillon', 'issued' => 'Émise', 'partially_paid' => 'Partielle', 'paid' => 'Payée', 'cancelled' => 'Annulée', 'overdue' => 'En retard'];
        $statusRows = Invoice::where('school_id', $sid)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $statusLabels = []; $statusData = [];
        foreach ($statusRows as $s => $c) { $statusLabels[] = $statusMap[$s] ?? ucfirst((string) $s); $statusData[] = (int) $c; }

        // ── Students by cycle (bar) ───────────────────────────────────────────
        $byCycle = Enrollment::where('school_id', $sid)
            ->when($year, fn($q) => $q->where('academic_year_id', $year->id))
            ->where('status', 'confirmed')
            ->with('grade.academicCycle')
            ->get()
            ->groupBy(fn($e) => $e->grade?->academicCycle?->name ?? '—')
            ->map->count();
        $cycleLabels = $byCycle->keys()->all();
        $cycleData   = $byCycle->values()->all();

        // ── Attendance breakdown (pie) ────────────────────────────────────────
        $attMap = ['present' => 'Présent', 'absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Excusé'];
        $attRows = AttendanceEntry::whereHas('session', fn($q) =>
                $q->where('school_id', $sid)->whereBetween('session_date', [$start->toDateString(), $end->toDateString()]))
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $attLabels = []; $attData = [];
        foreach ($attRows as $s => $c) { $attLabels[] = $attMap[$s] ?? ucfirst((string) $s); $attData[] = (int) $c; }

        // ── Chart configs ─────────────────────────────────────────────────────
        $pal      = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];
        $baseOpts = ['responsive' => true, 'maintainAspectRatio' => false];

        $charts = [
            'revenue' => [
                'type' => 'line',
                'data' => ['labels' => $revLabels, 'datasets' => [[
                    'label' => 'Encaissé (DJF)', 'data' => $revData,
                    'borderColor' => '#10b981', 'backgroundColor' => 'rgba(16,185,129,0.15)',
                    'fill' => true, 'tension' => 0.35, 'borderWidth' => 2, 'pointRadius' => 2,
                ]]],
                'options' => $baseOpts + ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true]]],
            ],
            'methods' => [
                'type' => 'doughnut',
                'data' => ['labels' => $methodLabels, 'datasets' => [['data' => $methodData, 'backgroundColor' => array_slice($pal, 0, max(1, count($methodData))), 'borderWidth' => 0]]],
                'options' => $baseOpts + ['cutout' => '62%', 'plugins' => ['legend' => ['position' => 'bottom']]],
            ],
            'status' => [
                'type' => 'doughnut',
                'data' => ['labels' => $statusLabels, 'datasets' => [['data' => $statusData, 'backgroundColor' => ['#94a3b8', '#38bdf8', '#f59e0b', '#22c55e', '#e11d48', '#ef4444'], 'borderWidth' => 0]]],
                'options' => $baseOpts + ['cutout' => '62%', 'plugins' => ['legend' => ['position' => 'bottom']]],
            ],
            'cycle' => [
                'type' => 'bar',
                'data' => ['labels' => $cycleLabels, 'datasets' => [['label' => 'Élèves', 'data' => $cycleData, 'backgroundColor' => '#6366f1', 'borderRadius' => 6]]],
                'options' => $baseOpts + ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]],
            ],
            'attendance' => [
                'type' => 'pie',
                'data' => ['labels' => $attLabels, 'datasets' => [['data' => $attData, 'backgroundColor' => ['#22c55e', '#ef4444', '#f59e0b', '#38bdf8'], 'borderWidth' => 0]]],
                'options' => $baseOpts + ['plugins' => ['legend' => ['position' => 'bottom']]],
            ],
        ];

        return [
            'year'            => $year,
            'rangeLabel'      => $start->translatedFormat('d M Y') . ' – ' . $end->translatedFormat('d M Y'),
            'revenuePeriod'   => $revenuePeriod,
            'paymentsCount'   => $paymentsCount,
            'newEnrollments'  => $newEnrollments,
            'totalStudents'   => $totalStudents,
            'totalBalance'    => $totalBalance,
            'overdueCount'    => $overdueCount,
            'charts'          => $charts,
            'chartKey'        => substr(md5($this->preset . $start . $end), 0, 10),
            'hasRevenue'      => array_sum($revData) > 0,
            'hasMethods'      => count($methodData) > 0,
            'hasAttendance'   => count($attData) > 0,
            'recentPayments'  => Payment::where('school_id', $sid)->with('student')->orderByDesc('payment_date')->limit(6)->get(),
            'recentAnnouncements' => Announcement::where('school_id', $sid)->where('is_published', true)->orderByDesc('published_at')->limit(4)->get(),
        ];
    }
};
?>

<div class="p-4 space-y-6">

    {{-- Header + date filter --}}
    <x-header :title="__('navigation.dashboard')" :subtitle="$rangeLabel" separator>
        <x-slot:actions>
            <div class="flex flex-wrap items-end gap-2">
                <x-select wire:model.live="preset" class="select-sm w-44"
                          :options="[
                            ['id' => 'this_month',  'name' => 'Ce mois-ci'],
                            ['id' => 'last_month',  'name' => 'Mois dernier'],
                            ['id' => 'last30',      'name' => '30 derniers jours'],
                            ['id' => 'year',        'name' => 'Cette année'],
                            ['id' => 'school_year', 'name' => 'Année scolaire'],
                            ['id' => 'custom',      'name' => 'Personnalisé'],
                          ]" />
                @if($preset === 'custom')
                <input type="date" wire:model.live="from" class="input input-bordered input-sm" />
                <input type="date" wire:model.live="to" class="input input-bordered input-sm" />
                @endif
                @if($year)<x-badge value="{{ $year->name }}" class="badge-primary badge-outline" />@endif
            </div>
        </x-slot:actions>
    </x-header>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach([
            ['label' => 'Encaissé (période)', 'value' => number_format($revenuePeriod) . ' DJF', 'icon' => 'o-banknotes',            'grad' => 'from-emerald-500 to-teal-600'],
            ['label' => 'Paiements (période)','value' => number_format($paymentsCount),           'icon' => 'o-credit-card',          'grad' => 'from-cyan-500 to-sky-600'],
            ['label' => 'Solde impayé',       'value' => number_format($totalBalance) . ' DJF',   'icon' => 'o-exclamation-triangle', 'grad' => 'from-amber-500 to-orange-600'],
            ['label' => 'Nouvelles inscr.',   'value' => number_format($newEnrollments),          'icon' => 'o-user-plus',            'grad' => 'from-indigo-500 to-violet-600'],
        ] as $kpi)
        <div class="relative p-5 overflow-hidden bg-base-100 shadow-lg rounded-2xl">
            <div class="absolute top-0 right-0 w-24 h-24 translate-x-1/2 -translate-y-1/2 rounded-full opacity-10 bg-gradient-to-br {{ $kpi['grad'] }}"></div>
            <div class="relative flex items-center gap-4">
                <div class="p-3 rounded-xl bg-gradient-to-br {{ $kpi['grad'] }} shadow">
                    <x-icon name="{{ $kpi['icon'] }}" class="w-6 h-6 text-white"/>
                </div>
                <div>
                    <p class="text-xs font-medium text-base-content/60">{{ $kpi['label'] }}</p>
                    <p class="text-xl font-black text-base-content">{{ $kpi['value'] }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Charts row 1: revenue trend + payments by method --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card title="Évolution des encaissements" subtitle="{{ $rangeLabel }}" class="lg:col-span-2" shadow>
            <div wire:key="chart-revenue-{{ $chartKey }}" class="h-72">
                @if($hasRevenue)
                <canvas x-data x-init="new Chart($el, @js($charts['revenue']))"></canvas>
                @else
                <div class="h-full flex items-center justify-center text-base-content/40 text-sm">Aucun encaissement sur la période.</div>
                @endif
            </div>
        </x-card>

        <x-card title="Modes de paiement" shadow>
            <div wire:key="chart-methods-{{ $chartKey }}" class="h-72">
                @if($hasMethods)
                <canvas x-data x-init="new Chart($el, @js($charts['methods']))"></canvas>
                @else
                <div class="h-full flex items-center justify-center text-base-content/40 text-sm">Aucune donnée.</div>
                @endif
            </div>
        </x-card>
    </div>

    {{-- Charts row 2: invoices status + students by cycle + attendance --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card title="Factures par statut" shadow>
            <div wire:key="chart-status-{{ $chartKey }}" class="h-64">
                <canvas x-data x-init="new Chart($el, @js($charts['status']))"></canvas>
            </div>
        </x-card>

        <x-card title="Élèves par cycle" shadow>
            <div wire:key="chart-cycle-{{ $chartKey }}" class="h-64">
                <canvas x-data x-init="new Chart($el, @js($charts['cycle']))"></canvas>
            </div>
        </x-card>

        <x-card title="Présences (période)" shadow>
            <div wire:key="chart-att-{{ $chartKey }}" class="h-64">
                @if($hasAttendance)
                <canvas x-data x-init="new Chart($el, @js($charts['attendance']))"></canvas>
                @else
                <div class="h-full flex items-center justify-center text-base-content/40 text-sm">Aucune présence enregistrée.</div>
                @endif
            </div>
        </x-card>
    </div>

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card :title="__('navigation.payments')" separator shadow>
            @forelse($recentPayments as $payment)
            <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0">
                <div>
                    <p class="font-semibold text-sm">{{ $payment->student->full_name ?? '—' }}</p>
                    <p class="text-xs text-base-content/60">{{ $payment->payment_date?->format('d/m/Y') }} — {{ $payment->reference }}</p>
                </div>
                <span class="font-bold text-emerald-600">{{ number_format($payment->amount) }} DJF</span>
            </div>
            @empty
            <x-alert icon="o-information-circle" class="alert-info">Aucun paiement récent.</x-alert>
            @endforelse
        </x-card>

        <x-card :title="__('navigation.announcements')" separator shadow>
            @forelse($recentAnnouncements as $ann)
            <div class="py-2 border-b border-base-200 last:border-0">
                <div class="flex items-center gap-2 mb-1">
                    <x-badge :value="$ann->level->label()" :class="'badge-' . $ann->level->color()" />
                    @if($ann->is_pinned)<x-icon name="o-map-pin" class="w-3 h-3 text-base-content/40" />@endif
                </div>
                <p class="font-semibold text-sm">{{ $ann->title }}</p>
                <p class="text-xs text-base-content/60 line-clamp-1">{{ $ann->body }}</p>
            </div>
            @empty
            <x-alert icon="o-information-circle" class="alert-info">Aucune annonce.</x-alert>
            @endforelse
        </x-card>
    </div>
</div>
