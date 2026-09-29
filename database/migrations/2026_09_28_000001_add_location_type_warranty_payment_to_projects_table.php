<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds location (address, barangay, city), project_type, warranty period,
 * and payment_status fields to the projects table.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('address')->nullable()->after('client_name');
            $table->string('barangay')->nullable()->after('address');
            $table->string('city')->nullable()->after('barangay');
            $table->string('project_type')->nullable()->after('city');
            $table->string('payment_status', 50)->default('Partial')->after('contract_price');
            $table->string('warranty_period', 100)->default('1 Year')->after('target_completion_date');
            $table->date('warranty_end_date')->nullable()->after('warranty_period');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'barangay',
                'city',
                'project_type',
                'payment_status',
                'warranty_period',
                'warranty_end_date',
            ]);
        });
    }
};
