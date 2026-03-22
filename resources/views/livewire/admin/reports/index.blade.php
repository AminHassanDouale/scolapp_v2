<?php
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\AttendanceEntry;
use App\Models\AttendanceSession;
use App\Models\Assessment;
use App\Models\StudentScore;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\Subject;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\GenderType;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {

    public int    $yearFilter   = 0;
    public string $reportType   = 'finance';
    public int    $classFilter  = 0;
    public int    $gradeFilter  = 0;
    public string $methodFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public string $periodFilter = '';
    public string $statusFilter = '';
    public string $genderFilter = '';

    public function mount(): void
    {
        $current = AcademicYear::where('school_id', auth()->user()->school_id)
            ->where('is_current', true)->first();
        if ($current) {
            $this->yearFilter = $current->id;
            $this->dateFrom   = $current->start_date?->format('Y-m-d') ?? '';
            $this->dateTo     = $current->end_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        }
    }

    public function updatedReportType(): void
    {
        $this->classFilter  = 0;
        $this->gradeFilter  = 0;
        $this->methodFilter = '';
        $this->periodFilter = '';
        $this->statusFilter = '';
        $this->genderFilter = '';
    }

    public function with(): array
    {
        $schoolId    = auth()->user()->school_id;
        $academicYears = AcademicYear::where('school_id', $schoolId)->orderByDesc('start_date')->get();
        $currentYear   = $this->yearFilter ? AcademicYear::find($this->yearFilter) : null;
        $classes       = SchoolClass::where('school_id', $schoolId)
            ->when($this->yearFilter, fn($q) => $q->where('academic_year_id', $this->yearFilter))
            ->with('grade')->orderBy('name')->get();
        $grades        = Grade::where('school_id', $schoolId)->orderBy('name')->get();
        $subjects      = Subject::where('school_id', $schoolId)->orderBy('name')->get();

        $data = [];

        // ── FINANCE ──────────────────────────────────────────────────────────
        if ($this->reportType === 'finance') {

            // Base payment query
            $payBase = Payment::where('school_id', $schoolId)
                ->where('status', PaymentStatus::CONFIRMED->value)
                ->when($this->yearFilter, function($q) {
                    $q->whereHas('invoices', fn($iq) => $iq->where('academic_year_id', $this->yearFilter));
                })
                ->when($this->methodFilter, fn($q) => $q->where('payment_method', $this->methodFilter))
                ->when($this->dateFrom,     fn($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
                ->when($this->dateTo,       fn($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
                ->when($this->classFilter,  fn($q) => $q->where('enrollment_id', function($sub) {
                    $sub->select('id')->from('enrollments')
                        ->where('school_class_id', $this->classFilter);
                }));

            $totalRevenue = (clone $payBase)->sum('amount');

            // Invoice base
            $invBase = Invoice::where('school_id', $schoolId)
                ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter,  fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $this->classFilter)));

            $totalBilled   = (clone $invBase)->sum('total');
            $totalPaid     = (clone $invBase)->sum('paid_total');
            $totalPending  = (clone $invBase)->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])->sum('balance_due');
            $totalOverdue  = (clone $invBase)->overdue()->sum('balance_due');
            $collectionRate = $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 1) : 0;

            // Monthly revenue (last 12 months)
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $total = Payment::where('school_id', $schoolId)
                    ->where('status', PaymentStatus::CONFIRMED->value)
                    ->when($this->methodFilter, fn($q) => $q->where('payment_method', $this->methodFilter))
                    ->whereYear('payment_date',  $month->year)
                    ->whereMonth('payment_date', $month->month)
                    ->sum('amount');
                $months[] = ['label' => $month->format('M Y'), 'total' => (float)$total];
            }
            $maxMonthly = max(array_column($months, 'total')) ?: 1;

            // Payment method breakdown
            $methodBreakdown = Payment::where('school_id', $schoolId)
                ->where('status', PaymentStatus::CONFIRMED->value)
                ->when($this->yearFilter, function($q) {
                    $q->whereHas('invoices', fn($iq) => $iq->where('academic_year_id', $this->yearFilter));
                })
                ->when($this->dateFrom, fn($q) => $q->whereDate('payment_date', '>=', $this->dateFrom))
                ->when($this->dateTo,   fn($q) => $q->whereDate('payment_date', '<=', $this->dateTo))
                ->selectRaw('payment_method, COUNT(*) as cnt, SUM(amount) as total')
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->get();
            $methodTotal = $methodBreakdown->sum('total') ?: 1;

            // Invoice status breakdown
            $invoiceStats = Invoice::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $this->classFilter)))
                ->selectRaw('status, COUNT(*) as cnt, SUM(total) as total, SUM(balance_due) as balance')
                ->groupBy('status')
                ->get();

            // Top 10 unpaid students
            $topUnpaid = Invoice::where('school_id', $schoolId)
                ->where('balance_due', '>', 0)
                ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter, fn($q) => $q->whereHas('enrollment', fn($e) => $e->where('school_class_id', $this->classFilter)))
                ->with('student')
                ->selectRaw('student_id, SUM(balance_due) as total_due')
                ->groupBy('student_id')
                ->orderByDesc('total_due')
                ->limit(10)
                ->get();

            // Recent payments
            $recentPayments = Payment::where('school_id', $schoolId)
                ->where('status', PaymentStatus::CONFIRMED->value)
                ->with('student')
                ->when($this->yearFilter, function($q) {
                    $q->whereHas('invoices', fn($iq) => $iq->where('academic_year_id', $this->yearFilter));
                })
                ->when($this->methodFilter, fn($q) => $q->where('payment_method', $this->methodFilter))
                ->orderByDesc('payment_date')
                ->limit(8)
                ->get();

            $data = compact(
                'totalRevenue','totalBilled','totalPaid','totalPending','totalOverdue',
                'collectionRate','months','maxMonthly','methodBreakdown','methodTotal',
                'invoiceStats','topUnpaid','recentPayments'
            );
        }

        // ── ENROLLMENT ────────────────────────────────────────────────────────
        elseif ($this->reportType === 'enrollment') {

            $enrBase = Enrollment::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->gradeFilter, fn($q) => $q->where('grade_id', $this->gradeFilter))
                ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
                ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter));

            $totalEnrollments = (clone $enrBase)->count();
            $confirmedCount   = (clone $enrBase)->where('status', EnrollmentStatus::CONFIRMED->value)->count();
            $holdCount        = (clone $enrBase)->where('status', EnrollmentStatus::HOLD->value)->count();
            $cancelledCount   = (clone $enrBase)->where('status', EnrollmentStatus::CANCELLED->value)->count();

            // By class
            $byClass = SchoolClass::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->gradeFilter, fn($q) => $q->where('grade_id', $this->gradeFilter))
                ->when($this->classFilter, fn($q) => $q->where('id', $this->classFilter))
                ->with('grade')
                ->withCount([
                    'enrollments as total_count'  => fn($q) => $q
                        ->when($this->statusFilter, fn($s) => $s->where('status', $this->statusFilter))
                        ->when(!$this->statusFilter, fn($s) => $s->where('status', EnrollmentStatus::CONFIRMED->value)),
                    'enrollments as male_count'   => fn($q) => $q->where('status', EnrollmentStatus::CONFIRMED->value)
                        ->whereHas('student', fn($s) => $s->where('gender', GenderType::MALE->value)),
                    'enrollments as female_count' => fn($q) => $q->where('status', EnrollmentStatus::CONFIRMED->value)
                        ->whereHas('student', fn($s) => $s->where('gender', GenderType::FEMALE->value)),
                ])
                ->orderBy('name')
                ->get();

            // By grade (using subquery count since Grade has no direct enrollments relation)
            $yearFilter   = $this->yearFilter;
            $statusFilter = $this->statusFilter;
            $byGrade = Grade::where('school_id', $schoolId)
                ->when($this->gradeFilter, fn($q) => $q->where('id', $this->gradeFilter))
                ->addSelect(['count' => Enrollment::selectRaw('COUNT(*)')
                    ->whereColumn('grade_id', 'grades.id')
                    ->when($yearFilter,   fn($s) => $s->where('academic_year_id', $yearFilter))
                    ->when($statusFilter, fn($s) => $s->where('status', $statusFilter))
                    ->when(!$statusFilter, fn($s) => $s->where('status', EnrollmentStatus::CONFIRMED->value)),
                ])
                ->orderByDesc('count')
                ->get();

            // Monthly new enrollments
            $monthlyEnrollments = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $cnt = Enrollment::where('school_id', $schoolId)
                    ->when($this->yearFilter, fn($q) => $q->where('academic_year_id', $this->yearFilter))
                    ->whereYear('enrolled_at',  $month->year)
                    ->whereMonth('enrolled_at', $month->month)
                    ->count();
                $monthlyEnrollments[] = ['label' => $month->format('M Y'), 'count' => $cnt];
            }
            $maxEnrMonthly = max(array_column($monthlyEnrollments, 'count')) ?: 1;

            $totalMale   = $byClass->sum('male_count');
            $totalFemale = $byClass->sum('female_count');
            $totalAll    = $byClass->sum('total_count');

            $data = compact(
                'totalEnrollments','confirmedCount','holdCount','cancelledCount',
                'byClass','byGrade','monthlyEnrollments','maxEnrMonthly',
                'totalMale','totalFemale','totalAll'
            );
        }

        // ── ATTENDANCE ────────────────────────────────────────────────────────
        elseif ($this->reportType === 'attendance') {

            $sessionIds = AttendanceSession::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
                ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter))
                ->when($this->dateFrom, fn($q) => $q->whereDate('session_date', '>=', $this->dateFrom))
                ->when($this->dateTo,   fn($q) => $q->whereDate('session_date', '<=', $this->dateTo))
                ->pluck('id');

            $totalSessions  = AttendanceSession::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
                ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter))
                ->when($this->dateFrom, fn($q) => $q->whereDate('session_date', '>=', $this->dateFrom))
                ->when($this->dateTo,   fn($q) => $q->whereDate('session_date', '<=', $this->dateTo))
                ->count();

            $totalEntries   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->count();
            $presentCount   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->where('status', AttendanceStatus::PRESENT->value)->count();
            $absentCount    = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->where('status', AttendanceStatus::ABSENT->value)->count();
            $lateCount      = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->where('status', AttendanceStatus::LATE->value)->count();
            $excusedCount   = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)->where('status', AttendanceStatus::EXCUSED->value)->count();
            $overallRate    = $totalEntries > 0 ? round(($presentCount / $totalEntries) * 100, 1) : 0;

            // Top 10 absentees
            $topAbsentees = AttendanceEntry::whereIn('attendance_session_id', $sessionIds)
                ->whereIn('status', [AttendanceStatus::ABSENT->value, AttendanceStatus::LATE->value])
                ->select('student_id', DB::raw('COUNT(*) as absence_count'))
                ->groupBy('student_id')
                ->orderByDesc('absence_count')
                ->limit(10)
                ->with('student')
                ->get();

            // By class
            $classesByAttendance = SchoolClass::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter, fn($q) => $q->where('id', $this->classFilter))
                ->with('grade')
                ->get()
                ->map(function ($class) use ($schoolId) {
                    $sIds = AttendanceSession::where('school_id', $schoolId)
                        ->where('school_class_id', $class->id)
                        ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
                        ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter))
                        ->when($this->dateFrom, fn($q) => $q->whereDate('session_date', '>=', $this->dateFrom))
                        ->when($this->dateTo,   fn($q) => $q->whereDate('session_date', '<=', $this->dateTo))
                        ->pluck('id');
                    $total   = AttendanceEntry::whereIn('attendance_session_id', $sIds)->count();
                    $present = AttendanceEntry::whereIn('attendance_session_id', $sIds)->where('status', AttendanceStatus::PRESENT->value)->count();
                    $absent  = AttendanceEntry::whereIn('attendance_session_id', $sIds)->where('status', AttendanceStatus::ABSENT->value)->count();
                    $rate    = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                    return ['class' => $class, 'total' => $total, 'present' => $present, 'absent' => $absent, 'rate' => $rate];
                })
                ->filter(fn($r) => $r['total'] > 0)
                ->sortByDesc('rate');

            // Monthly attendance rate
            $monthlyAttendance = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $mSIds = AttendanceSession::where('school_id', $schoolId)
                    ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                    ->when($this->classFilter, fn($q) => $q->where('school_class_id', $this->classFilter))
                    ->whereYear('session_date',  $month->year)
                    ->whereMonth('session_date', $month->month)
                    ->pluck('id');
                $mTotal   = AttendanceEntry::whereIn('attendance_session_id', $mSIds)->count();
                $mPresent = AttendanceEntry::whereIn('attendance_session_id', $mSIds)->where('status', AttendanceStatus::PRESENT->value)->count();
                $mRate    = $mTotal > 0 ? round(($mPresent / $mTotal) * 100, 1) : 0;
                $monthlyAttendance[] = ['label' => $month->format('M Y'), 'rate' => $mRate, 'total' => $mTotal];
            }

            $data = compact(
                'totalSessions','totalEntries','presentCount','absentCount','lateCount','excusedCount',
                'overallRate','topAbsentees','classesByAttendance','monthlyAttendance'
            );
        }

        // ── ACADEMIC (NOTES) ──────────────────────────────────────────────────
        elseif ($this->reportType === 'academic') {

            $assessBase = Assessment::where('school_id', $schoolId)
                ->where('is_published', true)
                ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter,  fn($q) => $q->where('school_class_id', $this->classFilter))
                ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter));

            $assessIds = (clone $assessBase)->pluck('id');

            $totalAssessments = $assessIds->count();
            $totalScores      = StudentScore::whereIn('assessment_id', $assessIds)->where('is_absent', false)->count();

            // Average per subject
            $avgBySubject = Subject::where('school_id', $schoolId)
                ->get()
                ->map(function ($subject) use ($schoolId) {
                    $aIds = Assessment::where('school_id', $schoolId)
                        ->where('subject_id', $subject->id)
                        ->where('is_published', true)
                        ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
                        ->when($this->classFilter,  fn($q) => $q->where('school_class_id', $this->classFilter))
                        ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter))
                        ->pluck('id');
                    if ($aIds->isEmpty()) return null;
                    $scores = StudentScore::whereIn('assessment_id', $aIds)->where('is_absent', false);
                    $avgPct = $scores->count() > 0
                        ? round(DB::table('student_scores')
                            ->join('assessments', 'student_scores.assessment_id', '=', 'assessments.id')
                            ->whereIn('student_scores.assessment_id', $aIds)
                            ->where('student_scores.is_absent', false)
                            ->selectRaw('AVG(student_scores.score / assessments.max_score * 100) as avg_pct')
                            ->value('avg_pct') ?? 0, 1)
                        : 0;
                    return ['subject' => $subject, 'avg_pct' => $avgPct, 'count' => $scores->count()];
                })
                ->filter()
                ->filter(fn($r) => $r['count'] > 0)
                ->sortByDesc('avg_pct')
                ->values();

            // Average per class
            $avgByClass = SchoolClass::where('school_id', $schoolId)
                ->when($this->yearFilter,  fn($q) => $q->where('academic_year_id', $this->yearFilter))
                ->when($this->classFilter, fn($q) => $q->where('id', $this->classFilter))
                ->with('grade')
                ->get()
                ->map(function ($class) use ($schoolId) {
                    $aIds = Assessment::where('school_id', $schoolId)
                        ->where('school_class_id', $class->id)
                        ->where('is_published', true)
                        ->when($this->yearFilter,   fn($q) => $q->where('academic_year_id', $this->yearFilter))
                        ->when($this->periodFilter, fn($q) => $q->where('period', $this->periodFilter))
                        ->pluck('id');
                    if ($aIds->isEmpty()) return null;
                    $avgPct = round(DB::table('student_scores')
                        ->join('assessments', 'student_scores.assessment_id', '=', 'assessments.id')
                        ->whereIn('student_scores.assessment_id', $aIds)
                        ->where('student_scores.is_absent', false)
                        ->selectRaw('AVG(student_scores.score / assessments.max_score * 100) as avg_pct')
                        ->value('avg_pct') ?? 0, 1);
                    $cnt = StudentScore::whereIn('assessment_id', $aIds)->where('is_absent', false)->count();
                    if ($cnt === 0) return null;
                    return ['class' => $class, 'avg_pct' => $avgPct, 'count' => $cnt];
                })
                ->filter()
                ->sortByDesc('avg_pct')
                ->values();

            // Score distribution buckets
            $buckets = ['< 25%' => 0, '25-49%' => 0, '50-64%' => 0, '65-79%' => 0, '80-100%' => 0];
            DB::table('student_scores')
                ->join('assessments', 'student_scores.assessment_id', '=', 'assessments.id')
                ->whereIn('student_scores.assessment_id', $assessIds)
                ->where('student_scores.is_absent', false)
                ->where('assessments.max_score', '>', 0)
                ->selectRaw('student_scores.score, assessments.max_score')
                ->get()
                ->each(function ($row) use (&$buckets) {
                    $pct = ($row->score / $row->max_score) * 100;
                    if      ($pct < 25)  $buckets['< 25%']++;
                    elseif  ($pct < 50)  $buckets['25-49%']++;
                    elseif  ($pct < 65)  $buckets['50-64%']++;
                    elseif  ($pct < 80)  $buckets['65-79%']++;
                    else                 $buckets['80-100%']++;
                });
            $maxBucket = max($buckets) ?: 1;

            $data = compact('totalAssessments','totalScores','avgBySubject','avgByClass','buckets','maxBucket');
        }

        // ── STUDENTS ──────────────────────────────────────────────────────────
        elseif ($this->reportType === 'students') {

            $stuBase = Student::where('school_id', $schoolId)
                ->when($this->genderFilter, fn($q) => $q->where('gender', $this->genderFilter));

            $totalStudents   = (clone $stuBase)->count();
            $activeStudents  = (clone $stuBase)->where('is_active', true)->count();
            $inactiveStudents= (clone $stuBase)->where('is_active', false)->count();
            $maleCount       = Student::where('school_id', $schoolId)->where('gender', GenderType::MALE->value)->count();
            $femaleCount     = Student::where('school_id', $schoolId)->where('gender', GenderType::FEMALE->value)->count();
            $disabledCount   = Student::where('school_id', $schoolId)->where('has_disability', true)->count();

            // New this academic year
            $newThisYear = $this->yearFilter
                ? Enrollment::where('school_id', $schoolId)
                    ->where('academic_year_id', $this->yearFilter)
                    ->where('status', EnrollmentStatus::CONFIRMED->value)
                    ->distinct('student_id')->count('student_id')
                : 0;

            // Age distribution
            $ageGroups = Student::where('school_id', $schoolId)
                ->whereNotNull('date_of_birth')
                ->get()
                ->groupBy(function ($s) {
                    $age = $s->date_of_birth->age;
                    if ($age < 6)       return '< 6 ans';
                    elseif ($age < 10)  return '6-9 ans';
                    elseif ($age < 13)  return '10-12 ans';
                    elseif ($age < 16)  return '13-15 ans';
                    elseif ($age < 19)  return '16-18 ans';
                    else                return '19+ ans';
                })
                ->map->count()
                ->sortKeys();

            // Monthly new registrations (last 12 months)
            $monthlyStudents = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $cnt = Student::where('school_id', $schoolId)
                    ->whereYear('created_at',  $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count();
                $monthlyStudents[] = ['label' => $month->format('M Y'), 'count' => $cnt];
            }
            $maxStuMonthly = max(array_column($monthlyStudents, 'count')) ?: 1;

            // Nationality distribution (top 8)
            $nationalities = Student::where('school_id', $schoolId)
                ->whereNotNull('nationality')
                ->selectRaw('nationality, COUNT(*) as cnt')
                ->groupBy('nationality')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $data = compact(
                'totalStudents','activeStudents','inactiveStudents','maleCount','femaleCount',
                'disabledCount','newThisYear','ageGroups','monthlyStudents','maxStuMonthly','nationalities'
            );
        }

        return [
            'academicYears' => $academicYears,
            'currentYear'   => $currentYear,
            'classes'       => $classes,
            'grades'        => $grades,
            'subjects'      => $subjects,
            'data'          => $data,
        ];
    }
};
?>

<div>
    <x-header title="Rapports & Statistiques" subtitle="Analyse détaillée de l'activité scolaire" separator progress-indicator />

    {{-- Tab selector --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        @foreach([
            ['finance',    'Finance',       'o-banknotes'],
            ['enrollment', 'Inscriptions',  'o-academic-cap'],
            ['attendance', 'Présences',     'o-clipboard-document-check'],
            ['academic',   'Notes',         'o-chart-bar'],
            ['students',   'Élèves',        'o-users'],
        ] as [$type,$label,$icon])
        <button wire:click="$set('reportType', '{{ $type }}')"
                class="btn btn-sm {{ $reportType === $type ? 'btn-primary' : 'btn-ghost' }} gap-1">
            <x-icon name="{{ $icon }}" class="w-4 h-4" />{{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Filters bar --}}
    <div class="bg-base-200/50 rounded-2xl p-4 mb-6 flex flex-wrap gap-3 items-end">
        {{-- Always: Academic Year --}}
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Année scolaire</label>
            <x-select wire:model.live="yearFilter"
                      :options="$academicYears" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0"
                      class="select-sm w-44" />
        </div>

        {{-- Finance: method + date range + class --}}
        @if($reportType === 'finance')
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Méthode</label>
            <select wire:model.live="methodFilter" class="select select-sm select-bordered w-40">
                <option value="">Toutes</option>
                <option value="cash">Espèces</option>
                <option value="bank_transfer">Virement</option>
                <option value="check">Chèque</option>
                <option value="mobile_money">Mobile Money</option>
            </select>
        </div>
        <x-datepicker label="Du" wire:model.live="dateFrom" icon="o-calendar"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]"
                      class="input-sm w-36" />
        <x-datepicker label="Au" wire:model.live="dateTo" icon="o-calendar"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]"
                      class="input-sm w-36" />
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Classe</label>
            <x-select wire:model.live="classFilter"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" class="select-sm w-40" />
        </div>
        @endif

        {{-- Enrollment: grade + class + status --}}
        @if($reportType === 'enrollment')
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Niveau</label>
            <x-select wire:model.live="gradeFilter"
                      :options="$grades" option-value="id" option-label="name"
                      placeholder="Tous" placeholder-value="0" class="select-sm w-36" />
        </div>
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Classe</label>
            <x-select wire:model.live="classFilter"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" class="select-sm w-40" />
        </div>
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Statut</label>
            <select wire:model.live="statusFilter" class="select select-sm select-bordered w-36">
                <option value="">Tous</option>
                <option value="confirmed">Confirmé</option>
                <option value="hold">En attente</option>
                <option value="cancelled">Annulé</option>
            </select>
        </div>
        @endif

        {{-- Attendance: class + period + date range --}}
        @if($reportType === 'attendance')
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Classe</label>
            <x-select wire:model.live="classFilter"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" class="select-sm w-40" />
        </div>
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Période</label>
            <select wire:model.live="periodFilter" class="select select-sm select-bordered w-40">
                <option value="">Toutes</option>
                <option value="trimester_1">Trimestre 1</option>
                <option value="trimester_2">Trimestre 2</option>
                <option value="trimester_3">Trimestre 3</option>
                <option value="semester_1">Semestre 1</option>
                <option value="semester_2">Semestre 2</option>
                <option value="annual">Annuel</option>
            </select>
        </div>
        <x-datepicker label="Du" wire:model.live="dateFrom" icon="o-calendar"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]"
                      class="input-sm w-36" />
        <x-datepicker label="Au" wire:model.live="dateTo" icon="o-calendar"
                      :config="['dateFormat'=>'Y-m-d','altFormat'=>'d/m/Y','altInput'=>true]"
                      class="input-sm w-36" />
        @endif

        {{-- Academic: class + period + grade --}}
        @if($reportType === 'academic')
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Niveau</label>
            <x-select wire:model.live="gradeFilter"
                      :options="$grades" option-value="id" option-label="name"
                      placeholder="Tous" placeholder-value="0" class="select-sm w-36" />
        </div>
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Classe</label>
            <x-select wire:model.live="classFilter"
                      :options="$classes" option-value="id" option-label="name"
                      placeholder="Toutes" placeholder-value="0" class="select-sm w-40" />
        </div>
        <div>
            <label class="label label-text text-xs font-bold uppercase tracking-wide pb-1">Période</label>
            <select wire:model.live="periodFilter" class="select select-sm select-bordered w-40">
                <option value="">Toutes</option>
                <option value="trimester_1">Trimestre 1</option>
                <option value="trimester_2">Trimestre 2</option>
                <option value="trimester_3">Trimestre 3</option>
                <option value="semester_1">Semestre 1</option>
                <option value="semester_2">Semestre 2</option>
                <option value="annual">Annuel</option>
            </select>
        </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         FINANCE
    ══════════════════════════════════════════════════════════════════════════ --}}
    @if($reportType === 'finance')
    <div class="space-y-6">

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/60 p-4 text-primary-content">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Revenus collectés</p>
                <p class="text-2xl font-black mt-1">{{ number_format((int)$data['totalRevenue'], 0, ',', ' ') }}</p>
                <p class="text-xs opacity-60">DJF</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-info to-info/60 p-4 text-info-content">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Facturé total</p>
                <p class="text-2xl font-black mt-1">{{ number_format((int)$data['totalBilled'], 0, ',', ' ') }}</p>
                <p class="text-xs opacity-60">DJF</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-warning to-warning/60 p-4 text-warning-content">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Solde en attente</p>
                <p class="text-2xl font-black mt-1">{{ number_format((int)$data['totalPending'], 0, ',', ' ') }}</p>
                <p class="text-xs opacity-60">DJF</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-error to-error/60 p-4 text-error-content">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">En retard</p>
                <p class="text-2xl font-black mt-1">{{ number_format((int)$data['totalOverdue'], 0, ',', ' ') }}</p>
                <p class="text-xs opacity-60">DJF</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-success to-success/60 p-4 text-success-content">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Taux recouvrement</p>
                <p class="text-2xl font-black mt-1">{{ $data['collectionRate'] }}%</p>
                <div class="w-full bg-white/20 rounded-full h-1.5 mt-1">
                    <div class="bg-white h-1.5 rounded-full" style="width:{{ $data['collectionRate'] }}%"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Monthly revenue chart --}}
            <x-card title="Revenus mensuels (12 mois)" class="lg:col-span-2" separator>
                <div class="flex items-end gap-1 h-44 mt-2">
                    @foreach($data['months'] as $month)
                    @php $height = $data['maxMonthly'] > 0 ? ($month['total'] / $data['maxMonthly']) * 100 : 0; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                        <div class="absolute bottom-6 hidden group-hover:flex bg-base-300 text-xs px-1.5 py-0.5 rounded shadow z-10 whitespace-nowrap">
                            {{ number_format((int)$month['total'], 0, ',', ' ') }} DJF
                        </div>
                        <div class="w-full bg-primary/80 hover:bg-primary rounded-t cursor-pointer transition-all"
                             style="height: {{ max(2, $height) }}%; min-height: {{ $month['total'] > 0 ? '6px' : '2px' }}"></div>
                        <span class="text-[9px] text-base-content/40 rotate-45 origin-left mt-1 whitespace-nowrap block">
                            {{ $month['label'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Payment method breakdown --}}
            <x-card title="Méthodes de paiement" separator>
                <div class="space-y-3 mt-2">
                    @forelse($data['methodBreakdown'] as $m)
                    @php
                        $pct = $data['methodTotal'] > 0 ? round(($m->total / $data['methodTotal']) * 100) : 0;
                        $label = match($m->payment_method) {
                            'cash'          => 'Espèces',
                            'bank_transfer' => 'Virement',
                            'check'         => 'Chèque',
                            'mobile_money'  => 'Mobile Money',
                            default         => $m->payment_method,
                        };
                        $color = match($m->payment_method) {
                            'cash'          => 'bg-success',
                            'bank_transfer' => 'bg-info',
                            'check'         => 'bg-warning',
                            'mobile_money'  => 'bg-primary',
                            default         => 'bg-base-300',
                        };
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-semibold">{{ $label }}</span>
                            <span class="text-base-content/60">{{ $pct }}% · {{ $m->cnt }} paiements</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="{{ $color }} h-2 rounded-full transition-all" style="width:{{ $pct }}%"></div>
                        </div>
                        <p class="text-xs text-base-content/50 text-right mt-0.5">{{ number_format((int)$m->total, 0, ',', ' ') }} DJF</p>
                    </div>
                    @empty
                    <p class="text-sm text-base-content/40 text-center py-4">Aucun paiement</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Invoice status --}}
            <x-card title="Statut des factures" separator>
                <div class="space-y-3 mt-2">
                    @php $totalInvCnt = $data['invoiceStats']->sum('cnt') ?: 1; @endphp
                    @foreach($data['invoiceStats'] as $stat)
                    @php
                        $pct = round(($stat->cnt / $totalInvCnt) * 100);
                        $color = match($stat->status) {
                            'paid'           => 'bg-success',
                            'partially_paid' => 'bg-warning',
                            'overdue'        => 'bg-error',
                            'issued'         => 'bg-info',
                            'cancelled'      => 'bg-base-300',
                            default          => 'bg-ghost',
                        };
                        $statusLabel = match($stat->status) {
                            'paid'           => 'Payée',
                            'partially_paid' => 'Partiellement payée',
                            'overdue'        => 'En retard',
                            'issued'         => 'Émise',
                            'cancelled'      => 'Annulée',
                            'draft'          => 'Brouillon',
                            default          => $stat->status,
                        };
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-semibold">{{ $statusLabel }}</span>
                            <span class="text-base-content/60">{{ $stat->cnt }} ({{ $pct }}%)</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="{{ $color }} h-2 rounded-full" style="width:{{ $pct }}%"></div>
                        </div>
                        <p class="text-xs text-base-content/50 text-right mt-0.5">{{ number_format((int)$stat->total, 0, ',', ' ') }} DJF</p>
                    </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Top 10 unpaid --}}
            <x-card title="Top 10 — Soldes impayés" separator>
                <div class="space-y-1 mt-2">
                    @forelse($data['topUnpaid'] as $i => $inv)
                    <div class="flex items-center justify-between py-1.5 border-b border-base-200 last:border-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-base-content/30 w-5">{{ $i+1 }}</span>
                            <a href="{{ route('admin.students.show', $inv->student?->uuid) }}"
                               wire:navigate class="text-sm font-semibold hover:text-primary truncate max-w-[180px]">
                                {{ $inv->student?->full_name }}
                            </a>
                        </div>
                        <span class="font-bold text-error shrink-0 text-sm">
                            {{ number_format((int)$inv->total_due, 0, ',', ' ') }} DJF
                        </span>
                    </div>
                    @empty
                    <p class="text-sm text-base-content/40 text-center py-6">Aucun impayé</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- Recent payments --}}
        <x-card title="Paiements récents" separator>
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead><tr>
                        <th>Date</th><th>Élève</th><th>Méthode</th><th class="text-right">Montant</th>
                    </tr></thead>
                    <tbody>
                    @forelse($data['recentPayments'] as $pay)
                    <tr class="hover">
                        <td class="text-sm text-base-content/60">{{ $pay->payment_date?->format('d/m/Y') }}</td>
                        <td class="font-semibold text-sm">{{ $pay->student?->full_name ?? '—' }}</td>
                        <td class="text-sm">{{ match($pay->payment_method) {
                            'cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque',
                            'mobile_money' => 'Mobile Money', default => $pay->payment_method ?? '—'
                        } }}</td>
                        <td class="text-right font-bold text-success">{{ number_format((int)$pay->amount, 0, ',', ' ') }} DJF</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-base-content/40 py-4">Aucun paiement</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         ENROLLMENT
    ══════════════════════════════════════════════════════════════════════════ --}}
    @elseif($reportType === 'enrollment')
    <div class="space-y-6">

        {{-- KPI --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/60 p-4 text-primary-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Total inscriptions</p>
                <p class="text-3xl font-black mt-1">{{ $data['totalEnrollments'] }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-success to-success/60 p-4 text-success-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Confirmées</p>
                <p class="text-3xl font-black mt-1">{{ $data['confirmedCount'] }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-warning to-warning/60 p-4 text-warning-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">En attente</p>
                <p class="text-3xl font-black mt-1">{{ $data['holdCount'] }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-error to-error/60 p-4 text-error-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Annulées</p>
                <p class="text-3xl font-black mt-1">{{ $data['cancelledCount'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Monthly trend --}}
            <x-card title="Nouvelles inscriptions (12 mois)" class="lg:col-span-2" separator>
                <div class="flex items-end gap-1 h-40 mt-2">
                    @foreach($data['monthlyEnrollments'] as $m)
                    @php $h = $data['maxEnrMonthly'] > 0 ? ($m['count'] / $data['maxEnrMonthly']) * 100 : 0; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                        <div class="absolute bottom-6 hidden group-hover:block bg-base-300 text-xs px-1.5 py-0.5 rounded shadow z-10">
                            {{ $m['count'] }}
                        </div>
                        <div class="w-full bg-info/80 hover:bg-info rounded-t transition-all"
                             style="height:{{ max(2,$h) }}%; min-height:{{ $m['count']>0?'6px':'2px' }}"></div>
                        <span class="text-[9px] text-base-content/40 rotate-45 origin-left mt-1 whitespace-nowrap block">{{ $m['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </x-card>

            {{-- By grade --}}
            <x-card title="Par niveau" separator>
                <div class="space-y-2 mt-2">
                    @php $maxGrade = $data['byGrade']->max('count') ?: 1; @endphp
                    @foreach($data['byGrade'] as $g)
                    <div>
                        <div class="flex justify-between text-sm mb-0.5">
                            <span class="font-semibold truncate">{{ $g->name }}</span>
                            <span class="text-base-content/60 shrink-0 ml-2">{{ $g->count }}</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="bg-primary h-2 rounded-full" style="width:{{ round(($g->count/$maxGrade)*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- By class table --}}
        <x-card title="Effectifs par classe" separator>
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead><tr>
                        <th>Classe</th><th>Niveau</th>
                        <th class="text-center">Effectif</th>
                        <th class="text-center">Garçons</th>
                        <th class="text-center">Filles</th>
                        <th>Capacité</th>
                    </tr></thead>
                    <tbody>
                    @foreach($data['byClass'] as $class)
                    @php
                        $cap  = $class->capacity ?? 0;
                        $fill = $cap > 0 ? min(100, round(($class->total_count / $cap) * 100)) : 0;
                        $fillColor = $fill >= 90 ? 'progress-error' : ($fill >= 70 ? 'progress-warning' : 'progress-primary');
                    @endphp
                    <tr class="hover">
                        <td class="font-semibold">{{ $class->name }}</td>
                        <td class="text-sm text-base-content/60">{{ $class->grade?->name }}</td>
                        <td class="text-center font-bold text-lg">{{ $class->total_count }}</td>
                        <td class="text-center text-info font-semibold">{{ $class->male_count }}</td>
                        <td class="text-center text-secondary font-semibold">{{ $class->female_count }}</td>
                        <td>
                            @if($cap > 0)
                            <div class="flex items-center gap-2">
                                <progress class="progress {{ $fillColor }} w-20 h-2"
                                          value="{{ $class->total_count }}" max="{{ $cap }}"></progress>
                                <span class="text-xs text-base-content/60">{{ $class->total_count }}/{{ $cap }} ({{ $fill }}%)</span>
                            </div>
                            @else
                            <span class="text-xs text-base-content/30">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr>
                        <td colspan="2" class="font-bold">Total</td>
                        <td class="text-center font-black text-lg">{{ $data['totalAll'] }}</td>
                        <td class="text-center font-bold text-info">{{ $data['totalMale'] }}</td>
                        <td class="text-center font-bold text-secondary">{{ $data['totalFemale'] }}</td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         ATTENDANCE
    ══════════════════════════════════════════════════════════════════════════ --}}
    @elseif($reportType === 'attendance')
    <div class="space-y-6">

        {{-- KPI --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-success to-success/60 p-4 text-success-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Taux présence</p>
                <p class="text-3xl font-black mt-1">{{ $data['overallRate'] }}%</p>
            </div>
            <div class="rounded-2xl bg-base-200 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Séances</p>
                <p class="text-3xl font-black mt-1">{{ number_format($data['totalSessions']) }}</p>
            </div>
            <div class="rounded-2xl bg-success/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Présences</p>
                <p class="text-3xl font-black mt-1 text-success">{{ number_format($data['presentCount']) }}</p>
            </div>
            <div class="rounded-2xl bg-error/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Absences</p>
                <p class="text-3xl font-black mt-1 text-error">{{ number_format($data['absentCount']) }}</p>
            </div>
            <div class="rounded-2xl bg-warning/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Retards</p>
                <p class="text-3xl font-black mt-1 text-warning">{{ number_format($data['lateCount']) }}</p>
            </div>
            <div class="rounded-2xl bg-info/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Excusées</p>
                <p class="text-3xl font-black mt-1 text-info">{{ number_format($data['excusedCount']) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Monthly attendance rate (last 6 months) --}}
            <x-card title="Taux de présence mensuel (6 mois)" separator>
                <div class="space-y-3 mt-2">
                    @foreach($data['monthlyAttendance'] as $m)
                    @php $rateColor = $m['rate'] >= 80 ? 'bg-success' : ($m['rate'] >= 60 ? 'bg-warning' : 'bg-error'); @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-semibold">{{ $m['label'] }}</span>
                            <span class="{{ $m['rate'] >= 80 ? 'text-success' : ($m['rate'] >= 60 ? 'text-warning' : 'text-error') }} font-bold">
                                {{ $m['rate'] }}%
                                @if($m['total'] > 0)<span class="text-base-content/40 font-normal text-xs">({{ $m['total'] }} entrées)</span>@endif
                            </span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2.5">
                            <div class="{{ $rateColor }} h-2.5 rounded-full transition-all" style="width:{{ $m['rate'] }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Top absentees --}}
            <x-card title="Top 10 — Élèves les plus absents" separator>
                <div class="space-y-1 mt-2">
                    @forelse($data['topAbsentees'] as $i => $entry)
                    <div class="flex items-center justify-between py-1.5 border-b border-base-200 last:border-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-base-content/30 w-5">{{ $i+1 }}</span>
                            <a href="{{ route('admin.students.show', $entry->student?->uuid) }}"
                               wire:navigate class="text-sm font-semibold hover:text-primary truncate max-w-[180px]">
                                {{ $entry->student?->full_name ?? '—' }}
                            </a>
                        </div>
                        <x-badge value="{{ $entry->absence_count }} abs." class="badge-error badge-sm" />
                    </div>
                    @empty
                    <p class="text-sm text-base-content/40 text-center py-6">Aucune absence enregistrée</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- By class comparison --}}
        <x-card title="Présence par classe" separator>
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead><tr>
                        <th>Classe</th><th>Niveau</th>
                        <th class="text-center">Total entrées</th>
                        <th class="text-center">Présents</th>
                        <th class="text-center">Absents</th>
                        <th>Taux de présence</th>
                    </tr></thead>
                    <tbody>
                    @forelse($data['classesByAttendance'] as $item)
                    @php
                        $rColor = $item['rate'] >= 80 ? 'progress-success' : ($item['rate'] >= 60 ? 'progress-warning' : 'progress-error');
                        $tColor = $item['rate'] >= 80 ? 'text-success' : ($item['rate'] >= 60 ? 'text-warning' : 'text-error');
                    @endphp
                    <tr class="hover">
                        <td class="font-semibold">{{ $item['class']->name }}</td>
                        <td class="text-sm text-base-content/60">{{ $item['class']->grade?->name }}</td>
                        <td class="text-center">{{ $item['total'] }}</td>
                        <td class="text-center text-success font-semibold">{{ $item['present'] }}</td>
                        <td class="text-center text-error font-semibold">{{ $item['absent'] }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <progress class="progress {{ $rColor }} w-20 h-2"
                                          value="{{ $item['rate'] }}" max="100"></progress>
                                <span class="text-sm font-bold {{ $tColor }}">{{ $item['rate'] }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-base-content/40 py-4">Aucune donnée de présence</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         ACADEMIC (NOTES)
    ══════════════════════════════════════════════════════════════════════════ --}}
    @elseif($reportType === 'academic')
    <div class="space-y-6">

        {{-- KPI --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/60 p-4 text-primary-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Évaluations publiées</p>
                <p class="text-3xl font-black mt-1">{{ $data['totalAssessments'] }}</p>
            </div>
            <div class="rounded-2xl bg-base-200 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Notes saisies</p>
                <p class="text-3xl font-black mt-1">{{ number_format($data['totalScores']) }}</p>
            </div>
            @if($data['avgBySubject']->isNotEmpty())
            <div class="rounded-2xl bg-gradient-to-br from-success to-success/60 p-4 text-success-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Meilleure matière</p>
                <p class="text-lg font-black mt-1 truncate">{{ $data['avgBySubject']->first()['subject']->name }}</p>
                <p class="text-sm">{{ $data['avgBySubject']->first()['avg_pct'] }}%</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-warning to-warning/60 p-4 text-warning-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">À améliorer</p>
                <p class="text-lg font-black mt-1 truncate">{{ $data['avgBySubject']->last()['subject']->name }}</p>
                <p class="text-sm">{{ $data['avgBySubject']->last()['avg_pct'] }}%</p>
            </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Average by subject --}}
            <x-card title="Moyenne par matière" separator>
                <div class="space-y-3 mt-2">
                    @forelse($data['avgBySubject'] as $item)
                    @php
                        $pct = $item['avg_pct'];
                        $bColor = $pct >= 70 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-error');
                        $tColor = $pct >= 70 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-error');
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-semibold truncate">{{ $item['subject']->name }}</span>
                            <span class="{{ $tColor }} font-bold shrink-0 ml-2">{{ $pct }}%</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="{{ $bColor }} h-2 rounded-full transition-all" style="width:{{ $pct }}%"></div>
                        </div>
                        <p class="text-xs text-base-content/40 mt-0.5">{{ $item['count'] }} notes</p>
                    </div>
                    @empty
                    <p class="text-sm text-base-content/40 text-center py-6">Aucune donnée académique</p>
                    @endforelse
                </div>
            </x-card>

            {{-- Score distribution --}}
            <x-card title="Distribution des scores" separator>
                <div class="flex items-end gap-3 h-44 mt-4">
                    @php
                        $bucketColors = ['< 25%'=>'bg-error','25-49%'=>'bg-warning','50-64%'=>'bg-info','65-79%'=>'bg-primary','80-100%'=>'bg-success'];
                    @endphp
                    @foreach($data['buckets'] as $label => $count)
                    @php $h = $data['maxBucket'] > 0 ? ($count / $data['maxBucket']) * 100 : 0; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                        <span class="text-xs font-bold {{ $count > 0 ? '' : 'text-base-content/20' }}">{{ $count }}</span>
                        <div class="{{ $bucketColors[$label] ?? 'bg-base-300' }} w-full rounded-t transition-all opacity-80 hover:opacity-100"
                             style="height:{{ max(2,$h) }}%; min-height:{{ $count>0?'8px':'2px' }}"></div>
                        <span class="text-[10px] text-base-content/50 font-semibold">{{ $label }}</span>
                    </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- Average by class --}}
        @if($data['avgByClass']->isNotEmpty())
        <x-card title="Moyenne par classe" separator>
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead><tr>
                        <th>Classe</th><th>Niveau</th><th>Notes saisies</th><th>Moyenne</th><th>Performance</th>
                    </tr></thead>
                    <tbody>
                    @foreach($data['avgByClass'] as $item)
                    @php
                        $pct = $item['avg_pct'];
                        $pColor = $pct >= 70 ? 'progress-success' : ($pct >= 50 ? 'progress-warning' : 'progress-error');
                        $tColor = $pct >= 70 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-error');
                    @endphp
                    <tr class="hover">
                        <td class="font-semibold">{{ $item['class']->name }}</td>
                        <td class="text-sm text-base-content/60">{{ $item['class']->grade?->name }}</td>
                        <td>{{ number_format($item['count']) }}</td>
                        <td class="font-black text-lg {{ $tColor }}">{{ $pct }}%</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <progress class="progress {{ $pColor }} w-24 h-2" value="{{ $pct }}" max="100"></progress>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         STUDENTS
    ══════════════════════════════════════════════════════════════════════════ --}}
    @elseif($reportType === 'students')
    <div class="space-y-6">

        {{-- KPI --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-primary to-primary/60 p-4 text-primary-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Total élèves</p>
                <p class="text-3xl font-black mt-1">{{ $data['totalStudents'] }}</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-success to-success/60 p-4 text-success-content text-center">
                <p class="text-xs opacity-70 font-semibold uppercase tracking-wide">Actifs</p>
                <p class="text-3xl font-black mt-1">{{ $data['activeStudents'] }}</p>
            </div>
            <div class="rounded-2xl bg-base-200 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Inactifs</p>
                <p class="text-3xl font-black mt-1">{{ $data['inactiveStudents'] }}</p>
            </div>
            <div class="rounded-2xl bg-info/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Garçons</p>
                <p class="text-3xl font-black mt-1 text-info">{{ $data['maleCount'] }}</p>
            </div>
            <div class="rounded-2xl bg-secondary/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Filles</p>
                <p class="text-3xl font-black mt-1 text-secondary">{{ $data['femaleCount'] }}</p>
            </div>
            <div class="rounded-2xl bg-warning/10 p-4 text-center">
                <p class="text-xs text-base-content/60 font-semibold uppercase tracking-wide">Nouveaux (année)</p>
                <p class="text-3xl font-black mt-1 text-warning">{{ $data['newThisYear'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Monthly registrations --}}
            <x-card title="Inscriptions par mois (12 mois)" class="lg:col-span-2" separator>
                <div class="flex items-end gap-1 h-40 mt-2">
                    @foreach($data['monthlyStudents'] as $m)
                    @php $h = $data['maxStuMonthly'] > 0 ? ($m['count'] / $data['maxStuMonthly']) * 100 : 0; @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 group relative">
                        <div class="absolute bottom-6 hidden group-hover:block bg-base-300 text-xs px-1.5 py-0.5 rounded shadow z-10">
                            {{ $m['count'] }}
                        </div>
                        <div class="w-full bg-secondary/80 hover:bg-secondary rounded-t transition-all"
                             style="height:{{ max(2,$h) }}%; min-height:{{ $m['count']>0?'6px':'2px' }}"></div>
                        <span class="text-[9px] text-base-content/40 rotate-45 origin-left mt-1 whitespace-nowrap block">{{ $m['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Gender + disability --}}
            <x-card title="Répartition" separator>
                <div class="space-y-4 mt-2">
                    {{-- Gender bar --}}
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-base-content/50 mb-1">Genre</p>
                        @php
                            $total = $data['maleCount'] + $data['femaleCount'] ?: 1;
                            $malePct   = round(($data['maleCount']   / $total) * 100);
                            $femalePct = round(($data['femaleCount'] / $total) * 100);
                        @endphp
                        <div class="flex rounded-full overflow-hidden h-5 text-xs font-bold">
                            <div class="bg-info flex items-center justify-center text-info-content" style="width:{{ $malePct }}%">
                                @if($malePct > 15)Garçons {{ $malePct }}%@endif
                            </div>
                            <div class="bg-secondary flex items-center justify-center text-secondary-content" style="width:{{ $femalePct }}%">
                                @if($femalePct > 15)Filles {{ $femalePct }}%@endif
                            </div>
                        </div>
                        <div class="flex justify-between text-xs mt-1">
                            <span class="text-info font-semibold">♂ {{ $data['maleCount'] }}</span>
                            <span class="text-secondary font-semibold">♀ {{ $data['femaleCount'] }}</span>
                        </div>
                    </div>

                    {{-- Disability --}}
                    <div class="bg-base-200 rounded-xl p-3 flex justify-between items-center">
                        <span class="text-sm font-semibold">Élèves en situation de handicap</span>
                        <x-badge value="{{ $data['disabledCount'] }}" class="badge-warning" />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Age distribution --}}
            <x-card title="Distribution par âge" separator>
                <div class="space-y-2 mt-2">
                    @php $maxAge = max($data['ageGroups']->values()->toArray() ?: [1]); @endphp
                    @foreach($data['ageGroups'] as $group => $cnt)
                    <div>
                        <div class="flex justify-between text-sm mb-0.5">
                            <span class="font-semibold">{{ $group }}</span>
                            <span class="text-base-content/60">{{ $cnt }} élèves</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2.5">
                            <div class="bg-primary h-2.5 rounded-full" style="width:{{ round(($cnt/$maxAge)*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-card>

            {{-- Nationality distribution --}}
            <x-card title="Nationalités (top 8)" separator>
                <div class="space-y-2 mt-2">
                    @php $maxNat = $data['nationalities']->max('cnt') ?: 1; @endphp
                    @forelse($data['nationalities'] as $nat)
                    <div>
                        <div class="flex justify-between text-sm mb-0.5">
                            <span class="font-semibold capitalize">{{ $nat->nationality }}</span>
                            <span class="text-base-content/60">{{ $nat->cnt }}</span>
                        </div>
                        <div class="w-full bg-base-200 rounded-full h-2">
                            <div class="bg-accent h-2 rounded-full" style="width:{{ round(($nat->cnt/$maxNat)*100) }}%"></div>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-base-content/40 text-center py-4">Aucune nationalité renseignée</p>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
    @endif
</div>
