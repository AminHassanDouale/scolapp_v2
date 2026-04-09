<?php
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\DmoneyTransaction;
use App\Models\AcademicYear;
use App\Enums\PaymentStatus;
use App\Enums\InvoiceStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {

    public string $periodFilter = 'year';   // month, quarter, year, custom
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public int    $yearFilter   = 0;

    public function mount(): void
    {
        $this->yearFilter = (int) now()->format('Y');
        $this->dateFrom   = now()->startOfYear()->format('Y-m-d');
        $this->dateTo     = now()->format('Y-m-d');
    }

    public function updatedPeriodFilter(): void
    {
        match($this->periodFilter) {
            'month'   => [$this->dateFrom = now()->startOfMonth()->format('Y-m-d'), $this->dateTo = now()->format('Y-m-d')],
            'quarter' => [$this->dateFrom = now()->startOfQuarter()->format('Y-m-d'), $this->dateTo = now()->format('Y-m-d')],
            'year'    => [$this->dateFrom = now()->startOfYear()->format('Y-m-d'), $this->dateTo = now()->format('Y-m-d')],
            default   => null,
        };
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $from     = $this->dateFrom ?: now()->startOfYear()->format('Y-m-d');
        $to       = $this->dateTo   ?: now()->format('Y-m-d');

        // ── Recettes (confirmed payments + completed D-Money) ─────────────────
        $recettesManual = Payment::where('school_id', $schoolId)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $recettesDmoney = DmoneyTransaction::where('school_id', $schoolId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to . ' 23:59:59'])
            ->sum('amount');

        $totalRecettes = $recettesManual + $recettesDmoney;

        // ── Dépenses ──────────────────────────────────────────────────────────
        $totalDepenses = Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        // ── Solde net ─────────────────────────────────────────────────────────
        $soldeNet = $totalRecettes - $totalDepenses;

        // ── Créances (invoices not yet paid) ──────────────────────────────────
        $totalCreances = Invoice::where('school_id', $schoolId)
            ->whereIn('status', [InvoiceStatus::ISSUED->value, InvoiceStatus::OVERDUE->value, InvoiceStatus::PARTIALLY_PAID->value])
            ->sum('balance_due');

        // ── Monthly chart data (recettes vs dépenses per month) ───────────────
        $months = collect();
        $start  = Carbon::parse($from)->startOfMonth();
        $end    = Carbon::parse($to)->startOfMonth();

        while ($start->lte($end)) {
            $m   = $start->format('Y-m');
            $rec = Payment::where('school_id', $schoolId)
                       ->where('status', PaymentStatus::CONFIRMED->value)
                       ->whereYear('payment_date', $start->year)
                       ->whereMonth('payment_date', $start->month)
                       ->sum('amount')
                 + DmoneyTransaction::where('school_id', $schoolId)
                       ->where('status', 'completed')
                       ->whereYear('completed_at', $start->year)
                       ->whereMonth('completed_at', $start->month)
                       ->sum('amount');

            $dep = Expense::where('school_id', $schoolId)
                       ->whereYear('expense_date', $start->year)
                       ->whereMonth('expense_date', $start->month)
                       ->sum('amount');

            $months->push([
                'month'    => $start->translatedFormat('M y'),
                'recettes' => (float) $rec,
                'depenses' => (float) $dep,
                'solde'    => (float) ($rec - $dep),
            ]);
            $start->addMonth();
        }

        // ── Dépenses par catégorie ─────────────────────────────────────────────
        $byCategory = Expense::where('school_id', $schoolId)
            ->whereBetween('expense_date', [$from, $to])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // ── Recettes par opérateur ─────────────────────────────────────────────
        $byProvider = Payment::where('school_id', $schoolId)
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->whereBetween('payment_date', [$from, $to])
            ->select('meta', DB::raw('SUM(amount) as total, COUNT(*) as count'))
            ->groupBy('meta')
            ->get()
            ->map(function ($p) {
                $meta = is_string($p->meta) ? json_decode($p->meta, true) : ($p->meta ?? []);
                $provider = $meta['provider'] ?? 'autre';
                return ['provider' => $provider, 'total' => $p->total, 'count' => $p->count];
            })
            ->groupBy('provider')
            ->map(fn($g) => ['total' => $g->sum('total'), 'count' => $g->sum('count')]);

        // Add D-Money to provider breakdown
        $dmoneyCount = DmoneyTransaction::where('school_id', $schoolId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to . ' 23:59:59'])
            ->count();
        if ($recettesDmoney > 0) {
            $byProvider->put('d_money', [
                'total' => $recettesDmoney,
                'count' => $dmoneyCount,
            ]);
        }

        // ── Recent expenses ───────────────────────────────────────────────────
        $recentExpenses = Expense::where('school_id', $schoolId)
            ->orderByDesc('expense_date')
            ->limit(8)
            ->get();

        $periodOptions = [
            ['id' => 'month',   'name' => 'Ce mois'],
            ['id' => 'quarter', 'name' => 'Ce trimestre'],
            ['id' => 'year',    'name' => 'Cette année'],
            ['id' => 'custom',  'name' => 'Personnalisé'],
        ];

        return compact(
            'totalRecettes', 'totalDepenses', 'soldeNet', 'totalCreances',
            'months', 'byCategory', 'byProvider', 'recentExpenses', 'periodOptions'
        );
    }
};
?>

<div>
    <x-header title="Comptabilité" subtitle="Tableau de bord financier" separator progress-indicator>
        <x-slot:actions>
            <a href="{{ route('admin.finance.expenses.index') }}" wire:navigate>
                <x-button label="Dépenses" icon="o-arrow-trending-down" class="btn-outline btn-sm" />
            </a>
            <a href="{{ route('admin.finance.payments.suivi') }}" wire:navigate>
                <x-button label="Encaissements" icon="o-arrow-trending-up" class="btn-outline btn-sm" />
            </a>
        </x-slot:actions>
    </x-header>

    {{-- Period filter --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        @foreach($periodOptions as $opt)
        <button wire:click="$set('periodFilter', '{{ $opt['id'] }}')"
                class="btn btn-sm {{ $periodFilter === $opt['id'] ? 'btn-primary' : 'btn-ghost border border-base-300' }}">
            {{ $opt['name'] }}
        </button>
        @endforeach
        @if($periodFilter === 'custom')
        <x-datepicker wire:model.live="dateFrom" placeholder="Du" class="input-sm w-36"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]" />
        <x-datepicker wire:model.live="dateTo" placeholder="Au" class="input-sm w-36"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]" />
        @endif
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Recettes --}}
        <div class="rounded-2xl bg-gradient-to-br from-success to-success/70 p-5 text-success-content">
            <div class="flex items-center gap-2 mb-2">
                <x-icon name="o-arrow-trending-up" class="w-5 h-5 opacity-80" />
                <p class="text-sm font-semibold opacity-80">Recettes</p>
            </div>
            <p class="text-3xl font-black tabular-nums">{{ number_format($totalRecettes, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70 mt-1">DJF — paiements confirmés</p>
        </div>
        {{-- Dépenses --}}
        <div class="rounded-2xl bg-gradient-to-br from-error to-error/70 p-5 text-error-content">
            <div class="flex items-center gap-2 mb-2">
                <x-icon name="o-arrow-trending-down" class="w-5 h-5 opacity-80" />
                <p class="text-sm font-semibold opacity-80">Dépenses</p>
            </div>
            <p class="text-3xl font-black tabular-nums">{{ number_format($totalDepenses, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70 mt-1">DJF — sorties de caisse</p>
        </div>
        {{-- Solde net --}}
        <div class="rounded-2xl p-5 {{ $soldeNet >= 0 ? 'bg-gradient-to-br from-primary to-primary/70 text-primary-content' : 'bg-gradient-to-br from-warning to-warning/70 text-warning-content' }}">
            <div class="flex items-center gap-2 mb-2">
                <x-icon name="o-scale" class="w-5 h-5 opacity-80" />
                <p class="text-sm font-semibold opacity-80">Solde net</p>
            </div>
            <p class="text-3xl font-black tabular-nums">{{ $soldeNet >= 0 ? '+' : '' }}{{ number_format($soldeNet, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70 mt-1">DJF — recettes − dépenses</p>
        </div>
        {{-- Créances --}}
        <div class="rounded-2xl bg-base-200 p-5">
            <div class="flex items-center gap-2 mb-2">
                <x-icon name="o-clock" class="w-5 h-5 text-warning" />
                <p class="text-sm font-semibold text-base-content/60">Créances</p>
            </div>
            <p class="text-3xl font-black tabular-nums text-warning">{{ number_format($totalCreances, 0, ',', ' ') }}</p>
            <p class="text-xs text-base-content/50 mt-1">DJF — soldes impayés</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Monthly table --}}
        <div class="lg:col-span-2">
            <x-card title="Évolution mensuelle" separator>
                <div class="overflow-x-auto">
                    <table class="table table-sm w-full">
                        <thead><tr>
                            <th>Mois</th>
                            <th class="text-right text-success">Recettes</th>
                            <th class="text-right text-error">Dépenses</th>
                            <th class="text-right">Solde</th>
                            <th>Barre</th>
                        </tr></thead>
                        <tbody>
                        @forelse($months as $row)
                        @php $max = max(collect($months)->max('recettes'), collect($months)->max('depenses'), 1); @endphp
                        <tr class="hover">
                            <td class="font-semibold text-sm capitalize">{{ $row['month'] }}</td>
                            <td class="text-right text-success font-bold tabular-nums text-sm">{{ number_format($row['recettes'], 0, ',', ' ') }}</td>
                            <td class="text-right text-error font-bold tabular-nums text-sm">{{ number_format($row['depenses'], 0, ',', ' ') }}</td>
                            <td class="text-right font-black tabular-nums text-sm {{ $row['solde'] >= 0 ? 'text-primary' : 'text-warning' }}">
                                {{ $row['solde'] >= 0 ? '+' : '' }}{{ number_format($row['solde'], 0, ',', ' ') }}
                            </td>
                            <td class="w-24">
                                <div class="flex flex-col gap-0.5">
                                    <div class="h-1.5 rounded-full bg-success/20">
                                        <div class="h-full rounded-full bg-success" style="width:{{ $max > 0 ? min(100, round($row['recettes']/$max*100)) : 0 }}%"></div>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-error/20">
                                        <div class="h-full rounded-full bg-error" style="width:{{ $max > 0 ? min(100, round($row['depenses']/$max*100)) : 0 }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-base-content/40 py-6">Aucune donnée</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- Right column --}}
        <div class="space-y-6">

            {{-- Dépenses par catégorie --}}
            <x-card title="Dépenses par catégorie" separator>
                @forelse($byCategory as $cat)
                @php
                    $catColor = match($cat->category) {
                        'salaires'    => 'bg-purple-500',
                        'loyer'       => 'bg-blue-500',
                        'fournitures' => 'bg-amber-500',
                        'services'    => 'bg-cyan-500',
                        'maintenance' => 'bg-orange-500',
                        default       => 'bg-base-300',
                    };
                    $pct = $totalDepenses > 0 ? round($cat->total / $totalDepenses * 100) : 0;
                @endphp
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-semibold">{{ \App\Models\Expense::categoryLabel($cat->category) }}</span>
                        <span class="tabular-nums text-base-content/60">{{ number_format($cat->total, 0, ',', ' ') }} DJF <span class="text-xs">({{ $pct }}%)</span></span>
                    </div>
                    <div class="h-2 rounded-full bg-base-200">
                        <div class="h-full rounded-full {{ $catColor }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-base-content/40 text-center py-4">Aucune dépense sur la période</p>
                @endforelse
            </x-card>

            {{-- Recettes par opérateur --}}
            <x-card title="Recettes par opérateur" separator>
                @php
                    $providerColors = [
                        'd_money'  => 'bg-emerald-500',
                        'waafi'    => 'bg-green-500',
                        'cac_pay'  => 'bg-red-500',
                        'exim_pay' => 'bg-blue-500',
                        'saba_pay' => 'bg-orange-500',
                        'e_dahab'  => 'bg-yellow-500',
                        'autre'    => 'bg-base-300',
                    ];
                    $providerLabels = [
                        'd_money'  => 'D-Money',
                        'waafi'    => 'Waafi',
                        'cac_pay'  => 'Cac Pay',
                        'exim_pay' => 'Exim Pay',
                        'saba_pay' => 'Saba Pay',
                        'e_dahab'  => 'E-Dahab',
                        'autre'    => 'Autre',
                    ];
                @endphp
                @forelse($byProvider as $key => $data)
                @php $pct = $totalRecettes > 0 ? round($data['total'] / $totalRecettes * 100) : 0; @endphp
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-semibold">{{ $providerLabels[$key] ?? ucfirst($key) }}</span>
                        <span class="tabular-nums text-base-content/60">{{ number_format($data['total'], 0, ',', ' ') }} DJF <span class="text-xs">({{ $pct }}%)</span></span>
                    </div>
                    <div class="h-2 rounded-full bg-base-200">
                        <div class="h-full rounded-full {{ $providerColors[$key] ?? 'bg-base-300' }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-base-content/40 text-center py-4">Aucune recette sur la période</p>
                @endforelse
            </x-card>

        </div>
    </div>

    {{-- Recent expenses --}}
    @if($recentExpenses->isNotEmpty())
    <x-card title="Dernières dépenses" separator class="mt-6">
        <div class="space-y-2">
            @foreach($recentExpenses as $exp)
            @php
                $catColor = match($exp->category) {
                    'salaires'    => 'bg-purple-100 text-purple-700',
                    'loyer'       => 'bg-blue-100 text-blue-700',
                    'fournitures' => 'bg-amber-100 text-amber-700',
                    'services'    => 'bg-cyan-100 text-cyan-700',
                    'maintenance' => 'bg-orange-100 text-orange-700',
                    default       => 'bg-base-200 text-base-content/60',
                };
            @endphp
            <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-base-100 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold {{ $catColor }} shrink-0">
                        {{ \App\Models\Expense::categoryLabel($exp->category) }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold">{{ $exp->label }}</p>
                        <p class="text-xs text-base-content/40">{{ $exp->expense_date->format('d/m/Y') }}</p>
                    </div>
                </div>
                <p class="font-black text-error tabular-nums text-sm shrink-0">-{{ number_format($exp->amount, 0, ',', ' ') }} DJF</p>
            </div>
            @endforeach
        </div>
        <div class="mt-3 text-center">
            <a href="{{ route('admin.finance.expenses.index') }}" wire:navigate class="text-xs text-primary hover:underline">
                Voir toutes les dépenses →
            </a>
        </div>
    </x-card>
    @endif

</div>
