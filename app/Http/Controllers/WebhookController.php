<?php

namespace App\Http\Controllers;

use App\Models\DmoneyTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\BillingApiService;
use App\Enums\PaymentStatus;
use App\Enums\InvoiceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * POST /webhooks/billing
     *
     * D-Money posts payment notifications here.
     * Payload: { merch_order_id, trade_no, trade_status, total_amount, trans_currency, pay_time }
     * Response must always be: { returnCode: "SUCCESS", returnMsg: "OK" }
     */
    public function handle(Request $request): JsonResponse
    {
        $data    = $request->all();
        $orderId = $data['merch_order_id'] ?? null;
        $status  = $data['trade_status']   ?? null;

        Log::info('D-Money webhook received', ['order_id' => $orderId, 'trade_status' => $status]);

        if (! $orderId || ! $status) {
            Log::warning('D-Money webhook: missing required fields', $data);
            return response()->json(['returnCode' => 'SUCCESS', 'returnMsg' => 'OK']);
        }

        try {
            match (strtoupper($status)) {
                'SUCCESS'          => $this->onPaymentSuccess($orderId, $data),
                'FAILED', 'EXPIRED'=> $this->onPaymentFailed($orderId, $status, $data),
                default            => Log::info('D-Money webhook: unhandled trade_status', ['status' => $status]),
            };
        } catch (\Throwable $e) {
            Log::error('D-Money webhook processing error', [
                'error'    => $e->getMessage(),
                'order_id' => $orderId,
            ]);
        }

        // Always acknowledge to D-Money
        return response()->json(['returnCode' => 'SUCCESS', 'returnMsg' => 'OK']);
    }

    // ── Handlers ───────────────────────────────────────────────────────────────

    private function onPaymentSuccess(string $orderId, array $data): void
    {
        $tx = DmoneyTransaction::where('order_id', $orderId)->first();
        if (! $tx) {
            Log::warning('D-Money webhook: unknown order_id', ['order_id' => $orderId]);
            return;
        }

        if ($tx->isCompleted()) {
            return; // idempotent — already processed
        }

        // Verify with the API before marking paid
        try {
            /** @var BillingApiService $billing */
            $billing = app(BillingApiService::class);
            $verified = $billing->queryPayment($orderId);
            if (strtoupper($verified['trade_status'] ?? '') !== 'SUCCESS') {
                Log::warning('D-Money webhook: verification failed', [
                    'order_id'     => $orderId,
                    'api_response' => $verified,
                ]);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('D-Money webhook: could not verify payment, proceeding with webhook data', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        DB::transaction(function () use ($tx, $data) {
            $invoice = Invoice::find($tx->invoice_id);
            if (! $invoice || in_array($invoice->status, [InvoiceStatus::PAID, InvoiceStatus::CANCELLED])) {
                return;
            }

            // Create Payment record
            $payment = Payment::create([
                'school_id'       => $tx->school_id,
                'student_id'      => $tx->student_id,
                'enrollment_id'   => $invoice->enrollment_id,
                'received_by'     => null,  // automated
                'status'          => PaymentStatus::CONFIRMED->value,
                'payment_method'  => 'mobile_money',
                'amount'          => $tx->amount,
                'payment_date'    => now()->toDateString(),
                'transaction_ref' => $data['trade_no'] ?? $tx->order_id,
                'notes'           => 'Paiement D-Money en ligne (automatique)',
                'meta'            => [
                    'channel'    => 'dmoney_online',
                    'order_id'   => $tx->order_id,
                    'trade_no'   => $data['trade_no'] ?? null,
                    'dmoney_tx'  => $tx->id,
                ],
            ]);

            // Allocate payment to invoice
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount'     => $tx->amount,
            ]);

            // Update invoice balance
            $invoice->increment('paid_total', $tx->amount);
            $invoice->decrement('balance_due', $tx->amount);

            if ($invoice->fresh()->balance_due <= 0) {
                $invoice->update(['status' => InvoiceStatus::PAID->value, 'balance_due' => 0]);
            } else {
                $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID->value]);
            }

            // Mark transaction completed
            $tx->update([
                'status'          => 'completed',
                'payment_id'      => $payment->id,
                'webhook_payload' => $data,
                'completed_at'    => now(),
            ]);

            Log::info('D-Money payment completed — invoice updated', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount'     => $tx->amount,
            ]);
        });
    }

    private function onPaymentFailed(string $orderId, string $status, array $data): void
    {
        DmoneyTransaction::where('order_id', $orderId)
            ->where('status', 'pending')
            ->update([
                'status'          => strtoupper($status) === 'EXPIRED' ? 'failed' : 'failed',
                'webhook_payload' => $data,
                'cancelled_at'    => now(),
            ]);

        Log::info('D-Money payment failed/expired', ['order_id' => $orderId, 'trade_status' => $status]);
    }
}
