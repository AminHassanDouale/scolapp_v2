<?php
use App\Models\MessageThread;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public MessageThread $thread;
    public string $newMessage = '';
    public bool   $showAddParticipant = false;
    public int    $newParticipantId   = 0;

    public function mount(string $uuid): void
    {
        $this->thread = MessageThread::where('school_id', auth()->user()->school_id)
            ->whereHas('recipients', fn($q) => $q->where('user_id', auth()->id()))
            ->with(['messages.sender', 'recipients.user', 'createdBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Mark as read
        MessageRecipient::where('thread_id', $uuid)
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

        // Reload messages
        $this->thread->load(['messages.sender', 'recipients.user']);
        $this->success('Message envoyé.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
    }

    public function addParticipant(): void
    {
        $this->validate(['newParticipantId' => 'required|integer|min:1']);

        $existing = $this->thread->recipients()->where('user_id', $this->newParticipantId)->exists();
        if ($existing) {
            $this->error('Cet utilisateur est déjà dans la conversation.', position: 'toast-top toast-center', icon: 'o-x-circle', css: 'alert-error', timeout: 4000);
            return;
        }

        MessageRecipient::create([
            'thread_id' => $this->thread->id,
            'user_id'   => $this->newParticipantId,
            'is_read'   => false,
        ]);

        $this->thread->load(['messages.sender', 'recipients.user']);
        $this->reset(['showAddParticipant', 'newParticipantId']);
        $this->success('Participant ajouté.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
    }

    public function removeParticipant(int $userId): void
    {
        if ($userId === auth()->id()) {
            $this->error('Vous ne pouvez pas vous retirer de la conversation.', position: 'toast-top toast-center', icon: 'o-x-circle', css: 'alert-error', timeout: 4000);
            return;
        }
        $this->thread->recipients()->where('user_id', $userId)->delete();
        $this->thread->load(['messages.sender', 'recipients.user']);
        $this->success('Participant retiré.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $availableUsers = User::where('school_id', auth()->user()->school_id)
            ->where('id', '!=', auth()->id())
            ->whereNotIn('id', $this->thread->recipients->pluck('user_id'))
            ->orderBy('name')
            ->get()
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name ?? $u->email])
            ->all();

        return [
            'messages'       => $this->thread->messages,
            'participants'   => $this->thread->recipients,
            'availableUsers' => $availableUsers,
        ];
    }
};
?>

<div class="flex h-[calc(100vh-8rem)] overflow-hidden rounded-2xl shadow-lg bg-base-100">

    {{-- Main message area --}}
    <div class="flex-1 flex flex-col min-w-0">
        {{-- Thread header --}}
        <div class="p-4 border-b border-base-200 bg-base-50 flex items-center gap-3">
            <a href="{{ route('admin.messages.index') }}" wire:navigate
               class="btn btn-ghost btn-sm btn-circle">
                <x-icon name="o-arrow-left" class="w-4 h-4" />
            </a>
            <div class="flex-1 min-w-0">
                <h3 class="font-bold truncate">{{ $thread->subject ?? 'Sans objet' }}</h3>
                <p class="text-xs text-base-content/60">
                    {{ $participants->count() }} participant(s) ·
                    Créé par {{ $thread->createdBy?->name ?? 'Inconnu' }} ·
                    {{ $thread->created_at->format('d/m/Y') }}
                </p>
            </div>
            <x-button icon="o-user-plus"
                      wire:click="$set('showAddParticipant', true)"
                      class="btn-ghost btn-sm" tooltip="Ajouter un participant" />
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4"
             x-data x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })"
             x-ref="msgContainer">
            @foreach($messages as $msg)
            @php $isMe = $msg->sender_id === auth()->id(); @endphp
            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} gap-3">
                @if(!$isMe)
                <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center text-secondary-content text-xs font-bold shrink-0 mt-1">
                    {{ substr($msg->sender?->name ?? '?', 0, 1) }}
                </div>
                @endif
                <div class="max-w-md lg:max-w-lg">
                    @if(!$isMe)
                    <p class="text-xs text-base-content/60 mb-1">{{ $msg->sender?->name ?? $msg->sender?->email }}</p>
                    @endif
                    <div class="px-4 py-3 rounded-2xl {{ $isMe ? 'bg-primary text-primary-content rounded-tr-none' : 'bg-base-200 rounded-tl-none' }}">
                        <p class="text-sm whitespace-pre-wrap">{{ $msg->body }}</p>
                    </div>
                    <p class="text-xs text-base-content/40 mt-1 {{ $isMe ? 'text-right' : '' }}">
                        {{ $msg->created_at->format('d/m H:i') }}
                    </p>
                </div>
                @if($isMe)
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-primary-content text-xs font-bold shrink-0 mt-1">
                    {{ substr(auth()->user()->name ?? '?', 0, 1) }}
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Compose area --}}
        <div class="p-4 border-t border-base-200">
            <div class="flex gap-2">
                <textarea
                    wire:model="newMessage"
                    wire:keydown.ctrl.enter="sendMessage"
                    rows="3"
                    placeholder="Écrire un message... (Ctrl+Entrée pour envoyer)"
                    class="textarea textarea-bordered flex-1 resize-none text-sm"
                ></textarea>
                <x-button icon="o-paper-airplane" wire:click="sendMessage"
                          class="btn-primary self-end" spinner tooltip="Envoyer (Ctrl+Entrée)" />
            </div>
        </div>
    </div>

    {{-- Participants sidebar --}}
    <div class="w-64 border-l border-base-200 flex flex-col shrink-0">
        <div class="p-4 border-b border-base-200">
            <h4 class="font-bold text-sm">Participants ({{ $participants->count() }})</h4>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-2">
            @foreach($participants as $recipient)
            <div class="flex items-center gap-2 p-2 rounded-xl hover:bg-base-200 group">
                <div class="w-8 h-8 rounded-full bg-secondary/20 flex items-center justify-center text-secondary font-bold text-xs shrink-0">
                    {{ substr($recipient->user?->name ?? '?', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $recipient->user?->name ?? $recipient->user?->email }}</p>
                    @if($recipient->is_read)
                    <p class="text-xs text-base-content/40">Lu</p>
                    @else
                    <p class="text-xs text-warning">Non lu</p>
                    @endif
                </div>
                @if($recipient->user_id !== auth()->id() && $thread->created_by === auth()->id())
                <button wire:click="removeParticipant({{ $recipient->user_id }})"
                        class="btn btn-ghost btn-xs text-error opacity-0 group-hover:opacity-100 transition-opacity">
                    <x-icon name="o-x-mark" class="w-3 h-3" />
                </button>
                @endif
            </div>
            @endforeach
        </div>
        <div class="p-3 border-t border-base-200">
            <x-button label="Ajouter" icon="o-user-plus"
                      wire:click="$set('showAddParticipant', true)"
                      class="btn-outline btn-sm w-full" />
        </div>
    </div>

    {{-- Add participant modal --}}
    <x-modal wire:model="showAddParticipant" title="Ajouter un participant" separator>
        <x-form wire:submit="addParticipant" class="space-y-4">
            <x-select
                label="Utilisateur *"
                wire:model="newParticipantId"
                :options="$availableUsers"
                option-value="id"
                option-label="name"
                placeholder="Choisir un utilisateur..."
                placeholder-value="0"
                required
            />
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showAddParticipant = false" class="btn-ghost" />
                <x-button label="Ajouter" type="submit" icon="o-user-plus" class="btn-primary" spinner />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
