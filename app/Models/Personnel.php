<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Personnel Model
 *
 * Represents a field worker (site engineer, foreman, safety officer, worker)
 * who can be assigned to projects. These are NOT system login accounts.
 *
 * employee_id is auto-generated on creation in the format EMP-2026XXX.
 * Status (Assigned / Not Assigned) is derived from the project_id column.
 */
class Personnel extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var string */
    protected $table = 'personnel';

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'address',
        'expertise',
        'project_id',
        'date_assigned',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date_of_birth' => 'date',
        'date_assigned' => 'date',
    ];

    /** @var list<string> */
    protected $appends = ['full_name', 'status'];

    // ─── Boot: auto-generate employee_id ──────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Personnel $personnel) {
            if (empty($personnel->employee_id)) {
                $personnel->employee_id = static::generateEmployeeId();
            }
        });
    }

    /**
     * Generate the next employee ID in the format EMP-2026XXX.
     * Ensures uniqueness across BOTH personnel and users tables.
     */
    public static function generateEmployeeId(): string
    {
        return \App\Models\User::generateEmployeeNumber();
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Full name composed from first, middle, and last name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Status derived from project assignment.
     * Defensively verifies that the associated project exists and is not deleted.
     */
    public function getStatusAttribute(): string
    {
        if ($this->relationLoaded('project')) {
            return ($this->project_id && $this->project !== null) ? 'Assigned' : 'Not Assigned';
        }

        return ($this->project_id && $this->project()->exists()) ? 'Assigned' : 'Not Assigned';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The project this personnel is currently assigned to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The user who created this personnel record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * The user who last updated this personnel record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
