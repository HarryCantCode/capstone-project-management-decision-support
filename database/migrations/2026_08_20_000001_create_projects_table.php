<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the projects table.
 *
 * Status is an enum constrained at the DB level (per database.md).
 * Soft deletes are required on Project (per database.md) — this system's
 * whole purpose is record-keeping; hard-deleting a project loses history.
 *
 * Allowed status transitions (per OQ-3):
 *   pending → ongoing   (only)
 *   ongoing → completed (only)
 * All other transitions are blocked in ProjectService, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('client_name');
            $table->string('project_code')->unique(); // e.g. DEX-2026-001
            $table->enum('status', ['pending', 'ongoing', 'completed'])->default('pending')->index();

            // Soft deletes — required for all transactional records (database.md)
            $table->softDeletes();

            // Audit columns (required on every table — database.md)
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');

            $table->timestamps();

            // Index for the most common WHERE clause on the project list view
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
