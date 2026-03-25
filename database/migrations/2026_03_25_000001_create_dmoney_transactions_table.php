<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dmoney_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // School context
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Linked to invoice + student
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Guardian who initiated
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Billing API references
            $table->string('billing_subscription_id')->nullable();
            $table->string('order_id')->nullable()->index();    // D-Money order reference
            $table->text('checkout_url')->nullable();

            // Amount
            $table->unsignedBigInteger('amount');              // in DJF (integer, no decimals)
            $table->string('currency', 3)->default('DJF');

            // Status: pending | completed | failed | cancelled
            $table->string('status', 20)->default('pending')->index();

            // Guardian phone (optional, for records)
            $table->string('guardian_phone', 30)->nullable();

            // Filled when webhook arrives
            $table->json('webhook_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Payment created from webhook
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dmoney_transactions');
    }
};
