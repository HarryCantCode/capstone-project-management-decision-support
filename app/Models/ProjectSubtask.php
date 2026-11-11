<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectSubtask — represents a Baby Task (specific checkbox task) inside a Mother Task.
 *
 * @property int $id
 * @property int $project_task_id
 * @property string $title
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ProjectSubtask extends Model
{
    use HasFactory;

    protected $table = 'project_subtasks';

    protected $fillable = [
        'project_task_id',
        'title',
        'due_date',
        'is_completed',
        'completed_at',
        'completed_by',
        'approved_by',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'completed_by' => 'integer',
        'approved_by' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        $flush = fn () => cache()->forget('dashboard.stats.global');

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Parent mother task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    /**
     * User who marked / submitted this subtask as completed.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * User who approved this subtask completion.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Uploaded files/attachments for this sub task.
     */
    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectFile::class, 'project_subtask_id')->orderByDesc('created_at');
    }
}
