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
        ];
    }

    /**
     * Staff user who initiated the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Target project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Admin or Manager user who reviewed/actioned this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
