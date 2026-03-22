<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_followups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('stage', 30);           // 7day, 14day, overdue
            $table->string('channel', 20)->default('email'); // email, sms, call
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'stage', 'channel']); // idempotent
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_followups');
    }
};
