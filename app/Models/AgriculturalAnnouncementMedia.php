<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgriculturalAnnouncementMedia extends Model
{
    protected $fillable = [
        'revision_id', 'storage_path', 'mime_type', 'file_size', 'alt_text', 'sort_order', 'is_cover', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'sort_order' => 'integer', 'is_cover' => 'boolean'];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(AgriculturalAnnouncementRevision::class, 'revision_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
