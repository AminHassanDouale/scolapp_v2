<?php
use App\Models\DeviceToken;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search         = '';
    public string $platformFilter = '';
    public string $sortBy         = 'last_used_at';
    public string $sortDir        = 'desc';

    public function updatingSearch(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->sortDir = $this->sortBy === $col && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy  = $col;
    }

    public function revokeToken(int $id): void
    {
        $token = DeviceToken::findOrFail($id);
        abort_unless($token->school_id === auth()->user()->school_id, 403);
        $token->update(['is_active' => false]);
        $this->success('Jeton revoque.', position: 'toast-top toast-end', icon: 'o-x-circle', timeout: 3000);
    }

    public function deleteToken(int $id): void
    {
        $token = DeviceToken::findOrFail($id);
        abort_unless($token->school_id === auth()->user()->school_id, 403);
        $token->delete();
        $this->success('Jeton supprime.', position: 'toast-top toast-end', icon: 'o-trash', timeout: 3000);
    }

    public function revokeAll(): void
    {
        DeviceToken::where('school_id', auth()->user()->school_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        $this->success('Tous les jetons actifs ont ete revoques.', position: 'toast-top toast-end', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $tokens = DeviceToken::where('school_id', $schoolId)
            ->with('user')
            ->when($this->search, fn($q) =>
                $q->whereHas('user', fn($u) =>
                    $u->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name',  'like', "%{$this->search}%")
                      ->orWhere('email',      'like', "%{$this->search}%")
                )->orWhere('device_name', 'like', "%{$this->search}%")
            )
            ->when($this->platformFilter, fn($q) => $q->where('platform', $this->platformFilter))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(25);

        $base    = DeviceToken::where('school_id', $schoolId);
        $total   = (clone $base)->count();
        $active  = (clone $base)->where('is_active', true)->count();
        $android = (clone $base)->where('platform', 'android')->count();
        $ios     = (clone $base)->where('platform', 'ios')->count();
        $web     = (clone $base)->where('platform', 'web')->count();

        return [
            'tokens' => $tokens,
            'stats'  => compact('total', 'active', 'android', 'ios', 'web'),
        ];
    }
};
?>