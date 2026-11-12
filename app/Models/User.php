<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * User Model
 *
 * Represents an authenticated user in the system.
 * Handles roles/permissions, activity logging, and two-factor authentication.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Role assignment is intentionally excluded from fillable — roles are
     * assigned explicitly via Spatie's givePermissionTo / assignRole, never
     * via a bulk fill that could be triggered by crafted form input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_enabled',
        'employee_number',
        'job_title',
        'failed_login_attempts',
        'locked_until',
        'must_change_password',
        'password_changed_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Boot model events for User.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->employee_number)) {
                $user->employee_number = static::generateEmployeeNumber();
            }
            if (empty($user->name) && (!empty($user->first_name) || !empty($user->last_name))) {
                $parts = array_filter([$user->first_name, $user->middle_name, $user->last_name]);
                $user->name = implode(' ', $parts);
            }
        });

        static::updating(function (User $user) {
            if (!empty($user->first_name) || !empty($user->last_name)) {
                $parts = array_filter([$user->first_name, $user->middle_name, $user->last_name]);
                $user->name = implode(' ', $parts);
            }
        });
    }

    /**
     * Generate the next employee number in the format EMP-2026XXX.
     * Ensures uniqueness across BOTH users and personnel (manpower) tables.
     * Uses index scans instead of table scans for ultra-fast performance.
     */
    public static function generateEmployeeNumber(): string
    {
        $year = date('Y');
        $prefix = "EMP-{$year}";

        $latestUser = static::withTrashed()
            ->where('employee_number', 'like', "{$prefix}%")
            ->orderByDesc('employee_number')
            ->value('employee_number');

        $latestPersonnel = \App\Models\Personnel::withTrashed()
            ->where('employee_id', 'like', "{$prefix}%")
            ->orderByDesc('employee_id')
            ->value('employee_id');

        $userNum = $latestUser ? (int) substr($latestUser, 4) : 0;
        $personnelNum = $latestPersonnel ? (int) substr($latestPersonnel, 4) : 0;

        $maxNum = max($userNum, $personnelNum);

        if ($maxNum > 0) {
            $next = $maxNum + 1;
        } else {
            $next = (int) ($year . '001');
        }

        return 'EMP-' . $next;
    }

    /**
     * Configure activity logging for this model.
     * Logs name and email changes for the audit trail.
     * Password changes are intentionally excluded from the log —
     * the hash must never appear in the activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'first_name', 'middle_name', 'last_name', 'birthdate', 'email', 'employee_number', 'job_title', 'two_factor_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Determine if the user account is currently temporarily locked out.
     */
    public function isLocked(): bool
    {
        return !empty($this->locked_until) && $this->locked_until->isFuture();
    }

    /**
     * Get remaining lockout duration in minutes (rounded up).
     */
    public function lockoutRemainingMinutes(): int
    {
        if (!$this->isLocked()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInSeconds($this->locked_until) / 60));
    }

    /**
     * Unlock the account and reset failed login attempts.
     */
    public function unlock(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    // ─── Audit relationships ──────────────────────────────────────────────────

    /**
     * The user who created this record.
     * Nullable because the first admin seeded has no creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who last updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * In-app notifications for this user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderByDesc('created_at');
    }

    /**
     * Staff pending actions submitted by this user.
     */
    public function pendingActions(): HasMany
    {
        return $this->hasMany(StaffPendingAction::class, 'user_id')->orderByDesc('created_at');
    }

    /**
     * Determine the landing route name or URL based on the user's role.
     * Prevents unauthorized role access (e.g. Staff hitting Admin/Manager dashboard).
     */
    public function defaultLandingRoute(): string
    {
        return $this->hasRole('Staff') ? route('resources.index') : route('dashboard');
    }
}
