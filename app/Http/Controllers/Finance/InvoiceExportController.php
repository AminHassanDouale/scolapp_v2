<?php

namespace App\Http\Controllers\Finance;

use App\Exports\InvoicesExport;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceExportController extends Controller
{
    private function buildQuery(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $query = Invoice::where('school_id', $schoolId)
            ->with(['student', 'academicYear', 'enrollment.schoolClass']);

        if ($ids = $request->array('ids')) {
            return $query->whereIn('id', $ids);
        }

        return $query
            ->when($request->input('status'),      fn($q) => $q->where('status', $request->input('status')))
            ->when($request->input('student_id'),  fn($q) => $q->where('student_id', $request->input('student_id')))
            ->when($request->input('year_id'),     fn($q) => $q->where('academic_year_id', $request->input('year_id')))
            ->when($request->input('issue_from'),  function ($q) use ($request) {
                $q->where('issue_date', '>=', Carbon::createFromFormat('d/m/Y', $request->input('issue_from'))->startOfDay());
            })
            ->when($request->input('issue_to'),    function ($q) use ($request) {
                $q->where('issue_date', '<=', Carbon::createFromFormat('d/m/Y', $request->input('issue_to'))->endOfDay());
            })
            ->when($request->input('amount_min'),  fn($q) => $q->where('total', '>=', $request->input('amount_min')))
            ->when($request->input('amount_max'),  fn($q) => $q->where('total', '<=', $request->input('amount_max')));
    }

    public function pdf(Request $request): Response
    {
        $invoices = $this->buildQuery($request)->orderBy('issue_date')->get();

        $pdf = Pdf::loadView('exports.invoices.pdf', compact('invoices'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('factures-' . now()->format('Y-m-d') . '.pdf');
    }

    public function xlsx(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $invoices = $this->buildQuery($request)
            ->with(['student', 'academicYear', 'enrollment.schoolClass'])
            ->orderBy('issue_date')
            ->get();

        $filename = 'factures-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new InvoicesExport($invoices), $filename);
    }
}
