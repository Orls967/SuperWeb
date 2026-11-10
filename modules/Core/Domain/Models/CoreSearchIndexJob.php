<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Model riwayat dan status pekerjaan indeks pencarian (State Machine 8 status).
 */
class CoreSearchIndexJob extends Model
{
    use HasUuids;

    protected $table = 'core_search_index_jobs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'job_type',
        'module',
        'status',
        'records_total',
        'records_processed',
        'error_message',
        'started_at',
        'completed_at',
        'payload',
    ];

    protected $casts = [
        'records_total' => 'integer',
        'records_processed' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'payload' => 'array',
    ];
}
