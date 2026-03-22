<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();

            // Header customization
            $table->string('header_title')->default('Bulletin Scolaire');
            $table->string('subtitle')->nullable();
            $table->text('instructions')->nullable();   // printed on the bulletin
            $table->text('footer_text')->nullable();

            // Principal / director
            $table->string('principal_name')->nullable();
            $table->string('principal_title')->default('Le Directeur');

            // Mention thresholds (on 20)
            $table->decimal('mention_tb_min', 4, 1)->default(16);
            $table->decimal('mention_b_min',  4, 1)->default(14);
            $table->decimal('mention_ab_min', 4, 1)->default(12);
            $table->decimal('mention_p_min',  4, 1)->default(10);

            // Display toggles
            $table->boolean('show_rank')->default(true);
            $table->boolean('show_class_avg')->default(true);
            $table->boolean('show_absences')->default(false);
            $table->boolean('show_teacher_comment')->default(true);

            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_templates');
    }
};
