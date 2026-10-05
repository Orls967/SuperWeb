<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tugas gudang: putaway/pick/pack/stage/replenish (41.3). */
class Task extends Model
{
    protected $table = 'wms_tasks';

    protected $fillable = [
        'kind', 'status', 'priority', 'bin_id', 'product_id', 'lot_number', 'qty',
        'source_ref', 'wave_id', 'assigned_to_user_id', 'completed_at', 'created_by_user_id',
    ];

    protected $casts = [
        'bin_id' => 'integer', 'product_id' => 'integer', 'qty' => 'decimal:6',
        'wave_id' => 'integer', 'assigned_to_user_id' => 'integer',
        'completed_at' => 'datetime', 'created_by_user_id' => 'integer',
    ];

    public const KINDS = ['putaway', 'pick', 'pack', 'stage', 'replenish'];

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'bin_id');
    }
}
