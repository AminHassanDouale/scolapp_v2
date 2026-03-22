<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Guardian;

new #[Layout('layouts.guardian')] class extends Component {
    public function with(): array
    {
        $guardian = Guardian::where('user_id', auth()->id())->with(['students.enrollments.schoolClass', 'students.enrollments.grade'])->first();
        $students = $guardian?->students ?? collect();
        return compact('students');
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <x-header title="{{ __('navigation.my_children') }}" subtitle="{{ __('navigation.children_details') }}" separator>
        <x-slot:actions>
            <x-button icon="o-arrow-left" link="{{ route('guardian.dashboard') }}" wire:navigate class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($students as $student)
        @php $enrollment = $student->enrollments->where('status', 'active')->first(); @endphp
        <x-card shadow class="border-0 overflow-hidden">
            <div class="h-3 w-full rounded-t-lg" style="background: linear-gradient(90deg, #059669, #10b981)"></div>
            <div class="p-5">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-100 flex items-center justify-center">
                        <span class="text-2xl font-black text-emerald-700">{{ substr($student->name, 0, 1) }}</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-black">{{ $student->full_name }}</h3>
                        <p class="text-sm text-base-content/60">{{ $student->reference }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-base-50 rounded-lg p-3">
                        <p class="text-xs text-base-content/50">Classe</p>
                        <p class="font-semibold text-sm">{{ $enrollment?->schoolClass?->name ?? '—' }}</p>
                    </div>
                    <div class="bg-base-50 rounded-lg p-3">
                        <p class="text-xs text-base-content/50">Niveau</p>
                        <p class="font-semibold text-sm">{{ $enrollment?->grade?->name ?? '—' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <a href="{{ route('guardian.attendance', ['student' => $student->uuid]) }}" wire:navigate>
                        <x-button label="Présences" icon="o-calendar-days" class="btn-xs btn-outline btn-primary w-full" />
                    </a>
                    <a href="{{ route('guardian.grades', ['student' => $student->uuid]) }}" wire:navigate>
                        <x-button label="Notes" icon="o-chart-bar" class="btn-xs btn-outline btn-success w-full" />
                    </a>
                    <a href="{{ route('guardian.invoices', ['student' => $student->uuid]) }}" wire:navigate>
                        <x-button label="Factures" icon="o-document-currency-dollar" class="btn-xs btn-outline btn-warning w-full" />
                    </a>
                </div>
            </div>
        </x-card>
        @empty
        <div class="col-span-2 text-center py-16 text-base-content/40">
            <x-icon name="o-users" class="w-14 h-14 mx-auto mb-3" />
            <p class="font-medium">Aucun enfant associé à ce compte</p>
        </div>
        @endforelse
    </div>
</div>
