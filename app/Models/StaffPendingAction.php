<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StaffPendingAction Model
 *
 * Represents an action initiated by a Staff member that requires Admin or Manager approval
 * (e.g. adding an expense, marking a task completion, or uploading a project file).
 *
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property string $action_type       add_expense | complete_subtask | add_file
 * @property array $payload
 * @property string $status            pending | approved | rejected
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $last_pinged_at
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StaffPendingAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'action_type',
        'payload',
        'status',
        'reviewed_by',
        'reviewed_at',
        'last_pinged_at',
        'admin_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reviewed_at' => 'datetime',
            'last_pinged_at' => 'datetime',
        ];
    }

    /**
     * Staff user who initiated the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Target project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * Admin or Manager user who reviewed/actioned this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * Human-readable label for the action type.
     */
    public function getActionTypeLabelAttribute(): string
    {
        return match ($this->action_type) {
            'complete_subtask' => 'Task Completion',
            'add_expense' => 'Expense Entry',
            'add_file' => 'File Upload',
            default => ucwords(str_replace('_', ' ', (string) $this->action_type)),
        };
    }

    /**
     * Human-readable label for the review status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Unexamined',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst((string) $this->status),
        };
    }

    /**
     * Primary summary title of the requested action.
     */
    public function getSummaryTitleAttribute(): string
    {
        $payload = $this->payload ?? [];

        return match ($this->action_type) {
            'complete_subtask' => (string) ($payload['subtask_title'] ?? 'Subtask Completion'),
            'add_expense' => (string) ($payload['description'] ?? 'Expense Entry'),
            'add_file' => (string) ($payload['file_name'] ?? 'Uploaded File'),
            default => 'Staff Request',
        };
    }

    /**
     * Secondary context subtitle of the requested action.
     */
    public function getSummarySubtitleAttribute(): string
    {
        $payload = $this->payload ?? [];

        return match ($this->action_type) {
            'complete_subtask' => 'Major Task: ' . ($payload['task_name'] ?? 'Project Timeline'),
            'add_expense' => 'Amount: ₱' . number_format((float) ($payload['amount'] ?? 0), 2) . ' · ' . ucfirst((string) ($payload['cost_type'] ?? 'General')),
            'add_file' => 'Type: ' . ucwords(str_replace('_', ' ', (string) ($payload['file_type'] ?? 'General'))) . (!empty($payload['subtask_title']) ? " · Subtask: {$payload['subtask_title']}" : ''),
            default => '',
        };
    }

    /**
     * Determine if this request can be pinged by the staff member.
     */
    public function isPingable(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if (! $this->last_pinged_at) {
            return true;
        }

        return $this->last_pinged_at->lte(now()->subMinutes(15));
    }

    /**
     * Number of seconds remaining before this request can be pinged again.
     */
    public function getCooldownSecondsRemainingAttribute(): int
    {
        if ($this->status !== 'pending' || ! $this->last_pinged_at) {
            return 0;
        }

        $secondsPassed = (int) $this->last_pinged_at->diffInSeconds(now());

        return max(0, 900 - $secondsPassed);
    }
}
