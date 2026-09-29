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
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    /**
     * Parent mother task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }
}
