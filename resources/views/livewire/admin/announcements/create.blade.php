<?php
use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Enums\AnnouncementLevel;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public string $title          = '';
    public string $body           = '';
    public string $level          = 'info';
    public string $targetAudience = 'all';
    public array  $targetClassIds = [];
    public string $publishAt      = '';
    public string $expiresAt      = '';

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:200',
            'body'  => 'required|string',
            'level' => 'required|in:' . implode(',', array_column(AnnouncementLevel::cases(), 'value')),
        ]);

        Announcement::create([
            'school_id'       => auth()->user()->school_id,
            'created_by'      => auth()->id(),
            'title'           => $this->title,
            'body'            => $this->body,
            'level'           => $this->level,
            'target_audience' => $this->targetAudience,
            'target_class_ids'=> $this->targetAudience === 'class' ? $this->targetClassIds : null,
            'publish_at'      => $this->publishAt ?: null,
            'expires_at'      => $this->expiresAt ?: null,
        ]);

        $this->success('Annonce publiée.', position: 'toast-top toast-end', icon: 'o-paper-airplane', css: 'alert-success', timeout: 3000);
        $this->redirect(route('admin.announcements.index'), navigate: true);
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        return [
            'levels'   => collect(AnnouncementLevel::cases())->map(fn($l) => ['id' => $l->value, 'name' => $l->label()])->all(),
            'classes'  => SchoolClass::where('school_id', $schoolId)->with('grade')->orderBy('name')->get(),
            'audiences'=> [
                ['id' => 'all',       'name' => 'Toute la communauté'],
                ['id' => 'teachers',  'name' => 'Enseignants'],
                ['id' => 'guardians', 'name' => 'Parents / Tuteurs'],
                ['id' => 'students',  'name' => 'Élèves'],
                ['id' => 'class',     'name' => 'Classes spécifiques'],
            ],
        ];
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.announcements.index') }}" wire:navigate class="hover:text-primary">Annonces</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content">Nouvelle annonce</span>
            </div>
        </x-slot:title>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Form --}}
        <div class="lg:col-span-2">
            <x-card title="Nouvelle annonce" separator>
                <x-form wire:submit="save" class="space-y-4">
                    <x-input label="Titre *" wire:model.live="title" placeholder="Titre de l'annonce" required />

                    <div class="grid grid-cols-2 gap-4">
                        <x-select label="Niveau *" wire:model.live="level"
                                  :options="$levels" option-value="id" option-label="name" />
                        <x-select label="Audience *" wire:model.live="targetAudience"
                                  :options="$audiences" option-value="id" option-label="name" />
                    </div>

                    @if($targetAudience === 'class')
                    <x-select
                        label="Classes ciblées *"
                        wire:model="targetClassIds"
                        :options="$classes"
                        option-value="id"
                        option-label="name"
                        placeholder="Choisir des classes..."
                        placeholder-value=""
                        multiple
                    />
                    @endif

                    <x-textarea label="Contenu *" wire:model.live="body" rows="6"
                                placeholder="Rédigez votre annonce..." required />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label"><span class="label-text">Publication différée</span></label>
                            <input type="datetime-local" wire:model="publishAt" class="input input-bordered w-full input-sm" />
                            <p class="text-xs text-base-content/50 mt-1">Laisser vide pour publier maintenant</p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text">Date d'expiration</span></label>
                            <input type="datetime-local" wire:model="expiresAt" class="input input-bordered w-full input-sm" />
                            <p class="text-xs text-base-content/50 mt-1">Laisser vide = pas d'expiration</p>
                        </div>
                    </div>

                    <x-slot:actions>
                        <x-button label="Annuler" :link="route('admin.announcements.index')"
                                  class="btn-ghost" wire:navigate />
                        <x-button label="Publier l'annonce" type="submit"
                                  icon="o-megaphone" class="btn-primary" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </div>

        {{-- Live preview --}}
        <div>
            <x-card title="Aperçu" separator>
                @php
                    $borderColor = match($level) {
                        'info'    => 'border-l-info',
                        'warning' => 'border-l-warning',
                        'urgent'  => 'border-l-error',
                        default   => 'border-l-base-300',
                    };
                    $badgeClass = match($level) {
                        'info'    => 'badge-info',
                        'warning' => 'badge-warning',
                        'urgent'  => 'badge-error',
                        default   => 'badge-ghost',
                    };
                    $levelLabel = collect($levels)->firstWhere('id', $level)['name'] ?? $level;
                @endphp
                <div class="border-l-4 {{ $borderColor }} pl-4 py-2 rounded-r-xl bg-base-200/50">
                    <div class="flex items-center gap-2 mb-1">
                        <p class="font-bold text-sm">{{ $title ?: 'Titre de l\'annonce' }}</p>
                        <x-badge :value="$levelLabel" class="{{ $badgeClass }} badge-xs" />
                    </div>
                    <p class="text-xs text-base-content/70 line-clamp-4">{{ $body ?: 'Le contenu de l\'annonce apparaîtra ici...' }}</p>
                    <p class="text-xs text-base-content/40 mt-2">{{ auth()->user()->name }} · Maintenant</p>
                </div>
            </x-card>
        </div>
    </div>
</div>
