<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'first_name')) {
            return; // Already done
        }

        // Add name column if not present
        if (! Schema::hasColumn('students', 'name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('name', 200)->after('user_id');
            });
        }

        // Migrate data
        DB::statement("UPDATE students SET name = TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))");

        // Drop old columns
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('user_id');
            $table->string('last_name',  100)->nullable()->after('first_name');
        });

        if (Schema::hasColumn('students', 'name')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
};
