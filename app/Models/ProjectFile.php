<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ProjectFile — represents an uploaded attachment (PDF, Docx, Image) for a project or specific subtask.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $project_subtask_id
 * @property int|null $uploaded_by
 * @property string $file_type invoice, task_attachment, general
 * @property string $file_name
 * @property string $file_path
 * @property string|null $mime_type
 * @property int $file_size
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ProjectFile extends Model
{
    use HasFactory;

    public const TYPE_PROJECT_DOCUMENT = 'project_document';
    public const TYPE_PROJECT_TRANSACTION = 'project_transaction';
    public const TYPE_PROJECT_INVOICE = 'invoice';
    public const TYPE_PROJECT_NOTES = 'project_notes';
    public const TYPE_PROJECT_DRAWING = 'project_drawing';
    public const TYPE_TASK_ATTACHMENT = 'task_attachment';
    public const TYPE_GENERAL = 'general';

    public const CATEGORY_LABELS = [
        self::TYPE_PROJECT_DOCUMENT => 'Project Document',
        self::TYPE_PROJECT_TRANSACTION => 'Project Transaction',
        self::TYPE_PROJECT_INVOICE => 'Project Invoice',
        self::TYPE_PROJECT_NOTES => 'Project Notes',
        self::TYPE_PROJECT_DRAWING => 'Project Drawing / Blueprint',
        self::TYPE_TASK_ATTACHMENT => 'Task Attachment',
        self::TYPE_GENERAL => 'General Document',
    ];

    protected $table = 'project_files';

    protected $fillable = [
        'project_id',
        'project_subtask_id',
        'uploaded_by',
        'file_type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(ProjectSubtask::class, 'project_subtask_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get human-friendly label for the file category/type.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->file_type] ?? ucfirst(str_replace('_', ' ', (string) $this->file_type));
    }

    /**
     * Get formatted file size string (e.g., 2.5 MB, 450 KB).
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Check if file is an image.
     */
    public function getIsImageAttribute(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    }

    /**
     * Check if file is a PDF.
     */
    public function getIsPdfAttribute(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return $mime === 'application/pdf' || strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Check if file is a Word document.
     */
    public function getIsWordAttribute(): bool
    {
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));

        return in_array($extension, ['doc', 'docx']);
    }

    /**
     * Get public URL of file if stored in public disk.
     */
    public function getDownloadUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
