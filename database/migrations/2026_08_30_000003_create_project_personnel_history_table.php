<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the project_personnel_history table.
 *
 * Tracks every assignment and release of field personnel to projects,
 * allowing completed/archived projects to retain a permanent record of all
 * manpower who helped build the project even after personnel are released.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('project_personnel_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->date('assigned_at');
            $table->date('released_at')->nullable();
            $table->string('release_reason')->nullable(); // 'unassigned', 'project_completed', 'reassigned'
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'released_at']);
            $table->index(['personnel_id', 'released_at']);
        });

        // Backfill any currently assigned personnel
        $currentlyAssigned = DB::table('personnel')
            ->whereNotNull('project_id')
            ->get();

        foreach ($currentlyAssigned as $person) {
            DB::table('project_personnel_history')->insert([
                'project_id' => $person->project_id,
                'personnel_id' => $person->id,
                'assigned_at' => $person->date_assigned ?? now()->toDateString(),
                'assigned_by' => $person->updated_by ?? $person->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_personnel_history');
    }
};
