<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the project_status_history table.
 *
 * Every time a project's status changes, ProjectObserver writes a row here.
 * This table is the source for the history timeline on the project detail view
 * and for the Reports module (which phase? Phase 5).
 *
 * No soft deletes on this table — history rows are append-only and must
 * never be deleted (they're the audit record of what happened).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('project_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('from_status')->nullable(); // null = first status set (creation)
            $table->string('to_status');
            $table->text('notes')->nullable(); // optional reason provided by the user
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at')->useCurrent();

            // Index for fetching history for a project in chronological order
            $table->index(['project_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_status_history');
    }
};
