<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Resource Model
 *
 * Represents an assignable resource (e.g., equipment, personnel, materials)
 * that can be allocated to various projects.
 */
class Resource extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'resource_code',
        'type',
        'quantity_available',
        'condition',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the user who created this resource.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this resource.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the allocations associated with this resource.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }
}
