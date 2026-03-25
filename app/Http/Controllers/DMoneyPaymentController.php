<?php

namespace App\Http\Controllers;

use App\Models\DmoneyTransaction;
use Illuminate\Http\Request;

class DMoneyPaymentController extends Controller
{
    /**
     * GET /guardian/paiement/succes?order_id=XXX
     *
     * D-Money redirects the guardian here after checkout.
     * The ACTUAL payment confirmation comes via webhook.
     * We show a "processing" page and redirect to invoices.
     */
    public function success(Request $request)
    {
        $orderId = $request->query('order_id');
        $tx      = $orderId
            ? DmoneyTransaction::where('order_id', $orderId)
                ->where('user_id', auth()->id())
                ->first()
            : null;

        return view('payment.dmoney-result', [
            'success'  => true,
            'tx'       => $tx,
            'redirect' => route('guardian.invoices'),
        ]);
    }

    /**
     * GET /guardian/paiement/annule?order_id=XXX
     *
     * User cancelled on D-Money checkout page.
     */
    public function cancel(Request $request)
    {
        $orderId = $request->query('order_id');

        if ($orderId) {
            DmoneyTransaction::where('order_id', $orderId)
                ->where('user_id', auth()->id())
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return view('payment.dmoney-result', [
            'success'  => false,
            'tx'       => null,
            'redirect' => route('guardian.invoices'),
        ]);
    }
}
