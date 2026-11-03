<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YardSlot extends Model
{
    protected $table = 'prt_yard_slots';

    protected $fillable = [
        'yard_block_id',
        'container_no',
        'slot_coordinate',
        'status',
        'gate_in_at',
        'gate_out_at',
    ];

    protected $casts = [
        'gate_in_at' => 'datetime',
        'gate_out_at' => 'datetime',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(YardBlock::class, 'yard_block_id');
    }
}
