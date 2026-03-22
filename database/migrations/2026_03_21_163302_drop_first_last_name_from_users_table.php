<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'first_name')) {
            return; // Already dropped
        }

        // Merge first_name + last_name into name for any rows where name is blank
        DB::statement("UPDATE users SET name = TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) WHERE (name IS NULL OR name = '') AND (first_name IS NOT NULL OR last_name IS NOT NULL)");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('school_id');
            $table->string('last_name',  100)->nullable()->after('first_name');
        });
    }
};
