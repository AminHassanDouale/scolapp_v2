<?php
use App\Models\FeeSchedule;
use App\Models\StudentFeePlan;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {

    public FeeSchedule $feeSchedule;

    public function mount(int $uuid): void
    {
        $this->feeSchedule = FeeSchedule::where('school_id', auth()->user()->school_id)
            ->with([
                'feeItems',
                'studentFeePlans.enrollment.student',
                'studentFeePlans.enrollment.schoolClass',
            ])
            ->findOrFail($uuid);
    }

    public function with(): array
    {
        $total = $this->feeSchedule->feeItems->sum(fn($i) => $i->pivot->amount ?? 0);
        $usedCount = $this->feeSchedule->studentFeePlans->count();

        return [
            'total'     => $total,
            'usedCount' => $usedCount,
        ];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.finance.fee-schedules.index') }}" wire:navigate class="hover:text-primary">Barèmes</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">{{ $feeSchedule->name }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="Modifier" icon="o-pencil"
                      wire:click="$dispatch('open-modal', { name: 'edit' })"
                      class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Info cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/70 p-4 text-primary-content">
            <p class="text-sm opacity-80">Montant total</p>
            <p class="text-2xl font-black mt-1">{{ number_format($total, 0, ',', ' ') }}</p>
            <p class="text-xs opacity-70">DJF</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4">
            <p class="text-sm text-base-content/60">Type</p>
            <p class="text-xl font-black mt-1">{{ $feeSchedule->type?->label() ?? $feeSchedule->type }}</p>
        </div>
        <div class="rounded-2xl bg-base-200 p-4">
            <p class="text-sm text-base-content/60">Postes de frais</p>
            <p class="text-xl font-black mt-1">{{ $feeSchedule->feeItems->count() }}</p>
        </div>
        <div class="rounded-2xl {{ $feeSchedule->is_active ? 'bg-success/10' : 'bg-base-200' }} p-4">
            <p class="text-sm text-base-content/60">Statut</p>
            <p class="text-xl font-black mt-1 {{ $feeSchedule->is_active ? 'text-success' : 'text-base-content/40' }}">
                {{ $feeSchedule->is_active ? 'Actif' : 'Inactif' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Tabs --}}
        <div class="lg:col-span-2">
            <x-tabs>
                <x-tab name="items" label="Postes de frais" icon="o-list-bullet">
                    <x-card class="mt-4">
                        @if($feeSchedule->feeItems->count())
                        <div class="overflow-x-auto"><table class="table w-full">
                            <thead><tr>
                                <th>Poste</th>
                                <th class="text-right">Montant (DJF)</th>
                                <th class="text-center">Délai (jours)</th>
                            </tr></thead><tbody>
                            @foreach($feeSchedule->feeItems as $item)
                            <tr class="hover">
                                <td class="font-semibold">{{ $item->name }}</td>
                                <td class="text-right font-bold">{{ number_format($item->pivot->amount ?? 0, 0, ',', ' ') }}</td>
                                <td class="text-center text-base-content/60">{{ $item->pivot->due_offset_days ?? 0 }}</td>
                            </tr>
                            @endforeach
                            <tr class="border-t-2 border-base-300">
                                <td class="font-black">Total</td>
                                <td class="text-right font-black text-primary">{{ number_format($total, 0, ',', ' ') }}</td>
                                <td></td>
                            </tr>
                        </tbody></table></div>
                        @else
                        <div class="text-center py-8 text-base-content/40">
                            <p>Aucun poste de frais défini</p>
                        </div>
                        @endif
                    </x-card>
                </x-tab>

                <x-tab name="students" label="Élèves inscrits ({{ $usedCount }})" icon="o-users">
                    <x-card class="mt-4">
                        @if($feeSchedule->studentFeePlans->count())
                        <div class="overflow-x-auto"><table class="table w-full">
                            <thead><tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Statut inscription</th>
                                <th class="text-right">Remise</th>
                            </tr></thead><tbody>
                            @foreach($feeSchedule->studentFeePlans as $plan)
                            <tr class="hover">
                                <td>
                                    <a href="{{ route('admin.students.show', $plan->enrollment?->student?->uuid) }}"
                                       wire:navigate class="font-semibold hover:text-primary text-sm">
                                        {{ $plan->enrollment?->student?->full_name }}
                                    </a>
                                </td>
                                <td class="text-sm">{{ $plan->enrollment?->schoolClass?->name }}</td>
                                <td>
                                    <x-badge :value="$plan->enrollment?->status?->label() ?? $plan->enrollment?->status"
                                             class="badge-sm badge-outline" />
                                </td>
                                <td class="text-right text-sm">
                                    @if($plan->discount_pct)
                                    <span class="text-success">{{ $plan->discount_pct }}%</span>
                                    @elseif($plan->discount_amount)
                                    <span class="text-success">{{ number_format($plan->discount_amount, 0, ',', ' ') }} DJF</span>
                                    @else
                                    <span class="text-base-content/30">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody></table></div>
                        @else
                        <div class="text-center py-8 text-base-content/40">
                            <p>Aucun élève n'utilise ce barème actuellement.</p>
                        </div>
                        @endif
                    </x-card>
                </x-tab>
            </x-tabs>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <x-card title="Informations">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Nom</span>
                        <span class="font-semibold">{{ $feeSchedule->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Type</span>
                        <span class="font-semibold">{{ $feeSchedule->type?->label() ?? $feeSchedule->type }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Statut</span>
                        @if($feeSchedule->is_active)
                        <x-badge value="Actif" class="badge-success badge-xs" />
                        @else
                        <x-badge value="Inactif" class="badge-ghost badge-xs" />
                        @endif
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Élèves inscrits</span>
                        <span class="font-semibold">{{ $usedCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Créé le</span>
                        <span>{{ $feeSchedule->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-card>

            <a href="{{ route('admin.finance.fee-schedules.index') }}" wire:navigate>
                <x-button label="Retour aux barèmes" icon="o-arrow-left" class="btn-outline w-full" />
            </a>
        </div>
    </div>
</div>
