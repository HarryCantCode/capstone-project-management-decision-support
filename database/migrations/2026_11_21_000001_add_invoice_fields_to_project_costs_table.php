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
        Schema::table('project_costs', function (Blueprint $table) {
            if (!Schema::hasColumn('project_costs', 'invoice_path')) {
                $table->string('invoice_path')->nullable()->after('incurred_date');
            }
            if (!Schema::hasColumn('project_costs', 'invoice_original_name')) {
                $table->string('invoice_original_name')->nullable()->after('invoice_path');
            }
            if (!Schema::hasColumn('project_costs', 'invoice_size')) {
                $table->unsignedBigInteger('invoice_size')->nullable()->after('invoice_original_name');
            }
            if (!Schema::hasColumn('project_costs', 'invoice_mime_type')) {
                $table->string('invoice_mime_type')->nullable()->after('invoice_size');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_costs', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['invoice_mime_type', 'invoice_size', 'invoice_original_name', 'invoice_path'] as $column) {
                if (Schema::hasColumn('project_costs', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
