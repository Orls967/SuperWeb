<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataBackfillChunk extends Model
{
    use HasFactory;

    protected $table = 'plt_backfill_chunks';

    protected $fillable = [
        'backfill_id',
        'chunk_index',
        'range_start',
        'range_end',
        'records_processed',
        'status',
        'retry_count',
        'error_message',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'range_start' => 'integer',
        'range_end' => 'integer',
        'records_processed' => 'integer',
        'retry_count' => 'integer',
    ];

    public function backfill(): BelongsTo
    {
        return $this->belongsTo(DataBackfill::class, 'backfill_id');
    }
}
