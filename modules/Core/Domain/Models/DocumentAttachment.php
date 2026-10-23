<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Contracts\DocumentAttachment as BaseDocumentAttachment;
use Modules\Shared\Domain\Traits\HasUuid;

class DocumentAttachment extends BaseDocumentAttachment
{
    use HasUuid;

    protected $table = 'core_documents';

    protected $fillable = [
        'uuid',
        'documentable_type',
        'documentable_id',
        'document_type',
        'original_filename',
        'stored_path',
        'disk',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'uploaded_by',
        'metadata',
        'retention_until',
        'is_archived',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'metadata' => 'array',
        'retention_until' => 'date',
        'is_archived' => 'boolean',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
