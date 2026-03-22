<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $current_password    = '';
    public string $password            = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->success('Mot de passe mis à jour.', position: 'toast-top toast-end', icon: 'o-lock-closed', css: 'alert-success', timeout: 3000);
    }
};
?>

<div>
    <x-header title="Sécurité du compte" separator progress-indicator />

    <div class="max-w-lg">
        <x-card title="Changer le mot de passe" separator>
            <x-form wire:submit="updatePassword" class="space-y-4">
                <x-input label="Mot de passe actuel *" wire:model="current_password"
                         type="password" required />
                <x-input label="Nouveau mot de passe *" wire:model="password"
                         type="password" required />
                <x-input label="Confirmer le nouveau mot de passe *" wire:model="password_confirmation"
                         type="password" required />
                <x-slot:actions>
                    <x-button label="Mettre à jour" type="submit" icon="o-shield-check"
                              class="btn-primary" spinner />
                </x-slot:actions>
            </x-form>
        </x-card>

        <x-card title="Sessions actives" separator class="mt-4">
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-xl bg-base-200">
                    <div class="flex items-center gap-3">
                        <x-icon name="o-computer-desktop" class="w-6 h-6 text-base-content/60" />
                        <div>
                            <p class="text-sm font-semibold">Session actuelle</p>
                            <p class="text-xs text-base-content/50">{{ request()->ip() }} — {{ request()->userAgent() ? substr(request()->userAgent(), 0, 40) . '...' : 'Navigateur inconnu' }}</p>
                        </div>
                    </div>
                    <x-badge value="Active" class="badge-success badge-sm" />
                </div>
            </div>
        </x-card>
    </div>
</div>
