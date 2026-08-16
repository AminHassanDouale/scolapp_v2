<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Announcement;
use App\Models\Invoice;
use App\Enums\InvoiceStatus;

new #[Layout('layouts.guardian')] class extends Component {
    use Toast;

    public function with(): array
    {
        $user     = auth()->user();
        $guardian = Guardian::where('user_id', $user->id)->with('students')->first();
        $students = $guardian?->students ?? collect();

        $unpaidInvoices = Invoice::whereHas('enrollment.student', fn($q) => $q->whereIn('id', $students->pluck('id')))
            ->whereIn('status', [InvoiceStatus::ISSUED->value, InvoiceStatus::OVERDUE->value, InvoiceStatus::PARTIALLY_PAID->value])
            ->count();

        $classIds = $students->isNotEmpty()
            ? \App\Models\Enrollment::whereIn('student_id', $students->pluck('id'))
                ->where('status', 'confirmed')->pluck('school_class_id')->filter()->unique()->values()->all()
            : [];
        $recentAnnouncements = Announcement::where('school_id', $user->school_id)
            ->publishedNow()
            ->forAudience('guardians', $classIds)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        return compact('guardian', 'students', 'unpaidInvoices', 'recentAnnouncements');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.guardian_portal') }}" subtitle="{{ now()->isoFormat('dddd D MMMM Y') }}" separator>
        <x-slot:actions>
            <x-badge value="{{ __('navigation.guardian') }}" class="badge-success badge-lg" />
        </x-slot:actions>
    </x-header>

    {{-- Welcome banner --}}
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%)">
        <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/10 -translate-y-10 translate-x-10"></div>
        <div class="relative">
            <p class="text-emerald-100 text-sm font-medium">{{ __('navigation.welcome_back') }}</p>
            <h2 class="text-2xl font-black mt-1">{{ $guardian?->full_name ?? auth()->user()->full_name }}</h2>
            <p class="text-emerald-100 mt-1">{{ $students->count() }} {{ __('navigation.enrolled_children') }}</p>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <x-card class="border-0 shadow-sm bg-gradient-to-br from-emerald-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <x-icon name="o-user-group" class="w-5 h-5 text-emerald-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-emerald-700">{{ $students->count() }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.children') }}</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-red-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <x-icon name="o-document-currency-dollar" class="w-5 h-5 text-red-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-red-700">{{ $unpaidInvoices }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.unpaid_invoices') }}</p>
                </div>
            </div>
        </x-card>

        <x-card class="border-0 shadow-sm bg-gradient-to-br from-blue-50 to-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                    <x-icon name="o-megaphone" class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <p class="text-2xl font-black text-blue-700">{{ $recentAnnouncements->count() }}</p>
                    <p class="text-xs text-base-content/60">{{ __('navigation.announcements') }}</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- Quick access --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        @php $qa = [
            ['guardian.timetable',  'o-clock',                     'Emploi du temps', 'from-violet-500 to-purple-600'],
            ['guardian.attendance', 'o-calendar-days',             'Présences',       'from-amber-500 to-orange-600'],
            ['guardian.grades',     'o-chart-bar',                 'Notes',           'from-blue-500 to-indigo-600'],
            ['guardian.bulletins',  'o-document-text',             'Bulletins',       'from-emerald-500 to-teal-600'],
            ['guardian.invoices',   'o-document-currency-dollar',  'Factures',        'from-rose-500 to-red-600'],
        ]; @endphp
        @foreach($qa as $a)
        <a href="{{ route($a[0]) }}" wire:navigate class="group">
            <div class="rounded-2xl p-4 text-center bg-white shadow-sm border border-base-200 hover:shadow-md hover:-translate-y-0.5 transition-all">
                <div class="w-11 h-11 mx-auto rounded-xl bg-gradient-to-br {{ $a[3] }} flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <x-icon name="{{ $a[1] }}" class="w-5 h-5 text-white" />
                </div>
                <p class="text-xs font-semibold text-base-content/80">{{ $a[2] }}</p>
            </div>
        </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- My children --}}
        <x-card title="{{ __('navigation.my_children') }}" shadow separator>
            @forelse($students as $student)
            <div class="flex items-center gap-3 py-3 border-b border-base-100 last:border-0">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <span class="font-bold text-emerald-700">{{ substr($student->name, 0, 1) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold">{{ $student->full_name }}</p>
                    <p class="text-xs text-base-content/50">{{ $student->enrollments->first()?->schoolClass?->name ?? '—' }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('guardian.attendance', ['student' => $student->uuid]) }}" wire:navigate>
                        <x-badge value="Présences" class="badge-ghost badge-sm hover:badge-primary cursor-pointer" />
                    </a>
                    <a href="{{ route('guardian.grades', ['student' => $student->uuid]) }}" wire:navigate>
                        <x-badge value="Notes" class="badge-ghost badge-sm hover:badge-success cursor-pointer" />
                    </a>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-base-content/40">
                <x-icon name="o-users" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm">Aucun enfant associé à ce compte</p>
            </div>
            @endforelse
            <div class="pt-3">
                <a href="{{ route('guardian.children') }}" wire:navigate>
                    <x-button label="Voir tous" icon="o-arrow-right" class="btn-sm btn-ghost w-full" />
                </a>
            </div>
        </x-card>

        {{-- Recent announcements --}}
        <x-card title="{{ __('navigation.announcements') }}" shadow separator>
            @forelse($recentAnnouncements as $ann)
            <div class="py-2 border-b border-base-100 last:border-0">
                <p class="font-medium text-sm">{{ $ann->title }}</p>
                <p class="text-xs text-base-content/50 mt-0.5">{{ $ann->published_at?->diffForHumans() }}</p>
            </div>
            @empty
            <div class="text-center py-8 text-base-content/40">
                <x-icon name="o-megaphone" class="w-10 h-10 mx-auto mb-2" />
                <p class="text-sm">Aucune annonce</p>
            </div>
            @endforelse
            <div class="pt-3">
                <a href="{{ route('guardian.announcements') }}" wire:navigate>
                    <x-button label="Toutes les annonces" icon="o-arrow-right" class="btn-sm btn-ghost w-full" />
                </a>
            </div>
        </x-card>
    </div>

    {{-- Quick links --}}
    @if($unpaidInvoices > 0)
    <x-alert icon="o-exclamation-triangle" class="alert-warning">
        <div class="flex items-center justify-between w-full">
            <span>Vous avez <strong>{{ $unpaidInvoices }}</strong> facture(s) impayée(s).</span>
            <a href="{{ route('guardian.invoices') }}" wire:navigate>
                <x-button label="Voir les factures" class="btn-sm btn-warning" />
            </a>
        </div>
    </x-alert>
    @endif
</div>
