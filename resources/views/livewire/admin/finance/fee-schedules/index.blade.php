<?php
use App\Models\FeeSchedule;
use App\Models\FeeItem;
use App\Models\Grade;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public bool  $showCreate = false;
    public bool  $showEdit   = false;
    public int   $editId     = 0;

    // Form fields
    public string $cf_name      = '';
    public int    $cf_grade_id  = 0;
    public bool   $cf_is_active = true;
    public bool   $cf_is_default = false;
    public array  $cf_items     = [];   // [['fee_item_id' => 0, 'amount' => 0]]

    public function mount(): void
    {
        $this->cf_items = [['fee_item_id' => 0, 'amount' => 0]];
    }

    public function addItem(): void
    {
        $this->cf_items[] = ['fee_item_id' => 0, 'amount' => 0];
    }

    public function removeItem(int $index): void
    {
        array_splice($this->cf_items, $index, 1);
        if (empty($this->cf_items)) {
            $this->cf_items = [['fee_item_id' => 0, 'amount' => 0]];
        }
    }

    public function createSchedule(): void
    {
        $this->validate([
            'cf_name'     => 'required|string|max:200',
            'cf_grade_id' => 'required|integer|min:1',
        ]);

        $schedule = FeeSchedule::create([
            'school_id'     => auth()->user()->school_id,
            'name'          => $this->cf_name,
            'grade_id'      => $this->cf_grade_id,
            'schedule_type' => 'yearly',   // always annual reference
            'is_default'    => $this->cf_is_default,
            'is_active'     => $this->cf_is_active,
        ]);

        foreach ($this->cf_items as $item) {
            if ($item['fee_item_id'] && $item['amount'] > 0) {
                $schedule->feeItems()->attach($item['fee_item_id'], ['amount' => $item['amount']]);
            }
        }

        $this->resetForm();
        $this->showCreate = false;
        $this->success('Barème créé.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function editSchedule(int $id): void
    {
        $schedule             = FeeSchedule::with('feeItems')->findOrFail($id);
        $this->editId         = $id;
        $this->cf_name        = $schedule->name;
        $this->cf_grade_id    = $schedule->grade_id ?? 0;
        $this->cf_is_active   = $schedule->is_active;
        $this->cf_is_default  = $schedule->is_default;
        $this->cf_items       = $schedule->feeItems->map(fn($i) => [
            'fee_item_id' => $i->id,
            'amount'      => $i->pivot->amount,
        ])->toArray();
        if (empty($this->cf_items)) {
            $this->cf_items = [['fee_item_id' => 0, 'amount' => 0]];
        }
        $this->showEdit = true;
    }

    public function updateSchedule(): void
    {
        $this->validate([
            'cf_name'     => 'required|string|max:200',
            'cf_grade_id' => 'required|integer|min:1',
        ]);

        $schedule = FeeSchedule::findOrFail($this->editId);
        $schedule->update([
            'name'       => $this->cf_name,
            'grade_id'   => $this->cf_grade_id,
            'is_active'  => $this->cf_is_active,
            'is_default' => $this->cf_is_default,
        ]);

        $sync = [];
        foreach ($this->cf_items as $item) {
            if ($item['fee_item_id'] && $item['amount'] > 0) {
                $sync[$item['fee_item_id']] = ['amount' => $item['amount']];
            }
        }
        $schedule->feeItems()->sync($sync);

        $this->showEdit = false;
        $this->resetForm();
        $this->success('Barème mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function deleteSchedule(int $id): void
    {
        FeeSchedule::findOrFail($id)->delete();
        $this->success('Barème supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    private function resetForm(): void
    {
        $this->cf_name       = '';
        $this->cf_grade_id   = 0;
        $this->cf_is_active  = true;
        $this->cf_is_default = false;
        $this->cf_items      = [['fee_item_id' => 0, 'amount' => 0]];
        $this->editId        = 0;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $feeSchedules = FeeSchedule::where('school_id', $schoolId)
            ->with(['feeItems', 'grade.academicCycle'])
            ->orderBy('name')
            ->get();

        $byGrade = $feeSchedules->groupBy('grade_id');

        return [
            'feeSchedules' => $feeSchedules,
            'byGrade'      => $byGrade,
            'grades'       => Grade::where('school_id', $schoolId)->with('academicCycle')->orderBy('name')->get(),
            'feeItems'     => FeeItem::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <x-header title="Barèmes de frais" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouveau barème" icon="o-plus"
                      wire:click="$set('showCreate', true)" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- Info banner --}}
    <x-alert icon="o-information-circle" class="alert-info mb-6">
        <span>Chaque niveau a un barème annuel. Le mode de paiement (mensuel, trimestriel…) est choisi à l'inscription — les factures sont générées automatiquement en divisant les montants annuels.</span>
    </x-alert>

    @if($feeSchedules->isEmpty())
    <div class="text-center py-20 text-base-content/40">
        <x-icon name="o-document-currency-dollar" class="w-16 h-16 mx-auto mb-3 opacity-20" />
        <p class="font-bold text-lg">Aucun barème configuré</p>
        <p class="text-sm mt-1 mb-4">Créez un barème par niveau scolaire avec les frais annuels.</p>
        <x-button label="Créer le premier barème" icon="o-plus"
                  wire:click="$set('showCreate', true)" class="btn-primary" />
    </div>
    @else

    {{-- Schedules grouped by grade --}}
    @foreach($byGrade as $gradeId => $schedules)
    @php $gradeName = $schedules->first()->grade?->name ?? 'Sans niveau'; @endphp
    <div wire:key="grade-group-{{ $gradeId }}" class="mb-8">
        <h2 class="text-base font-bold mb-3 flex items-center gap-2 text-base-content/70">
            <x-icon name="o-academic-cap" class="w-4 h-4 text-primary" />
            {{ $gradeName }}
            <span class="text-xs font-normal text-base-content/40">— {{ $schedules->first()->grade?->academicCycle?->name }}</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($schedules as $schedule)
            @php $annual = $schedule->feeItems->sum(fn($i) => $i->pivot->amount ?? 0); @endphp
            <x-card wire:key="schedule-{{ $schedule->id }}">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            @if($schedule->is_active)
                            <x-badge value="Actif" class="badge-success badge-xs" />
                            @else
                            <x-badge value="Inactif" class="badge-ghost badge-xs" />
                            @endif
                            @if($schedule->is_default)
                            <x-badge value="Défaut" class="badge-primary badge-xs" />
                            @endif
                        </div>
                        <h3 class="font-bold truncate">{{ $schedule->name }}</h3>
                        <p class="text-lg font-black text-primary mt-0.5">
                            {{ number_format($annual, 0, ',', ' ') }} DJF <span class="text-xs font-normal text-base-content/50">/ an</span>
                        </p>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <x-button icon="o-pencil" wire:click="editSchedule({{ $schedule->id }})"
                                  class="btn-ghost btn-xs" tooltip="Modifier" />
                        <x-button icon="o-trash" wire:click="deleteSchedule({{ $schedule->id }})"
                                  wire:confirm="Supprimer ce barème ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </div>

                {{-- Fee items --}}
                <div class="space-y-1 border-t border-base-200 pt-2">
                    @foreach($schedule->feeItems as $item)
                    <div wire:key="fitem-{{ $item->id }}" class="flex justify-between text-sm py-0.5">
                        <span class="text-base-content/60">{{ $item->name }}</span>
                        <span class="font-semibold">{{ number_format($item->pivot->amount ?? 0, 0, ',', ' ') }} DJF</span>
                    </div>
                    @endforeach
                    @if($schedule->feeItems->isEmpty())
                    <p class="text-xs text-base-content/40 italic">Aucun poste de frais</p>
                    @endif
                </div>

                {{-- Installment preview --}}
                @if($annual > 0)
                <div class="mt-3 pt-2 border-t border-base-200">
                    <p class="text-xs text-base-content/40 mb-1.5 font-semibold uppercase tracking-wider">Par versement</p>
                    <div class="grid grid-cols-2 gap-1">
                        @foreach(\App\Enums\FeeScheduleType::cases() as $freq)
                        <div class="text-xs flex justify-between bg-base-200/50 rounded px-2 py-1">
                            <span class="text-base-content/50">{{ $freq->label() }}</span>
                            <span class="font-semibold">{{ number_format(round($annual / $freq->installments()), 0, ',', ' ') }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </x-card>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif

    {{-- Create Modal --}}
    <x-modal wire:model="showCreate" title="Nouveau barème" separator class="max-w-2xl">
        <x-form wire:submit="createSchedule" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom du barème *" wire:model="cf_name" required class="col-span-2" />
                <x-select label="Niveau *" wire:model="cf_grade_id"
                          :options="$grades->map(fn($g) => ['id' => $g->id, 'name' => $g->name . ($g->academicCycle ? ' (' . $g->academicCycle->name . ')' : '')])->all()"
                          option-value="id" option-label="name"
                          placeholder="Choisir un niveau..." placeholder-value="0" />
                <div class="flex flex-col gap-2 justify-end pb-1">
                    <x-checkbox label="Actif" wire:model="cf_is_active" />
                    <x-checkbox label="Barème par défaut" wire:model="cf_is_default" />
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="font-semibold text-sm">Postes de frais annuels</p>
                    <x-button label="Ajouter" icon="o-plus" wire:click="addItem" class="btn-ghost btn-xs" />
                </div>
                <div class="space-y-2">
                    @foreach($cf_items as $i => $item)
                    <div wire:key="ci-{{ $i }}" class="flex items-center gap-2">
                        <x-select wire:model="cf_items.{{ $i }}.fee_item_id"
                                  :options="$feeItems" option-value="id" option-label="name"
                                  placeholder="Poste..." placeholder-value="0" class="flex-1" />
                        <x-input wire:model="cf_items.{{ $i }}.amount"
                                 type="number" min="0" placeholder="Montant annuel (DJF)" class="w-44" />
                        <x-button icon="o-x-mark" wire:click="removeItem({{ $i }})"
                                  class="btn-ghost btn-xs text-error" />
                    </div>
                    @endforeach
                </div>
                @php $total = collect($cf_items)->sum(fn($i) => (float)($i['amount'] ?? 0)); @endphp
                @if($total > 0)
                <div class="flex justify-end mt-2 pt-2 border-t border-base-200">
                    <p class="font-bold text-primary">Total annuel : {{ number_format($total, 0, ',', ' ') }} DJF</p>
                </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCreate = false" class="btn-ghost" />
                <x-button label="Créer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Edit Modal --}}
    <x-modal wire:model="showEdit" title="Modifier le barème" separator class="max-w-2xl">
        <x-form wire:submit="updateSchedule" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom du barème *" wire:model="cf_name" required class="col-span-2" />
                <x-select label="Niveau *" wire:model="cf_grade_id"
                          :options="$grades->map(fn($g) => ['id' => $g->id, 'name' => $g->name . ($g->academicCycle ? ' (' . $g->academicCycle->name . ')' : '')])->all()"
                          option-value="id" option-label="name"
                          placeholder="Choisir un niveau..." placeholder-value="0" />
                <div class="flex flex-col gap-2 justify-end pb-1">
                    <x-checkbox label="Actif" wire:model="cf_is_active" />
                    <x-checkbox label="Barème par défaut" wire:model="cf_is_default" />
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="font-semibold text-sm">Postes de frais annuels</p>
                    <x-button label="Ajouter" icon="o-plus" wire:click="addItem" class="btn-ghost btn-xs" />
                </div>
                <div class="space-y-2">
                    @foreach($cf_items as $i => $item)
                    <div wire:key="ei-{{ $i }}" class="flex items-center gap-2">
                        <x-select wire:model="cf_items.{{ $i }}.fee_item_id"
                                  :options="$feeItems" option-value="id" option-label="name"
                                  placeholder="Poste..." placeholder-value="0" class="flex-1" />
                        <x-input wire:model="cf_items.{{ $i }}.amount"
                                 type="number" min="0" placeholder="Montant annuel (DJF)" class="w-44" />
                        <x-button icon="o-x-mark" wire:click="removeItem({{ $i }})"
                                  class="btn-ghost btn-xs text-error" />
                    </div>
                    @endforeach
                </div>
                @php $total = collect($cf_items)->sum(fn($i) => (float)($i['amount'] ?? 0)); @endphp
                @if($total > 0)
                <div class="flex justify-end mt-2 pt-2 border-t border-base-200">
                    <p class="font-bold text-primary">Total annuel : {{ number_format($total, 0, ',', ' ') }} DJF</p>
                </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showEdit = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
