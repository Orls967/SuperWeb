<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodCollection extends LogisticsEntity
{
    public const STATUS_COLLECTED = 'collected';

    public const STATUS_DEPOSITED = 'deposited';

    public const STATUS_SETTLED = 'settled';

    protected $table = 'lgx_cod_collections';

    protected $fillable = [
        'shipment_id',
        'shipper_id',
        'driver_id',
        'amount_idr',
        'fee_idr',
        'net_amount_idr',
        'status',
        'collected_at',
        'deposit_hub_id',
        'deposited_by',
        'deposited_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'fee_idr' => 'integer',
            'net_amount_idr' => 'integer',
            'collected_at' => 'datetime',
            'deposited_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function depositHub(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'deposit_hub_id');
    }
}
