<?php

namespace Modules\Agri\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgriColdChainLog extends Model
{
    use HasUuids;

    protected $table = 'agri_cold_chain_logs';

    protected $fillable = [
        'batch_id',
        'reefer_truck_id',
        'recorded_at',
        'temperature_celsius',
        'humidity_percentage',
        'gps_coordinates',
        'cold_chain_status',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature_celsius' => 'decimal:2',
        'humidity_percentage' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AgriCollectionBatch::class, 'batch_id');
    }
}
