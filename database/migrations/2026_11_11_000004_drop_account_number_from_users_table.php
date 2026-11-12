<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'account_number')) {
            Schema::table('users', function (Blueprint $table) {
                try {
                    $table->dropUnique(['account_number']);
                } catch (\Throwable) {
                    // Unique index might have been dropped or named differently
                }
                $table->dropColumn('account_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'account_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('account_number', 7)->nullable()->unique()->after('employee_number');
            });
        }
    }
};
