<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public string $name             = '';
    public string $email            = '';
    public string $phone            = '';
    public string $whatsapp_number  = '';
    public string $ui_lang          = 'fr';
    public string $timezone         = 'Africa/Djibouti';
    public $avatar                  = null;   // TemporaryUploadedFile

    // Password change
    public string $current_password = '';
    public string $new_password     = '';
    public string $new_password_confirmation = '';
    public bool   $showPasswordForm = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->fill([
            'name'            => $user->name ?? '',
            'email'           => $user->email ?? '',
            'phone'           => $user->phone ?? '',
            'whatsapp_number' => $user->whatsapp_number ?? '',
            'ui_lang'         => $user->ui_lang ?? 'fr',
            'timezone'        => $user->timezone ?? 'Africa/Djibouti',
        ]);
    }

    public function saveProfile(): void
    {
        $userId = auth()->id();
        $this->validate([
            'name'            => 'required|string|max:200',
            'email'           => "required|email|unique:users,email,{$userId}",
            'phone'           => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
        ]);

        auth()->user()->update([
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone ?: null,
            'whatsapp_number' => $this->whatsapp_number ?: null,
        ]);

        $this->success('Profil mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function saveAvatar(): void
    {
        $this->validate(['avatar' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048']);

        $user = auth()->user();
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $path = $this->avatar->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        $this->avatar = null;
        $this->success('Photo mise à jour.', position: 'toast-top toast-end', icon: 'o-photo', css: 'alert-success', timeout: 3000);
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if (!Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'Le mot de passe actuel est incorrect.');
            return;
        }

        auth()->user()->update(['password' => Hash::make($this->new_password)]);
        $this->reset(['current_password', 'new_password', 'new_password_confirmation', 'showPasswordForm']);
        $this->success('Mot de passe modifié.', position: 'toast-top toast-end', icon: 'o-lock-closed', css: 'alert-success', timeout: 3000);
    }

    public function switchLang(string $lang): void
    {
        if (! in_array($lang, ['fr', 'en', 'ar'])) return;
        auth()->user()->update(['ui_lang' => $lang]);
        session(['locale' => $lang]);
        // Full reload so the whole UI (menus + content) picks up the new locale
        $this->redirect(route('profile.show'), navigate: false);
    }

    public function savePreferences(): void
    {
        auth()->user()->update(['timezone' => $this->timezone]);
        $this->success('Préférences enregistrées.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $timezones = [
            ['id' => 'Africa/Djibouti',    'name' => 'Africa/Djibouti (UTC+3)'],
            ['id' => 'Africa/Addis_Ababa', 'name' => 'Africa/Addis_Ababa (UTC+3)'],
            ['id' => 'Africa/Nairobi',     'name' => 'Africa/Nairobi (UTC+3)'],
            ['id' => 'Europe/Paris',       'name' => 'Europe/Paris'],
            ['id' => 'UTC',                'name' => 'UTC'],
        ];

        return [
            'timezones' => $timezones,
            'userRoles' => auth()->user()->getRoleNames(),
            'school'    => \App\Models\School::find(auth()->user()->school_id),
        ];
    }
};
?>

<div>
    <x-header title="Mon profil" separator progress-indicator />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main tabs --}}
        <div class="lg:col-span-2">
            <x-tabs>
                {{-- Info tab --}}
                <x-tab name="info" label="Informations" icon="o-user">
                    <x-card class="mt-4">
                        <x-form wire:submit="saveProfile" class="space-y-4">
                            <x-input label="Nom d'affichage *" wire:model="name" required />
                            <x-input label="Email *" wire:model="email" type="email" required />
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-input label="Téléphone" wire:model="phone" icon="o-phone" placeholder="+253 77…" />
                                <x-input label="WhatsApp" wire:model="whatsapp_number" icon="o-chat-bubble-left-right" placeholder="+253 77…"
                                         hint="Numéro pour les notifications WhatsApp" />
                            </div>
                            <x-slot:actions>
                                <x-button label="Enregistrer" type="submit" icon="o-check"
                                          class="btn-primary" spinner="saveProfile" />
                            </x-slot:actions>
                        </x-form>
                    </x-card>
                </x-tab>

                {{-- Security tab --}}
                <x-tab name="security" label="Sécurité" icon="o-lock-closed">
                    <x-card class="mt-4">
                        @if(!$showPasswordForm)
                        <div class="flex items-center justify-between p-4 bg-base-200 rounded-xl">
                            <div>
                                <p class="font-semibold">Mot de passe</p>
                                <p class="text-sm text-base-content/60">Dernière modification inconnue</p>
                            </div>
                            <x-button label="Changer le mot de passe" icon="o-key"
                                      wire:click="$set('showPasswordForm', true)"
                                      class="btn-outline btn-sm" />
                        </div>
                        @else
                        <x-form wire:submit="changePassword" class="space-y-4">
                            <x-input label="Mot de passe actuel *" wire:model="current_password"
                                     type="password" required />
                            <x-input label="Nouveau mot de passe *" wire:model="new_password"
                                     type="password" required />
                            <x-input label="Confirmer le nouveau mot de passe *"
                                     wire:model="new_password_confirmation"
                                     type="password" required />
                            <x-slot:actions>
                                <x-button label="Annuler" wire:click="$set('showPasswordForm', false)"
                                          class="btn-ghost" />
                                <x-button label="Modifier" type="submit" icon="o-check"
                                          class="btn-primary" spinner="changePassword" />
                            </x-slot:actions>
                        </x-form>
                        @endif
                    </x-card>
                </x-tab>

                {{-- Preferences tab --}}
                <x-tab name="prefs" label="Préférences" icon="o-cog-6-tooth">
                    <x-card class="mt-4 space-y-6">
                        {{-- Language --}}
                        <div>
                            <p class="font-semibold mb-3">Langue de l'interface</p>
                            <div class="flex gap-3">
                                @foreach([['fr','Français','🇫🇷'],['en','English','🇬🇧'],['ar','العربية','🇸🇦']] as [$code,$label,$flag])
                                <button wire:click="switchLang('{{ $code }}')"
                                        class="flex-1 py-3 px-4 rounded-xl border-2 transition-all text-center
                                               {{ $ui_lang === $code ? 'border-primary bg-primary/10 font-bold' : 'border-base-300 hover:border-primary/50' }}">
                                    <div class="text-xl mb-1">{{ $flag }}</div>
                                    <div class="text-sm">{{ $label }}</div>
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Timezone --}}
                        <div>
                            <p class="font-semibold mb-3">Fuseau horaire</p>
                            <x-select wire:model="timezone" :options="$timezones"
                                      option-value="id" option-label="name" />
                        </div>

                        {{-- Theme (Alpine local storage) --}}
                        <div x-data="{ darkMode: localStorage.getItem('theme') === 'dark' }">
                            <p class="font-semibold mb-3">Thème de l'interface</p>
                            <div class="flex gap-3">
                                <button @click="darkMode = false; localStorage.setItem('theme', 'light'); document.documentElement.setAttribute('data-theme', 'light')"
                                        :class="!darkMode ? 'border-primary bg-primary/10 font-bold' : 'border-base-300'"
                                        class="flex-1 py-3 px-4 rounded-xl border-2 transition-all text-center">
                                    ☀️ Clair
                                </button>
                                <button @click="darkMode = true; localStorage.setItem('theme', 'dark'); document.documentElement.setAttribute('data-theme', 'dark')"
                                        :class="darkMode ? 'border-primary bg-primary/10 font-bold' : 'border-base-300'"
                                        class="flex-1 py-3 px-4 rounded-xl border-2 transition-all text-center">
                                    🌙 Sombre
                                </button>
                            </div>
                        </div>

                        <x-button label="Enregistrer les préférences" wire:click="savePreferences"
                                  icon="o-check" class="btn-primary" spinner="savePreferences" />
                    </x-card>
                </x-tab>
            </x-tabs>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Avatar card --}}
            <x-card>
                <div class="text-center py-4">
                    <div class="w-24 h-24 rounded-full overflow-hidden mx-auto mb-3 ring-2 ring-primary/20 bg-primary flex items-center justify-center">
                        @if($avatar)
                            <img src="{{ $avatar->temporaryUrl() }}" class="w-full h-full object-cover" alt="">
                        @elseif(auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar_url }}" class="w-full h-full object-cover" alt="">
                        @else
                            <span class="font-black text-3xl text-primary-content">{{ substr(auth()->user()->name ?? auth()->user()->email ?? '?', 0, 1) }}</span>
                        @endif
                    </div>
                    <h3 class="font-bold text-lg">{{ auth()->user()->name }}</h3>
                    <p class="text-base-content/60 text-sm">{{ auth()->user()->email }}</p>
                    <div class="flex flex-wrap gap-1 justify-center mt-2">
                        @foreach($userRoles as $role)
                        <x-badge :value="$role" class="badge-primary badge-sm" />
                        @endforeach
                    </div>

                    {{-- Avatar upload --}}
                    <div class="mt-4 pt-4 border-t border-base-200">
                        <x-file wire:model="avatar" accept="image/png,image/jpeg,image/webp" hint="PNG/JPG/WEBP — max 2 Mo" />
                        <div wire:loading wire:target="avatar" class="text-xs text-base-content/50 mt-1">Chargement…</div>
                        @if($avatar)
                        <x-button label="Enregistrer la photo" icon="o-check" wire:click="saveAvatar" spinner="saveAvatar" class="btn-primary btn-sm btn-block mt-2" />
                        @endif
                    </div>
                </div>
            </x-card>

            {{-- Account info --}}
            <x-card title="Informations du compte">
                <div class="space-y-3 text-sm">
                    @if($school)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">École</span>
                        <span class="font-semibold truncate ml-2">{{ $school->name }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Membre depuis</span>
                        <span>{{ auth()->user()->created_at->format('d/m/Y') }}</span>
                    </div>
                    @if(auth()->user()->last_login_at)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Dernière connexion</span>
                        <span>{{ auth()->user()->last_login_at->diffForHumans() }}</span>
                    </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
