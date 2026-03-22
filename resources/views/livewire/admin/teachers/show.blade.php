<?php
use App\Enums\AttachmentCategory;
use App\Models\Teacher;
use App\Models\Assessment;
use App\Models\Attachment;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Storage;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public Teacher $teacher;
    public bool    $showDocsDrawer = false;

    public function mount(string $uuid): void
    {
        $this->teacher = Teacher::where('school_id', auth()->user()->school_id)
            ->where('uuid', $uuid)
            ->with(['subjects', 'schoolClasses.grade', 'schoolClasses.academicYear'])
            ->firstOrFail();

        if (session()->has('success')) { $this->success(session('success')); }
        if (session()->has('warning')) { $this->warning(session('warning')); }
    }

    public function toggleActive(): void
    {
        $this->teacher->update(['is_active' => !$this->teacher->is_active]);
        $this->teacher->refresh();
        $this->success($this->teacher->is_active ? 'Enseignant activé.' : 'Enseignant désactivé.', position: 'toast-top toast-end', icon: 'o-bolt', css: 'alert-success', timeout: 3000);
    }

    public function deleteDocument(int $id): void
    {
        $attachment = Attachment::findOrFail($id);
        abort_unless($attachment->school_id === auth()->user()->school_id, 403);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
        $this->success('Document supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    public function with(): array
    {
        $assessmentsCount = Assessment::where('teacher_id', $this->teacher->id)->count();

        $recentDocs = Attachment::where('attachable_type', Teacher::class)
            ->where('attachable_id', $this->teacher->id)
            ->where('school_id', auth()->user()->school_id)
            ->latest()
            ->get();

        $documentsCount = $recentDocs->count();

        $yearsActive = $this->teacher->hire_date
            ? (int) $this->teacher->hire_date->diffInYears(now())
            : null;

        $groupedDocs = $recentDocs->groupBy(fn($a) =>
            $a->category instanceof AttachmentCategory ? $a->category->value : $a->category
        );

        return compact('assessmentsCount', 'documentsCount', 'recentDocs', 'groupedDocs', 'yearsActive');
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.teachers.index') }}" wire:navigate class="hover:text-primary">Enseignants</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">{{ $teacher->full_name }}</span>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="{{ $teacher->is_active ? 'Désactiver' : 'Activer' }}"
                      icon="{{ $teacher->is_active ? 'o-pause-circle' : 'o-play-circle' }}"
                      wire:click="toggleActive"
                      class="{{ $teacher->is_active ? 'btn-ghost text-warning' : 'btn-ghost text-success' }}" />
            <x-button label="Documents" icon="o-paper-clip"
                      wire:click="$set('showDocsDrawer', true)"
                      class="btn-outline"
                      :badge="$documentsCount ?: null" badge-classes="badge-warning" />
            <x-button label="Modifier" icon="o-pencil"
                      :link="route('admin.teachers.edit', $teacher->uuid)"
                      class="btn-primary" wire:navigate />
        </x-slot:actions>
    </x-header>

    {{-- ── Hero ── --}}
    <x-card class="mb-6 overflow-hidden p-0">
        <div class="h-28 bg-linear-to-r from-primary via-primary/80 to-secondary"></div>
        <div class="px-6 pb-5">
            <div class="flex items-end gap-5 -mt-12 mb-4">
                <div class="w-24 h-24 rounded-2xl bg-base-100 border-4 border-base-100 shadow-lg overflow-hidden flex items-center justify-center shrink-0">
                    @if($teacher->photo_url)
                    <img src="{{ $teacher->photo_url }}" alt="{{ $teacher->full_name }}" class="w-full h-full object-cover" />
                    @else
                    <span class="font-black text-4xl text-primary">{{ strtoupper(substr($teacher->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="pb-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-2xl font-black">{{ $teacher->full_name }}</h2>
                        <span class="badge {{ $teacher->is_active ? 'badge-success' : 'badge-ghost' }} badge-sm">
                            {{ $teacher->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </div>
                    @if($teacher->specialization)
                    <p class="text-base-content/60 text-sm mt-0.5">{{ $teacher->specialization }}</p>
                    @endif
                    <div class="flex items-center gap-4 mt-2 flex-wrap">
                        @if($teacher->email)
                        <span class="text-sm text-base-content/60 flex items-center gap-1">
                            <x-icon name="o-envelope" class="w-3.5 h-3.5"/>{{ $teacher->email }}
                        </span>
                        @endif
                        @if($teacher->phone)
                        <span class="text-sm text-base-content/60 flex items-center gap-1">
                            <x-icon name="o-phone" class="w-3.5 h-3.5"/>{{ $teacher->phone }}
                        </span>
                        @endif
                        @if($teacher->reference)
                        <span class="text-sm font-mono text-base-content/40 flex items-center gap-1">
                            <x-icon name="o-hashtag" class="w-3.5 h-3.5"/>{{ $teacher->reference }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Stat bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-3 border-t border-base-200">
                <div class="text-center">
                    <p class="text-2xl font-black text-primary">{{ $teacher->subjects->count() }}</p>
                    <p class="text-xs text-base-content/50 mt-0.5">Matière(s)</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-black text-secondary">{{ $teacher->schoolClasses->count() }}</p>
                    <p class="text-xs text-base-content/50 mt-0.5">Classe(s)</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-black text-accent">{{ $assessmentsCount }}</p>
                    <p class="text-xs text-base-content/50 mt-0.5">Évaluation(s)</p>
                </div>
                <button wire:click="$set('showDocsDrawer', true)" class="text-center hover:opacity-70 transition-opacity">
                    <p class="text-2xl font-black text-warning">{{ $documentsCount }}</p>
                    <p class="text-xs text-base-content/50 mt-0.5">Document(s)</p>
                </button>
                <div class="text-center">
                    <p class="text-2xl font-black text-info">{{ $yearsActive ?? '—' }}</p>
                    <p class="text-xs text-base-content/50 mt-0.5">An(s) d'ancienneté</p>
                </div>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Main content ── --}}
        <div class="lg:col-span-2">
            <x-tabs>

                {{-- Info tab --}}
                <x-tab name="info" label="Informations" icon="o-user">
                    <div class="mt-4 space-y-4">
                        <x-card title="Données personnelles" separator>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                                <div>
                                    <p class="text-base-content/50 mb-0.5">Genre</p>
                                    <p class="font-semibold">{{ $teacher->gender?->value === 'male' ? 'Homme' : ($teacher->gender?->value === 'female' ? 'Femme' : '—') }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50 mb-0.5">Date d'embauche</p>
                                    <p class="font-semibold">{{ $teacher->hire_date ? $teacher->hire_date->format('d/m/Y') : '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50 mb-0.5">Spécialisation</p>
                                    <p class="font-semibold">{{ $teacher->specialization ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50 mb-0.5">Référence</p>
                                    <p class="font-mono font-bold">{{ $teacher->reference ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50 mb-0.5">Adresse</p>
                                    <p class="font-semibold">{{ $teacher->address ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50 mb-0.5">Membre depuis</p>
                                    <p class="font-semibold">{{ $teacher->created_at->format('d/m/Y') }}</p>
                                </div>
                            </div>
                            @if($teacher->notes)
                            <div class="mt-4 pt-4 border-t border-base-200">
                                <p class="text-base-content/50 text-sm mb-1">Notes</p>
                                <p class="text-sm bg-base-100 rounded-xl p-3 leading-relaxed">{{ $teacher->notes }}</p>
                            </div>
                            @endif
                        </x-card>

                        @if($teacher->subjects->count())
                        <x-card title="Matières enseignées" separator>
                            <div class="space-y-2">
                                @foreach($teacher->subjects as $subject)
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-base-100">
                                    <div class="flex items-center gap-2.5">
                                        @if($subject->color)
                                        <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $subject->color }}"></span>
                                        @endif
                                        <span class="font-semibold text-sm">{{ $subject->name }}</span>
                                        @if($subject->code)
                                        <span class="text-xs text-base-content/40 font-mono">{{ $subject->code }}</span>
                                        @endif
                                    </div>
                                    @if($subject->default_coefficient && $subject->default_coefficient != 1)
                                    <span class="text-xs text-base-content/50 bg-base-200 px-2 py-0.5 rounded-full">
                                        Coeff. {{ $subject->default_coefficient }}
                                    </span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </x-card>
                        @endif
                    </div>
                </x-tab>

                {{-- Classes tab --}}
                <x-tab name="classes" label="Classes" icon="o-building-library">
                    <x-card class="mt-4">
                        @if($teacher->schoolClasses->count())
                        <div class="overflow-x-auto"><table class="table w-full">
                            <thead><tr>
                                <th>Classe</th><th>Niveau</th><th>Année scolaire</th>
                            </tr></thead><tbody>
                            @foreach($teacher->schoolClasses as $class)
                            <tr class="hover">
                                <td class="font-semibold text-sm">{{ $class->name }}</td>
                                <td class="text-sm text-base-content/70">{{ $class->grade?->name ?? '—' }}</td>
                                <td><span class="badge badge-outline badge-sm">{{ $class->academicYear?->name ?? '—' }}</span></td>
                            </tr>
                            @endforeach
                        </tbody></table></div>
                        @else
                        <div class="text-center py-10 text-base-content/40">
                            <x-icon name="o-building-library" class="w-10 h-10 mx-auto mb-2 opacity-20" />
                            <p class="text-sm">Aucune classe assignée</p>
                            <a href="{{ route('admin.teachers.edit', $teacher->uuid) }}" wire:navigate
                               class="btn btn-ghost btn-sm mt-2">Assigner des classes</a>
                        </div>
                        @endif
                    </x-card>
                </x-tab>

                {{-- Documents tab -- full list grouped by category --}}
                <x-tab name="documents" label="Documents" icon="o-paper-clip"
                       :badge="$documentsCount ?: null" badge-classes="badge-warning badge-xs">
                    <div class="mt-4 space-y-3">

                        {{-- Upload CTA --}}
                        <x-card>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-warning/10 flex items-center justify-center">
                                        <x-icon name="o-arrow-up-tray" class="w-5 h-5 text-warning" />
                                    </div>
                                    <div>
                                        <p class="font-semibold text-sm">Ajouter un document</p>
                                        <p class="text-xs text-base-content/50">Diplôme, certificat, contrat, passeport…</p>
                                    </div>
                                </div>
                                <x-button label="Téléverser" icon="o-plus"
                                          wire:click="$set('showDocsDrawer', true)"
                                          class="btn-warning btn-sm" />
                            </div>
                        </x-card>

                        {{-- Empty state --}}
                        @if($recentDocs->isEmpty())
                        <div class="text-center py-12 text-base-content/30 border-2 border-dashed border-base-300 rounded-2xl">
                            <x-icon name="o-folder-open" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                            <p class="font-semibold">Aucun document</p>
                            <p class="text-xs mt-1">Ajoutez des diplômes, certificats, contrats…</p>
                        </div>
                        @else

                        {{-- Grouped by category --}}
                        @foreach($groupedDocs as $catValue => $docs)
                        @php
                            $catEnum  = AttachmentCategory::tryFrom($catValue);
                            $catLabel = $catEnum?->label() ?? $catValue;
                            $catIcon  = $catEnum?->icon() ?? 'o-paper-clip';
                        @endphp
                        <div wire:key="cat-{{ $catValue }}">
                            <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-2 flex items-center gap-1.5">
                                <x-icon :name="$catIcon" class="w-3.5 h-3.5" />{{ $catLabel }}
                                <span class="font-normal">({{ $docs->count() }})</span>
                            </p>
                            <div class="space-y-1.5">
                                @foreach($docs as $doc)
                                <div wire:key="doc-{{ $doc->id }}"
                                     class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 hover:bg-base-200 transition-colors group">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                                                {{ $doc->isPdf() ? 'bg-error/10 text-error' : ($doc->isImage() ? 'bg-success/10 text-success' : 'bg-primary/10 text-primary') }}">
                                        @if($doc->isPdf())
                                            <x-icon name="o-document" class="w-5 h-5" />
                                        @elseif($doc->isImage())
                                            <x-icon name="o-photo" class="w-5 h-5" />
                                        @else
                                            <x-icon name="o-document-text" class="w-5 h-5" />
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-semibold text-sm truncate">{{ $doc->label }}</p>
                                            @if($doc->isExpired())
                                                <x-badge value="Expiré" class="badge-error badge-xs" />
                                            @elseif($doc->isExpiringSoon())
                                                <x-badge value="Expire bientôt" class="badge-warning badge-xs" />
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-base-content/40 mt-0.5 flex-wrap">
                                            <span>{{ $doc->original_name }}</span>
                                            <span>{{ $doc->humanSize() }}</span>
                                            @if($doc->expires_at)
                                            <span>Exp. {{ $doc->expires_at->format('d/m/Y') }}</span>
                                            @endif
                                            <span>{{ $doc->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if($doc->notes)
                                        <p class="text-xs text-base-content/50 mt-0.5 italic truncate">{{ $doc->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                        <a href="{{ $doc->url() }}" target="_blank" class="btn btn-ghost btn-xs" title="Télécharger">
                                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                        </a>
                                        <x-button icon="o-trash"
                                                  wire:click="deleteDocument({{ $doc->id }})"
                                                  wire:confirm="Supprimer ce document ?"
                                                  class="btn-ghost btn-xs text-error" />
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach

                        @endif
                    </div>
                </x-tab>

            </x-tabs>
        </div>

        {{-- ── Sidebar ── --}}
        <div class="space-y-4">

            {{-- Recent documents --}}
            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold flex items-center gap-1.5">
                            <x-icon name="o-paper-clip" class="w-4 h-4 text-warning"/>
                            Documents
                            @if($documentsCount)
                            <x-badge value="{{ $documentsCount }}" class="badge-warning badge-xs" />
                            @endif
                        </span>
                        <x-button label="Ajouter" icon="o-plus"
                                  wire:click="$set('showDocsDrawer', true)"
                                  class="btn-warning btn-xs" />
                    </div>
                </x-slot:title>

                @if($recentDocs->isEmpty())
                <div class="text-center py-6 text-base-content/30 border-2 border-dashed border-base-200 rounded-xl">
                    <x-icon name="o-cloud-arrow-up" class="w-8 h-8 mx-auto mb-1.5 opacity-30" />
                    <p class="text-xs">Aucun document</p>
                    <x-button label="Téléverser" icon="o-plus"
                              wire:click="$set('showDocsDrawer', true)"
                              class="btn-warning btn-xs mt-2" />
                </div>
                @else
                <div class="space-y-2">
                    @foreach($recentDocs->take(6) as $doc)
                    @php
                        $dc = AttachmentCategory::tryFrom(
                            $doc->category instanceof AttachmentCategory ? $doc->category->value : $doc->category
                        );
                    @endphp
                    <div wire:key="side-doc-{{ $doc->id }}" class="flex items-center gap-2 group">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0
                                    {{ $doc->isPdf() ? 'bg-error/10 text-error' : ($doc->isImage() ? 'bg-success/10 text-success' : 'bg-primary/10 text-primary') }}">
                            @if($doc->isPdf())
                                <x-icon name="o-document" class="w-4 h-4" />
                            @elseif($doc->isImage())
                                <x-icon name="o-photo" class="w-4 h-4" />
                            @else
                                <x-icon name="o-document-text" class="w-4 h-4" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold truncate">{{ $doc->label }}</p>
                            <p class="text-xs text-base-content/40 truncate">{{ $dc?->label() ?? $doc->category }}</p>
                        </div>
                        <div class="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                            <a href="{{ $doc->url() }}" target="_blank" class="btn btn-ghost btn-xs">
                                <x-icon name="o-arrow-down-tray" class="w-3.5 h-3.5" />
                            </a>
                            <x-button icon="o-trash"
                                      wire:click="deleteDocument({{ $doc->id }})"
                                      wire:confirm="Supprimer ?"
                                      class="btn-ghost btn-xs text-error" />
                        </div>
                    </div>
                    @endforeach
                    @if($recentDocs->count() > 6)
                    <p class="text-xs text-center text-base-content/40 pt-1">
                        + {{ $recentDocs->count() - 6 }} autre(s) — onglet Documents
                    </p>
                    @endif
                </div>
                @endif
            </x-card>

            {{-- Quick info --}}
            <x-card title="Résumé">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-base-content/60">Statut</span>
                        <span class="badge {{ $teacher->is_active ? 'badge-success' : 'badge-ghost' }} badge-sm">
                            {{ $teacher->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Matières</span>
                        <span class="font-bold">{{ $teacher->subjects->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Classes</span>
                        <span class="font-bold">{{ $teacher->schoolClasses->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Évaluations</span>
                        <span class="font-bold">{{ $assessmentsCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Documents</span>
                        <span class="font-bold {{ $documentsCount ? 'text-warning' : '' }}">{{ $documentsCount }}</span>
                    </div>
                    @if($teacher->hire_date)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Embauché le</span>
                        <span>{{ $teacher->hire_date->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    @if($teacher->reference)
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Référence</span>
                        <span class="font-mono font-bold">{{ $teacher->reference }}</span>
                    </div>
                    @endif
                </div>
            </x-card>

            {{-- Subjects chips --}}
            @if($teacher->subjects->count())
            <x-card title="Matières">
                <div class="flex flex-wrap gap-1.5">
                    @foreach($teacher->subjects as $subject)
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold"
                          style="background-color: {{ $subject->color ?? '#6366f1' }}20; color: {{ $subject->color ?? '#6366f1' }}">
                        {{ $subject->name }}
                    </span>
                    @endforeach
                </div>
            </x-card>
            @endif

            {{-- Actions --}}
            <x-card title="Actions rapides">
                <div class="space-y-2">
                    <a href="{{ route('admin.teachers.edit', $teacher->uuid) }}" wire:navigate class="block">
                        <x-button label="Modifier le profil" icon="o-pencil" class="btn-outline w-full" />
                    </a>
                    <x-button label="{{ $teacher->is_active ? 'Désactiver' : 'Activer' }}"
                              icon="{{ $teacher->is_active ? 'o-pause-circle' : 'o-play-circle' }}"
                              wire:click="toggleActive"
                              class="w-full {{ $teacher->is_active ? 'btn-ghost text-warning' : 'btn-ghost text-success' }}" />
                </div>
            </x-card>

        </div>
    </div>

    {{-- ── Documents drawer (upload + full list via sub-component) ── --}}
    <x-drawer wire:model="showDocsDrawer" title="Documents de {{ $teacher->full_name }}"
              position="right" class="w-full sm:w-[480px]">
        <div class="p-1">
            <livewire:admin.components.attachments
                model-type="teacher"
                :model-id="$teacher->id"
                :key="'docs-drawer-'.$teacher->id" />
        </div>
        <x-slot:actions>
            <x-button label="Fermer" wire:click="$set('showDocsDrawer', false)" class="btn-ghost" />
        </x-slot:actions>
    </x-drawer>
</div>
