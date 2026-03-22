<?php
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Enums\InvoiceStatus;
use App\Enums\EnrollmentStatus;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    private function schoolId(): int
    {
        return auth()->user()->school_id;
    }

    public function with(): array
    {
        $sid = $this->schoolId();

        $currentYear = AcademicYear::where('school_id', $sid)->where('is_current', true)->first();

        $totalStudents     = Student::where('school_id', $sid)->where('is_active', true)->count();
        $totalTeachers     = Teacher::where('school_id', $sid)->where('is_active', true)->count();
        $totalEnrollments  = $currentYear
            ? Enrollment::where('school_id', $sid)->where('academic_year_id', $currentYear->id)->where('status', EnrollmentStatus::CONFIRMED)->count()
            : 0;

        $invoiceBase       = Invoice::where('school_id', $sid);
        $totalRevenue      = (clone $invoiceBase)->sum('total');
        $totalCollected    = (clone $invoiceBase)->sum('paid_total');
        $totalBalance      = $totalRevenue - $totalCollected;
        $overdueCount      = (clone $invoiceBase)->where('status', InvoiceStatus::OVERDUE)->count();

        $recentPayments = Payment::where('school_id', $sid)
            ->with('student')
            ->orderByDesc('payment_date')
            ->limit(5)
            ->get();

        $recentAnnouncements = Announcement::where('school_id', $sid)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // Monthly revenue for chart (last 6 months)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyRevenue[] = [
                'month'     => $month->format('M Y'),
                'collected' => Payment::where('school_id', $sid)
                    ->whereYear('payment_date', $month->year)
                    ->whereMonth('payment_date', $month->month)
                    ->sum('amount'),
            ];
        }

        return [
            'currentYear'         => $currentYear,
            'totalStudents'       => $totalStudents,
            'totalTeachers'       => $totalTeachers,
            'totalEnrollments'    => $totalEnrollments,
            'totalRevenue'        => $totalRevenue,
            'totalCollected'      => $totalCollected,
            'totalBalance'        => $totalBalance,
            'overdueCount'        => $overdueCount,
            'recentPayments'      => $recentPayments,
            'recentAnnouncements' => $recentAnnouncements,
            'monthlyRevenue'      => $monthlyRevenue,
        ];
    }
};
?>

<div class="p-4">

    {{-- Header --}}
    <x-header :title="__('navigation.dashboard')" separator>
        <x-slot:actions>
            @if($currentYear)
            <x-badge value="{{ $currentYear->name }}" class="badge-primary badge-outline" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            [
                'label' => __('navigation.students'),
                'value' => number_format($totalStudents),
                'icon'  => 'o-user-group',
                'grad'  => 'from-blue-500 to-blue-600',
                'link'  => route('admin.students.index'),
            ],
            [
                'label' => __('navigation.teachers'),
                'value' => number_format($totalTeachers),
                'icon'  => 'o-briefcase',
                'grad'  => 'from-purple-500 to-purple-600',
                'link'  => route('admin.teachers.index'),
            ],
            [
                'label' => __('navigation.enrollments'),
                'value' => number_format($totalEnrollments),
                'icon'  => 'o-clipboard-document-check',
                'grad'  => 'from-green-500 to-green-600',
                'link'  => route('admin.enrollments.index'),
            ],
            [
                'label' => __('invoices.stats.balance'),
                'value' => number_format($totalBalance) . ' DJF',
                'icon'  => 'o-exclamation-triangle',
                'grad'  => 'from-amber-500 to-amber-600',
                'link'  => route('admin.finance.invoices.index'),
            ],
        ] as $kpi)
        <a href="{{ $kpi['link'] }}" wire:navigate
           class="relative p-5 overflow-hidden bg-base-100 shadow-lg rounded-2xl hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 block">
            <div class="absolute top-0 right-0 w-24 h-24 translate-x-1/2 -translate-y-1/2 rounded-full opacity-10 bg-gradient-to-br {{ $kpi['grad'] }}"></div>
            <div class="relative flex items-center gap-4">
                <div class="p-3 rounded-xl bg-gradient-to-br {{ $kpi['grad'] }} shadow">
                    <x-icon name="{{ $kpi['icon'] }}" class="w-6 h-6 text-white"/>
                </div>
                <div>
                    <p class="text-xs font-medium text-base-content/60">{{ $kpi['label'] }}</p>
                    <p class="text-xl font-black text-base-content">{{ $kpi['value'] }}</p>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Finance Summary --}}
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
        @foreach([
            ['label' => __('invoices.stats.total_amount'), 'value' => number_format($totalRevenue) . ' DJF',   'color' => 'text-purple-600'],
            ['label' => __('invoices.stats.paid_amount'),  'value' => number_format($totalCollected) . ' DJF', 'color' => 'text-green-600'],
            ['label' => __('invoices.filters.overdue_only'), 'value' => $overdueCount . ' factures',           'color' => 'text-red-600'],
        ] as $stat)
        <div class="p-5 bg-base-100 rounded-2xl shadow">
            <p class="text-xs text-base-content/60 mb-1">{{ $stat['label'] }}</p>
            <p class="text-2xl font-black {{ $stat['color'] }}">{{ $stat['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Recent Payments --}}
        <x-card :title="__('navigation.payments')" separator>
            @forelse($recentPayments as $payment)
            <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0">
                <div>
                    <p class="font-semibold text-sm">{{ $payment->student->full_name ?? '—' }}</p>
                    <p class="text-xs text-base-content/60">{{ $payment->payment_date->format('d/m/Y') }} — {{ $payment->reference }}</p>
                </div>
                <span class="font-bold text-green-600">{{ number_format($payment->amount) }} DJF</span>
            </div>
            @empty
            <x-alert icon="o-information-circle" class="alert-info">
                {{ __('Aucun paiement récent.') }}
            </x-alert>
            @endforelse
        </x-card>

        {{-- Announcements --}}
        <x-card :title="__('navigation.announcements')" separator>
            @forelse($recentAnnouncements as $ann)
            <div class="py-2 border-b border-base-200 last:border-0">
                <div class="flex items-center gap-2 mb-1">
                    <x-badge
                        :value="$ann->level->label()"
                        :class="'badge-' . $ann->level->color()"
                    />
                    @if($ann->is_pinned)
                    <x-icon name="o-map-pin" class="w-3 h-3 text-base-content/40" />
                    @endif
                </div>
                <p class="font-semibold text-sm">{{ $ann->title }}</p>
                <p class="text-xs text-base-content/60 line-clamp-1">{{ $ann->body }}</p>
            </div>
            @empty
            <x-alert icon="o-information-circle" class="alert-info">
                {{ __('Aucune annonce.') }}
            </x-alert>
            @endforelse
        </x-card>
    </div>

</div>
