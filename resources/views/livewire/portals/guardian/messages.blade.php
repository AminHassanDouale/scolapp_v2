<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\MessageThread;

new #[Layout('layouts.guardian')] class extends Component {
    public function with(): array
    {
        $threads = MessageThread::whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['lastMessage', 'participants.user'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return compact('threads');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.messages') }}" subtitle="{{ __('navigation.my_messages') }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <x-card shadow class="border-0 p-0 overflow-hidden">
        @forelse($threads as $thread)
        <a href="{{ route('guardian.messages.thread', $thread->uuid) }}" wire:navigate
           class="flex items-center gap-4 p-4 border-b border-base-100 hover:bg-base-50 transition-colors">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                <x-icon name="o-envelope" class="w-5 h-5 text-emerald-600" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm truncate">{{ $thread->subject }}</p>
                <p class="text-xs text-base-content/50 truncate">{{ $thread->lastMessage?->body }}</p>
            </div>
            <div class="text-xs text-base-content/40 shrink-0">{{ $thread->updated_at?->diffForHumans() }}</div>
        </a>
        @empty
        <div class="text-center py-16 text-base-content/40">
            <x-icon name="o-envelope-open" class="w-12 h-12 mx-auto mb-3" />
            <p class="font-medium">Aucun message</p>
        </div>
        @endforelse
        @if($threads->hasPages())<div class="p-4">{{ $threads->links() }}</div>@endif
    </x-card>
</div>
