<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreBulkExportJob extends Model
{
    protected $table = 'core_bulk_export_jobs';

    protected $fillable = [
        'tenant_id',
        'requested_by_user_id',
        'target_key',
        'status',
        'total_rows',
        'zip_path',
        'file_sha256',
        'expires_at',
        'purged_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'expires_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id', 'id');
    }
}

