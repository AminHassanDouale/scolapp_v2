<?php

namespace App\Http\Controllers;

use App\Actions\SyncDmoneyPayment;
use App\Models\DmoneyTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            app(SyncDmoneyPayment::class)->handle($orderId, $data);
        } catch (\Throwable $e) {
            Log::error('D-Money webhook processing error', [
                'error'    => $e->getMessage(),
                'order_id' => $orderId,
            ]);
        }

        // Always acknowledge to D-Money
        return response()->json(['returnCode' => 'SUCCESS', 'returnMsg' => 'OK']);
    }
}
