<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adapter CK-01: jembatan central_kitchen → outlet Resto.
 * Data Resto tidak diduplikasi — hanya pointer + stempel sinkronisasi.
 */
class RestoAdapter extends Model
{
    protected $table = 'mfg_resto_adapters';

    protected $fillable = [
        'plant_id', 'outlet_id', 'adapter_type', 'status',
        'last_synced_at', 'last_sync_key',
    ];

    protected $casts = [
        'outlet_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }
}
