<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Models\School;
use App\Models\User;
use App\Actions\ProvisionSchoolDefaults;
use Mary\Traits\Toast;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.platform')] class extends Component {
    use Toast, WithFileUploads;

    // ── School ──────────────────────────────────────────────────────────────
    public string $name = '';
    public string $code = '';
    public string $city = '';
    public string $country = 'DJ';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $plan = 'trial';
    public string $contact_name = '';
    public string $currency = 'DJF';
    public string $timezone = 'Africa/Djibouti';
    public ?int $trial_days = 14;
    public ?int $subscription_months = 12;

    // ── Logo ────────────────────────────────────────────────────────────────
    public $logo = null; // TemporaryUploadedFile

    // ── Users & roles ───────────────────────────────────────────────────────
    public array $users = [
        ['name' => '', 'email' => '', 'password' => '', 'role' => 'admin'],
    ];

    // Auto-create the default academic structure (years, classes, rooms A/B)
    public bool $provision_defaults = true;

    public function addUser(): void
    {
        $this->users[] = ['name' => '', 'email' => '', 'password' => '', 'role' => 'teacher'];
    }

    public function removeUser(int $i): void
    {
        if (count($this->users) > 1) {
            unset($this->users[$i]);
            $this->users = array_values($this->users);
        }
    }

    protected function rules(): array
    {
        return [
            'name'                => 'required|string|min:2|max:100',
            'code'                => 'required|string|max:20|unique:schools,code',
            'city'                => 'nullable|string|max:100',
            'country'             => 'nullable|string|size:2',
            'email'               => 'nullable|email|max:100|unique:schools,email',
            'phone'               => 'nullable|string|max:30',
            'address'             => 'nullable|string|max:150',
            'plan'                => 'required|in:trial,basic,pro,enterprise',
            'contact_name'        => 'nullable|string|max:100',
            'currency'            => 'required|string|max:10',
            'timezone'            => 'required|string|max:40',
            'trial_days'          => 'nullable|integer|min:0',
            'subscription_months' => 'nullable|integer|min:1|max:120',
            'logo'                => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            'users'               => 'required|array|min:1',
            'users.*.name'        => 'required|string|max:100',
            'users.*.email'       => 'required|email|max:150|distinct|unique:users,email',
            'users.*.password'    => 'required|string|min:6',
            'users.*.role'        => 'required|string|in:admin,director,accountant,caissier,monitor,teacher',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'users.*.name'     => 'nom',
            'users.*.email'    => 'email',
            'users.*.password' => 'mot de passe',
            'users.*.role'     => 'rôle',
        ];
    }

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

        // Store the logo (public disk) if provided
        $logoPath = $this->logo ? $this->logo->store('schools/logos', 'public') : null;

        $school = DB::transaction(function () use ($slug, $trialEndsAt, $subscriptionEndsAt, $logoPath) {
            $school = School::create([
                'uuid'                 => (string) Str::uuid(),
                'name'                 => $this->name,
                'slug'                 => $slug,
                'code'                 => strtoupper($this->code),
                'logo'                 => $logoPath,
                'city'                 => $this->city ?: null,
                'country'              => $this->country ?: null,
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

            foreach ($this->users as $u) {
                $user = User::create([
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'name'      => $u['name'],
                    'email'     => $u['email'],
                    'password'  => Hash::make($u['password']),
                    'ui_lang'   => 'fr',
                    'timezone'  => $this->timezone,
                ]);
                $user->assignRole($u['role']);
            }

            // Default academic structure: year, cycles, grades, classes, rooms A/B
            if ($this->provision_defaults) {
                app(ProvisionSchoolDefaults::class)->execute($school);
            }

            return $school;
        });

        $count = count($this->users);
        $this->success(
            "École \"{$school->name}\" créée avec {$count} utilisateur(s).",
            position: 'toast-top toast-end',
            redirectTo: route('platform.schools.show', $school->uuid)
        );
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
                ['id' => 'Africa/Djibouti',    'name' => 'Africa/Djibouti'],
                ['id' => 'Africa/Nairobi',     'name' => 'Africa/Nairobi'],
                ['id' => 'Africa/Addis_Ababa', 'name' => 'Africa/Addis_Ababa'],
                ['id' => 'Europe/Paris',       'name' => 'Europe/Paris'],
                ['id' => 'UTC',                'name' => 'UTC'],
            ],
            'roleOptions' => [
                ['id' => 'admin',      'name' => 'Administrateur (Direction)'],
                ['id' => 'director',   'name' => 'Directeur pédagogique'],
                ['id' => 'accountant', 'name' => 'Comptable'],
                ['id' => 'caissier',   'name' => 'Caissier'],
                ['id' => 'monitor',    'name' => 'Surveillant'],
                ['id' => 'teacher',    'name' => 'Enseignant'],
            ],
        ];
    }
};
?>

<div class="p-4 lg:p-8 space-y-6 max-w-3xl mx-auto">
    <x-header title="Nouvelle école" subtitle="Inscrire une nouvelle école, ses utilisateurs et son logo" separator>
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

        {{-- Logo --}}
        <x-card title="Logo de l'école" shadow class="border-0">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="w-20 h-20 rounded-xl bg-base-200 flex items-center justify-center overflow-hidden ring-1 ring-base-300 shrink-0">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="w-full h-full object-cover" alt="Aperçu du logo">
                    @else
                        <span class="text-xs text-base-content/40 text-center px-1">Aucun logo</span>
                    @endif
                </div>
                <div class="flex-1 w-full">
                    <x-file wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            hint="PNG, JPG, WEBP ou SVG — max 2 Mo" />
                    <div wire:loading wire:target="logo" class="text-xs text-base-content/50 mt-1">Chargement…</div>
                </div>
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

        {{-- Users & roles --}}
        <x-card title="Utilisateurs & rôles" shadow class="border-0">
            <p class="text-sm text-base-content/60 mb-4">Créez les comptes initiaux de l'école — au moins un administrateur.</p>

            @error('users')<p class="text-error text-sm mb-3">{{ $message }}</p>@enderror

            @foreach($users as $i => $u)
            <div wire:key="user-row-{{ $i }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start mb-3 p-3 rounded-xl bg-base-200/50">
                <x-input wire:model="users.{{ $i }}.name" label="Nom complet" placeholder="Prénom Nom" class="md:col-span-3" />
                <x-input wire:model="users.{{ $i }}.email" label="Email" type="email" placeholder="user@ecole.dj" class="md:col-span-3" />
                <x-input wire:model="users.{{ $i }}.password" label="Mot de passe" type="password" placeholder="min. 6 caractères" class="md:col-span-3" />
                <x-select wire:model="users.{{ $i }}.role" label="Rôle" :options="$roleOptions" class="md:col-span-2" />
                <div class="md:col-span-1 flex items-end h-full pt-2 md:pt-8">
                    @if(count($users) > 1)
                    <x-button icon="o-trash" wire:click="removeUser({{ $i }})" class="btn-ghost btn-sm text-error" title="Retirer" />
                    @endif
                </div>
            </div>
            @endforeach

            <x-button label="Ajouter un utilisateur" icon="o-plus" wire:click="addUser" class="btn-outline btn-sm mt-2" />
        </x-card>

        {{-- Default academic structure --}}
        <x-card title="Structure par défaut" shadow class="border-0">
            <x-checkbox wire:model="provision_defaults"
                        label="Créer automatiquement la structure académique"
                        hint="Année scolaire courante, cycles (Maternelle → Lycée), une classe par niveau et 2 salles (A/B) par classe — activables/désactivables ensuite pour la sélection." />
        </x-card>

        <x-slot:actions>
            <x-button label="Annuler" link="{{ route('platform.schools.index') }}" class="btn-ghost" />
            <x-button label="Créer l'école" icon="o-check" type="submit" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
