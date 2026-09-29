<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_number')) {
                $table->string('employee_number')->nullable()->after('name')->unique();
            }
            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title')->nullable()->after('employee_number');
            }
        });

        // Set placeholder employee numbers for existing users to satisfy the unique constraint.
        // If there are existing users, we'll assign them a sequential EMP- number based on their ID.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("UPDATE users SET employee_number = 'EMP-' || PRINTF('%03d', id) WHERE employee_number IS NULL");
        } else {
            DB::statement("UPDATE users SET employee_number = CONCAT('EMP-', LPAD(id, 3, '0')) WHERE employee_number IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_number', 'job_title']);
        });
    }
};
