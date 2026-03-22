<?php
use App\Models\Announcement;
use App\Enums\AnnouncementLevel;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string $search        = '';
    public string $levelFilter   = '';
    public bool   $showArchived  = false;
    public bool   $showFilters   = false;

    public function updatingSearch(): void { $this->resetPage(); }

    public function archive(int $id): void
    {
        Announcement::findOrFail($id)->update(['expires_at' => now()]);
        $this->success('Annonce archivée.', position: 'toast-top toast-end', icon: 'o-megaphone', css: 'alert-success', timeout: 3000);
    }

    public function delete(int $id): void
    {
        Announcement::findOrFail($id)->delete();
        $this->success('Annonce supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $query = Announcement::where('school_id', $schoolId)
            ->with('createdBy')
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%")
                ->orWhere('body', 'like', "%{$this->search}%"))
            ->when($this->levelFilter, fn($q) => $q->where('level', $this->levelFilter))
            ->when(!$this->showArchived, fn($q) =>
                $q->where(fn($s) => $s->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            )
            ->orderByDesc('created_at');

        $levelCounts = Announcement::where('school_id', $schoolId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->selectRaw('level, COUNT(*) as cnt')
            ->groupBy('level')
            ->pluck('cnt', 'level');

        $activeCount = Announcement::where('school_id', $schoolId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        return [
            'announcements' => $query->paginate(12),
            'levelCounts'   => $levelCounts,
            'activeCount'   => $activeCount,
            'levels'        => collect(AnnouncementLevel::cases())->map(fn($l) => ['id' => $l->value, 'name' => $l->label()])->all(),
        ];
    }
};
?>

<div>
    <x-header title="Annonces" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Nouvelle annonce" icon="o-plus"
                      :link="route('admin.announcements.create')"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl bg-base-200 p-4">
            <p class="text-sm text-base-content/60">Actives</p>
            <p class="text-3xl font-black mt-1">{{ $activeCount }}</p>
        </div>
        @foreach(AnnouncementLevel::cases() as $level)
        @php
            $cnt   = $levelCounts[$level->value] ?? 0;
            $color = match($level) {
                AnnouncementLevel::INFO    => 'from-info to-info/70 text-info-content',
                AnnouncementLevel::WARNING => 'from-warning to-warning/70 text-warning-content',
                AnnouncementLevel::URGENT  => 'from-error to-error/70 text-error-content',
            };
        @endphp
        <button wire:click="$set('levelFilter', '{{ $levelFilter === $level->value ? '' : $level->value }}')"
                class="rounded-2xl bg-gradient-to-br {{ $color }} p-4 text-left hover:shadow-md transition-all
                       {{ $levelFilter === $level->value ? 'ring-2 ring-offset-1 ring-primary' : '' }}">
            <p class="text-sm opacity-80">{{ $level->label() }}</p>
            <p class="text-3xl font-black mt-1">{{ $cnt }}</p>
        </button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-4 flex-wrap">
        <x-input wire:model.live.debounce="search" placeholder="Rechercher..."
                 icon="o-magnifying-glass" clearable class="flex-1 min-w-48" />
        <x-checkbox label="Voir archivées" wire:model.live="showArchived" />
    </div>

    {{-- Cards grid --}}
    <div class="space-y-3">
        @forelse($announcements as $announcement)
        @php
            $isExpired = $announcement->expires_at && $announcement->expires_at->isPast();
            $borderColor = match($announcement->level) {
                AnnouncementLevel::INFO    => 'border-l-info',
                AnnouncementLevel::WARNING => 'border-l-warning',
                AnnouncementLevel::URGENT  => 'border-l-error',
                default => 'border-l-base-300',
            };
            $badgeClass = match($announcement->level) {
                AnnouncementLevel::INFO    => 'badge-info',
                AnnouncementLevel::WARNING => 'badge-warning',
                AnnouncementLevel::URGENT  => 'badge-error',
                default => 'badge-ghost',
            };
        @endphp
        <div wire:key="announcement-{{ $announcement->id }}" class="card bg-base-100 shadow-sm border-l-4 {{ $borderColor }} {{ $isExpired ? 'opacity-60' : '' }}">
            <div class="card-body py-4 px-5">
                <div class="flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <h3 class="font-bold">{{ $announcement->title }}</h3>
                            <x-badge :value="$announcement->level->label()" class="{{ $badgeClass }} badge-sm" />
                            @if($isExpired)
                            <x-badge value="Archivée" class="badge-ghost badge-xs" />
                            @endif
                        </div>
                        <p class="text-sm text-base-content/70 line-clamp-2">{{ $announcement->body }}</p>
                        <div class="flex items-center gap-4 mt-2 text-xs text-base-content/50">
                            <span>{{ $announcement->createdBy?->name ?? 'Système' }}</span>
                            <span>{{ $announcement->created_at->diffForHumans() }}</span>
                            @if($announcement->target_audience && $announcement->target_audience !== 'all')
                            <span class="badge badge-outline badge-xs">{{ $announcement->target_audience }}</span>
                            @endif
                            @if($announcement->expires_at && !$isExpired)
                            <span>Expire {{ $announcement->expires_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        @if(!$isExpired)
                        <x-button icon="o-archive-box" wire:click="archive({{ $announcement->id }})"
                                  wire:confirm="Archiver cette annonce ?"
                                  class="btn-ghost btn-xs" tooltip="Archiver" />
                        @endif
                        <x-button icon="o-trash" wire:click="delete({{ $announcement->id }})"
                                  wire:confirm="Supprimer cette annonce ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-16 text-base-content/40">
            <x-icon name="o-megaphone" class="w-16 h-16 mx-auto mb-3 opacity-20" />
            <p class="font-semibold">Aucune annonce</p>
            <p class="text-sm mt-1 mb-4">Créez votre première annonce pour informer votre communauté.</p>
            <x-button label="Créer une annonce" icon="o-plus"
                      :link="route('admin.announcements.create')" class="btn-primary" wire:navigate />
        </div>
        @endforelse
    </div>

    @if($announcements->hasPages())
    <div class="mt-4">{{ $announcements->links() }}</div>
    @endif
</div>
