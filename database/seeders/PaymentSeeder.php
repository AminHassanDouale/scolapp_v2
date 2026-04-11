<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $school  = School::where('slug', 'ecole-demo')->first();
        $admin   = User::where('email', 'admin@scolapp.com')->first();
        $methods = ['cash', 'bank_transfer', 'mobile_money'];

        $invoices = Invoice::where('school_id', $school->id)
            ->whereIn('status', ['paid', 'partially_paid'])
            ->with('student')
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->paid_total <= 0) continue;

            $payment = Payment::create([
                'uuid'           => (string) Str::uuid(),
                'reference'      => 'PAY-' . strtoupper(Str::random(8)),
                'school_id'      => $school->id,
                'student_id'     => $invoice->student_id,
                'enrollment_id'  => $invoice->enrollment_id,
                'received_by'    => $admin->id,
                'status'         => 'confirmed',
                'payment_method' => $methods[array_rand($methods)],
                'amount'         => $invoice->paid_total,
                'payment_date'   => now()->subDays(rand(1, 60))->format('Y-m-d'),
                'notes'          => null,
            ]);

            // Allocation
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount'     => $invoice->paid_total,
            ]);

            $count++;
        }

        $this->command->info("  → {$count} payments + allocations created.");
    }
}
