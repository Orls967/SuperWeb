<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpaSettlement extends Model
{
    use HasUuids;

    protected $table = 'egy_ppa_settlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'settlement_number',
        'ppa_contract_id',
        'period',
        'delivered_kwh',
        'rate_per_kwh_idr',
        'total_payout_idr',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'delivered_kwh' => 'integer',
        'rate_per_kwh_idr' => 'integer',
        'total_payout_idr' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(PpaContract::class, 'ppa_contract_id');
    }
}
