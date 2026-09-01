<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    /**
     * Stream a single payment receipt as a printable PDF (inline), including
     * signature fields for the caissier and the titulaire/parent.
     *
     * The signature fields appear only on this printable copy — the email and
     * WhatsApp receipts render the same view WITHOUT signatures.
     */
    public function print(string $uuid): Response
    {
        $schoolId = auth()->user()->school_id;

        $payment = Payment::where('uuid', $uuid)
            ->where('school_id', $schoolId)
            ->with(['student.guardians', 'paymentAllocations.invoice.academicYear', 'school'])
            ->firstOrFail();

        $school   = $payment->school ?? School::find($schoolId);
        $guardian = $payment->student?->guardians?->first();

        $pdf = Pdf::loadView('exports.payments.receipt-pdf', [
            'payment'    => $payment,
            'school'     => $school,
            'guardian'   => $guardian,
            'signatures' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('recu-' . $payment->reference . '.pdf');
    }
}
