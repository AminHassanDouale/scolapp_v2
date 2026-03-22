<?php
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Enums\GenderType;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public Teacher $teacher;

    public string $name           = '';
    public string $email          = '';
    public string $phone          = '';
    public string $gender         = '';
    public string $hire_date      = '';
    public string $specialization = '';
    public string $address        = '';
    public string $notes          = '';
    public bool   $is_active      = true;
    public array  $subjectIds     = [];
    public array  $classIds       = [];
    public mixed  $photo          = null;

    public function mount(string $uuid): void
    {
        $this->teacher = Teacher::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with(['subjects', 'schoolClasses'])
            ->firstOrFail();

        $this->fill([
            'name'           => $this->teacher->name,
            'email'          => $this->teacher->email ?? '',
            'phone'          => $this->teacher->phone ?? '',
            'gender'         => $this->teacher->gender?->value ?? '',
            'hire_date'      => $this->teacher->hire_date?->format('Y-m-d') ?? '',
            'specialization' => $this->teacher->specialization ?? '',
            'address'        => $this->teacher->address ?? '',
            'notes'          => $this->teacher->notes ?? '',
            'is_active'      => $this->teacher->is_active,
        ]);

        $this->subjectIds = $this->teacher->subjects->pluck('id')->toArray();
        $this->classIds   = $this->teacher->schoolClasses->pluck('id')->toArray();
    }

    public function save(): void
    {
        $this->validate([
            'name'           => 'required|string|max:200',
            'email'          => 'nullable|email|max:200|unique:teachers,email,' . $this->teacher->id,
            'phone'          => 'nullable|string|max:30',
            'gender'         => 'nullable|in:male,female',
            'hire_date'      => 'nullable|date',
            'specialization' => 'nullable|string|max:200',
        ]);

        $this->teacher->update([
            'name'           => $this->name,
            'email'          => $this->email ?: null,
            'phone'          => $this->phone ?: null,
            'gender'         => $this->gender ?: null,
            'hire_date'      => $this->hire_date ?: null,
            'specialization' => $this->specialization ?: null,
            'address'        => $this->address ?: null,
            'notes'          => $this->notes ?: null,
            'is_active'      => $this->is_active,
        ]);

        $this->teacher->subjects()->sync($this->subjectIds);
        $this->teacher->schoolClasses()->sync($this->classIds);

        $this->success('Enseignant mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function uploadPhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:2048|mimes:jpg,jpeg,png,webp']);

        if ($this->teacher->photo) {
            Storage::disk('public')->delete($this->teacher->photo);
        }

        $path = $this->photo->store('photos/teachers/' . auth()->user()->school_id, 'public');
        $this->teacher->update(['photo' => $path]);
        $this->photo = null;
        $this->success('Photo mise à jour.', position: 'toast-top toast-end', icon: 'o-camera', css: 'alert-success', timeout: 3000);
    }

    public function removePhoto(): void
    {
        if ($this->teacher->photo) {
            Storage::disk('public')->delete($this->teacher->photo);
            $this->teacher->update(['photo' => null]);
        }
        $this->success('Photo supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $currentYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first();

        return [
            'subjects' => Subject::where('school_id', $schoolId)->orderBy('name')->get(),
            'classes'  => SchoolClass::where('school_id', $schoolId)
                ->when($currentYear, fn($q) => $q->where('academic_year_id', $currentYear->id))
                ->with(['grade', 'academicYear'])
                ->orderBy('name')
                ->get()
                ->map(fn($c) => (object) ['id' => $c->id, 'name' => $c->name . ' (' . ($c->grade?->name ?? '') . ')']),
            'genders' => collect(GenderType::cases())
                ->map(fn($g) => ['id' => $g->value, 'name' => $g->label()])
                ->all(),
        ];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.teachers.index') }}" wire:navigate class="hover:text-primary">Enseignants</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <a href="{{ route('admin.teachers.show', $teacher->uuid) }}" wire:navigate class="hover:text-primary">{{ $teacher->full_name }}</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">Modifier</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="Voir le profil" icon="o-eye"
                      :link="route('admin.teachers.show', $teacher->uuid)"
                      class="btn-ghost" wire:navigate />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Main form ── --}}
        <div class="lg:col-span-2 space-y-5">

            <x-card title="Informations personnelles" separator>
                <x-form wire:submit="save" class="space-y-4">
                    <x-input label="Nom complet *" wire:model="name" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Email" wire:model="email" type="email" icon="o-envelope" />
                        <x-input label="Téléphone" wire:model="phone" icon="o-phone" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-select label="Genre" wire:model="gender"
                                  :options="$genders" option-value="id" option-label="name"
                                  placeholder="Non spécifié" placeholder-value="" />
                        <x-datepicker label="Date d'embauche" wire:model="hire_date"
                                      icon="o-calendar"
                                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true,'locale'=>['firstDayOfWeek'=>1]]" />
                    </div>
                    <x-input label="Spécialisation" wire:model="specialization"
                             placeholder="Ex : Mathématiques, Physique-Chimie…" icon="o-academic-cap" />
                    <x-input label="Adresse" wire:model="address"
                             placeholder="Quartier, Ville" icon="o-map-pin" />
                    <x-textarea label="Notes" wire:model="notes"
                                placeholder="Informations complémentaires…" rows="3" />

                    <div class="flex items-center justify-between pt-2 border-t border-base-200">
                        <div>
                            <p class="font-semibold text-sm">Compte actif</p>
                            <p class="text-xs text-base-content/50">L'enseignant peut se connecter et être assigné à des classes</p>
                        </div>
                        <input type="checkbox" wire:model="is_active" class="toggle toggle-success" />
                    </div>

                    <x-slot:actions>
                        <x-button label="Enregistrer les modifications" type="submit"
                                  icon="o-check" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>

            {{-- Classes --}}
            <x-card title="Classes assignées" separator>
                <x-choices
                    wire:model="classIds"
                    :options="$classes"
                    option-value="id"
                    option-label="name"
                    placeholder="Chercher une classe..."
                    searchable
                />
                <x-alert icon="o-information-circle" class="alert-info text-xs mt-3">
                    Seules les classes de l'année scolaire en cours sont affichées.
                </x-alert>
                <div class="mt-3">
                    <x-button label="Enregistrer les classes" wire:click="save"
                              icon="o-check" class="btn-outline btn-sm" spinner="save" />
                </div>
            </x-card>

        </div>

        {{-- ── Sidebar ── --}}
        <div class="space-y-5">

            {{-- Photo --}}
            <x-card title="Photo de profil" separator>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-28 h-28 rounded-2xl overflow-hidden bg-base-200 border border-base-300 flex items-center justify-center">
                        @if($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover" />
                        @elseif($teacher->photo_url)
                        <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->full_name }}" class="w-full h-full object-cover" />
                        @else
                        <span class="text-4xl font-black text-primary">{{ strtoupper(substr($teacher->name, 0, 1)) }}</span>
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
                        <p class="text-xs text-base-content/50">JPG, PNG, WebP — max 2 Mo</p>
                        @endif
                    </label>
                    @error('photo') <p class="text-error text-xs w-full">{{ $message }}</p> @enderror

                    <div class="flex gap-2 w-full">
                        <x-button label="Enregistrer" wire:click="uploadPhoto"
                                  icon="o-check" class="btn-primary btn-sm flex-1"
                                  spinner="uploadPhoto" :disabled="!$photo" />
                        @if($teacher->photo)
                        <x-button icon="o-trash" wire:click="removePhoto"
                                  wire:confirm="Supprimer la photo ?"
                                  class="btn-ghost btn-sm text-error" tooltip="Supprimer la photo" />
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- Subjects --}}
            <x-card title="Matières enseignées" separator>
                <x-choices
                    wire:model="subjectIds"
                    :options="$subjects"
                    option-value="id"
                    option-label="name"
                    placeholder="Chercher une matière..."
                    searchable
                />
                @if($subjects->isNotEmpty())
                <div class="mt-3">
                    <x-button label="Enregistrer les matières" wire:click="save"
                              icon="o-check" class="btn-outline btn-sm w-full" spinner="save" />
                </div>
                @endif
            </x-card>

        </div>
    </div>
</div>
