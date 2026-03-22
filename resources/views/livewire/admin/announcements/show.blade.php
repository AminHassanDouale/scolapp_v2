<?php
use App\Models\Announcement;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Announcement $announcement;

    public function mount(string $uuid): void
    {
        $this->announcement = Announcement::where('uuid', $uuid)
            ->where('school_id', auth()->user()->school_id)
            ->with('author')
            ->firstOrFail();
    }

    public function archive(): void
    {
        $this->announcement->update(['is_archived' => true]);
        $this->success('Annonce archivée.', position: 'toast-top toast-end', icon: 'o-megaphone', css: 'alert-success', timeout: 3000);
    }

    public function unarchive(): void
    {
        $this->announcement->update(['is_archived' => false]);
        $this->success('Annonce restaurée.', position: 'toast-top toast-end', icon: 'o-megaphone', css: 'alert-success', timeout: 3000);
    }

    public function delete(): void
    {
        $this->announcement->delete();
        $this->success('Annonce supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
        $this->redirectRoute('admin.announcements.index', navigate: true);
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.announcements.index') }}" wire:navigate class="hover:text-primary">Annonces</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content truncate max-w-xs">{{ $announcement->title }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            @if($announcement->is_archived)
            <x-button label="Restaurer" icon="o-arrow-uturn-left" wire:click="unarchive"
                      class="btn-outline btn-sm" />
            @else
            <x-button label="Archiver" icon="o-archive-box" wire:click="archive"
                      class="btn-outline btn-sm" />
            @endif
            <x-button label="Supprimer" icon="o-trash" wire:click="delete"
                      wire:confirm="Supprimer cette annonce définitivement ?"
                      class="btn-error btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="max-w-3xl">
        {{-- Announcement card --}}
        <x-card>
            @php
                $levelColor = match($announcement->level ?? 'info') {
                    'warning' => 'bg-warning',
                    'error'   => 'bg-error',
                    'success' => 'bg-success',
                    default   => 'bg-info',
                };
                $levelBadge = match($announcement->level ?? 'info') {
                    'warning' => 'badge-warning',
                    'error'   => 'badge-error',
                    'success' => 'badge-success',
                    default   => 'badge-info',
                };
            @endphp

            <div class="flex items-start gap-4">
                <div class="w-1.5 self-stretch rounded-full {{ $levelColor }} shrink-0"></div>
                <div class="flex-1">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <h1 class="text-2xl font-black">{{ $announcement->title }}</h1>
                            <div class="flex items-center gap-2 mt-1">
                                <x-badge :value="ucfirst($announcement->level ?? 'info')" class="badge-sm {{ $levelBadge }}" />
                                @if($announcement->is_archived)
                                <x-badge value="Archivée" class="badge-ghost badge-sm" />
                                @endif
                                @if($announcement->target_roles)
                                @foreach($announcement->target_roles as $role)
                                <x-badge :value="$role" class="badge-outline badge-xs" />
                                @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="text-right text-sm text-base-content/50 shrink-0">
                            <p>{{ $announcement->created_at->format('d/m/Y à H:i') }}</p>
                            @if($announcement->author)
                            <p class="font-medium">{{ $announcement->author->name }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="prose prose-sm max-w-none text-base-content/80 leading-relaxed">
                        {!! nl2br(e($announcement->body)) !!}
                    </div>

                    @if($announcement->expires_at)
                    <div class="mt-4 flex items-center gap-2 text-sm text-base-content/50">
                        <x-icon name="o-clock" class="w-4 h-4"/>
                        Expire le {{ $announcement->expires_at->format('d/m/Y') }}
                        @if($announcement->expires_at->isPast())
                        <x-badge value="Expirée" class="badge-error badge-xs" />
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </x-card>

        <div class="mt-4">
            <a href="{{ route('admin.announcements.index') }}" wire:navigate>
                <x-button label="Retour aux annonces" icon="o-arrow-left" class="btn-outline" />
            </a>
        </div>
    </div>
</div>
