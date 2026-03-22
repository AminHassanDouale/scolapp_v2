<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\FeeScheduleType;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateRegistrationInvoice(Enrollment $enrollment): Invoice
    {
        $feePlan = $enrollment->studentFeePlan()->with('feeSchedule.feeItems')->first();
        $school  = $enrollment->school;

        if (! $feePlan) {
            throw new \RuntimeException('No fee plan found for enrollment.');
        }

        // Sum registration fee items only
        $registrationItems = $feePlan->feeSchedule->feeItems->where('type', 'registration');
        $subtotal = $registrationItems->sum(fn($item) => $item->pivot->amount);

        // Apply discounts from student fee plan
        $subtotal = $this->applyDiscount($subtotal, $feePlan->discount_amount, $feePlan->discount_pct);

        $vatAmount = (int) round($subtotal * ($school->vat_rate / 100));
        $total     = $subtotal + $vatAmount;

        return $enrollment->invoices()->create([
            'reference'       => Invoice::generateReference(),
            'school_id'       => $enrollment->school_id,
            'student_id'      => $enrollment->student_id,
            'academic_year_id'=> $enrollment->academic_year_id,
            'fee_schedule_id' => $feePlan->fee_schedule_id,
            'invoice_type'    => InvoiceType::REGISTRATION,
            'schedule_type'   => $feePlan->feeSchedule->schedule_type,
            'status'          => InvoiceStatus::ISSUED,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(14)->toDateString(),
            'subtotal'        => $subtotal,
            'vat_rate'        => $school->vat_rate,
            'vat_amount'      => $vatAmount,
            'total'           => $total,
            'paid_total'      => 0,
            'balance_due'     => $total,
        ]);
    }

    public function generateTuitionInstallments(Enrollment $enrollment): array
    {
        $feePlan   = $enrollment->studentFeePlan()->with('feeSchedule.feeItems')->first();
        $schedule  = $feePlan->feeSchedule;
        $school    = $enrollment->school;
        $year      = $enrollment->academicYear;

        $tuitionItems = $schedule->feeItems->where('type', 'tuition');
        $tuitionTotal = $tuitionItems->sum(fn($item) => $item->pivot->amount);
        $tuitionTotal = $this->applyDiscount($tuitionTotal, $feePlan->discount_amount, $feePlan->discount_pct);

        $scheduleType  = $schedule->schedule_type;
        $installments  = $scheduleType->installments();
        $perInstallment = (int) floor($tuitionTotal / $installments);
        $remainder     = $tuitionTotal - ($perInstallment * $installments);

        $startDate = Carbon::parse($year->start_date);
        $invoices  = [];

        for ($i = 1; $i <= $installments; $i++) {
            $amount    = $perInstallment + ($i === 1 ? $remainder : 0); // put remainder in first
            $issueDate = $startDate->copy();
            $dueDate   = match($scheduleType) {
                FeeScheduleType::MONTHLY   => $startDate->copy()->addMonths($i - 1)->endOfMonth(),
                FeeScheduleType::QUARTERLY => $startDate->copy()->addMonths(($i - 1) * 3)->endOfMonth(),
                FeeScheduleType::YEARLY    => Carbon::parse($year->end_date),
            };

            $vatAmount = (int) round($amount * ($school->vat_rate / 100));
            $total     = $amount + $vatAmount;

            $invoices[] = $enrollment->invoices()->create([
                'reference'          => Invoice::generateReference(),
                'school_id'          => $enrollment->school_id,
                'student_id'         => $enrollment->student_id,
                'academic_year_id'   => $enrollment->academic_year_id,
                'fee_schedule_id'    => $feePlan->fee_schedule_id,
                'invoice_type'       => InvoiceType::TUITION,
                'schedule_type'      => $scheduleType,
                'status'             => InvoiceStatus::ISSUED,
                'issue_date'         => $issueDate->toDateString(),
                'due_date'           => $dueDate->toDateString(),
                'subtotal'           => $amount,
                'vat_rate'           => $school->vat_rate,
                'vat_amount'         => $vatAmount,
                'total'              => $total,
                'paid_total'         => 0,
                'balance_due'        => $total,
                'installment_number' => $i,
            ]);
        }

        return $invoices;
    }

    public function recordPayment(
        array   $paymentData,
        array   $allocationMap  // [invoice_id => amount]
    ): Payment {
        return DB::transaction(function () use ($paymentData, $allocationMap) {
            $payment = Payment::create([
                ...$paymentData,
                'reference' => Payment::generateReference(),
            ]);

            foreach ($allocationMap as $invoiceId => $amount) {
                $invoice = Invoice::findOrFail($invoiceId);

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount'     => $amount,
                ]);

                // Update invoice paid / balance
                $newPaid    = $invoice->paid_total + $amount;
                $newBalance = max(0, $invoice->total - $newPaid);

                $status = match(true) {
                    $newBalance === 0           => InvoiceStatus::PAID,
                    $newPaid > 0                => InvoiceStatus::PARTIALLY_PAID,
                    default                     => InvoiceStatus::ISSUED,
                };

                $invoice->update([
                    'paid_total'  => $newPaid,
                    'balance_due' => $newBalance,
                    'status'      => $status,
                ]);
            }

            return $payment->fresh();
        });
    }

    public function markOverdueInvoices(int $schoolId): int
    {
        return Invoice::where('school_id', $schoolId)
            ->whereIn('status', [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIALLY_PAID->value])
            ->whereDate('due_date', '<', now())
            ->update(['status' => InvoiceStatus::OVERDUE]);
    }

    private function applyDiscount(int $amount, int $fixedDiscount, float $pctDiscount): int
    {
        $afterPct   = (int) round($amount * (1 - $pctDiscount / 100));
        $afterFixed = max(0, $afterPct - $fixedDiscount);
        return $afterFixed;
    }
}
