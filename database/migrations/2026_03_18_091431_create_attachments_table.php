<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();

            // Polymorphic: attachable to Teacher, Student, Guardian, etc.
            $table->morphs('attachable');

            $table->string('category');            // AttachmentCategory enum value
            $table->string('label');               // display name (e.g. "Diplôme CAPES 2019")
            $table->string('disk')->default('local');
            $table->string('path');                // storage path
            $table->string('original_name');       // original filename
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->string('mime_type')->nullable();

            $table->date('expires_at')->nullable(); // for ID cards, passports, etc.
            $table->text('notes')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
