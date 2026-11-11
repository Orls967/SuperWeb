<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreRetentionRun extends Model
{
    protected $table = 'core_retention_runs';

    protected $fillable = [
        'retention_policy_id',
        'source_table',
        'archive_table',
        'chunk_start_ref',
        'chunk_end_ref',
        'rows_archived',
        'chunk_sha256',
        'status',
        'executed_at',
    ];

    protected $casts = [
        'rows_archived' => 'integer',
        'executed_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CoreRetentionPolicy::class, 'retention_policy_id');
    }

    public function drillRestores(): HasMany
    {
        return $this->hasMany(CoreArchiveDrillRestore::class, 'retention_run_id');
    }
}

