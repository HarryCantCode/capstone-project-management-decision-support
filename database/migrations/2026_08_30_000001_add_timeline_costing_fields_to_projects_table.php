<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds timeline scheduling and costing fields to the projects table.
 *
 * - target_completion_date: The target completion date for the project.
 * - start_date: Project start date (defaults to project creation date if empty).
 * - contract_price: Total contract price / allocated budget for the project.
 * - actual_spend: Actual expenditures tracked for this project.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('client_name');
            $table->date('target_completion_date')->nullable()->after('start_date');
            $table->decimal('contract_price', 12, 2)->default(0.00)->after('target_completion_date');
            $table->decimal('actual_spend', 12, 2)->default(0.00)->after('contract_price');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'target_completion_date',
                'contract_price',
                'actual_spend',
            ]);
        });
    }
};
