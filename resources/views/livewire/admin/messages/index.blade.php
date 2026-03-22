<?php
use App\Models\MessageThread;
use App\Models\Message;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $search       = '';
    public bool   $showCompose  = false;
    public ?int   $activeThread = null;
    public string $newMessage   = '';
    public string $subject      = '';
    public array  $recipientIds = [];

    private function schoolId(): int
    {
        return auth()->user()->school_id;
    }

    public function selectThread(int $threadId): void
    {
        $this->activeThread = $threadId;
        $this->newMessage   = '';

        // Mark as read
        \App\Models\MessageRecipient::where('thread_id', $threadId)
            ->where('user_id', auth()->id())
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => 'required|string|min:1|max:5000']);

        Message::create([
            'thread_id' => $this->activeThread,
            'sender_id' => auth()->id(),
            'body'      => $this->newMessage,
        ]);

        $this->newMessage = '';
        $this->success('Message envoyé.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function compose(): void
    {
        $this->validate([
            'subject'      => 'required|string|max:200',
            'newMessage'   => 'required|string|min:1|max:5000',
            'recipientIds' => 'required|array|min:1',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () {
            $thread = MessageThread::create([
                'school_id'  => $this->schoolId(),
                'created_by' => auth()->id(),
                'subject'    => $this->subject,
            ]);

            Message::create([
                'thread_id' => $thread->id,
                'sender_id' => auth()->id(),
                'body'      => $this->newMessage,
            ]);

            // Add recipients (including self)
            $all = array_unique(array_merge($this->recipientIds, [auth()->id()]));
            foreach ($all as $uid) {
                \App\Models\MessageRecipient::create([
                    'thread_id' => $thread->id,
                    'user_id'   => $uid,
                    'is_read'   => $uid === auth()->id(),
                    'read_at'   => $uid === auth()->id() ? now() : null,
                ]);
            }

            $this->activeThread = $thread->id;
        });

        $this->reset(['showCompose', 'subject', 'newMessage', 'recipientIds']);
        $this->success('Message envoyé.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $threads = MessageThread::where('school_id', $this->schoolId())
            ->whereHas('recipients', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['messages' => fn($q) => $q->latest()->limit(1), 'recipients.user'])
            ->when($this->search, fn($q) => $q->where('subject', 'like', "%{$this->search}%"))
            ->orderByDesc('updated_at')
            ->get();

        $activeMessages = null;
        if ($this->activeThread) {
            $activeMessages = Message::where('thread_id', $this->activeThread)
                ->with('sender')
                ->orderBy('created_at')
                ->get();
        }

        return [
            'threads'        => $threads,
            'activeMessages' => $activeMessages,
            'activeThreadObj'=> $this->activeThread
                ? MessageThread::with('recipients.user')->find($this->activeThread)
                : null,
            'users' => User::where('school_id', $this->schoolId())
                ->where('id', '!=', auth()->id())
                ->orderBy('name')
                ->get()
                ->map(fn($u) => ['id' => $u->id, 'name' => $u->name ?? $u->email])
                ->all(),
        ];
    }
};
?>

<div class="flex h-[calc(100vh-8rem)] overflow-hidden rounded-2xl shadow-lg bg-base-100">

    {{-- Thread list --}}
    <div class="w-80 border-r border-base-200 flex flex-col flex-shrink-0">
        {{-- Header --}}
        <div class="p-4 border-b border-base-200">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-black text-lg">Messages</h2>
                <x-button icon="o-pencil-square" wire:click="$set('showCompose', true)"
                          class="btn-primary btn-sm" tooltip="Nouveau message" />
            </div>
            <x-input wire:model.live.debounce="search" placeholder="Rechercher..." icon="o-magnifying-glass" clearable class="input-sm" />
        </div>

        {{-- Thread list --}}
        <div class="overflow-y-auto flex-1">
            @forelse($threads as $thread)
            @php
                $lastMsg = $thread->messages->first();
                $isRead  = $thread->recipients->where('user_id', auth()->id())->first()?->is_read;
            @endphp
            <button
                wire:click="selectThread({{ $thread->id }})"
                class="w-full text-left p-4 border-b border-base-200 hover:bg-base-200 transition-colors
                    {{ $activeThread === $thread->id ? 'bg-primary/10 border-l-4 border-l-primary' : '' }}"
            >
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-secondary flex items-center justify-center text-secondary-content font-bold flex-shrink-0">
                        {{ substr($thread->createdBy?->name ?? '?', 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <p class="font-{{ $isRead ? 'medium' : 'black' }} text-sm truncate">
                                {{ $thread->subject ?? 'Sans objet' }}
                            </p>
                            @if(! $isRead)
                            <div class="w-2 h-2 rounded-full bg-primary flex-shrink-0 ml-2"></div>
                            @endif
                        </div>
                        <p class="text-xs text-base-content/60 truncate mt-0.5">
                            {{ $lastMsg?->body ?? '' }}
                        </p>
                        <p class="text-xs text-base-content/40 mt-1">
                            {{ $thread->updated_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            </button>
            @empty
            <div class="p-8 text-center text-base-content/40">
                <x-icon name="o-chat-bubble-left-right" class="w-12 h-12 mx-auto mb-2 opacity-30"/>
                <p class="text-sm">Aucun message</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Message area --}}
    <div class="flex-1 flex flex-col">
        @if($activeThread && $activeMessages)
            {{-- Thread header --}}
            <div class="p-4 border-b border-base-200 bg-base-50">
                <h3 class="font-bold">{{ $activeThreadObj?->subject ?? 'Sans objet' }}</h3>
                <p class="text-xs text-base-content/60">
                    {{ $activeThreadObj?->recipients->count() }} participant(s) —
                    @foreach($activeThreadObj?->recipients->take(3) ?? [] as $r)
                        {{ $r->user?->name ?? $r->user?->email }}@if(!$loop->last), @endif
                    @endforeach
                </p>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="msgContainer"
                 x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                @foreach($activeMessages as $msg)
                @php $isMe = $msg->sender_id === auth()->id(); @endphp
                <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} gap-3">
                    @if(!$isMe)
                    <div class="w-8 h-8 rounded-full bg-neutral flex items-center justify-center text-neutral-content text-xs font-bold flex-shrink-0 mt-1">
                        {{ substr($msg->sender?->name ?? '?', 0, 1) }}
                    </div>
                    @endif
                    <div class="max-w-md">
                        @if(!$isMe)
                        <p class="text-xs text-base-content/60 mb-1">{{ $msg->sender?->name }}</p>
                        @endif
                        <div class="px-4 py-3 rounded-2xl {{ $isMe ? 'bg-primary text-primary-content rounded-tr-none' : 'bg-base-200 rounded-tl-none' }}">
                            <p class="text-sm whitespace-pre-wrap">{{ $msg->body }}</p>
                        </div>
                        <p class="text-xs text-base-content/40 mt-1 {{ $isMe ? 'text-right' : '' }}">
                            {{ $msg->created_at->format('d/m H:i') }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Compose area --}}
            <div class="p-4 border-t border-base-200">
                <div class="flex gap-2">
                    <textarea
                        wire:model="newMessage"
                        wire:keydown.ctrl.enter="sendMessage"
                        rows="2"
                        placeholder="Écrire un message... (Ctrl+Entrée pour envoyer)"
                        class="textarea textarea-bordered flex-1 resize-none text-sm"
                    ></textarea>
                    <x-button icon="o-paper-airplane" wire:click="sendMessage" class="btn-primary self-end" spinner />
                </div>
            </div>

        @else
            <div class="flex-1 flex items-center justify-center flex-col gap-4 text-base-content/30">
                <x-icon name="o-chat-bubble-left-right" class="w-20 h-20 opacity-20"/>
                <div class="text-center">
                    <p class="font-bold text-lg">Sélectionnez une conversation</p>
                    <p class="text-sm mt-1">ou composez un nouveau message</p>
                </div>
                <x-button label="Nouveau message" icon="o-pencil-square"
                          wire:click="$set('showCompose', true)" class="btn-primary" />
            </div>
        @endif
    </div>

    {{-- Compose modal --}}
    <x-modal wire:model="showCompose" title="Nouveau message" separator>
        <x-form wire:submit="compose" class="space-y-4">
            <x-input label="Objet *" wire:model="subject" placeholder="Objet du message..." required />

            <x-select
                label="Destinataires *"
                wire:model="recipientIds"
                :options="$users"
                option-value="id"
                option-label="name"
                placeholder="Choisir des destinataires..."
                placeholder-value=""
                multiple
            />

            <x-textarea
                label="Message *"
                wire:model="newMessage"
                rows="5"
                placeholder="Votre message..."
                required
            />

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCompose = false" class="btn-ghost" />
                <x-button label="Envoyer" type="submit" icon="o-paper-airplane" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
