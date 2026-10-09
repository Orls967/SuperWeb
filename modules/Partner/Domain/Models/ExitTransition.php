<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitTransition extends Model
{
    use HasUuids;

    protected $table = 'ptn_exit_transitions';

    protected $fillable = [
        'partner_id', 'reason', 'final_settlement_idr', 'asset_split_summary',
        'exit_date', 'status',
    ];

    protected $casts = [
        'final_settlement_idr' => 'integer',
        'exit_date' => 'date',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
