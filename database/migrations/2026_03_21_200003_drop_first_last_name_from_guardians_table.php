<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guardians', 'first_name')) {
            return; // Already done
        }

        if (! Schema::hasColumn('guardians', 'name')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->string('name', 200)->after('user_id');
            });
        }

        DB::statement("UPDATE guardians SET name = TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))");

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('user_id');
            $table->string('last_name',  100)->nullable()->after('first_name');
        });

        if (Schema::hasColumn('guardians', 'name')) {
            Schema::table('guardians', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
};
