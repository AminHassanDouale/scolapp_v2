<?php
use App\Services\BillingApiService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $activeTab = 'connexion';

    // ── Connection ─────────────────────────────────────────────────────────────
    public bool   $testing   = false;
    public ?bool  $reachable = null;
    public array  $health    = [];
    public string $testMsg   = '';

    // ── Transactions ───────────────────────────────────────────────────────────
    public string $lookupOrderId = '';
    public array  $txDetail      = [];
    public array  $txNotify      = [];
    public bool   $loadingT      = false;

    public function mount(): void {}

    // ════════════════════════════════════════════════════════════════════════
    // CONNECTION
    // ════════════════════════════════════════════════════════════════════════

    public function testConnection(): void
    {
        $this->testing = true;
        $this->health  = [];
        $this->testMsg = '';
        try {
            $this->health   = app(BillingApiService::class)->healthCheck();
            $this->reachable = true;
            $this->testMsg   = 'API accessible.';
            $this->success($this->testMsg, position: 'toast-top toast-end', timeout: 3000);
        } catch (\Throwable $e) {
            $this->reachable = false;
            $this->testMsg   = 'Erreur : ' . $e->getMessage();
            $this->error($this->testMsg, position: 'toast-top toast-end', timeout: 6000);
        } finally {
            $this->testing = false;
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // TRANSACTIONS
    // ════════════════════════════════════════════════════════════════════════

    public function lookupTransaction(): void
    {
        if (! $this->lookupOrderId) return;
        $this->loadingT = true;
        $this->txDetail = [];
        $this->txNotify = [];
        try {
            $svc            = app(BillingApiService::class);
            $this->txDetail = $svc->queryPayment($this->lookupOrderId);
            $this->txNotify = $svc->getNotification($this->lookupOrderId);
        } catch (\Throwable $e) {
            $this->error('Erreur : ' . $e->getMessage(), position: 'toast-top toast-end');
        } finally {
            $this->loadingT = false;
        }
    }

    public function with(): array
    {
        return [
            'cfgUrl'       => config('billing.api_url', '—'),
            'cfgNotifyUrl' => config('billing.notify_url', '—'),
        ];
    }
};
?>

<div>
    <x-header title="API Paiement D-Money" subtitle="Passerelle api.scolapp.com — aucune authentification requise" separator>
        <x-slot:actions>
            <x-button label="Transactions locales" icon="o-banknotes"
                      link="{{ route('admin.billing.index') }}" class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Tabs --}}
    <x-tabs wire:model="activeTab" class="mb-6">
        <x-tab id="connexion"    label="Connexion"    icon="o-signal" />
        <x-tab id="transactions" label="Transactions" icon="o-magnifying-glass" />
    </x-tabs>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- CONNEXION --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'connexion')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <x-card title="Configuration .env" icon="o-cog-6-tooth">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-center py-2 border-b border-base-200">
                    <span class="text-base-content/60">BILLING_API_URL</span>
                    <code class="font-mono text-xs">{{ $cfgUrl }}</code>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-base-200">
                    <span class="text-base-content/60">BILLING_NOTIFY_URL</span>
                    <code class="font-mono text-xs break-all">{{ $cfgNotifyUrl }}</code>
                </div>
                <div class="flex justify-between items-center py-2">
                    <span class="text-base-content/60">Webhook D-Money → ScolApp</span>
                    <code class="font-mono text-xs break-all">{{ url('/webhooks/billing') }}</code>
                </div>
            </div>
            <div class="mt-3 p-3 rounded-xl bg-info/10 border border-info/20 text-xs text-info">
                <strong>Aucune authentification requise.</strong> Simplement HTTPS POST/GET.
            </div>
        </x-card>

        <x-card title="Test de connexion" icon="o-play">
            @if($reachable !== null)
            <div class="flex items-center gap-2 mb-4">
                <x-icon :name="$reachable ? 'o-check-circle' : 'o-x-circle'"
                        class="w-5 h-5 {{ $reachable ? 'text-success' : 'text-error' }}" />
                <span class="text-sm {{ $reachable ? 'text-success' : 'text-error' }}">
                    API {{ $reachable ? 'accessible' : 'inaccessible' }}
                </span>
            </div>
            @if($testMsg)
            <p class="text-xs text-base-content/60 mb-3 italic">{{ $testMsg }}</p>
            @endif
            @endif

            @if($health)
            <div class="p-3 rounded-xl bg-base-200 text-xs mb-4">
                <p class="font-semibold mb-1 text-base-content/70">Health Check</p>
                @foreach($health as $k => $v)
                    @if(is_string($v) || is_numeric($v))
                    <p><span class="text-base-content/50">{{ $k }}</span>: <span class="font-medium">{{ $v }}</span></p>
                    @endif
                @endforeach
            </div>
            @endif

            <x-button label="Tester la connexion" icon="o-play"
                      wire:click="testConnection" :loading="$testing"
                      class="btn-primary btn-sm" />
        </x-card>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TRANSACTIONS --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'transactions')
    <x-card title="Rechercher un paiement D-Money" icon="o-magnifying-glass">
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <x-input label="Order ID (merch_order_id)" wire:model="lookupOrderId"
                         placeholder="SCL000001XXXX" clearable />
            </div>
            <x-button label="Vérifier" icon="o-magnifying-glass"
                      wire:click="lookupTransaction" :loading="$loadingT"
                      class="btn-primary btn-sm mb-0.5" />
        </div>

        @if($txDetail)
        <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Query result --}}
            <div class="p-4 bg-base-200 rounded-xl text-sm space-y-2">
                <p class="font-semibold text-base-content/70 text-xs uppercase tracking-wider mb-2">Statut paiement</p>
                @foreach($txDetail as $k => $v)
                @if(is_string($v) || is_numeric($v))
                <div class="flex justify-between gap-2">
                    <span class="text-base-content/50">{{ $k }}</span>
                    @if($k === 'trade_status')
                    @php $color = match(strtoupper($v)) { 'SUCCESS' => 'badge-success', 'PENDING' => 'badge-warning', default => 'badge-error' }; @endphp
                    <x-badge :value="$v" class="{{ $color }} badge-sm" />
                    @else
                    <span class="font-medium font-mono text-xs">{{ $v }}</span>
                    @endif
                </div>
                @endif
                @endforeach
            </div>

            {{-- Notifications --}}
            @if($txNotify)
            <div class="p-4 bg-base-200 rounded-xl text-sm">
                <p class="font-semibold text-base-content/70 text-xs uppercase tracking-wider mb-2">
                    Journal notifications
                    @if(isset($txNotify['count']))
                    <x-badge value="{{ $txNotify['count'] }}" class="badge-neutral badge-xs ml-1" />
                    @endif
                </p>
                @if(isset($txNotify['latest_status']))
                <p class="text-xs mb-2">
                    Dernier statut :
                    @php $c2 = match(strtoupper($txNotify['latest_status'] ?? '')) { 'SUCCESS' => 'badge-success', 'PENDING' => 'badge-warning', default => 'badge-error' }; @endphp
                    <x-badge :value="$txNotify['latest_status']" class="{{ $c2 }} badge-sm" />
                </p>
                @endif
                @if(!empty($txNotify['notifications']) && is_array($txNotify['notifications']))
                <div class="space-y-1 max-h-48 overflow-y-auto">
                    @foreach(array_slice(array_reverse($txNotify['notifications']), 0, 5) as $notif)
                    <div class="text-xs p-2 rounded-lg bg-base-100 font-mono">
                        {{ is_array($notif) ? json_encode($notif) : $notif }}
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        </div>
        @endif
    </x-card>
    @endif

</div>
