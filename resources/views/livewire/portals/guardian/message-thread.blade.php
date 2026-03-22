<?php
use App\Models\MessageThread;
use App\Models\Message;
use App\Models\MessageRecipient;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.guardian')] class extends Component {
    use Toast;

    public MessageThread $thread;
    public string $newMessage = '';

    public function mount(string $uuid): void
    {
        $this->thread = MessageThread::where('school_id', auth()->user()->school_id)
            ->whereHas('recipients', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['messages.sender', 'recipients.user', 'creator'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        MessageRecipient::where('thread_id', $this->thread->id)
            ->where('user_id', auth()->id())
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => 'required|string|min:1|max:5000']);

        Message::create([
            'thread_id' => $this->thread->id,
            'sender_id' => auth()->id(),
            'body'      => $this->newMessage,
        ]);

        $this->thread->touch();
        $this->newMessage = '';
        $this->thread->load(['messages.sender', 'recipients.user']);
        $this->success('Message envoyé.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        return [
            'messages'     => $this->thread->messages,
            'participants' => $this->thread->recipients,
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">
    <x-header :title="$thread->subject ?? __('navigation.messages')" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.messages') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="flex flex-col lg:flex-row gap-4 h-[calc(100vh-14rem)]">

        {{-- Message area --}}
        <div class="flex-1 flex flex-col bg-base-100 rounded-2xl shadow overflow-hidden">

            {{-- Thread meta --}}
            <div class="px-4 py-3 border-b border-base-200 bg-base-50">
                <p class="text-xs text-base-content/60">
                    {{ $participants->count() }} participant(s) ·
                    {{ __('navigation.created_by') }} {{ $thread->creator?->name ?? 'Inconnu' }} ·
                    {{ $thread->created_at->format('d/m/Y') }}
                </p>
            </div>

            {{-- Messages list --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4"
                 x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                @foreach($messages as $msg)
                @php $isMe = $msg->sender_id === auth()->id(); @endphp
                <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} gap-3">
                    @if(!$isMe)
                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 text-xs font-bold shrink-0 mt-1">
                        {{ substr($msg->sender?->name ?? '?', 0, 1) }}
                    </div>
                    @endif
                    <div class="max-w-sm lg:max-w-md">
                        @if(!$isMe)
                        <p class="text-xs text-base-content/60 mb-1">{{ $msg->sender?->name ?? $msg->sender?->email }}</p>
                        @endif
                        <div class="px-4 py-3 rounded-2xl {{ $isMe ? 'bg-emerald-500 text-white rounded-tr-none' : 'bg-base-200 rounded-tl-none' }}">
                            <p class="text-sm whitespace-pre-wrap">{{ $msg->body }}</p>
                        </div>
                        <p class="text-xs text-base-content/40 mt-1 {{ $isMe ? 'text-right' : '' }}">
                            {{ $msg->created_at->format('d/m H:i') }}
                        </p>
                    </div>
                    @if($isMe)
                    <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-white text-xs font-bold shrink-0 mt-1">
                        {{ substr(auth()->user()->name ?? '?', 0, 1) }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- Compose --}}
            <div class="p-4 border-t border-base-200">
                <div class="flex gap-2">
                    <textarea
                        wire:model="newMessage"
                        wire:keydown.ctrl.enter="sendMessage"
                        rows="3"
                        placeholder="Écrire un message... (Ctrl+Entrée)"
                        class="textarea textarea-bordered flex-1 resize-none text-sm"
                    ></textarea>
                    <x-button icon="o-paper-airplane" wire:click="sendMessage"
                              class="btn-primary self-end" spinner />
                </div>
            </div>
        </div>

        {{-- Participants --}}
        <div class="w-full lg:w-56 bg-base-100 rounded-2xl shadow p-4 flex flex-col gap-3 shrink-0 lg:h-full overflow-y-auto">
            <h4 class="font-bold text-sm">Participants ({{ $participants->count() }})</h4>
            @foreach($participants as $recipient)
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-xs shrink-0">
                    {{ substr($recipient->user?->name ?? '?', 0, 1) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium truncate">{{ $recipient->user?->name ?? $recipient->user?->email }}</p>
                    <p class="text-xs {{ $recipient->is_read ? 'text-base-content/40' : 'text-warning' }}">
                        {{ $recipient->is_read ? 'Lu' : 'Non lu' }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>

    </div>
</div>
