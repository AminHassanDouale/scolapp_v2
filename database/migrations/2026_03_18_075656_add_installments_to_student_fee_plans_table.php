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
        Schema::table('student_fee_plans', function (Blueprint $table) {
            // Nullable: if set, overrides the frequency's default installment count
            $table->unsignedSmallInteger('installments')->nullable()->after('payment_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('student_fee_plans', function (Blueprint $table) {
            $table->dropColumn('installments');
        });
    }
};
