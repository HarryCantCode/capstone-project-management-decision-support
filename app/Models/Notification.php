<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification Model
 *
 * Stores in-app notifications for Admin and Manager users.
 * Supports read/unread state and archive functionality.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type          warranty_expiry | new_project | new_task
 * @property string $title
 * @property string $message
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The user this notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Only non-archived notifications.
     *
     * @param Builder<Notification> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Only unread notifications.
     *
     * @param Builder<Notification> $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Only archived notifications.
     *
     * @param Builder<Notification> $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    /**
     * Mark this notification as read.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark this notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Archive this notification.
     */
    public function archive(): void
    {
        $this->update([
            'archived_at' => now(),
            'read_at' => $this->read_at ?? now(),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Whether this notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Whether this notification is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Get the icon identifier for this notification type.
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'warranty_expiry' => 'shield',
            'new_project' => 'briefcase',
            'new_task' => 'clipboard',
            'expense_logged' => 'dollar-sign',
            'credential_reset', 'password_reset', 'email_reset' => 'key',
            'major_task_completed' => 'check-circle',
            'task_due_soon' => 'clock',
            'file_uploaded' => 'file-text',
            'manpower_assigned' => 'users',
            'new_material' => 'package',
            'low_stock' => 'alert-triangle',
            'staff_action_review' => 'clipboard-check',
            'staff_action_decision' => 'bell',
            default => 'bell',
        };
    }

    /**
     * Get a URL to navigate to from this notification.
     */
    public function getUrlAttribute(): ?string
    {
        $data = $this->data ?? [];

        if (!empty($data['url'])) {
            return $data['url'];
        }

        if (!empty($data['project_id'])) {
            return route('projects.show', $data['project_id']);
        }

        if (!empty($data['resource_id'])) {
            return route('resources.show', $data['resource_id']);
        }

        return null;
    }
}
