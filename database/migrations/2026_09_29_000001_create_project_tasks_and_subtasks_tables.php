<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Creates tables for mother tasks (project_tasks) and baby tasks (project_subtasks).
     */
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('task_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('sort_order')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'start_date']);
            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['project_task_id', 'is_completed']);
            $table->index(['project_task_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_subtasks');
        Schema::dropIfExists('project_tasks');
    }
};
