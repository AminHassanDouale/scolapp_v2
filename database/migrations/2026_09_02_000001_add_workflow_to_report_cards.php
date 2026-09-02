<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the approval workflow (INTEC-style) to report cards:
 *   - status column + per-step approval stamps
 *   - manual override values (which win over computed ones)
 *   - an append-only audit trail table (report_card_approvals)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->string('status')->default('draft')->index()->after('period');

            // Approval stamps
            $table->foreignId('submitted_by')->nullable()->after('is_published')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('pedagogie_approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('pedagogie_approved_at')->nullable()->after('pedagogie_approved_by');
            $table->foreignId('finance_approved_by')->nullable()->after('pedagogie_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable()->after('finance_approved_by');
            $table->foreignId('direction_approved_by')->nullable()->after('finance_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('direction_approved_at')->nullable()->after('direction_approved_by');
            $table->text('rejection_reason')->nullable()->after('direction_approved_at');

            // Manual overrides (win over computed — see spec §2)
            $table->decimal('average_manual', 6, 2)->nullable()->after('average');
            $table->decimal('total_manual', 8, 2)->nullable()->after('average_manual');
            $table->decimal('class_average_manual', 6, 2)->nullable()->after('class_average');
            $table->string('discipline_status', 30)->nullable()->after('teacher_comment');
        });

        // Back-fill status from the legacy is_published flag
        DB::table('report_cards')->where('is_published', true)->update(['status' => 'published']);

        Schema::create('report_card_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->string('step');                 // status at the time of the action
            $table->string('action');               // approved | rejected | submitted | published
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['report_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_approvals');

        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('pedagogie_approved_by');
            $table->dropConstrainedForeignId('finance_approved_by');
            $table->dropConstrainedForeignId('direction_approved_by');
            $table->dropColumn([
                'status', 'submitted_at', 'pedagogie_approved_at', 'finance_approved_at',
                'direction_approved_at', 'rejection_reason',
                'average_manual', 'total_manual', 'class_average_manual', 'discipline_status',
            ]);
        });
    }
};
