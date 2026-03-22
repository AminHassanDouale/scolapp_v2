<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export Factures</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a2e; }
        h1   { font-size: 18px; color: #4f46e5; margin-bottom: 4px; }
        p    { margin: 2px 0; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        thead th { background: #4f46e5; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .badge-paid    { color: #16a34a; font-weight: bold; }
        .badge-overdue { color: #dc2626; font-weight: bold; }
        .badge-issued  { color: #2563eb; }
        .badge-partial { color: #d97706; }
        .badge-draft   { color: #6b7280; }
        .badge-cancelled { color: #ef4444; }
        tfoot td { background: #f3f4f6; font-weight: bold; padding: 8px 6px; }
    </style>
</head>
<body>
    <h1>ScolApp SMS — Export Factures</h1>
    <p>Généré le {{ now()->format('d/m/Y à H:i') }}</p>
    <p>Total : <strong>{{ $invoices->count() }}</strong> facture(s)</p>

    <table>
        <thead>
            <tr>
                <th>Référence</th>
                <th>Élève</th>
                <th>Type</th>
                <th>Date émission</th>
                <th>Échéance</th>
                <th class="text-right">Total (DJF)</th>
                <th class="text-right">Payé (DJF)</th>
                <th class="text-right">Solde (DJF)</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            @php
                $statusClass = match($invoice->status->value) {
                    'paid'           => 'badge-paid',
                    'overdue'        => 'badge-overdue',
                    'issued'         => 'badge-issued',
                    'partially_paid' => 'badge-partial',
                    'cancelled'      => 'badge-cancelled',
                    default          => 'badge-draft',
                };
            @endphp
            <tr>
                <td><strong>{{ $invoice->reference }}</strong></td>
                <td>{{ $invoice->student?->full_name ?? '—' }}</td>
                <td>{{ $invoice->invoice_type->label() }}</td>
                <td>{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                <td>{{ $invoice->due_date?->format('d/m/Y') }}</td>
                <td class="text-right">{{ number_format($invoice->total) }}</td>
                <td class="text-right">{{ number_format($invoice->paid_total) }}</td>
                <td class="text-right">{{ number_format($invoice->balance_due) }}</td>
                <td class="{{ $statusClass }}">{{ $invoice->status->label() }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">Total</td>
                <td class="text-right">{{ number_format($invoices->sum('total')) }}</td>
                <td class="text-right">{{ number_format($invoices->sum('paid_total')) }}</td>
                <td class="text-right">{{ number_format($invoices->sum('balance_due')) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
