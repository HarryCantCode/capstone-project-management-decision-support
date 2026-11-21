<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProjectCost Model
 *
 * Represents an individual cost/expense line item incurred for a project.
 */
class ProjectCost extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'project_costs';

    protected $fillable = [
        'project_id',
        'resource_id',
        'description',
        'cost_type',
        'amount',
        'quantity',
        'incurred_date',
        'invoice_path',
        'invoice_original_name',
        'invoice_size',
        'invoice_mime_type',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'integer',
            'invoice_size' => 'integer',
            'incurred_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    // ─── Accessors & Helpers ──────────────────────────────────────────────────

    public function getInvoiceUrlAttribute(): ?string
    {
        return !empty($this->invoice_path) ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->invoice_path) : null;
    }

    public function getHasInvoiceAttribute(): bool
    {
        return !empty($this->invoice_path);
    }

    public function getFormattedInvoiceSizeAttribute(): string
    {
        $bytes = (int) ($this->invoice_size ?? 0);
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes > 0 ? $bytes . ' B' : '—';
    }

    public function getIsInvoiceImageAttribute(): bool
    {
        $mime = strtolower((string) ($this->invoice_mime_type ?? ''));
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo((string) ($this->invoice_original_name ?? $this->invoice_path), PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    }

    public function getIsInvoicePdfAttribute(): bool
    {
        $mime = strtolower((string) ($this->invoice_mime_type ?? ''));

        return $mime === 'application/pdf' || strtolower(pathinfo((string) ($this->invoice_original_name ?? $this->invoice_path), PATHINFO_EXTENSION)) === 'pdf';
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
