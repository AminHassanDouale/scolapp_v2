<?php
use App\Services\BillingApiService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public bool   $testing      = false;
    public ?bool  $reachable    = null;
    public ?bool  $authOk       = null;
    public string $testMessage  = '';

    public function testConnection(): void
    {
        $this->testing     = true;
        $this->reachable   = null;
        $this->authOk      = null;
        $this->testMessage = '';

        try {
            $svc = app(BillingApiService::class);

            $this->reachable = $svc->isReachable();

            if ($this->reachable) {
                $svc->forgetToken();
                $this->authOk = $svc->testAuth();
                $this->testMessage = $this->authOk
                    ? 'Connexion établie et authentification réussie.'
                    : 'API accessible mais authentification échouée. Vérifiez les identifiants.';
            } else {
                $this->testMessage = 'API inaccessible. Vérifiez l\'URL et votre réseau.';
            }

            if ($this->authOk) {
                $this->success($this->testMessage, position: 'toast-top toast-end', timeout: 4000);
            } else {
                $this->warning($this->testMessage, position: 'toast-top toast-end', timeout: 5000);
            }
        } catch (\Throwable $e) {
            $this->testMessage = 'Erreur : ' . $e->getMessage();
            $this->error($this->testMessage, position: 'toast-top toast-end', timeout: 6000);
        } finally {
            $this->testing = false;
        }
    }

    public function clearTokenCache(): void
    {
        app(BillingApiService::class)->forgetToken();
        $this->success('Cache du token JWT effacé.', position: 'toast-top toast-end', timeout: 3000);
    }

    public function with(): array
    {
        $apiUrl    = config('billing.api_url', '—');
        $apiEmail  = config('billing.api_email')  ? '✔ défini' : '✘ non défini';
        $apiPass   = config('billing.api_password') ? '✔ défini' : '✘ non défini';
        $whSecret  = config('billing.webhook_secret') ? '✔ défini' : '✘ non défini';
        $planId    = config('billing.dmoney_plan_id', '—');
        $successUrl = config('billing.success_url', '—');
        $cancelUrl  = config('billing.cancel_url', '—');

        return compact('apiUrl', 'apiEmail', 'apiPass', 'whSecret', 'planId', 'successUrl', 'cancelUrl');
    }
};
?>

<div>
    <x-header title="Paramètres API Facturation (D-Money)" subtitle="Configuration de la passerelle de paiement en ligne" separator>
        <x-slot:actions>
            <x-button label="Transactions" icon="o-banknotes"
                      link="{{ route('admin.billing.index') }}" class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Config overview --}}
        <x-card title="Configuration .env" icon="o-cog-6-tooth">
            <x-list-item title="URL de l'API" :value="$apiUrl" icon="o-globe-alt" no-separator />
            <x-list-item title="Email API" :value="$apiEmail" icon="o-envelope" no-separator />
            <x-list-item title="Mot de passe API" :value="$apiPass" icon="o-lock-closed" no-separator />
            <x-list-item title="Secret Webhook" :value="$whSecret" icon="o-shield-check" no-separator />
            <x-list-item title="Plan ID D-Money" :value="(string)$planId" icon="o-tag" no-separator />
            <x-list-item title="URL succès" :value="$successUrl" icon="o-check-circle" no-separator />
            <x-list-item title="URL annulation" :value="$cancelUrl" icon="o-x-circle" no-separator />

            <x-slot:footer>
                <div class="text-xs text-base-content/50 p-3">
                    Ces valeurs sont lues depuis le fichier <code>.env</code>. Modifiez-les directement sur le serveur.
                </div>
            </x-slot:footer>
        </x-card>

        {{-- Test connection --}}
        <x-card title="Test de connexion" icon="o-signal">
            <p class="text-sm text-base-content/70 mb-4">
                Vérifiez que l'API de facturation est accessible et que les identifiants sont corrects.
            </p>

            {{-- Status indicators --}}
            @if($reachable !== null)
            <div class="space-y-3 mb-4">
                <div class="flex items-center gap-2">
                    @if($reachable)
                        <x-icon name="o-check-circle" class="w-5 h-5 text-success" />
                        <span class="text-sm text-success">API accessible</span>
                    @else
                        <x-icon name="o-x-circle" class="w-5 h-5 text-error" />
                        <span class="text-sm text-error">API inaccessible</span>
                    @endif
                </div>

                @if($authOk !== null)
                <div class="flex items-center gap-2">
                    @if($authOk)
                        <x-icon name="o-check-circle" class="w-5 h-5 text-success" />
                        <span class="text-sm text-success">Authentification réussie</span>
                    @else
                        <x-icon name="o-x-circle" class="w-5 h-5 text-error" />
                        <span class="text-sm text-error">Authentification échouée</span>
                    @endif
                </div>
                @endif

                @if($testMessage)
                <x-alert :type="$authOk ? 'success' : 'warning'" :title="$testMessage" class="text-sm" />
                @endif
            </div>
            @endif

            <div class="flex gap-2 flex-wrap">
                <x-button label="Tester la connexion" icon="o-play"
                          wire:click="testConnection" :loading="$testing"
                          class="btn-primary btn-sm" />

                <x-button label="Effacer le cache token" icon="o-trash"
                          wire:click="clearTokenCache"
                          class="btn-ghost btn-sm text-warning" />
            </div>

            <x-slot:footer>
                <div class="text-xs text-base-content/50 p-3">
                    Le token JWT est mis en cache pendant 50 min pour éviter des appels inutiles à l'API.
                </div>
            </x-slot:footer>
        </x-card>

        {{-- Webhook info --}}
        <x-card title="Webhook D-Money" icon="o-arrow-path" class="lg:col-span-2">
            <p class="text-sm text-base-content/70 mb-3">
                Configurez cette URL dans votre tableau de bord D-Money / API facturation pour recevoir les confirmations de paiement automatiquement.
            </p>
            <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200">
                <x-icon name="o-link" class="w-5 h-5 text-primary shrink-0" />
                <code class="text-sm font-mono flex-1 break-all">{{ url('/webhooks/billing') }}</code>
                <x-button icon="o-clipboard-document" class="btn-ghost btn-xs"
                          x-on:click="navigator.clipboard.writeText('{{ url('/webhooks/billing') }}'); $dispatch('toast', {message: 'Copié !'})"
                          tooltip="Copier" />
            </div>
            <div class="mt-3 text-xs text-base-content/50">
                Méthode : <strong>POST</strong> · Signature : <strong>HMAC-SHA256</strong> (header <code>X-Billing-Signature</code>) ·
                La route est exclue de la vérification CSRF.
            </div>
        </x-card>

    </div>
</div>
