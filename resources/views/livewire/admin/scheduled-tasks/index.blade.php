<?php
use App\Enums\ScheduledTaskType;
use App\Enums\TaskFrequency;
use App\Models\Guardian;
use App\Models\ScheduledTask;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Artisan;

new #[Layout('layouts.app')] class extends Component {

    // ── List state ────────────────────────────────────────────────────────────
    public string $search = '';

    // ── Form state ────────────────────────────────────────────────────────────
    public bool   $showDrawer  = false;
    public ?int   $editingId   = null;

    // Core fields
    public string $name          = '';
    public string $description   = '';
    public string $type          = '';
    public string $targetType    = '';
    public int    $targetId      = 0;
    public string $frequency     = 'daily';
    public string $scheduledTime = '08:00';
    public int    $dayOfWeek     = 0;
    public int    $dayOfMonth    = 1;
    public bool   $isActive      = true;

    // Meta / customisation
    public string $customSubject = '';
    public string $customBody    = '';
    public int    $daysBefore    = 7;

    // Recipients
    public array  $recipientUserIds     = [];   // school user IDs (admins pre-checked)
    public array  $recipientGuardianIds = [];   // individually-selected guardian IDs
    public string $guardianSearch       = '';   // live search input for guardians

    // ── Data ──────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $tasks = ScheduledTask::where('school_id', $schoolId)
            ->with(['targetClass', 'targetGrade'])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('is_active', 'desc')
            ->orderBy('next_run_at')
            ->get();

        // All school users (for recipient checkboxes)
        $schoolUsers = User::where('school_id', $schoolId)
            ->whereNotNull('email')
            ->orderBy('name')
            ->get();

        // Guardian search results (live)
        $guardianResults = $this->guardianSearch
            ? Guardian::where('school_id', $schoolId)
                ->whereNotNull('email')
                ->where(function ($q) {
                    $q->where('name', 'like', "%{$this->guardianSearch}%")
                      ->orWhere('email', 'like', "%{$this->guardianSearch}%");
                })
                ->whereNotIn('id', $this->recipientGuardianIds)
                ->limit(8)
                ->get()
            : collect();

        // Already-selected guardians (to show chips)
        $selectedGuardians = $this->recipientGuardianIds
            ? Guardian::whereIn('id', $this->recipientGuardianIds)->get()
            : collect();

        return [
            'tasks'             => $tasks,
            'classes'           => SchoolClass::where('school_id', $schoolId)->orderBy('name')->get(),
            'grades'            => Grade::where('school_id', $schoolId)->orderBy('name')->get(),
            'types'             => ScheduledTaskType::cases(),
            'frequencies'       => TaskFrequency::cases(),
            'schoolUsers'       => $schoolUsers,
            'guardianResults'   => $guardianResults,
            'selectedGuardians' => $selectedGuardians,
        ];
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        // Pre-select all admin users of the school
        $this->recipientUserIds = User::where('school_id', auth()->user()->school_id)
            ->whereNotNull('email')
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->toArray();
        $this->showDrawer = true;
    }

    public function openEdit(int $id): void
    {
        $task = ScheduledTask::findOrFail($id);
        $this->editingId     = $id;
        $this->name          = $task->name;
        $this->description   = $task->description ?? '';
        $this->type          = $task->type->value;
        $this->targetType    = $task->target_type;
        $this->targetId      = (int)$task->target_id;
        $this->frequency     = $task->frequency->value;
        $this->scheduledTime = $task->scheduled_time;
        $this->dayOfWeek     = (int)$task->day_of_week;
        $this->dayOfMonth    = (int)($task->day_of_month ?? 1);
        $this->isActive      = $task->is_active;
        $this->customSubject = $task->meta['custom_subject']       ?? '';
        $this->customBody    = $task->meta['custom_body']          ?? '';
        $this->daysBefore    = (int)($task->meta['days_before']    ?? 7);

        // Load saved recipients
        $this->recipientUserIds     = array_map('strval', $task->meta['recipient_user_ids']     ?? []);
        $this->recipientGuardianIds = $task->meta['recipient_guardian_ids'] ?? [];

        // If no users saved yet, pre-check all school users
        if (empty($this->recipientUserIds)) {
            $this->recipientUserIds = User::where('school_id', auth()->user()->school_id)
                ->whereNotNull('email')
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->toArray();
        }

        $this->showDrawer = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'          => 'required|string|max:120',
            'type'          => 'required|in:' . implode(',', array_column(ScheduledTaskType::cases(), 'value')),
            'targetType'    => 'required|string',
            'frequency'     => 'required|in:daily,weekly,monthly',
            'scheduledTime' => 'required|date_format:H:i',
            'dayOfWeek'     => 'integer|min:0|max:6',
            'dayOfMonth'    => 'integer|min:1|max:31',
        ]);

        $schoolId = auth()->user()->school_id;

        $meta = [];
        if ($this->customSubject) $meta['custom_subject'] = $this->customSubject;
        if ($this->customBody)    $meta['custom_body']    = $this->customBody;
        if ($this->type === ScheduledTaskType::PAYMENT_DUE_SOON->value) {
            $meta['days_before'] = $this->daysBefore;
        }

        // Save recipients
        $meta['recipient_user_ids']     = array_map('intval', $this->recipientUserIds);
        $meta['recipient_guardian_ids'] = array_map('intval', $this->recipientGuardianIds);

        $data = [
            'school_id'      => $schoolId,
            'created_by'     => auth()->id(),
            'name'           => $this->name,
            'description'    => $this->description,
            'type'           => $this->type,
            'target_type'    => $this->targetType,
            'target_id'      => $this->targetId ?: null,
            'frequency'      => $this->frequency,
            'scheduled_time' => $this->scheduledTime,
            'day_of_week'    => $this->frequency === 'weekly'  ? $this->dayOfWeek  : null,
            'day_of_month'   => $this->frequency === 'monthly' ? $this->dayOfMonth : null,
            'meta'           => $meta ?: null,
            'is_active'      => $this->isActive,
        ];

        if ($this->editingId) {
            $task = ScheduledTask::findOrFail($this->editingId);
            $task->update($data);
            $task->update(['next_run_at' => $task->computeNextRunAt()]);
            $this->success('Tâche mise à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
        } else {
            $task = ScheduledTask::create($data);
            $task->update(['next_run_at' => $task->computeNextRunAt()]);
            $this->success('Tâche planifiée créée.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
        }

        $this->showDrawer = false;
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $task = ScheduledTask::findOrFail($id);
        $task->update(['is_active' => !$task->is_active]);

        if ($task->is_active && !$task->next_run_at) {
            $task->update(['next_run_at' => $task->computeNextRunAt()]);
        }

        $this->success($task->is_active ? 'Tâche activée.' : 'Tâche mise en pause.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function runNow(int $id): void
    {
        $task = ScheduledTask::findOrFail($id);
        $task->update(['next_run_at' => now()]);
        Artisan::call('tasks:dispatch', ['--school' => $task->school_id]);
        $this->success('Tâche exécutée manuellement.', position: 'toast-top toast-end', icon: 'o-clock', css: 'alert-success', timeout: 3000);
    }

    public function delete(int $id): void
    {
        ScheduledTask::findOrFail($id)->delete();
        $this->success('Tâche supprimée.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    // ── Guardian recipient helpers ─────────────────────────────────────────────

    public function addGuardian(int $id): void
    {
        if (!in_array($id, $this->recipientGuardianIds)) {
            $this->recipientGuardianIds[] = $id;
        }
        $this->guardianSearch = '';
    }

    public function removeGuardian(int $id): void
    {
        $this->recipientGuardianIds = array_values(
            array_filter($this->recipientGuardianIds, fn($gid) => $gid !== $id)
        );
    }

    // ── Misc ─────────────────────────────────────────────────────────────────

    public function updatedType(): void
    {
        if ($this->type) {
            $this->targetType = ScheduledTaskType::from($this->type)->defaultTarget();
        }
    }

    private function resetForm(): void
    {
        $this->editingId            = null;
        $this->name                 = '';
        $this->description          = '';
        $this->type                 = '';
        $this->targetType           = '';
        $this->targetId             = 0;
        $this->frequency            = 'daily';
        $this->scheduledTime        = '08:00';
        $this->dayOfWeek            = 0;
        $this->dayOfMonth           = 1;
        $this->isActive             = true;
        $this->customSubject        = '';
        $this->customBody           = '';
        $this->daysBefore           = 7;
        $this->recipientUserIds     = [];
        $this->recipientGuardianIds = [];
        $this->guardianSearch       = '';
    }
};
?>

<div>
    <x-header title="Tâches planifiées" subtitle="Automatisez l'envoi d'emails et de notifications" separator progress-indicator>
        <x-slot:actions>
            <x-input wire:model.live.debounce="search" placeholder="Rechercher…" icon="o-magnifying-glass" class="input-sm w-52" clearable />
            <x-button label="Nouvelle tâche" icon="o-plus" wire:click="openCreate" class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Task cards --}}
    @forelse($tasks as $task)
    @php
        $typeEnum  = $task->type;
        $freqEnum  = $task->frequency;
        $colorMap  = ['warning'=>'text-warning','error'=>'text-error','info'=>'text-info','success'=>'text-success','primary'=>'text-primary','secondary'=>'text-secondary'];
        $textColor = $colorMap[$typeEnum->color()] ?? 'text-base-content';
        $bgMap     = ['warning'=>'bg-warning/10','error'=>'bg-error/10','info'=>'bg-info/10','success'=>'bg-success/10','primary'=>'bg-primary/10','secondary'=>'bg-secondary/10'];
        $bgColor   = $bgMap[$typeEnum->color()] ?? 'bg-base-200';
        $uCount    = count($task->meta['recipient_user_ids']     ?? []);
        $gCount    = count($task->meta['recipient_guardian_ids'] ?? []);
    @endphp
    <div class="card bg-base-100 shadow border border-base-200 mb-4 {{ !$task->is_active ? 'opacity-55' : '' }}">
        <div class="card-body p-5">
            <div class="flex items-start justify-between gap-4">

                {{-- Icon + Title --}}
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <div class="rounded-xl p-2.5 {{ $bgColor }} shrink-0">
                        <x-icon name="{{ $typeEnum->icon() }}" class="w-5 h-5 {{ $textColor }}" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-base truncate">{{ $task->name }}</h3>
                            <x-badge value="{{ $typeEnum->label() }}" class="badge-{{ $typeEnum->color() }} badge-sm" />
                            @if(!$task->is_active)
                                <x-badge value="En pause" class="badge-ghost badge-sm" />
                            @endif
                        </div>
                        @if($task->description)
                            <p class="text-sm text-base-content/60 mt-0.5">{{ $task->description }}</p>
                        @endif

                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-base-content/60">
                            <span class="flex items-center gap-1">
                                <x-icon name="{{ $freqEnum->icon() }}" class="w-3.5 h-3.5" />
                                {{ $task->frequencyLabel() }}
                            </span>
                            <span class="flex items-center gap-1">
                                <x-icon name="o-user-group" class="w-3.5 h-3.5" />
                                {{ $task->targetLabel() }}
                            </span>

                            {{-- Recipient summary --}}
                            @if($uCount > 0 || $gCount > 0)
                            <span class="flex items-center gap-1 text-primary">
                                <x-icon name="o-envelope" class="w-3.5 h-3.5" />
                                @if($uCount > 0){{ $uCount }} utilisateur(s)@endif
                                @if($uCount > 0 && $gCount > 0) · @endif
                                @if($gCount > 0){{ $gCount }} tuteur(s)@endif
                            </span>
                            @endif

                            @if($task->next_run_at)
                            <span class="flex items-center gap-1 {{ $task->isDue() ? 'text-error' : '' }}">
                                <x-icon name="o-clock" class="w-3.5 h-3.5" />
                                Prochain run : {{ $task->next_run_at->format('d/m/Y H:i') }}
                            </span>
                            @endif
                            @if($task->last_run_at)
                            <span class="flex items-center gap-1">
                                <x-icon name="o-check-circle" class="w-3.5 h-3.5 text-success" />
                                Dernier run : {{ $task->last_run_at->diffForHumans() }}
                                @if($task->run_count > 0)· {{ $task->run_count }}×@endif
                            </span>
                            @endif
                            @if($task->last_error)
                            <span class="flex items-center gap-1 text-error" title="{{ $task->last_error }}">
                                <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5" />
                                {{ $task->failure_count }} échec(s) — voir log
                            </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1 shrink-0">
                    <x-button icon="{{ $task->is_active ? 'o-pause' : 'o-play' }}"
                              wire:click="toggle({{ $task->id }})"
                              class="btn-ghost btn-sm"
                              tooltip="{{ $task->is_active ? 'Mettre en pause' : 'Activer' }}" />
                    <x-button icon="o-bolt"
                              wire:click="runNow({{ $task->id }})"
                              class="btn-ghost btn-sm text-warning"
                              tooltip="Exécuter maintenant"
                              wire:confirm="Exécuter cette tâche immédiatement ?" />
                    <x-button icon="o-pencil-square"
                              wire:click="openEdit({{ $task->id }})"
                              class="btn-ghost btn-sm"
                              tooltip="Modifier" />
                    <x-button icon="o-trash"
                              wire:click="delete({{ $task->id }})"
                              class="btn-ghost btn-sm text-error"
                              tooltip="Supprimer"
                              wire:confirm="Supprimer cette tâche planifiée ?" />
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center py-16 text-base-content/40">
        <x-icon name="o-clock" class="w-12 h-12 mx-auto mb-3 opacity-30" />
        <p class="font-semibold">Aucune tâche planifiée</p>
        <p class="text-sm mt-1">Créez une tâche pour automatiser vos envois d'emails.</p>
        <x-button label="Créer la première tâche" icon="o-plus" wire:click="openCreate" class="btn-primary btn-sm mt-4" />
    </div>
    @endforelse

    {{-- ════════════════════════════════════════════════════════════════════════
         DRAWER
    ════════════════════════════════════════════════════════════════════════════ --}}
    <x-drawer wire:model="showDrawer"
              :title="$editingId ? 'Modifier la tâche' : 'Nouvelle tâche planifiée'"
              subtitle="Type · Cible · Destinataires · Horaire"
              class="w-full max-w-xl"
              right>
        <div class="space-y-5 py-2">

            {{-- Name + Description --}}
            <x-input wire:model="name" label="Nom de la tâche *" placeholder="Ex : Rappel mensuel factures impayées" />
            <x-textarea wire:model="description" label="Description" placeholder="Brève description de cette tâche" rows="2" />

            <div class="divider text-xs font-bold uppercase tracking-widest text-base-content/40">Type & Cible</div>

            {{-- Task type picker --}}
            <div>
                <label class="label label-text font-semibold mb-1">Type de tâche *</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($types as $t)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="type" value="{{ $t->value }}" class="sr-only peer" />
                        <div class="border-2 rounded-xl p-3 peer-checked:border-primary peer-checked:bg-primary/5 border-base-300 hover:border-base-400 transition-all">
                            <div class="flex items-center gap-2">
                                <x-icon name="{{ $t->icon() }}" class="w-4 h-4 text-base-content/60" />
                                <span class="text-sm font-semibold">{{ $t->label() }}</span>
                            </div>
                            <p class="text-xs text-base-content/50 mt-1 leading-tight">{{ $t->description() }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('type')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Target audience --}}
            @if($type)
            <div>
                <label class="label label-text font-semibold">Audience principale *</label>
                <select wire:model.live="targetType" class="select select-bordered w-full">
                    <option value="">Choisir…</option>
                    @if(in_array($type, ['invoice_reminder','overdue_alert','payment_due_soon','custom_notification']))
                    <option value="all_guardians">Tous les tuteurs</option>
                    <option value="unpaid_guardians">Tuteurs — factures impayées</option>
                    <option value="overdue_guardians">Tuteurs — factures en retard</option>
                    <option value="class_guardians">Tuteurs d'une classe spécifique</option>
                    <option value="grade_guardians">Tuteurs d'un niveau spécifique</option>
                    @endif
                    @if(in_array($type, ['attendance_summary','financial_summary','custom_notification']))
                    <option value="school_admins">Administrateurs de l'école</option>
                    @endif
                </select>
                <p class="text-xs text-base-content/50 mt-1">L'audience principale est résolue dynamiquement à chaque exécution.</p>
            </div>

            @if($targetType === 'class_guardians')
            <x-select wire:model="targetId" label="Classe cible"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Choisir une classe" placeholder-value="0" />
            @elseif($targetType === 'grade_guardians')
            <x-select wire:model="targetId" label="Niveau cible"
                      :options="$grades" option-value="id" option-label="name"
                      placeholder="Choisir un niveau" placeholder-value="0" />
            @endif
            @endif

            @if($type === 'payment_due_soon')
            <x-input wire:model="daysBefore" type="number" min="1" max="60"
                     label="Jours avant échéance"
                     hint="Envoyer le rappel X jours avant la date d'échéance" />
            @endif

            {{-- ─── DESTINATAIRES ─────────────────────────────────────── --}}
            <div class="divider text-xs font-bold uppercase tracking-widest text-base-content/40">Destinataires supplémentaires</div>

            <div class="bg-base-200/60 rounded-2xl p-4 space-y-4">
                <p class="text-xs text-base-content/60">
                    En plus de l'audience principale, sélectionnez les utilisateurs et tuteurs qui recevront systématiquement ce rapport.
                </p>

                {{-- School users (admins, etc.) with checkboxes --}}
                <div>
                    <label class="label label-text font-semibold text-sm mb-2 flex items-center gap-2">
                        <x-icon name="o-users" class="w-4 h-4" />
                        Utilisateurs de l'école
                        <span class="badge badge-primary badge-sm">{{ count($recipientUserIds) }} sélectionné(s)</span>
                    </label>

                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                        @foreach($schoolUsers as $user)
                        <label class="flex items-center gap-3 p-2.5 rounded-xl cursor-pointer hover:bg-base-200 transition-colors {{ in_array((string)$user->id, $recipientUserIds) ? 'bg-primary/5 border border-primary/20' : 'border border-transparent' }}">
                            <input type="checkbox"
                                   wire:model.live="recipientUserIds"
                                   value="{{ $user->id }}"
                                   class="checkbox checkbox-primary checkbox-sm" />
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary shrink-0">
                                    {{ strtoupper(substr($user->name ?? '', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold truncate">{{ $user->full_name }}</p>
                                    <p class="text-xs text-base-content/50 truncate">{{ $user->email }}</p>
                                </div>
                            </div>
                            @if(auth()->id() === $user->id)
                            <span class="badge badge-ghost badge-xs">Vous</span>
                            @endif
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="divider my-1"></div>

                {{-- Guardian search & selection --}}
                <div>
                    <label class="label label-text font-semibold text-sm mb-2 flex items-center gap-2">
                        <x-icon name="o-user-circle" class="w-4 h-4" />
                        Tuteurs spécifiques
                        @if(count($recipientGuardianIds) > 0)
                        <span class="badge badge-secondary badge-sm">{{ count($recipientGuardianIds) }}</span>
                        @endif
                    </label>

                    {{-- Selected guardian chips --}}
                    @if($selectedGuardians->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        @foreach($selectedGuardians as $g)
                        <div class="flex items-center gap-1 bg-secondary/10 border border-secondary/20 text-secondary rounded-full px-3 py-1 text-xs font-semibold">
                            <span>{{ $g->name }}</span>
                            <button type="button" wire:click="removeGuardian({{ $g->id }})"
                                    class="ml-1 hover:text-error transition-colors">✕</button>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Search input --}}
                    <div class="relative">
                        <x-input wire:model.live.debounce.300ms="guardianSearch"
                                 placeholder="Rechercher un tuteur par nom ou email…"
                                 icon="o-magnifying-glass"
                                 class="input-sm w-full" />

                        {{-- Results dropdown --}}
                        @if($guardianResults->isNotEmpty())
                        <div class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-xl shadow-lg overflow-hidden">
                            @foreach($guardianResults as $g)
                            <button type="button"
                                    wire:click="addGuardian({{ $g->id }})"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-base-200 transition-colors text-left">
                                <div class="w-7 h-7 rounded-full bg-secondary/10 flex items-center justify-center text-xs font-bold text-secondary shrink-0">
                                    {{ strtoupper(substr($g->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $g->name }}</p>
                                    <p class="text-xs text-base-content/50">{{ $g->email }}</p>
                                </div>
                                <x-icon name="o-plus-circle" class="w-4 h-4 text-secondary ml-auto shrink-0" />
                            </button>
                            @endforeach
                        </div>
                        @elseif($guardianSearch && $guardianResults->isEmpty())
                        <div class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-xl shadow-lg px-4 py-3 text-sm text-base-content/50">
                            Aucun tuteur trouvé pour "{{ $guardianSearch }}"
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ─── PLANIFICATION ─────────────────────────────────────── --}}
            <div class="divider text-xs font-bold uppercase tracking-widest text-base-content/40">Planification</div>

            {{-- Frequency --}}
            <div>
                <label class="label label-text font-semibold">Fréquence *</label>
                <div class="flex gap-2">
                    @foreach($frequencies as $f)
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" wire:model.live="frequency" value="{{ $f->value }}" class="sr-only peer" />
                        <div class="border-2 rounded-xl p-2.5 text-center peer-checked:border-primary peer-checked:bg-primary/5 border-base-300 hover:border-base-400 transition-all">
                            <x-icon name="{{ $f->icon() }}" class="w-4 h-4 mx-auto mb-0.5" />
                            <p class="text-xs font-semibold">{{ $f->label() }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            <x-input wire:model="scheduledTime" type="time" label="Heure d'envoi *"
                     hint="Heure à laquelle la tâche sera exécutée" />

            @if($frequency === 'weekly')
            <div>
                <label class="label label-text font-semibold">Jour de la semaine</label>
                <div class="flex gap-1.5 flex-wrap">
                    @foreach(['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'] as $i => $d)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model="dayOfWeek" value="{{ $i }}" class="sr-only peer" />
                        <div class="w-10 h-10 flex items-center justify-center rounded-full border-2 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-content border-base-300 text-sm font-bold hover:border-base-400 transition-all">
                            {{ $d }}
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            @if($frequency === 'monthly')
            <x-input wire:model="dayOfMonth" type="number" min="1" max="28"
                     label="Jour du mois" hint="Entre 1 et 28 pour éviter les mois courts" />
            @endif

            {{-- ─── PERSONNALISATION ───────────────────────────────────── --}}
            <div class="divider text-xs font-bold uppercase tracking-widest text-base-content/40">Personnalisation</div>

            <x-input wire:model="customSubject" label="Objet de l'email (optionnel)"
                     placeholder="Laisser vide pour utiliser l'objet par défaut" />

            @if($type === 'custom_notification')
            <x-textarea wire:model="customBody" label="Contenu du message *"
                        placeholder="Rédigez ici le message à envoyer…" rows="5" />
            @endif

            <x-toggle wire:model="isActive" label="Tâche active" hint="Une tâche inactive ne s'exécutera pas." />
        </div>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showDrawer', false)" class="btn-ghost" />
            <x-button :label="$editingId ? 'Enregistrer' : 'Créer la tâche'"
                      icon="o-check" wire:click="save"
                      class="btn-primary" />
        </x-slot:actions>
    </x-drawer>
</div>
