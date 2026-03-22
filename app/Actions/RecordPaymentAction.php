<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;

class RecordPaymentAction
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * @param  array  $paymentData  Payment attributes (student_id, school_id, amount, payment_date, etc.)
     * @param  array  $invoiceIds   Invoice IDs to allocate against (auto-distributed oldest first)
     * @return Payment
     */
    public function __invoke(array $paymentData, array $invoiceIds): Payment
    {
        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->orderBy('due_date')
            ->get();

        $remaining     = $paymentData['amount'];
        $allocationMap = [];

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $allocate              = min($remaining, $invoice->balance_due);
            $allocationMap[$invoice->id] = $allocate;
            $remaining            -= $allocate;
        }

        return $this->invoiceService->recordPayment($paymentData, $allocationMap);
    }
}
