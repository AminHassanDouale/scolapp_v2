<?php
use App\Models\School;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public string $name           = '';
    public string $code           = '';
    public string $address        = '';
    public string $city           = '';
    public string $country        = 'DJ';
    public string $phone          = '';
    public string $email          = '';
    public string $website        = '';
    public string $currency       = 'DJF';
    public string $default_locale = 'fr';
    public string $timezone       = 'Africa/Djibouti';
    public string $date_format    = 'd/m/Y';
    public float  $vat_rate       = 0;
    public bool   $is_active      = true;

    // Logo upload
    #[Validate(['logo' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048'])]
    public $logo = null; // TemporaryUploadedFile

    private ?School $school = null;

    public function mount(): void
    {
        $this->school = School::findOrFail(auth()->user()->school_id);
        $this->fill([
            'name'           => $this->school->name,
            'code'           => $this->school->code ?? '',
            'address'        => $this->school->address ?? '',
            'city'           => $this->school->city ?? '',
            'country'        => $this->school->country ?? 'DJ',
            'phone'          => $this->school->phone ?? '',
            'email'          => $this->school->email ?? '',
            'website'        => $this->school->website ?? '',
            'currency'       => $this->school->currency ?? 'DJF',
            'default_locale' => $this->school->default_locale ?? 'fr',
            'timezone'       => $this->school->timezone ?? 'Africa/Djibouti',
            'date_format'    => $this->school->date_format ?? 'd/m/Y',
            'vat_rate'       => $this->school->vat_rate ?? 0,
            'is_active'      => $this->school->is_active ?? true,
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:200',
            'code'        => 'required|string|max:20',
            'email'       => 'nullable|email|max:200',
            'vat_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        School::findOrFail(auth()->user()->school_id)->update([
            'name'           => $this->name,
            'code'           => $this->code,
            'address'        => $this->address ?: null,
            'city'           => $this->city ?: null,
            'country'        => $this->country ?: null,
            'phone'          => $this->phone ?: null,
            'email'          => $this->email ?: null,
            'website'        => $this->website ?: null,
            'currency'       => $this->currency,
            'default_locale' => $this->default_locale,
            'timezone'       => $this->timezone,
            'date_format'    => $this->date_format,
            'vat_rate'       => $this->vat_rate,
            'is_active'      => $this->is_active,
        ]);

        $this->success('Paramètres de l\'école enregistrés.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function uploadLogo(): void
    {
        $this->validateOnly('logo');

        $school = School::findOrFail(auth()->user()->school_id);

        // Delete old logo file if present
        if ($school->logo && Storage::disk('public')->exists($school->logo)) {
            Storage::disk('public')->delete($school->logo);
        }

        $path = $this->logo->store('schools/logos', 'public');
        $school->update(['logo' => $path]);

        $this->logo = null;
        $this->school = $school->fresh();

        $this->success('Logo mis à jour.', position: 'toast-top toast-end', timeout: 3000);
    }

    public function deleteLogo(): void
    {
        $school = School::findOrFail(auth()->user()->school_id);

        if ($school->logo && Storage::disk('public')->exists($school->logo)) {
            Storage::disk('public')->delete($school->logo);
        }

        $school->update(['logo' => null]);
        $this->school = $school->fresh();

        $this->success('Logo supprimé.', position: 'toast-top toast-end', timeout: 3000);
    }

    public function with(): array
    {
        return [
            'currentSchool' => School::findOrFail(auth()->user()->school_id),
            'locales'       => [['id'=>'fr','name'=>'Français'],['id'=>'en','name'=>'English'],['id'=>'ar','name'=>'العربية']],
            'currencies'    => [['id'=>'DJF','name'=>'DJF — Franc Djiboutien'],['id'=>'USD','name'=>'USD — Dollar'],['id'=>'EUR','name'=>'EUR — Euro'],['id'=>'ETB','name'=>'ETB — Birr Éthiopien']],
            'timezones'     => [['id'=>'Africa/Djibouti','name'=>'Africa/Djibouti (UTC+3)'],['id'=>'Africa/Nairobi','name'=>'Africa/Nairobi (UTC+3)'],['id'=>'Europe/Paris','name'=>'Europe/Paris'],['id'=>'UTC','name'=>'UTC']],
            'dateFormats'   => [['id'=>'d/m/Y','name'=>'31/12/2025'],['id'=>'Y-m-d','name'=>'2025-12-31'],['id'=>'m/d/Y','name'=>'12/31/2025']],
        ];
    }
};
?>

<div>
    <x-header title="Paramètres de l'école" separator progress-indicator />

    <x-tabs>
        {{-- General --}}
        <x-tab name="general" label="Général" icon="o-building-office">
            {{-- Logo section --}}
            <x-card class="mt-4" title="Logo de l'école" subtitle="Format PNG, JPG ou SVG · Max 2 Mo · Recommandé : 200×200 px">
                <div class="flex items-start gap-6">
                    {{-- Current logo preview --}}
                    <div class="shrink-0">
                        @if($logo)
                            {{-- Temporary upload preview --}}
                            <img src="{{ $logo->temporaryUrl() }}"
                                 alt="Aperçu"
                                 class="w-28 h-28 rounded-xl object-contain border-2 border-dashed border-primary bg-base-100 p-1">
                            <p class="text-xs text-primary text-center mt-1 font-medium">Aperçu</p>
                        @elseif($currentSchool->logo)
                            <img src="{{ $currentSchool->logo_url }}"
                                 alt="{{ $currentSchool->name }}"
                                 class="w-28 h-28 rounded-xl object-contain border border-base-300 bg-base-100 p-1">
                        @else
                            <div class="w-28 h-28 rounded-xl border-2 border-dashed border-base-300 bg-base-200 flex flex-col items-center justify-center gap-1">
                                <x-icon name="o-photo" class="w-8 h-8 text-base-content/30" />
                                <span class="text-xs text-base-content/40">Aucun logo</span>
                            </div>
                        @endif
                    </div>

                    {{-- Upload controls --}}
                    <div class="flex-1 space-y-3">
                        <x-form wire:submit="uploadLogo" class="space-y-3">
                            <x-file wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    label="Choisir un nouveau logo" hint="PNG, JPG, WEBP ou SVG — max 2 Mo" />
                            @error('logo')
                                <p class="text-error text-sm">{{ $message }}</p>
                            @enderror
                            <div class="flex items-center gap-2">
                                <x-button label="Enregistrer le logo" type="submit" icon="o-arrow-up-tray"
                                          class="btn-primary btn-sm" spinner="uploadLogo"
                                          :disabled="!$logo" />
                                @if($currentSchool->logo)
                                    <x-button label="Supprimer" icon="o-trash"
                                              wire:click="deleteLogo"
                                              wire:confirm="Supprimer le logo de l'école ?"
                                              class="btn-ghost btn-sm text-error"
                                              spinner="deleteLogo" />
                                @endif
                            </div>
                        </x-form>
                    </div>
                </div>
            </x-card>

            {{-- School info --}}
            <x-card class="mt-4" title="Informations générales">
                <x-form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Nom de l'école *" wire:model="name" required />
                        <x-input label="Code *" wire:model="code" placeholder="SCH001" maxlength="20" required />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Téléphone" wire:model="phone" />
                        <x-input label="Email" wire:model="email" type="email" />
                    </div>
                    <x-input label="Site web" wire:model="website" placeholder="https://..." />
                    <x-input label="Adresse" wire:model="address" />
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Ville" wire:model="city" />
                        <x-input label="Pays (code)" wire:model="country" placeholder="DJ" maxlength="5" />
                    </div>
                    <x-checkbox label="École active" wire:model="is_active" />
                    <x-slot:actions>
                        <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        {{-- Finance --}}
        <x-tab name="finance" label="Finance" icon="o-banknotes">
            <x-card class="mt-4">
                <x-form wire:submit="save" class="space-y-4">
                    <x-select label="Devise" wire:model="currency"
                              :options="$currencies" option-value="id" option-label="name" />
                    <x-input label="Taux de TVA (%)" wire:model="vat_rate" type="number" step="0.1" min="0" max="100" />
                    <x-alert icon="o-information-circle" class="alert-info text-sm">
                        La TVA s'appliquera automatiquement aux nouvelles factures créées.
                    </x-alert>
                    <x-slot:actions>
                        <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>

        {{-- Localization --}}
        <x-tab name="localization" label="Localisation" icon="o-globe-alt">
            <x-card class="mt-4">
                <x-form wire:submit="save" class="space-y-4">
                    <x-select label="Langue par défaut" wire:model="default_locale"
                              :options="$locales" option-value="id" option-label="name" />
                    <x-select label="Fuseau horaire" wire:model="timezone"
                              :options="$timezones" option-value="id" option-label="name" />
                    <x-select label="Format de date" wire:model="date_format"
                              :options="$dateFormats" option-value="id" option-label="name" />
                    <x-slot:actions>
                        <x-button label="Enregistrer" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </x-tab>
    </x-tabs>
</div>
