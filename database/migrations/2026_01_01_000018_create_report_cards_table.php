<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('period');               // ReportPeriod enum
            $table->decimal('average', 6, 2)->nullable();
            $table->decimal('class_average', 6, 2)->nullable();
            $table->smallInteger('rank')->nullable();
            $table->smallInteger('class_size')->nullable();
            $table->text('general_comment')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['enrollment_id', 'period']);
        });

        Schema::create('report_card_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->decimal('average', 6, 2)->nullable();
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->decimal('weighted_avg', 6, 2)->nullable();
            $table->decimal('class_avg', 6, 2)->nullable();
            $table->smallInteger('rank')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['report_card_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_subjects');
        Schema::dropIfExists('report_cards');
    }
};
