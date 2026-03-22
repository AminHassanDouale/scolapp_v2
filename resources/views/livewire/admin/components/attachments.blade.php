<?php
/**
 * Reusable attachment manager.
 *
 * Usage:
 *   <livewire:admin.components.attachments model-type="teacher" :model-id="$teacher->id" />
 *   <livewire:admin.components.attachments model-type="student" :model-id="$student->id" />
 *   <livewire:admin.components.attachments model-type="guardian" :model-id="$guardian->id" />
 */

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    // Props
    public string $modelType = '';  // 'teacher' | 'student' | 'guardian'
    public int    $modelId   = 0;

    // Upload form
    public bool   $showUpload = false;
    public string $category   = '';
    public string $label      = '';
    public string $notes      = '';
    public string $expiresAt  = '';
    public mixed  $file       = null;

    public function mount(string $modelType, int $modelId): void
    {
        $this->modelType = $modelType;
        $this->modelId   = $modelId;
    }

    public function upload(): void
    {
        $this->validate([
            'category' => 'required|string',
            'label'    => 'required|string|max:200',
            'file'     => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            'expiresAt'=> 'nullable|date|after:today',
        ]);

        $schoolId   = auth()->user()->school_id;
        $modelClass = $this->resolveModelClass();

        $ext          = $this->file->getClientOriginalExtension();
        $uuid         = (string) \Illuminate\Support\Str::uuid();
        $folder       = "attachments/{$schoolId}/{$this->modelType}/{$this->modelId}";
        $path         = $this->file->storeAs($folder, "{$uuid}.{$ext}", 'local');

        Attachment::create([
            'uuid'           => $uuid,
            'school_id'      => $schoolId,
            'attachable_type'=> $modelClass,
            'attachable_id'  => $this->modelId,
            'category'       => $this->category,
            'label'          => $this->label,
            'disk'           => 'local',
            'path'           => $path,
            'original_name'  => $this->file->getClientOriginalName(),
            'size'           => $this->file->getSize(),
            'mime_type'      => $this->file->getMimeType(),
            'expires_at'     => $this->expiresAt ?: null,
            'notes'          => $this->notes ?: null,
            'uploaded_by'    => auth()->id(),
        ]);

        $this->reset(['category', 'label', 'notes', 'expiresAt', 'file']);
        $this->showUpload = false;
        $this->success('Document ajouté.', position: 'toast-top toast-end', icon: 'o-document-check', css: 'alert-success', timeout: 3000);
    }

    public function delete(int $id): void
    {
        $attachment = Attachment::findOrFail($id);
        abort_unless($attachment->school_id === auth()->user()->school_id, 403);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        $this->success('Document supprimé.', position: 'toast-top toast-end', icon: 'o-trash', css: 'alert-success', timeout: 3000);
    }

    private function resolveModelClass(): string
    {
        return match($this->modelType) {
            'teacher'  => \App\Models\Teacher::class,
            'student'  => \App\Models\Student::class,
            'guardian' => \App\Models\Guardian::class,
            default    => throw new \InvalidArgumentException("Unknown model type: {$this->modelType}"),
        };
    }

    public function with(): array
    {
        $modelClass  = $this->resolveModelClass();
        $attachments = Attachment::where('attachable_type', $modelClass)
            ->where('attachable_id', $this->modelId)
            ->where('school_id', auth()->user()->school_id)
            ->with('uploader')
            ->latest()
            ->get();

        $grouped = $attachments->groupBy(fn($a) => $a->category instanceof AttachmentCategory
            ? $a->category->value : $a->category);

        $categories = collect(AttachmentCategory::forModel($modelClass))
            ->map(fn($c) => ['id' => $c->value, 'name' => $c->label()])
            ->all();

        return [
            'attachments' => $attachments,
            'grouped'     => $grouped,
            'categories'  => $categories,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-base flex items-center gap-2">
            <x-icon name="o-paper-clip" class="w-4 h-4 text-primary" />
            Pièces jointes
            @if($attachments->count())
            <x-badge value="{{ $attachments->count() }}" class="badge-neutral badge-sm" />
            @endif
        </h3>
        <x-button label="Ajouter un document" icon="o-plus"
                  wire:click="$set('showUpload', true)"
                  class="btn-primary btn-sm" />
    </div>

    {{-- Empty state --}}
    @if($attachments->isEmpty())
    <div class="text-center py-10 text-base-content/30 border-2 border-dashed border-base-300 rounded-xl">
        <x-icon name="o-document" class="w-10 h-10 mx-auto mb-2 opacity-30" />
        <p class="text-sm">Aucun document joint</p>
    </div>
    @else

    {{-- Grouped by category --}}
    <div class="space-y-4">
        @foreach($grouped as $catValue => $docs)
        @php
            $catEnum  = \App\Enums\AttachmentCategory::tryFrom($catValue);
            $catLabel = $catEnum?->label() ?? $catValue;
            $catIcon  = $catEnum?->icon() ?? 'o-paper-clip';
        @endphp
        <div wire:key="cat-{{ $catValue }}">
            <p class="text-xs font-bold uppercase tracking-wider text-base-content/40 mb-2 flex items-center gap-1">
                <x-icon :name="$catIcon" class="w-3.5 h-3.5" />
                {{ $catLabel }}
            </p>
            <div class="space-y-2">
                @foreach($docs as $doc)
                <div wire:key="doc-{{ $doc->id }}"
                     class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 hover:bg-base-200 transition-colors group">

                    {{-- File type icon --}}
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

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-sm truncate">{{ $doc->label }}</p>
                            @if($doc->isExpired())
                            <x-badge value="Expiré" class="badge-error badge-xs" />
                            @elseif($doc->isExpiringSoon())
                            <x-badge value="Expire bientôt" class="badge-warning badge-xs" />
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-xs text-base-content/40 mt-0.5">
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

                    {{-- Actions --}}
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                        <a href="{{ $doc->url() }}" target="_blank"
                           class="btn btn-ghost btn-xs tooltip" data-tip="Télécharger">
                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                        </a>
                        <x-button icon="o-trash"
                                  wire:click="delete({{ $doc->id }})"
                                  wire:confirm="Supprimer ce document ?"
                                  class="btn-ghost btn-xs text-error" tooltip="Supprimer" />
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Upload modal --}}
    <x-modal wire:model="showUpload" title="Ajouter un document" separator class="max-w-lg">
        <x-form wire:submit="upload" class="space-y-4">

            <x-select label="Type de document *"
                      wire:model="category"
                      :options="$categories"
                      option-value="id"
                      option-label="name"
                      placeholder="Choisir un type..."
                      placeholder-value=""
                      required />

            <x-input label="Intitulé *"
                     wire:model="label"
                     placeholder="Ex: Diplôme CAPES — Juin 2018"
                     required />

            {{-- File drop zone --}}
            <div>
                <label class="fieldset-legend mb-0.5">Fichier *</label>
                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed
                              rounded-xl cursor-pointer hover:bg-base-200 transition-colors
                              {{ $file ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                    <input type="file" wire:model="file" class="hidden"
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" />
                    @if($file)
                    <x-icon name="o-check-circle" class="w-8 h-8 text-primary mb-1" />
                    <p class="text-sm font-semibold text-primary">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-base-content/50">{{ round($file->getSize() / 1024, 1) }} KB</p>
                    @else
                    <x-icon name="o-cloud-arrow-up" class="w-8 h-8 text-base-content/30 mb-1" />
                    <p class="text-sm text-base-content/50">Cliquer pour sélectionner un fichier</p>
                    <p class="text-xs text-base-content/30 mt-0.5">PDF, Image, Word, Excel — max 10 MB</p>
                    @endif
                </label>
                @error('file') <div class="text-error text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-datepicker label="Date d'expiration"
                              wire:model="expiresAt"
                              icon="o-calendar"
                              placeholder="Optionnel"
                              :config="['dateFormat' => 'Y-m-d', 'altFormat' => 'd/m/Y', 'altInput' => true, 'minDate' => 'today']" />
                <x-input label="Notes" wire:model="notes"
                         placeholder="Optionnel" />
            </div>

            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showUpload = false" class="btn-ghost" />
                <x-button label="Enregistrer" type="submit" icon="o-check"
                          class="btn-primary" spinner="upload" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
