<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseworkAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\CourseworkAttachmentFactory> */
    use HasFactory;

    protected $fillable = ['coursework_revision_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size_bytes'];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(CourseworkRevision::class, 'coursework_revision_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
