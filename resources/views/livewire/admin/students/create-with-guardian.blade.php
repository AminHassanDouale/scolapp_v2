<?php
use App\Models\Student;
use App\Models\Guardian;
use App\Models\Attachment;
use App\Models\User;
use App\Enums\GenderType;
use App\Enums\GuardianRelation;
use App\Enums\AttachmentCategory;
use App\Mail\GuardianWelcomeMail;
use App\Mail\StudentRegisteredMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    // ── Student fields ─────────────────────────────────────────────────────────
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
    public mixed  $studentPhoto     = null;
    public mixed  $docBirthCert     = null;
    public mixed  $docStudentId     = null;
    public mixed  $docStudentPassport = null;
    public mixed  $docHealthRecord  = null;
    public mixed  $docStudentOther  = null;

    // ── Guardian fields ────────────────────────────────────────────────────────
    public string $g_name        = '';
    public string $g_gender      = '';
    public string $g_phone       = '';
    public string $g_whatsapp    = '';
    public string $g_email       = '';
    public string $g_profession  = '';
    public string $g_national_id = '';
    public string $g_address     = '';
    public mixed  $guardianPhoto = null;
    public mixed  $docGuardianId = null;
    public mixed  $docGuardianPass = null;
    public mixed  $docGuardianOther = null;

    // ── Pivot / relation ───────────────────────────────────────────────────────
    public string $g_relation              = 'father';
    public bool   $g_is_primary            = true;
    public bool   $g_has_custody           = true;
    public bool   $g_can_pickup            = true;
    public bool   $g_receive_notifications = true;

    // ── save() ────────────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->validate([
            // Student
            'name'             => 'required|string|max:200',
            'gender'           => 'nullable|in:male,female',
            'date_of_birth'    => 'nullable|date|before:today',
            'nationality'      => 'nullable|string|max:5',
            'blood_type'       => 'nullable|string|max:5',
            'studentPhoto'          => 'nullable|image|max:2048',
            'docBirthCert'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docStudentId'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docStudentPassport'    => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docHealthRecord'       => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docStudentOther'       => 'nullable|file|max:10240',
            // Guardian
            'g_name'          => 'required|string|max:200',
            'g_phone'         => 'required|string|max:30',
            'g_whatsapp'      => 'nullable|string|max:30',
            'g_email'         => 'nullable|email|max:150',
            'guardianPhoto'   => 'nullable|image|max:2048',
            'docGuardianId'   => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docGuardianPass' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'docGuardianOther'=> 'nullable|file|max:10240',
            // Relation
            'g_relation' => 'required|string',
        ]);

        $schoolId = auth()->user()->school_id;

        DB::transaction(function () use ($schoolId) {
            // ── 1. Create student ──────────────────────────────────────────────
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

            // Student photo
            if ($this->studentPhoto) {
                $path = $this->studentPhoto->store("photos/students/{$schoolId}", 'public');
                $student->update(['photo' => $path]);
            }

            // Student documents
            $studentDocMap = [
                AttachmentCategory::BIRTH_CERTIFICATE->value => [$this->docBirthCert,      'Acte de naissance'],
                AttachmentCategory::ID_CARD->value           => [$this->docStudentId,       "Carte d'identité"],
                AttachmentCategory::PASSPORT->value          => [$this->docStudentPassport, 'Passeport'],
                AttachmentCategory::HEALTH_RECORD->value     => [$this->docHealthRecord,    'Carnet de santé'],
                AttachmentCategory::OTHER->value             => [$this->docStudentOther,    'Autre document'],
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

            // ── 2. Create guardian ─────────────────────────────────────────────
            $guardian = Guardian::create([
                'school_id'       => $schoolId,
                'name'            => $this->g_name,
                'gender'          => $this->g_gender ?: null,
                'phone'           => $this->g_phone,
                'whatsapp_number' => $this->g_whatsapp ?: null,
                'email'           => $this->g_email ?: null,
                'profession'      => $this->g_profession ?: null,
                'national_id'     => $this->g_national_id ?: null,
                'address'         => $this->g_address ?: null,
                'is_active'       => true,
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

            // ── 3. Attach guardian → student ───────────────────────────────────
            $student->guardians()->attach($guardian->id, [
                'relation'              => $this->g_relation,
                'is_primary'            => $this->g_is_primary,
                'has_custody'           => $this->g_has_custody,
                'can_pickup'            => $this->g_can_pickup,
                'receive_notifications' => $this->g_receive_notifications,
            ]);

            // ── 4. Create guardian user account & send welcome email ───────────
            if ($guardian->email && ! User::where('email', $guardian->email)->exists()) {
                $plainPassword = Str::password(12, symbols: false);
                $guardianUser  = User::create([
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $schoolId,
                    'name'      => $guardian->full_name,
                    'email'     => $guardian->email,
                    'password'  => Hash::make($plainPassword),
                    'ui_lang'   => 'fr',
                    'timezone'  => 'Africa/Djibouti',
                ]);
                $guardianUser->assignRole('guardian');
                $guardian->update(['user_id' => $guardianUser->id]);

                try {
                    $school = \App\Models\School::findOrFail($schoolId);
                    $student->load(['enrollments.schoolClass', 'enrollments.grade']);
                    Mail::to($guardian->email)->send(
                        new GuardianWelcomeMail($guardian, $school, $student, $plainPassword)
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('GuardianWelcomeMail failed: ' . $e->getMessage());
                }
            } elseif ($guardian->email) {
                // Guardian already has an account — just notify of the new student
                try {
                    $school = \App\Models\School::findOrFail($schoolId);
                    Mail::to($guardian->email)->queue(
                        new StudentRegisteredMail($student, $guardian, $school)
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('StudentRegisteredMail failed: ' . $e->getMessage());
                }
            }

            $this->redirectRoute('admin.enrollments.create', ['student_uuid' => $student->uuid], navigate: true);
        });
    }

    // ── with() ────────────────────────────────────────────────────────────────
    public function with(): array
    {
        return [
            'genders' => [
                ['id' => 'male',   'name' => 'Masculin'],
                ['id' => 'female', 'name' => 'Féminin'],
            ],
            'bloodTypes' => collect(['A+','A-','B+','B-','AB+','AB-','O+','O-'])
                ->map(fn($b) => ['id' => $b, 'name' => $b])->all(),
            'relations' => collect(GuardianRelation::cases())
                ->map(fn($r) => ['id' => $r->value, 'name' => $r->label()])->all(),
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.students.index') }}" wire:navigate class="hover:text-primary">Élèves</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">Nouvel élève + Responsable</span>
            </div>
        </x-slot:title>
    </x-header>

    <x-form wire:submit="save">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- LEFT COLUMN — Form fields                                          --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-7 space-y-5">

            {{-- ── Section 1: Élève ─────────────────────────────────────────── --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-primary text-primary-content flex items-center justify-center text-xs font-black">1</div>
                        <span>Identité de l'élève</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    <x-input label="Nom complet *" wire:model="name" placeholder="Mohamed Ali Hassan" required />

                    <div class="grid grid-cols-2 gap-4">
                        <x-choices label="Genre"
                                   wire:model="gender"
                                   :options="$genders"
                                   option-value="id" option-label="name"
                                   single placeholder="Non précisé" />
                        <x-datepicker label="Date de naissance"
                                      wire:model="date_of_birth"
                                      icon="o-calendar"
                                      :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'locale' => ['firstDayOfWeek' => 1]]" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Lieu de naissance" wire:model="place_of_birth" placeholder="Djibouti" />
                        <x-input label="Nationalité" wire:model="nationality" placeholder="DJ" maxlength="5" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="N° identité nationale" wire:model="national_id" placeholder="DJ123456" />
                        <x-choices label="Groupe sanguin"
                                   wire:model="blood_type"
                                   :options="$bloodTypes"
                                   option-value="id" option-label="name"
                                   single placeholder="Non précisé" />
                    </div>

                    <x-input label="Adresse" wire:model="address" placeholder="Quartier, Djibouti" />

                    <div>
                        <x-checkbox label="L'élève a un handicap ou besoin particulier"
                                    wire:model.live="has_disability" />
                        @if($has_disability)
                        <div class="mt-3">
                            <x-textarea label="Description du besoin" wire:model="disability_notes"
                                        placeholder="Décrivez les besoins..." rows="3" />
                        </div>
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- ── Section 2: Responsable légal ────────────────────────────── --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-secondary text-secondary-content flex items-center justify-center text-xs font-black">2</div>
                        <span>Responsable légal</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    <x-input label="Nom complet *" wire:model="g_name" placeholder="Ahmed Omar Farah" required />

                    <div class="grid grid-cols-2 gap-4">
                        <x-choices label="Genre"
                                   wire:model="g_gender"
                                   :options="$genders"
                                   option-value="id" option-label="name"
                                   single placeholder="Non précisé" />
                        <x-input label="Profession" wire:model="g_profession" placeholder="Fonctionnaire" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Téléphone *" wire:model="g_phone"
                                 placeholder="+253 77 00 00 00" required />
                        <x-input label="WhatsApp" wire:model="g_whatsapp"
                                 placeholder="+253 77 00 00 00"
                                 hint="Vide = même que le téléphone" />
                    </div>

                    <x-input label="Email" wire:model="g_email" type="email"
                             placeholder="ahmed@exemple.com"
                             hint="Un compte d'accès sera créé automatiquement" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="N° identité nationale" wire:model="g_national_id" placeholder="DJ123456" />
                        <x-input label="Adresse" wire:model="g_address" placeholder="Quartier, Djibouti" />
                    </div>

                    {{-- Guardian documents ──────────────────────────────────── --}}
                    <div class="pt-1">
                        <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-3">
                            Documents du responsable <span class="font-normal normal-case">(optionnels)</span>
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach([
                                ['field' => 'docGuardianId',    'label' => "Carte d'identité", 'icon' => 'o-identification'],
                                ['field' => 'docGuardianPass',  'label' => 'Passeport',         'icon' => 'o-identification'],
                                ['field' => 'docGuardianOther', 'label' => 'Autre document',    'icon' => 'o-paper-clip'],
                            ] as $doc)
                            @php $uploaded = $this->{$doc['field']}; @endphp
                            <div wire:key="gdoc-{{ $doc['field'] }}" class="flex items-center gap-2 p-3 rounded-xl border {{ $uploaded ? 'border-secondary/40 bg-secondary/5' : 'border-base-300' }}">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0
                                            {{ $uploaded ? 'bg-secondary/10 text-secondary' : 'bg-base-200 text-base-content/30' }}">
                                    <x-icon :name="$doc['icon']" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold truncate">{{ $doc['label'] }}</p>
                                    @if($uploaded)
                                    <p class="text-xs text-secondary truncate">{{ $uploaded->getClientOriginalName() }}</p>
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

            {{-- ── Section 3: Lien élève ↔ responsable ───────────────────────── --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-accent text-accent-content flex items-center justify-center text-xs font-black">3</div>
                        <span>Lien avec l'élève</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    <x-choices label="Relation *"
                               wire:model="g_relation"
                               :options="$relations"
                               option-value="id" option-label="name"
                               single
                               placeholder="Choisir la relation..."
                               required />

                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-base-300 cursor-pointer hover:bg-base-200/50 transition-colors {{ $g_is_primary ? 'border-primary/30 bg-primary/5' : '' }}">
                            <input type="checkbox" wire:model.live="g_is_primary" class="checkbox checkbox-primary checkbox-sm" />
                            <div>
                                <p class="text-sm font-semibold">Responsable principal</p>
                                <p class="text-xs text-base-content/50">Contact prioritaire</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-base-300 cursor-pointer hover:bg-base-200/50 transition-colors {{ $g_receive_notifications ? 'border-success/30 bg-success/5' : '' }}">
                            <input type="checkbox" wire:model.live="g_receive_notifications" class="checkbox checkbox-success checkbox-sm" />
                            <div>
                                <p class="text-sm font-semibold">Notifications</p>
                                <p class="text-xs text-base-content/50">Email & WhatsApp</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-base-300 cursor-pointer hover:bg-base-200/50 transition-colors {{ $g_has_custody ? 'border-info/30 bg-info/5' : '' }}">
                            <input type="checkbox" wire:model.live="g_has_custody" class="checkbox checkbox-info checkbox-sm" />
                            <div>
                                <p class="text-sm font-semibold">Garde légale</p>
                                <p class="text-xs text-base-content/50">A la garde de l'élève</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-base-300 cursor-pointer hover:bg-base-200/50 transition-colors {{ $g_can_pickup ? 'border-warning/30 bg-warning/5' : '' }}">
                            <input type="checkbox" wire:model.live="g_can_pickup" class="checkbox checkbox-warning checkbox-sm" />
                            <div>
                                <p class="text-sm font-semibold">Peut récupérer</p>
                                <p class="text-xs text-base-content/50">Autorisé à récupérer l'élève</p>
                            </div>
                        </label>
                    </div>
                </div>
            </x-card>

            {{-- ── Submit row ───────────────────────────────────────────────── --}}
            <x-card>
                <div class="flex items-center gap-3">
                    <x-button label="Annuler"
                              :link="route('admin.students.index')"
                              class="btn-ghost" wire:navigate />
                    <x-button label="Créer l'élève et le responsable"
                              type="submit"
                              icon="o-check"
                              class="btn-primary flex-1 sm:flex-none"
                              spinner="save" />
                </div>
            </x-card>

        </div>

        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT COLUMN — Photos + Summary                                    --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-5">
            <div class="sticky top-6 space-y-4">

                {{-- Student photo ─────────────────────────────────────────── --}}
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-user-circle" class="w-4 h-4 text-primary" />
                            <span class="text-sm">Photo de l'élève</span>
                        </div>
                    </x-slot:title>
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-24 h-24 rounded-full border-4 border-primary/20 overflow-hidden bg-primary/5 flex items-center justify-center">
                            @if($studentPhoto)
                                <img src="{{ $studentPhoto->temporaryUrl() }}" class="w-full h-full object-cover" />
                            @elseif($name)
                                <span class="text-3xl font-black text-primary">{{ mb_substr($name, 0, 1) }}</span>
                            @else
                                <x-icon name="o-user" class="w-10 h-10 text-primary/30" />
                            @endif
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer px-4 py-2 rounded-xl border border-base-300 hover:bg-base-200 transition-colors text-sm">
                            <x-icon name="o-camera" class="w-4 h-4" />
                            {{ $studentPhoto ? 'Changer la photo' : 'Choisir une photo' }}
                            <input type="file" wire:model.live="studentPhoto" class="hidden" accept="image/*" />
                        </label>
                        @error('studentPhoto') <p class="text-error text-xs">{{ $message }}</p> @enderror
                        <p class="text-xs text-base-content/40">JPG, PNG, WEBP — max 2 MB</p>
                    </div>
                </x-card>

                {{-- Guardian photo ────────────────────────────────────────── --}}
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-user-circle" class="w-4 h-4 text-secondary" />
                            <span class="text-sm">Photo du responsable</span>
                        </div>
                    </x-slot:title>
                    <div class="flex flex-col items-center gap-4">
                        <div class="w-20 h-20 rounded-full border-4 border-secondary/20 overflow-hidden bg-secondary/5 flex items-center justify-center">
                            @if($guardianPhoto)
                                <img src="{{ $guardianPhoto->temporaryUrl() }}" class="w-full h-full object-cover" />
                            @elseif($g_name)
                                <span class="text-2xl font-black text-secondary">{{ mb_substr($g_name, 0, 1) }}</span>
                            @else
                                <x-icon name="o-user" class="w-8 h-8 text-secondary/30" />
                            @endif
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer px-3 py-1.5 rounded-xl border border-base-300 hover:bg-base-200 transition-colors text-sm">
                            <x-icon name="o-camera" class="w-4 h-4" />
                            {{ $guardianPhoto ? 'Changer' : 'Choisir une photo' }}
                            <input type="file" wire:model.live="guardianPhoto" class="hidden" accept="image/*" />
                        </label>
                        @error('guardianPhoto') <p class="text-error text-xs">{{ $message }}</p> @enderror
                    </div>
                </x-card>

                {{-- Documents de l'élève ──────────────────────────────────── --}}
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-paper-clip" class="w-4 h-4 text-primary" />
                            <span class="text-sm">Documents de l'élève</span>
                        </div>
                    </x-slot:title>
                    <div class="space-y-2">
                        @foreach([
                            ['field' => 'docBirthCert',       'label' => 'Acte de naissance'],
                            ['field' => 'docStudentId',        'label' => "Carte d'identité"],
                            ['field' => 'docStudentPassport',  'label' => 'Passeport'],
                            ['field' => 'docHealthRecord',     'label' => 'Carnet de santé'],
                            ['field' => 'docStudentOther',     'label' => 'Autre document'],
                        ] as $doc)
                        @php $uploaded = $this->{$doc['field']}; @endphp
                        <div wire:key="sdoc2-{{ $doc['field'] }}" class="flex items-center justify-between gap-2 py-1.5 px-2 rounded-lg {{ $uploaded ? 'bg-primary/5' : '' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-1.5 h-1.5 rounded-full shrink-0 {{ $uploaded ? 'bg-primary' : 'bg-base-300' }}"></div>
                                <span class="text-xs {{ $uploaded ? 'text-primary font-semibold' : 'text-base-content/50' }} truncate">{{ $doc['label'] }}</span>
                            </div>
                            <label class="btn btn-ghost btn-xs cursor-pointer shrink-0">
                                <x-icon name="{{ $uploaded ? 'o-check' : 'o-plus' }}" class="w-3 h-3 {{ $uploaded ? 'text-primary' : '' }}" />
                                <input type="file" wire:model.live="{{ $doc['field'] }}" class="hidden"
                                       accept=".pdf,.jpg,.jpeg,.png,.webp" />
                            </label>
                        </div>
                        @endforeach
                    </div>
                </x-card>

                {{-- Recap summary ────────────────────────────────────────── --}}
                @if($name || $g_name)
                <x-card>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-eye" class="w-4 h-4 text-base-content/50" />
                            <span class="text-sm text-base-content/60">Récapitulatif</span>
                        </div>
                    </x-slot:title>
                    <div class="space-y-3 text-sm">
                        @if($name)
                        <div>
                            <p class="text-xs font-bold uppercase text-primary/60 mb-1">Élève</p>
                            <p class="font-semibold">{{ $name }}</p>
                            @if($date_of_birth) <p class="text-xs text-base-content/50">Né(e) le {{ \Carbon\Carbon::parse($date_of_birth)->format('d/m/Y') }}</p> @endif
                        </div>
                        @endif
                        @if($g_name)
                        <div class="pt-2 border-t border-base-200">
                            <p class="text-xs font-bold uppercase text-secondary/60 mb-1">Responsable</p>
                            <p class="font-semibold">{{ $g_name }}</p>
                            @if($g_phone) <p class="text-xs text-base-content/50">{{ $g_phone }}</p> @endif
                            @if($g_email)
                            <p class="text-xs text-success/70 flex items-center gap-1 mt-0.5">
                                <x-icon name="o-envelope" class="w-3 h-3" />
                                Compte accès parent créé
                            </p>
                            @endif
                            @if($g_whatsapp || $g_phone)
                            <p class="text-xs text-success/70 flex items-center gap-1 mt-0.5">
                                <x-icon name="o-chat-bubble-left" class="w-3 h-3" />
                                WhatsApp activé
                            </p>
                            @endif
                        </div>
                        @endif
                        @if($g_relation)
                        <div class="pt-2 border-t border-base-200">
                            <p class="text-xs text-base-content/50">
                                Relation : <span class="font-semibold text-base-content">{{ collect($relations)->firstWhere('id', $g_relation)['name'] ?? $g_relation }}</span>
                            </p>
                        </div>
                        @endif
                    </div>
                </x-card>
                @endif

            </div>
        </div>

    </div>
    </x-form>
</div>
