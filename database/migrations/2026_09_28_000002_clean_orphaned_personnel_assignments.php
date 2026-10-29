<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clean up orphaned personnel assignments where the assigned project was soft-deleted.
 *
 * Releases any field personnel whose project_id points to a deleted project,
 * clearing project_id and date_assigned, and closing any open history rows.
 */
return new class () extends Migration {
    public function up(): void
    {
        $now = now()->toDateString();
        $timestamp = now();

        // 1. Close open personnel history rows where the project was soft-deleted
        DB::table('project_personnel_history')
            ->whereNull('released_at')
            ->whereIn('project_id', function ($query) {
                $query->select('id')->from('projects')->whereNotNull('deleted_at');
            })
            ->update([
                'released_at'    => $now,
                'release_reason' => 'project_deleted',
                'updated_at'     => $timestamp,
            ]);

        // 2. Unassign personnel whose project was soft-deleted
        DB::table('personnel')
            ->whereNotNull('project_id')
            ->whereIn('project_id', function ($query) {
                $query->select('id')->from('projects')->whereNotNull('deleted_at');
            })
            ->update([
                'project_id'    => null,
                'date_assigned' => null,
                'updated_at'    => $timestamp,
            ]);
    }

    public function down(): void
    {
        // One-way data consistency fix; no rollback needed
    }
};
