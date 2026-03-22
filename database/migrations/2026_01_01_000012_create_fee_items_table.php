<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');               // Tuition, Registration, Transport
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 30)->nullable(); // tuition, registration, other
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->string('name');
            $table->string('schedule_type');       // FeeScheduleType enum
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Fee schedule ↔ Fee item pivot with amount
        Schema::create('fee_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_schedule_id')->constrained('fee_schedules')->cascadeOnDelete();
            $table->foreignId('fee_item_id')->constrained('fee_items')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');  // in DJF
            $table->timestamps();

            $table->unique(['fee_schedule_id', 'fee_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedule_items');
        Schema::dropIfExists('fee_schedules');
        Schema::dropIfExists('fee_items');
    }
};
