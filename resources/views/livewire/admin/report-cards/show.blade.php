<?php
use App\Mail\ReportCardPublishedMail;
use App\Models\ReportCard;
use App\Models\ReportCardTemplate;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public ReportCard $reportCard;

    // Editable comment fields
    public string $general_comment = '';
    public string $teacher_comment = '';

    // Workflow
    public bool   $showReject   = false;
    public string $rejectReason = '';

    public function mount(string $uuid): void
    {
        $this->reportCard = ReportCard::where('uuid', $uuid)
            ->with([
                'enrollment.student',
                'enrollment.schoolClass.grade',
                'enrollment.academicYear',
                'subjectGrades.subject',
                'approvals.user',
            ])
            ->firstOrFail();

        abort_unless(
            $this->reportCard->enrollment?->student?->school_id === auth()->user()->school_id,
            403
        );

        $this->general_comment = $this->reportCard->general_comment ?? '';
        $this->teacher_comment = $this->reportCard->teacher_comment ?? '';
    }

    public function advanceWorkflow(): void
    {
        $user = auth()->user();
        abort_unless($this->reportCard->canAdvance($user), 403);

        $newStatus = $this->reportCard->advance($user);
        $this->reportCard->refresh()->load('approvals.user');

        // Publishing notifies the guardians (email + WhatsApp)
        if ($newStatus === \App\Enums\ReportCardStatus::PUBLISHED) {
            $this->notifyGuardiansPublished();
        }

        $this->success('Bulletin : ' . $newStatus->label() . '.', position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
    }

    public function rejectWorkflow(): void
    {
        $user = auth()->user();
        abort_unless($this->reportCard->canReject($user), 403);
        $this->validate(['rejectReason' => 'required|string|min:5'], [], ['rejectReason' => 'motif']);

        $this->reportCard->reject($user, $this->rejectReason);
        $this->reportCard->refresh()->load('approvals.user');
        $this->reset(['showReject', 'rejectReason']);

        $this->warning('Bulletin rejeté — retourné en brouillon.', position: 'toast-top toast-end', icon: 'o-x-circle', css: 'alert-warning', timeout: 3000);
    }

    private function notifyGuardiansPublished(): void
    {
        $student   = $this->reportCard->enrollment?->student;
        $guardians = $student?->guardians()
            ->wherePivot('receive_notifications', true)
            ->whereNotNull('email')
            ->get() ?? collect();

        $waText = "📄 *Bulletin disponible* — " . ($student?->school?->name ?? config('app.name')) . "\n"
            . "Le bulletin de " . ($student?->full_name ?? 'votre enfant') . " est publié.\n"
            . "Connectez-vous à votre espace parent pour le consulter.";

        foreach ($guardians as $guardian) {
            try {
                Mail::to($guardian->email)->send(new ReportCardPublishedMail($this->reportCard, $guardian));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ReportCard publish mail failed: ' . $e->getMessage());
            }
            app(\App\Services\WhatsAppService::class)->notifyModel($guardian, $waText);
        }
    }

    public function saveComments(): void
    {
        $this->reportCard->update([
            'general_comment' => $this->general_comment ?: null,
            'teacher_comment' => $this->teacher_comment ?: null,
        ]);
        $this->success('Appréciations enregistrées.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }

    public function publish(): void
    {
        $this->reportCard->update(['is_published' => true, 'published_at' => now()]);
        $this->reportCard->refresh();

        // Notify guardians with receive_notifications = true
        $student   = $this->reportCard->enrollment?->student;
        $guardians = $student?->guardians()
            ->wherePivot('receive_notifications', true)
            ->whereNotNull('email')
            ->get() ?? collect();

        $waText = "📄 *Bulletin disponible* — " . ($student?->school?->name ?? config('app.name')) . "\n"
            . "Le bulletin de " . ($student?->full_name ?? 'votre enfant') . " est publié.\n"
            . "Connectez-vous à votre espace parent pour le consulter.";

        $sent = 0;
        foreach ($guardians as $guardian) {
            Mail::to($guardian->email)->send(new ReportCardPublishedMail($this->reportCard, $guardian));
            app(\App\Services\WhatsAppService::class)->notifyModel($guardian, $waText);
            $sent++;
        }

        $msg = 'Bulletin publié.';
        if ($sent > 0) {
            $msg .= " {$sent} tuteur(s) notifié(s) par email.";
        }
        $this->success($msg, position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
    }

    public function unpublish(): void
    {
        $this->reportCard->update(['is_published' => false, 'published_at' => null]);
        $this->reportCard->refresh();
        $this->success('Publication annulée.', position: 'toast-top toast-end', icon: 'o-x-mark', css: 'alert-success', timeout: 3000);
    }

    private function mention(float|null $avg, ReportCardTemplate $tpl): array
    {
        if ($avg === null) return ['label' => '—', 'badge' => 'badge-ghost', 'color' => 'text-base-content/30'];
        if ($avg >= (float)$tpl->mention_tb_min) return ['label' => 'Très Bien',  'badge' => 'badge-success', 'color' => 'text-success'];
        if ($avg >= (float)$tpl->mention_b_min)  return ['label' => 'Bien',        'badge' => 'badge-info',    'color' => 'text-info'];
        if ($avg >= (float)$tpl->mention_ab_min) return ['label' => 'Assez Bien', 'badge' => 'badge-primary', 'color' => 'text-primary'];
        if ($avg >= (float)$tpl->mention_p_min)  return ['label' => 'Passable',   'badge' => 'badge-warning', 'color' => 'text-warning'];
        return ['label' => 'Insuffisant', 'badge' => 'badge-error', 'color' => 'text-error'];
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $tpl      = ReportCardTemplate::forSchool($schoolId);

        $avg           = $this->reportCard->effectiveAverage();
        $generalMention = $this->mention($avg, $tpl);

        $subjectMentions = $this->reportCard->subjectGrades->mapWithKeys(function ($sg) use ($tpl) {
            $sgAvg = $sg->average !== null ? (float)$sg->average : null;
            return [$sg->id => $this->mention($sgAvg, $tpl)];
        });

        $school = auth()->user()->school;

        return compact('tpl', 'generalMention', 'subjectMentions', 'school');
    }
};
?>

<div>
    {{-- ── Top bar (screen only) ─────────────────────────────────────────── --}}
    <div class="print:hidden">
        <x-header separator progress-indicator>
            <x-slot:title>
                <div class="flex items-center gap-2 text-sm text-base-content/60">
                    <a href="{{ route('admin.report-cards.index') }}" wire:navigate class="hover:text-primary">Bulletins</a>
                    <x-icon name="o-chevron-right" class="w-3 h-3"/>
                    <span class="text-base-content font-semibold">
                        {{ $reportCard->enrollment?->student?->full_name }}
                        — {{ $reportCard->period?->label() ?? $reportCard->period }}
                    </span>
                </div>
            </x-slot:title>
            <x-slot:actions>
                @php $st = $reportCard->statusEnum(); @endphp
                <x-badge :value="$st->label()" class="badge-{{ $st->color() }} badge-sm" />
                <x-button label="Imprimer" icon="o-printer"
                          onclick="window.print()"
                          class="btn-ghost btn-sm" />
                @if($reportCard->canReject(auth()->user()))
                <x-button label="Rejeter" icon="o-x-mark" wire:click="$set('showReject', true)"
                          class="btn-error btn-outline btn-sm" />
                @endif
                @if($reportCard->canAdvance(auth()->user()) && $st->next())
                <x-button label="{{ $st->advanceLabel() }}"
                          icon="{{ $st === \App\Enums\ReportCardStatus::APPROVED ? 'o-paper-airplane' : 'o-check' }}"
                          wire:click="advanceWorkflow"
                          wire:confirm="Confirmer : {{ $st->advanceLabel() }} ?"
                          class="btn-success btn-sm" spinner="advanceWorkflow" />
                @endif
            </x-slot:actions>
        </x-header>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── BULLETIN CARD (printable) ──────────────────────────────── --}}
        <div class="lg:col-span-2">

            {{-- Official bulletin document --}}
            <div id="bulletin-doc" class="bg-white rounded-2xl shadow-lg border border-base-200 overflow-hidden print:shadow-none print:rounded-none print:border-0">

                {{-- Header band --}}
                <div class="bg-linear-to-br from-primary to-primary/80 text-primary-content px-8 py-6 print:py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest opacity-70">
                                {{ $school?->name ?? 'École' }}
                            </p>
                            <h1 class="text-2xl font-black mt-1">{{ $tpl->header_title }}</h1>
                            @if($tpl->subtitle)
                            <p class="text-sm opacity-80 mt-0.5">{{ $tpl->subtitle }}</p>
                            @endif
                        </div>
                        <div class="text-right text-xs opacity-70 space-y-1 shrink-0">
                            <p>{{ $reportCard->enrollment?->academicYear?->name }}</p>
                            <p>{{ $reportCard->period?->label() }}</p>
                            @if($tpl->show_rank && $reportCard->rank)
                            <p class="text-sm font-black opacity-100">
                                Rang {{ $reportCard->rank }}/{{ $reportCard->class_size }}
                            </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Student info bar --}}
                <div class="flex items-center gap-4 px-8 py-4 bg-base-50 border-b border-base-200 print:py-3">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center font-black text-primary text-xl shrink-0 print:w-10 print:h-10">
                        {{ strtoupper(substr($reportCard->enrollment?->student?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <p class="font-black text-lg leading-tight">{{ $reportCard->enrollment?->student?->full_name }}</p>
                        <p class="text-sm text-base-content/60">
                            {{ $reportCard->enrollment?->schoolClass?->name }}
                            @if($reportCard->enrollment?->schoolClass?->grade)
                            — {{ $reportCard->enrollment?->schoolClass?->grade?->name }}
                            @endif
                        </p>
                    </div>
                    {{-- General average circle --}}
                    <div class="text-center shrink-0">
                        @php $avg = $reportCard->effectiveAverage(); @endphp
                        <div class="w-20 h-20 rounded-full border-4 flex flex-col items-center justify-center
                                    {{ $avg !== null && $avg >= 10 ? 'border-success' : ($avg !== null ? 'border-error' : 'border-base-300') }}
                                    print:w-16 print:h-16">
                            <span class="text-2xl font-black leading-none print:text-xl">
                                {{ $avg !== null ? number_format($avg, 2) : '—' }}
                            </span>
                            <span class="text-[10px] text-base-content/50">/20</span>
                        </div>
                        <p class="text-xs font-semibold mt-1 {{ $generalMention['color'] }}">
                            {{ $generalMention['label'] }}
                        </p>
                    </div>
                </div>

                {{-- Instructions (if any) --}}
                @if($tpl->instructions)
                <div class="px-8 py-3 bg-info/5 border-b border-base-200 text-xs text-base-content/60 italic print:py-2">
                    {{ $tpl->instructions }}
                </div>
                @endif

                {{-- Subject grades table --}}
                <div class="px-8 py-5 print:py-4">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/50 mb-3">
                        Notes par matière
                    </h2>

                    @if($reportCard->subjectGrades->count())
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-base-200">
                                <th class="text-left py-2 font-semibold text-base-content/70">Matière</th>
                                <th class="text-center py-2 font-semibold text-base-content/70 w-16">Coef.</th>
                                <th class="text-center py-2 font-semibold text-base-content/70 w-20">Note /20</th>
                                @if($tpl->show_class_avg)
                                <th class="text-center py-2 font-semibold text-base-content/70 w-24">Moy. classe</th>
                                @endif
                                <th class="text-left py-2 font-semibold text-base-content/70">Mention</th>
                                @if($tpl->show_teacher_comment)
                                <th class="text-left py-2 font-semibold text-base-content/70">Appréciation</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($reportCard->subjectGrades->sortBy('subject.name') as $sg)
                        @php
                            $sgAvg     = $sg->average !== null ? (float)$sg->average : null;
                            $sgMention = $subjectMentions[$sg->id] ?? ['label' => '—', 'badge' => 'badge-ghost', 'color' => 'text-base-content/30'];
                        @endphp
                        <tr class="border-b border-base-100 hover:bg-base-50 transition-colors">
                            <td class="py-2.5">
                                <div class="flex items-center gap-2">
                                    @if($sg->subject?->color)
                                    <div class="w-2.5 h-2.5 rounded-full shrink-0"
                                         style="background-color: {{ $sg->subject->color }}"></div>
                                    @endif
                                    <span class="font-semibold">{{ $sg->subject?->name }}</span>
                                </div>
                            </td>
                            <td class="py-2.5 text-center text-base-content/60">×{{ number_format($sg->coefficient, 1) }}</td>
                            <td class="py-2.5 text-center">
                                @if($sgAvg !== null)
                                <span class="font-black text-base {{ $sgAvg >= 10 ? 'text-success' : 'text-error' }}">
                                    {{ number_format($sgAvg, 2) }}
                                </span>
                                @else
                                <span class="text-base-content/30">—</span>
                                @endif
                            </td>
                            @if($tpl->show_class_avg)
                            <td class="py-2.5 text-center text-base-content/50 text-xs">
                                {{ $sg->class_avg !== null ? number_format((float)$sg->class_avg, 2) : '—' }}
                            </td>
                            @endif
                            <td class="py-2.5">
                                <span class="badge {{ $sgMention['badge'] }} badge-sm">{{ $sgMention['label'] }}</span>
                            </td>
                            @if($tpl->show_teacher_comment)
                            <td class="py-2.5 text-xs text-base-content/60 italic max-w-xs">
                                {{ $sg->comment }}
                            </td>
                            @endif
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-base-300 bg-base-50">
                                <td class="py-3 font-black text-sm">MOYENNE GÉNÉRALE</td>
                                <td></td>
                                <td class="py-3 text-center font-black text-lg {{ $avg !== null && $avg >= 10 ? 'text-success' : 'text-error' }}">
                                    {{ $avg !== null ? number_format($avg, 2) : '—' }}
                                </td>
                                @if($tpl->show_class_avg)
                                <td class="py-3 text-center text-sm text-base-content/50">
                                    {{ $reportCard->class_average !== null ? number_format((float)$reportCard->class_average, 2) : '—' }}
                                </td>
                                @endif
                                <td colspan="{{ $tpl->show_teacher_comment ? 2 : 1 }}">
                                    <span class="badge {{ $generalMention['badge'] }} badge-md font-bold">
                                        {{ $generalMention['label'] }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    @else
                    <div class="text-center py-8 text-base-content/30 text-sm">
                        Aucune note enregistrée pour cette période.
                    </div>
                    @endif
                </div>

                {{-- Comments --}}
                @if($reportCard->general_comment || $reportCard->teacher_comment)
                <div class="px-8 pb-5 space-y-3 print:pb-4">
                    @if($reportCard->general_comment)
                    <div class="rounded-xl bg-base-100 border border-base-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-1">
                            Appréciation générale
                        </p>
                        <p class="text-sm italic leading-relaxed text-base-content/70">
                            "{{ $reportCard->general_comment }}"
                        </p>
                    </div>
                    @endif
                    @if($reportCard->teacher_comment && $tpl->show_teacher_comment)
                    <div class="rounded-xl bg-base-100 border border-base-200 p-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-1">
                            Observation du professeur principal
                        </p>
                        <p class="text-sm italic leading-relaxed text-base-content/70">
                            "{{ $reportCard->teacher_comment }}"
                        </p>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Footer --}}
                <div class="px-8 py-4 border-t border-base-200 flex items-end justify-between text-xs text-base-content/50 print:py-3">
                    <div>
                        @if($tpl->footer_text)
                        <p class="italic">{{ $tpl->footer_text }}</p>
                        @endif
                        <p class="mt-1">Généré le {{ $reportCard->created_at->format('d/m/Y') }}</p>
                    </div>
                    @if($tpl->principal_name)
                    <div class="text-right">
                        <p class="font-semibold text-base-content/70">{{ $tpl->principal_title }}</p>
                        <p class="mt-4 border-t border-base-300 pt-1">{{ $tpl->principal_name }}</p>
                    </div>
                    @endif
                </div>

            </div>{{-- end bulletin-doc --}}

            {{-- Edit comments (screen only) --}}
            <div class="mt-5 print:hidden">
                <x-card title="Appréciations" separator>
                    <div class="space-y-4">
                        <div>
                            <label class="label"><span class="label-text font-semibold">Appréciation générale</span></label>
                            <x-textarea wire:model="general_comment" rows="3"
                                        placeholder="Élève sérieux, en progrès constant..." />
                        </div>
                        @if($tpl->show_teacher_comment)
                        <div>
                            <label class="label"><span class="label-text font-semibold">Observation du professeur principal</span></label>
                            <x-textarea wire:model="teacher_comment" rows="2"
                                        placeholder="Comportement, effort, remarques particulières..." />
                        </div>
                        @endif
                        <x-button label="Enregistrer les appréciations" icon="o-check"
                                  wire:click="saveComments"
                                  class="btn-primary btn-sm" spinner="saveComments" />
                    </div>
                </x-card>
            </div>

        </div>

        {{-- ── SIDEBAR (screen only) ──────────────────────────────────── --}}
        <div class="space-y-4 print:hidden">

            {{-- Student card --}}
            <x-card title="Élève">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center font-black text-primary text-2xl shrink-0">
                        {{ strtoupper(substr($reportCard->enrollment?->student?->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-bold leading-tight">{{ $reportCard->enrollment?->student?->full_name }}</p>
                        <p class="text-sm text-base-content/60">{{ $reportCard->enrollment?->schoolClass?->name }}</p>
                        @if($reportCard->enrollment?->schoolClass?->grade)
                        <p class="text-xs text-base-content/40">{{ $reportCard->enrollment?->schoolClass?->grade?->name }}</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.students.show', $reportCard->enrollment?->student?->uuid) }}" wire:navigate>
                    <x-button label="Voir le profil" icon="o-arrow-top-right-on-square"
                              class="btn-outline btn-sm w-full" />
                </a>
            </x-card>

            {{-- Info card --}}
            <x-card title="Informations">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-base-content/60">Période</span>
                        <x-badge :value="$reportCard->period?->label() ?? $reportCard->period" class="badge-outline badge-sm" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Année</span>
                        <span class="font-semibold">{{ $reportCard->enrollment?->academicYear?->name }}</span>
                    </div>
                    @if($tpl->show_rank && $reportCard->rank)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Rang</span>
                        <span class="font-bold">{{ $reportCard->rank }} / {{ $reportCard->class_size }}</span>
                    </div>
                    @endif
                    @if($tpl->show_class_avg && $reportCard->class_average !== null)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Moy. classe</span>
                        <span>{{ number_format((float)$reportCard->class_average, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between items-center">
                        <span class="text-base-content/60">Statut</span>
                        @php $st = $reportCard->statusEnum(); @endphp
                        <x-badge :value="$st->label()" class="badge-{{ $st->color() }} badge-sm" />
                    </div>
                    @if($reportCard->rejection_reason)
                    <div class="text-xs bg-error/10 text-error rounded-lg p-2">
                        <span class="font-semibold">Motif du rejet :</span> {{ $reportCard->rejection_reason }}
                    </div>
                    @endif
                    @if($reportCard->published_at)
                    <div class="flex justify-between text-xs">
                        <span class="text-base-content/50">Publié le</span>
                        <span>{{ $reportCard->published_at->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-xs">
                        <span class="text-base-content/50">Généré le</span>
                        <span>{{ $reportCard->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </x-card>

            {{-- Workflow timeline --}}
            <x-card title="Circuit de validation">
                @php $steps = [
                    ['Soumis',            $reportCard->submitted_at,          $reportCard->submitted_by],
                    ['Validé pédagogie',  $reportCard->pedagogie_approved_at, $reportCard->pedagogie_approved_by],
                    ['Validé finance',    $reportCard->finance_approved_at,   $reportCard->finance_approved_by],
                    ['Approuvé direction',$reportCard->direction_approved_at, $reportCard->direction_approved_by],
                    ['Publié',            $reportCard->published_at,          null],
                ]; @endphp
                <ol class="relative border-l border-base-200 ml-2 space-y-4">
                    @foreach($steps as [$label, $at, $by])
                    <li class="ml-4">
                        <div class="absolute w-2.5 h-2.5 rounded-full -left-[5px] mt-1.5 {{ $at ? 'bg-success' : 'bg-base-300' }}"></div>
                        <p class="text-sm font-semibold {{ $at ? '' : 'text-base-content/40' }}">{{ $label }}</p>
                        @if($at)<p class="text-xs text-base-content/50">{{ \Illuminate\Support\Carbon::parse($at)->format('d/m/Y H:i') }}</p>@endif
                    </li>
                    @endforeach
                </ol>
                @if($reportCard->approvals->isNotEmpty())
                <div class="mt-4 pt-3 border-t border-base-200 space-y-2">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">Historique</p>
                    @foreach($reportCard->approvals->take(6) as $ap)
                    <div class="text-xs flex items-start gap-2">
                        <x-icon name="{{ $ap->action === 'rejected' ? 'o-x-circle' : 'o-check-circle' }}"
                                class="w-3.5 h-3.5 mt-0.5 {{ $ap->action === 'rejected' ? 'text-error' : 'text-success' }}" />
                        <div>
                            <span class="font-medium">{{ $ap->user?->name ?? '—' }}</span>
                            <span class="text-base-content/50">· {{ $ap->action }} · {{ $ap->created_at->format('d/m H:i') }}</span>
                            @if($ap->comment)<p class="text-base-content/50 italic">{{ $ap->comment }}</p>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </x-card>

            {{-- Mention scale --}}
            <x-card title="Barème des mentions">
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="badge badge-success badge-sm">Très Bien</span>
                        <span class="text-base-content/60">≥ {{ $tpl->mention_tb_min }}/20</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="badge badge-info badge-sm">Bien</span>
                        <span class="text-base-content/60">≥ {{ $tpl->mention_b_min }}/20</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="badge badge-primary badge-sm">Assez Bien</span>
                        <span class="text-base-content/60">≥ {{ $tpl->mention_ab_min }}/20</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="badge badge-warning badge-sm">Passable</span>
                        <span class="text-base-content/60">≥ {{ $tpl->mention_p_min }}/20</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="badge badge-error badge-sm">Insuffisant</span>
                        <span class="text-base-content/60">&lt; {{ $tpl->mention_p_min }}/20</span>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-base-200">
                    <a href="{{ route('admin.report-cards.template') }}" wire:navigate
                       class="text-xs text-primary hover:underline flex items-center gap-1">
                        <x-icon name="o-cog-6-tooth" class="w-3 h-3" />
                        Personnaliser le modèle
                    </a>
                </div>
            </x-card>

            <a href="{{ route('admin.report-cards.index') }}" wire:navigate>
                <x-button label="Retour aux bulletins" icon="o-arrow-left" class="btn-ghost btn-sm w-full" />
            </a>

        </div>
    </div>

    {{-- Reject modal --}}
    <x-modal wire:model="showReject" title="Rejeter le bulletin" separator class="print:hidden">
        <p class="text-sm text-base-content/60 mb-3">
            Le bulletin retournera en brouillon et pourra être corrigé. Indiquez le motif (min. 5 caractères).
        </p>
        <x-textarea wire:model="rejectReason" rows="3" placeholder="Motif du rejet…" />
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('showReject', false)" class="btn-ghost" />
            <x-button label="Confirmer le rejet" icon="o-x-mark" wire:click="rejectWorkflow" class="btn-error" spinner="rejectWorkflow" />
        </x-slot:actions>
    </x-modal>
</div>

{{-- Print styles --}}
<style>
@media print {
    body * { visibility: hidden; }
    #bulletin-doc, #bulletin-doc * { visibility: visible; }
    #bulletin-doc { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
