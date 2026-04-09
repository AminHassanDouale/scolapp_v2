<?php
use App\Enums\GenderType;
use App\Enums\GuardianRelation;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public Guardian $guardian;

    public string $name            = '';
    public string $email           = '';
    public string $phone           = '';
    public string $phone_secondary = '';
    public string $whatsapp_number = '';
    public string $gender          = '';
    public string $profession      = '';
    public string $national_id     = '';
    public string $address         = '';
    public bool   $is_active       = true;
    public mixed  $photo           = null;

    public function mount(string $uuid): void
    {
        $this->guardian = Guardian::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with(['students'])
            ->firstOrFail();

        $this->fill([
            'name'            => $this->guardian->name,
            'email'           => $this->guardian->email ?? '',
            'phone'           => $this->guardian->phone ?? '',
            'phone_secondary' => $this->guardian->phone_secondary ?? '',
            'whatsapp_number' => $this->guardian->whatsapp_number ?? '',
            'gender'          => $this->guardian->gender?->value ?? '',
            'profession'      => $this->guardian->profession ?? '',
            'national_id'     => $this->guardian->national_id ?? '',
            'address'         => $this->guardian->address ?? '',
            'is_active'       => $this->guardian->is_active,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'name'            => 'required|string|max:200',
            'email'           => 'nullable|email|max:200|unique:guardians,email,' . $this->guardian->id,
            'phone'           => 'nullable|string|max:30',
            'phone_secondary' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'gender'          => 'nullable|in:male,female',
            'profession'      => 'nullable|string|max:200',
            'national_id'     => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:500',
        ]);

        $this->guardian->update([
            'name'            => $this->name,
            'email'           => $this->email ?: null,
            'phone'           => $this->phone ?: null,
            'phone_secondary' => $this->phone_secondary ?: null,
            'whatsapp_number' => $this->whatsapp_number ?: null,
            'gender'          => $this->gender ?: null,
            'profession'      => $this->profession ?: null,
            'national_id'     => $this->national_id ?: null,
            'address'         => $this->address ?: null,
            'is_active'       => $this->is_active,
        ]);

        // Sync name on linked user account if exists
        if ($this->guardian->user_id) {
            $this->guardian->user?->update([
                'name'             => $this->guardian->full_name,
                'phone'            => $this->phone ?: null,
                'whatsapp_number'  => $this->whatsapp_number ?: null,
            ]);
        }

        $this->success('Responsable mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function uploadPhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:2048|mimes:jpg,jpeg,png,webp']);

        if ($this->guardian->photo) {
            Storage::disk('public')->delete($this->guardian->photo);
        }

        $path = $this->photo->store('photos/guardians/' . auth()->user()->school_id, 'public');
        $this->guardian->update(['photo' => $path]);
        $this->photo = null;
        $this->success('Photo mise à jour.', position: 'toast-top toast-end', icon: 'o-camera', css: 'alert-success', timeout: 3000);
    }

    public function removePhoto(): void
    {
        if ($this->guardian->photo) {
            Storage::disk('public')->delete($this->guardian->photo);
            $this->guardian->update(['photo' => null]);
        }
        $this->success('Photo supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function detachStudent(int $studentId): void
    {
        $this->guardian->students()->detach($studentId);
        $this->guardian->load('students');
        $this->success('Élève détaché.', position: 'toast-top toast-end', icon: 'o-user-minus', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        return [
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
                <a href="{{ route('admin.guardians.index') }}" wire:navigate class="hover:text-primary">Responsables</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <a href="{{ route('admin.guardians.show', $guardian->uuid) }}" wire:navigate class="hover:text-primary">{{ $guardian->full_name }}</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">Modifier</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="Voir le profil" icon="o-eye"
                      :link="route('admin.guardians.show', $guardian->uuid)"
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
                        <x-select label="Genre" wire:model="gender"
                                  :options="$genders" option-value="id" option-label="name"
                                  placeholder="Non spécifié" placeholder-value="" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Téléphone principal" wire:model="phone" icon="o-phone" />
                        <x-input label="Téléphone secondaire" wire:model="phone_secondary" icon="o-phone" />
                    </div>
                    <x-input label="WhatsApp" wire:model="whatsapp_number"
                             icon="o-chat-bubble-left-ellipsis"
                             hint="Laissez vide pour utiliser le téléphone principal" />
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Profession" wire:model="profession" icon="o-briefcase" />
                        <x-input label="CIN / Passeport" wire:model="national_id" icon="o-identification" />
                    </div>
                    <x-input label="Adresse" wire:model="address" icon="o-map-pin" />

                    <div class="flex items-center justify-between pt-2 border-t border-base-200">
                        <div>
                            <p class="font-semibold text-sm">Compte actif</p>
                            <p class="text-xs text-base-content/50">Le responsable peut se connecter à l'espace parent</p>
                        </div>
                        <input type="checkbox" wire:model="is_active" class="toggle toggle-success" />
                    </div>

                    <x-slot:actions>
                        <x-button label="Enregistrer les modifications" type="submit"
                                  icon="o-check" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>

            {{-- Linked students --}}
            <x-card title="Élèves liés" separator>
                @forelse($guardian->students as $student)
                <div wire:key="student-{{ $student->id }}" class="flex items-center justify-between py-2 border-b border-base-200 last:border-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-xs shrink-0">
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-semibold text-sm">{{ $student->full_name }}</p>
                            <div class="flex items-center gap-2">
                                <p class="text-xs text-base-content/50">{{ $student->pivot->relation }}</p>
                                @if($student->pivot->is_primary)
                                <x-badge value="Principal" class="badge-primary badge-xs" />
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.students.show', $student->uuid) }}" wire:navigate>
                            <x-button icon="o-eye" class="btn-ghost btn-xs" tooltip="Voir l'élève" />
                        </a>
                        <x-button icon="o-user-minus"
                                  wire:click="detachStudent({{ $student->id }})"
                                  wire:confirm="Détacher cet élève de ce responsable ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Détacher" />
                    </div>
                </div>
                @empty
                <p class="text-sm text-base-content/40 py-4 text-center">Aucun élève lié</p>
                @endforelse
            </x-card>

        </div>

        {{-- ── Sidebar: Photo ── --}}
        <div class="space-y-5">

            <x-card title="Photo de profil" separator>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-28 h-28 rounded-2xl overflow-hidden bg-base-200 border border-base-300 flex items-center justify-center">
                        @if($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover" />
                        @elseif($guardian->photo_url)
                        <img src="{{ $guardian->photo_url }}" alt="{{ $guardian->full_name }}" class="w-full h-full object-cover" />
                        @else
                        <span class="text-4xl font-black text-primary">{{ strtoupper(substr($guardian->name, 0, 1)) }}</span>
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
                        @if($guardian->photo)
                        <x-button icon="o-trash" wire:click="removePhoto"
                                  wire:confirm="Supprimer la photo ?"
                                  class="btn-ghost btn-sm text-error" tooltip="Supprimer la photo" />
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- Account info --}}
            <x-card title="Compte parent" separator>
                @if($guardian->user_id)
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-2">
                        <x-badge value="Compte actif" class="badge-success" />
                    </div>
                    <p class="text-base-content/60">Email : {{ $guardian->email }}</p>
                    <a href="{{ route('admin.guardians.show', $guardian->uuid) }}" wire:navigate>
                        <x-button label="Gérer les identifiants" icon="o-key"
                                  class="btn-outline btn-sm w-full mt-2" />
                    </a>
                </div>
                @else
                <div class="text-center py-2 text-sm text-base-content/50">
                    <x-icon name="o-user-circle" class="w-8 h-8 mx-auto mb-1 opacity-30" />
                    <p>Pas de compte parent</p>
                    @if($guardian->email)
                    <a href="{{ route('admin.guardians.show', $guardian->uuid) }}" wire:navigate>
                        <x-button label="Créer le compte" icon="o-plus"
                                  class="btn-outline btn-sm mt-2" />
                    </a>
                    @else
                    <p class="text-xs mt-1">Ajoutez un email pour créer un compte</p>
                    @endif
                </div>
                @endif
            </x-card>

        </div>
    </div>
</div>
