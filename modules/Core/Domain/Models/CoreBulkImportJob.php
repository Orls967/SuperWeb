<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreBulkImportJob extends Model
{
    protected $table = 'core_bulk_import_jobs';

    protected $fillable = [
        'tenant_id',
        'approval_id',
        'requested_by_user_id',
        'target_key',
        'source_filename',
        'file_path',
        'error_csv_path',
        'status',
        'total_rows',
        'valid_rows',
        'error_rows',
        'processed_rows',
        'last_processed_row',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'error_rows' => 'integer',
        'processed_rows' => 'integer',
        'last_processed_row' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CoreTenant::class, 'tenant_id', 'id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(CoreBulkImportError::class, 'bulk_import_job_id');
    }
}
