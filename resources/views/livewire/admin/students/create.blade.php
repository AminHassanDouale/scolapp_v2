<?php
use App\Models\Student;
use App\Models\Guardian;
use App\Models\Attachment;
use App\Enums\GenderType;
use App\Enums\GuardianRelation;
use App\Enums\AttachmentCategory;
use App\Mail\StudentRegisteredMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    // ── Step tracking ─────────────────────────────────────────────────────────
    public int $step = 1;

    // ── Step 1 — Student identity ─────────────────────────────────────────────
    public string $name             = '';
    public string $gender           = '';
    public string $date_of_birth    = '';
    public string $place_of_birth   = '';
    public string $nationality      = 'DJ';
    public string $national_id      = '';
    public string $address          = '';
    public string $blood_type       = '';
    public bool   $has_disability   = false;
    public string $disability_notes = '';

    // Student photo & documents (all optional)
    public mixed $studentPhoto       = null;   // profile photo
    public mixed $docBirthCert       = null;   // acte de naissance
    public mixed $docStudentId       = null;   // carte d'identité
    public mixed $docStudentPassport = null;   // passeport
    public mixed $docHealthRecord    = null;   // carnet de santé
    public mixed $docStudentOther    = null;   // autre

    // ── Step 2 — Guardian ─────────────────────────────────────────────────────
    public string $guardianMode        = 'existing';
    public ?int   $existingGuardianId  = null;

    // New guardian fields
    public string $g_name        = '';
    public string $g_phone       = '';
    public string $g_email       = '';
    public string $g_profession  = '';
    public string $g_national_id = '';

    // Guardian photo & documents (only when creating a new guardian)
    public mixed $guardianPhoto    = null;
    public mixed $docGuardianId    = null;
    public mixed $docGuardianPass  = null;
    public mixed $docGuardianOther = null;

    // Relation fields (always shown)
    public string $g_relation              = 'father';
    public bool   $g_is_primary            = true;
    public bool   $g_receive_notifications = true;

    // ── nextStep() ────────────────────────────────────────────────────────────
    public function nextStep(): void
    {
        $this->validate([
            'name'          => 'required|string|max:200',
            'gender'        => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before:today',
            'nationality'   => 'nullable|string|max:5',
            'blood_type'    => 'nullable|string|max:5',
            'studentPhoto'       => 'nullable|image|max:2048',
            'docBirthCert'       => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docStudentId'       => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docStudentPassport' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docHealthRecord'    => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docStudentOther'    => 'nullable|file|max:10240',
        ]);

        $this->step = 2;
    }

    // ── save() ────────────────────────────────────────────────────────────────
    public function save(): void
    {
        if ($this->guardianMode === 'existing') {
            $this->validate([
                'existingGuardianId' => 'required|integer|min:1',
            ], ['existingGuardianId.min' => 'Veuillez sélectionner un responsable.']);
        } else {
            $this->validate([
                'g_name'         => 'required|string|max:200',
                'g_phone'        => 'required|string|max:30',
                'g_email'        => 'nullable|email|max:150',
                'guardianPhoto'  => 'nullable|image|max:2048',
                'docGuardianId'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
                'docGuardianPass'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
                'docGuardianOther' => 'nullable|file|max:10240',
            ]);
        }

        $schoolId = auth()->user()->school_id;

        // ── Create student ────────────────────────────────────────────────────
        $student = Student::create([
            'school_id'        => $schoolId,
            'name'             => $this->name,
            'gender'           => $this->gender ?: null,
            'date_of_birth'    => $this->date_of_birth ?: null,
            'place_of_birth'   => $this->place_of_birth ?: null,
            'nationality'      => $this->nationality,
            'national_id'      => $this->national_id ?: null,
            'address'          => $this->address ?: null,
            'blood_type'       => $this->blood_type ?: null,
            'has_disability'   => $this->has_disability,
            'disability_notes' => $this->has_disability ? ($this->disability_notes ?: null) : null,
            'is_active'        => true,
        ]);

        // ── Student photo ─────────────────────────────────────────────────────
        if ($this->studentPhoto) {
            $path = $this->studentPhoto->store("photos/students/{$schoolId}", 'public');
            $student->update(['photo' => $path]);
        }

        // ── Student documents ─────────────────────────────────────────────────
        $studentDocMap = [
            AttachmentCategory::BIRTH_CERTIFICATE->value => [$this->docBirthCert,       'Acte de naissance'],
            AttachmentCategory::ID_CARD->value           => [$this->docStudentId,        "Carte d'identité"],
            AttachmentCategory::PASSPORT->value          => [$this->docStudentPassport,  'Passeport'],
            AttachmentCategory::HEALTH_RECORD->value     => [$this->docHealthRecord,     'Carnet de santé'],
            AttachmentCategory::OTHER->value             => [$this->docStudentOther,     'Autre document'],
        ];

        foreach ($studentDocMap as $category => [$file, $defaultLabel]) {
            if (! $file) continue;
            $uuid = (string) Str::uuid();
            $ext  = $file->getClientOriginalExtension();
            $path = $file->storeAs("attachments/{$schoolId}/student/{$student->id}", "{$uuid}.{$ext}", 'local');
            Attachment::create([
                'uuid'            => $uuid,
                'school_id'       => $schoolId,
                'attachable_type' => Student::class,
                'attachable_id'   => $student->id,
                'category'        => $category,
                'label'           => $defaultLabel,
                'disk'            => 'local',
                'path'            => $path,
                'original_name'   => $file->getClientOriginalName(),
                'size'            => Storage::disk('local')->size($path),
                'mime_type'       => $file->getMimeType(),
                'uploaded_by'     => auth()->id(),
            ]);
        }

        // ── Resolve guardian ──────────────────────────────────────────────────
        if ($this->guardianMode === 'existing') {
            $guardian = Guardian::findOrFail($this->existingGuardianId);
        } else {
            $guardian = Guardian::create([
                'school_id'   => $schoolId,
                'name'        => $this->g_name,
                'phone'       => $this->g_phone,
                'email'       => $this->g_email ?: null,
                'profession'  => $this->g_profession ?: null,
                'national_id' => $this->g_national_id ?: null,
                'is_active'   => true,
            ]);

            // Guardian photo
            if ($this->guardianPhoto) {
                $path = $this->guardianPhoto->store("photos/guardians/{$schoolId}", 'public');
                $guardian->update(['photo' => $path]);
            }

            // Guardian documents
            $guardianDocMap = [
                AttachmentCategory::ID_CARD->value  => [$this->docGuardianId,    "Carte d'identité"],
                AttachmentCategory::PASSPORT->value => [$this->docGuardianPass,  'Passeport'],
                AttachmentCategory::OTHER->value    => [$this->docGuardianOther, 'Autre document'],
            ];

            foreach ($guardianDocMap as $category => [$file, $defaultLabel]) {
                if (! $file) continue;
                $uuid = (string) Str::uuid();
                $ext  = $file->getClientOriginalExtension();
                $path = $file->storeAs("attachments/{$schoolId}/guardian/{$guardian->id}", "{$uuid}.{$ext}", 'local');
                Attachment::create([
                    'uuid'            => $uuid,
                    'school_id'       => $schoolId,
                    'attachable_type' => Guardian::class,
                    'attachable_id'   => $guardian->id,
                    'category'        => $category,
                    'label'           => $defaultLabel,
                    'disk'            => 'local',
                    'path'            => $path,
                    'original_name'   => $file->getClientOriginalName(),
                    'size'            => Storage::disk('local')->size($path),
                    'mime_type'       => $file->getMimeType(),
                    'uploaded_by'     => auth()->id(),
                ]);
            }
        }

        // ── Attach guardian → student ─────────────────────────────────────────
        $student->guardians()->attach($guardian->id, [
            'relation'              => $this->g_relation,
            'is_primary'            => $this->g_is_primary,
            'has_custody'           => true,
            'can_pickup'            => true,
            'receive_notifications' => $this->g_receive_notifications,
        ]);

        // ── Notification email ────────────────────────────────────────────────
        if ($guardian->email) {
            Mail::to($guardian->email)->queue(
                new StudentRegisteredMail($student, $guardian, auth()->user()->school)
            );
        }

        $this->redirectRoute('admin.enrollments.create', ['student_uuid' => $student->uuid], navigate: true);
    }

    // ── with() ────────────────────────────────────────────────────────────────
    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $guardians = Guardian::where('school_id', $schoolId)
            ->orderBy('name')
            ->get()
            ->map(fn($g) => [
                'id'   => $g->id,
                'name' => $g->full_name . ($g->phone ? ' — ' . $g->phone : ''),
            ])
            ->all();

        $genders = [
            ['id' => 'male',   'name' => 'Masculin'],
            ['id' => 'female', 'name' => 'Féminin'],
        ];

        $bloodTypes = collect(['A+','A-','B+','B-','AB+','AB-','O+','O-'])
            ->map(fn($b) => ['id' => $b, 'name' => $b])
            ->all();

        $relations = collect(GuardianRelation::cases())
            ->map(fn($r) => ['id' => $r->value, 'name' => $r->label()])
            ->all();

        return compact('guardians', 'genders', 'bloodTypes', 'relations');
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.students.index') }}" wire:navigate class="hover:text-primary">Élèves</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">Nouvel élève</span>
            </div>
        </x-slot:title>
    </x-header>

    {{-- Step indicator --}}
    <div class="max-w-3xl mb-8">
        <div class="flex items-center gap-0">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold
                    {{ $step >= 1 ? 'bg-primary text-primary-content' : 'bg-base-300 text-base-content/40' }}">
                    1
                </div>
                <span class="text-sm font-semibold {{ $step >= 1 ? 'text-primary' : 'text-base-content/40' }}">
                    Élève
                </span>
            </div>
            <div class="flex-1 mx-4 h-px {{ $step >= 2 ? 'bg-primary' : 'bg-base-300' }}"></div>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold
                    {{ $step >= 2 ? 'bg-primary text-primary-content' : 'bg-base-300 text-base-content/40' }}">
                    2
                </div>
                <span class="text-sm font-semibold {{ $step >= 2 ? 'text-primary' : 'text-base-content/40' }}">
                    Responsable légal
                </span>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- STEP 1 — Student identity + files                                     --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($step === 1)
    <div class="max-w-3xl">
        <x-form wire:submit="nextStep" class="space-y-6">

            {{-- Identity --}}
            <x-card title="Identité de l'élève" separator>
                <div class="space-y-4">
                    <x-input label="Nom complet *" wire:model="name" placeholder="Mohamed Ali" required />

                    <div class="grid grid-cols-2 gap-4">
                        <x-choices label="Genre"
                                   wire:model="gender"
                                   :options="$genders"
                                   option-value="id"
                                   option-label="name"
                                   single
                                   placeholder="Non précisé" />
                        <x-datepicker label="Date de naissance" wire:model="date_of_birth"
                                      icon="o-calendar"
                                      :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Lieu de naissance" wire:model="place_of_birth" placeholder="Djibouti" />
                        <x-input label="Nationalité" wire:model="nationality" placeholder="DJ" maxlength="5" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="N° d'identité nationale" wire:model="national_id" placeholder="DJ123456" />
                        <x-choices label="Groupe sanguin"
                                   wire:model="blood_type"
                                   :options="$bloodTypes"
                                   option-value="id"
                                   option-label="name"
                                   single
                                   placeholder="Non précisé" />
                    </div>

                    <x-input label="Adresse" wire:model="address" placeholder="Quartier, Djibouti" />

                    <div class="pt-1">
                        <x-checkbox label="L'élève a un handicap ou besoin particulier"
                                    wire:model.live="has_disability" />
                        @if($has_disability)
                        <div class="mt-3">
                            <x-textarea label="Description" wire:model="disability_notes"
                                        placeholder="Décrivez les besoins de l'élève..." rows="3" />
                        </div>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- Photo + Documents --}}
            <x-card title="Photo & documents" separator>
                <div class="space-y-5">

                    {{-- Photo --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-2">Photo de profil</p>
                        <div class="flex items-center gap-4">
                            {{-- Preview --}}
                            <div class="w-20 h-20 rounded-full border-2 border-base-300 overflow-hidden shrink-0 bg-base-200 flex items-center justify-center">
                                @if($studentPhoto)
                                    <img src="{{ $studentPhoto->temporaryUrl() }}" class="w-full h-full object-cover" />
                                @else
                                    <x-icon name="o-user" class="w-8 h-8 text-base-content/30" />
                                @endif
                            </div>
                            <div class="flex-1">
                                <label class="flex items-center gap-2 cursor-pointer w-fit px-4 py-2 rounded-lg border border-base-300 hover:bg-base-200 transition-colors text-sm">
                                    <x-icon name="o-camera" class="w-4 h-4" />
                                    {{ $studentPhoto ? 'Changer la photo' : 'Choisir une photo' }}
                                    <input type="file" wire:model.live="studentPhoto" class="hidden" accept="image/*" />
                                </label>
                                @error('studentPhoto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                                <p class="text-xs text-base-content/40 mt-1">JPG, PNG, WEBP — max 2 MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="divider my-1"></div>

                    {{-- Documents --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-3">Documents de l'élève <span class="font-normal normal-case">(optionnels)</span></p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                            @foreach([
                                ['field' => 'docBirthCert',       'label' => 'Acte de naissance',    'icon' => 'o-document'],
                                ['field' => 'docStudentId',        'label' => "Carte d'identité",     'icon' => 'o-identification'],
                                ['field' => 'docStudentPassport',  'label' => 'Passeport',            'icon' => 'o-identification'],
                                ['field' => 'docHealthRecord',     'label' => 'Carnet de santé',      'icon' => 'o-heart'],
                                ['field' => 'docStudentOther',     'label' => 'Autre document',       'icon' => 'o-paper-clip'],
                            ] as $doc)
                            @php $uploaded = $this->{$doc['field']}; @endphp
                            <div wire:key="sdoc-{{ $doc['field'] }}" class="flex items-center gap-3 p-3 rounded-xl border {{ $uploaded ? 'border-primary/40 bg-primary/5' : 'border-base-300' }}">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0
                                            {{ $uploaded ? 'bg-primary/10 text-primary' : 'bg-base-200 text-base-content/30' }}">
                                    <x-icon :name="$doc['icon']" class="w-4.5 h-4.5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold truncate">{{ $doc['label'] }}</p>
                                    @if($uploaded)
                                    <p class="text-xs text-primary truncate">{{ $uploaded->getClientOriginalName() }}</p>
                                    @else
                                    <p class="text-xs text-base-content/40">Non fourni</p>
                                    @endif
                                    @error($doc['field']) <p class="text-error text-xs">{{ $message }}</p> @enderror
                                </div>
                                <label class="btn btn-ghost btn-xs cursor-pointer">
                                    <x-icon name="{{ $uploaded ? 'o-arrow-path' : 'o-plus' }}" class="w-3.5 h-3.5" />
                                    <input type="file" wire:model.live="{{ $doc['field'] }}" class="hidden"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" />
                                </label>
                            </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            </x-card>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.students.index') }}" wire:navigate>
                    <x-button label="Annuler" icon="o-arrow-left" class="btn-outline" />
                </a>
                <x-button label="Suivant →" type="submit" class="btn-primary" spinner />
            </div>
        </x-form>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- STEP 2 — Guardian                                                     --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($step === 2)
    <div class="max-w-3xl">
        <x-form wire:submit="save" class="space-y-6">

            {{-- Mode toggle --}}
            <div class="flex gap-2">
                <button type="button"
                        wire:click="$set('guardianMode', 'existing')"
                        class="btn btn-sm {{ $guardianMode === 'existing' ? 'btn-primary' : 'btn-ghost border border-base-300' }}">
                    <x-icon name="o-magnifying-glass" class="w-4 h-4" />
                    Responsable existant
                </button>
                <button type="button"
                        wire:click="$set('guardianMode', 'new')"
                        class="btn btn-sm {{ $guardianMode === 'new' ? 'btn-primary' : 'btn-ghost border border-base-300' }}">
                    <x-icon name="o-user-plus" class="w-4 h-4" />
                    Nouveau responsable
                </button>
            </div>

            {{-- Mode: existing --}}
            @if($guardianMode === 'existing')
            <x-card title="Responsable existant" separator>
                <x-choices label="Responsable légal *"
                           wire:model="existingGuardianId"
                           :options="$guardians"
                           option-value="id"
                           option-label="name"
                           single
                           clearable
                           placeholder="Rechercher par nom ou téléphone..."
                           required />
            </x-card>
            @endif

            {{-- Mode: new --}}
            @if($guardianMode === 'new')
            <x-card title="Nouveau responsable" separator>
                <div class="space-y-5">

                    {{-- Identity --}}
                    <div class="space-y-4">
                        <x-input label="Nom complet *" wire:model="g_name" placeholder="Ahmed Omar" required />
                        <div class="grid grid-cols-2 gap-4">
                            <x-input label="Téléphone *" wire:model="g_phone" placeholder="+253 77 00 00 00" required />
                            <x-input label="Email" wire:model="g_email" type="email" placeholder="ahmed@example.com" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <x-input label="Profession" wire:model="g_profession" placeholder="Enseignant" />
                            <x-input label="N° d'identité nationale" wire:model="g_national_id" placeholder="DJ123456" />
                        </div>
                    </div>

                    <div class="divider my-1"></div>

                    {{-- Guardian photo --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-2">Photo du responsable</p>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-full border-2 border-base-300 overflow-hidden shrink-0 bg-base-200 flex items-center justify-center">
                                @if($guardianPhoto)
                                    <img src="{{ $guardianPhoto->temporaryUrl() }}" class="w-full h-full object-cover" />
                                @else
                                    <x-icon name="o-user" class="w-6 h-6 text-base-content/30" />
                                @endif
                            </div>
                            <div>
                                <label class="flex items-center gap-2 cursor-pointer w-fit px-3 py-1.5 rounded-lg border border-base-300 hover:bg-base-200 transition-colors text-sm">
                                    <x-icon name="o-camera" class="w-4 h-4" />
                                    {{ $guardianPhoto ? 'Changer' : 'Choisir une photo' }}
                                    <input type="file" wire:model.live="guardianPhoto" class="hidden" accept="image/*" />
                                </label>
                                @error('guardianPhoto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Guardian documents --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-3">Documents du responsable <span class="font-normal normal-case">(optionnels)</span></p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                            @foreach([
                                ['field' => 'docGuardianId',    'label' => "Carte d'identité", 'icon' => 'o-identification'],
                                ['field' => 'docGuardianPass',  'label' => 'Passeport',         'icon' => 'o-identification'],
                                ['field' => 'docGuardianOther', 'label' => 'Autre document',    'icon' => 'o-paper-clip'],
                            ] as $doc)
                            @php $uploaded = $this->{$doc['field']}; @endphp
                            <div wire:key="gdoc-{{ $doc['field'] }}" class="flex items-center gap-2 p-3 rounded-xl border {{ $uploaded ? 'border-primary/40 bg-primary/5' : 'border-base-300' }}">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0
                                            {{ $uploaded ? 'bg-primary/10 text-primary' : 'bg-base-200 text-base-content/30' }}">
                                    <x-icon :name="$doc['icon']" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold truncate">{{ $doc['label'] }}</p>
                                    @if($uploaded)
                                    <p class="text-xs text-primary truncate">{{ $uploaded->getClientOriginalName() }}</p>
                                    @else
                                    <p class="text-xs text-base-content/40">Non fourni</p>
                                    @endif
                                    @error($doc['field']) <p class="text-error text-xs">{{ $message }}</p> @enderror
                                </div>
                                <label class="btn btn-ghost btn-xs cursor-pointer">
                                    <x-icon name="{{ $uploaded ? 'o-arrow-path' : 'o-plus' }}" class="w-3.5 h-3.5" />
                                    <input type="file" wire:model.live="{{ $doc['field'] }}" class="hidden"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp" />
                                </label>
                            </div>
                            @endforeach

                        </div>
                    </div>

                </div>
            </x-card>
            @endif

            {{-- Relation fields (always shown) --}}
            <x-card title="Lien avec l'élève" separator>
                <div class="space-y-4">
                    <x-choices label="Relation *"
                               wire:model="g_relation"
                               :options="$relations"
                               option-value="id"
                               option-label="name"
                               single
                               placeholder="Choisir la relation..."
                               required />
                    <div class="space-y-2">
                        <x-checkbox label="Responsable principal" wire:model="g_is_primary" />
                        <x-checkbox label="Recevoir les notifications" wire:model="g_receive_notifications" />
                    </div>
                </div>
            </x-card>

            <div class="flex items-center gap-3">
                <x-button label="← Retour" wire:click="$set('step', 1)" class="btn-outline" />
                <x-button label="Créer l'élève et continuer" type="submit" icon="o-check"
                          class="btn-primary" spinner="save" />
            </div>
        </x-form>
    </div>
    @endif
</div>
