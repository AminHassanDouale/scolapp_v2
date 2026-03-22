<?php
use App\Mail\TeacherWelcomeMail;
use App\Models\School;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public string $name           = '';
    public string $email          = '';
    public string $phone          = '';
    public string $gender         = '';
    public string $hire_date      = '';
    public string $specialization = '';
    public string $address        = '';
    public string $notes          = '';
    public array  $subjectIds     = [];
    public mixed  $photo          = null;

    public function save(): void
    {
        $this->validate([
            'name'           => 'required|string|max:200',
            'email'          => 'required|email|unique:teachers,email',
            'phone'          => 'nullable|string|max:30',
            'gender'         => 'nullable|in:male,female',
            'hire_date'      => 'nullable|date',
            'specialization' => 'nullable|string|max:200',
            'photo'          => 'nullable|image|max:2048|mimes:jpg,jpeg,png,webp',
        ]);

        $teacher = Teacher::create([
            'school_id'      => auth()->user()->school_id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone ?: null,
            'gender'         => $this->gender ?: null,
            'hire_date'      => $this->hire_date ?: null,
            'specialization' => $this->specialization ?: null,
            'address'        => $this->address ?: null,
            'notes'          => $this->notes ?: null,
            'is_active'      => true,
        ]);

        if ($this->subjectIds) {
            $teacher->subjects()->sync($this->subjectIds);
        }

        if ($this->photo) {
            $path = $this->photo->store('photos/teachers/' . auth()->user()->school_id, 'public');
            $teacher->update(['photo' => $path]);
        }

        // ── Create login account for teacher ──────────────────────────────────
        $plainPassword = null;
        if ($teacher->email && !User::where('email', $teacher->email)->exists()) {
            $plainPassword = Str::password(12, symbols: false);
            $user = User::create([
                'uuid'       => (string) Str::uuid(),
                'school_id'  => auth()->user()->school_id,
                'name'       => $teacher->full_name,
                'email'      => $teacher->email,
                'password'   => Hash::make($plainPassword),
                'ui_lang'    => 'fr',
                'timezone'   => 'Africa/Djibouti',
            ]);
            $user->assignRole('teacher');
            $teacher->update(['user_id' => $user->id]);
        }

        // ── Send welcome email with credentials ───────────────────────────────
        if ($teacher->email) {
            try {
                $school = School::findOrFail(auth()->user()->school_id);
                $teacher->load(['subjects', 'schoolClasses.grade']);
                Mail::to($teacher->email)->send(new TeacherWelcomeMail($teacher, $school, $plainPassword ?? ''));
                session()->flash('success', 'Enseignant créé — identifiants envoyés à ' . $teacher->email);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('TeacherWelcomeMail failed: ' . $e->getMessage());
                session()->flash('success', 'Enseignant créé avec succès.');
            }
        } else {
            session()->flash('success', 'Enseignant créé avec succès.');
        }

        $this->redirect(route('admin.teachers.show', $teacher->uuid));
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        return [
            'subjectOptions' => Subject::where('school_id', $schoolId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
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
                <span class="text-base-content font-semibold">Nouvel enseignant</span>
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
                                 placeholder="Ahmed Hassan" required />

                        <div class="grid grid-cols-2 gap-4">
                            <x-input label="Email *" wire:model="email" type="email"
                                     placeholder="ahmed.hassan@ecole.dj" required />
                            <x-input label="Téléphone" wire:model="phone"
                                     placeholder="+253 77 00 00 00" icon="o-phone" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <x-select label="Genre" wire:model="gender"
                                      :options="[['id'=>'male','name'=>'Masculin'],['id'=>'female','name'=>'Féminin']]"
                                      option-value="id" option-label="name"
                                      placeholder="Non précisé" placeholder-value="" />
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
                    </div>
                </x-card>

                {{-- Subjects --}}
                <x-card title="Matières enseignées" separator>
                    @if($subjectOptions->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($subjectOptions as $subject)
                        @php $checked = in_array($subject->id, $subjectIds); @endphp
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all
                                      {{ $checked ? 'border-primary bg-primary/5' : 'border-base-200 hover:bg-base-100' }}">
                            <input type="checkbox" class="checkbox checkbox-sm checkbox-primary"
                                   wire:model.live="subjectIds" value="{{ $subject->id }}" />
                            <div class="flex items-center gap-1.5 min-w-0">
                                @if($subject->color)
                                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                      style="background-color: {{ $subject->color }}"></span>
                                @endif
                                <span class="text-sm font-medium truncate">{{ $subject->name }}</span>
                                @if($subject->code)
                                <span class="text-xs text-base-content/40 shrink-0">({{ $subject->code }})</span>
                                @endif
                            </div>
                        </label>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-6 text-base-content/40">
                        <x-icon name="o-book-open" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                        <p class="text-sm">Aucune matière disponible.</p>
                        <a href="{{ route('admin.academic.subjects') }}" wire:navigate class="link link-primary text-sm mt-1">
                            Créer des matières
                        </a>
                    </div>
                    @endif
                </x-card>

            </div>

            {{-- ── Right: Photo + preview ── --}}
            <div class="space-y-5">

                {{-- Photo upload --}}
                <x-card title="Photo de profil" separator>
                    <div class="flex flex-col items-center gap-3">
                        {{-- Preview --}}
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

                {{-- Live preview card --}}
                <x-card title="Aperçu" separator>
                    <div class="flex items-center gap-3 p-2">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-lg shrink-0">
                            {{ $name ? strtoupper(substr($name, 0, 1)) : '?' }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold truncate">
                                {{ $name ?: 'Nom complet' }}
                            </p>
                            @if($specialization)
                            <p class="text-xs text-base-content/50 truncate">{{ $specialization }}</p>
                            @endif
                            @if($email)
                            <p class="text-xs text-base-content/50 truncate">{{ $email }}</p>
                            @endif
                        </div>
                    </div>
                    @if(count($subjectIds))
                    <div class="mt-3 pt-3 border-t border-base-200">
                        <p class="text-xs text-base-content/40 mb-1.5">Matières ({{ count($subjectIds) }})</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($subjectOptions->whereIn('id', $subjectIds) as $s)
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  style="background-color: {{ $s->color ?? '#6366f1' }}20; color: {{ $s->color ?? '#6366f1' }}">
                                {{ $s->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </x-card>

                {{-- Submit --}}
                <div class="flex gap-2">
                    <a href="{{ route('admin.teachers.index') }}" wire:navigate class="flex-1">
                        <x-button label="Annuler" icon="o-arrow-left" class="btn-outline w-full" />
                    </a>
                    <x-button label="Créer" type="submit" icon="o-check"
                              class="btn-primary flex-1" spinner />
                </div>

            </div>
        </div>
    </x-form>
</div>
