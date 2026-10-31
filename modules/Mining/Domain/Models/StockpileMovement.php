<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class StockpileMovement extends Model
{
    protected $table = 'min_stockpile_movements';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'movement_uuid',
        'site_id',
        'stockpile_id',
        'movement_type',
        'direction',
        'weight_kg',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'weight_kg' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('StockpileMovement is an append-only immutable audit fact.');
        });

        static::deleting(function () {
            throw new RuntimeException('StockpileMovement is an append-only immutable audit fact.');
        });
    }

    public function stockpile()
    {
        return $this->belongsTo(MiningStockpile::class, 'stockpile_id');
    }
}
