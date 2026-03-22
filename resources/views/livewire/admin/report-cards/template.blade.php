<?php
use App\Models\ReportCardTemplate;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    // Header
    public string $header_title = 'Bulletin Scolaire';
    public string $subtitle     = '';
    public string $instructions = '';
    public string $footer_text  = '';

    // Director
    public string $principal_name  = '';
    public string $principal_title = 'Le Directeur';

    // Mention thresholds
    public float $mention_tb_min = 16;
    public float $mention_b_min  = 14;
    public float $mention_ab_min = 12;
    public float $mention_p_min  = 10;

    // Display toggles
    public bool $show_rank            = true;
    public bool $show_class_avg       = true;
    public bool $show_absences        = false;
    public bool $show_teacher_comment = true;

    public function mount(): void
    {
        $tpl = ReportCardTemplate::forSchool(auth()->user()->school_id);

        $this->header_title         = $tpl->header_title;
        $this->subtitle             = $tpl->subtitle            ?? '';
        $this->instructions         = $tpl->instructions        ?? '';
        $this->footer_text          = $tpl->footer_text         ?? '';
        $this->principal_name       = $tpl->principal_name      ?? '';
        $this->principal_title      = $tpl->principal_title;
        $this->mention_tb_min       = (float)$tpl->mention_tb_min;
        $this->mention_b_min        = (float)$tpl->mention_b_min;
        $this->mention_ab_min       = (float)$tpl->mention_ab_min;
        $this->mention_p_min        = (float)$tpl->mention_p_min;
        $this->show_rank            = (bool)$tpl->show_rank;
        $this->show_class_avg       = (bool)$tpl->show_class_avg;
        $this->show_absences        = (bool)$tpl->show_absences;
        $this->show_teacher_comment = (bool)$tpl->show_teacher_comment;
    }

    public function save(): void
    {
        $this->validate([
            'header_title'    => 'required|string|max:100',
            'subtitle'        => 'nullable|string|max:150',
            'instructions'    => 'nullable|string|max:500',
            'footer_text'     => 'nullable|string|max:300',
            'principal_name'  => 'nullable|string|max:100',
            'principal_title' => 'required|string|max:80',
            'mention_tb_min'  => 'required|numeric|min:0|max:20',
            'mention_b_min'   => 'required|numeric|min:0|max:20',
            'mention_ab_min'  => 'required|numeric|min:0|max:20',
            'mention_p_min'   => 'required|numeric|min:0|max:20',
        ]);

        ReportCardTemplate::forSchool(auth()->user()->school_id)->update([
            'header_title'         => $this->header_title,
            'subtitle'             => $this->subtitle             ?: null,
            'instructions'         => $this->instructions         ?: null,
            'footer_text'          => $this->footer_text          ?: null,
            'principal_name'       => $this->principal_name       ?: null,
            'principal_title'      => $this->principal_title,
            'mention_tb_min'       => $this->mention_tb_min,
            'mention_b_min'        => $this->mention_b_min,
            'mention_ab_min'       => $this->mention_ab_min,
            'mention_p_min'        => $this->mention_p_min,
            'show_rank'            => $this->show_rank,
            'show_class_avg'       => $this->show_class_avg,
            'show_absences'        => $this->show_absences,
            'show_teacher_comment' => $this->show_teacher_comment,
        ]);

        $this->success('Modèle de bulletin enregistré.', position: 'toast-top toast-end', icon: 'o-pencil-square', css: 'alert-success', timeout: 3000);
    }
};
?>

<div>
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-2 text-sm text-base-content/60">
                <a href="{{ route('admin.report-cards.index') }}" wire:navigate class="hover:text-primary">Bulletins</a>
                <x-icon name="o-chevron-right" class="w-3 h-3"/>
                <span class="text-base-content font-semibold">Modèle de bulletin</span>
            </div>
        </x-slot:title>
    </x-header>

    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ─── SETTINGS COLUMN ───────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Header & identity --}}
                <x-card title="En-tête du bulletin" separator>
                    <div class="space-y-4">
                        <x-input label="Titre principal *" wire:model.live="header_title"
                                 placeholder="Bulletin Scolaire" />
                        <x-input label="Sous-titre" wire:model.live="subtitle"
                                 placeholder="République Française — Académie de Paris" />
                        <x-textarea label="Instructions / texte d'introduction" wire:model.live="instructions"
                                    rows="3"
                                    placeholder="Ce bulletin est confidentiel et destiné aux familles..."/>
                    </div>
                </x-card>

                {{-- Mentions scale --}}
                <x-card title="Barème des mentions" separator>
                    <div class="mb-3 p-3 rounded-xl bg-info/10 text-xs text-info flex gap-2">
                        <x-icon name="o-information-circle" class="w-4 h-4 shrink-0 mt-0.5" />
                        <span>Ces seuils s'appliquent à toutes les moyennes (matière et générale) sur 20 points.</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold flex items-center gap-2">
                                <span class="badge badge-success badge-sm">Très Bien</span>
                                moyenne ≥
                            </label>
                            <x-input wire:model.live="mention_tb_min" type="number" step="0.5" min="0" max="20" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold flex items-center gap-2">
                                <span class="badge badge-info badge-sm">Bien</span>
                                moyenne ≥
                            </label>
                            <x-input wire:model.live="mention_b_min" type="number" step="0.5" min="0" max="20" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold flex items-center gap-2">
                                <span class="badge badge-primary badge-sm">Assez Bien</span>
                                moyenne ≥
                            </label>
                            <x-input wire:model.live="mention_ab_min" type="number" step="0.5" min="0" max="20" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold flex items-center gap-2">
                                <span class="badge badge-warning badge-sm">Passable</span>
                                moyenne ≥
                            </label>
                            <x-input wire:model.live="mention_p_min" type="number" step="0.5" min="0" max="20" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-base-content/40 italic">
                        En-dessous de {{ $mention_p_min }}/20 → <span class="badge badge-error badge-xs">Insuffisant</span>
                    </p>
                </x-card>

                {{-- Display options --}}
                <x-card title="Options d'affichage" separator>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-base-100">
                            <div>
                                <p class="font-semibold text-sm">Afficher le rang</p>
                                <p class="text-xs text-base-content/50">Position de l'élève dans la classe</p>
                            </div>
                            <input type="checkbox" wire:model.live="show_rank" class="toggle toggle-primary" />
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-base-100">
                            <div>
                                <p class="font-semibold text-sm">Afficher la moyenne de la classe</p>
                                <p class="text-xs text-base-content/50">Colonne "Moy. classe" dans le tableau des notes</p>
                            </div>
                            <input type="checkbox" wire:model.live="show_class_avg" class="toggle toggle-primary" />
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-base-100">
                            <div>
                                <p class="font-semibold text-sm">Afficher les absences par matière</p>
                                <p class="text-xs text-base-content/50">Nombre d'absences dans le tableau</p>
                            </div>
                            <input type="checkbox" wire:model.live="show_absences" class="toggle toggle-primary" />
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <p class="font-semibold text-sm">Afficher les appréciations des enseignants</p>
                                <p class="text-xs text-base-content/50">Colonne "Appréciation" et section professeur principal</p>
                            </div>
                            <input type="checkbox" wire:model.live="show_teacher_comment" class="toggle toggle-primary" />
                        </div>
                    </div>
                </x-card>

                {{-- Director signature --}}
                <x-card title="Signature du directeur" separator>
                    <div class="grid grid-cols-2 gap-4">
                        <x-input label="Titre" wire:model.live="principal_title"
                                 placeholder="Le Directeur" />
                        <x-input label="Nom complet" wire:model.live="principal_name"
                                 placeholder="M. Jean Dupont" />
                    </div>
                    <p class="mt-2 text-xs text-base-content/40">
                        Laissez le nom vide pour ne pas afficher la zone de signature.
                    </p>
                </x-card>

                {{-- Footer --}}
                <x-card title="Pied de page" separator>
                    <x-textarea label="Texte de bas de page" wire:model.live="footer_text" rows="2"
                                placeholder="Tout recours doit être adressé au secrétariat dans un délai de 15 jours." />
                </x-card>

                <div class="flex gap-3">
                    <x-button label="Enregistrer le modèle" type="submit" icon="o-check"
                              class="btn-primary" spinner />
                    <a href="{{ route('admin.report-cards.index') }}" wire:navigate>
                        <x-button label="Retour" icon="o-arrow-left" class="btn-ghost" />
                    </a>
                </div>

            </div>

            {{-- ─── LIVE PREVIEW ───────────────────────────────── --}}
            <div class="sticky top-4">
                <x-card>
                    <p class="text-xs font-semibold uppercase tracking-widest text-base-content/40 mb-3">Aperçu de l'en-tête</p>

                    <div class="rounded-xl overflow-hidden border border-base-200 shadow-sm">
                        {{-- Header band --}}
                        <div class="bg-linear-to-br from-primary to-primary/80 text-primary-content px-5 py-4">
                            <p class="text-[10px] opacity-60 uppercase tracking-wider">Nom de l'école</p>
                            <p class="font-black text-lg mt-0.5">{{ $header_title ?: 'Bulletin Scolaire' }}</p>
                            @if($subtitle)
                            <p class="text-xs opacity-70 mt-0.5">{{ $subtitle }}</p>
                            @endif
                        </div>

                        {{-- Student band --}}
                        <div class="flex items-center gap-3 px-5 py-3 bg-white border-b border-base-100">
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-black text-primary shrink-0">A</div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm">Nom de l'Élève</p>
                                <p class="text-xs text-base-content/50">Classe — Niveau</p>
                            </div>
                            <div class="text-center shrink-0">
                                <div class="w-12 h-12 rounded-full border-2 border-success flex flex-col items-center justify-center">
                                    <span class="text-sm font-black">15.5</span>
                                </div>
                                <p class="text-[10px] mt-0.5 text-success font-semibold">Bien</p>
                            </div>
                        </div>

                        {{-- Instructions preview --}}
                        @if($instructions)
                        <div class="px-5 py-2 bg-info/5 border-b border-base-100">
                            <p class="text-[10px] text-base-content/50 italic truncate">{{ $instructions }}</p>
                        </div>
                        @endif

                        {{-- Table preview --}}
                        <div class="px-5 py-3 bg-white">
                            <table class="w-full text-[10px]">
                                <thead>
                                    <tr class="border-b border-base-200">
                                        <th class="text-left py-1 text-base-content/50">Matière</th>
                                        <th class="text-center py-1 text-base-content/50">Coef.</th>
                                        <th class="text-center py-1 text-base-content/50">Note</th>
                                        @if($show_class_avg)
                                        <th class="text-center py-1 text-base-content/50">Moy. cl.</th>
                                        @endif
                                        <th class="text-left py-1 text-base-content/50">Mention</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-base-50">
                                        <td class="py-1 font-semibold">Mathématiques</td>
                                        <td class="text-center text-base-content/50">×3</td>
                                        <td class="text-center font-bold text-success">16.00</td>
                                        @if($show_class_avg)<td class="text-center text-base-content/40">13.50</td>@endif
                                        <td><span class="badge badge-info badge-xs">Bien</span></td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 font-semibold">Français</td>
                                        <td class="text-center text-base-content/50">×3</td>
                                        <td class="text-center font-bold text-warning">9.50</td>
                                        @if($show_class_avg)<td class="text-center text-base-content/40">11.20</td>@endif
                                        <td><span class="badge badge-error badge-xs">Insuffisant</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Mention scale preview --}}
                        <div class="px-5 py-2 bg-base-50 border-t border-base-100 text-[9px] text-base-content/40 space-y-0.5">
                            <p>TB ≥ {{ $mention_tb_min }} | B ≥ {{ $mention_b_min }} | AB ≥ {{ $mention_ab_min }} | P ≥ {{ $mention_p_min }} | I &lt; {{ $mention_p_min }}</p>
                        </div>

                        {{-- Footer preview --}}
                        @if($footer_text || $principal_name)
                        <div class="px-5 py-2 border-t border-base-200 flex justify-between items-end text-[9px] text-base-content/40">
                            <p class="italic truncate max-w-[60%]">{{ $footer_text }}</p>
                            @if($principal_name)
                            <div class="text-right">
                                <p>{{ $principal_title }}</p>
                                <p class="font-semibold text-base-content/60 mt-1">{{ $principal_name }}</p>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>

                    <p class="text-xs text-base-content/40 text-center mt-3 italic">Les données affichées sont des exemples.</p>
                </x-card>
            </div>

        </div>
    </x-form>
</div>
