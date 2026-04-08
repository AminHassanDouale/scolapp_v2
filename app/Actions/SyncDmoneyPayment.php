<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Mail\PaymentReceivedMail;
use App\Models\DmoneyTransaction;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\BillingApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncDmoneyPayment
{
    /**
     * Query D-Money for the order, and mark it paid if confirmed.
     * Safe to call multiple times (idempotent).
     *
     * @return bool  true if the payment was newly completed
     */
    public function handle(string $orderId, array $queryData = []): bool
    {
        $tx = DmoneyTransaction::where('order_id', $orderId)->first();
        if (! $tx || $tx->isCompleted()) {
            return false;
        }

        // Fetch live status from D-Money if not supplied
        if (empty($queryData)) {
            try {
                $queryData = app(BillingApiService::class)->queryPayment($orderId);
            } catch (\Throwable $e) {
                Log::warning('SyncDmoneyPayment: queryPayment failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
                return false;
            }
        }

        $tradeStatus = strtolower($queryData['trade_status'] ?? '');

        $successStatuses  = ['completed', 'success', 'paid'];
        $failedStatuses   = ['failed', 'canceled', 'cancelled', 'expired'];

        if (in_array($tradeStatus, $failedStatuses)) {
            $tx->update([
                'status'          => 'failed',
                'webhook_payload' => $queryData,
                'cancelled_at'    => now(),
            ]);
            return false;
        }

        if (! in_array($tradeStatus, $successStatuses)) {
            return false; // still pending or processing
        }

        $payment = null;

        DB::transaction(function () use ($tx, $queryData, &$payment) {
            $invoice = Invoice::find($tx->invoice_id);
            if (! $invoice || in_array($invoice->status, [InvoiceStatus::PAID, InvoiceStatus::CANCELLED])) {
                return;
            }

            $payment = Payment::create([
                'school_id'       => $tx->school_id,
                'student_id'      => $tx->student_id,
                'enrollment_id'   => $invoice->enrollment_id,
                'received_by'     => null,
                'status'          => PaymentStatus::CONFIRMED->value,
                'payment_method'  => 'mobile_money',
                'amount'          => $tx->amount,
                'payment_date'    => now()->toDateString(),
                'transaction_ref' => $queryData['trade_no'] ?? $tx->order_id,
                'notes'           => 'Paiement D-Money en ligne (automatique)',
                'meta'            => [
                    'channel'   => 'dmoney_online',
                    'order_id'  => $tx->order_id,
                    'trade_no'  => $queryData['trade_no'] ?? null,
                    'dmoney_tx' => $tx->id,
                ],
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount'     => $tx->amount,
            ]);

            $invoice->increment('paid_total', $tx->amount);
            $newBalance = max(0, (float) $invoice->fresh()->balance_due - $tx->amount);
            $invoice->update([
                'balance_due' => $newBalance,
                'status'      => $newBalance <= 0
                    ? InvoiceStatus::PAID->value
                    : InvoiceStatus::PARTIALLY_PAID->value,
            ]);

            $tx->update([
                'status'          => 'completed',
                'payment_id'      => $payment->id,
                'webhook_payload' => $queryData,
                'completed_at'    => now(),
            ]);

            Log::info('SyncDmoneyPayment: invoice updated', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount'     => $tx->amount,
            ]);
        });

        if ($payment) {
            $this->sendConfirmationEmail($payment, $tx->student_id);
        }

        return $payment !== null;
    }

    private function sendConfirmationEmail(Payment $payment, int $studentId): void
    {
        $guardian = Guardian::whereHas('students', fn($q) => $q->where('students.id', $studentId))
            ->with('user')
            ->get()
            ->first(fn($g) => filled($g->email) || filled($g->user?->email));

        if (! $guardian) {
            return;
        }

        try {
            $payment->load('school', 'student', 'paymentAllocations.invoice.enrollment.schoolClass');
            Mail::to($guardian->email ?? $guardian->user->email)
                ->queue(new PaymentReceivedMail($payment, $guardian));
        } catch (\Throwable $e) {
            Log::error('SyncDmoneyPayment: failed to queue email', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
