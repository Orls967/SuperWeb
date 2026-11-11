<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreBulkImportError extends Model
{
    public $timestamps = false;

    protected $table = 'core_bulk_import_errors';

    protected $fillable = [
        'bulk_import_job_id',
        'row_number',
        'reason',
        'raw_row_json',
        'created_at',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'created_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(CoreBulkImportJob::class, 'bulk_import_job_id');
    }
}

