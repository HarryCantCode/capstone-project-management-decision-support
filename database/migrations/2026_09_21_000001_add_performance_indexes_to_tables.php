<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds performance indexes to optimize module searches, filtering, and sorting.
 */
return new class () extends Migration {
    public function up(): void
    {
        // 1. Indexes for personnel table (speed up Manpower filtering & sorting)
        Schema::table('personnel', function (Blueprint $table) {
            $table->index('expertise', 'personnel_expertise_index');
            $table->index('created_at', 'personnel_created_at_index');
            $table->index(['project_id', 'expertise'], 'personnel_project_expertise_index');
        });

        // 2. Indexes for project_costs table (speed up Costing breakdown & category filtering)
        Schema::table('project_costs', function (Blueprint $table) {
            $table->index(['project_id', 'incurred_date'], 'project_costs_project_date_index');
            $table->index(['project_id', 'cost_type'], 'project_costs_project_type_index');
        });

        // 3. Indexes for projects table (speed up search by name and client)
        Schema::table('projects', function (Blueprint $table) {
            $table->index('name', 'projects_name_index');
            $table->index('client_name', 'projects_client_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_name_index');
            $table->dropIndex('projects_client_name_index');
        });

        Schema::table('project_costs', function (Blueprint $table) {
            $table->dropIndex('project_costs_project_date_index');
            $table->dropIndex('project_costs_project_type_index');
        });

        Schema::table('personnel', function (Blueprint $table) {
            $table->dropIndex('personnel_expertise_index');
            $table->dropIndex('personnel_created_at_index');
            $table->dropIndex('personnel_project_expertise_index');
        });
    }
};
