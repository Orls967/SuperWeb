<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreArchiveDrillRestore extends Model
{
    protected $table = 'core_archive_drill_restores';

    protected $fillable = [
        'retention_run_id',
        'source_table',
        'restored_rows',
        'expected_sha256',
        'actual_sha256',
        'matched',
        'restored_payload',
        'drilled_at',
    ];

    protected $casts = [
        'restored_rows' => 'integer',
        'matched' => 'boolean',
        'drilled_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(CoreRetentionRun::class, 'retention_run_id');
    }
}

