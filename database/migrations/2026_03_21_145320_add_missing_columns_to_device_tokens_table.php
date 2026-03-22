<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('device_tokens', 'uuid')) {
                $table->uuid('uuid')->unique()->after('id');
            }
            if (!Schema::hasColumn('device_tokens', 'school_id')) {
                $table->foreignId('school_id')
                      ->after('user_id')
                      ->constrained('schools')
                      ->cascadeOnDelete();
            }
            if (!Schema::hasColumn('device_tokens', 'device_name')) {
                $table->string('device_name', 200)->nullable()->after('platform');
            }
            if (!Schema::hasColumn('device_tokens', 'device_model')) {
                $table->string('device_model', 100)->nullable()->after('device_name');
            }
            if (!Schema::hasColumn('device_tokens', 'os_version')) {
                $table->string('os_version', 50)->nullable()->after('device_model');
            }
            if (!Schema::hasColumn('device_tokens', 'app_version')) {
                $table->string('app_version', 30)->nullable()->after('os_version');
            }
            if (!Schema::hasColumn('device_tokens', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_used_at');
            }
        });

        // Add composite indexes if not present
        Schema::table('device_tokens', function (Blueprint $table) {
            try { $table->index(['user_id', 'is_active'], 'dt_user_active_idx'); } catch (\Throwable) {}
            try { $table->index(['school_id', 'platform'], 'dt_school_platform_idx'); } catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'school_id', 'device_name', 'device_model', 'os_version', 'app_version', 'is_active']);
        });
    }
};
