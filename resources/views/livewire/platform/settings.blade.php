<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Mary\Traits\Toast;

new #[Layout('layouts.platform')] class extends Component {
    use Toast;

    // App settings
    public string $appName      = '';
    public string $supportEmail = '';
    public string $defaultLocale = 'fr';
    public string $trialDays    = '14';

    // Maintenance
    public bool $maintenanceMode = false;

    // Email / SMTP
    public string $smtpHost = '';
    public string $smtpPort = '587';
    public string $smtpUser = '';
    public string $fromName  = '';
    public string $fromEmail = '';

    public function mount(): void
    {
        $this->appName      = config('app.name', 'ScolApp SMS');
        $this->supportEmail = config('mail.from.address', '');
        $this->fromName     = config('mail.from.name', 'ScolApp');
        $this->fromEmail    = config('mail.from.address', '');
        $this->smtpHost     = config('mail.mailers.smtp.host', '');
        $this->smtpPort     = (string) config('mail.mailers.smtp.port', '587');
        $this->smtpUser     = config('mail.mailers.smtp.username', '');
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'appName'       => 'required|string|min:2|max:80',
            'supportEmail'  => 'required|email',
            'defaultLocale' => 'required|in:fr,en,ar',
            'trialDays'     => 'required|integer|min:1|max:90',
        ]);

        // In production: update .env or config in DB
        $this->success('Paramètres généraux enregistrés.', position: 'toast-top toast-end');
    }

    public function saveEmail(): void
    {
        $this->validate([
            'smtpHost'  => 'required|string',
            'smtpPort'  => 'required|integer',
            'fromName'  => 'required|string',
            'fromEmail' => 'required|email',
        ]);

        $this->success('Configuration email enregistrée.', position: 'toast-top toast-end');
    }

    public function sendTestEmail(): void
    {
        // Placeholder: trigger a test mail
        $this->info('Email de test envoyé (simulé).', position: 'toast-top toast-end');
    }

    public function with(): array
    {
        return [
            'localeOptions' => [
                ['id' => 'fr', 'name' => 'Français'],
                ['id' => 'en', 'name' => 'English'],
                ['id' => 'ar', 'name' => 'العربية'],
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-8 space-y-6 max-w-3xl mx-auto">
    <x-header title="Paramètres plateforme" subtitle="Configuration globale de ScolApp SMS" separator />

    {{-- General settings --}}
    <x-card title="Général" shadow class="border-0">
        <x-form wire:submit="saveGeneral">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input wire:model="appName" label="Nom de l'application" placeholder="ScolApp SMS" class="md:col-span-2" />
                <x-input wire:model="supportEmail" label="Email de support" type="email" placeholder="support@scolapp.com" />
                <x-select wire:model="defaultLocale" label="Langue par défaut" :options="$localeOptions" />
                <x-input wire:model="trialDays" label="Durée d'essai par défaut (jours)" type="number" min="1" max="90" />
            </div>
            <x-slot:actions>
                <x-button label="Enregistrer" icon="o-check" type="submit" class="btn-primary btn-sm" spinner="saveGeneral" />
            </x-slot:actions>
        </x-form>
    </x-card>

    {{-- Email / SMTP --}}
    <x-card title="Configuration email (SMTP)" shadow class="border-0">
        <x-form wire:submit="saveEmail">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input wire:model="smtpHost" label="Serveur SMTP" placeholder="smtp.mailgun.org" />
                <x-input wire:model="smtpPort" label="Port" type="number" placeholder="587" />
                <x-input wire:model="smtpUser" label="Utilisateur SMTP" placeholder="postmaster@scolapp.com" />
                <x-input wire:model="fromName" label="Nom expéditeur" placeholder="ScolApp SMS" />
                <x-input wire:model="fromEmail" label="Email expéditeur" type="email" placeholder="noreply@scolapp.com" class="md:col-span-2" />
            </div>
            <x-slot:actions>
                <x-button label="Tester l'email" icon="o-paper-airplane" wire:click="sendTestEmail" class="btn-ghost btn-sm" />
                <x-button label="Enregistrer" icon="o-check" type="submit" class="btn-primary btn-sm" spinner="saveEmail" />
            </x-slot:actions>
        </x-form>
    </x-card>

    {{-- System info --}}
    <x-card title="Informations système" shadow class="border-0">
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-base-content/60 text-xs uppercase tracking-wider mb-1">Version Laravel</p>
                <p class="font-mono font-semibold">{{ app()->version() }}</p>
            </div>
            <div>
                <p class="text-base-content/60 text-xs uppercase tracking-wider mb-1">Version PHP</p>
                <p class="font-mono font-semibold">{{ PHP_VERSION }}</p>
            </div>
            <div>
                <p class="text-base-content/60 text-xs uppercase tracking-wider mb-1">Environnement</p>
                <p class="font-mono font-semibold">{{ app()->environment() }}</p>
            </div>
            <div>
                <p class="text-base-content/60 text-xs uppercase tracking-wider mb-1">Debug</p>
                <p class="font-mono font-semibold {{ config('app.debug') ? 'text-warning' : 'text-success' }}">
                    {{ config('app.debug') ? 'Activé ⚠️' : 'Désactivé ✓' }}
                </p>
            </div>
            <div>
                <p class="text-base-content/60 text-xs uppercase tracking-wider mb-1">Cache driver</p>
                <p class="font-mono font-semibold">{{ config('cache.default') }}</p>
            </div>
            <div>
                <p class="text-base-content/60 text-xs uppercase tracking-wider mb-1">Queue driver</p>
                <p class="font-mono font-semibold">{{ config('queue.default') }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('platform.dashboard') }}" class="btn btn-ghost btn-sm">
                <x-icon name="o-arrow-path" class="w-4 h-4" />
                Vider le cache (artisan)
            </a>
        </div>
    </x-card>

    {{-- Danger zone --}}
    <x-card title="Zone sensible" shadow class="border-0 border-warning/30">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-sm">Mode maintenance</p>
                    <p class="text-xs text-base-content/60">Met l'application hors ligne pour tous les utilisateurs sauf super-admin.</p>
                </div>
                <x-toggle wire:model.live="maintenanceMode" class="toggle-warning" />
            </div>
        </div>
    </x-card>
</div>
