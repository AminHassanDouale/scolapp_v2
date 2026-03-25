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
     * Receives events forwarded from the billing API (D-Money callbacks).
     * No auth — protected by HMAC signature instead.
     */
    public function handle(Request $request): JsonResponse
    {
        // ── Signature verification ────────────────────────────────────────────
        $signature = $request->header('X-Webhook-Signature', '');
        $payload   = $request->getContent();

        if (
            config('billing.webhook_secret') &&
            ! BillingApiService::verifyWebhookSignature($payload, $signature)
        ) {
            Log::warning('D-Money webhook: invalid signature', [
                'ip'        => $request->ip(),
                'signature' => $signature,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data      = $request->all();
        $eventType = $data['event_type'] ?? null;
        $orderId   = $data['order_id']   ?? null;

        Log::info('D-Money webhook received', ['event' => $eventType, 'order_id' => $orderId]);

        try {
            match ($eventType) {
                'payment.completed'      => $this->onPaymentCompleted($data),
                'payment.failed'         => $this->onPaymentFailed($data),
                'subscription.activated' => $this->onPaymentCompleted($data),  // alias
                'subscription.expired',
                'subscription.canceled'  => $this->onPaymentFailed($data),
                default => Log::info('D-Money webhook: unhandled event', ['type' => $eventType]),
            };
        } catch (\Throwable $e) {
            Log::error('D-Money webhook processing error', [
                'error'    => $e->getMessage(),
                'event'    => $eventType,
                'order_id' => $orderId,
            ]);
            return response()->json(['error' => 'Processing error'], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    // ── Handlers ───────────────────────────────────────────────────────────────

    private function onPaymentCompleted(array $data): void
    {
        $orderId = $data['order_id'] ?? null;
        if (! $orderId) return;

        $tx = DmoneyTransaction::where('order_id', $orderId)->first();
        if (! $tx) {
            Log::warning('D-Money webhook: unknown order_id', ['order_id' => $orderId]);
            return;
        }

        if ($tx->isCompleted()) {
            return; // idempotent — already processed
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
                'transaction_ref' => $tx->order_id,
                'notes'           => 'Paiement D-Money en ligne (automatique)',
                'meta'            => [
                    'channel'   => 'dmoney_online',
                    'order_id'  => $tx->order_id,
                    'dmoney_tx' => $tx->id,
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

            // Update transaction
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

    private function onPaymentFailed(array $data): void
    {
        $orderId = $data['order_id'] ?? null;
        if (! $orderId) return;

        DmoneyTransaction::where('order_id', $orderId)
            ->where('status', 'pending')
            ->update([
                'status'          => $data['event_type'] === 'subscription.canceled' ? 'cancelled' : 'failed',
                'webhook_payload' => $data,
                'cancelled_at'    => now(),
            ]);

        Log::info('D-Money payment failed/cancelled', ['order_id' => $orderId]);
    }
}
