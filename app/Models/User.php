<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'account_number',
        'job_title',
        'failed_login_attempts',
        'locked_until',
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
            if (empty($user->account_number)) {
                $user->account_number = static::generateAccountNumber();
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
     * Generate the next 7-digit account number.
     * Starts at 1000001 and increments sequentially.
     * Uses indexed scan instead of CAST table scan.
     */
    public static function generateAccountNumber(): string
    {
        $latest = static::withTrashed()
            ->whereNotNull('account_number')
            ->whereRaw('LENGTH(account_number) = 7')
            ->orderByDesc('account_number')
            ->value('account_number');

        if ($latest && is_numeric($latest)) {
            $next = ((int) $latest) + 1;
        } else {
            $next = 1000001;
        }

        return str_pad((string) $next, 7, '0', STR_PAD_LEFT);
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
            ->logOnly(['name', 'first_name', 'middle_name', 'last_name', 'birthdate', 'email', 'employee_number', 'account_number', 'job_title', 'two_factor_enabled'])
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
}
