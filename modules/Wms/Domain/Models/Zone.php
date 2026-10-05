<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Zona gudang: putaway, storage, pick, staging, quarantine, dock. */
class Zone extends Model
{
    protected $table = 'wms_zones';

    protected $fillable = ['warehouse_id', 'code', 'name', 'kind', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class, 'zone_id');
    }
}
