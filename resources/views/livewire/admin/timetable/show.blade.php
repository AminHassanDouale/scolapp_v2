<?php

use App\Models\TimetableTemplate;
use App\Models\TimetableEntry;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Room;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Illuminate\Support\Carbon;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    // Route param
    public TimetableTemplate $template;

    // Settings
    public int    $sessionDuration = 90;   // minutes
    public string $dayStart        = '07:30';
    public string $dayEnd          = '18:00';
    public string $viewMode        = 'class'; // class | teacher

    // Entry modal
    public bool   $showEntryModal  = false;
    public ?int   $editingEntryId  = null;

    // Entry form fields
    public int    $entry_day        = 0;
    public string $entry_start      = '';
    public string $entry_end        = '';
    public int    $entry_teacher_id = 0;
    public int    $entry_subject_id = 0;
    public int    $entry_room_id    = 0;

    // Settings drawer
    public bool $showSettings = false;

    public function mount(string $uuid): void
    {
        $this->template = TimetableTemplate::where('uuid', $uuid)
            ->where('school_id', auth()->user()->school_id)
            ->with(['schoolClass.grade', 'academicYear', 'entries.teacher', 'entries.subject', 'entries.roomModel'])
            ->firstOrFail();
    }

    // ── Slot helpers ───────────────────────────────────────────────────────────

    public function getTimeSlots(): array
    {
        $generated = [];
        $cursor    = Carbon::createFromFormat('H:i', $this->dayStart);
        $end       = Carbon::createFromFormat('H:i', $this->dayEnd);

        while ($cursor->lt($end)) {
            $generated[] = $cursor->format('H:i');
            $cursor->addMinutes($this->sessionDuration);
        }

        $entryTimes = $this->template->entries
            ->map(fn($e) => substr($e->start_time, 0, 5))
            ->filter(fn($t) => $t >= $this->dayStart && $t <= $this->dayEnd)
            ->toArray();

        $merged = array_unique(array_merge($generated, $entryTimes));
        sort($merged);
        return $merged;
    }

    public function computeEndTime(string $start): string
    {
        return Carbon::createFromFormat('H:i', $start)->addMinutes($this->sessionDuration)->format('H:i');
    }

    public function updatedEntryStart(string $value): void
    {
        try {
            $this->entry_end = Carbon::createFromFormat('H:i', $value)->addMinutes($this->sessionDuration)->format('H:i');
        } catch (\Throwable) {}
    }

    // ── Entry CRUD ─────────────────────────────────────────────────────────────

    public function openNewEntry(int $day, string $startTime): void
    {
        $this->reset(['editingEntryId', 'entry_teacher_id', 'entry_subject_id', 'entry_room_id']);
        $this->entry_day   = $day;
        $this->entry_start = $startTime;
        $this->entry_end   = $this->computeEndTime($startTime);
        $this->showEntryModal = true;
    }

    public function openEditEntry(int $entryId): void
    {
        $entry = TimetableEntry::findOrFail($entryId);
        $this->editingEntryId   = $entry->id;
        $this->entry_day        = $entry->day_of_week;
        $this->entry_start      = substr($entry->start_time, 0, 5);
        $this->entry_end        = substr($entry->end_time, 0, 5);
        $this->entry_teacher_id = $entry->teacher_id ?? 0;
        $this->entry_subject_id = $entry->subject_id ?? 0;
        $this->entry_room_id    = $entry->room_id ?? 0;
        $this->showEntryModal   = true;
    }

    public function saveEntry(): void
    {
        $this->validate([
            'entry_day'        => 'required|integer|between:0,4',
            'entry_start'      => 'required|date_format:H:i',
            'entry_end'        => 'required|date_format:H:i|after:entry_start',
            'entry_subject_id' => ['required', 'integer', 'min:1', 'exists:subjects,id'],
            // room is optional (0 = none); only validate existence when one is selected
            'entry_room_id'    => $this->entry_room_id > 0
                ? ['required', 'integer', 'exists:rooms,id']
                : ['nullable', 'integer'],
        ], [
            'entry_subject_id.required' => 'Veuillez choisir une matière.',
            'entry_subject_id.min'      => 'Veuillez choisir une matière.',
            'entry_subject_id.exists'   => 'Cette matière est introuvable.',
        ]);

        // Room conflict check — same school, same academic year, same day, overlapping times
        if ($this->entry_room_id) {
            $conflict = TimetableEntry::where('room_id', $this->entry_room_id)
                ->where('day_of_week', $this->entry_day)
                ->where('start_time', '<', $this->entry_end   . ':00')
                ->where('end_time',   '>', $this->entry_start . ':00')
                ->whereHas('template', fn($q) =>
                    $q->where('school_id', auth()->user()->school_id)
                      ->where('academic_year_id', $this->template->academic_year_id)
                )
                ->when($this->editingEntryId, fn($q) => $q->where('id', '!=', $this->editingEntryId))
                ->with('template.schoolClass')
                ->first();

            if ($conflict) {
                $className = $conflict->template->schoolClass?->name ?? '—';
                $from = substr($conflict->start_time, 0, 5);
                $to   = substr($conflict->end_time, 0, 5);
                $this->addError('entry_room_id', "Salle déjà occupée par {$className} de {$from} à {$to}.");
                return;
            }
        }

        $data = [
            'timetable_template_id' => $this->template->id,
            'day_of_week'           => $this->entry_day,
            'start_time'            => $this->entry_start . ':00',
            'end_time'              => $this->entry_end   . ':00',
            'teacher_id'            => $this->entry_teacher_id ?: null,
            'subject_id'            => $this->entry_subject_id,
            'room_id'               => $this->entry_room_id ?: null,
        ];

        if ($this->editingEntryId) {
            TimetableEntry::findOrFail($this->editingEntryId)->update($data);
            $this->success('Créneau mis à jour.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
        } else {
            // Guard against duplicate slot (same template + day + start)
            TimetableEntry::updateOrCreate(
                [
                    'timetable_template_id' => $this->template->id,
                    'day_of_week'           => $this->entry_day,
                    'start_time'            => $this->entry_start . ':00',
                ],
                $data
            );
            $this->success('Créneau ajouté.', position: 'toast-top toast-end', icon: 'o-plus-circle', css: 'alert-success', timeout: 3000);
        }

        $this->showEntryModal = false;
        // Invalidate the in-memory relation so with() re-fetches from DB
        $this->template->unsetRelation('entries');
    }

    public function deleteEntry(int $id): void
    {
        TimetableEntry::findOrFail($id)->delete();
        $this->success('Créneau supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
        $this->showEntryModal = false;
        $this->template->unsetRelation('entries');
    }

    // ── Data ───────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        // Always reload from DB so grid reflects latest saves/deletes
        $this->template->load(['entries.teacher', 'entries.subject', 'entries.roomModel']);
        $this->template->setRelation('entries', $this->template->entries->sortBy('start_time')->values());

        $days = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
        ];

        $dayShort = [
            0 => 'Dim',
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
        ];

        $slots   = $this->getTimeSlots();
        $entries = $this->template->entries;

        $grid = [];
        foreach ($entries as $entry) {
            $day  = (int) $entry->day_of_week;
            $time = substr((string) $entry->start_time, 0, 5);
            $grid[$day][$time] = $entry;
        }

        $subjects = Subject::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();
        $teachers = Teacher::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();
        $rooms    = Room::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();

        // For modal: current subject color
        $editingSubject = $this->entry_subject_id
            ? $subjects->firstWhere('id', $this->entry_subject_id)
            : null;

        // Compute occupied room IDs for the current modal day+time range
        $occupiedRoomMap = collect();
        if ($this->showEntryModal && $this->entry_start && $this->entry_end) {
            $occupiedRoomMap = TimetableEntry::whereNotNull('room_id')
                ->where('day_of_week', $this->entry_day)
                ->where('start_time', '<', $this->entry_end   . ':00')
                ->where('end_time',   '>', $this->entry_start . ':00')
                ->whereHas('template', fn($q) =>
                    $q->where('school_id', $schoolId)
                      ->where('academic_year_id', $this->template->academic_year_id)
                )
                ->when($this->editingEntryId, fn($q) => $q->where('id', '!=', $this->editingEntryId))
                ->with('template.schoolClass')
                ->get()
                ->keyBy('room_id');
        }

        // Build room options: label occupied rooms clearly
        $roomOptions = $rooms->map(function ($room) use ($occupiedRoomMap) {
            $conflict = $occupiedRoomMap->get($room->id);
            if ($conflict) {
                $className = $conflict->template->schoolClass?->name ?? '—';
                $from = substr($conflict->start_time, 0, 5);
                $to   = substr($conflict->end_time,   0, 5);
                return ['id' => $room->id, 'name' => "🔴 {$room->name} — {$className} ({$from}–{$to})"];
            }
            $cap = $room->capacity ? " · {$room->capacity} pl." : '';
            return ['id' => $room->id, 'name' => "✅ {$room->name}{$cap}"];
        });

        return [
            'days'           => $days,
            'dayShort'       => $dayShort,
            'slots'          => $slots,
            'grid'           => $grid,
            'entries'        => $entries,
            'teachers'       => $teachers,
            'subjects'       => $subjects,
            'rooms'          => $rooms,
            'roomOptions'    => $roomOptions,
            'editingSubject' => $editingSubject,
        ];
    }
};
?>

<div>
    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    {{-- ── HEADER ─────────────────────────────────────────────────────────────── --}}
    <x-header :title="$template->name" separator progress-indicator>
        <x-slot:subtitle>
            <div class="flex items-center gap-2 flex-wrap mt-1">
                <x-badge :value="$template->schoolClass?->name ?? '—'" class="badge-primary" />
                @if($template->schoolClass?->grade)
                    <x-badge :value="$template->schoolClass->grade->name" class="badge-outline" />
                @endif
                <x-badge :value="$template->academicYear?->name ?? '—'" class="badge-ghost badge-sm" />
                <x-badge :value="$template->is_active ? 'Actif' : 'Inactif'"
                         :class="$template->is_active ? 'badge-success' : 'badge-error'" />
            </div>
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button label="Ajouter un créneau" icon="o-plus"
                      wire:click="openNewEntry(0, '{{ $dayStart }}')"
                      class="btn-primary btn-sm" />
            <x-button label="Paramètres" icon="o-cog-6-tooth"
                      wire:click="$set('showSettings', true)"
                      class="btn-ghost btn-sm" />
            <x-button label="Retour" icon="o-arrow-left"
                      :link="route('admin.timetable.index')"
                      class="btn-ghost btn-sm" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- ── TOOLBAR ─────────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <div class="join">
            <button wire:click="$set('viewMode','class')"
                    class="join-item btn btn-sm {{ $viewMode === 'class' ? 'btn-primary' : 'btn-ghost' }}">
                <x-icon name="o-table-cells" class="w-4 h-4" /> Vue classe
            </button>
            <button wire:click="$set('viewMode','teacher')"
                    class="join-item btn btn-sm {{ $viewMode === 'teacher' ? 'btn-primary' : 'btn-ghost' }}">
                <x-icon name="o-user" class="w-4 h-4" /> Vue enseignant
            </button>
        </div>

        <div class="flex items-center gap-4 text-xs text-base-content/50">
            <span class="flex items-center gap-1">
                <x-icon name="o-clock" class="w-3.5 h-3.5" /> {{ $sessionDuration }} min / séance
            </span>
            <span class="flex items-center gap-1">
                <x-icon name="o-sun" class="w-3.5 h-3.5" /> {{ $dayStart }} – {{ $dayEnd }}
            </span>
            <span class="flex items-center gap-1 font-semibold text-base-content/70">
                <x-icon name="o-squares-2x2" class="w-3.5 h-3.5" /> {{ $entries->count() }} créneaux
            </span>
        </div>
    </div>

    {{-- ── CLASS VIEW ──────────────────────────────────────────────────────────── --}}
    @if($viewMode === 'class')
    <div class="overflow-x-auto rounded-2xl border border-base-200 shadow-sm bg-base-100">
        <table class="w-full border-collapse" style="min-width: {{ count($days) * 160 + 90 }}px">
            <thead>
                <tr>
                    <th class="sticky left-0 z-20 bg-base-200/90 backdrop-blur text-center text-xs font-semibold text-base-content/50 w-20 py-3 px-2 border-r border-b border-base-200">
                        Horaire
                    </th>
                    @foreach($days as $num => $label)
                    <th class="text-center py-3 px-2 border-b border-base-200 font-semibold text-sm
                               {{ $num === now()->dayOfWeek ? 'bg-primary/5 text-primary' : 'bg-base-50 text-base-content' }}">
                        {{ $label }}
                        <div class="text-[10px] font-normal opacity-50 mt-0.5">{{ $dayShort[$num] }}</div>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($slots as $slot)
                @php
                    $slotEnd  = \Illuminate\Support\Carbon::createFromFormat('H:i', $slot)->addMinutes($sessionDuration)->format('H:i');
                    $isLunch  = $slot >= '12:00' && $slot < '13:30';
                    $filledCount = collect($days)->keys()->filter(fn($d) => isset($grid[$d][$slot]))->count();
                @endphp

                @if($isLunch)
                    <tr class="bg-amber-50/60 dark:bg-amber-900/10">
                        <td class="sticky left-0 z-10 bg-amber-50/90 backdrop-blur text-center py-2 px-2 border-r border-b border-amber-100 text-xs text-amber-600 font-medium">
                            🍽 Pause
                        </td>
                        <td colspan="{{ count($days) }}" class="text-center py-2 text-xs text-amber-500/60 border-b border-amber-100 italic">
                            Pause déjeuner
                        </td>
                    </tr>
                @else
                    <tr wire:key="slot-{{ $slot }}" class="group hover:bg-base-50/50 transition-colors">
                        {{-- Time column --}}
                        <td class="sticky left-0 z-10 bg-base-100 text-center border-r border-b border-base-200 px-2 py-2 w-20 align-middle">
                            <div class="text-xs font-bold text-base-content/70">{{ $slot }}</div>
                            <div class="text-[10px] text-base-content/40">{{ $slotEnd }}</div>
                            @if($filledCount > 0)
                            <div class="mt-1">
                                <span class="badge badge-xs badge-ghost">{{ $filledCount }}/{{ count($days) }}</span>
                            </div>
                            @endif
                        </td>

                        {{-- Day cells --}}
                        @foreach($days as $dayNum => $dayLabel)
                        @php
                            $entry   = $grid[$dayNum][$slot] ?? null;
                            $color   = $entry?->subject?->color ?? null;
                            $isToday = $dayNum === now()->dayOfWeek;
                            $cellH   = max(72, intval($sessionDuration * 0.75));
                        @endphp
                        <td class="border-b border-base-200 p-1.5 align-top {{ $isToday ? 'bg-primary/2' : '' }}"
                            style="height: {{ $cellH }}px; min-width: 150px">

                            @if($entry)
                                {{-- Filled cell --}}
                                <div wire:key="entry-{{ $entry->id }}"
                                     wire:click="openEditEntry({{ $entry->id }})"
                                     class="relative group/cell rounded-xl cursor-pointer overflow-hidden flex flex-col justify-between px-3 py-2 shadow-sm transition-all hover:shadow-md hover:scale-[1.01]"
                                     style="min-height: {{ $cellH - 12 }}px;
                                            background: {{ $color ? 'color-mix(in srgb, ' . $color . ' 15%, white)' : 'oklch(var(--b2))' }};
                                            border-left: 3px solid {{ $color ?? 'oklch(var(--p))' }};">
                                    <div class="min-w-0">
                                        <p class="font-bold text-[11px] leading-tight truncate"
                                           style="color: {{ $color ?? 'oklch(var(--p))' }}">
                                            {{ $entry->subject?->name ?? 'Matière' }}
                                        </p>
                                        @if($entry->teacher)
                                        <p class="text-[10px] text-base-content/55 mt-0.5 truncate flex items-center gap-1">
                                            <x-icon name="o-user" class="w-2.5 h-2.5 shrink-0" />
                                            {{ $entry->teacher->full_name }}
                                        </p>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between mt-1">
                                        @if($entry->display_room)
                                        <p class="text-[9px] text-base-content/40 flex items-center gap-0.5">
                                            <x-icon name="o-map-pin" class="w-2.5 h-2.5" />
                                            {{ $entry->display_room }}
                                        </p>
                                        @else
                                        <span></span>
                                        @endif
                                        <span class="text-[9px] text-base-content/30 font-mono">
                                            {{ substr($entry->start_time,0,5) }}–{{ substr($entry->end_time,0,5) }}
                                        </span>
                                    </div>
                                    {{-- Hover actions (standard group, no named variant) --}}
                                    <div class="absolute top-1 right-1 flex gap-0.5 opacity-0 group-hover/cell:opacity-100 transition-opacity">
                                        <button wire:click.stop="openEditEntry({{ $entry->id }})"
                                                class="w-5 h-5 rounded-full bg-white/80 shadow flex items-center justify-center hover:bg-primary hover:text-white transition-colors">
                                            <x-icon name="o-pencil" class="w-2.5 h-2.5" />
                                        </button>
                                        <button wire:click.stop="deleteEntry({{ $entry->id }})"
                                                wire:confirm="Supprimer ce créneau ?"
                                                class="w-5 h-5 rounded-full bg-white/80 shadow flex items-center justify-center hover:bg-error hover:text-white transition-colors">
                                            <x-icon name="o-x-mark" class="w-2.5 h-2.5" />
                                        </button>
                                    </div>
                                </div>
                            @else
                                {{-- Empty cell: always faintly visible, more on row-hover --}}
                                <div wire:click="openNewEntry({{ $dayNum }}, '{{ $slot }}')"
                                     style="min-height: {{ $cellH - 12 }}px"
                                     class="rounded-xl border-2 border-dashed border-base-200 flex flex-col items-center justify-center cursor-pointer
                                            group-hover:border-base-300 hover:border-primary/50 hover:bg-primary/5 transition-all gap-1">
                                    <x-icon name="o-plus" class="w-4 h-4 text-base-content/20 group-hover:text-base-content/40" />
                                    <span class="text-[9px] text-base-content/20 group-hover:text-base-content/40">Ajouter</span>
                                </div>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Legend --}}
    @if($subjects->where('color', '!=', null)->isNotEmpty())
    <div class="flex flex-wrap gap-2 mt-3">
        @foreach($subjects->whereNotNull('color')->take(12) as $subj)
        <span class="badge badge-sm gap-1.5 border"
              style="background: color-mix(in srgb, {{ $subj->color }} 15%, white); border-color: {{ $subj->color }}; color: {{ $subj->color }}">
            <span class="w-2 h-2 rounded-full inline-block" style="background: {{ $subj->color }}"></span>
            {{ $subj->name }}
        </span>
        @endforeach
    </div>
    @endif

    {{-- ── TEACHER VIEW ─────────────────────────────────────────────────────────── --}}
    @else
    @php $byTeacher = $entries->groupBy(fn($e) => $e->teacher_id ?? 0); @endphp
    <div class="space-y-6">
        @forelse($byTeacher as $teacherId => $teacherEntries)
        @php
            $teacher     = $teacherEntries->first()->teacher;
            $teacherName = $teacher?->full_name ?? 'Sans enseignant';
            $entryCount  = $teacherEntries->count();
        @endphp
        <x-card shadow>
            <x-slot:title>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center">
                        <x-icon name="o-user" class="w-5 h-5 text-primary" />
                    </div>
                    <div>
                        <p class="font-bold">{{ $teacherName }}</p>
                        <p class="text-xs text-base-content/50 font-normal">{{ $entryCount }} créneau(x)</p>
                    </div>
                </div>
            </x-slot:title>
            <div class="overflow-x-auto -mx-4 px-4">
                <table class="w-full border-collapse" style="min-width: {{ count($days) * 130 + 80 }}px">
                    <thead>
                        <tr>
                            <th class="text-xs font-semibold text-base-content/50 w-20 py-2 px-2 border-b text-center">Heure</th>
                            @foreach($days as $num => $label)
                            <th class="text-xs font-semibold text-center py-2 px-2 border-b
                                       {{ $num === now()->dayOfWeek ? 'text-primary' : 'text-base-content/60' }}">
                                {{ $label }}
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slots as $slot)
                        <tr class="hover:bg-base-50">
                            <td class="text-center text-xs text-base-content/50 border-b py-1.5 px-2 font-mono">{{ $slot }}</td>
                            @foreach($days as $dayNum => $dayLabel)
                            @php
                                $entry = $teacherEntries->first(fn($e) =>
                                    $e->day_of_week === $dayNum && substr($e->start_time, 0, 5) === $slot
                                );
                                $color = $entry?->subject?->color ?? null;
                            @endphp
                            <td class="border-b p-1" style="height: 52px">
                                @if($entry)
                                <div wire:click="openEditEntry({{ $entry->id }})"
                                     class="h-full rounded-lg px-2 py-1 flex flex-col justify-center cursor-pointer hover:brightness-95 transition-all"
                                     style="background: {{ $color ? 'color-mix(in srgb, ' . $color . ' 18%, white)' : 'oklch(var(--b2))' }};
                                            border-left: 3px solid {{ $color ?? 'oklch(var(--p))' }}">
                                    <p class="text-[10px] font-bold truncate" style="color: {{ $color ?? 'oklch(var(--p))' }}">
                                        {{ $entry->subject?->name }}
                                    </p>
                                    @if($entry->display_room)
                                    <p class="text-[9px] text-base-content/40">{{ $entry->display_room }}</p>
                                    @endif
                                </div>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        @empty
        <div class="text-center py-16 text-base-content/40">
            <x-icon name="o-user-group" class="w-10 h-10 mx-auto mb-2 opacity-20" />
            <p>Aucun créneau attribué à un enseignant</p>
        </div>
        @endforelse
    </div>
    @endif


    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- ENTRY MODAL                                                               --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    <x-modal wire:model="showEntryModal"
             box-class="max-w-lg w-full"
             :title="$editingEntryId ? 'Modifier le créneau' : 'Nouveau créneau'">

        {{-- Colored subject banner --}}
        @if($editingSubject?->color)
        <div class="h-1.5 -mx-6 -mt-4 mb-4 rounded-t"
             style="background: {{ $editingSubject->color }}"></div>
        @endif

        <x-form wire:submit="saveEntry" class="space-y-4">

            {{-- Day + time row --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    @php
                        $dayOptions = collect([
                            ['id' => 0, 'name' => 'Dimanche'],
                            ['id' => 1, 'name' => 'Lundi'],
                            ['id' => 2, 'name' => 'Mardi'],
                            ['id' => 3, 'name' => 'Mercredi'],
                            ['id' => 4, 'name' => 'Jeudi'],
                        ]);
                    @endphp
                    <x-select wire:model="entry_day"
                              label="Jour"
                              :options="$dayOptions"
                              option-value="id"
                              option-label="name"
                              icon="o-calendar-days" />
                </div>
                <div>
                    <label class="label text-sm font-medium pb-1 block">Début</label>
                    <input type="time" wire:model.live="entry_start"
                           class="input input-bordered w-full" step="300" />
                    @error('entry_start') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-sm font-medium pb-1 block">Fin</label>
                    <input type="time" wire:model="entry_end"
                           class="input input-bordered w-full" step="300" />
                    @error('entry_end') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Duration badge --}}
            @if($entry_start && $entry_end)
            @php
                try {
                    $diff = \Carbon\Carbon::createFromFormat('H:i', $entry_start)
                        ->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i', $entry_end));
                } catch (\Throwable) { $diff = 0; }
            @endphp
            <div class="flex items-center gap-2 -mt-2">
                <span class="badge badge-sm {{ $diff === $sessionDuration ? 'badge-success' : 'badge-ghost' }}">
                    {{ $diff }} min
                    @if($diff === $sessionDuration) · 1 séance @elseif($diff > 0 && $sessionDuration > 0 && $diff % $sessionDuration === 0) · {{ intdiv($diff, $sessionDuration) }} séances @endif
                </span>
            </div>
            @endif

            {{-- Subject --}}
            <div>
                <x-select wire:model.live="entry_subject_id"
                          label="Matière *"
                          :options="$subjects"
                          option-value="id"
                          option-label="name"
                          placeholder="Choisir une matière"
                          placeholder-value="0"
                          icon="o-book-open" />
                @error('entry_subject_id') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                @if($editingSubject?->color)
                <div class="mt-1.5 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full inline-block" style="background: {{ $editingSubject->color }}"></span>
                    <span class="text-xs text-base-content/50">{{ $editingSubject->name }}</span>
                </div>
                @endif
            </div>

            {{-- Teacher --}}
            <x-select wire:model="entry_teacher_id"
                      label="Enseignant"
                      :options="$teachers->map(fn($t) => ['id' => $t->id, 'name' => $t->full_name])"
                      option-value="id"
                      option-label="name"
                      placeholder="Non assigné"
                      placeholder-value="0"
                      icon="o-user" />

            {{-- Room --}}
            <div>
                <x-select wire:model.live="entry_room_id"
                          label="Salle"
                          :options="$roomOptions"
                          option-value="id"
                          option-label="name"
                          placeholder="Aucune salle assignée"
                          placeholder-value="0"
                          icon="o-map-pin" />
                @error('entry_room_id')
                    <p class="text-error text-xs mt-1 flex items-center gap-1">
                        <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5" />
                        {{ $message }}
                    </p>
                @enderror
                @if($rooms->isEmpty())
                    <p class="text-xs text-warning mt-1">Aucune salle configurée. <a href="#" class="underline">Ajouter des salles</a></p>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Annuler"
                          wire:click="$set('showEntryModal', false)"
                          class="btn-ghost" />
                @if($editingEntryId)
                    <x-button label="Supprimer"
                              wire:click="deleteEntry({{ $editingEntryId }})"
                              wire:confirm="Supprimer ce créneau ?"
                              wire:click.then="$set('showEntryModal', false)"
                              class="btn-error btn-outline" icon="o-trash" />
                @endif
                <x-button label="{{ $editingEntryId ? 'Mettre à jour' : 'Ajouter' }}"
                          icon="o-check" type="submit" class="btn-primary" spinner="saveEntry" />
            </x-slot:actions>
        </x-form>
    </x-modal>


    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    {{-- SETTINGS DRAWER                                                           --}}
    {{-- ══════════════════════════════════════════════════════════════════════════ --}}
    <x-drawer wire:model="showSettings" title="Paramètres de la grille"
              position="right" class="w-full lg:w-[380px]" separator>

        <div class="space-y-5 p-1">
            {{-- Session duration --}}
            <div>
                <label class="label font-semibold text-sm mb-2 block">
                    Durée d'une séance
                </label>
                <div class="flex items-center gap-3">
                    <input type="range" wire:model.live="sessionDuration"
                           min="30" max="180" step="15"
                           class="range range-primary flex-1" />
                    <div class="badge badge-primary badge-lg font-bold min-w-[3.5rem] text-center">
                        {{ $sessionDuration }}'
                    </div>
                </div>
                <div class="flex justify-between text-[10px] text-base-content/40 mt-1 px-1">
                    <span>30'</span><span>1h</span><span>1h30</span><span>2h</span><span>3h</span>
                </div>
                <div class="flex gap-2 mt-3 flex-wrap">
                    @foreach([45, 60, 90, 120] as $preset)
                    <button wire:click="$set('sessionDuration', {{ $preset }})"
                            class="btn btn-xs {{ $sessionDuration === $preset ? 'btn-primary' : 'btn-ghost border' }}">
                        {{ $preset }}'
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="divider"></div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label font-semibold text-sm mb-1 block">Début de journée</label>
                    <input type="time" wire:model.live="dayStart"
                           class="input input-bordered w-full" step="1800" />
                </div>
                <div>
                    <label class="label font-semibold text-sm mb-1 block">Fin de journée</label>
                    <input type="time" wire:model.live="dayEnd"
                           class="input input-bordered w-full" step="1800" />
                </div>
            </div>

            <div>
                <p class="text-sm font-semibold text-base-content/70 mb-2">
                    Aperçu — {{ count($this->getTimeSlots()) }} créneaux
                </p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($this->getTimeSlots() as $s)
                    <span class="badge badge-outline badge-sm font-mono">{{ $s }}</span>
                    @endforeach
                </div>
            </div>

            <div class="divider"></div>

            <div class="space-y-1 text-sm text-base-content/60">
                <p><span class="font-medium text-base-content">Classe :</span> {{ $template->schoolClass?->name }}</p>
                <p><span class="font-medium text-base-content">Année :</span> {{ $template->academicYear?->name }}</p>
                <p><span class="font-medium text-base-content">Créneaux :</span> {{ $entries->count() }}</p>
            </div>

            <a href="{{ route('admin.timetable.edit', $template->uuid) }}" wire:navigate>
                <x-button label="Modifier les informations" icon="o-pencil" class="btn-ghost btn-sm w-full" />
            </a>
        </div>
    </x-drawer>
</div>
