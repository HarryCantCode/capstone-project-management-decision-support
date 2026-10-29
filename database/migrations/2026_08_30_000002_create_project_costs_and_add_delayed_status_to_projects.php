<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates project_costs table for itemized costing and updates projects.status to allow 'delayed'.
 */
return new class () extends Migration {
    public function up(): void
    {
        // 1. Update project status to include 'delayed'
        // Using string column for maximum compatibility across MySQL / SQLite
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        // 2. Create project_costs table
        if (!Schema::hasTable('project_costs')) {
            Schema::create('project_costs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('description');
                $table->string('cost_type')->default('materials'); // materials, equipment, labor, subcontractor, other
                $table->decimal('amount', 12, 2)->default(0.00);
                $table->date('incurred_date')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_costs');

        Schema::table('projects', function (Blueprint $table) {
            $table->enum('status', ['pending', 'ongoing', 'completed'])->default('pending')->change();
        });
    }
};
