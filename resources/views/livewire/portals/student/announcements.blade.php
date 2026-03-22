<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Announcement;
use Livewire\WithPagination;

new #[Layout('layouts.student')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        $announcements = Announcement::where('school_id', auth()->user()->school_id)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->paginate(15);

        return compact('announcements');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.announcements') }}" subtitle="Annonces de l'école" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('student.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="space-y-4">
        @forelse($announcements as $ann)
        <x-card shadow class="border-0 hover:shadow-md transition-shadow">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <x-icon name="o-megaphone" class="w-5 h-5 text-violet-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-bold text-base">{{ $ann->title }}</h3>
                        <span class="text-xs text-base-content/40 flex-shrink-0">{{ $ann->published_at?->diffForHumans() }}</span>
                    </div>
                    @if($ann->content)
                    <p class="text-sm text-base-content/70 mt-2 leading-relaxed">{{ Str::limit($ann->content, 300) }}</p>
                    @endif
                </div>
            </div>
        </x-card>
        @empty
        <x-card shadow class="border-0 text-center py-16">
            <x-icon name="o-megaphone" class="w-12 h-12 mx-auto mb-3 text-base-content/20" />
            <p class="font-medium text-base-content/40">Aucune annonce publiée</p>
        </x-card>
        @endforelse

        <div>{{ $announcements->links() }}</div>
    </div>
</div>
