<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduHeadhunterFee extends Model
{
    protected $table = 'edu_headhunter_fees';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'placement_id',
        'fee_minor',
        'status',
        'held_at',
        'captured_at',
        'released_at',
        'idempotency_key',
    ];

    protected $casts = [
        'fee_minor' => 'integer',
        'held_at' => 'datetime',
        'captured_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(EduPlacement::class, 'placement_id', 'id');
    }
}
