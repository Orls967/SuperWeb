<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class WeighbridgeTicket extends Model
{
    protected $table = 'min_weighbridge_tickets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'ticket_uuid',
        'site_id',
        'pit_id',
        'area_id',
        'truck_id',
        'ticket_number',
        'truck_plate_number',
        'driver_party_id',
        'gross_weight_kg',
        'tare_weight_kg',
        'net_weight_kg',
        'grade_specs',
        'is_overload',
        'gross_weight_ton',
        'tare_weight_ton',
        'net_weight_ton',
        'nickel_grade_percentage',
        'destination_stockpile',
        'destination_stockpile_id',
        'hash',
        'previous_hash',
        'ticket_hash',
        'prev_hash',
        'weighed_at',
    ];

    protected $casts = [
        'gross_weight_kg' => 'integer',
        'tare_weight_kg' => 'integer',
        'net_weight_kg' => 'integer',
        'grade_specs' => 'array',
        'is_overload' => 'boolean',
        'gross_weight_ton' => 'decimal:2',
        'tare_weight_ton' => 'decimal:2',
        'net_weight_ton' => 'decimal:2',
        'nickel_grade_percentage' => 'decimal:2',
        'weighed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('WeighbridgeTicket is an append-only immutable audit fact.');
        });

        static::deleting(function () {
            throw new RuntimeException('WeighbridgeTicket is an append-only immutable audit fact.');
        });
    }

    public function site()
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function pit()
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }

    public function area()
    {
        return $this->belongsTo(MiningArea::class, 'area_id');
    }

    public function truck()
    {
        return $this->belongsTo(MiningEquipment::class, 'truck_id');
    }

    public function destinationStockpile()
    {
        return $this->belongsTo(MiningStockpile::class, 'destination_stockpile_id');
    }
}
