<?php
use App\Models\FeeSchedule;
use App\Models\FeeItem;
use App\Enums\FeeScheduleType;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $name        = '';
    public string $type        = '';
    public bool   $is_active   = true;
    public string $description = '';

    // Dynamic fee items
    public array $items = [
        ['fee_item_id' => 0, 'amount' => '', 'due_offset_days' => 0],
    ];

    public function addItem(): void
    {
        $this->items[] = ['fee_item_id' => 0, 'amount' => '', 'due_offset_days' => 0];
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        if (empty($this->items)) {
            $this->items = [['fee_item_id' => 0, 'amount' => '', 'due_offset_days' => 0]];
        }
    }

    public function save(): void
    {
        $this->validate([
            'name'             => 'required|string|max:100',
            'type'             => 'nullable|string',
            'items.*.amount'   => 'nullable|numeric|min:0',
        ]);

        $schedule = FeeSchedule::create([
            'school_id'   => auth()->user()->school_id,
            'name'        => $this->name,
            'type'        => $this->type ?: null,
            'description' => $this->description ?: null,
            'is_active'   => $this->is_active,
        ]);

        foreach ($this->items as $item) {
            if (!$item['fee_item_id'] || $item['amount'] === '') continue;
            $schedule->feeItems()->attach($item['fee_item_id'], [
                'amount'          => (float)$item['amount'],
                'due_offset_days' => (int)$item['due_offset_days'],
            ]);
        }

        $this->success('Barème créé avec succès.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
        $this->redirectRoute('admin.finance.fee-schedules.show', $schedule->uuid, navigate: true);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        return [
            'feeItems' => FeeItem::where('school_id', $schoolId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn($f) => ['id' => $f->id, 'name' => $f->name])
                ->all(),
            'types' => collect(FeeScheduleType::cases())
                ->map(fn($t) => ['id' => $t->value, 'name' => $t->label()])->all(),
            'total' => collect($this->items)->sum(fn($i) => (float)($i['amount'] ?? 0)),
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
                <span class="text-base-content">Nouveau barème</span>
            </div>
        </x-slot:title>
    </x-header>

    <div class="max-w-2xl">
        <x-form wire:submit="save" class="space-y-6">
            <x-card title="Informations générales" separator>
                <div class="space-y-4">
                    <x-input label="Nom du barème *" wire:model="name"
                             placeholder="Frais de scolarité — Primaire 2025/26" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-select label="Type" wire:model="type"
                                  :options="$types" option-value="id" option-label="name"
                                  placeholder="Non précisé" placeholder-value="" />
                        <div class="flex items-center gap-3 mt-6">
                            <x-checkbox label="Barème actif" wire:model="is_active" />
                        </div>
                    </div>
                    <x-textarea label="Description" wire:model="description" rows="2"
                                placeholder="Optionnel — Description ou notes internes" />
                </div>
            </x-card>

            <x-card title="Postes de frais" separator>
                <div class="space-y-3">
                    @foreach($items as $i => $item)
                    <div class="flex items-end gap-3 p-3 rounded-xl bg-base-200">
                        <div class="flex-1">
                            <x-select label="{{ $i === 0 ? 'Poste *' : '' }}"
                                      wire:model.live="items.{{ $i }}.fee_item_id"
                                      :options="$feeItems" option-value="id" option-label="name"
                                      placeholder="Choisir un poste..." placeholder-value="0" />
                        </div>
                        <div class="w-32">
                            <x-input label="{{ $i === 0 ? 'Montant (DJF)' : '' }}"
                                     wire:model.live="items.{{ $i }}.amount"
                                     type="number" min="0" step="100"
                                     placeholder="0" />
                        </div>
                        <div class="w-24">
                            <x-input label="{{ $i === 0 ? 'Délai (j)' : '' }}"
                                     wire:model="items.{{ $i }}.due_offset_days"
                                     type="number" min="0" placeholder="0" />
                        </div>
                        <x-button icon="o-trash" wire:click="removeItem({{ $i }})"
                                  class="btn-ghost btn-sm text-error mb-0.5" />
                    </div>
                    @endforeach

                    <x-button label="Ajouter un poste" icon="o-plus"
                              wire:click="addItem" class="btn-outline btn-sm" />
                </div>

                {{-- Total preview --}}
                <div class="mt-4 flex justify-end">
                    <div class="rounded-xl bg-primary/10 px-4 py-2 text-right">
                        <p class="text-xs text-base-content/60">Total</p>
                        <p class="text-xl font-black text-primary">{{ number_format($total, 0, ',', ' ') }} DJF</p>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.finance.fee-schedules.index') }}" wire:navigate>
                    <x-button label="Annuler" icon="o-arrow-left" class="btn-outline" />
                </a>
                <x-button label="Créer le barème" type="submit" icon="o-check"
                          class="btn-primary" spinner />
            </div>
        </x-form>
    </div>
</div>
