<?php
use App\Enums\GuardianRelation;
use App\Mail\GuardianWelcomeMail;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    // Personal info
    public string $name        = '';
    public string $email       = '';
    public string $phone       = '';
    public string $phone_secondary = '';
    public string $gender      = '';
    public string $profession  = '';
    public string $national_id = '';
    public string $address     = '';
    public mixed  $photo       = null;

    // Link to student (optional)
    public ?int   $studentId   = null;
    public string $relation    = '';
    public bool   $is_primary  = false;

    public function save(): void
    {
        $this->validate([
            'name'            => 'required|string|max:200',
            'email'           => 'nullable|email|max:200|unique:guardians,email',
            'phone'           => 'nullable|string|max:30',
            'phone_secondary' => 'nullable|string|max:30',
            'gender'          => 'nullable|in:male,female',
            'profession'      => 'nullable|string|max:200',
            'national_id'     => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:500',
            'photo'           => 'nullable|image|max:2048|mimes:jpg,jpeg,png,webp',
            'studentId'       => 'nullable|exists:students,id',
            'relation'        => 'nullable|string',
        ]);

        $schoolId = auth()->user()->school_id;

        $guardian = Guardian::create([
            'school_id'       => $schoolId,
            'name'            => $this->name,
            'email'           => $this->email ?: null,
            'phone'           => $this->phone ?: null,
            'phone_secondary' => $this->phone_secondary ?: null,
            'gender'          => $this->gender ?: null,
            'profession'      => $this->profession ?: null,
            'national_id'     => $this->national_id ?: null,
            'address'         => $this->address ?: null,
            'is_active'       => true,
        ]);

        if ($this->photo) {
            $path = $this->photo->store('photos/guardians/' . $schoolId, 'public');
            $guardian->update(['photo' => $path]);
        }

        // Attach to student if selected
        if ($this->studentId) {
            $guardian->students()->attach($this->studentId, [
                'relation'              => $this->relation ?: GuardianRelation::OTHER->value,
                'is_primary'            => $this->is_primary,
                'has_custody'           => true,
                'can_pickup'            => true,
                'receive_notifications' => true,
            ]);
        }

        // Create user account + send credentials
        $plainPassword = null;
        if ($guardian->email && !User::where('email', $guardian->email)->exists()) {
            $plainPassword = Str::password(12, symbols: false);
            $user = User::create([
                'uuid'       => (string) Str::uuid(),
                'school_id'  => $schoolId,
                'name'       => $guardian->full_name,
                'email'      => $guardian->email,
                'password'   => Hash::make($plainPassword),
                'ui_lang'    => 'fr',
                'timezone'   => 'Africa/Djibouti',
            ]);
            $user->assignRole('guardian');
            $guardian->update(['user_id' => $user->id]);
        }

        if ($guardian->email && $plainPassword) {
            try {
                $school  = School::findOrFail($schoolId);
                $student = $this->studentId
                    ? Student::with(['enrollments.schoolClass', 'enrollments.grade'])->find($this->studentId)
                    : $guardian->students()->with(['enrollments.schoolClass', 'enrollments.grade'])->first();
                if ($student) {
                    $guardian->load('students');
                    Mail::to($guardian->email)->send(
                        new GuardianWelcomeMail($guardian, $school, $student, $plainPassword)
                    );
                }
                session()->flash('success', 'Responsable créé — identifiants envoyés à ' . $guardian->email);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('GuardianWelcomeMail failed: ' . $e->getMessage());
                session()->flash('success', 'Responsable créé avec succès.');
            }
        } else {
            session()->flash('success', 'Responsable créé avec succès.');
        }

        $this->redirect(route('admin.guardians.show', $guardian->uuid));
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        return [
            'relationOptions' => collect(GuardianRelation::cases())
                ->map(fn($r) => ['id' => $r->value, 'name' => $r->label()])
                ->all(),
            'studentOptions'  => Student::where('school_id', $schoolId)
                ->orderBy('name')
                ->get()
                ->map(fn($s) => (object)['id' => $s->id, 'name' => $s->full_name]),
            'genderOptions'   => [
                ['id' => 'male',   'name' => 'Masculin'],
                ['id' => 'female', 'name' => 'Féminin'],
            ],
        ];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.guardians.index') }}" wire:navigate class="hover:text-primary">Responsables</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">Nouveau responsable</span>
            </div>
        </x-slot:title>
    </x-header>

    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ── Left: Personal info ── --}}
            <div class="lg:col-span-2 space-y-5">

                <x-card title="Informations personnelles" separator>
                    <div class="space-y-4">
                        <x-input label="Nom complet *" wire:model="name"
                                 placeholder="Amina Hassan" required />

                        <div class="grid grid-cols-2 gap-4">
                            <x-input label="Email" wire:model="email" type="email"
                                     placeholder="amina.hassan@email.dj" icon="o-envelope" />
                            <x-select label="Genre" wire:model="gender"
                                      :options="$genderOptions" option-value="id" option-label="name"
                                      placeholder="Non précisé" placeholder-value="" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <x-input label="Téléphone principal" wire:model="phone"
                                     placeholder="+253 77 00 00 00" icon="o-phone" />
                            <x-input label="Téléphone secondaire" wire:model="phone_secondary"
                                     placeholder="+253 77 00 00 01" icon="o-phone" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <x-input label="Profession" wire:model="profession"
                                     placeholder="Médecin, Enseignant…" icon="o-briefcase" />
                            <x-input label="CIN / Passeport" wire:model="national_id"
                                     placeholder="N° de pièce d'identité" icon="o-identification" />
                        </div>

                        <x-input label="Adresse" wire:model="address"
                                 placeholder="Quartier, Ville" icon="o-map-pin" />
                    </div>
                </x-card>

                {{-- Link to student --}}
                <x-card title="Lier à un élève (optionnel)" separator>
                    <div class="space-y-4">
                        <x-select
                            label="Élève"
                            wire:model.live="studentId"
                            :options="$studentOptions"
                            option-value="id"
                            option-label="name"
                            placeholder="Sélectionner un élève"
                            placeholder-value=""
                            searchable
                        />
                        @if($studentId)
                        <div class="grid grid-cols-2 gap-4">
                            <x-select
                                label="Relation"
                                wire:model="relation"
                                :options="$relationOptions"
                                option-value="id"
                                option-label="name"
                                placeholder="Choisir la relation"
                                placeholder-value=""
                            />
                            <div class="flex items-center gap-3 pt-6">
                                <input type="checkbox" wire:model="is_primary" class="checkbox checkbox-sm checkbox-primary" id="is_primary" />
                                <label for="is_primary" class="text-sm font-medium cursor-pointer">
                                    Responsable principal
                                </label>
                            </div>
                        </div>
                        @endif
                    </div>
                </x-card>

            </div>

            {{-- ── Right: Photo + preview ── --}}
            <div class="space-y-5">

                {{-- Photo upload --}}
                <x-card title="Photo de profil" separator>
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-28 h-28 rounded-2xl overflow-hidden bg-base-200 border border-base-300 flex items-center justify-center">
                            @if($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover" />
                            @elseif($name)
                            <span class="text-4xl font-black text-primary">{{ strtoupper(substr($name, 0, 1)) }}</span>
                            @else
                            <x-icon name="o-user" class="w-10 h-10 text-base-content/20" />
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
                    </div>
                </x-card>

                {{-- Live preview --}}
                <x-card title="Aperçu" separator>
                    <div class="flex items-center gap-3 p-2">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-lg shrink-0">
                            {{ $name ? strtoupper(substr($name, 0, 1)) : '?' }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold truncate">
                                {{ $name ?: 'Nom complet' }}
                            </p>
                            @if($profession)
                            <p class="text-xs text-base-content/50 truncate">{{ $profession }}</p>
                            @endif
                            @if($email)
                            <p class="text-xs text-base-content/50 truncate">{{ $email }}</p>
                            @endif
                            @if($phone)
                            <p class="text-xs text-base-content/50 truncate flex items-center gap-1">
                                <x-icon name="o-phone" class="w-3 h-3"/>{{ $phone }}
                            </p>
                            @endif
                        </div>
                    </div>
                    @if($email)
                    <div class="mt-3 pt-3 border-t border-base-200">
                        <div class="flex items-center gap-2 text-xs text-success">
                            <x-icon name="o-check-circle" class="w-4 h-4"/>
                            Un compte parent sera créé automatiquement
                        </div>
                    </div>
                    @endif
                </x-card>

                {{-- Submit --}}
                <div class="flex gap-2">
                    <a href="{{ route('admin.guardians.index') }}" wire:navigate class="flex-1">
                        <x-button label="Annuler" icon="o-arrow-left" class="btn-outline w-full" />
                    </a>
                    <x-button label="Créer" type="submit" icon="o-check"
                              class="btn-primary flex-1" spinner />
                </div>

            </div>
        </div>
    </x-form>
</div>
