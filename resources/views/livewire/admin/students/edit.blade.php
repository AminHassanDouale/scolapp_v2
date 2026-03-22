<?php
use App\Models\Student;
use App\Models\Guardian;
use App\Enums\GenderType;
use App\Enums\GuardianRelation;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public Student $student;

    // Student fields
    public string $name           = '';
    public string $gender         = '';
    public string $date_of_birth  = '';
    public string $place_of_birth = '';
    public string $nationality    = 'DJ';
    public string $national_id    = '';
    public string $address        = '';
    public string $blood_type     = '';
    public bool   $has_disability = false;
    public string $disability_notes = '';
    public bool   $is_active      = true;

    // Photo
    public mixed  $photo = null;

    // Guardian add
    public bool   $showAddGuardian  = false;
    public int    $guardianId       = 0;
    public string $guardianRelation = 'father';
    public bool   $guardianIsPrimary = false;
    public bool   $guardianNotifications = true;

    public function mount(string $uuid): void
    {
        $this->student = Student::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with('guardians')
            ->firstOrFail();

        $this->fill([
            'name'            => $this->student->name,
            'gender'          => $this->student->gender?->value ?? '',
            'date_of_birth'   => $this->student->date_of_birth?->format('Y-m-d') ?? '',
            'place_of_birth'  => $this->student->place_of_birth ?? '',
            'nationality'     => $this->student->nationality ?? 'DJ',
            'national_id'     => $this->student->national_id ?? '',
            'address'         => $this->student->address ?? '',
            'blood_type'      => $this->student->blood_type ?? '',
            'has_disability'  => $this->student->has_disability,
            'disability_notes'=> $this->student->disability_notes ?? '',
            'is_active'       => $this->student->is_active,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:200',
            'gender'      => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before:today',
            'nationality' => 'nullable|string|max:5',
            'blood_type'  => 'nullable|string|max:5',
        ]);

        $this->student->update([
            'name'             => $this->name,
            'gender'           => $this->gender ?: null,
            'date_of_birth'    => $this->date_of_birth ?: null,
            'place_of_birth'   => $this->place_of_birth ?: null,
            'nationality'      => $this->nationality,
            'national_id'      => $this->national_id ?: null,
            'address'          => $this->address ?: null,
            'blood_type'       => $this->blood_type ?: null,
            'has_disability'   => $this->has_disability,
            'disability_notes' => $this->has_disability ? $this->disability_notes : null,
            'is_active'        => $this->is_active,
        ]);

        $this->success('Élève mis à jour avec succès.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function uploadPhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:2048|mimes:jpg,jpeg,png,webp']);

        if ($this->student->photo) {
            Storage::disk('public')->delete($this->student->photo);
        }

        $path = $this->photo->store(
            'photos/students/' . auth()->user()->school_id,
            'public'
        );

        $this->student->update(['photo' => $path]);
        $this->photo = null;
        $this->success('Photo mise à jour.', position: 'toast-top toast-end', icon: 'o-camera', css: 'alert-success', timeout: 3000);
    }

    public function removePhoto(): void
    {
        if ($this->student->photo) {
            Storage::disk('public')->delete($this->student->photo);
            $this->student->update(['photo' => null]);
        }
        $this->success('Photo supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function attachGuardian(): void
    {
        $this->validate([
            'guardianId'       => 'required|integer|min:1',
            'guardianRelation' => 'required|string',
        ]);

        // Unset primary on others if this is primary
        if ($this->guardianIsPrimary) {
            $this->student->guardians()->updateExistingPivot(
                $this->student->guardians->pluck('id')->toArray(),
                ['is_primary' => false]
            );
        }

        $this->student->guardians()->syncWithoutDetaching([
            $this->guardianId => [
                'relation'              => $this->guardianRelation,
                'is_primary'            => $this->guardianIsPrimary,
                'has_custody'           => true,
                'can_pickup'            => true,
                'receive_notifications' => $this->guardianNotifications,
            ],
        ]);

        $this->reset(['guardianId', 'guardianRelation', 'guardianIsPrimary', 'guardianNotifications', 'showAddGuardian']);
        $this->student->load('guardians');
        $this->success('Responsable ajouté.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function detachGuardian(int $guardianId): void
    {
        $this->student->guardians()->detach($guardianId);
        $this->student->load('guardians');
        $this->success('Responsable retiré.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        return [
            'genders'   => collect(GenderType::cases())->map(fn($g) => ['id' => $g->value, 'name' => $g->label()])->all(),
            'relations' => collect(GuardianRelation::cases())->map(fn($r) => ['id' => $r->value, 'name' => $r->label()])->all(),
            'availableGuardians' => Guardian::where('school_id', auth()->user()->school_id)
                ->whereNotIn('id', $this->student->guardians->pluck('id'))
                ->orderBy('name')
                ->get()
                ->map(fn($g) => ['id' => $g->id, 'name' => $g->full_name . ' — ' . $g->phone])
                ->all(),
            'bloodTypes' => collect(['A+','A-','B+','B-','AB+','AB-','O+','O-'])
                ->map(fn($b) => ['id' => $b, 'name' => $b])->all(),
        ];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.students.index') }}" wire:navigate class="hover:text-primary">Élèves</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <a href="{{ route('admin.students.show', $student->uuid) }}" wire:navigate class="hover:text-primary">{{ $student->full_name }}</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">Modifier</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="Voir le profil" icon="o-eye"
                      :link="route('admin.students.show', $student->uuid)"
                      class="btn-ghost" wire:navigate />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Main form --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card title="Informations personnelles" separator>
                <x-form wire:submit="save" class="space-y-4">
                    <x-input label="Nom complet *" wire:model="name" required />

                    <div class="grid grid-cols-2 gap-4">
                        <x-select
                            label="Genre"
                            wire:model="gender"
                            :options="$genders"
                            option-value="id"
                            option-label="name"
                            placeholder="Non spécifié"
                            placeholder-value=""
                        />
                        <x-datepicker label="Date de naissance" wire:model="date_of_birth" icon="o-calendar" :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Lieu de naissance" wire:model="place_of_birth" />
                        <x-input label="Nationalité (code)" wire:model="nationality" placeholder="DJ" maxlength="5" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="N° identité nationale" wire:model="national_id" />
                        <x-select
                            label="Groupe sanguin"
                            wire:model="blood_type"
                            :options="$bloodTypes"
                            option-value="id"
                            option-label="name"
                            placeholder="—"
                            placeholder-value=""
                        />
                    </div>

                    <x-input label="Adresse" wire:model="address" />

                    <div class="divider text-xs text-base-content/40">Options</div>

                    <div class="flex items-center gap-6">
                        <x-checkbox label="Élève actif" wire:model="is_active" />
                        <x-checkbox label="Situation de handicap" wire:model.live="has_disability" />
                    </div>

                    @if($has_disability)
                    <x-textarea label="Description du handicap" wire:model="disability_notes" rows="2"
                                placeholder="Précisions sur le handicap ou les aménagements nécessaires..." />
                    @endif

                    <x-slot:actions>
                        <x-button label="Enregistrer les modifications" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">

            {{-- Photo card --}}
            <x-card title="Photo" separator>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-28 h-28 rounded-2xl overflow-hidden bg-base-200 flex items-center justify-center border border-base-300">
                        @if($student->photo)
                        <img src="{{ Storage::url($student->photo) }}" alt="{{ $student->full_name }}"
                             class="w-full h-full object-cover" />
                        @else
                        <span class="text-4xl font-black text-primary">{{ substr($student->name, 0, 1) }}</span>
                        @endif
                    </div>

                    <label class="w-full flex flex-col items-center justify-center h-20 border-2 border-dashed rounded-xl cursor-pointer hover:bg-base-200 transition-colors
                                  {{ $photo ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                        <input type="file" wire:model="photo" class="hidden" accept=".jpg,.jpeg,.png,.webp" />
                        @if($photo)
                        <x-icon name="o-check-circle" class="w-5 h-5 text-primary mb-0.5" />
                        <p class="text-xs font-semibold text-primary truncate max-w-full px-2">{{ $photo->getClientOriginalName() }}</p>
                        @else
                        <x-icon name="o-camera" class="w-5 h-5 text-base-content/30 mb-0.5" />
                        <p class="text-xs text-base-content/50">JPG, PNG, WebP — max 2 MB</p>
                        @endif
                    </label>
                    @error('photo') <p class="text-error text-xs">{{ $message }}</p> @enderror

                    <div class="flex gap-2 w-full">
                        <x-button label="Enregistrer" wire:click="uploadPhoto"
                                  icon="o-check" class="btn-primary btn-sm flex-1"
                                  spinner="uploadPhoto" :disabled="!$photo" />
                        @if($student->photo)
                        <x-button icon="o-trash" wire:click="removePhoto"
                                  wire:confirm="Supprimer la photo ?"
                                  class="btn-ghost btn-sm text-error" />
                        @endif
                    </div>
                </div>
            </x-card>

            <x-card title="Responsables légaux" separator>
                @forelse($student->guardians as $guardian)
                <div class="flex items-center gap-3 py-3 border-b border-base-200 last:border-0">
                    <div class="w-10 h-10 rounded-full bg-secondary/20 flex items-center justify-center font-bold text-secondary">
                        {{ substr($guardian->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate">{{ $guardian->full_name }}</p>
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-xs text-base-content/60">{{ $guardian->pivot->relation }}</span>
                            @if($guardian->pivot->is_primary)
                            <x-badge value="Principal" class="badge-primary badge-xs" />
                            @endif
                        </div>
                        @if($guardian->phone)
                        <p class="text-xs text-base-content/60">{{ $guardian->phone }}</p>
                        @endif
                    </div>
                    <button wire:click="detachGuardian({{ $guardian->id }})"
                            wire:confirm="Retirer ce responsable ?"
                            class="btn btn-ghost btn-xs text-error">
                        <x-icon name="o-x-mark" class="w-3.5 h-3.5"/>
                    </button>
                </div>
                @empty
                <x-alert icon="o-information-circle" class="alert-info text-sm mb-3">
                    Aucun responsable enregistré.
                </x-alert>
                @endforelse

                <x-button
                    label="Ajouter un responsable"
                    icon="o-plus"
                    wire:click="$set('showAddGuardian', true)"
                    class="btn-outline btn-sm w-full mt-3"
                />
            </x-card>

            {{-- Quick info --}}
            <x-card>
                <div class="space-y-2 text-sm">
                    @if($student->student_code)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Code élève</span>
                        <span class="font-mono font-bold">{{ $student->student_code }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Référence</span>
                        <span class="font-mono text-xs">{{ $student->reference }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Créé le</span>
                        <span>{{ $student->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Add Guardian Modal --}}
    <x-modal wire:model="showAddGuardian" title="Ajouter un responsable" separator>
        <x-form wire:submit="attachGuardian" class="space-y-4">
            <x-select
                label="Responsable *"
                wire:model="guardianId"
                :options="$availableGuardians"
                option-value="id"
                option-label="name"
                placeholder="Rechercher un responsable..."
                placeholder-value="0"
                required
            />

            <x-select
                label="Relation *"
                wire:model="guardianRelation"
                :options="$relations"
                option-value="id"
                option-label="name"
                required
            />

            <div class="flex gap-4">
                <x-checkbox label="Responsable principal" wire:model="guardianIsPrimary" />
                <x-checkbox label="Recevoir les notifications" wire:model="guardianNotifications" />
            </div>

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showAddGuardian = false" class="btn-ghost" />
                <x-button label="Ajouter" type="submit" icon="o-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
