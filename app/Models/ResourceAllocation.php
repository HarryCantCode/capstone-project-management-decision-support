<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ResourceAllocation Model
 *
 * Represents the assignment of a specific resource to a project,
 * along with the allocated quantity and optional notes.
 */
class ResourceAllocation extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'resource_id',
        'quantity',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the project that this resource is allocated to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the actual resource being allocated.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get the user who created this allocation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
