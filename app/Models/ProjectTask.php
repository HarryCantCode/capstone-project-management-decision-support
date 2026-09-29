<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProjectTask — represents a Mother Task covering a specific week or duration.
 *
 * @property int $id
 * @property int $project_id
 * @property string $task_name
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string|null $description
 * @property string $status
 * @property int $sort_order
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ProjectTask extends Model
{
    use HasFactory;

    protected $table = 'project_tasks';

    protected $fillable = [
        'project_id',
        'task_name',
        'start_date',
        'end_date',
        'description',
        'status',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sort_order' => 'integer',
    ];

    /**
     * Parent project for this mother task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Baby tasks / specific subtasks inside this mother task.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(ProjectSubtask::class, 'project_task_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * User who created this task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compute the progress percentage based on completed baby tasks.
     * If there are no baby tasks, defaults to 100% if status is completed, else 0%.
     */
    public function getProgressPercentageAttribute(): int
    {
        $total = $this->subtasks->count();
        if ($total === 0) {
            return $this->status === 'completed' ? 100 : 0;
        }

        $completed = $this->subtasks->where('is_completed', true)->count();

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Dynamic status derived from subtasks or dates:
     * - completed: 100% of baby tasks done (or manually marked completed)
     * - in_progress: at least 1 baby task completed, or current date is within start/end range
     * - pending: not started yet
     */
    public function getComputedStatusAttribute(): string
    {
        $total = $this->subtasks->count();
        if ($total > 0) {
            $completed = $this->subtasks->where('is_completed', true)->count();
            if ($completed === $total) {
                return 'completed';
            }
            if ($completed > 0) {
                return 'in_progress';
            }
        } elseif ($this->status === 'completed') {
            return 'completed';
        }

        $today = now()->startOfDay();
        $start = $this->start_date ? Carbon::parse($this->start_date)->startOfDay() : null;
        $end = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : null;

        if ($start && $today->gte($start)) {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * Duration in days (inclusive).
     */
    public function getDurationDaysAttribute(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return 7;
        }

        return (int) (Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1);
    }
}
