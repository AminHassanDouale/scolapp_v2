<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('plan')->default('trial')->after('is_active');       // trial|basic|pro|enterprise
            $table->timestamp('trial_ends_at')->nullable()->after('plan');      // null = no trial limit
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->unsignedInteger('max_students')->default(0)->after('subscription_ends_at'); // 0 = unlimited
            $table->unsignedInteger('max_teachers')->default(0)->after('max_students');
            $table->string('contact_name')->nullable()->after('max_teachers');
            $table->text('suspension_reason')->nullable()->after('contact_name');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'plan', 'trial_ends_at', 'subscription_ends_at',
                'max_students', 'max_teachers', 'contact_name',
                'suspension_reason', 'suspended_at',
            ]);
        });
    }
};
