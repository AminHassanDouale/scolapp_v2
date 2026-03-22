<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;

new #[Layout('layouts.caissier')] class extends Component {
    use Toast;

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $today    = today();

        $todayPayments   = Payment::where('school_id', $schoolId)->whereDate('payment_date', $today)->sum('amount');
        $todayCount      = Payment::where('school_id', $schoolId)->whereDate('payment_date', $today)->count();
        $pendingInvoices = Invoice::where('school_id', $schoolId)->where('status', 'unpaid')->count();
        $overdueInvoices = Invoice::where('school_id', $schoolId)->where('status', 'unpaid')->where('due_date', '<', $today)->count();

        $recentPayments = Payment::where('school_id', $schoolId)
            ->with(['invoice.enrollment.student'])
            ->orderByDesc('payment_date')
            ->limit(8)
            ->get();

        $monthlyTotal = Payment::where('school_id', $schoolId)
            ->whereMonth('payment_date', $today->month)
            ->whereYear('payment_date', $today->year)
            ->sum('amount');

        return compact('todayPayments', 'todayCount', 'pendingInvoices', 'overdueInvoices', 'recentPayments', 'monthlyTotal');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.caissier_portal') }}" subtitle="{{ now()->isoFormat('dddd D MMMM Y') }}" separator>
        <x-slot:actions>
            <x-badge value="{{ __('navigation.caissier') }}" class="badge-info badge-lg" />
        </x-slot:actions>
    </x-header>

    {{-- Welcome banner --}}
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #22d3ee 100%)">
        <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/10 -translate-y-10 translate-x-10"></div>
        <div class="relative">
            <p class="text-cyan-100 text-sm font-medium">{{ __('navigation.welcome_back') }}</p>
            <h2 class="text-2xl font-black mt-1">{{ auth()->user()->full_name }}</h2>
            <p class="text-cyan-100 mt-1">Caisse du jour · {{ now()->format('d/m/Y') }}</p>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-cyan-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-100 flex items-center justify-center">
                    <x-icon name="o-banknotes" class="w-5 h-5 text-cyan-600" />
                </div>
                <div>
                    <p class="text-lg font-black text-cyan-700">{{ number_format($todayPayments, 0, ',', ' ') }}</p>
                    <p class="text-xs text-base-content/60">Encaissé aujourd'hui (DJF)</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-teal-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
                    <x-icon name="o-credit-card" class="w-5 h-5 text-teal-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-teal-700">{{ $todayCount }}</p>
                    <p class="text-xs text-base-content/60">Paiements du jour</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-orange-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center">
                    <x-icon name="o-clock" class="w-5 h-5 text-orange-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-orange-700">{{ $pendingInvoices }}</p>
                    <p class="text-xs text-base-content/60">Factures en attente</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-red-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-red-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-red-700">{{ $overdueInvoices }}</p>
                    <p class="text-xs text-base-content/60">Factures en retard</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Monthly total banner --}}
    <x-card class="border-0 bg-gradient-to-r from-cyan-600 to-teal-600 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-cyan-100 text-sm">Total encaissé ce mois</p>
                <p class="text-3xl font-black mt-1">{{ number_format($monthlyTotal, 0, ',', ' ') }} DJF</p>
            </div>
            <div class="text-right">
                <a href="{{ route('caissier.report') }}" wire:navigate>
                    <x-button label="Voir le rapport" icon="o-chart-bar-square" class="btn-sm btn-white text-cyan-700" />
                </a>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Quick actions --}}
        <x-card title="Actions rapides" shadow separator>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('caissier.payment') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-cyan-50 hover:bg-cyan-100 transition-colors group">
                    <div class="w-10 h-10 rounded-full bg-cyan-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-credit-card" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-cyan-700">Encaisser</span>
                </a>
                <a href="{{ route('caissier.invoices') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-teal-50 hover:bg-teal-100 transition-colors group">
                    <div class="w-10 h-10 rounded-full bg-teal-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-document-currency-dollar" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-teal-700">Factures</span>
                </a>
                <a href="{{ route('caissier.report') }}" wire:navigate
                   class="flex flex-col items-center gap-2 p-4 rounded-xl bg-indigo-50 hover:bg-indigo-100 transition-colors group">
                    <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <x-icon name="o-chart-bar" class="w-5 h-5 text-white" />
                    </div>
                    <span class="text-xs font-semibold text-indigo-700">Rapport</span>
                </a>
            </div>
        </x-card>

        {{-- Recent payments --}}
        <x-card title="Derniers paiements" shadow separator>
            @forelse($recentPayments as $payment)
            <div class="flex items-center gap-3 py-2 border-b border-base-100 last:border-0">
                <div class="w-8 h-8 rounded-full bg-cyan-100 flex items-center justify-center flex-shrink-0">
                    <x-icon name="o-check-circle" class="w-4 h-4 text-cyan-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $payment->invoice?->enrollment?->student?->full_name }}</p>
                    <p class="text-xs text-base-content/50">{{ $payment->payment_date?->format('d/m/Y H:i') }}</p>
                </div>
                <span class="font-bold text-sm text-cyan-700 flex-shrink-0">{{ number_format($payment->amount, 0, ',', ' ') }}</span>
            </div>
            @empty
            <div class="text-center py-8 text-base-content/40">
                <x-icon name="o-banknotes" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm">Aucun paiement aujourd'hui</p>
            </div>
            @endforelse
        </x-card>
    </div>
</div>
