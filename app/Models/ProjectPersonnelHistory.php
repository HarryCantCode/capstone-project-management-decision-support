<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectPersonnelHistory Model
 *
 * Records historical and active field manpower assignments to projects.
 * Preserves a permanent record of all workers who contributed to a project
 * even when the project is marked completed and manpower are released.
 */
class ProjectPersonnelHistory extends Model
{
    use HasFactory;

    protected $table = 'project_personnel_history';

    protected $fillable = [
        'project_id',
        'personnel_id',
        'assigned_at',
        'released_at',
        'release_reason',
        'assigned_by',
        'released_by',
    ];

    protected $casts = [
        'assigned_at' => 'date',
        'released_at' => 'date',
    ];

    /**
     * The project this assignment belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The personnel (field worker) assigned.
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class)->withTrashed();
    }

    /**
     * The user who made the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    /**
     * The user who released the assignment.
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by')->withTrashed();
    }
}
