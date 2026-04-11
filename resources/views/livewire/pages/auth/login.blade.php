<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.guest')] class extends Component {
    use Toast;

    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|min:6')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', __('auth.failed'));
            return;
        }

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            $this->addError('email', __('auth.blocked'));
            return;
        }

        // Check school is active (skip for super-admin)
        if (! $user->hasRole('super-admin')) {
            $school = $user->school;
            if (! $school) {
                Auth::logout();
                $this->addError('email', __('auth.no_school'));
                return;
            }
            if (! $school->is_active) {
                Auth::logout();
                $this->redirect(route('school.suspended'));
                return;
            }
            if (! $school->hasValidSubscription()) {
                Auth::logout();
                $this->redirect(route('school.expired'));
                return;
            }
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        session()->regenerate();

        $this->redirect($user->portalRoute(), navigate: true);
    }
};
?>

<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-extrabold text-base-content tracking-tight">Bon retour ! 👋</h1>
        <p class="text-base-content/50 text-sm mt-1">Connectez-vous à votre espace scolaire.</p>
    </div>

    {{-- Form card --}}
    <div class="bg-base-100 rounded-2xl shadow-lg border border-base-200 p-7">

        {{-- Error banner --}}
        @if($errors->has('email'))
        <div class="flex items-start gap-2.5 p-3 rounded-xl bg-error/10 border border-error/20 text-error text-sm mb-5">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <span>{{ $errors->first('email') }}</span>
        </div>
        @endif

        <form wire:submit.prevent="login" class="space-y-4">

            {{-- Email --}}
            <div class="space-y-1.5">
                <label class="block text-sm font-semibold text-base-content">
                    {{ __('auth.email') }}
                </label>
                <label class="input input-bordered flex items-center gap-2 @error('email') input-error @enderror">
                    <svg class="w-4 h-4 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <input wire:model="email" type="email" autocomplete="email" autofocus
                           placeholder="vous@ecole.com" class="grow" />
                </label>
                @error('email')
                <p class="text-xs text-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="space-y-1.5">
                <label class="block text-sm font-semibold text-base-content">
                    {{ __('auth.password') }}
                </label>
                <label class="input input-bordered flex items-center gap-2 @error('password') input-error @enderror">
                    <svg class="w-4 h-4 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <input wire:model="password" type="password" autocomplete="current-password"
                           placeholder="••••••••" class="grow" />
                </label>
                @error('password')
                <p class="text-xs text-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember --}}
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" wire:model="remember" id="remember_me"
                       class="checkbox checkbox-primary checkbox-sm" />
                <label for="remember_me" class="text-sm text-base-content/65 cursor-pointer select-none">
                    {{ __('auth.remember_me') }}
                </label>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="login"
                    class="btn btn-primary w-full rounded-xl font-bold h-11 min-h-11 mt-2">
                <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    {{ __('auth.sign_in') }}
                </span>
                <span wire:loading wire:target="login" class="loading loading-spinner loading-sm"></span>
            </button>

        </form>
    </div>

    {{-- Footer note --}}
    <p class="text-center text-xs text-base-content/35">
        Besoin d'aide ? Contactez votre administrateur.
    </p>

</div>
