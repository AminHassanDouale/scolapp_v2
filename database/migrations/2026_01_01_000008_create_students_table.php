<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique()->nullable(); // auto-generated on first enrollment
            $table->string('student_code')->unique()->nullable();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // optional portal access
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender')->nullable(); // GenderType enum
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->nullable()->default('DJ');
            $table->string('national_id')->nullable();
            $table->string('photo')->nullable();
            $table->string('address')->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->boolean('has_disability')->default(false);
            $table->text('disability_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
