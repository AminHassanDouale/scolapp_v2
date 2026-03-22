<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            // Identity
            $table->string('name');
            $table->text('description')->nullable();

            // Task type
            $table->string('type');
            // invoice_reminder | overdue_alert | payment_due_soon |
            // attendance_summary | financial_summary | custom_notification

            // Target audience
            $table->string('target_type');
            // all_guardians | class_guardians | grade_guardians |
            // unpaid_guardians | overdue_guardians | school_admins
            $table->unsignedBigInteger('target_id')->nullable(); // class_id or grade_id

            // Scheduling
            $table->string('frequency'); // daily | weekly | monthly
            $table->string('scheduled_time', 5)->default('08:00'); // HH:MM
            $table->tinyInteger('day_of_week')->nullable();   // 0=Mon … 6=Sun (weekly)
            $table->tinyInteger('day_of_month')->nullable();  // 1–31 (monthly)

            // Customisation (subject, body override, days_before, etc.)
            $table->json('meta')->nullable();

            // State
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};
