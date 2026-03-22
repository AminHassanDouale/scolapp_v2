<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('reference')->unique()->nullable()->after('uuid');
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete()->after('reference');
            $table->string('phone')->nullable()->after('school_id');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('ui_lang', 10)->default('fr')->after('avatar');
            $table->string('timezone', 50)->default('Africa/Djibouti')->after('ui_lang');
            $table->boolean('is_blocked')->default(false)->after('timezone');
            $table->string('blocked_reason')->nullable()->after('is_blocked');
            $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
            $table->timestamp('last_login_at')->nullable()->after('blocked_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'reference', 'school_id', 'phone', 'avatar',
                'ui_lang', 'timezone', 'is_blocked', 'blocked_reason', 'blocked_at', 'last_login_at', 'last_login_ip',
                'deleted_at']);
        });
    }
};
