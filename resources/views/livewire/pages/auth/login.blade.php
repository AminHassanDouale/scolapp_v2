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

<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title text-2xl font-bold text-center justify-center mb-4">
            {{ __('auth.sign_in') }}
        </h2>

        <x-form wire:submit="login">
            <x-input
                :label="__('auth.email')"
                wire:model="email"
                type="email"
                icon="o-envelope"
                autofocus
                autocomplete="email"
            />
            <x-input
                :label="__('auth.password')"
                wire:model="password"
                type="password"
                icon="o-lock-closed"
                autocomplete="current-password"
            />
            <x-checkbox :label="__('auth.remember_me')" wire:model="remember" />
            <x-slot:actions>
                <x-button :label="__('auth.sign_in')" type="submit" icon="o-arrow-right-end-on-rectangle" class="btn-primary w-full" wire:loading.attr="disabled" spinner="login" />
            </x-slot:actions>
        </x-form>
    </div>
</div>
