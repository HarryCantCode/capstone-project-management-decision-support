<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records a single status transition for a project.
 *
 * Rows are append-only — they are never updated after creation.
 * This is enforced by having no fillable 'updated_at' and by the observer
 * only calling ->create(), never ->update() on this model.
 */
class ProjectStatusHistory extends Model
{
    /**
     * This table has no updated_at — history rows are written once, never edited.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by',
        'changed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The user who changed the status.
     * Uses 'changed_by' FK rather than the standard 'updated_by' convention
     * because this column has a specific semantic: it captures who performed
     * this particular transition, not who last touched the row.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
