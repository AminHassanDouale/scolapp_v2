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
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Guardian $guardian;

    // Attach student form
    public bool   $showAttachDrawer = false;
    public ?int   $attachStudentId  = null;
    public string $attachRelation   = '';
    public bool   $attachIsPrimary  = false;

    public function mount(string $uuid): void
    {
        $this->guardian = Guardian::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with(['students.enrollments.schoolClass', 'students.enrollments.grade', 'user'])
            ->firstOrFail();

        if (session()->has('success')) { $this->success(session('success')); }
    }

    public function toggleActive(): void
    {
        $this->guardian->update(['is_active' => !$this->guardian->is_active]);
        $this->guardian->refresh();
        $this->success(
            $this->guardian->is_active ? 'Responsable activé.' : 'Responsable désactivé.',
            position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000
        );
    }

    public function sendCredentials(): void
    {
        abort_unless(auth()->user()->hasRole(['super-admin', 'admin']), 403);

        if (!$this->guardian->email) {
            $this->error('Ce responsable n\'a pas d\'adresse email.', position: 'toast-top toast-end');
            return;
        }

        $password = Str::password(12, symbols: false);

        try {
            if (!$this->guardian->user_id) {
                // Create new account
                if (User::where('email', $this->guardian->email)->exists()) {
                    $this->error('Un compte existe déjà avec cet email.', position: 'toast-top toast-end');
                    return;
                }
                $user = User::create([
                    'uuid'       => (string) Str::uuid(),
                    'school_id'  => $this->guardian->school_id,
                    'name'       => $this->guardian->full_name,
                            'email'      => $this->guardian->email,
                    'password'   => Hash::make($password),
                    'ui_lang'    => 'fr',
                    'timezone'   => 'Africa/Djibouti',
                ]);
                $user->assignRole('guardian');
                $this->guardian->update(['user_id' => $user->id]);
            } else {
                // Reset existing password
                $this->guardian->user->update(['password' => Hash::make($password)]);
            }

            $this->guardian = $this->guardian->fresh(['students.enrollments.schoolClass', 'students.enrollments.grade', 'user']);

            $school  = School::findOrFail($this->guardian->school_id);
            $student = $this->guardian->students()->with(['enrollments.schoolClass', 'enrollments.grade'])->first();

            if ($student) {
                Mail::to($this->guardian->email)->send(
                    new GuardianWelcomeMail($this->guardian, $school, $student, $password)
                );
                $this->success(
                    'Identifiants envoyés à ' . $this->guardian->email,
                    position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 4000
                );
            } else {
                $this->warning(
                    'Compte créé mais aucun élève lié — email non envoyé.',
                    position: 'toast-top toast-end', timeout: 4000
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GuardianWelcomeMail failed: ' . $e->getMessage());
            $this->error('Erreur lors de l\'envoi de l\'email.', position: 'toast-top toast-end');
        }
    }

    public function attachStudent(): void
    {
        $this->validate([
            'attachStudentId' => 'required|exists:students,id',
            'attachRelation'  => 'required|string',
        ]);

        // Prevent duplicate
        if ($this->guardian->students->contains($this->attachStudentId)) {
            $this->error('Cet élève est déjà lié à ce responsable.', position: 'toast-top toast-end');
            return;
        }

        $this->guardian->students()->attach($this->attachStudentId, [
            'relation'              => $this->attachRelation,
            'is_primary'            => $this->attachIsPrimary,
            'has_custody'           => true,
            'can_pickup'            => true,
            'receive_notifications' => true,
        ]);

        $this->guardian = $this->guardian->fresh(['students.enrollments.schoolClass', 'students.enrollments.grade', 'user']);
        $this->showAttachDrawer = false;
        $this->attachStudentId  = null;
        $this->attachRelation   = '';
        $this->attachIsPrimary  = false;

        $this->success('Élève lié avec succès.', position: 'toast-top toast-end', icon: 'o-user-plus', css: 'alert-success', timeout: 3000);
    }

    public function detachStudent(int $studentId): void
    {
        $this->guardian->students()->detach($studentId);
        $this->guardian = $this->guardian->fresh(['students.enrollments.schoolClass', 'students.enrollments.grade', 'user']);
        $this->success('Élève détaché.', position: 'toast-top toast-end', icon: 'o-user-minus', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $availableStudents = Student::where('school_id', $schoolId)
            ->whereNotIn('id', $this->guardian->students->pluck('id'))
            ->orderBy('name')
            ->get()
            ->map(fn($s) => (object)['id' => $s->id, 'name' => $s->full_name]);

        $relationOptions = collect(GuardianRelation::cases())
            ->map(fn($r) => ['id' => $r->value, 'name' => $r->label()])
            ->all();

        return compact('availableStudents', 'relationOptions');
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.guardians.index') }}" wire:navigate class="hover:text-primary">Responsables</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">{{ $guardian->full_name }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="{{ $guardian->is_active ? 'Désactiver' : 'Activer' }}"
                      icon="{{ $guardian->is_active ? 'o-pause-circle' : 'o-play-circle' }}"
                      wire:click="toggleActive"
                      class="{{ $guardian->is_active ? 'btn-ghost text-warning' : 'btn-ghost text-success' }}" />
            @if(auth()->user()->hasRole(['super-admin', 'admin']) && $guardian->email)
            <x-button
                :label="$guardian->user_id ? 'Réinitialiser identifiants' : 'Envoyer identifiants'"
                :icon="$guardian->user_id ? 'o-arrow-path' : 'o-paper-airplane'"
                wire:click="sendCredentials"
                wire:confirm="{{ $guardian->user_id ? 'Réinitialiser le mot de passe et renvoyer les identifiants ?' : 'Créer le compte et envoyer les identifiants ?' }}"
                class="{{ $guardian->user_id ? 'btn-outline' : 'btn-warning' }}"
                spinner="sendCredentials"
            />
            @endif
            <x-button label="Modifier" icon="o-pencil"
                      :link="route('admin.guardians.edit', $guardian->uuid)"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- ── Hero ── --}}
    <x-card class="mb-6 overflow-hidden p-0">
        <div class="h-24 bg-linear-to-r from-emerald-600 via-emerald-500 to-teal-400"></div>
        <div class="px-6 pb-5">
            <div class="flex items-end gap-5 -mt-10 mb-4">
                <div class="w-20 h-20 rounded-2xl bg-base-100 border-4 border-base-100 shadow-lg overflow-hidden flex items-center justify-center shrink-0">
                    @if($guardian->photo_url)
                    <img src="{{ $guardian->photo_url }}" alt="{{ $guardian->full_name }}" class="w-full h-full object-cover" />
                    @else
                    <span class="text-3xl font-black text-primary">{{ strtoupper(substr($guardian->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="pb-1 flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-black">{{ $guardian->full_name }}</h1>
                        @if($guardian->is_active)
                        <x-badge value="Actif" class="badge-success badge-sm" />
                        @else
                        <x-badge value="Inactif" class="badge-ghost badge-sm" />
                        @endif
                        @if($guardian->user_id)
                        <x-badge value="Compte parent" class="badge-info badge-sm" />
                        @endif
                    </div>
                    @if($guardian->profession)
                    <p class="text-base-content/60 text-sm mt-0.5">{{ $guardian->profession }}</p>
                    @endif
                </div>
            </div>

            {{-- Contact quick info --}}
            <div class="flex flex-wrap gap-4 text-sm text-base-content/70">
                @if($guardian->email)
                <span class="flex items-center gap-1.5">
                    <x-icon name="o-envelope" class="w-4 h-4 text-primary"/>{{ $guardian->email }}
                </span>
                @endif
                @if($guardian->phone)
                <span class="flex items-center gap-1.5">
                    <x-icon name="o-phone" class="w-4 h-4 text-primary"/>{{ $guardian->phone }}
                </span>
                @endif
                @if($guardian->phone_secondary)
                <span class="flex items-center gap-1.5">
                    <x-icon name="o-phone" class="w-4 h-4 text-base-content/40"/>{{ $guardian->phone_secondary }}
                </span>
                @endif
                @if($guardian->address)
                <span class="flex items-center gap-1.5">
                    <x-icon name="o-map-pin" class="w-4 h-4 text-primary"/>{{ $guardian->address }}
                </span>
                @endif
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Main: Students ── --}}
        <div class="lg:col-span-2 space-y-5">

            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center justify-between w-full">
                        <span>Élèves liés ({{ $guardian->students->count() }})</span>
                        <x-button label="Lier un élève" icon="o-user-plus"
                                  wire:click="$set('showAttachDrawer', true)"
                                  class="btn-outline btn-sm" />
                    </div>
                </x-slot:title>

                @forelse($guardian->students as $student)
                <div wire:key="student-{{ $student->id }}"
                     class="flex items-center gap-4 p-3 rounded-xl border border-base-200 hover:bg-base-50 transition-colors mb-3 last:mb-0">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary shrink-0">
                        {{ strtoupper(substr($student->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('admin.students.show', $student->uuid) }}" wire:navigate
                               class="font-semibold hover:text-primary text-sm">
                                {{ $student->full_name }}
                            </a>
                            @if($student->pivot->is_primary)
                            <x-badge value="Principal" class="badge-primary badge-xs" />
                            @endif
                            @if($student->pivot->has_custody)
                            <x-badge value="Garde" class="badge-info badge-xs" />
                            @endif
                        </div>
                        <div class="text-xs text-base-content/50 mt-0.5 flex items-center gap-3 flex-wrap">
                            <span>{{ $student->pivot->relation }}</span>
                            @php $enrollment = $student->enrollments->first(); @endphp
                            @if($enrollment?->schoolClass)
                            <span class="flex items-center gap-1">
                                <x-icon name="o-academic-cap" class="w-3 h-3"/>{{ $enrollment->schoolClass->name }}
                            </span>
                            @endif
                            @if($enrollment?->grade)
                            <span>{{ $enrollment->grade->name }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($student->pivot->receive_notifications)
                        <x-icon name="o-bell" class="w-4 h-4 text-success" title="Reçoit les notifications" />
                        @endif
                        @if($student->pivot->can_pickup)
                        <x-icon name="o-hand-raised" class="w-4 h-4 text-info" title="Peut récupérer l'élève" />
                        @endif
                        <x-button icon="o-eye"
                                  :link="route('admin.students.show', $student->uuid)"
                                  class="btn-ghost btn-xs" tooltip="Voir l'élève" wire:navigate />
                        <x-button icon="o-user-minus"
                                  wire:click="detachStudent({{ $student->id }})"
                                  wire:confirm="Détacher {{ $student->full_name }} de ce responsable ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Détacher" />
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-base-content/40">
                    <x-icon name="o-user-group" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p class="text-sm">Aucun élève lié à ce responsable</p>
                    <x-button label="Lier un élève" icon="o-user-plus"
                              wire:click="$set('showAttachDrawer', true)"
                              class="btn-outline btn-sm mt-3" />
                </div>
                @endforelse
            </x-card>

        </div>

        {{-- ── Sidebar ── --}}
        <div class="space-y-5">

            {{-- Identity card --}}
            <x-card title="Identité" separator>
                <dl class="space-y-3 text-sm">
                    @if($guardian->gender)
                    <div class="flex justify-between">
                        <dt class="text-base-content/50">Genre</dt>
                        <dd class="font-medium">{{ $guardian->gender?->label() }}</dd>
                    </div>
                    @endif
                    @if($guardian->national_id)
                    <div class="flex justify-between">
                        <dt class="text-base-content/50">Pièce d'identité</dt>
                        <dd class="font-mono text-xs">{{ $guardian->national_id }}</dd>
                    </div>
                    @endif
                    @if($guardian->profession)
                    <div class="flex justify-between">
                        <dt class="text-base-content/50">Profession</dt>
                        <dd class="font-medium">{{ $guardian->profession }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-base-content/50">Statut</dt>
                        <dd>
                            @if($guardian->is_active)
                            <x-badge value="Actif" class="badge-success badge-sm" />
                            @else
                            <x-badge value="Inactif" class="badge-error badge-sm" />
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/50">Ajouté le</dt>
                        <dd class="text-xs">{{ $guardian->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- Account info --}}
            <x-card title="Espace parent" separator>
                @if($guardian->user_id)
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-success/20 flex items-center justify-center">
                            <x-icon name="o-check-circle" class="w-4 h-4 text-success" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-success">Compte actif</p>
                            <p class="text-xs text-base-content/50">Peut se connecter</p>
                        </div>
                    </div>
                    <div class="text-xs text-base-content/60 space-y-1 bg-base-200 rounded-lg p-3">
                        <p><span class="font-medium">Email :</span> {{ $guardian->email }}</p>
                        <p><span class="font-medium">Rôle :</span> Guardian</p>
                        <p><span class="font-medium">Portail :</span> /guardian</p>
                    </div>
                </div>
                @else
                <div class="text-center py-3">
                    <x-icon name="o-user-circle" class="w-10 h-10 mx-auto mb-2 text-base-content/20" />
                    <p class="text-sm font-medium">Sans compte</p>
                    @if($guardian->email)
                    <p class="text-xs text-base-content/50 mt-1">Cliquez sur "Envoyer identifiants" pour créer le compte.</p>
                    @else
                    <p class="text-xs text-base-content/50 mt-1">Ajoutez un email au profil pour créer un compte.</p>
                    @endif
                </div>
                @endif
            </x-card>

            {{-- Quick actions --}}
            <x-card title="Actions rapides" separator>
                <div class="space-y-2">
                    <a href="{{ route('admin.guardians.edit', $guardian->uuid) }}" wire:navigate class="block">
                        <x-button label="Modifier le profil" icon="o-pencil"
                                  class="btn-outline w-full" />
                    </a>
                    <x-button
                        label="{{ $guardian->is_active ? 'Désactiver' : 'Activer' }}"
                        icon="{{ $guardian->is_active ? 'o-pause-circle' : 'o-play-circle' }}"
                        wire:click="toggleActive"
                        class="w-full {{ $guardian->is_active ? 'btn-ghost text-warning' : 'btn-ghost text-success' }}"
                    />
                </div>
            </x-card>

        </div>
    </div>

    {{-- Attach student drawer --}}
    <x-drawer wire:model="showAttachDrawer" title="Lier un élève" position="right" class="w-80">
        <div class="p-4 space-y-4">
            <x-select
                label="Élève *"
                wire:model="attachStudentId"
                :options="$availableStudents"
                option-value="id"
                option-label="name"
                placeholder="Chercher un élève…"
                placeholder-value=""
                searchable
            />
            <x-select
                label="Relation *"
                wire:model="attachRelation"
                :options="$relationOptions"
                option-value="id"
                option-label="name"
                placeholder="Choisir la relation"
                placeholder-value=""
            />
            <div class="flex items-center gap-3">
                <input type="checkbox" wire:model="attachIsPrimary"
                       class="checkbox checkbox-sm checkbox-primary" id="attach_primary" />
                <label for="attach_primary" class="text-sm font-medium cursor-pointer">
                    Responsable principal
                </label>
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showAttachDrawer', false)" class="btn-ghost" />
            <x-button label="Lier" icon="o-user-plus" wire:click="attachStudent"
                      class="btn-primary" spinner="attachStudent" />
        </x-slot:actions>
    </x-drawer>
</div>
