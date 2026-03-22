<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\School;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new #[Layout('layouts.platform')] class extends Component {
    use Toast;

    #[Validate('required|string|min:2|max:100')]
    public string $name = '';

    #[Validate('required|string|max:20|unique:schools,code')]
    public string $code = '';

    #[Validate('nullable|string|max:100')]
    public string $city = '';

    #[Validate('nullable|string|size:2')]
    public string $country = 'DJ';

    #[Validate('nullable|email|max:100|unique:schools,email')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public string $phone = '';

    #[Validate('nullable|string|max:100')]
    public string $address = '';

    #[Validate('required|in:trial,basic,pro,enterprise')]
    public string $plan = 'trial';

    #[Validate('nullable|string|max:100')]
    public string $contact_name = '';

    #[Validate('required|string|max:10')]
    public string $currency = 'DJF';

    #[Validate('required|string|max:10')]
    public string $timezone = 'Africa/Djibouti';

    #[Validate('nullable|integer|min:0')]
    public ?int $trial_days = 14;

    #[Validate('nullable|integer|min:1|max:120')]
    public ?int $subscription_months = 12;

    public function save(): void
    {
        $this->validate();

        $slug = Str::slug($this->name);
        $baseSlug = $slug;
        $i = 1;
        while (School::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        $trialEndsAt        = null;
        $subscriptionEndsAt = null;

        if ($this->plan === 'trial') {
            $trialEndsAt = now()->addDays($this->trial_days ?? 14);
        } else {
            $subscriptionEndsAt = now()->addMonths($this->subscription_months ?? 12);
        }

        $school = School::create([
            'uuid'                 => (string) Str::uuid(),
            'name'                 => $this->name,
            'slug'                 => $slug,
            'code'                 => strtoupper($this->code),
            'city'                 => $this->city,
            'country'              => $this->country,
            'email'                => $this->email ?: null,
            'phone'                => $this->phone ?: null,
            'address'              => $this->address ?: null,
            'contact_name'         => $this->contact_name ?: null,
            'currency'             => $this->currency,
            'default_locale'       => 'fr',
            'timezone'             => $this->timezone,
            'date_format'          => 'd/m/Y',
            'vat_rate'             => 0,
            'plan'                 => $this->plan,
            'trial_ends_at'        => $trialEndsAt,
            'subscription_ends_at' => $subscriptionEndsAt,
            'is_active'            => true,
        ]);

        $this->success("École \"{$school->name}\" créée avec succès.", position: 'toast-top toast-end', redirectTo: route('platform.schools.show', $school->uuid));
    }

    public function with(): array
    {
        return [
            'plans' => [
                ['id' => 'trial',      'name' => 'Essai (Trial)'],
                ['id' => 'basic',      'name' => 'Basic'],
                ['id' => 'pro',        'name' => 'Pro'],
                ['id' => 'enterprise', 'name' => 'Enterprise'],
            ],
            'currencies' => [
                ['id' => 'DJF', 'name' => 'DJF — Franc Djiboutien'],
                ['id' => 'EUR', 'name' => 'EUR — Euro'],
                ['id' => 'USD', 'name' => 'USD — Dollar américain'],
                ['id' => 'XOF', 'name' => 'XOF — Franc CFA'],
            ],
            'timezones' => [
                ['id' => 'Africa/Djibouti', 'name' => 'Africa/Djibouti'],
                ['id' => 'Africa/Nairobi',  'name' => 'Africa/Nairobi'],
                ['id' => 'Africa/Addis_Ababa', 'name' => 'Africa/Addis_Ababa'],
                ['id' => 'Europe/Paris',    'name' => 'Europe/Paris'],
                ['id' => 'UTC',             'name' => 'UTC'],
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-8 space-y-6 max-w-3xl mx-auto">
    <x-header title="Nouvelle école" subtitle="Inscrire une nouvelle école sur la plateforme" separator>
        <x-slot:actions>
            <x-button label="Retour" icon="o-arrow-left" link="{{ route('platform.schools.index') }}" class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <x-form wire:submit="save">
        {{-- Identity --}}
        <x-card title="Identité de l'école" shadow class="border-0">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input wire:model="name" label="Nom de l'école" placeholder="Lycée Excellence Djibouti" required class="md:col-span-2" />
                <x-input wire:model="code" label="Code unique" placeholder="LYC001" hint="Identifiant court, ex: DEMO001" required />
                <x-input wire:model="contact_name" label="Nom du directeur" placeholder="Ahmed Diriye" />
                <x-input wire:model="email" label="Email de contact" placeholder="contact@ecole.dj" type="email" />
                <x-input wire:model="phone" label="Téléphone" placeholder="+253 77 00 00 00" />
                <x-input wire:model="address" label="Adresse" placeholder="Rue de la Liberté, Djibouti" class="md:col-span-2" />
                <x-input wire:model="city" label="Ville" placeholder="Djibouti" />
                <x-input wire:model="country" label="Pays (code 2 lettres)" placeholder="DJ" maxlength="2" />
            </div>
        </x-card>

        {{-- Plan & Billing --}}
        <x-card title="Plan & Abonnement" shadow class="border-0">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select wire:model.live="plan" label="Plan" :options="$plans" required />
                <x-select wire:model="currency" label="Devise" :options="$currencies" />

                @if($plan === 'trial')
                    <x-input wire:model="trial_days" label="Durée de l'essai (jours)" type="number" min="1" max="90" placeholder="14" />
                @else
                    <x-input wire:model="subscription_months" label="Durée de l'abonnement (mois)" type="number" min="1" max="120" placeholder="12" />
                @endif

                <x-select wire:model="timezone" label="Fuseau horaire" :options="$timezones" />
            </div>

            {{-- Plan details --}}
            <div class="mt-4 p-3 rounded-xl bg-base-200 text-sm space-y-1">
                @if($plan === 'trial')
                    <p class="text-warning font-medium">⚠️ Essai : 30 élèves max, 5 enseignants max</p>
                @elseif($plan === 'basic')
                    <p class="text-success font-medium">✓ Basic : 200 élèves max, 20 enseignants max</p>
                @elseif($plan === 'pro')
                    <p class="text-info font-medium">✓ Pro : 1 000 élèves max, 100 enseignants max</p>
                @elseif($plan === 'enterprise')
                    <p class="text-primary font-medium">★ Enterprise : illimité</p>
                @endif
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="Annuler" link="{{ route('platform.schools.index') }}" class="btn-ghost" />
            <x-button label="Créer l'école" icon="o-check" type="submit" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
