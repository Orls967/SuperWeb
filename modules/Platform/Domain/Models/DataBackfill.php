<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataBackfill extends Model
{
    use HasFactory;

    protected $table = 'plt_backfills';

    protected $fillable = [
        'job_name',
        'target_table',
        'total_records',
        'chunk_size',
        'status',
        'cursor_pointer',
        'metadata',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'chunk_size' => 'integer',
        'cursor_pointer' => 'integer',
        'metadata' => 'array',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DataBackfillChunk::class, 'backfill_id');
    }
}
